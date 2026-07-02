<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['filePath'])) {
    $filePath = $_POST['filePath'];

    $baseDir = realpath('../taskfiles');
    $realFilePath = realpath($baseDir . DIRECTORY_SEPARATOR . $filePath);

    // Security check: Prevent directory traversal
    if (strpos($realFilePath, $baseDir) !== 0 || !$realFilePath) {
        echo json_encode(['success' => false, 'message' => 'Invalid file path.']);
        exit;
    }

    // Check if the file exists
    if (!file_exists($realFilePath)) {
        echo json_encode(['success' => false, 'message' => 'File does not exist.']);
        exit;
    }

    // Attempt to delete the file
    if (unlink($realFilePath)) {
        echo json_encode(['success' => true, 'message' => 'File successfully deleted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'File could not be deleted. Check file permissions.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>
