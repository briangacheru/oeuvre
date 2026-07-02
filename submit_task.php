<?php
include "check-login.php";

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $topic = mysqli_real_escape_string($con, $_POST['topic']);
    $subject = mysqli_real_escape_string($con, $_POST['subject']);
    $account = mysqli_real_escape_string($con, $_POST['account']);
    $pages = mysqli_real_escape_string($con, $_POST['pages']);
    $cpp = mysqli_real_escape_string($con, $_POST['cpp']);
    $due_date = mysqli_real_escape_string($con, $_POST['due_date']);
    $is_confirmed = mysqli_real_escape_string($con, $_POST['is_confirmed']);
    $writer = mysqli_real_escape_string($con, explode("|", $_POST['writer'])[0]); // Assuming you only need the writer's name
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    // $task_files will be handled by the JavaScript and AJAX

    // SQL query to insert form data into tbltasks
    $sql = "INSERT INTO tbltasks (topic, subject, account, pages, cpp, due_date, is_confirmed, writer, email, description) VALUES ('$topic', '$subject', '$account', '$pages', '$cpp', '$due_date', '$is_confirmed', '$writer', '$email', '$description')";

    // Execute the query
    if (mysqli_query($con, $sql)) {
        // Success
        $task_id = mysqli_insert_id($con); // Get the ID of the inserted task
        echo "Task created successfully. Task ID: " . $task_id;
    } else {
        // Error handling
        echo "Error: " . $sql . "<br>" . mysqli_error($con);
    }
}
?>
