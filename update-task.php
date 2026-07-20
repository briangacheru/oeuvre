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

    // Was the task already "In Revision" before this save? Used below to
    // decide whether this is a fresh transition into revision (bumps the
    // counter) - checked before the main save so we're comparing against
    // the pre-update state. Kept independent of the main UPDATE below so a
    // missing revision_count column (migration not yet run) can never break
    // saving a task, only skip the revision-tracking bump.
    $wasInRevision = null;
    if ($prevStmt = mysqli_prepare($con, "SELECT status FROM tbltasks WHERE id = ?")) {
        mysqli_stmt_bind_param($prevStmt, 'i', $taskId);
        mysqli_stmt_execute($prevStmt);
        $prevResult = mysqli_stmt_get_result($prevStmt);
        if ($prevRow = mysqli_fetch_assoc($prevResult)) {
            $wasInRevision = ($prevRow['status'] === 'In Revision');
        }
        mysqli_stmt_close($prevStmt);
    }

    // Prepare SQL statement with placeholders
    $sql = "UPDATE tbltasks SET topic=?, subject=?, account=?, description=?, writer=?, email=?, status=?, due_date=?, cpp=?, pages=?, is_confirmed=?, task_files=? WHERE id=?";

    if ($stmt = mysqli_prepare($con, $sql)) {
        // Bind parameters and execute statement
        mysqli_stmt_bind_param($stmt, 'ssssssssssssi', $topic, $subject, $account, $description, $writer, $writerEmail, $status, $due_date, $cpp, $pages, $is_confirmed, $filesString, $taskId);

        if (mysqli_stmt_execute($stmt)) {
            // Check if insert was successful
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                // A fresh transition into "In Revision" starts a new resubmission
                // cycle - bump revision_count so files submitted after this point
                // can be badged "Revision 1", "Revision 2", etc. Separate,
                // best-effort UPDATE: if revision_count doesn't exist yet, this
                // silently no-ops rather than failing the task save above.
                if ($status === 'In Revision' && $wasInRevision === false) {
                    if ($revStmt = mysqli_prepare($con, "UPDATE tbltasks SET revision_count = revision_count + 1 WHERE id = ?")) {
                        mysqli_stmt_bind_param($revStmt, 'i', $taskId);
                        mysqli_stmt_execute($revStmt);
                        mysqli_stmt_close($revStmt);
                    }
                }

                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'Task updated successfully.', 'task_id' => encode_task_id($taskId)]);
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
