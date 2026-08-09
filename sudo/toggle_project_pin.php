<?php
include "check-login.php";
csrf_verify_or_redirect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectID = (int) $_POST['projectID'];

    $stmt = $con->prepare("UPDATE tbl_projects SET is_pinned = IF(is_pinned = 1, 0, 1) WHERE projectID = ? AND is_deleted = 0");
    $stmt->bind_param("i", $projectID);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
            <div class="bg-success me-3 icon-item"><span class="fas fa-check text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Pin status updated.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Project not found.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
    }
    header("Location: projects");
    exit();
}
