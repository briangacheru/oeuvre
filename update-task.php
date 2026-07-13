<?php
include "check-login.php";
csrf_verify_or_json_die();

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
    // Note: string fields below are passed as-is to a parameterized query,
    // which handles escaping itself. Do not mysqli_real_escape_string() these
    // before binding - that double-escapes the value (e.g. a real newline in
    // the description becomes the literal text "\r\n" in storage).
    $taskId = mysqli_real_escape_string($con, $_POST['taskId']);
    $topic = $_POST['topic'];
    $subject = $_POST['subject'];
    $account = $_POST['account'];
    $description = $_POST['description'];
    $writer = $_POST['writer'];
    $writerEmail = $_POST['email'];
    $status = $_POST['status'];
    $due_date = $_POST['due_date'];
    $cpp = $_POST['cpp'];
    $pages = $_POST['pages'];
    $is_confirmed = $_POST['is_confirmed'];

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
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . safe_db_error(mysqli_error($con))]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No action performed.']);
}
?>
