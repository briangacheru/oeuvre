<?php
include('check-login.php');
check_login();

if(isset($_POST['task_ids']) && is_array($_POST['task_ids'])) {
    $taskIds = $_POST['task_ids'];
    $taskIdsString = implode(',', array_map('intval', $taskIds));

    $query = "UPDATE tbltasks SET is_paid = 1, paid_on = NOW() WHERE id IN ($taskIdsString)";
    if(mysqli_query($con, $query)) {
        $_SESSION['alert'] = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="bi bi-check-circle"></i> Selected tasks marked as paid successfully.
                                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-check-circle"></i> No task was selected.
                                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
    }
}
header('Location: unpaid_tasks.php'); // Redirect back to the tasks page
exit;
?>
