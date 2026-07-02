<?php
session_start();
include('db.php');
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['odmsaid'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$aid = $_SESSION['odmsaid'];

try {
    // Get task notifications count (new submitted tasks + late tasks)
    $newTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS new_task_count FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted' AND admin_acknowledged = 0");
    $newTasksCountResult = mysqli_fetch_assoc($newTasksCountQuery);
    $newTasksCount = $newTasksCountResult['new_task_count'];

    $lateTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS late_task_count FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND due_date < NOW()");
    $lateTasksCountResult = mysqli_fetch_assoc($lateTasksCountQuery);
    $lateTasksCount = $lateTasksCountResult['late_task_count'];

    $totalTaskNotifications = $newTasksCount + $lateTasksCount;

    // Get unread messages count
    $unreadMessagesCountQuery = mysqli_query($con, "
        SELECT COUNT(*) as unread_count 
        FROM chat_messages 
        WHERE receiver_id = 1 
        AND sender_id != 1 
        AND is_read = 0
    ");
    $unreadMessagesCountResult = mysqli_fetch_assoc($unreadMessagesCountQuery);
    $unreadMessagesCount = $unreadMessagesCountResult['unread_count'];

    // Get unread comments count
    $unreadCommentsCountQuery = mysqli_query($con, "
        SELECT COUNT(*) AS unread_comments_count 
        FROM tbl_task_comments 
        WHERE user_type = 'writer' 
        AND is_read = 0
    ");
    $unreadCommentsCountResult = mysqli_fetch_assoc($unreadCommentsCountQuery);
    $unreadCommentsCount = $unreadCommentsCountResult['unread_comments_count'];

    // Return counts as JSON
    echo json_encode([
        'success' => true,
        'tasks' => $totalTaskNotifications,
        'messages' => $unreadMessagesCount,
        'comments' => $unreadCommentsCount,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>