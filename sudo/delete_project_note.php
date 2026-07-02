<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noteID     = (int) $_POST['noteID'];
    $encodedPID = $_POST['projectID'];
    $projectID  = (int) base64_decode($encodedPID);

    $stmt = $con->prepare("DELETE FROM tbl_project_notes WHERE noteID = ? AND projectID = ?");
    $stmt->bind_param("ii", $noteID, $projectID);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
            <div class="bg-success me-3 icon-item"><span class="fas fa-check text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Note deleted.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Note not found or already deleted.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
    }
    header("Location: project-details?projectID=" . $encodedPID);
    exit();
}