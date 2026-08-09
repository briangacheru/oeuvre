<?php
include "check-login.php";
csrf_verify_or_json_die();
require_once 'spaces-helper.php';
require_once __DIR__ . '/storage-helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deleteFile') {
    if (isset($_POST['filePath'])) {
        $filePath = $_POST['filePath'];

        // Delete via whichever backend is currently configured in sudo/settings.php
        if (get_storage_provider($con) === 'digitalocean') {
            $spacesHelper = new SpacesHelper();
            $result = $spacesHelper->deleteFile($filePath);
        } else {
            $result = storage_delete_file_local($filePath);
        }

        if ($result['success']) {
            echo json_encode([
                'status' => 'success',
                'message' => 'File successfully deleted.'
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