<?php
session_start();
include "dbcon.php";
header('Content-Type: application/json');

// Validate input
if (!isset($_GET['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
    exit();
}

$userId = filter_var($_GET['user_id'], FILTER_VALIDATE_INT);

if ($userId === false || $userId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit();
}

// Check authentication
if (!isset($_SESSION['odmsaid']) || empty($_SESSION['odmsaid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

$currentUserEmail = $_SESSION['odmsaid'];

try {
    // Get current user ID
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
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit();
    }

    $currentUserId = intval($currentUser['id']);

    // Update read status
    $updateQuery = mysqli_query($con, "
        UPDATE chat_messages 
        SET is_read = 1 
        WHERE receiver_id = $currentUserId 
          AND sender_id = $userId 
          AND is_read = 0
    ");

    if (!$updateQuery) {
        throw new Exception('Database update failed: ' . mysqli_error($con));
    }

    $affectedRows = mysqli_affected_rows($con);

    echo json_encode([
        'status' => 'success',
        'messages_updated' => $affectedRows
    ]);

} catch (Exception $e) {
    error_log('Update read status error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
?>