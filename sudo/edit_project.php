<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $projectID          = (int) $_POST['projectID'];
    $projectName        = mysqli_real_escape_string($con, $_POST['projectName']);
    $projectDescription = mysqli_real_escape_string($con, $_POST['projectDescription']);
    $projectAmount      = (float) $_POST['projectAmount'];
    $projectStatus      = (int) $_POST['projectStatus'];
    $projectPeriod      = mysqli_real_escape_string($con, $_POST['projectPeriod']);
    $is_achieved        = (int) $_POST['is_achieved'];

    // Fetch previous achieved state
    $prev = $con->prepare("SELECT is_achieved, completed_at FROM tbl_projects WHERE projectID = ?");
    $prev->bind_param("i", $projectID);
    $prev->execute();
    $prevRow = $prev->get_result()->fetch_assoc();

    // Auto-stamp completed_at only when transitioning to achieved for the first time
    $completedAt = $prevRow['completed_at']; // keep existing stamp
    if ($is_achieved == 1 && $prevRow['is_achieved'] == 0) {
        $completedAt = date('Y-m-d H:i:s');
    } elseif ($is_achieved == 0) {
        $completedAt = null; // un-achieve clears stamp
    }

    $stmt = $con->prepare("UPDATE tbl_projects
        SET projectName = ?, projectDescription = ?, projectAmount = ?,
            projectStatus = ?, projectPeriod = ?, is_achieved = ?, completed_at = ?
        WHERE projectID = ?");
    $stmt->bind_param("ssdisssi",
        $projectName, $projectDescription, $projectAmount,
        $projectStatus, $projectPeriod, $is_achieved, $completedAt, $projectID);

    if ($stmt->execute()) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
            <div class="bg-success me-3 icon-item"><span class="fas fa-check text-white fs-6"></span></div>
            <p class="mb-0 flex-1">' . htmlspecialchars($projectName) . ' updated successfully.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
            <div class="bg-danger me-3 icon-item"><span class="fas fa-times text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Error: ' . $con->error . '</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
    }
    header("Location: projects");
    exit();
}