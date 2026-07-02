<?php
// Assuming a simple check for user authentication/session here
include "check-login.php";

$targetDir = "taskfiles/"; // Ensure this directory exists and is writable

$response = ['status' => 'error', 'message' => 'File upload failed.'];

if (isset($_FILES['file']['name'])) {
    $fileName = basename($_FILES['file']['name']);
    $targetFilePath = $targetDir . $fileName;

    if (!is_allowed_upload($fileName)) {
        echo json_encode(['status' => 'error', 'message' => 'File type not allowed.']);
        exit;
    }

    // Move the file to the server directory
    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFilePath)) {
        $response = ['status' => 'success', 'filePath' => $targetFilePath];
    } else {
        $response['message'] = 'Sorry, there was an error uploading your file.';
    }
}

echo json_encode($response);
?>
