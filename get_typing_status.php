<?php
// Polled alongside messages for the currently open conversation. Typing
// signal is considered "fresh" for 5 seconds after the sender's last poke.
include "check-login.php";
header('Content-Type: application/json');

if (!isset($_SESSION['sessionWriter']) && !isset($_SESSION['odmsaid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

$partnerId = filter_var($_GET['partner_id'] ?? null, FILTER_VALIDATE_INT);
if ($partnerId === false || $partnerId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid partner ID']);
    exit();
}

$currentUserEmail = $_SESSION['sessionWriter'] ?? $_SESSION['odmsaid'];

$currentUserIdQuery = mysqli_prepare($con, "
    SELECT id FROM tbladmin WHERE email = ?
    UNION
    SELECT id FROM tblwriters WHERE email = ?
");
mysqli_stmt_bind_param($currentUserIdQuery, 'ss', $currentUserEmail, $currentUserEmail);
mysqli_stmt_execute($currentUserIdQuery);
$currentUser = mysqli_fetch_assoc(mysqli_stmt_get_result($currentUserIdQuery));

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

echo json_encode(['status' => 'success', 'typing' => mysqli_num_rows($result) > 0]);
