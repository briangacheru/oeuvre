<?php
include('check-login.php');

$result = mysqli_query($con, "UPDATE tbltasks SET admin_acknowledged = 1 WHERE admin_acknowledged = 0");

if ($result) {
    $affected = mysqli_affected_rows($con);
    echo json_encode(['success' => true, 'affected_rows' => $affected]);
} else {
    echo json_encode(['success' => false, 'error' => mysqli_error($con)]);
}
?>