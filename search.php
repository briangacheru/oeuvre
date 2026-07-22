<?php
/**
 * Top-nav search (writer/root). Scoped to only the logged-in writer's own
 * tasks, files, and messages - never other writers' data. GET-only, no
 * state change, so no CSRF check needed.
 */
include 'check-login.php';
header('Content-Type: application/json');

if (!isset($_SESSION['sessionWriter'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$aid = $_SESSION['sessionWriter'];
$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'query' => $q, 'groups' => []]);
    exit;
}

$like = '%' . $q . '%';
$groups = [];

// ---- Tasks ----
$taskItems = [];
$stmt = $con->prepare("SELECT id, topic, subject, account, status FROM tbltasks
    WHERE is_deleted = 0 AND email = ? AND (topic LIKE ? OR subject LIKE ? OR account LIKE ?)
    ORDER BY create_date DESC LIMIT 8");
$stmt->bind_param('ssss', $aid, $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $encodedId = encode_task_id($row['id']);
    $taskItems[] = [
        'title' => $row['topic'],
        'subtitle' => htmlspecialchars($row['account'], ENT_QUOTES) . ' &bull; ' . htmlspecialchars($row['status'], ENT_QUOTES),
        'actions' => [
            ['label' => 'View Task', 'url' => 'view-task?task_id=' . urlencode($encodedId)],
        ],
    ];
}
$stmt->close();
if ($taskItems) {
    $groups[] = ['label' => 'Tasks', 'icon' => 'fa-tasks', 'items' => $taskItems];
}

// ---- Files (task + submitted, only on tasks this writer owns) ----
$fileItems = [];
$stmt = $con->prepare("SELECT f.id, f.original_file_name, f.file_url, f.task_id, f.file_type, t.topic
    FROM tbl_task_files f
    JOIN tbltasks t ON t.id = f.task_id
    WHERE f.is_deleted = 0 AND t.email = ? AND f.original_file_name LIKE ?
    ORDER BY f.upload_time DESC LIMIT 8");
$stmt->bind_param('ss', $aid, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $encodedId = encode_task_id($row['task_id']);
    $fileItems[] = [
        'title' => $row['original_file_name'],
        'subtitle' => 'On task: ' . htmlspecialchars($row['topic'], ENT_QUOTES),
        'actions' => [
            ['label' => 'View Task', 'url' => 'view-task?task_id=' . urlencode($encodedId)],
            ['label' => 'Open File', 'url' => $row['file_url'], 'external' => true],
        ],
    ];
}
$stmt->close();
if ($fileItems) {
    $groups[] = ['label' => 'Files', 'icon' => 'fa-file', 'items' => $fileItems];
}

// ---- Messages (task discussion, only on tasks this writer owns) ----
$messageItems = [];
$stmt = $con->prepare("SELECT c.id, c.task_id, c.comment, c.user_type, c.username, t.topic
    FROM tbl_task_comments c
    JOIN tbltasks t ON t.id = c.task_id
    WHERE t.email = ? AND c.comment LIKE ?
    ORDER BY c.created_at DESC LIMIT 8");
$stmt->bind_param('ss', $aid, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $encodedId = encode_task_id($row['task_id']);
    $snippet = strip_tags($row['comment']);
    if (mb_strlen($snippet) > 90) {
        $snippet = mb_substr($snippet, 0, 87) . '...';
    }
    $messageItems[] = [
        'title' => $snippet,
        'subtitle' => 'From ' . htmlspecialchars($row['username'], ENT_QUOTES) . ' on: ' . htmlspecialchars($row['topic'], ENT_QUOTES),
        'actions' => [
            ['label' => 'View Conversation', 'url' => 'view-task?task_id=' . urlencode($encodedId)],
        ],
    ];
}
$stmt->close();
if ($messageItems) {
    $groups[] = ['label' => 'Messages', 'icon' => 'fa-comment-dots', 'items' => $messageItems];
}

echo json_encode(['success' => true, 'query' => $q, 'groups' => $groups]);
