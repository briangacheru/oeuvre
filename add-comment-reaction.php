<?php
// Toggle an emoji reaction on a task discussion comment. Writer-side; see
// sudo/add-comment-reaction.php for the admin twin. Both read/write the
// same tbl_comment_reactions table (shared discussion thread). See
// assets/js/comment-reactions.js.
require_once __DIR__ . '/shared-functions.php';
ob_start();
include 'check-login.php';
csrf_verify_or_json_die();
ob_clean();
header('Content-Type: application/json');

if (empty($_SESSION['sessionWriter'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
$aid = $_SESSION['sessionWriter'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$commentId = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
$emoji = $_POST['emoji'] ?? '';
$allowedEmoji = ['👍', '❤️', '👏', '😂', '🎉', '👀'];

if ($commentId <= 0 || !in_array($emoji, $allowedEmoji, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid reaction.']);
    exit;
}

try {
    $summary = toggle_comment_reaction($con, $commentId, 'writer', $aid, $emoji);
} catch (\mysqli_sql_exception $e) {
    echo json_encode(['success' => false, 'message' => 'Reactions need db-migrations/2026_09_15_add_interactive_features.sql run first.']);
    exit;
}

echo json_encode(['success' => true, 'reactions' => $summary]);
