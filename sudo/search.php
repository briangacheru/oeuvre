<?php
/**
 * Top-nav search (admin/sudo). Admins can see everything, so this searches
 * across all tasks, writers, files, messages, invoices, payments,
 * transactions, goals, todos, projects, reminders, chats, writer levels,
 * bonus records, the app version, and financial accounts - not scoped to
 * one writer's data the way root's search.php is. GET-only, no state
 * change, so no CSRF check needed.
 *
 * Several of these entity types have no dedicated per-record view page in
 * the app (invoices, payments, transactions, goals, reminders, chats,
 * writer levels, bonus records, financial accounts) - for those, matched
 * results link to the relevant list/management page rather than a specific
 * record, since that's genuinely the only way to reach them today.
 */
include 'check-login.php';
header('Content-Type: application/json');

if (!isset($_SESSION['odmsaid'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'query' => $q, 'groups' => []]);
    exit;
}

$like = '%' . $q . '%';
$groups = [];

// ---- Tasks ----
$taskItems = [];
$stmt = $con->prepare("SELECT id, topic, subject, account, status, writer FROM tbltasks
    WHERE is_deleted = 0 AND (topic LIKE ? OR subject LIKE ? OR account LIKE ? OR writer LIKE ?)
    ORDER BY create_date DESC LIMIT 8");
$stmt->bind_param('ssss', $like, $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $encodedId = encode_task_id($row['id']);
    $taskItems[] = [
        'title' => $row['topic'],
        'subtitle' => htmlspecialchars($row['account'], ENT_QUOTES) . ' &bull; ' . htmlspecialchars($row['writer'], ENT_QUOTES) . ' &bull; ' . htmlspecialchars($row['status'], ENT_QUOTES),
        'actions' => [
            ['label' => 'View Task', 'url' => 'view-task?task_id=' . urlencode($encodedId)],
            ['label' => 'Edit Task', 'url' => 'edit-task?task_id=' . urlencode($encodedId)],
        ],
    ];
}
$stmt->close();
if ($taskItems) {
    $groups[] = ['label' => 'Tasks', 'icon' => 'fa-tasks', 'items' => $taskItems];
}

// ---- Writers ----
$writerItems = [];
$stmt = $con->prepare("SELECT id, username, email FROM tblwriters
    WHERE username LIKE ? OR email LIKE ?
    ORDER BY created_at DESC LIMIT 8");
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $encodedId = encode_writer_id($row['id']);
    $writerItems[] = [
        'title' => $row['username'],
        'subtitle' => htmlspecialchars($row['email'], ENT_QUOTES),
        'actions' => [
            ['label' => 'View Writer', 'url' => 'writer?writerID=' . urlencode($encodedId)],
        ],
    ];
}
$stmt->close();
if ($writerItems) {
    $groups[] = ['label' => 'Writers', 'icon' => 'fa-user', 'items' => $writerItems];
}

// ---- Files (task + submitted, across all tasks) ----
$fileItems = [];
$stmt = $con->prepare("SELECT f.id, f.original_file_name, f.file_url, f.task_id, f.file_type, t.topic
    FROM tbl_task_files f
    JOIN tbltasks t ON t.id = f.task_id
    WHERE f.is_deleted = 0 AND f.original_file_name LIKE ?
    ORDER BY f.upload_time DESC LIMIT 8");
$stmt->bind_param('s', $like);
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

// ---- Messages (task discussion, across all tasks) ----
$messageItems = [];
$stmt = $con->prepare("SELECT c.id, c.task_id, c.comment, c.user_type, c.username, t.topic
    FROM tbl_task_comments c
    JOIN tbltasks t ON t.id = c.task_id
    WHERE c.comment LIKE ?
    ORDER BY c.created_at DESC LIMIT 8");
$stmt->bind_param('s', $like);
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

// ---- Invoices ---- (no per-record view page; link to the invoice log list)
$invoiceItems = [];
$stmt = $con->prepare("SELECT id, writer_name, writer_email, amount_payable, sent_at, notes FROM tbl_invoice_logs
    WHERE writer_name LIKE ? OR writer_email LIKE ? OR notes LIKE ?
    ORDER BY sent_at DESC LIMIT 8");
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $invoiceItems[] = [
        'title' => 'Invoice: ' . $row['writer_name'],
        'subtitle' => 'Ksh ' . number_format($row['amount_payable'], 2) . ' &bull; ' . date('d M Y', strtotime($row['sent_at'])),
        'actions' => [
            ['label' => 'View Invoice Logs', 'url' => 'invoice-logs'],
        ],
    ];
}
$stmt->close();
if ($invoiceItems) {
    $groups[] = ['label' => 'Invoices', 'icon' => 'fa-file-invoice-dollar', 'items' => $invoiceItems];
}

// ---- Payments (overdrafts & bonuses) ---- (no per-record view page; link to the list)
$paymentItems = [];
$stmt = $con->prepare("SELECT id, writer, amount, od_date, description, record_type FROM tbloverdrafts
    WHERE is_deleted = 0 AND (writer LIKE ? OR email LIKE ? OR description LIKE ? OR tag LIKE ?)
    ORDER BY od_date DESC LIMIT 8");
$stmt->bind_param('ssss', $like, $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $typeLabel = $row['record_type'] === 'bonus' ? 'Bonus' : 'Overdraft';
    $paymentItems[] = [
        'title' => $typeLabel . ': ' . $row['writer'],
        'subtitle' => 'Ksh ' . number_format($row['amount'], 2) . ' &bull; ' . date('d M Y', strtotime($row['od_date'])) . ($row['description'] ? ' &bull; ' . htmlspecialchars($row['description'], ENT_QUOTES) : ''),
        'actions' => [
            ['label' => 'View Payments', 'url' => 'overdraft'],
        ],
    ];
}
$stmt->close();
if ($paymentItems) {
    $groups[] = ['label' => 'Payments', 'icon' => 'fa-money-bill-wave', 'items' => $paymentItems];
}

// ---- Transactions ---- (no per-record view page; link to the list)
$transactionItems = [];
$stmt = $con->prepare("SELECT budgetID, category, subcategory, description, tag, amount, expenseDate FROM tblbudget
    WHERE is_deleted = 0 AND (description LIKE ? OR category LIKE ? OR subcategory LIKE ? OR tag LIKE ?)
    ORDER BY expenseDate DESC LIMIT 8");
$stmt->bind_param('ssss', $like, $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $transactionItems[] = [
        'title' => $row['description'] ?: ($row['category'] . ' / ' . $row['subcategory']),
        'subtitle' => 'Ksh ' . number_format($row['amount'], 2) . ' &bull; ' . htmlspecialchars($row['category'], ENT_QUOTES) . ' &bull; ' . date('d M Y', strtotime($row['expenseDate'])),
        'actions' => [
            ['label' => 'View Transactions', 'url' => 'transactions'],
        ],
    ];
}
$stmt->close();
if ($transactionItems) {
    $groups[] = ['label' => 'Transactions', 'icon' => 'fa-exchange-alt', 'items' => $transactionItems];
}

// ---- Goals ---- (no per-record view page; link to the list)
$goalItems = [];
$stmt = $con->prepare("SELECT goalID, goalName, goalDescription, goalAmount, goalStatus FROM tblsavingsgoals
    WHERE is_deleted = 0 AND (goalName LIKE ? OR goalDescription LIKE ?)
    ORDER BY goalID DESC LIMIT 8");
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $goalItems[] = [
        'title' => $row['goalName'],
        'subtitle' => 'Target: Ksh ' . number_format($row['goalAmount'], 2) . ' &bull; ' . htmlspecialchars($row['goalStatus'], ENT_QUOTES),
        'actions' => [
            ['label' => 'View Goals', 'url' => 'saving-goals'],
        ],
    ];
}
$stmt->close();
if ($goalItems) {
    $groups[] = ['label' => 'Goals', 'icon' => 'fa-bullseye', 'items' => $goalItems];
}

// ---- Todos ----
$todoItems = [];
$stmt = $con->prepare("SELECT id, title, description, status, due_date FROM tbltodos
    WHERE title LIKE ? OR description LIKE ?
    ORDER BY due_date ASC LIMIT 8");
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $todoItems[] = [
        'title' => $row['title'],
        'subtitle' => htmlspecialchars($row['status'], ENT_QUOTES) . ($row['due_date'] ? ' &bull; Due ' . date('d M Y', strtotime($row['due_date'])) : ''),
        'actions' => [
            ['label' => 'View Todo', 'url' => 'view-todo?id=' . (int) $row['id']],
        ],
    ];
}
$stmt->close();
if ($todoItems) {
    $groups[] = ['label' => 'Todos', 'icon' => 'fa-check-square', 'items' => $todoItems];
}

// ---- Projects ----
$projectItems = [];
$stmt = $con->prepare("SELECT projectID, projectName, projectDescription, projectAmount, is_achieved FROM tbl_projects
    WHERE is_deleted = 0 AND (projectName LIKE ? OR projectDescription LIKE ?)
    ORDER BY projectID DESC LIMIT 8");
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $encodedId = encode_project_id($row['projectID']);
    $projectItems[] = [
        'title' => $row['projectName'],
        'subtitle' => 'Budget: Ksh ' . number_format($row['projectAmount'], 2) . ' &bull; ' . ($row['is_achieved'] ? 'Completed' : 'In progress'),
        'actions' => [
            ['label' => 'View Project', 'url' => 'project-details?projectID=' . urlencode($encodedId)],
        ],
    ];
}
$stmt->close();
if ($projectItems) {
    $groups[] = ['label' => 'Projects', 'icon' => 'fa-folder-open', 'items' => $projectItems];
}

// ---- Reminders ---- (no per-record view page; link to the list)
$reminderItems = [];
$stmt = $con->prepare("SELECT id, title, category, reminder_time FROM reminders
    WHERE title LIKE ? OR category LIKE ?
    ORDER BY reminder_time DESC LIMIT 8");
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reminderItems[] = [
        'title' => $row['title'],
        'subtitle' => htmlspecialchars($row['category'], ENT_QUOTES) . ($row['reminder_time'] ? ' &bull; ' . date('d M Y, g:i A', strtotime($row['reminder_time'])) : ''),
        'actions' => [
            ['label' => 'View Reminders', 'url' => 'reminders'],
        ],
    ];
}
$stmt->close();
if ($reminderItems) {
    $groups[] = ['label' => 'Reminders', 'icon' => 'fa-bell', 'items' => $reminderItems];
}

// ---- Chats ---- (no way to deep-link a specific conversation yet; link to the chat page)
$chatItems = [];
$stmt = $con->prepare("SELECT id, sender_id, receiver_id, message, timestamp FROM chat_messages
    WHERE message LIKE ?
    ORDER BY timestamp DESC LIMIT 8");
$stmt->bind_param('s', $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $snippet = strip_tags($row['message']);
    if (mb_strlen($snippet) > 90) {
        $snippet = mb_substr($snippet, 0, 87) . '...';
    }
    $chatItems[] = [
        'title' => $snippet,
        'subtitle' => date('d M Y, g:i A', strtotime($row['timestamp'] . ' UTC')),
        'actions' => [
            ['label' => 'Open Chat', 'url' => 'chat'],
        ],
    ];
}
$stmt->close();
if ($chatItems) {
    $groups[] = ['label' => 'Chats', 'icon' => 'fa-comments', 'items' => $chatItems];
}

// ---- Writer Levels ---- (small fixed config list; link to the management page)
$levelItems = [];
$stmt = $con->prepare("SELECT id, level_name, level_description FROM tbl_writer_levels
    WHERE level_name LIKE ? OR level_description LIKE ?
    ORDER BY level_number ASC LIMIT 8");
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $levelItems[] = [
        'title' => $row['level_name'],
        'subtitle' => htmlspecialchars($row['level_description'], ENT_QUOTES),
        'actions' => [
            ['label' => 'View Writer Levels', 'url' => 'level-management'],
        ],
    ];
}
$stmt->close();
if ($levelItems) {
    $groups[] = ['label' => 'Writer Levels', 'icon' => 'fa-layer-group', 'items' => $levelItems];
}

// ---- Bonus Management ---- (no per-record view page; link to the list)
$bonusItems = [];
$stmt = $con->prepare("SELECT mb.id, mb.month, mb.year, mb.total_bonus_amount, mb.is_paid, w.username
    FROM tbl_monthly_bonuses mb
    LEFT JOIN tblwriters w ON mb.writer_id = w.id
    WHERE w.username LIKE ?
    ORDER BY mb.year DESC, mb.month DESC LIMIT 8");
$stmt->bind_param('s', $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $bonusItems[] = [
        'title' => 'Bonus: ' . $row['username'],
        'subtitle' => 'Ksh ' . number_format($row['total_bonus_amount'], 2) . ' &bull; ' . $row['month'] . '/' . $row['year'] . ' &bull; ' . ($row['is_paid'] ? 'Paid' : 'Unpaid'),
        'actions' => [
            ['label' => 'View Bonus History', 'url' => 'bonus-history'],
        ],
    ];
}
$stmt->close();
if ($bonusItems) {
    $groups[] = ['label' => 'Bonus Management', 'icon' => 'fa-award', 'items' => $bonusItems];
}

// ---- Version ---- (single record from version.json, not a DB table)
$versionFile = __DIR__ . '/version.json';
if (is_file($versionFile)) {
    $versionData = json_decode(file_get_contents($versionFile), true);
    if (is_array($versionData)) {
        $versionString = ($versionData['major'] ?? '0') . '.' . ($versionData['minor'] ?? '0') . '.' . ($versionData['patch'] ?? '0');
        $versionDescription = $versionData['description'] ?? '';
        $haystack = 'version ' . $versionString . ' ' . $versionDescription;
        if (stripos($haystack, $q) !== false) {
            $groups[] = [
                'label' => 'Version',
                'icon' => 'fa-code-branch',
                'items' => [[
                    'title' => 'App Version ' . $versionString,
                    'subtitle' => htmlspecialchars($versionDescription, ENT_QUOTES),
                    'actions' => [
                        ['label' => 'View Version Info', 'url' => 'changelog'],
                    ],
                ]],
            ];
        }
    }
}

// ---- Financial Dashboard (accounts) ---- (PIN-gated dashboard; no per-account deep link)
$accountItems = [];
$stmt = $con->prepare("SELECT id, account_name, bank_name, currency FROM accounts
    WHERE account_name LIKE ? OR bank_name LIKE ? OR notes LIKE ?
    ORDER BY account_name ASC LIMIT 8");
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $accountItems[] = [
        'title' => $row['account_name'],
        'subtitle' => htmlspecialchars($row['bank_name'], ENT_QUOTES) . ' &bull; ' . htmlspecialchars($row['currency'], ENT_QUOTES),
        'actions' => [
            ['label' => 'Open Financial Dashboard', 'url' => '14'],
        ],
    ];
}
$stmt->close();
if ($accountItems) {
    $groups[] = ['label' => 'Financial Dashboard', 'icon' => 'fa-chart-line', 'items' => $accountItems];
}

echo json_encode(['success' => true, 'query' => $q, 'groups' => $groups]);
