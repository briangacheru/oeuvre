<?php
include('check-login.php');

header('Content-Type: application/json');

if (isset($_POST['task_id'])) {
    $taskId = intval($_POST['task_id']);

    if (!$con) {
        echo json_encode(['success' => false, 'error' => 'Database connection not established']);
        exit;
    }

    $query = "UPDATE tbltasks SET admin_acknowledged = 1 WHERE id = $taskId";
    $result = mysqli_query($con, $query);

    if ($result) {
        $affectedRows = mysqli_affected_rows($con);
        if ($affectedRows > 0) {
            echo json_encode(['success' => true, 'message' => 'Task marked as read']);
        } else {
            echo json_encode(['success' => false, 'error' => 'No rows updated (maybe already acknowledged or invalid ID)']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($con)]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No task ID provided']);
}
?>