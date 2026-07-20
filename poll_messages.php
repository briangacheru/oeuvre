<?php
include "check-login.php"; // Database connection

if (!isset($_SESSION['sessionWriter'])) {
    echo json_encode([]);
    exit();
}

$aid = $_SESSION['sessionWriter'];

// Fetch current user information
$currentUserStmt = mysqli_prepare($con, "
    SELECT id, 'admin' as type FROM tbladmin WHERE email = ?
    UNION
    SELECT id, 'writer' as type FROM tblwriters WHERE email = ?
");
mysqli_stmt_bind_param($currentUserStmt, 'ss', $aid, $aid);
mysqli_stmt_execute($currentUserStmt);
$currentUser = mysqli_fetch_assoc(mysqli_stmt_get_result($currentUserStmt));
$currentUserId = $currentUser['id'];
$currentUserType = $currentUser['type'];

// Get the last message timestamp from the request
$lastTimestamp = isset($_GET['last_timestamp']) ? $_GET['last_timestamp'] : '0000-00-00 00:00:00';

// Fetch new messages
$newMessagesStmt = mysqli_prepare($con, "
    SELECT id, sender_id, sender_type, receiver_id, receiver_type, message, timestamp, file_url, original_file_name, is_read, is_edited, related_task_id
    FROM chat_messages
    WHERE (receiver_id = ? AND receiver_type = ?)
      AND timestamp > ?
      AND is_deleted = 0
    ORDER BY timestamp ASC
");
mysqli_stmt_bind_param($newMessagesStmt, 'iss', $currentUserId, $currentUserType, $lastTimestamp);
mysqli_stmt_execute($newMessagesStmt);
$newMessagesResult = mysqli_stmt_get_result($newMessagesStmt);

$newMessages = [];
while ($message = mysqli_fetch_assoc($newMessagesResult)) {
    $message['encoded_task_id'] = $message['related_task_id'] ? encode_task_id($message['related_task_id']) : null;
    $newMessages[] = $message;
}

echo json_encode($newMessages);
