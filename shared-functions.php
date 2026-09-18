<?php
/**
 * Shared helper functions used by BOTH the writer (root) and administrator (sudo)
 * interfaces. These are the functions that were byte-identical in functions.php and
 * sudo/functions.php. Interface-specific helpers (email_exists, username_exists, etc.)
 * remain in each interface's own functions file.
 */

if (!function_exists('display_alert')) {
function display_alert() {
    if(isset($_SESSION['alert'])) {
        echo $_SESSION['alert'];
        unset($_SESSION['alert']); // Clear the alert after displaying it
    }
}
}

if (!function_exists('display_message')) {
function display_message() {
    if (isset($_SESSION['message']) && !empty($_SESSION['message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
        <p class="mb-0 flex-1"><strong>Error: </strong>' . $_SESSION['message'] . '</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
        unset($_SESSION['message']);
    }
}
}

if (!function_exists('display_subAlert')) {
function display_subAlert() {
    if(isset($_SESSION['subAlert'])) {
        echo $_SESSION['subAlert'];
        unset($_SESSION['subAlert']); // Clear the alert after displaying it
    }
}
}

if (!function_exists('logged_in')) {
function logged_in(){
    if(isset($_SESSION['userSession']) || isset($_COOKIE['email'])){
        return true;
    } else {
        return false;
    }
}
}

if (!function_exists('redirect')) {
function redirect($location){
    return header("Location: {$location}");
}
}

if (!function_exists('set_alert')) {
function set_alert($alert) {
    if(!empty($alert)) {
        $_SESSION['alert'] = $alert;
    } else {
        $alert = "";
    }
}
}

if (!function_exists('set_message')) {
function set_message($message)
{
    if(!empty($message)){
        $_SESSION['message'] = $message;
    }else {
        $message = "";
    }
}
}

if (!function_exists('set_subAlert')) {
function set_subAlert($subAlert) {
    if(!empty($subAlert)) {
        $_SESSION['subAlert'] = $subAlert;
    } else {
        $subAlert = "";
    }
}
}

if (!function_exists('timeAgo')) {
function timeAgo($datetime, $isUtc = false)
{
    $commentTime = $isUtc ? new DateTime($datetime, new DateTimeZone('UTC')) : new DateTime($datetime);
    $now = new DateTime();
    $interval = $now->diff($commentTime);

    if ($interval->y > 0) {
        return $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
    } elseif ($interval->m > 0) {
        return $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
    } elseif ($interval->d > 0) {
        return $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
    } elseif ($interval->h > 0) {
        return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
    } elseif ($interval->i > 0) {
        return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}
}

if (!function_exists('validation_errors')) {
function validation_errors($error_message)
{
    $error_message = <<<DELIMITER

<div class="alert alert-danger text-center" role="alert">
  	<strong>Warning!</strong> $error_message
 </div>
DELIMITER;

    set_message($error_message);
}
}


// ---- Consolidated duplicated helpers (identical across interfaces) ----
if (!function_exists('sanitizeFileName')) {
function sanitizeFileName($fileName) {
    // Replace problematic characters with underscores (excluding space)
    $fileName = str_replace(['#', '?', '&', '%', '+', '='], '_', $fileName);
    // Remove any remaining special characters except dots, hyphens, underscores, and spaces
    $fileName = preg_replace('/[^a-zA-Z0-9._\s-]/', '_', $fileName);
    // Remove multiple consecutive underscores
    $fileName = preg_replace('/_+/', '_', $fileName);
    // Remove leading/trailing underscores
    $fileName = trim($fileName, '_');
    return $fileName;
}
}

if (!function_exists('getUploadErrorMessage')) {
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File is too large';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}
}

if (!function_exists('validateChatAttachment')) {
    // Shared allow-list for chat attachments (both interfaces): office docs,
    // zip, pdf, and photos (including formats getimagesize()/finfo commonly
    // misreport, like heic/avif - hence the extra fallback mime entries).
    // Only validates - does not move/store the file, so each caller can keep
    // its own upload-directory/filename logic.
    function validateChatAttachment($file, $maxBytes = 52428800) { // 50MB
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            return ['success' => false, 'message' => 'File upload error: ' . getUploadErrorMessage($code)];
        }

        if ($file['size'] > $maxBytes) {
            return ['success' => false, 'message' => 'File size exceeds the 50MB limit'];
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        } else {
            $mimeType = mime_content_type($file['tmp_name']);
        }

        $allowedExtensions = [
            // Documents
            'pdf'  => ['application/pdf'],
            'doc'  => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls'  => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'ppt'  => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'zip'  => ['application/zip', 'application/x-zip-compressed'],
            // Photos
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
            'heic' => ['image/heic', 'image/heif', 'application/octet-stream'],
            'heif' => ['image/heif', 'image/heic', 'application/octet-stream'],
            'avif' => ['image/avif', 'application/octet-stream'],
            'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
            'tiff' => ['image/tiff'],
            'tif'  => ['image/tiff'],
        ];

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!array_key_exists($extension, $allowedExtensions)) {
            return ['success' => false, 'message' => 'File type not allowed. Allowed: Word, Excel, PowerPoint, ZIP, PDF, and photos.'];
        }

        if (!in_array($mimeType, $allowedExtensions[$extension], true)) {
            return ['success' => false, 'message' => 'File content does not match its extension'];
        }

        // getimagesize()/embedded-script sniffing only applies to the formats
        // it can actually parse - heic/avif aren't reliably supported here.
        $knownImageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/x-ms-bmp', 'image/tiff'];
        if (in_array($mimeType, $knownImageMimes, true)) {
            if (getimagesize($file['tmp_name']) === false) {
                return ['success' => false, 'message' => 'Invalid image file'];
            }
            $sample = file_get_contents($file['tmp_name'], false, null, 0, 1024);
            foreach (['<?php', '<?', '<script', 'javascript:', 'vbscript:'] as $pattern) {
                if (stripos($sample, $pattern) !== false) {
                    return ['success' => false, 'message' => 'File contains suspicious content'];
                }
            }
        }

        return ['success' => true, 'extension' => $extension, 'mimeType' => $mimeType];
    }
}

if (!function_exists('validatePassword')) {
function validatePassword($password) {
    // Minimum eight characters, at least one uppercase letter, one lowercase letter, and one number
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{8,}$/';

    return preg_match($pattern, $password);
}
}

if (!function_exists('formatSizeUnits')) {
function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }
    return $bytes;
}
}

