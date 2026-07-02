<?php
include('check-login.php');
$aid = $_SESSION['sessionWriter'];

// Get new tasks count
$newTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS new_task_count FROM tbltasks WHERE is_deleted = 0 AND (status = 'In Progress' OR is_confirmed = 1) AND email = '$aid' AND acknowledged = 0");
$newTasksCountResult = mysqli_fetch_assoc($newTasksCountQuery);
$newTasksCount = $newTasksCountResult['new_task_count'];

// Get late tasks count
$lateTasksCountQuery = mysqli_query($con, "SELECT COUNT(*) AS late_task_count FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND due_date < NOW() AND email = '$aid'");
$lateTasksCountResult = mysqli_fetch_assoc($lateTasksCountQuery);
$lateTasksCount = $lateTasksCountResult['late_task_count'];

echo json_encode([
    'newTasksCount' => $newTasksCount,
    'lateTasksCount' => $lateTasksCount
]);
?>