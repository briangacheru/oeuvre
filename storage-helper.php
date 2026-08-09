<?php
/**
 * Switchable storage backend for task attachments (tbl_task_files) - the
 * only upload type this app ever put on DigitalOcean Spaces (chat, profile
 * photos, to-dos, and project attachments have always been local-disk only
 * and are unaffected by this).
 *
 * get_storage_provider() reads the admin's choice from tbl_storage_settings
 * ('digitalocean' or 'cpanel', see sudo/settings.php).
 *
 * This file deliberately does NOT wrap SpacesHelper itself. There are two
 * separate SpacesHelper classes in this repo (root spaces-helper.php and
 * sudo/spaces-helper.php) with different filename-uniquification behavior
 * (sudo's appends its own random suffix inside uploadFile(); root's assumes
 * the caller already made the name unique) - collapsing both into one call
 * here would silently change one of them. Existing upload/delete call sites
 * keep using their own local SpacesHelper exactly as before; only the new
 * 'cpanel' branch is added inline at each call site, using
 * storage_upload_file_local()/storage_delete_file_local() below. Both
 * providers store a full absolute URL in tbl_task_files.file_url, matching
 * what was already true for every Spaces-era row, so nothing downstream
 * that reads file_url as a direct href/src needs to change.
 *
 * migrate_task_files() is the "keep old links working" half: when an admin
 * switches provider in sudo/settings.php, it copies every task file that's
 * still sitting on the *other* backend over to the new one and repoints its
 * DB row - copies, never deletes the source, so a mid-migration failure or
 * a change of mind never loses a file. It uses root's spaces-helper.php
 * (the "name is already unique" variant) since tbl_task_files.file_name is
 * already the unique on-disk name - migration must preserve it exactly,
 * not run it through sudo's re-uniquification.
 *
 * Include with require_once __DIR__.'/storage-helper.php' from root files,
 * require_once __DIR__.'/../storage-helper.php' from sudo/.
 */
require_once __DIR__ . '/env.php';

if (!function_exists('storage_spaces_reachable')) {
    // Cheap pre-flight check before letting an admin switch NEW uploads to
    // DigitalOcean: are the required SPACES_* vars even configured? This
    // catches the common case (a cPanel-only install that never set up
    // Spaces) but can't catch credentials that are present but wrong/
    // revoked - migrate_task_files() still guards that case separately by
    // catching the exception if the first real API call fails.
    function storage_spaces_reachable() {
        return env('SPACES_BUCKET') && env('SPACES_KEY') && env('SPACES_SECRET');
    }
}

if (!function_exists('get_storage_provider')) {
    function get_storage_provider($con) {
        $result = @mysqli_query($con, "SELECT provider FROM tbl_storage_settings WHERE id = 1 LIMIT 1");
        $row = $result ? mysqli_fetch_assoc($result) : null;
        $provider = $row['provider'] ?? 'digitalocean';
        return in_array($provider, ['digitalocean', 'cpanel'], true) ? $provider : 'digitalocean';
    }
}

if (!function_exists('storage_local_url')) {
    // $key already carries its full project-root-relative path (callers
    // pass folder='taskfiles' or 'taskfiles/submissions', same convention
    // as the Spaces object-key prefix) - don't add another 'taskfiles/' on
    // top of it here.
    function storage_local_url($key) {
        return rtrim(env('APP_URL'), '/') . '/' . ltrim($key, '/');
    }
}

if (!function_exists('storage_upload_file_local')) {
    /**
     * @param string $filePath  Local temp path of the file to store.
     * @param string $fileName  Destination filename (caller-sanitized/already unique).
     * @param string $folder    Sub-path under the project root, e.g. 'taskfiles/submissions' -
     *                          matches the Spaces object-key prefix convention.
     * @return array{success: bool, url?: string, key?: string, message?: string}
     */
    function storage_upload_file_local($filePath, $fileName, $folder = '') {
        $relativeDir = trim($folder, '/');
        $targetDir = rtrim(__DIR__ . '/' . $relativeDir, '/');

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return ['success' => false, 'message' => "Could not create upload directory: $relativeDir"];
        }

        $destination = $targetDir . '/' . $fileName;
        if (!copy($filePath, $destination)) {
            return ['success' => false, 'message' => 'Could not write file to local storage.'];
        }

        $key = ($relativeDir !== '' ? $relativeDir . '/' : '') . $fileName;
        return ['success' => true, 'url' => storage_local_url($key), 'key' => $key];
    }
}

if (!function_exists('storage_delete_file_local')) {
    function storage_delete_file_local($key) {
        $root = realpath(__DIR__);
        $target = realpath(__DIR__ . '/' . ltrim($key, '/'));
        // Refuse to delete anything outside the project root - a $key
        // containing '../' should never let this walk up past it.
        if ($target === false || strpos($target, $root) !== 0) {
            return ['success' => false, 'message' => 'Invalid file path.'];
        }

        if (!file_exists($target)) {
            return ['success' => true]; // already gone - not an error
        }

        return unlink($target)
            ? ['success' => true]
            : ['success' => false, 'message' => 'Could not delete local file.'];
    }
}

