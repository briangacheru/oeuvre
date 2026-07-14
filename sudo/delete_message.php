<?php
require_once __DIR__ . '/../shared-functions.php';
require_once __DIR__ . '/session-name.php';
session_start();
csrf_verify_or_json_die();
include('dbcon.php');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['odmsaid']) || empty($_SESSION['odmsaid'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

$messageId = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT);

if ($messageId === false || $messageId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid message ID']);
    exit();
}

try {
    $escapedEmail = mysqli_real_escape_string($con, $_SESSION['odmsaid']);
    $senderQuery = mysqli_query($con, "
        SELECT id FROM tbladmin WHERE email = '$escapedEmail'
        UNION
        SELECT id FROM tblwriters WHERE email = '$escapedEmail'
    ");

    if (!$senderQuery) {
        throw new Exception('Database query failed: ' . safe_db_error(mysqli_error($con)));
    }

    $sender = mysqli_fetch_assoc($senderQuery);
    if (!$sender) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Sender not found']);
        exit();
    }

    $senderId = (int)$sender['id'];

    // Only the original sender may delete their own message (soft delete)
    $stmt = mysqli_prepare($con, "
        UPDATE chat_messages
        SET is_deleted = 1
        WHERE id = ? AND sender_id = ? AND is_deleted = 0
    ");
    mysqli_stmt_bind_param($stmt, 'ii', $messageId, $senderId);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Message not found or already deleted']);
    }
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    error_log('delete_message error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
