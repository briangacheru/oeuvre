<?php
include('check-login.php');

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $writer = $_POST['writer'];
    $amount = $_POST['amount'];
    $od_date = $_POST['od_date'];

    // SQL to update the overdraft record
    $sql = "UPDATE tbloverdrafts SET writer = ?, amount = ?, od_date = ? WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("sssi", $writer, $amount, $od_date, $id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Record updated successfully';
    } else {
        $response['message'] = 'Error updating record: ' . $stmt->error;
    }
    $stmt->close();
}

echo json_encode($response);
?>
