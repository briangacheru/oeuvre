<?php
// Returns every file attachment exchanged with a given conversation partner,
// for the "Shared Files" modal.
session_start();
include "dbcon.php";
header('Content-Type: application/json');

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
    mysqli_stmt_close($stmt);

    echo json_encode(['status' => 'success', 'files' => $files]);
} catch (Exception $e) {
    error_log('get_shared_files error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
