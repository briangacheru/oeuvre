<?php
include "check-login.php";

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = mysqli_real_escape_string($con, $_POST['id']);
    $amount = mysqli_real_escape_string($con, $_POST['amount']);
    $od_date = mysqli_real_escape_string($con, $_POST['od_date']);
    $writer = mysqli_real_escape_string($con, $_POST['writer']);
    $tag = mysqli_real_escape_string($con, $_POST['tag']); // New field for tag
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $record_type = isset($_POST['record_type']) ? mysqli_real_escape_string($con, $_POST['record_type']) : 'overdraft'; // Default to overdraft for backward compatibility

    // Function to calculate transaction cost (applies to both overdrafts and bonuses, but only for Mpesa)
    function calculateTransactionCost($amount, $recordType, $tag) {
        if ($tag !== 'Mpesa') return 0; // No transaction cost for Airtel Money

        if ($amount >= 1 && $amount <= 49) return 0;
        if ($amount >= 50 && $amount <= 100) return 0;
        if ($amount >= 101 && $amount <= 500) return 7;
        if ($amount >= 501 && $amount <= 1000) return 13;
        if ($amount >= 1001 && $amount <= 1500) return 23;
        if ($amount >= 1501 && $amount <= 2500) return 33;
        if ($amount >= 2501 && $amount <= 3500) return 53;
        if ($amount >= 3501 && $amount <= 5000) return 57;
        if ($amount >= 5001 && $amount <= 7500) return 78;
        if ($amount >= 7501 && $amount <= 10000) return 90;
        if ($amount >= 10001 && $amount <= 15000) return 100;
        if ($amount >= 15001 && $amount <= 20000) return 105;
        if ($amount >= 20001 && $amount <= 35000) return 108;
        if ($amount >= 35001 && $amount <= 50000) return 108;
        if ($amount >= 50001 && $amount <= 250000) return 108;
        return 0;
    }

    // Calculate transaction cost based on amount, record type, and tag
    $transactionCost = calculateTransactionCost($amount, $record_type, $tag);

    // Update the record with tag and recalculated transaction cost
    $stmt = mysqli_prepare($con, "UPDATE tbloverdrafts SET amount = ?, od_date = ?, writer = ?, tag = ?, description = ?, record_type = ?, transactionCost = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssssssdi', $amount, $od_date, $writer, $tag, $description, $record_type, $transactionCost, $id);

    if (mysqli_stmt_execute($stmt)) {
        $response['success'] = true;
        $recordTypeDisplay = ($record_type === 'bonus') ? 'Bonus' : 'Overdraft';
        $response['message'] = $recordTypeDisplay . ' record updated successfully.';
    } else {
        $response['message'] = 'Something went wrong. Please try again!';
    }

    mysqli_stmt_close($stmt);
} else {
    $response['message'] = 'Invalid request method.';
}

header('Content-Type: application/json');
echo json_encode($response);
?>