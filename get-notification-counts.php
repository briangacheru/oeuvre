<?php
session_start();
include('db.php');
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['sessionWriter'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$aid = $_SESSION['sessionWriter'];

try {
    // Get task notifications count (new submitted tasks + late tasks)
    $newTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS new_task_count FROM tbltasks WHERE is_deleted = 0 AND status IN ('In Progress', 'Unconfirmed', 'In Revision') AND acknowledged = 0");
    $newTasksCountResult = mysqli_fetch_assoc($newTasksCountQuery);
    $newTasksCount = $newTasksCountResult['new_task_count'];

    $lateTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS late_task_count FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND due_date < NOW()");
    $lateTasksCountResult = mysqli_fetch_assoc($lateTasksCountQuery);
    $lateTasksCount = $lateTasksCountResult['late_task_count'];

    $totalTaskNotifications = $newTasksCount + $lateTasksCount;

    // Get unread messages count
    $userQuery = mysqli_query($con, "SELECT id FROM tblwriters WHERE email = '$aid'");
    $userResult = mysqli_fetch_assoc($userQuery);
    $userID = $userResult['id'];
    $unreadMessagesCountQuery = mysqli_query($con, "SELECT COUNT(*) AS unread_count FROM chat_messages WHERE is_read = 0 AND receiver_id = '$userID'");

    $unreadMessagesCountResult = mysqli_fetch_assoc($unreadMessagesCountQuery);
    $unreadMessagesCount = $unreadMessagesCountResult['unread_count'] ?? 0;

    // Get unread comments count
    $unreadCommentsCountQuery = mysqli_query($con, "
        SELECT COUNT(*) AS unread_comments_count FROM tbl_task_comments tc 
        JOIN tbltasks t ON tc.task_id = t.id WHERE t.email = '$aid' 
        AND tc.user_type = 'admin' AND tc.is_read = 0");
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