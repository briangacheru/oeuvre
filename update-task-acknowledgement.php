<?php
include 'check-login.php';

header('Content-Type: application/json');

if (isset($_POST['task_id']) && isset($_POST['acknowledged'])) {
    $task_id = intval($_POST['task_id']);
    $acknowledged = intval($_POST['acknowledged']);
    $acknowledged_at = date('Y-m-d H:i:s');

    $query = 'UPDATE tbltasks SET acknowledged = ?, acknowledged_at = ? WHERE id = ?';
    $stmt = mysqli_prepare($con, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'isi', $acknowledged, $acknowledged_at, $task_id);
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
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
}
?>