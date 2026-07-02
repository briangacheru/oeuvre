<?php
/**
 * Generate Share Link API Endpoint
 *
 * This endpoint generates a shareable link for a task
 * Call via AJAX from the task view page
 */

session_start();
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/php-errors.log');
date_default_timezone_set('Africa/Nairobi');

include('../sudo/dbcon.php');
include('../sudo/task-share-helper.php');

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['odmsaid'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to generate share links'
    ]);
    exit();
}

// Get task ID from request
$taskId = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

if ($taskId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid task ID'
    ]);
    exit();
}

// Verify task exists (removed email restriction since admins can view all tasks)
$query = "SELECT id, topic FROM tbltasks WHERE id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $taskId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Task not found'
    ]);
    exit();
}

$task = $result->fetch_assoc();
$stmt->close();

// Generate the share link
$shareUrl = generateTaskShareLink($taskId);
$shareText = "Task #" . $taskId . " - " . $task['topic'];

// Return success response
echo json_encode([
    'success' => true,
    'shareUrl' => $shareUrl,
    'shareText' => $shareText,
    'taskId' => $taskId,
    'taskTopic' => $task['topic']
]);
?>