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
    $topic = mysqli_real_escape_string($con, $_POST['topic']);
    $subject = mysqli_real_escape_string($con, $_POST['subject']);
    $account = mysqli_real_escape_string($con, $_POST['account']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $writer = mysqli_real_escape_string($con, $_POST['writer']);
    $writerEmail = mysqli_real_escape_string($con, $_POST['email']);
    $due_date = mysqli_real_escape_string($con, $_POST['due_date']);
    $cpp = mysqli_real_escape_string($con, $_POST['cpp']);
    $pages = mysqli_real_escape_string($con, $_POST['pages']);
    $is_confirmed = mysqli_real_escape_string($con, $_POST['is_confirmed']);

    $uploadedFiles = json_decode($_POST['uploadedFiles'], true);
    $fileNames = array_map(function($filePath) {
        return basename($filePath);
    }, $uploadedFiles);

    $filesString = implode(',', $fileNames);

    $sql = "INSERT INTO tbltasks (topic, subject, account, description, writer, email, due_date, cpp, pages, is_confirmed, task_files) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 'sssssssssss', $topic, $subject, $account, $description, $writer, $writerEmail, $due_date, $cpp, $pages, $is_confirmed, $filesString);

        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                header('Content-Type: application/json');
                $task_id = mysqli_insert_id($con); // This gets the last inserted ID
                $encodedId = base64_encode((string)$task_id);
                echo json_encode(['status' => 'success', 'message' => 'Task created successfully.', 'task_id' => $encodedId]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Failed to create task.']);
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
