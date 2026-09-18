<?php
// Writer-facing AJAX endpoint: submit a deadline extension request for a
// task the writer currently holds. Mirrors the cancellation-reason pattern
// on sudo/tasks-in-progress.php (modal + reason + email) but in the other
// direction - writer asks, admin approves/denies on
// sudo/extension-requests.php. See
// db-migrations/2026_09_15_add_interactive_features.sql for
// tbl_task_extension_requests.
require_once __DIR__ . '/shared-functions.php';
ob_start();
include 'check-login.php';
require_once __DIR__ . '/email-template.php';
csrf_verify_or_json_die();
ob_clean();
header('Content-Type: application/json');

if (empty($_SESSION['sessionWriter'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
$aid = $_SESSION['sessionWriter'];

function extReqJson($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    extReqJson(false, 'Invalid request method');
}

$taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
$requestedDueDate = trim($_POST['requested_due_date'] ?? '');
$reason = trim($_POST['reason'] ?? '');

if ($taskId <= 0 || $requestedDueDate === '' || $reason === '') {
    extReqJson(false, 'Please fill in a new due date and a reason.');
}

if (mb_strlen($reason) > 500) {
    extReqJson(false, 'Reason is too long (500 characters max).');
}

if (!check_rate_limit($con, 'task_extension_request', $aid, 5, 3600)) {
    extReqJson(false, rate_limit_message($con, 'task_extension_request', $aid, 3600, 'extension requests'));
}

$requestedDate = DateTime::createFromFormat('Y-m-d\TH:i', $requestedDueDate) ?: DateTime::createFromFormat('Y-m-d', $requestedDueDate);
if (!$requestedDate) {
    extReqJson(false, 'Invalid date format.');
}
$requestedDueDateSql = $requestedDate->format('Y-m-d H:i:s');

// Confirm this task is actually the requesting writer's, is still active,
// and grab the current due date + topic for the email/record.
// $aid is the writer's session EMAIL (see check-login.php); tbltasks.writer
// holds their username string, not their email - email is the column that
// actually matches $aid (see check_new_tasks.php for the same pattern).
$stmt = mysqli_prepare($con, "SELECT topic, due_date, status FROM tbltasks WHERE id = ? AND email = ? AND is_deleted = 0");
mysqli_stmt_bind_param($stmt, 'is', $taskId, $aid);
mysqli_stmt_execute($stmt);
$task = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$task) {
    extReqJson(false, 'Task not found.');
}
if (in_array($task['status'], ['Completed', 'Cancelled', 'Submitted'])) {
    extReqJson(false, 'Cannot request an extension on a task that is ' . strtolower($task['status']) . '.');
}
if ($requestedDate->getTimestamp() <= strtotime($task['due_date'])) {
    extReqJson(false, 'The requested due date must be later than the current due date.');
}

// One pending request per task at a time.
$dupStmt = mysqli_prepare($con, "SELECT id FROM tbl_task_extension_requests WHERE task_id = ? AND status = 'pending'");
mysqli_stmt_bind_param($dupStmt, 'i', $taskId);
mysqli_stmt_execute($dupStmt);
if (mysqli_stmt_get_result($dupStmt)->fetch_assoc()) {
    mysqli_stmt_close($dupStmt);
    extReqJson(false, 'You already have a pending extension request for this task.');
}
mysqli_stmt_close($dupStmt);

$now = date('Y-m-d H:i:s');
$insert = mysqli_prepare($con, "INSERT INTO tbl_task_extension_requests
    (task_id, writer_email, current_due_date, requested_due_date, reason, status, created_at)
    VALUES (?, ?, ?, ?, ?, 'pending', ?)");
mysqli_stmt_bind_param($insert, 'isssss', $taskId, $aid, $task['due_date'], $requestedDueDateSql, $reason, $now);

if (!mysqli_stmt_execute($insert)) {
    mysqli_stmt_close($insert);
    extReqJson(false, 'Failed to submit request. Please try again.');
}
mysqli_stmt_close($insert);

log_activity($con, 'writer', $aid, 'extension_requested', "Task #$taskId: " . ($task['topic'] ?? ''), $taskId);

// Notify the admin - not fatal if it fails, the request is already saved
// and visible on sudo/extension-requests.php.
$encodedId = encode_task_id($taskId);
$viewUrl = rtrim(env('APP_URL'), '/') . '/sudo/view-task?task_id=' . $encodedId;
$body = "<p>A writer has requested a deadline extension.</p>"
    . "<p><strong>Task:</strong> #$taskId - " . htmlspecialchars($task['topic'] ?? '', ENT_QUOTES, 'UTF-8') . "</p>"
    . "<p><strong>Writer:</strong> " . htmlspecialchars($aid, ENT_QUOTES, 'UTF-8') . "</p>"
    . "<p><strong>Current due date:</strong> " . date('d M Y, g:i A', strtotime($task['due_date'])) . "</p>"
    . "<p><strong>Requested due date:</strong> " . date('d M Y, g:i A', $requestedDate->getTimestamp()) . "</p>"
    . "<p><strong>Reason:</strong> " . nl2br(htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')) . "</p>";
$html = render_email_html('Deadline Extension Requested', $body, 'Review Request', $viewUrl);
send_app_mail(env('ADMIN_EMAIL'), 'iTasker Admin', "Extension Requested - Task #$taskId", $html);
send_push_to_admins($con, 'Extension Requested', "Task #$taskId - " . ($task['topic'] ?? ''), '/sudo/extension-requests');

extReqJson(true, 'Extension request sent. You will be notified once it is reviewed.');
