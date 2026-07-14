<?php
// Lightweight poll target: for the currently open conversation, returns the
// current text of every edited message (either party), so an edit made by
// one side shows up on the other side's screen without a full reload.
require_once __DIR__ . '/session-name.php';
session_start();
include "dbcon.php";
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (!isset($_SESSION['odmsaid']) || empty($_SESSION['odmsaid'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$partnerId = filter_var($_GET['partner_id'] ?? null, FILTER_VALIDATE_INT);
if ($partnerId === false || $partnerId <= 0) {
    echo json_encode(['error' => 'Invalid partner ID']);
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
    mysqli_stmt_close($stmt);

    echo json_encode(['status' => 'success', 'edits' => $edits]);
} catch (Exception $e) {
    error_log('get_message_edits error: ' . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}
