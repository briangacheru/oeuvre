<?php
// Lightweight poll target: for the currently open conversation, returns the
// IDs of messages *I* sent to that partner which are now marked read, so the
// sender's UI can flip single ticks to double green ticks without a full
// conversation reload.
include "check-login.php";
header('Content-Type: application/json');

if (!isset($_SESSION['sessionWriter']) && !isset($_SESSION['odmsaid'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$partnerId = filter_var($_GET['partner_id'] ?? null, FILTER_VALIDATE_INT);
if ($partnerId === false || $partnerId <= 0) {
    echo json_encode(['error' => 'Invalid partner ID']);
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
    echo json_encode(['error' => 'User not found']);
    exit();
}

$currentUserId = (int)$currentUser['id'];

$stmt = mysqli_prepare($con, "
    SELECT id FROM chat_messages
    WHERE sender_id = ? AND receiver_id = ? AND is_read = 1
");
mysqli_stmt_bind_param($stmt, 'ii', $currentUserId, $partnerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$readIds = [];
while ($row = mysqli_fetch_assoc($result)) {
    $readIds[] = (int)$row['id'];
}

echo json_encode(['status' => 'success', 'read_ids' => $readIds]);
