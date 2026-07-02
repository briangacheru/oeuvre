<?php
include "check-login.php";
csrf_verify_or_json_die();
require_once 'spaces-helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deleteFile') {
    if (isset($_POST['filePath'])) {
        $filePath = $_POST['filePath'];

        // Delete from Digital Ocean Spaces
        $spacesHelper = new SpacesHelper();
        $result = $spacesHelper->deleteFile($filePath);

        if ($result['success']) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $result['message']]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No file path provided']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}