if (!function_exists('utcToNairobiTimestamp')) {
    // chat_messages.timestamp (and last_seen) are stored in UTC. A bare
    // strtotime() on that string is parsed using PHP's default timezone
    // (Africa/Nairobi, set in check-login.php), throwing it off by the
    // UTC+3 offset. Parsing as UTC explicitly gives the correct absolute
    // Unix timestamp - date()/strtotime()-style formatting from there
    // already renders in Africa/Nairobi via the process default timezone.
    function utcToNairobiTimestamp($mysqlDatetime) {
        if (!$mysqlDatetime || $mysqlDatetime === '0000-00-00 00:00:00') {
            return false;
        }
        return (new DateTime($mysqlDatetime, new DateTimeZone('UTC')))->getTimestamp();
    }
}

if (!function_exists('isRecentlyOnline')) {
    // Presence is derived from last_seen rather than the is_online DB column,
    // since is_online only flips back to 0 on an explicit logout - a closed
    // tab, crashed browser, or dead session leaves it stuck at 1 forever.
    // last_seen gets refreshed by check-login.php on every authenticated
    // request, including the ~30s background poll in admin-task-notification.js,
    // so a short threshold reliably reflects an actively open session.
    //
    // last_seen is stored in UTC (MySQL's NOW() reflects the DB server's own
    // timezone, not PHP's date_default_timezone_set('Africa/Nairobi')), so it
    // must be parsed as UTC explicitly rather than with a bare strtotime(),
    // which would interpret the string in the process's default timezone and
    // throw the comparison off by the Nairobi UTC+3 offset.
    function isRecentlyOnline($lastSeen, $thresholdSeconds = 120) {
        if (!$lastSeen || $lastSeen === '0000-00-00 00:00:00') {
            return false;
        }
        $lastSeenTimestamp = (new DateTime($lastSeen, new DateTimeZone('UTC')))->getTimestamp();
        return (time() - $lastSeenTimestamp) <= $thresholdSeconds;
    }
}

if (!function_exists('getLastSeenText')) {
    // Mirrors the relative "last seen" wording used on sudo/writer.php.
    // See isRecentlyOnline() above for why last_seen must be parsed as UTC.
    function getLastSeenText($lastSeen) {
        if (!$lastSeen || $lastSeen === '0000-00-00 00:00:00') {
            return 'Offline';
        }

        $lastSeenDt = new DateTime($lastSeen, new DateTimeZone('UTC'));
        $lastSeenDt->setTimezone(new DateTimeZone('Africa/Nairobi'));
        $now = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
        $diff = $now->diff($lastSeenDt);

        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        } elseif ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        } elseif ($diff->days >= 7) {
            $weeks = floor($diff->days / 7);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        } elseif ($diff->days > 0) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'Just now';
        }
    }
}

if (!function_exists('getPresenceStatusClass')) {
    // Presence indicator tier for the chat contact avatar dot. status-online
    // comes from isRecentlyOnline(); the rest bucket by how long ago
    // last_seen was, from "seen today" down to "seen a month+ ago". See
    // isRecentlyOnline() above for why last_seen must be parsed as UTC.
    function getPresenceStatusClass($isOnline, $lastSeen) {
        if ($isOnline) {
            return 'status-online';
        }
        if (!$lastSeen || $lastSeen === '0000-00-00 00:00:00') {
            return 'status-year';
        }

        $lastSeenDt = new DateTime($lastSeen, new DateTimeZone('UTC'));
        $lastSeenDt->setTimezone(new DateTimeZone('Africa/Nairobi'));
        $now = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
        $daysAgo = $now->diff($lastSeenDt)->days;

        if ($daysAgo < 1) {
            return 'status-day';
        } elseif ($daysAgo < 7) {
            return 'status-week';
        } elseif ($daysAgo < 14) {
            return 'status-fortnight';
        } elseif ($daysAgo < 30) {
            return 'status-month';
        } else {
            return 'status-year';
        }
    }
}


// ---- CSRF protection ----
// A single per-session token is generated once and reused for every form
// rendered during that session, so multiple open tabs/forms all validate.
if (!function_exists('csrf_token')) {
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
}

if (!function_exists('csrf_field')) {
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
}

if (!function_exists('csrf_verify')) {
function csrf_verify() {
    return isset($_POST['csrf_token'])
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}
}

if (!function_exists('csrf_verify_or_redirect')) {
    // For traditional HTML form pages that render a Bootstrap alert via
    // $_SESSION['alert'] and redirect back to themselves after a POST.
    function csrf_verify_or_redirect() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
            $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
                <p class="mb-0 flex-1">Your request could not be verified (invalid or expired security token). Please try again.</p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
            header('Location: ' . basename($_SERVER['PHP_SELF']));
            exit;
        }
    }
}

if (!function_exists('csrf_verify_or_json_die')) {
    // For AJAX/JSON endpoints.
    function csrf_verify_or_json_die() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid or expired security token. Please refresh and try again.']);
            exit;
        }
    }
}

// ---- Entity id encoding ----
// Database ids are never put in URLs as plain integers or as plain base64 (which
// is trivially reversible client-side via atob()). Instead they're encrypted with
// a per-install secret (TASK_ID_KEY in .env) using AES-256-GCM, so the token can't
// be decoded or forged without that key. Each entity type (task, writer, project,
// ...) is bound into the ciphertext as AEAD "additional data", so a token minted
// for one entity type fails authentication (and is rejected) if presented as a
// token for a different entity type — even though every entity shares one key and
// one raw integer id space. decode_*_id() also accepts the old plain-base64
// format so links already emailed/shared before this change keep working (that
// legacy format predates this per-entity binding, so it can't be retroactively
// checked — a stale legacy token for the "wrong" entity was already ambiguous
// before this change and remains so only until it's replaced by a fresh token).
//
// encode_*_id()/decode_*_id() are the ONLY functions that should ever touch an id
// destined for a URL — never call base64_encode()/base64_decode() on an id
// directly. Add a new pair (thin wrappers around encode_entity_id/decode_entity_id
// below, following encode_task_id/decode_task_id as the template) for any new
// entity type before putting its id in a URL.

