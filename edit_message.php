<?php
include "check-login.php";
csrf_verify_or_json_die();
header('Content-Type: application/json');

if (!isset($_SESSION['sessionWriter']) && !isset($_SESSION['odmsaid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

$messageId = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT);
$newMessage = trim($_POST['message'] ?? '');

if ($messageId === false || $messageId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid message ID']);
    exit();
}

if ($newMessage === '') {
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
    exit();
}

if (strlen($newMessage) > 1000) {
    echo json_encode(['status' => 'error', 'message' => 'Message too long (max 1000 characters)']);
    exit();
}

$currentUserEmail = $_SESSION['sessionWriter'] ?? $_SESSION['odmsaid'];

$senderQuery = mysqli_prepare($con, "
    SELECT id FROM tbladmin WHERE email = ?
    UNION
    SELECT id FROM tblwriters WHERE email = ?
");
mysqli_stmt_bind_param($senderQuery, 'ss', $currentUserEmail, $currentUserEmail);
mysqli_stmt_execute($senderQuery);
$sender = mysqli_fetch_assoc(mysqli_stmt_get_result($senderQuery));

if (!$sender) {
    echo json_encode(['status' => 'error', 'message' => 'Sender not found']);
    exit();
}

$senderId = (int)$sender['id'];

// Only the original sender may edit, and only while not already deleted
$stmt = mysqli_prepare($con, "
    UPDATE chat_messages
    SET message = ?, is_edited = 1, edited_at = NOW()
    WHERE id = ? AND sender_id = ? AND is_deleted = 0
");
mysqli_stmt_bind_param($stmt, 'sii', $newMessage, $messageId, $senderId);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Message not found or not editable']);
}
