<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attachmentID = (int) $_POST['attachmentID'];
    $encodedPID   = $_POST['projectID'];
    $projectID    = (int) base64_decode($encodedPID);

    // Fetch stored filename before deleting
    $fetch = $con->prepare("SELECT storedName FROM tbl_project_attachments WHERE attachmentID = ? AND projectID = ?");
    $fetch->bind_param("ii", $attachmentID, $projectID);
    $fetch->execute();
    $row = $fetch->get_result()->fetch_assoc();

    if (!$row) {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Attachment not found.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
        header("Location: project-details?projectID=" . $encodedPID);
        exit();
    }

    $stmt = $con->prepare("DELETE FROM tbl_project_attachments WHERE attachmentID = ? AND projectID = ?");
    $stmt->bind_param("ii", $attachmentID, $projectID);

    if ($stmt->execute()) {
        $filePath = "../uploads/project_attachments/" . $row['storedName'];
        if (file_exists($filePath)) unlink($filePath);
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
            <div class="bg-success me-3 icon-item"><span class="fas fa-check text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Attachment deleted.</p>
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