if (!function_exists('id_codec_secret_key')) {
    function id_codec_secret_key() {
        static $key = false; // false = not resolved yet, null = resolved but missing/invalid
        if ($key === false) {
            $hex = function_exists('env') ? env('TASK_ID_KEY', '') : '';
            $key = (is_string($hex) && strlen($hex) === 64 && ctype_xdigit($hex)) ? hex2bin($hex) : null;
            if ($key === null) {
                error_log('TASK_ID_KEY is missing or invalid in .env — id links are falling back to insecure legacy encoding.');
            }
        }
        return $key;
    }
}

if (!function_exists('encode_entity_id')) {
    function encode_entity_id($id, $context) {
        $plain = (string) (int) $id;
        $key = id_codec_secret_key();
        if ($key === null) {
            return base64_encode($plain); // legacy fallback if not configured
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $context);
        if ($ciphertext === false) {
            return base64_encode($plain);
        }

        $payload = $iv . $tag . $ciphertext;
        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }
}

if (!function_exists('decode_entity_id')) {
    // Returns the decoded id as an int, or 0 if $encoded is missing/invalid/
    // tampered with/for a different entity type — matching the historical
    // behavior of (int) base64_decode(...), so callers that check
    // `is_numeric($id) && !empty($id)` or rely on a WHERE id = 0 query returning
    // no rows keep working unchanged.
    function decode_entity_id($encoded, $context) {
        if (!is_string($encoded) || $encoded === '') {
            return 0;
        }

        $key = id_codec_secret_key();
        if ($key !== null) {
            $b64 = strtr($encoded, '-_', '+/');
            $pad = strlen($b64) % 4;
            if ($pad) {
                $b64 .= str_repeat('=', 4 - $pad);
            }
            $payload = base64_decode($b64, true);
            if ($payload !== false && strlen($payload) > 28) {
                $iv         = substr($payload, 0, 12);
                $tag        = substr($payload, 12, 16);
                $ciphertext = substr($payload, 28);
                $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $context);
                if ($plain !== false && ctype_digit($plain)) {
                    return (int) $plain;
                }
            }
        }

        // Legacy fallback — plain base64(id) links issued before this change.
        $legacy = base64_decode($encoded, true);
        if ($legacy !== false && ctype_digit($legacy)) {
            return (int) $legacy;
        }

        return 0;
    }
}

if (!function_exists('encode_task_id')) {
    function encode_task_id($id) { return encode_entity_id($id, 'task'); }
}
if (!function_exists('decode_task_id')) {
    function decode_task_id($encoded) { return decode_entity_id($encoded, 'task'); }
}

if (!function_exists('encode_writer_id')) {
    function encode_writer_id($id) { return encode_entity_id($id, 'writer'); }
}
if (!function_exists('decode_writer_id')) {
    function decode_writer_id($encoded) { return decode_entity_id($encoded, 'writer'); }
}

if (!function_exists('encode_project_id')) {
    function encode_project_id($id) { return encode_entity_id($id, 'project'); }
}
if (!function_exists('decode_project_id')) {
    function decode_project_id($encoded) { return decode_entity_id($encoded, 'project'); }
}

if (!function_exists('encode_overdraft_id')) {
    function encode_overdraft_id($id) { return encode_entity_id($id, 'overdraft'); }
}
if (!function_exists('decode_overdraft_id')) {
    function decode_overdraft_id($encoded) { return decode_entity_id($encoded, 'overdraft'); }
}

if (!function_exists('encode_invoice_log_id')) {
    function encode_invoice_log_id($id) { return encode_entity_id($id, 'invoice_log'); }
}
if (!function_exists('decode_invoice_log_id')) {
    function decode_invoice_log_id($encoded) { return decode_entity_id($encoded, 'invoice_log'); }
}

if (!function_exists('encode_message_id')) {
    function encode_message_id($id) { return encode_entity_id($id, 'message'); }
}
if (!function_exists('decode_message_id')) {
    function decode_message_id($encoded) { return decode_entity_id($encoded, 'message'); }
}

if (!function_exists('resolve_shared_task_redirect')) {
    // Given an encoded task id (as passed via a shared task link's ?task_id=
    // param) and a writer's email, returns the URL to send them to: the task itself
    // if they have access, or 'all-tasks' (with an access-denied alert queued in
    // $_SESSION['alert']) if they don't. Returns null if $encodedTaskId is empty.
    function resolve_shared_task_redirect($con, $email, $encodedTaskId) {
        if (empty($encodedTaskId)) {
            return null;
        }

        $taskId = decode_task_id($encodedTaskId);
        $stmt = $con->prepare("SELECT id FROM tbltasks WHERE id = ? AND email = ?");
        $stmt->bind_param("is", $taskId, $email);
        $stmt->execute();
        $hasAccess = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($hasAccess) {
            return 'view-task?task_id=' . urlencode($encodedTaskId);
        }

        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
                                <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
                                <p class="mb-0 flex-1">You do not have access to that task.</p>
                                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
        return 'all-tasks';
    }
}

// ---- Login lockout ----
// $table must always be a hardcoded literal ('tblwriters' or 'tbladmin')
// supplied by the calling code, never derived from request input.

if (!function_exists('account_lock_status')) {
    // Returns the locked_until timestamp (string) if the account is
    // currently locked, or null if not locked (or lock has expired).
    function account_lock_status($con, $table, $email) {
        $stmt = $con->prepare("SELECT locked_until FROM `$table` WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && !empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
            return $row['locked_until'];
        }
        return null;
    }
}

if (!function_exists('register_failed_login')) {
    // Increments the failed-attempt counter for $email in $table. Once it
    // reaches LOGIN_MAX_ATTEMPTS, locks the account for LOGIN_LOCKOUT_HOURS
    // and resets the counter. Returns the new locked_until timestamp if the
    // account just became locked, or null if it's just a regular failed
    // attempt short of the threshold.
    function register_failed_login($con, $table, $email) {
        $maxAttempts = (int) env('LOGIN_MAX_ATTEMPTS', 5);
        $lockoutHours = (float) env('LOGIN_LOCKOUT_HOURS', 1);

        $stmt = $con->prepare("SELECT failed_login_attempts FROM `$table` WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return null;
        }

        $attempts = (int) $row['failed_login_attempts'] + 1;

        if ($attempts >= $maxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + (int) round($lockoutHours * 3600));
            $upd = $con->prepare("UPDATE `$table` SET failed_login_attempts = 0, locked_until = ? WHERE email = ?");
            $upd->bind_param('ss', $lockedUntil, $email);
            $upd->execute();
            return $lockedUntil;
        }

        $upd = $con->prepare("UPDATE `$table` SET failed_login_attempts = ? WHERE email = ?");
        $upd->bind_param('is', $attempts, $email);
        $upd->execute();
        return null;
    }
}

