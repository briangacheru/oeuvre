<?php
include "check-login.php"; // Include your database connection and session start

$writer_name = isset($_GET['writer_name']) ? $_GET['writer_name'] : '';

$response = array();

// Fetch total completed tasks
$query = "SELECT SUM(CPP*pages) AS total FROM tbltasks WHERE writer = ? AND is_deleted = 0 AND is_paid = 0 AND status = 'Completed'";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, "s", $writer_name);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
$response['totalCompletedTasks'] = (float) ($row['total'] ?? 0);

// Fetch total overdrafts
$query2 = "SELECT SUM(amount) AS totalOverdraft FROM tbloverdrafts WHERE writer = ? AND is_settled = 0 AND is_deleted = 0";
$stmt2 = mysqli_prepare($con, $query2);
mysqli_stmt_bind_param($stmt2, "s", $writer_name);
mysqli_stmt_execute($stmt2);
$result2 = mysqli_stmt_get_result($stmt2);
$row2 = mysqli_fetch_assoc($result2);
$response['totalOverdrafts'] = (float) ($row2['totalOverdraft'] ?? 0);

$response['amountDue'] = $response['totalCompletedTasks'] - $response['totalOverdrafts'];

echo json_encode($response);
?>
