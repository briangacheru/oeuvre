<?php
// Admin AJAX endpoint backing inline editing on task list tables (click a
// due-date or CPP cell to edit without opening edit-task.php). Writer
// reassignment reuses the existing sudo/update-task-writer.php instead of
// being duplicated here. See assets/js/inline-task-edit.js.
require_once __DIR__ . '/../shared-functions.php';
ob_start();
include 'check-login.php';
requireCapability($currentAdminRole, 'operate_tasks', 'json');
csrf_verify_or_json_die();
ob_clean();
header('Content-Type: application/json');

$aid = $_SESSION['odmsaid'];

function inlineJson($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    inlineJson(false, 'Invalid request method');
}

$taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
$field = $_POST['field'] ?? '';
$value = trim($_POST['value'] ?? '');

if ($taskId <= 0) {
    inlineJson(false, 'Invalid task.');
}

$stmt = mysqli_prepare($con, "SELECT status, due_date, cpp FROM tbltasks WHERE id = ? AND is_deleted = 0");
mysqli_stmt_bind_param($stmt, 'i', $taskId);
mysqli_stmt_execute($stmt);
$task = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$task) {
    inlineJson(false, 'Task not found.');
}

if ($field === 'due_date') {
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value) ?: DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt) {
        inlineJson(false, 'Invalid date.');
    }
    $sqlValue = $dt->format('Y-m-d H:i:s');
    $upd = mysqli_prepare($con, "UPDATE tbltasks SET due_date = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, 'si', $sqlValue, $taskId);
    $display = date('d M Y, g:i A', $dt->getTimestamp());
} elseif ($field === 'cpp') {
    if (!is_numeric($value) || (float) $value <= 0) {
        inlineJson(false, 'CPP must be a positive number.');
    }
    $sqlValue = (float) $value;
    $upd = mysqli_prepare($con, "UPDATE tbltasks SET cpp = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, 'di', $sqlValue, $taskId);
    $display = number_format($sqlValue, 2);
} else {
    inlineJson(false, 'That field cannot be edited inline.');
}

if (!mysqli_stmt_execute($upd)) {
    mysqli_stmt_close($upd);
    inlineJson(false, 'Failed to save.');
}
mysqli_stmt_close($upd);

log_activity($con, 'admin', $aid, 'task_inline_edit', "Task #$taskId: $field -> $value");

inlineJson(true, 'Saved.', ['display' => $display, 'raw_value' => $sqlValue]);
