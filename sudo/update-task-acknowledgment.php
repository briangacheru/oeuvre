<?php
session_start();
include 'dbcon.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id']) && isset($_POST['acknowledged'])) {
    $taskId = intval($_POST['task_id']);
    $acknowledged = intval($_POST['acknowledged']);

    // Validate acknowledged value (should be 0 or 1)
    if ($acknowledged !== 0 && $acknowledged !== 1) {
        echo json_encode(['success' => false, 'error' => 'Invalid acknowledged value']);
        exit;
    }

    if ($taskId > 0) {
        $sql = "UPDATE tbltasks SET admin_acknowledged = ? WHERE id = ? AND status = 'Submitted'";
        $stmt = mysqli_prepare($con, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $acknowledged, $taskId);

            if (mysqli_stmt_execute($stmt)) {
                $affectedRows = mysqli_stmt_affected_rows($stmt);
                if ($affectedRows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Task acknowledgment updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'No task found or no changes made']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . mysqli_error($con)]);
            }
            mysqli_stmt_close($stmt);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database preparation error: ' . mysqli_error($con)]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid task ID']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request parameters']);
}
?>