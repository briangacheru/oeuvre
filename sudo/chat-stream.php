<?php
// Admin-side twin of ../chat-stream.php - see that file's header comment
// for the overall approach (SSE only signals "something changed", the
// existing pollMessages() in sudo/chat.php still does the real fetch).
require_once __DIR__ . '/session-name.php';
session_start();
include 'dbcon.php';
require_once __DIR__ . '/../shared-functions.php';

if (empty($_SESSION['odmsaid'])) {
    http_response_code(401);
    exit;
}
$aid = $_SESSION['odmsaid'];

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');

$currentUserStmt = mysqli_prepare($con, "
    SELECT id, 'admin' as type FROM tbladmin WHERE email = ?
    UNION
    SELECT id, 'writer' as type FROM tblwriters WHERE email = ?
");
mysqli_stmt_bind_param($currentUserStmt, 'ss', $aid, $aid);
mysqli_stmt_execute($currentUserStmt);
$currentUser = mysqli_fetch_assoc(mysqli_stmt_get_result($currentUserStmt));
if (!$currentUser) {
    http_response_code(404);
    exit;
}
$currentUserId = $currentUser['id'];
$currentUserType = $currentUser['type'];

$lastSeenMaxId = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;
$maxRuntime = 50;
$start = time();

while (time() - $start < $maxRuntime) {
    if (connection_aborted()) {
        exit;
    }

    $stmt = mysqli_prepare($con, "SELECT MAX(id) as max_id FROM chat_messages
        WHERE ((receiver_id = ? AND receiver_type = ?) OR (sender_id = ? AND sender_type = ?))
        AND is_deleted = 0");
    mysqli_stmt_bind_param($stmt, 'isis', $currentUserId, $currentUserType, $currentUserId, $currentUserType);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    $maxId = (int) ($row['max_id'] ?? 0);

    if ($maxId > $lastSeenMaxId) {
        $lastSeenMaxId = $maxId;
        echo "event: new-message\n";
        echo 'data: ' . json_encode(['max_id' => $maxId]) . "\n\n";
    } else {
        echo "event: ping\n";
        echo "data: {}\n\n";
    }

    if (ob_get_level() > 0) { ob_flush(); }
    flush();

    sleep(2);
}

echo "event: reconnect\n";
echo "data: {}\n\n";
