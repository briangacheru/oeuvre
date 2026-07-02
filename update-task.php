<?php
include "check-login.php";

// Assuming $con is a valid mysqli connection object established in "check-login.php" or elsewhere
if ($_POST['action'] == 'submitForm') {
    // List of required fields
    $requiredFields = ['topic', 'subject', 'account', 'description', 'writer', 'email', 'due_date', 'cpp', 'pages'];

    // Check each required field
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            header('Content-Type: application/json');
            // Respond with an error message indicating which field is missing
            echo json_encode(['status' => 'error', 'message' => "The field {$field} is required."]);
            exit; // Stop the script execution
        }
    }
    // Since FILTER_SANITIZE_STRING is deprecated, consider alternative sanitization
    $taskId = mysqli_real_escape_string($con, $_POST['taskId']);
    $topic = mysqli_real_escape_string($con, $_POST['topic']);
    $subject = mysqli_real_escape_string($con, $_POST['subject']);
    $account = mysqli_real_escape_string($con, $_POST['account']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $writer = mysqli_real_escape_string($con, $_POST['writer']);
    $writerEmail = mysqli_real_escape_string($con, $_POST['email']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $due_date = mysqli_real_escape_string($con, $_POST['due_date']);
    $cpp = mysqli_real_escape_string($con, $_POST['cpp']);
    $pages = mysqli_real_escape_string($con, $_POST['pages']);
    $is_confirmed = mysqli_real_escape_string($con, $_POST['is_confirmed']);

    // Handle existing file paths
    $existingFiles = $_POST['existingFiles'] ?? []; // Default to an empty array if not set

    // Process uploaded files (assume your file upload logic here adds file paths to $uploadedFiles)
    $uploadedFiles = json_decode($_POST['uploadedFiles'], true) ?? [];
    $uploadedFileNames = array_map('basename', $uploadedFiles);

    // Merge existing files with newly uploaded ones
    $allFiles = array_merge($existingFiles, $uploadedFileNames);
    $filesString = implode(',', $allFiles); // Convert the array of file names to a comma-separated string

    // Prepare SQL statement with placeholders
    $sql = "UPDATE tbltasks SET topic=?, subject=?, account=?, description=?, writer=?, email=?, status=?, due_date=?, cpp=?, pages=?, is_confirmed=?, task_files=? WHERE id=?";

    if ($stmt = mysqli_prepare($con, $sql)) {
        // Bind parameters and execute statement
        mysqli_stmt_bind_param($stmt, 'ssssssssssssi', $topic, $subject, $account, $description, $writer, $writerEmail, $status, $due_date, $cpp, $pages, $is_confirmed, $filesString, $taskId);

        if (mysqli_stmt_execute($stmt)) {
            // Check if insert was successful
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'Task updated successfully.', 'task_id' => base64_encode($taskId)]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'No changes were made or task not found.']);
            }
        } else {
            // Handle execution error
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_stmt_error($stmt)]);
        }
        mysqli_stmt_close($stmt); // Close statement
    } else {
        // Handle preparation error
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($con)]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No action performed.']);
}
?>
