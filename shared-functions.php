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
