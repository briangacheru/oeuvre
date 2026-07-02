<?php
session_start();
include "dbcon.php";
header('Content-Type: application/json');

// Basic input validation
if (!isset($_GET['user_id']) || !isset($_GET['user_type'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

$userId = intval($_GET['user_id']);
$userType = trim($_GET['user_type']); // Removed deprecated FILTER_SANITIZE_STRING
$currentUserEmail = $_SESSION['odmsaid'] ?? '';

// Validate inputs
if ($userId <= 0) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

if (!in_array($userType, ['admin', 'writer'])) {
    echo json_encode(['error' => 'Invalid user type']);
    exit();
}

if (empty($currentUserEmail)) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

try {
    // Get current user ID with escaped string
    $escapedEmail = mysqli_real_escape_string($con, $currentUserEmail);
    $currentUserQuery = mysqli_query($con, "
        SELECT id FROM tbladmin WHERE email = '$escapedEmail'
        UNION
        SELECT id FROM tblwriters WHERE email = '$escapedEmail'
    ");

    if (!$currentUserQuery) {
        throw new Exception('Database query failed: ' . mysqli_error($con));
    }

    $currentUser = mysqli_fetch_assoc($currentUserQuery);

    if (!$currentUser) {
        echo json_encode(['error' => 'User not found']);
        exit();
    }

    $currentUserId = intval($currentUser['id']);

    // Fetch messages
    $messagesQuery = mysqli_query($con, "
        SELECT sender_id, receiver_id, message, timestamp, file_url, is_read 
        FROM chat_messages 
        WHERE (sender_id = $userId AND receiver_id = $currentUserId)
           OR (receiver_id = $userId AND sender_id = $currentUserId)
        ORDER BY timestamp ASC
        LIMIT 100
    ");

    if (!$messagesQuery) {
        throw new Exception('Failed to fetch messages: ' . mysqli_error($con));
    }

    $messages = [];
    while ($message = mysqli_fetch_assoc($messagesQuery)) {
        $messages[] = [
            'sender_id' => intval($message['sender_id']),
            'receiver_id' => intval($message['receiver_id']),
            'message' => $message['message'] ?? '',
            'timestamp' => $message['timestamp'],
            'file_url' => $message['file_url'],
            'is_read' => intval($message['is_read']) ? true : false
        ];
    }

    echo json_encode($messages);

} catch (Exception $e) {
    error_log('Fetch messages error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to load messages']);
}
?>