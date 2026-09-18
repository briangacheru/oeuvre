<?php
// Admin sets / changes the 1-5 quality rating on a Completed task from the
// "Quality Rating" card on sudo/view-task.php. Rating at completion time
// goes through sudo/complete-task.php instead (same helper underneath).
include('check-login.php');
header('Content-Type: application/json');
if (empty($_SESSION['odmsaid'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorised.']);
    exit;
}
csrf_verify_or_json_die();
requireCapability($currentAdminRole, 'operate_tasks', 'json');
include_once('writer-performance-functions.php');

$taskId = (int) decode_task_id($_POST['task_id'] ?? '');
$rating = (int) ($_POST['rating'] ?? 0);
$note = trim((string) ($_POST['note'] ?? ''));

if ($taskId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task.']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Pick a rating between 1 and 5 stars.']);
    exit;
}
if (mb_strlen($note) > 500) {
    echo json_encode(['success' => false, 'message' => 'Keep the note under 500 characters.']);
    exit;
}

echo json_encode(saveTaskQualityRating($con, $taskId, $rating, $note, $_SESSION['odmsaid']));