if (!function_exists('reset_failed_login')) {
    // Clears the failed-attempt counter and any lock on successful login.
    function reset_failed_login($con, $table, $email) {
        $upd = $con->prepare("UPDATE `$table` SET failed_login_attempts = 0, locked_until = NULL WHERE email = ?");
        $upd->bind_param('s', $email);
        $upd->execute();
    }
}

if (!function_exists('format_lockout_message')) {
    // Consistent lockout copy for both interfaces.
    function format_lockout_message($lockedUntil, $justLocked = false) {
        $when = date('g:i A', strtotime($lockedUntil));
        $intro = $justLocked
            ? 'Too many failed login attempts.'
            : 'This account is locked due to too many failed login attempts.';
        return $intro . ' Please try again after ' . $when
            . ', or contact the administrator at ' . htmlspecialchars(env('ADMIN_EMAIL'), ENT_QUOTES, 'UTF-8') . ' for help.';
    }
}

// ---- General-purpose rate limiting ----
// A sliding-window request counter, independent of the login-lockout
// mechanism above (that one is per-account and only fires on a WRONG
// password; this one throttles a bucket of requests regardless of whether
// each individual request succeeds, and works for unauthenticated
// endpoints - register/forgot-password - where there's no account row to
// attach a counter to yet). Backed by tbl_rate_limits, see
// db-migrations/2026_07_26_add_rate_limits.sql.

if (!function_exists('check_rate_limit')) {
    /**
     * Records this attempt against ($action, $identifier) and returns
     * whether it's allowed under the last $windowSeconds. A blocked call
     * does NOT record another row - retrying while blocked doesn't push
     * the window out further, it just keeps reporting blocked until the
     * oldest recorded hit ages out.
     *
     * $identifier is typically an IP address (unauthenticated endpoints:
     * login, register, forgot-password) or a session email (authenticated
     * endpoints: task submission, comments, search) - always something
     * already trusted by the caller, never taken directly from request
     * input that could be spoofed to frame/dodge a different bucket.
     */
    function check_rate_limit($con, $action, $identifier, $maxHits, $windowSeconds) {
        $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);

        // Opportunistic cleanup for this bucket - keeps the table small
        // without needing a separate cron sweep for a table that's, by
        // design, never queried outside its own narrow window.
        $del = $con->prepare("DELETE FROM tbl_rate_limits WHERE action = ? AND identifier = ? AND created_at < ?");
        $del->bind_param('sss', $action, $identifier, $cutoff);
        $del->execute();

        $stmt = $con->prepare("SELECT COUNT(*) AS c FROM tbl_rate_limits WHERE action = ? AND identifier = ? AND created_at >= ?");
        $stmt->bind_param('sss', $action, $identifier, $cutoff);
        $stmt->execute();
        $count = (int) $stmt->get_result()->fetch_assoc()['c'];

        if ($count >= $maxHits) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $ins = $con->prepare("INSERT INTO tbl_rate_limits (action, identifier, created_at) VALUES (?, ?, ?)");
        $ins->bind_param('sss', $action, $identifier, $now);
        $ins->execute();

        return true;
    }
}

if (!function_exists('rate_limit_message')) {
    // Consistent "slow down" copy - tells the user when the oldest hit in
    // the current window will age out and let them through again.
    function rate_limit_message($con, $action, $identifier, $windowSeconds, $what = 'requests') {
        $stmt = $con->prepare("SELECT MIN(created_at) AS oldest FROM tbl_rate_limits WHERE action = ? AND identifier = ?");
        $stmt->bind_param('ss', $action, $identifier);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        $waitSeconds = ($row && $row['oldest']) ? max(1, (strtotime($row['oldest']) + $windowSeconds) - time()) : $windowSeconds;
        $waitMinutes = max(1, (int) ceil($waitSeconds / 60));

        return "Too many $what. Please wait $waitMinutes minute" . ($waitMinutes === 1 ? '' : 's') . " and try again.";
    }
}

// ---- Persistent activity log ----
// Separate from the rate-limit table above - that one is a rolling
// short-term window check_rate_limit() actively purges as rows age out;
// this one is never purged by app code, so it's what the "writer actions"
// section of sudo/activity-log.php actually reads from.

