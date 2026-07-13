<?php
// Lightweight poll target: for the currently open conversation, returns the
// current text of every edited message (either party), so an edit made by
// one side shows up on the other side's screen without a full reload.
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
    SELECT id, message FROM chat_messages
    WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
      AND is_edited = 1 AND is_deleted = 0
");
mysqli_stmt_bind_param($stmt, 'iiii', $currentUserId, $partnerId, $partnerId, $currentUserId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$edits = [];
while ($row = mysqli_fetch_assoc($result)) {
    $edits[] = ['id' => (int)$row['id'], 'message' => $row['message']];
}

echo json_encode(['status' => 'success', 'edits' => $edits]);
