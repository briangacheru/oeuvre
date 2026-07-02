<?php
include 'check-login.php'; // Ensure this includes your database connection settings

if (isset($_GET['task_id'])) {
    $taskId = base64_decode($_GET['task_id']);

    // Fetch the original task
    $query = "SELECT * FROM tbltasks WHERE id='$taskId'";
    $result = mysqli_query($con, $query);

    if ($row = mysqli_fetch_assoc($result)) {
        // Exclude the ID from the columns to duplicate
        $topic = mysqli_real_escape_string($con, $row['topic']);
        $subject = mysqli_real_escape_string($con, $row['subject']);
        $account = mysqli_real_escape_string($con, $row['account']);
        $description = mysqli_real_escape_string($con, $row['description']);
        $due_date = mysqli_real_escape_string($con, $row['due_date']);
        $cpp = mysqli_real_escape_string($con, $row['cpp']);
        $pages = mysqli_real_escape_string($con, $row['pages']);
        $is_confirmed = mysqli_real_escape_string($con, $row['is_confirmed']);
        $is_duplicate = 1; // Set the is_duplicate flag to 1
        $status = 'Draft'; // Set status to Draft
        $original_task_id = $taskId; // Store the original task ID

        // Insert the duplicate task with status set to Draft and original_task_id
        // writer and email are intentionally set to NULL
        $duplicateQuery = "INSERT INTO tbltasks (topic, subject, account, description, writer, email, due_date, cpp, pages, is_confirmed, is_duplicate, original_task_id, status) 
                           VALUES ('$topic', '$subject', '$account', '$description', NULL, NULL, '$due_date', '$cpp', '$pages', '$is_confirmed', '$is_duplicate', '$original_task_id', '$status')";

        if (mysqli_query($con, $duplicateQuery)) {
            $newTaskId = mysqli_insert_id($con); // Get the ID of the new task

            // Now duplicate the file records from tbl_task_files
            $filesQuery = "SELECT * FROM tbl_task_files WHERE task_id = ? AND is_deleted = 0";
            $stmt = mysqli_prepare($con, $filesQuery);
            mysqli_stmt_bind_param($stmt, 'i', $taskId);
            mysqli_stmt_execute($stmt);
            $filesResult = mysqli_stmt_get_result($stmt);

            $filesCopied = 0;
            $filesErrors = 0;

            while ($fileRow = mysqli_fetch_assoc($filesResult)) {
                // Insert a duplicate file record with the new task_id
                $insertFileQuery = "INSERT INTO tbl_task_files (task_id, file_name, original_file_name, file_path, file_url, file_size, file_type, upload_time, uploaded_by, is_deleted) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 0)";

                $fileStmt = mysqli_prepare($con, $insertFileQuery);
                mysqli_stmt_bind_param($fileStmt, 'isssssss',
                    $newTaskId,
                    $fileRow['file_name'],
                    $fileRow['original_file_name'],
                    $fileRow['file_path'],
                    $fileRow['file_url'],
                    $fileRow['file_size'],
                    $fileRow['file_type'],
                    $fileRow['uploaded_by']
                );

                if (mysqli_stmt_execute($fileStmt)) {
                    $filesCopied++;
                } else {
                    $filesErrors++;
                }

                mysqli_stmt_close($fileStmt);
            }

            mysqli_stmt_close($stmt);

            $encodedId = base64_encode($newTaskId);

            // Build success message
            $successMessage = "Task duplicated successfully!";
            if ($filesCopied > 0) {
                $successMessage .= " $filesCopied file(s) linked.";
            }
            if ($filesErrors > 0) {
                $successMessage .= " However, $filesErrors file(s) could not be linked.";
            }

            // Set success message
            $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
                <div class="bg-success me-3 icon-item"><span class="fas fa-check-circle text-white fs-6"></span></div>
                <p class="mb-0 flex-1">' . htmlspecialchars($successMessage) . '</p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';

            header("Location: view-task?task_id=$encodedId"); // Redirect to the new task
            exit;
        } else {
            // Set error message
            $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
                <p class="mb-0 flex-1">Error duplicating task: ' . mysqli_error($con) . '</p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';

            header("Location: view-task?task_id=" . base64_encode($taskId)); // Redirect back to original task
            exit;
        }
    } else {
        // Set error message
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Original task not found.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';

        header("Location: index"); // Redirect to dashboard
        exit;
    }
} else {
    // Set error message
    $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
        <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
        <p class="mb-0 flex-1">No task ID provided.</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';

    header("Location: index"); // Redirect to dashboard
    exit;
}
?>