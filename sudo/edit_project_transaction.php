<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionID  = (int) $_POST['transactionID'];
    $encodedPID     = $_POST['projectID'];
    $projectID      = (int) base64_decode($encodedPID);
    $type           = $_POST['type'] === 'Income' ? 'Income' : 'Expense';
    $category       = mysqli_real_escape_string($con, trim($_POST['category']));
    $description    = mysqli_real_escape_string($con, trim($_POST['description']));
    $amount         = (float) $_POST['amount'];
    $tag            = mysqli_real_escape_string($con, trim($_POST['tag']));
    $transactionDate = mysqli_real_escape_string($con, $_POST['transactionDate']);

    // Verify ownership
    $check = $con->prepare("SELECT transactionID FROM tbl_project_transactions WHERE transactionID = ? AND projectID = ?");
    $check->bind_param("ii", $transactionID, $projectID);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Transaction not found.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
        header("Location: project-details?projectID=" . $encodedPID);
        exit();
    }

    $stmt = $con->prepare("UPDATE tbl_project_transactions
        SET type = ?, category = ?, description = ?, amount = ?, tag = ?, transactionDate = ?
        WHERE transactionID = ? AND projectID = ?");
    $stmt->bind_param("sssdssii", $type, $category, $description, $amount, $tag, $transactionDate, $transactionID, $projectID);

    if ($stmt->execute()) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
            <div class="bg-success me-3 icon-item"><span class="fas fa-check text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Transaction updated successfully.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
            <div class="bg-danger me-3 icon-item"><span class="fas fa-times text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Error: ' . $con->error . '</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
    }
    header("Location: project-details?projectID=" . $encodedPID);
    exit();
}
