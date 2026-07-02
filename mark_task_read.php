<?php
include('check-login.php');

header('Content-Type: application/json');

if (isset($_POST['task_id'])) {
    $taskId = intval($_POST['task_id']);
    $aid = $_SESSION['sessionWriter'];
    $acknowledged_at = date('Y-m-d H:i:s');

    $query = 'UPDATE tbltasks SET acknowledged = 1, acknowledged_at = ? WHERE id = ? AND email = ?';
    $stmt = mysqli_prepare($con, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sis', $acknowledged_at, $taskId, $aid);
        $result = mysqli_stmt_execute($stmt);

        if ($result) {
            echo json_encode([
                'success' => true,
                'acknowledged_at' => $acknowledged_at
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database update failed']);
        }

        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to prepare statement']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No task ID provided']);
}
?>