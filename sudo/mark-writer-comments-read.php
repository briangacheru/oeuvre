<?php
include 'check-login.php';
if (!isset($_SESSION['odmsaid'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$email = $_SESSION['odmsaid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $task_id = (int)($input['task_id'] ?? 0);

    if ($task_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
        exit();
    }

    try {
        if ($action === 'mark_writer_comments_read') {
            // Mark writer comments as read for this task
            $updateQuery = mysqli_query($con, "
                UPDATE tbl_task_comments 
                SET is_read = 1 
                WHERE task_id = $task_id 
                AND user_type = 'writer' 
                AND is_read = 0
            ");

            $count = mysqli_affected_rows($con);

            echo json_encode([
                'success' => true,
                'count' => $count,
                'message' => "Marked $count writer comments as read"
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>