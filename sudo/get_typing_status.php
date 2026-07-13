<?php
// Polled alongside messages for the currently open conversation. Typing
// signal is considered "fresh" for 5 seconds after the sender's last poke.
session_start();
include "dbcon.php";
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (!isset($_SESSION['odmsaid']) || empty($_SESSION['odmsaid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

$partnerId = filter_var($_GET['partner_id'] ?? null, FILTER_VALIDATE_INT);
if ($partnerId === false || $partnerId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid partner ID']);
    exit();
}

try {
    $escapedEmail = mysqli_real_escape_string($con, $_SESSION['odmsaid']);
    $currentUserQuery = mysqli_query($con, "
        SELECT id FROM tbladmin WHERE email = '$escapedEmail'
        UNION
        SELECT id FROM tblwriters WHERE email = '$escapedEmail'
    ");

    if (!$currentUserQuery) {
        throw new Exception('Database query failed: ' . safe_db_error(mysqli_error($con)));
    }

    $currentUser = mysqli_fetch_assoc($currentUserQuery);
    if (!$currentUser) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit();
    }

    $currentUserId = (int)$currentUser['id'];

    $stmt = mysqli_prepare($con, "
        SELECT 1 FROM chat_typing_status
        WHERE sender_id = ? AND receiver_id = ? AND updated_at >= (NOW() - INTERVAL 5 SECOND)
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'ii', $partnerId, $currentUserId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $isTyping = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    echo json_encode(['status' => 'success', 'typing' => $isTyping]);
} catch (Exception $e) {
    error_log('get_typing_status error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