if (!function_exists('storage_download_to_temp')) {
    // Same technique submission_upload.php/sudo/submit-task.php already use
    // to pull Spaces-hosted attachments down for emailing.
    function storage_download_to_temp($url, $localPath) {
        $ch = curl_init(str_replace(' ', '%20', $url));
        $fp = fopen($localPath, 'wb');
        if ($fp === false) {
            return false;
        }

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fp);
        curl_close($ch);

        if ($success === false || $httpCode >= 400) {
            @unlink($localPath);
            return false;
        }

        return true;
    }
}

if (!function_exists('storage_url_is_on_provider')) {
    // Which backend is a given (already-stored) file_url currently sitting
    // on? Spaces URLs always contain the configured Spaces host (or CDN
    // host, if one's set) - anything else stored by this app is a local
    // APP_URL-relative link.
    function storage_url_is_on_provider($url, $provider) {
        $spacesHost = parse_url(env('SPACES_CDN_ENDPOINT') ?: env('SPACES_ENDPOINT', ''), PHP_URL_HOST);
        $isSpacesUrl = $spacesHost && stripos($url, $spacesHost) !== false;
        // Fallback in case SPACES_ENDPOINT isn't the exact bucket subdomain
        // used in stored URLs (bucket.region.digitaloceanspaces.com).
        $isSpacesUrl = $isSpacesUrl || stripos($url, 'digitaloceanspaces.com') !== false;

        return $provider === 'digitalocean' ? $isSpacesUrl : !$isSpacesUrl;
    }
}

if (!function_exists('migrate_task_files')) {
    /**
     * Copies every non-deleted task file that's still on the OTHER backend
     * over to whatever tbl_storage_settings currently says (so call this
     * AFTER writing the new provider choice to the DB) and repoints its
     * tbl_task_files row. Never deletes the source copy. Best-effort per
     * file - one failure doesn't stop the batch.
     *
     * @return array{total: int, migrated: int, skipped: int, failed: int, error?: string}
     */
    function migrate_task_files($con) {
        require_once __DIR__ . '/spaces-helper.php';

        $summary = ['total' => 0, 'migrated' => 0, 'skipped' => 0, 'failed' => 0];
        $toProvider = get_storage_provider($con);

        $spacesHelper = null;
        if ($toProvider === 'digitalocean') {
            try {
                $spacesHelper = new SpacesHelper();
            } catch (\Throwable $e) {
                // Bad/missing SPACES_* credentials - nothing can be migrated
                // to Spaces. Leave every file where it is rather than crash,
                // but flag it so the caller doesn't report "0 to copy" as
                // if that meant there was simply nothing to do.
                error_log('migrate_task_files: could not initialize SpacesHelper - ' . $e->getMessage());
                $summary['error'] = 'Could not connect to DigitalOcean Spaces - check the SPACES_* settings in .env.';
                return $summary;
            }
        }

        $result = mysqli_query($con, "SELECT id, file_url, file_name FROM tbl_task_files WHERE is_deleted = 0 AND file_url IS NOT NULL AND file_url != ''");
        if (!$result) {
            return $summary;
        }

        set_time_limit(0);
        $tempDir = sys_get_temp_dir();

        while ($row = mysqli_fetch_assoc($result)) {
            $summary['total']++;

            if (storage_url_is_on_provider($row['file_url'], $toProvider)) {
                $summary['skipped']++; // already on the target backend
                continue;
            }

            $tempFile = $tempDir . '/' . uniqid('storage_migrate_') . '_' . basename($row['file_name'] ?: 'file');
            if (!storage_download_to_temp($row['file_url'], $tempFile)) {
                error_log("migrate_task_files: failed to download tbl_task_files.id={$row['id']} from {$row['file_url']}");
                $summary['failed']++;
                continue;
            }

            $fileName = basename($row['file_name'] ?: uniqid('file'));
            try {
                $uploadResult = $spacesHelper
                    ? $spacesHelper->uploadFile($tempFile, $fileName, 'taskfiles')
                    : storage_upload_file_local($tempFile, $fileName, 'taskfiles');
            } catch (\Throwable $e) {
                $uploadResult = ['success' => false, 'message' => $e->getMessage()];
            }
            @unlink($tempFile);

            if (empty($uploadResult['success'])) {
                error_log("migrate_task_files: failed to upload tbl_task_files.id={$row['id']} to $toProvider - " . ($uploadResult['message'] ?? 'unknown error'));
                $summary['failed']++;
                continue;
            }

            $updateStmt = mysqli_prepare($con, "UPDATE tbl_task_files SET file_url = ?, file_path = ? WHERE id = ?");
            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, 'ssi', $uploadResult['url'], $uploadResult['key'], $row['id']);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
            }

            $summary['migrated']++;
        }

        return $summary;
    }
}
