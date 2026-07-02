<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'dbcon.php';

try {
    if (!isset($_SESSION['odmsaid'])) {
        throw new Exception('Not authenticated');
    }

    $aid = $_SESSION['odmsaid'];

    $sql = 'SELECT * FROM tbladmin WHERE email=:aid';
    $query = $dbh->prepare($sql);
    $query->bindParam(':aid', $aid, PDO::PARAM_STR);
    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_OBJ);
    $isAdmin = ($query->rowCount() > 0 && $results[0]->AdminName == 'Admin');

    if (!$isAdmin) {
        throw new Exception('Not authorized');
    }

    // Initialize counts
    $taskCount = 0;
    $messageCount = 0;
    $commentCount = 0;

    // Count new submitted tasks (not yet acknowledged)
    $newTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS new_task_count FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted' AND admin_acknowledged = 0");
    $newTasksCountResult = mysqli_fetch_assoc($newTasksCountQuery);
    $newTasksCount = $newTasksCountResult['new_task_count'];

    // Count late tasks
    $lateTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS late_task_count FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND due_date < NOW()");
    $lateTasksCountResult = mysqli_fetch_assoc($lateTasksCountQuery);
    $lateTasksCount = $lateTasksCountResult['late_task_count'];

    $taskCount = $newTasksCount + $lateTasksCount;

    // Count unread messages (you'll need to define this query based on your messages table)
    $unreadMessagesQuery = mysqli_query($con, "SELECT COUNT(*) AS count FROM chat_messages WHERE is_read = 0 AND receiver_id = '{$_SESSION['odmsaid']}'");
    if ($unreadMessagesQuery) {
        $unreadMessagesResult = mysqli_fetch_assoc($unreadMessagesQuery);
        $messageCount = $unreadMessagesResult ? $unreadMessagesResult['count'] : 0;
    } else {
        $messageCount = 0;
    }

    // Count unread comments from writers
    $unreadCommentsCountQuery = mysqli_query($con, "
        SELECT COUNT(*) AS unread_comments_count 
        FROM tbl_task_comments 
        WHERE user_type = 'writer' 
        AND is_read = 0
    ");
    $unreadCommentsCountResult = mysqli_fetch_assoc($unreadCommentsCountQuery);
    $commentCount = $unreadCommentsCountResult['unread_comments_count'];

    // Return counts as JSON
    $response = [
        'success' => true,
        'task_count' => $taskCount,
        'message_count' => $messageCount,
        'comment_count' => $commentCount,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    echo json_encode($response);

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    echo json_encode($response);
}
?>