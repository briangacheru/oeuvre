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