if (!function_exists('log_activity')) {
    // $taskId is optional (added for the task activity timeline on
    // view-task.php - see db-migrations/2026_09_15_add_interactive_features.sql
    // and get_task_activity_timeline() below) so every existing call site
    // that predates it keeps working unchanged, just without a task_id.
    function log_activity($con, $actorType, $email, $action, $details = null, $taskId = null) {
        $now = date('Y-m-d H:i:s');
        try {
            if ($taskId !== null) {
                $stmt = $con->prepare("INSERT INTO tbl_activity_log (actor_type, email, action, details, created_at, task_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('sssssi', $actorType, $email, $action, $details, $now, $taskId);
            } else {
                $stmt = $con->prepare("INSERT INTO tbl_activity_log (actor_type, email, action, details, created_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('sssss', $actorType, $email, $action, $details, $now);
            }
            $stmt->execute();
        } catch (\mysqli_sql_exception $e) {
            // task_id column not added yet (migration pending) - fall back
            // to the pre-timeline insert so logging itself still works.
            if ($taskId !== null) {
                $stmt = $con->prepare("INSERT INTO tbl_activity_log (actor_type, email, action, details, created_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('sssss', $actorType, $email, $action, $details, $now);
                $stmt->execute();
            }
        }
    }
}

// ---- Task activity timeline (view-task.php / sudo/view-task.php) ----
if (!function_exists('get_task_activity_timeline')) {
    // $excludeActions lets a caller drop noisy/uninteresting event types -
    // e.g. view-task.php (writer) hides 'task_view' since every open of the
    // page would otherwise log itself into its own timeline.
    function get_task_activity_timeline($con, $taskId, $limit = 30, $excludeActions = []) {
        $limit = (int) $limit;
        try {
            $where = "task_id = ?";
            $types = 'i';
            $params = [$taskId];
            if (!empty($excludeActions)) {
                $placeholders = implode(',', array_fill(0, count($excludeActions), '?'));
                $where .= " AND action NOT IN ($placeholders)";
                $types .= str_repeat('s', count($excludeActions));
                $params = array_merge($params, $excludeActions);
            }
            $stmt = $con->prepare("SELECT * FROM tbl_activity_log WHERE $where ORDER BY created_at DESC LIMIT $limit");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (\mysqli_sql_exception $e) {
            return []; // task_id column not added yet - migration pending
        }
    }
}

// Every log_activity() call on a task writes details in the shape
// "Task #<id>: <rest>" (or, for a resubmission, "Resubmission #N - Task
// #<id>: <rest>") - useful context for a shared admin log, but redundant on
// view-task.php/sudo/view-task.php's own activity timeline, which already
// shows this exact task's id and topic elsewhere on the page. Strips both
// out rather than hardcoding a per-action format, so it keeps working as
// new action types get logged.
if (!function_exists('format_activity_log_details')) {
    function format_activity_log_details($details, $taskId, $topic = '') {
        if (empty($details)) {
            return '';
        }
        $text = str_replace("Task #$taskId: ", '', $details);
        if (!empty($topic)) {
            $text = str_replace($topic, '', $text);
        }
        // Clean up whatever separator is left dangling at either end once
        // the topic/task-id chunk in the middle of the string is gone (e.g.
        // "Resubmission #2 - " or " - assigned to Jane").
        $text = trim($text, " -\t\n\r\0\x0B");
        return $text;
    }
}

// ---- Changelog / version history ----
// Replaces sudo/version.json, which only ever held ONE mutable record -
// every version bump overwrote the previous description, so despite the
// name there was no actual history. tbl_changelog is append-only; the
// current version is simply its most recent row. See
// db-migrations/2026_07_26_add_changelog.sql.

if (!function_exists('get_current_version')) {
    function get_current_version($con) {
        // Called from footer.php on every single page load (via
        // getVersionNumber()/getVersionLastUpdated() in version-functions.php),
        // so a missing/broken tbl_changelog here would take down the vendor
        // <script> tags (bootstrap, FontAwesome) at the bottom of every admin
        // page along with it - not just this feature. PHP 8.1+ defaults mysqli
        // to throwing mysqli_sql_exception on a bad query instead of returning
        // false, so `$result ? ... : null` alone doesn't actually guard
        // against a missing table; it has to be caught too.
        try {
            $result = mysqli_query($con, "SELECT * FROM tbl_changelog ORDER BY created_at DESC, id DESC LIMIT 1");
            $row = $result ? mysqli_fetch_assoc($result) : null;
        } catch (\mysqli_sql_exception $e) {
            $row = null;
        }
        if (!$row) {
            // Table exists but is empty (or missing/migration not run yet) -
            // a safe, obviously-a-placeholder default rather than a fatal
            // error, matching how the old JSON reader handled a missing file.
            return ['major' => 0, 'minor' => 0, 'patch' => 0, 'description' => '', 'created_by' => null, 'created_at' => date('Y-m-d H:i:s')];
        }
        return $row;
    }
}

if (!function_exists('get_changelog_history')) {
    function get_changelog_history($con, $limit = 50) {
        $limit = (int) $limit;
        $result = mysqli_query($con, "SELECT * FROM tbl_changelog ORDER BY created_at DESC, id DESC LIMIT $limit");
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('add_changelog_entry')) {
    // $type is 'major', 'minor', or 'patch' - bumps the corresponding part
    // of the CURRENT version and resets the lower parts, same semantics
    // the old JSON-based updateVersionNumber() had.
    function add_changelog_entry($con, $type, $description, $createdBy = null) {
        $current = get_current_version($con);
        $major = (int) $current['major'];
        $minor = (int) $current['minor'];
        $patch = (int) $current['patch'];

        switch ($type) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
            default:
                $patch++;
                break;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = mysqli_prepare($con, "INSERT INTO tbl_changelog (major, minor, patch, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iiisss', $major, $minor, $patch, $description, $createdBy, $now);
        mysqli_stmt_execute($stmt);

        return ['major' => $major, 'minor' => $minor, 'patch' => $patch, 'description' => $description, 'created_by' => $createdBy, 'created_at' => $now];
    }
}

// ---- Login email verification codes ----
// Required after a 7-day (normal) or 14-day (remember-me) session has
// expired and the writer/admin logs back in with their password. Only the
// hash is stored (mirrors reset_token), and codes are single-use.
// $table must always be a hardcoded literal ('tblwriters' or 'tbladmin')
// supplied by the calling code, never derived from request input.

if (!function_exists('generate_login_otp')) {
    // Creates a fresh 6-digit code for $email in $table, stores its hash with
    // a 10-minute expiry, and resets the attempt counter. Returns the raw
    // code (for emailing) - this is the only place it exists in plaintext.
    // Returns null if the login_otp_* columns don't exist yet (migration not
    // run) rather than fatal-erroring the whole login flow - callers should
    // treat null as "skip the OTP step for now, this account isn't ready".
    function generate_login_otp($con, $table, $email) {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = hash('sha256', $code);
        $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        $stmt = $con->prepare("UPDATE `$table` SET login_otp_hash = ?, login_otp_expires = ?, login_otp_attempts = 0 WHERE email = ?");
        if (!$stmt) {
            error_log("generate_login_otp: prepare failed (has db-migrations/2026_07_18_add_login_otp.sql been run?) - " . $con->error);
            return null;
        }
        $stmt->bind_param('sss', $codeHash, $expires, $email);
        $stmt->execute();

        return $code;
    }
}

if (!function_exists('verify_login_otp')) {
    // Checks $submittedCode against the stored hash for $email in $table.
    // Returns ['success' => true] and clears the code on match, or
    // ['success' => false, 'error' => 'expired'|'locked'|'invalid'|'unavailable'].
    function verify_login_otp($con, $table, $email, $submittedCode) {
        $maxAttempts = 5;

        $stmt = $con->prepare("SELECT login_otp_hash, login_otp_expires, login_otp_attempts FROM `$table` WHERE email = ?");
        if (!$stmt) {
            error_log("verify_login_otp: prepare failed (has db-migrations/2026_07_18_add_login_otp.sql been run?) - " . $con->error);
            return ['success' => false, 'error' => 'unavailable'];
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || empty($row['login_otp_hash']) || empty($row['login_otp_expires'])
            || strtotime($row['login_otp_expires']) < time()) {
            return ['success' => false, 'error' => 'expired'];
        }

        if ((int) $row['login_otp_attempts'] >= $maxAttempts) {
            return ['success' => false, 'error' => 'locked'];
        }

        $submittedHash = hash('sha256', trim((string) $submittedCode));

        if (!hash_equals($row['login_otp_hash'], $submittedHash)) {
            $upd = $con->prepare("UPDATE `$table` SET login_otp_attempts = login_otp_attempts + 1 WHERE email = ?");
            $upd->bind_param('s', $email);
            $upd->execute();
            return ['success' => false, 'error' => 'invalid'];
        }

        // Single-use: clear the code once it's been consumed.
        $clear = $con->prepare("UPDATE `$table` SET login_otp_hash = NULL, login_otp_expires = NULL, login_otp_attempts = 0 WHERE email = ?");
        $clear->bind_param('s', $email);
        $clear->execute();

        return ['success' => true];
    }
}

// ---- Known-device login detection ----
// A device (browser) is "known" only after it completes a full login
// (password, plus the emailed OTP when required). is_known_device_token()
// checks a long-lived cookie against tblwriter_known_devices/
// tbladmin_known_devices; remember_device() (re)issues that cookie and
// (re)records the device with a sliding trust window, refreshed on every
// recognized login. $table/$emailColumn must always be hardcoded literals
// ('tblwriter_known_devices'/'writer_email' or 'tbladmin_known_devices'/
// 'admin_email') supplied by the calling code, never derived from request
// input.

if (!function_exists('known_device_trust_days')) {
    function known_device_trust_days() { return 30; }
}

if (!function_exists('is_known_device_token')) {
    // Returns true only if $deviceToken matches a non-expired row for $email.
    // Fails closed (false) on any error, including the migration not having
    // been run yet - a missing table must never be read as "device known".
    function is_known_device_token($con, $table, $emailColumn, $email, $deviceToken) {
        if (empty($deviceToken)) {
            return false;
        }

        $tokenHash = hash('sha256', $deviceToken);
        // expires_at is written with PHP's date() under Africa/Nairobi (see
        // remember_device() below) - MySQL's NOW() is this server's UTC clock,
        // so comparing against it directly would run ~3 hours off. Compare
        // against a PHP-computed timestamp on the same clock instead.
        $now = date('Y-m-d H:i:s');
        $stmt = $con->prepare("SELECT id FROM `$table` WHERE `$emailColumn` = ? AND device_token_hash = ? AND expires_at > ?");
        if (!$stmt) {
            error_log("is_known_device_token: prepare failed on `$table` (has db-migrations/2026_07_20_add_known_devices.sql been run?) - " . $con->error);
            return false;
        }
        $stmt->bind_param('sss', $email, $tokenHash, $now);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $found;
    }
}

if (!function_exists('remember_device')) {
    // Call after a successful login. Pass $existingToken (the cookie value
    // that was just verified by is_known_device_token()) to slide that same
    // device's trust window forward; omit it to mint a brand-new token for a
    // device that just passed OTP for the first time. Always (re)sets the
    // cookie, since the browser's own copy should slide forward too.
    function remember_device($con, $table, $emailColumn, $email, $cookieName, $existingToken = null) {
        $token = $existingToken ?: bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $days = known_device_trust_days();
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + $days * 86400);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $label = function_exists('get_device_info') ? get_device_info($_SERVER['HTTP_USER_AGENT'] ?? '') : null;

        $sql = "INSERT INTO `$table` (`$emailColumn`, device_token_hash, device_label, ip_address, first_seen, last_seen, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    last_seen = VALUES(last_seen), expires_at = VALUES(expires_at),
                    ip_address = VALUES(ip_address), device_label = VALUES(device_label)";
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            error_log("remember_device: prepare failed on `$table` (has db-migrations/2026_07_20_add_known_devices.sql been run?) - " . $con->error);
            return;
        }
        $stmt->bind_param('sssssss', $email, $tokenHash, $label, $ip, $now, $now, $expiresAt);
        $stmt->execute();
        $stmt->close();

        setcookie($cookieName, $token, time() + $days * 86400, '/', '', true, true);
    }
}

// ---- Outbound mail (shared SMTP sender) ----
// Every existing send site (sudo/submit-task.php, submission_upload.php, ...)
// hand-rolls this same PHPMailer/SMTP boilerplate inline. This helper exists
// so newer features (extension requests, mention notifications, etc.) don't
// add yet another copy of it - it does not touch or replace the existing
// call sites. Uses fully-qualified class names (no `use` import) since this
// file is included well after its own top and from both interfaces.
if (!function_exists('send_app_mail')) {
    /**
     * @param string      $toEmail
     * @param string      $toName
     * @param string      $subject
     * @param string      $htmlBody   Full HTML, e.g. the output of render_email_html().
     * @param string|null $bccEmail   Optional BCC address (e.g. env('ADMIN_EMAIL')).
     * @param string|null $bccName
     * @return bool true on success, false on failure (check error_log for detail).
     */
    function send_app_mail($toEmail, $toName, $subject, $htmlBody, $bccEmail = null, $bccName = null) {
        require_once __DIR__ . '/vendor/autoload.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = env('SMTP_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = env('SMTP_USER');
            $mail->Password = env('SMTP_PASS');
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) env('SMTP_PORT', 587);

            $mail->setFrom(env('MAIL_FROM_ADDRESS'), 'iTasker');
            $mail->addAddress($toEmail, $toName);
            if ($bccEmail) {
                $mail->addBCC($bccEmail, $bccName ?: 'iTasker Admin');
            }
            $mail->addCustomHeader('X-Mailer', 'iTasker v1.0');

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log('send_app_mail failed to ' . $toEmail . ': ' . ($mail->ErrorInfo ?: $e->getMessage()));
            return false;
        }
    }
}

// ---- Remote file download (cURL) ----
// A named, reusable twin of the download loop submission_upload.php defines
// inline as an unguarded global function - that one can't be reused from
// elsewhere in the same request without a redeclare fatal, so new call
// sites (the pre-submission word/page checker) use this instead.
if (!function_exists('download_remote_file')) {
    function download_remote_file($url, $localPath) {
        $ch = curl_init(str_replace(' ', '%20', $url));
        $fp = fopen($localPath, 'wb');
        if ($fp === false) {
            return false;
        }
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($success === false || $httpCode >= 400) {
            $success = false;
        }
        curl_close($ch);
        fclose($fp);
        if (!$success) {
            @unlink($localPath);
        }
        return $success;
    }
}

// ---- Web Push (browser/installed-app notifications) ----
// Sends to every subscription a writer/admin has saved (they can have more
// than one - phone + desktop). Uses minishlink/web-push (composer.json) and
// the VAPID_* keys in .env. A dead/expired subscription (410/404 from the
// push service) is pruned automatically. See push-sw.js,
// assets/js/push-notifications.js, save-push-subscription.php.
if (!function_exists('send_push_notification')) {
    function send_push_notification($con, $userType, $userEmail, $title, $body, $url = '/') {
        $vapidPublic = env('VAPID_PUBLIC_KEY');
        $vapidPrivate = env('VAPID_PRIVATE_KEY');
        if (empty($vapidPublic) || empty($vapidPrivate)) {
            return; // not configured - silently a no-op, same as an admin who hasn't set SMTP
        }

        try {
            $stmt = $con->prepare("SELECT endpoint, p256dh, auth FROM tbl_push_subscriptions WHERE user_type = ? AND user_email = ?");
            $stmt->bind_param('ss', $userType, $userEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            $subs = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (\mysqli_sql_exception $e) {
            return; // migration not run yet
        }

        if (empty($subs)) {
            return;
        }

        require_once __DIR__ . '/vendor/autoload.php';

        try {
            $webPush = new \Minishlink\WebPush\WebPush([
                'VAPID' => [
                    'subject' => env('VAPID_SUBJECT', 'mailto:' . env('ADMIN_EMAIL', 'admin@example.com')),
                    'publicKey' => $vapidPublic,
                    'privateKey' => $vapidPrivate,
                ],
            ]);
        } catch (\Exception $e) {
            error_log('send_push_notification: could not init WebPush - ' . $e->getMessage());
            return;
        }

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);

        foreach ($subs as $sub) {
            $webPush->queueNotification(
                \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'publicKey' => $sub['p256dh'],
                    'authToken' => $sub['auth'],
                ]),
                $payload
            );
        }

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                $hash = hash('sha256', $endpoint);
                $del = $con->prepare("DELETE FROM tbl_push_subscriptions WHERE endpoint_hash = ?");
                $del->bind_param('s', $hash);
                $del->execute();
                $del->close();
            }
        }
    }
}

