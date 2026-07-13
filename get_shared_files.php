<?php
// Returns every file attachment exchanged with a given conversation partner,
// for the "Shared Files" modal.
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
    SELECT id, sender_id, file_url, timestamp
    FROM chat_messages
    WHERE file_url IS NOT NULL
      AND is_deleted = 0
      AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
    ORDER BY timestamp DESC
    LIMIT 100
");
mysqli_stmt_bind_param($stmt, 'iiii', $currentUserId, $partnerId, $partnerId, $currentUserId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$files = [];
while ($row = mysqli_fetch_assoc($result)) {
    $files[] = [
        'id' => (int)$row['id'],
        'sender_id' => (int)$row['sender_id'],
        'file_url' => $row['file_url'],
        'timestamp' => $row['timestamp']
    ];
}

echo json_encode(['status' => 'success', 'files' => $files]);
