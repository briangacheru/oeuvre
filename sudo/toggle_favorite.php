<?php
include 'check-login.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['task_id'])) {
        $taskId = $_POST['task_id'];

        // Fetch the current favorite status
        $sql = 'SELECT is_favorite FROM tbltasks WHERE id = ?';
        $stmt = $con->prepare($sql);
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $currentStatus = $row['is_favorite'];

        // Toggle the favorite status
        $newStatus = ($currentStatus == 1) ? 0 : 1;

        // Update the favorite status in the database
        $sql = 'UPDATE tbltasks SET is_favorite = ? WHERE id = ?';
        $stmt = $con->prepare($sql);
        $stmt->bind_param('ii', $newStatus, $taskId);
        if ($stmt->execute()) {
            $message = ($newStatus == 1) ? 'Task added to favorites!' : 'Task removed from favorites!';
            echo json_encode([
                'success' => true,
                'is_favorite' => $newStatus,
                'message' => $message
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update favorite status.'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}
?>