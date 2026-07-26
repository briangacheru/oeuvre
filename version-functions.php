<?php
/**
 * Shared version display helpers for both interfaces. Thin wrappers around
 * tbl_changelog (see shared-functions.php's get_current_version()) -
 * kept as separate named functions so existing callers (footer.php,
 * sudo/footer.php) didn't need to change when this moved off the old
 * sudo/version.json file.
 */
require_once __DIR__ . '/shared-functions.php';

if (!function_exists('getVersionNumber')) {
    // Gets the current version number, formatted as "vX.Y.Z"
    function getVersionNumber() {
        global $con;
        $v = get_current_version($con);
        return "v{$v['major']}.{$v['minor']}.{$v['patch']}";
    }
}

if (!function_exists('getVersionDescription')) {
    // Gets the description attached to the current (most recent) version
    function getVersionDescription() {
        global $con;
        $v = get_current_version($con);
        return $v['description'] ?? '';
    }
}

if (!function_exists('getVersionLastUpdated')) {
    // Gets the current version's timestamp, formatted per $format
    function getVersionLastUpdated($format = 'F j, Y') {
        global $con;
        $v = get_current_version($con);
        return date($format, strtotime($v['created_at']));
    }
}
