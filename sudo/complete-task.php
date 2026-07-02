<?php
include('check-login.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $encodedId = $_POST['task_id'];
    $taskId = base64_decode($encodedId);

    // Update the task status to 'Completed'
    $sql = "UPDATE tbltasks SET status = 'Completed', completed_on = NOW() WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $taskId);

    if ($stmt->execute()) {
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
