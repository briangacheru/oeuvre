<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input
    $goalID = mysqli_real_escape_string($con, $_POST['goalID']);
    $goalName = mysqli_real_escape_string($con, $_POST['goalName']);
    $goalDescription = mysqli_real_escape_string($con, $_POST['goalDescription']);
    $goalAmount = mysqli_real_escape_string($con, $_POST['goalAmount']);
    $goalPeriod = mysqli_real_escape_string($con, $_POST['goalPeriod']);

    // Check if achieved_on date was selected
    if (isset($_POST['achieved_on']) && !empty($_POST['achieved_on'])) {
        $achieved_on = mysqli_real_escape_string($con, $_POST['achieved_on']); // Use selected date
        $goalStatus = 1; // Automatically mark goal as completed
        $is_achieved = 1; // Mark as achieved
    } else {
        $achieved_on = NULL; // Keep it NULL if not completed
        $goalStatus = 0; // Keep goal status as active
        $is_achieved = 0; // Not achieved
    }

    $query = "UPDATE tblsavingsgoals
              SET goalName = '$goalName', goalDescription = '$goalDescription', goalAmount = '$goalAmount', goalStatus = '$goalStatus', goalPeriod = '$goalPeriod', is_achieved = '$is_achieved', achieved_on = '$achieved_on'
              WHERE goalID = $goalID";

    if (mysqli_query($con, $query)) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center"><p class="mb-0 flex-1">' . htmlspecialchars($goalName) . ' goal editted successfully!</p>
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