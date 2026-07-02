<?php
session_start();
include "dbcon.php";

// Prevent any output before JSON
error_reporting(0);
ob_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['odmsaid'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method',
        'method' => $_SERVER['REQUEST_METHOD'],
        'post_data' => $_POST
    ]);
    exit;
}

$fileId = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
$fileType = isset($_POST['file_type']) ? $_POST['file_type'] : '';
$taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;

if ($fileId <= 0) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid file ID: ' . $fileId]);
    exit;
}

// Soft delete - set is_deleted = 1
$updateQuery = "UPDATE tbl_task_files SET is_deleted = 1 WHERE id = ?";
$stmt = mysqli_prepare($con, $updateQuery);

if (!$stmt) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . mysqli_error($con)]);
    exit;
}

mysqli_stmt_bind_param($stmt, 'i', $fileId);

if (mysqli_stmt_execute($stmt)) {
    $affectedRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affectedRows > 0) {
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'No file found with ID: ' . $fileId]);
    }
} else {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Failed to delete file: ' . mysqli_stmt_error($stmt)]);
    mysqli_stmt_close($stmt);
}

exit;
?>