// ---- @mentions in task discussion comments ----
// Extracts @username tokens from a comment, matches them against
// tblwriters/tbladmin usernames, notifies each matched user (email + push,
// skipping the commenter themselves), and returns a comma-separated list
// of the matched usernames for storage in tbl_task_comments.mentions. See
// db-migrations/2026_09_15_add_interactive_features.sql.
if (!function_exists('parse_and_notify_mentions')) {
    function parse_and_notify_mentions($con, $comment, $taskId, $commenterUsername, $commenterType) {
        if (!preg_match_all('/@([A-Za-z0-9_.\-]{2,50})/', $comment, $matches)) {
            return null;
        }
        $candidates = array_unique($matches[1]);
        if (empty($candidates)) {
            return null;
        }

        $matched = [];
        $encodedId = function_exists('encode_task_id') ? encode_task_id($taskId) : $taskId;

        foreach ($candidates as $name) {
            if (strcasecmp($name, $commenterUsername) === 0) {
                continue; // don't notify yourself
            }

            // Check writers first, then admins.
            $stmt = $con->prepare("SELECT username, email FROM tblwriters WHERE username = ? AND is_deleted = 0 LIMIT 1");
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $target = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $targetType = 'writer';

            if (!$target) {
                $stmt = $con->prepare("SELECT username, email FROM tbladmin WHERE username = ? LIMIT 1");
                $stmt->bind_param('s', $name);
                $stmt->execute();
                $target = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $targetType = 'admin';
            }

            if (!$target) {
                continue;
            }

            $matched[] = $target['username'];

            $url = $targetType === 'admin'
                ? '/sudo/view-task?task_id=' . $encodedId
                : '/view-task?task_id=' . $encodedId;
            $title = 'You were mentioned';
            $body = htmlspecialchars($commenterUsername, ENT_QUOTES, 'UTF-8') . " mentioned you on task #$taskId";

            if (function_exists('send_push_notification')) {
                send_push_notification($con, $targetType, $target['email'], $title, $body, $url);
            }
            if (function_exists('send_app_mail') && function_exists('render_email_html')) {
                $emailHtml = render_email_html($title, "<p>$body</p>", 'View Task', rtrim(env('APP_URL'), '/') . $url);
                send_app_mail($target['email'], $target['username'], "$title - Task #$taskId", $emailHtml);
            }
        }

        return empty($matched) ? null : implode(',', array_unique($matched));
    }
}

