<?php
include "check-login.php";

if (isset($_GET['id'])) {
    $budgetID = intval($_GET['id']);
    $query = mysqli_query($con, "SELECT * FROM tblbudget WHERE budgetID = $budgetID AND is_deleted = 0");

    if ($query && mysqli_num_rows($query) > 0) {
        $transaction = mysqli_fetch_assoc($query);
        echo json_encode([
            'status' => 'success',
            'data' => $transaction
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Transaction not found'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request'
    ]);
}
?>