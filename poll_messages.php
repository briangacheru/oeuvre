<?php
include "check-login.php"; // Database connection

if (!isset($_SESSION['sessionWriter'])) {
    echo json_encode([]);
    exit();
}

$aid = $_SESSION['sessionWriter'];

// Fetch current user information
$currentUserQuery = mysqli_query($con, "
    SELECT id, 'admin' as type FROM tbladmin WHERE email = '$aid'
    UNION 
    SELECT id, 'writer' as type FROM tblwriters WHERE email = '$aid'
");

$currentUser = mysqli_fetch_assoc($currentUserQuery);
$currentUserId = $currentUser['id'];
$currentUserType = $currentUser['type'];

// Get the last message timestamp from the request
$lastTimestamp = isset($_GET['last_timestamp']) ? $_GET['last_timestamp'] : '0000-00-00 00:00:00';

// Fetch new messages
$newMessagesQuery = mysqli_query($con, "
    SELECT sender_id, sender_type, receiver_id, receiver_type, message, timestamp, file_url
    FROM chat_messages 
    WHERE (receiver_id = $currentUserId AND receiver_type = '$currentUserType') 
      AND timestamp > '$lastTimestamp'
    ORDER BY timestamp ASC
");

$newMessages = [];
while ($message = mysqli_fetch_assoc($newMessagesQuery)) {
    $newMessages[] = $message;
}

echo json_encode($newMessages);
