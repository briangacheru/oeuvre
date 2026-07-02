<?php
include('check-login.php');

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['writer-id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    // SQL to update the overdraft record
    $sql = "UPDATE tblwriters SET username = ?, email = ?, phone = ? WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("sssi", $name, $email, $phone, $id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Writer updated successfully';
    } else {
        $response['message'] = 'Error updating record: ' . $stmt->error;
    }
    $stmt->close();
}

echo json_encode($response);
?>
