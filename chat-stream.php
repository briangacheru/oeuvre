<?php
// Server-Sent Events endpoint backing chat.php. Deliberately does NOT
// duplicate poll_messages.php's query/JSON shape or the client's message
// rendering - it only tells the already-working pollMessages() in chat.php
// WHEN to run (a lightweight "anything changed?" check against MAX(id) on
// every message visible to this user), instead of replacing that logic.
// This keeps the 3-second setInterval in chat.php as a safety-net fallback
// (slowed down, see chat.php) if the browser doesn't support EventSource or
// the connection drops, rather than a single point of failure.
require_once __DIR__ . '/shared-functions.php';
ob_start();
include 'check-login.php';
ob_end_clean();

if (empty($_SESSION['sessionWriter'])) {
    http_response_code(401);
    exit;
}
$aid = $_SESSION['sessionWriter'];

// Stop output buffering/compression so events flush immediately.
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // nginx, if ever fronted by it
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

// A long-lived connection with a hard ceiling, matching common PHP/Apache
// execution limits - the client's EventSource reconnects automatically
// when the stream ends, so this is a rolling 50-second window, not a
// single long request the server has to hold open indefinitely.
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
