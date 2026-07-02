<?php
$targetDirectory = "taskfiles/"; // Make sure this directory exists and has appropriate permissions
$response = [];

foreach ($_FILES as $file) {
    $targetFilePath = $targetDirectory . basename($file['name']);
    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        $response[] = $targetFilePath; // Or just the file name, depending on your needs
    } else {
        $response[] = "Error uploading " . $file['name'];
    }
}

echo json_encode($response); // Send back the file paths or names as JSON
?>
