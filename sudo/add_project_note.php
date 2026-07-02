<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $encodedPID = $_POST['projectID'];
    $projectID  = (int) base64_decode($encodedPID);
    $note       = trim($_POST['note']);

    if (empty($note)) {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Note cannot be empty.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
        header("Location: project-details?projectID=" . $encodedPID);
        exit();
    }

    $stmt = $con->prepare("INSERT INTO tbl_project_notes (projectID, note) VALUES (?, ?)");
    $stmt->bind_param("is", $projectID, $note);

    if ($stmt->execute()) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
            <div class="bg-success me-3 icon-item"><span class="fas fa-check text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Note added successfully.</p>
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