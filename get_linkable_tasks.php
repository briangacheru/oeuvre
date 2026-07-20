<?php
// Returns the current writer's own tasks, for the chat's "Link to Task" picker.
include "check-login.php";
header('Content-Type: application/json');

if (!isset($_SESSION['sessionWriter'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

$email = $_SESSION['sessionWriter'];

$stmt = mysqli_prepare($con, "
    SELECT id, topic FROM tbltasks
    WHERE email = ? AND is_deleted = 0
      AND status IN ('In Progress', 'In Revision', 'Unconfirmed')
    ORDER BY create_date DESC
    LIMIT 50
");
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$tasks = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tasks[] = ['id' => (int)$row['id'], 'encoded_id' => encode_task_id($row['id']), 'topic' => $row['topic']];
}

echo json_encode(['status' => 'success', 'tasks' => $tasks]);
