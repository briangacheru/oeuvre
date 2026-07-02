<?php
include "check-login.php";

// Fetch savings goals with goalStatus = 0
$query = "SELECT goalName FROM tblsavingsgoals WHERE goalStatus = 0 AND is_deleted = 0";
$result = mysqli_query($con, $query);

$goals = [];
while ($row = mysqli_fetch_assoc($result)) {
    $goals[] = $row;
}


// Return the results as JSON
header('Content-Type: application/json');
echo json_encode($goals);
?>
