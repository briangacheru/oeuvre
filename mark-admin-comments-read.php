<?php
include 'check-login.php';
// Check if writer is logged in
if (!isset($_SESSION['sessionWriter'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$writer_email = $_SESSION['sessionWriter'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $task_id = (int)($input['task_id'] ?? 0);

    if ($task_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
        exit();
    }

    try {
        if ($action === 'mark_admin_comments_read') {
            // Verify that this task belongs to the current writer
            $taskCheckQuery = mysqli_query($con, "
                SELECT id FROM tbltasks 
                WHERE id = $task_id 
                AND email = '" . mysqli_real_escape_string($con, $writer_email) . "'
                AND is_deleted = 0
            ");

            if (mysqli_num_rows($taskCheckQuery) === 0) {
                echo json_encode(['success' => false, 'message' => 'Task not found or access denied']);
                exit();
            }

            // Mark admin comments as read for this task
            $updateQuery = mysqli_query($con, "
                UPDATE tbl_task_comments 
                SET is_read = 1 
                WHERE task_id = $task_id 
                AND user_type = 'admin' 
                AND is_read = 0
            ");

            $count = mysqli_affected_rows($con);

            echo json_encode([
                'success' => true,
                'count' => $count,
                'message' => "Marked $count admin comments as read"
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