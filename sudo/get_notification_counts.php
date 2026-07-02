<?php
include('check-login.php');

// Get new tasks count
$newTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS new_task_count FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted' AND admin_acknowledged = 0");
$newTasksCountResult = mysqli_fetch_assoc($newTasksCountQuery);
$newTasksCount = $newTasksCountResult['new_task_count'];

// Get late tasks count
$lateTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS late_task_count FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND due_date < NOW()");
$lateTasksCountResult = mysqli_fetch_assoc($lateTasksCountQuery);
$lateTasksCount = $lateTasksCountResult['late_task_count'];

echo json_encode([
    'newTasksCount' => $newTasksCount,
    'lateTasksCount' => $lateTasksCount
]);
?>