<?php
include('check-login.php');
csrf_verify_or_redirect();

require_once __DIR__ . '/session-name.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $encodedId = $_POST['task_id'];
    $taskId = decode_task_id($encodedId);

    // Update the task status to 'Completed'
    // NOW() reflects the DB server's own timezone, not PHP's Africa/Nairobi
    // setting (see check-login.php), so the timestamp is computed here instead.
    $paidOn = date('Y-m-d H:i:s');
    $sql = "UPDATE tbltasks SET  is_paid = 1, paid_on = ? WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("si", $paidOn, $taskId);

    if ($stmt->execute()) {
        $_SESSION['alert'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                Task marked as paid successfully.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Error updating task payment: ' . $stmt->error . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
    }
    $stmt->close();
}

header('Location: view-task?task_id=' . $encodedId); // Redirect to the task details page with the encoded task ID
exit;
?>
