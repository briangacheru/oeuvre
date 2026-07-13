<?php
session_start();
include "dbcon.php";
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check authentication
if (!isset($_SESSION['odmsaid']) || empty($_SESSION['odmsaid'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$aid = $_SESSION['odmsaid'];

// Validate and sanitize input - FIXED: Removed deprecated filter
$lastTimestamp = trim($_GET['last_timestamp'] ?? '0000-00-00 00:00:00');

// Validate timestamp format
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $lastTimestamp)) {
    $lastTimestamp = '0000-00-00 00:00:00';
}

try {
    // Get current user information
    $escapedEmail = mysqli_real_escape_string($con, $aid);
    $currentUserQuery = mysqli_query($con, "
        SELECT id, 'admin' as type FROM tbladmin WHERE email = '$escapedEmail'
        UNION 
        SELECT id, 'writer' as type FROM tblwriters WHERE email = '$escapedEmail'
    ");

    if (!$currentUserQuery) {
        throw new Exception('Database query failed: ' . safe_db_error(mysqli_error($con)));
    }

    $currentUser = mysqli_fetch_assoc($currentUserQuery);

    if (!$currentUser) {
        echo json_encode(['error' => 'User not found']);
        exit();
    }

    $currentUserId = intval($currentUser['id']);
    $currentUserType = $currentUser['type'];

    // Escape timestamp for safety
    $escapedTimestamp = mysqli_real_escape_string($con, $lastTimestamp);

    // Fetch new messages
    $newMessagesQuery = mysqli_query($con, "
        SELECT id, sender_id, sender_type, receiver_id, receiver_type, message, timestamp, file_url, is_read, is_edited, related_task_id
        FROM chat_messages
        WHERE receiver_id = $currentUserId
          AND receiver_type = '$currentUserType'
          AND timestamp > '$escapedTimestamp'
          AND is_deleted = 0
        ORDER BY timestamp ASC
        LIMIT 50
    ");

    if (!$newMessagesQuery) {
        throw new Exception('Database query failed: ' . safe_db_error(mysqli_error($con)));
    }

    $newMessages = [];
    while ($message = mysqli_fetch_assoc($newMessagesQuery)) {
        $newMessages[] = [
            'id' => intval($message['id']),
            'sender_id' => intval($message['sender_id']),
            'sender_type' => $message['sender_type'],
            'receiver_id' => intval($message['receiver_id']),
            'receiver_type' => $message['receiver_type'],
            'message' => $message['message'] ?? '',
            'timestamp' => $message['timestamp'],
            'file_url' => $message['file_url'],
            'is_read' => intval($message['is_read']) ? true : false,
            'is_edited' => intval($message['is_edited']) ? true : false,
            'related_task_id' => $message['related_task_id'] ? intval($message['related_task_id']) : null
        ];
    }

    echo json_encode($newMessages);

} catch (Exception $e) {
    error_log('Poll messages error: ' . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}
?>