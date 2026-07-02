<?php
include "check-login.php";
require_once 'spaces-helper.php';

// Generate a 4-character unique ID
function generateShortId($length = 4) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

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

        // Sanitize the original filename
        $sanitizedFileName = sanitizeFileName($originalFileName);

        // Process filename to add unique ID
        $fileExtension = pathinfo($sanitizedFileName, PATHINFO_EXTENSION);
        $fileNameWithoutExt = pathinfo($sanitizedFileName, PATHINFO_FILENAME);

        // Generate unique 4-character ID
        $uniqueId = generateShortId(4);

        // Create new filename: originalname_uniqueID.extension
        $newFileName = $fileNameWithoutExt . '_' . $uniqueId . '.' . $fileExtension;

        // Upload to Digital Ocean Spaces in the taskfiles/submissions folder
        $spacesHelper = new SpacesHelper();
        $result = $spacesHelper->uploadFile($tempFilePath, $newFileName, 'taskfiles/submissions');

        if ($result['success']) {
            echo json_encode([
                'status' => 'success',
                'filePath' => $result['key'],
                'fileUrl' => $result['url'],
                'fileName' => $originalFileName,        // Keep this for backward compatibility
                'originalName' => $originalFileName,    // Add this for clarity
                'actualFileName' => $newFileName,
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