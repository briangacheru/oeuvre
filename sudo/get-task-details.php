<?php
include "check-login.php";

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Task ID required']);
    exit;
}

$taskId = intval($_GET['id']);

try {
    // Get task details
    $stmt = $con->prepare("
        SELECT t.*, c.name as category_name, c.color as category_color 
        FROM tbltodos t 
        LEFT JOIN categories c ON t.category_id = c.id 
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $taskId);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();

    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        exit;
    }

    // Get subtasks
    $stmt = $con->prepare("SELECT * FROM subtasks WHERE todo_id = ? ORDER BY created_at");
    $stmt->bind_param("i", $taskId);
    $stmt->execute();
    $subtasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get attachments
    $stmt = $con->prepare("SELECT * FROM task_attachments WHERE todo_id = ? ORDER BY created_at");
    $stmt->bind_param("i", $taskId);
    $stmt->execute();
    $attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'task' => $task,
        'subtasks' => $subtasks,
        'attachments' => $attachments
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>