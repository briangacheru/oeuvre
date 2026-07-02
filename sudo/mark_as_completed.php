<?php
include('check-login.php');
check_login();

if($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['task_ids'])) {
    foreach($_POST['task_ids'] as $task_id) {
        // Update task status to 'Completed'
        $query = mysqli_query($con, "UPDATE tbltasks SET status = 'Completed', completed_on = NOW() WHERE id='".mysqli_real_escape_string($con, $task_id)."'");
    }
    $_SESSION['alert'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">Selected tasks have been marked as completed.
                           <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
    header('Location: inprogress_tasks.php'); // Redirect back to the tasks page
    exit;
} else {
    // Handle the case where no task was selected
    $_SESSION['alert'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">No task was selected.
                           <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
    header('Location: inprogress_tasks.php');
    exit;
}
?>
