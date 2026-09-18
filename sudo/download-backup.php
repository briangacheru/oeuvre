<?php
include "check-login.php";
requireCapability($currentAdminRole, 'manage_settings', 'text');
require_once __DIR__ . '/backup-functions.php';

$filename = $_GET['file'] ?? '';
if (!is_valid_backup_filename($filename)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Invalid backup filename.';
    exit();
}

$path = db_backup_dir() . '/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Backup not found.';
    exit();
}

log_activity($con, 'admin', $_SESSION['odmsaid'] ?? '', 'db_backup_downloaded', $filename);

header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit();
