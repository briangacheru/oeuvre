<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $subcategory = mysqli_real_escape_string($con, $_POST['subcategory']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $tag = mysqli_real_escape_string($con, $_POST['tag']);
    $amount = mysqli_real_escape_string($con, $_POST['amount']);
    $expenseDate = mysqli_real_escape_string($con, $_POST['expenseDate']);

    // Check for duplicate record
    $checkQuery = "SELECT COUNT(*) AS record_count FROM tblbudget WHERE amount = ? AND expenseDate = ?";
    $checkStmt = mysqli_prepare($con, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, 'ss', $amount, $expenseDate);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_bind_result($checkStmt, $recordCount);
    mysqli_stmt_fetch($checkStmt);
    mysqli_stmt_close($checkStmt);

    if ($recordCount > 0) {
        $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert"> 
                                <i class="bi bi-exclamation-circle me-1"></i> A record with the same amount and date already exists.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
        header('Location: transactions');
        exit;
    }

    // Function to calculate transaction cost for Mpesa
    function calculateTransactionCost($amount) {
        if ($amount >= 1 && $amount <= 49) return 0; // Free
        if ($amount >= 50 && $amount <= 100) return 0; // Free
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
        return 0; // Default if amount is out of range
    }

    // Calculate transactionCost only for Mpesa
    $transactionCost = 0;
    if ($tag === 'Mpesa') {
        $transactionCost = calculateTransactionCost($amount);
    }

    // Insert the record into the database, including the transactionCost
    $query = "INSERT INTO tblbudget (category, subcategory, description, tag, amount, expenseDate, transactionCost)
              VALUES ('$category', '$subcategory', '$description', '$tag', '$amount', '$expenseDate', '$transactionCost')";

    if (mysqli_query($con, $query)) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center"><p class="mb-0 flex-1">' . htmlspecialchars($category) . ' transaction added successfully!</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center"><p class="mb-0 flex-1">Error: ' . mysqli_error($con) . '</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    header("Location: transactions");
    exit();
}
?>
