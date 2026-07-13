<?php
// Poked by the client (debounced) while the user is actively typing into the
// message box. No CSRF check - matches the existing lightweight, session-
// auth-only convention used by poll_messages.php/update_read_status.php for
// this class of frequent, non-destructive signal.
include "check-login.php";
header('Content-Type: application/json');

if (!isset($_SESSION['sessionWriter']) && !isset($_SESSION['odmsaid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

$receiverId = filter_var($_POST['receiver_id'] ?? null, FILTER_VALIDATE_INT);
$receiverType = trim($_POST['receiver_type'] ?? '');

if ($receiverId === false || $receiverId <= 0 || !in_array($receiverType, ['admin', 'writer'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid receiver']);
    exit();
}

$currentUserEmail = $_SESSION['sessionWriter'] ?? $_SESSION['odmsaid'];

$currentUserIdQuery = mysqli_prepare($con, "
    SELECT id, 'admin' as type FROM tbladmin WHERE email = ?
    UNION
    SELECT id, 'writer' as type FROM tblwriters WHERE email = ?
");
mysqli_stmt_bind_param($currentUserIdQuery, 'ss', $currentUserEmail, $currentUserEmail);
mysqli_stmt_execute($currentUserIdQuery);
$currentUser = mysqli_fetch_assoc(mysqli_stmt_get_result($currentUserIdQuery));

if (!$currentUser) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit();
}

$senderId = (int)$currentUser['id'];
$senderType = $currentUser['type'];

$stmt = mysqli_prepare($con, "
    INSERT INTO chat_typing_status (sender_id, sender_type, receiver_id, receiver_type, updated_at)
    VALUES (?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE updated_at = NOW()
");
mysqli_stmt_bind_param($stmt, 'isis', $senderId, $senderType, $receiverId, $receiverType);
mysqli_stmt_execute($stmt);

echo json_encode(['status' => 'success']);
