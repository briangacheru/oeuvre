<?php
// Poked by the client (debounced) while the user is actively typing into the
// message box. No CSRF check - matches the existing lightweight, session-
// auth-only convention used by poll_messages.php/update_read_status.php for
// this class of frequent, non-destructive signal.
session_start();
include "dbcon.php";
header('Content-Type: application/json');

if (!isset($_SESSION['odmsaid']) || empty($_SESSION['odmsaid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

$receiverId = filter_var($_POST['receiver_id'] ?? null, FILTER_VALIDATE_INT);
$receiverType = trim($_POST['receiver_type'] ?? '');

if ($receiverId === false || $receiverId <= 0 || !in_array($receiverType, ['admin', 'writer'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid receiver']);
    exit();
}

try {
    $escapedEmail = mysqli_real_escape_string($con, $_SESSION['odmsaid']);
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
    mysqli_stmt_close($stmt);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    error_log('set_typing_status error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