// ---- Reactions on task discussion comments ----
// Toggle: reacting again with the same emoji removes it. Returns the full
// updated reaction summary for that comment so the client can re-render
// without a second request.
if (!function_exists('toggle_comment_reaction')) {
    function toggle_comment_reaction($con, $commentId, $userType, $userEmail, $emoji) {
        $existing = $con->prepare("SELECT id FROM tbl_comment_reactions WHERE comment_id = ? AND user_email = ? AND emoji = ?");
        $existing->bind_param('iss', $commentId, $userEmail, $emoji);
        $existing->execute();
        $row = $existing->get_result()->fetch_assoc();
        $existing->close();

        if ($row) {
            $del = $con->prepare("DELETE FROM tbl_comment_reactions WHERE id = ?");
            $del->bind_param('i', $row['id']);
            $del->execute();
            $del->close();
        } else {
            $now = date('Y-m-d H:i:s');
            $ins = $con->prepare("INSERT INTO tbl_comment_reactions (comment_id, user_type, user_email, emoji, created_at) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param('issss', $commentId, $userType, $userEmail, $emoji, $now);
            $ins->execute();
            $ins->close();
        }

        return get_comment_reaction_summary($con, [$commentId], $userEmail)[$commentId] ?? [];
    }
}

if (!function_exists('get_comment_reaction_summary')) {
    // Batched: pass every comment id on the page in one call rather than
    // one request per comment.
    function get_comment_reaction_summary($con, array $commentIds, $viewerEmail) {
        $summary = [];
        if (empty($commentIds)) {
            return $summary;
        }
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        $types = str_repeat('i', count($commentIds));
        $stmt = $con->prepare("SELECT comment_id, emoji, COUNT(*) as cnt, GROUP_CONCAT(user_email) as reactors FROM tbl_comment_reactions WHERE comment_id IN ($placeholders) GROUP BY comment_id, emoji");
        $stmt->bind_param($types, ...$commentIds);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $reactors = explode(',', $row['reactors']);
            $summary[(int) $row['comment_id']][] = [
                'emoji' => $row['emoji'],
                'count' => (int) $row['cnt'],
                'reacted_by_me' => in_array($viewerEmail, $reactors, true),
            ];
        }
        $stmt->close();
        return $summary;
    }
}

