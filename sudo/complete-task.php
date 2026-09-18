<?php
include('check-login.php');
csrf_verify_or_redirect();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $encodedId = $_POST['task_id'];
    $taskId = decode_task_id($encodedId);

    // Update the task status to 'Completed'
    // NOW() reflects the DB server's own timezone, not PHP's Africa/Nairobi
    // setting (see check-login.php), so the timestamp is computed here instead
    // (matches mark-inprogress-complete.php / mark-tasks-completed.php).
    $completedOn = date('Y-m-d H:i:s');
    $sql = "UPDATE tbltasks SET status = 'Completed', completed_on = ? WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("si", $completedOn, $taskId);

    if ($stmt->execute()) {
        if (function_exists('log_activity')) {
            log_activity($con, 'admin', $_SESSION['odmsaid'] ?? '', 'task_completed', "Task #$taskId: marked as completed", $taskId);
        }
        // Optional quality rating picked in the Complete Task modal on
        // sudo/view-task.php (can also be set/changed later via rate-task.php).
        $qualityRating = intval($_POST['quality_rating'] ?? 0);
        if ($qualityRating >= 1 && $qualityRating <= 5) {
            include_once('writer-performance-functions.php');
            saveTaskQualityRating($con, $taskId, $qualityRating, (string) ($_POST['quality_note'] ?? ''), $_SESSION['odmsaid'] ?? '');
        }
        $_SESSION['alert'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                Task completed successfully.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Error updating record: ' . $stmt->error . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
    }
    $stmt->close();
}

header('Location: view-task?task_id=' . $encodedId); // Redirect to the task details page with the encoded task ID
exit;
?>
