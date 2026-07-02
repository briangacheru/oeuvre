<?php
include "check-login.php";
require_once 'spaces-helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deleteFile') {
    if (isset($_POST['filePath'])) {
        $filePath = $_POST['filePath'];

        // Create SpacesHelper instance
        $spacesHelper = new SpacesHelper();

        // Delete the file from Digital Ocean Spaces
        $result = $spacesHelper->deleteFile($filePath);

        if ($result['success']) {
            echo json_encode([
                'status' => 'success',
                'message' => 'File successfully deleted from Digital Ocean Spaces.'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to delete file: ' . $result['message']
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No file path provided.'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request.'
    ]);
}
?>