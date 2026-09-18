<?php
// Admin-side twin of ../get-comment-reactions.php.
require_once __DIR__ . '/../shared-functions.php';
ob_start();
include 'check-login.php';
ob_clean();
header('Content-Type: application/json');

if (empty($_SESSION['odmsaid'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
$aid = $_SESSION['odmsaid'];

$taskId = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
if ($taskId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit;
}

try {
    $stmt = $con->prepare("SELECT id FROM tbl_task_comments WHERE task_id = ?");
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $ids = array_map(fn($r) => (int) $r['id'], $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $stmt->close();

    $summary = get_comment_reaction_summary($con, $ids, $aid);
} catch (\mysqli_sql_exception $e) {
    echo json_encode(['success' => true, 'reactions' => new stdClass()]);
    exit;
}

echo json_encode(['success' => true, 'reactions' => $summary]);
