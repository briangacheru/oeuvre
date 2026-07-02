<?php
include "check-login.php";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectID = $_POST['projectID'];
    $projectID = (int) $projectID;
    $query = "UPDATE tbl_projects SET is_deleted = 1 WHERE projectID = $projectID";

    if (mysqli_query($con, $query)) {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center"><p class="mb-0 flex-1">Project deleted successfully!</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center"><p class="mb-0 flex-1">Error: ' . mysqli_error($con) . '</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    header("Location: projects");
    exit();
}
?>