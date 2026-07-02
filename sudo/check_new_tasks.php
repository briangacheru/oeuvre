<?php
include('check-login.php');
// Get new tasks that haven't been acknowledged
$query = mysqli_query($con, "SELECT * FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted' AND admin_acknowledged = 0 ORDER BY submitted_on DESC");
if (!$query) {
    echo json_encode(['success' => false, 'error' => 'Query failed', 'tasks' => []]);
    exit;
}
$newTasks = [];
while ($task = mysqli_fetch_assoc($query)) {
    $newTasks[] = $task;
}
if (count($newTasks) > 0) {
        echo json_encode(['success' => true, 'tasks' => $newTasks]);
} else {
    echo json_encode(['success' => false, 'tasks' => []]);
}
?>