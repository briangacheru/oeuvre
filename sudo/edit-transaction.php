<?php
require 'check-login.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['budgetID']); // Use the generic id passed from the frontend
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $subcategory = mysqli_real_escape_string($con, $_POST['subcategory']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $amount = mysqli_real_escape_string($con, $_POST['amount']);
    $tag = mysqli_real_escape_string($con, $_POST['tag']);
    $date = mysqli_real_escape_string($con, $_POST['date']);

    // Check if the record exists and belongs to tblbudget
    $checkQuery = "SELECT budgetID FROM tblbudget WHERE budgetID = '$id' AND is_deleted = 0";
    $checkResult = mysqli_query($con, $checkQuery);

    if ($checkResult && mysqli_num_rows($checkResult) > 0) {
        // Proceed with the update if the record belongs to tblbudget
        $query = "UPDATE tblbudget SET 
                    category = '$category', subcategory = '$subcategory', description = '$description', amount = '$amount', tag = '$tag', expenseDate = '$date' WHERE budgetID = '$id'";
        if (mysqli_query($con, $query)) {
            if (mysqli_affected_rows($con) > 0) {
                $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center">
                    <p class="mb-0 flex-1">Transaction edited successfully!</p>
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
            } else {
                $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center">
                    <p class="mb-0 flex-1">No changes were made to the transaction.</p>
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
            }
        } else {
            $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center">
                <p class="mb-0 flex-1">Error: ' . mysqli_error($con) . '</p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center">
            <p class="mb-0 flex-1">Transaction not found!</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
        error_log("Query returned no results. ID: $id, Table: tblbudget");
    }
} else {
    $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center">
        <p class="mb-0 flex-1">Invalid request method!</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

header("Location: transactions");
exit();
?>
