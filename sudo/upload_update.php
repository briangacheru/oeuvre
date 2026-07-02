<?php
include "check-login.php";
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

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
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            ];

            $errorMessage = isset($errorMessages[$file['error']])
                ? $errorMessages[$file['error']]
                : 'Unknown upload error';

            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $errorMessage]);
            exit;
        }

        // Sanitize the original filename first
        $originalName = $file['name'];
        $sanitizedName = sanitizeFileName($originalName);

        // Generate a unique filename to prevent overwriting
        $extension = pathinfo($sanitizedName, PATHINFO_EXTENSION);
        $uniqueString = substr(md5(uniqid()), 0, 4); // Generate a 4-character unique string
        $filenameWithoutExt = pathinfo($sanitizedName, PATHINFO_FILENAME); // Get filename without extension
        $uniqueName = $filenameWithoutExt . '_' . $uniqueString . '.' . $extension; // Append unique string

        // Create a temporary file path
        $tempFilePath = $file['tmp_name'];

        try {
            // Load configuration
            $config = include 'spaces-config.php';

            // Create S3 client
            $s3 = new S3Client([
                'version' => 'latest',
                'region' => $config['region'],
                'endpoint' => 'https://' . $config['region'] . '.digitaloceanspaces.com',
                'credentials' => [
                    'key' => $config['access_key'],
                    'secret' => $config['secret_key'],
                ],
                'use_path_style_endpoint' => false,
            ]);

            // Set the key to be directly in the taskfiles folder
            $key = 'taskfiles/' . $uniqueName;

            // Upload file directly to the taskfiles folder
            $result = $s3->putObject([
                'Bucket' => $config['bucket'],
                'Key' => $key,
                'Body' => fopen($tempFilePath, 'r'),
                'ACL' => 'public-read',
                'ContentType' => (function_exists('finfo_open')
                    ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tempFilePath)
                    : (function_exists('mime_content_type')
                        ? mime_content_type($tempFilePath)
                        : $file['type'])),
            ]);

            // Use direct Spaces URL instead of CDN
            $fileUrl = 'https://' . $config['bucket'] . '.' . $config['region'] . '.digitaloceanspaces.com/' . $key;

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'File uploaded successfully',
                'filePath' => $uniqueName, // Just the filename, not the full path
                'fileUrl' => $fileUrl,
                'fileSize' => $file['size'],
                'originalFileName' => $originalName, // Keep original for display
                'actualFileName' => $uniqueName // Just the sanitized + unique filename
            ]);
        } catch (AwsException $e) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'AWS Error: ' . $e->getMessage()]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>