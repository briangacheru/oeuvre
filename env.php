<?php
/**
 * Loads configuration from the project-root .env file.
 * Shared by the writer (root) and administrator (sudo) interfaces.
 *
 * Usage: require_once this file, then call env('KEY', 'default').
 */
if (!function_exists('env')) {
    function env($key, $default = null) {
        static $vars = null;
        if ($vars === null) {
            $vars = [];
            $file = __DIR__ . '/.env';
            if (is_readable($file)) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#') {
                        continue;
                    }
                    $pos = strpos($line, '=');
                    if ($pos === false) {
                        continue;
                    }
                    $name = trim(substr($line, 0, $pos));
                    $value = trim(substr($line, $pos + 1));
                    $len = strlen($value);
                    if ($len >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$len - 1] === $value[0]) {
                        $value = substr($value, 1, $len - 2);
                    }
                    $vars[$name] = $value;
                }
            }
        }
        return array_key_exists($key, $vars) ? $vars[$key] : $default;
    }
}

if (!function_exists('safe_db_error')) {
    /**
     * Returns a database error string that is safe to send to the browser.
     * With APP_DEBUG on (local dev) the real detail is returned unchanged;
     * in production the detail is logged server-side and a generic message
     * is shown so schema/query internals are not disclosed to users.
     */
    function safe_db_error($detail = '') {
        if ($detail !== '' && $detail !== null) {
            error_log('DB error: ' . $detail);
        }
        return filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN)
            ? $detail
            : 'A database error occurred. Please try again.';
    }
}

if (!function_exists('is_allowed_upload')) {
    /**
     * Whitelist check for uploaded file names. Rejects anything whose
     * extension is not on the allow-list (blocks .php, .phtml, .sh, etc.).
     * $type 'image' allows image types only; 'file' allows images + common
     * document/archive types used for task attachments.
     */
    function is_allowed_upload($fileName, $type = 'file') {
        $ext = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
        $images = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $docs = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt', 'ods', 'odp', 'zip', 'rar', '7z'];
        $allowed = ($type === 'image') ? $images : array_merge($images, $docs);
        return $ext !== '' && in_array($ext, $allowed, true);
    }
}
