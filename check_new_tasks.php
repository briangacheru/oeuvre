<?php
include('check-login.php');
$aid = $_SESSION['sessionWriter'];
// Get new tasks that haven't been acknowledged
$query = mysqli_query($con, "SELECT * FROM tbltasks WHERE is_deleted = 0 AND (status = 'In Progress' OR is_confirmed = 1) AND email = '$aid' AND acknowledged = 0 ORDER BY create_date DESC");
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