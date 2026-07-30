<?php
include('check-login.php');
csrf_verify_or_json_die();

require_once __DIR__ . '/session-name.php';
session_start();

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $encodedId = $_POST['task_id'] ?? '';
    $taskId = decode_task_id($encodedId);

    $paymentMethod = $_POST['payment_method'] ?? '';
    $transactionCode = null;

    if ($paymentMethod === 'transaction_code') {
        $transactionCode = trim($_POST['transaction_code'] ?? '');
        $paidOnInput = trim($_POST['paid_on'] ?? '');

        if ($transactionCode === '') {
            $response['message'] = 'Please enter the transaction code.';
            echo json_encode($response);
            exit;
        }
        if ($paidOnInput === '') {
            $response['message'] = 'Please enter the transaction date and time.';
            echo json_encode($response);
            exit;
        }

        $paidOnDt = DateTime::createFromFormat('Y-m-d\TH:i', $paidOnInput);
        if (!$paidOnDt) {
            $response['message'] = 'Invalid transaction date/time.';
            echo json_encode($response);
            exit;
        }
        // paid_on is Nairobi-local (PHP date()) by convention across every
        // writer of this column - the admin-entered value is treated the
        // same way, no timezone conversion.
        $paidOn = $paidOnDt->format('Y-m-d H:i:s');
    } elseif ($paymentMethod === 'overdraft') {
        $paidOn = date('Y-m-d H:i:s');
        $transactionCode = null;
    } else {
        $response['message'] = 'Please choose a payment method.';
        echo json_encode($response);
        exit;
    }

    $sql = "UPDATE tbltasks SET is_paid = 1, paid_on = ?, payment_method = ?, transaction_code = ? WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("sssi", $paidOn, $paymentMethod, $transactionCode, $taskId);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Task marked as paid successfully.';
    } else {
        $response['message'] = 'Error updating task payment: ' . safe_db_error($stmt->error);
    }
    $stmt->close();
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
exit;
