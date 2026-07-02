<?php

if (isset($_POST['task_id']) && !empty($_FILES['task_files'])) {
    $taskID = intval($_POST['task_id']);
    $filePaths = [];

    foreach ($_FILES['task_files']['tmp_name'] as $key => $tmpName) {
        if ($_FILES['task_files']['error'][$key] === UPLOAD_ERR_OK) {
            $fileName = time() . "_" . $_FILES['task_files']['name'][$key]; // Prefix to avoid name collisions
            $targetFilePath = "uploads/" . $fileName;

            if (move_uploaded_file($tmpName, $targetFilePath)) {
                $filePaths[] = $targetFilePath;
            }
        }
    }

    // Update the task with the file paths in JSON format
    $filePathsJson = mysqli_real_escape_string($con, json_encode($filePaths));
    $sql = "UPDATE tbltasks SET file_paths = '$filePathsJson' WHERE id = $taskID";

    if (mysqli_query($con, $sql)) {
        echo "Files uploaded successfully.";
    } else {
        echo "Error updating task with files: " . mysqli_error($con);
    }
} else {
    echo "No files or task ID provided.";
}
?>
