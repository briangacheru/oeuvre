<?php
include "check-login.php";
require_once 'spaces-helper.php';

// Sanitize filename to remove problematic characters
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Upload failed with error code: ' . $file['error']]);
            exit;
        }

        // Create a temporary file path
        $tempFilePath = $file['tmp_name'];
        $originalFileName = $file['name'];
        $fileSize = $file['size'];

        // Sanitize the filename
        $sanitizedFileName = sanitizeFileName($originalFileName);

        // Upload to Digital Ocean Spaces in the taskfiles folder
        $spacesHelper = new SpacesHelper();
        $result = $spacesHelper->uploadFile($tempFilePath, $sanitizedFileName, 'taskfiles');

        if ($result['success']) {
            // Extract just the filename from the full key path
            $actualFileName = basename($result['key']);

            echo json_encode([
                'status' => 'success',
                'filePath' => $actualFileName, // Just the filename, not the full path
                'fileUrl' => $result['url'],
                'fileName' => $originalFileName, // Keep original for display
                'actualFileName' => $actualFileName, // Just the sanitized filename
                'fileSize' => $fileSize
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $result['message']]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>