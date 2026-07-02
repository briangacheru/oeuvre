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
        $writer = mysqli_real_escape_string($con, $row['writer']);
        $writerEmail = mysqli_real_escape_string($con, $row['email']);
        $due_date = mysqli_real_escape_string($con, $row['due_date']);
        $cpp = mysqli_real_escape_string($con, $row['cpp']);
        $pages = mysqli_real_escape_string($con, $row['pages']);
        $is_confirmed = mysqli_real_escape_string($con, $row['is_confirmed']);
        $filesString = mysqli_real_escape_string($con, $row['task_files']);

        // Insert the duplicate task
        $duplicateQuery = "INSERT INTO tbltasks (topic, subject, account, description, writer, email, due_date, cpp, pages, is_confirmed, task_files) 
                                VALUES ('$topic', '$subject', '$account', '$description', '$writer', '$writerEmail', '$due_date', '$cpp', '$pages', '$is_confirmed', '$filesString' )";
        if (mysqli_query($con, $duplicateQuery)) {
            $newTaskId = mysqli_insert_id($con); // Get the ID of the new task
            $encodedId = base64_encode($newTaskId);
            header("Location: view-task.php?task_id=$encodedId&message=Task duplicated successfully"); // Redirect to the new task
            exit;
        } else {
            echo "Error duplicating task: " . mysqli_error($con);
        }
    } else {
        echo "Original task not found.";
    }
} else {
    echo "No task ID provided.";
}

?>