// ---- Web Push broadcast to every admin who opted in ----
// There's no single "admin inbox" the way ADMIN_EMAIL's BCC works for
// email - push subscriptions are per-admin-account, so an admin-facing
// event (a writer submitted, an extension was requested) fans out to
// every distinct admin who has a saved subscription.
if (!function_exists('send_push_to_admins')) {
    function send_push_to_admins($con, $title, $body, $url = '/sudo/') {
        try {
            $result = mysqli_query($con, "SELECT DISTINCT user_email FROM tbl_push_subscriptions WHERE user_type = 'admin'");
        } catch (\mysqli_sql_exception $e) {
            return;
        }
        if (!$result) {
            return;
        }
        while ($row = mysqli_fetch_assoc($result)) {
            send_push_notification($con, 'admin', $row['user_email'], $title, $body, $url);
        }
    }
}

// ---- Feature flags (admin-toggled writer-facing features) ----
// Backs sudo/bonus-settings.php's on/off switch for the writer bonus
// progress meter, and is written to be reusable for future flags too. See
// db-migrations/2026_09_15_add_interactive_features.sql.
if (!function_exists('is_feature_enabled')) {
    function is_feature_enabled($con, $flagName, $default = false) {
        // Guarded like get_current_version() - PHP 8.1+ mysqli throws on a
        // missing table (migration not run yet) rather than returning false.
        try {
            $stmt = $con->prepare("SELECT is_enabled FROM tbl_feature_flags WHERE flag_name = ?");
            if (!$stmt) {
                return $default;
            }
            $stmt->bind_param('s', $flagName);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row ? (bool) $row['is_enabled'] : $default;
        } catch (\mysqli_sql_exception $e) {
            return $default;
        }
    }
}

// ---- Monthly bonus projection (writer-facing progress meter) ----
// Mirrors the math in sudo/writer-performance-functions.php's
// calculateMonthlyBonus() (kept as a separate copy rather than reused
// directly, per this project's root/sudo split convention - see README
// "Files with the same name..."), but for the CURRENT, still-in-progress
// month, so a writer can see where they stand before month-end instead of
// only after sudo/bonus-settings.php runs its monthly calculation.
if (!function_exists('calculate_monthly_bonus_projection')) {
    function calculate_monthly_bonus_projection($con, $writerEmail, $month, $year) {
        $settingsResult = mysqli_query($con, "SELECT setting_name, setting_value FROM tbl_bonus_settings WHERE is_active = 1");
        $settings = [];
        if ($settingsResult) {
            while ($row = mysqli_fetch_assoc($settingsResult)) {
                $settings[$row['setting_name']] = floatval($row['setting_value']);
            }
        }

        $stmt = $con->prepare("SELECT
            COUNT(*) as total_completed,
            SUM(CASE WHEN submitted_on > due_date THEN 1 ELSE 0 END) as late_completions,
            SUM(pages * cpp) as total_earnings,
            SUM(CASE WHEN submitted_on < due_date THEN (pages * cpp) ELSE 0 END) as early_earnings
            FROM tbltasks
            WHERE email = ?
            AND status IN ('Completed', 'Submitted')
            AND MONTH(submitted_on) = ?
            AND YEAR(submitted_on) = ?
            AND is_deleted = 0");
        $stmt->bind_param('sii', $writerEmail, $month, $year);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $totalCompleted = (int) ($data['total_completed'] ?? 0);
        $lateCompletions = (int) ($data['late_completions'] ?? 0);
        $totalEarnings = (float) ($data['total_earnings'] ?? 0);
        $earlyEarnings = (float) ($data['early_earnings'] ?? 0);

        $basePct = $settings['base_bonus_percentage'] ?? 5.0;
        $earlyPct = $settings['early_completion_bonus'] ?? 2.5;
        $perfectPct = $settings['perfect_month_bonus'] ?? 10.0;

        $baseBonus = ($totalEarnings * $basePct) / 100;
        $earlyBonus = ($earlyEarnings * $earlyPct) / 100;
        // The perfect-month bonus is only "at risk", not yet earned, until the
        // month closes - shown separately so the meter can say how close the
        // writer is, rather than claiming it's already secured.
        $perfectMonthBonusIfEarned = $totalEarnings * $perfectPct / 100;
        $onTrackForPerfectMonth = $totalCompleted > 0 && $lateCompletions == 0;

        return [
            'total_completed' => $totalCompleted,
            'late_completions' => $lateCompletions,
            'total_earnings' => $totalEarnings,
            'guaranteed_bonus' => round($baseBonus + $earlyBonus, 2),
            'perfect_month_bonus_amount' => round($perfectMonthBonusIfEarned, 2),
            'on_track_for_perfect_month' => $onTrackForPerfectMonth,
            'perfect_month_percentage' => $perfectPct,
        ];
    }
}

if (!function_exists('set_feature_enabled')) {
    function set_feature_enabled($con, $flagName, $enabled, $updatedBy) {
        $now = date('Y-m-d H:i:s');
        $enabledInt = $enabled ? 1 : 0;
        $stmt = $con->prepare("INSERT INTO tbl_feature_flags (flag_name, is_enabled, updated_at, updated_by)
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('siss', $flagName, $enabledInt, $now, $updatedBy);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
