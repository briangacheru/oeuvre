<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/session-name.php';
session_start();
// Require an authenticated session; this is an AJAX endpoint, so respond
// with JSON rather than redirecting.
if (empty($_SESSION['sessionWriter']) && empty($_SESSION['odmsaid'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$targetDirectory = "taskfiles/"; // Make sure this directory exists and has appropriate permissions
$response = [];

foreach ($_FILES as $file) {
    if (!is_allowed_upload($file['name'])) {
        $response[] = "Rejected (file type not allowed): " . basename($file['name']);
        continue;
    }
    $targetFilePath = $targetDirectory . basename($file['name']);
    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        $response[] = $targetFilePath; // Or just the file name, depending on your needs
    } else {
        $response[] = "Error uploading " . $file['name'];
    }
}

echo json_encode($response); // Send back the file paths or names as JSON
?>
