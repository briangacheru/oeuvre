<?php
// Admin AJAX endpoint: approve or deny a pending deadline extension request.
// Approving updates tbltasks.due_date directly. See
// sudo/extension-requests.php and
// db-migrations/2026_09_15_add_interactive_features.sql.
require_once __DIR__ . '/../shared-functions.php';
ob_start();
include 'check-login.php';
require_once __DIR__ . '/../email-template.php';
requireCapability($currentAdminRole, 'operate_tasks', 'json');
csrf_verify_or_json_die();
ob_clean();
header('Content-Type: application/json');

$aid = $_SESSION['odmsaid'];

function resolveExtJson($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resolveExtJson(false, 'Invalid request method');
}

$requestId = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
$decision = $_POST['decision'] ?? '';
$adminResponse = trim($_POST['admin_response'] ?? '');

if ($requestId <= 0 || !in_array($decision, ['approve', 'deny'], true)) {
    resolveExtJson(false, 'Invalid request.');
}

$stmt = mysqli_prepare($con, "SELECT r.*, t.topic FROM tbl_task_extension_requests r LEFT JOIN tbltasks t ON t.id = r.task_id WHERE r.id = ? AND r.status = 'pending'");
mysqli_stmt_bind_param($stmt, 'i', $requestId);
mysqli_stmt_execute($stmt);
$req = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$req) {
    resolveExtJson(false, 'Request not found or already resolved.');
}

$newStatus = $decision === 'approve' ? 'approved' : 'denied';
$now = date('Y-m-d H:i:s');

mysqli_begin_transaction($con);
try {
    $upd = mysqli_prepare($con, "UPDATE tbl_task_extension_requests SET status = ?, admin_response = ?, resolved_by = ?, resolved_at = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, 'ssssi', $newStatus, $adminResponse, $aid, $now, $requestId);
    if (!mysqli_stmt_execute($upd)) {
        throw new Exception('Failed to update request.');
    }
    mysqli_stmt_close($upd);

    if ($decision === 'approve') {
        $dueUpd = mysqli_prepare($con, "UPDATE tbltasks SET due_date = ? WHERE id = ?");
        mysqli_stmt_bind_param($dueUpd, 'si', $req['requested_due_date'], $req['task_id']);
        if (!mysqli_stmt_execute($dueUpd)) {
            throw new Exception('Failed to update task due date.');
        }
        mysqli_stmt_close($dueUpd);
    }

    mysqli_commit($con);
} catch (Exception $e) {
    mysqli_rollback($con);
    resolveExtJson(false, $e->getMessage());
}

log_activity($con, 'admin', $aid, 'extension_' . $newStatus, "Task #{$req['task_id']}: " . ($req['topic'] ?? ''), (int) $req['task_id']);

// Notify the writer.
$encodedId = encode_task_id($req['task_id']);
$viewUrl = rtrim(env('APP_URL'), '/') . '/view-task?task_id=' . $encodedId;
if ($decision === 'approve') {
    $body = "<p>Your deadline extension request for task <strong>#{$req['task_id']}</strong> was approved.</p>"
        . "<p><strong>New due date:</strong> " . date('d M Y, g:i A', strtotime($req['requested_due_date'])) . "</p>"
        . ($adminResponse ? "<p><strong>Note from admin:</strong> " . nl2br(htmlspecialchars($adminResponse, ENT_QUOTES, 'UTF-8')) . "</p>" : "");
    $html = render_email_html('Extension Approved', $body, 'View Task', $viewUrl);
    send_app_mail($req['writer_email'], $req['writer_email'], "Extension Approved - Task #{$req['task_id']}", $html);
    send_push_notification($con, 'writer', $req['writer_email'], 'Extension Approved', "Task #{$req['task_id']} due date was extended.", '/view-task?task_id=' . $encodedId);
} else {
    $body = "<p>Your deadline extension request for task <strong>#{$req['task_id']}</strong> was denied.</p>"
        . ($adminResponse ? "<p><strong>Note from admin:</strong> " . nl2br(htmlspecialchars($adminResponse, ENT_QUOTES, 'UTF-8')) . "</p>" : "");
    $html = render_email_html('Extension Denied', $body, 'View Task', $viewUrl);
    send_app_mail($req['writer_email'], $req['writer_email'], "Extension Denied - Task #{$req['task_id']}", $html);
    send_push_notification($con, 'writer', $req['writer_email'], 'Extension Denied', "Task #{$req['task_id']} extension request was denied.", '/view-task?task_id=' . $encodedId);
}

resolveExtJson(true, 'Request ' . $newStatus . '.');
