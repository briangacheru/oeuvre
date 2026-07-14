<?php
require_once __DIR__ . '/../shared-functions.php';
include "check-login.php";
csrf_verify_or_json_die();
require_once 'spaces-helper.php';

// Sanitize filename to remove problematic characters

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Upload failed with error code: ' . $file['error']]);
            exit;
        }

        // Enforce the same type/size policy as chat attachments (Word, Excel,
        // PowerPoint, PDF, ZIP, photos; 50MB) server-side - the Dropzone config
        // in sudo/create-task.php only restricts this client-side, which a
        // request posted directly to this endpoint can bypass.
        $validation = validateChatAttachment($file);
        if (!$validation['success']) {
            echo json_encode(['status' => 'error', 'message' => $validation['message']]);
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