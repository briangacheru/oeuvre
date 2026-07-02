<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectID       = (int) $_POST['projectID'];
    $type            = $_POST['type'] === 'Income' ? 'Income' : 'Expense';
    $category        = mysqli_real_escape_string($con, trim($_POST['category']));
    $description     = mysqli_real_escape_string($con, trim($_POST['description']));
    $amount          = (float) $_POST['amount'];
    $tag             = mysqli_real_escape_string($con, trim($_POST['tag']));
    $transactionDate = mysqli_real_escape_string($con, $_POST['transactionDate']);

    // Verify the project actually exists before inserting
    $check = mysqli_query($con, "SELECT projectID FROM tbl_projects WHERE projectID = $projectID AND is_deleted = 0");
    if (!$check || mysqli_num_rows($check) === 0) {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center"><p class="mb-0 flex-1">Invalid project — transaction not saved.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
        header("Location: projects");
        exit();
    }

    $query = "INSERT INTO tbl_project_transactions
                (projectID, type, category, description, amount, tag, transactionDate)
              VALUES
                ($projectID, '$type', '$category', '$description', $amount, '$tag', '$transactionDate')";

    $encodedID = base64_encode($projectID);

    if (mysqli_query($con, $query)) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center"><p class="mb-0 flex-1">' . $type . ' of Ksh ' . number_format($amount, 2) . ' recorded successfully!</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center"><p class="mb-0 flex-1">Error: ' . mysqli_error($con) . '</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }

    header("Location: project-details?projectID=" . $encodedID);
    exit();
}
?>