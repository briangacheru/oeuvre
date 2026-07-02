<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $goalName = mysqli_real_escape_string($con, $_POST['goalName']);
    $goalDescription = mysqli_real_escape_string($con, $_POST['goalDescription']);
    $goalAmount = mysqli_real_escape_string($con, $_POST['goalAmount']);
    $goalPeriod = mysqli_real_escape_string($con, $_POST['goalPeriod']);

    $query = "INSERT INTO tblsavingsgoals (goalName, goalDescription, goalAmount, goalPeriod) 
              VALUES ('$goalName', '$goalDescription', '$goalAmount', '$goalPeriod')";

    if (mysqli_query($con, $query)) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center"><p class="mb-0 flex-1">' . htmlspecialchars($goalName) . ' saving goal added successfully!</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center"><p class="mb-0 flex-1">Error: ' . mysqli_error($con) . '</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    header("Location: saving-goals");
    exit();
}
?>
