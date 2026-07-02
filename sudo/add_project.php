<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $projectName = mysqli_real_escape_string($con, $_POST['projectName']);
    $projectDescription = mysqli_real_escape_string($con, $_POST['projectDescription']);
    $projectAmount = mysqli_real_escape_string($con, $_POST['projectAmount']);
    $projectPeriod = mysqli_real_escape_string($con, $_POST['projectPeriod']);

    $query = "INSERT INTO tbl_projects (projectName, projectDescription, projectAmount, projectPeriod) 
              VALUES ('$projectName', '$projectDescription', '$projectAmount', '$projectPeriod')";

    if (mysqli_query($con, $query)) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center"><p class="mb-0 flex-1">' . htmlspecialchars($projectName) . ' project added successfully!</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center"><p class="mb-0 flex-1">Error: ' . mysqli_error($con) . '</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    header("Location: projects");
    exit();
}
?>