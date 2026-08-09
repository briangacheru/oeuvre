<?php
include "check-login.php";
csrf_verify_or_redirect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noteID     = (int) $_POST['noteID'];
    $encodedPID = $_POST['projectID'];
    $projectID  = decode_project_id($encodedPID);
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

    // Verify ownership before writing, same as edit_project_transaction.php
    $check = $con->prepare("SELECT noteID FROM tbl_project_notes WHERE noteID = ? AND projectID = ?");
    $check->bind_param("ii", $noteID, $projectID);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Note not found.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
        header("Location: project-details?projectID=" . $encodedPID);
        exit();
    }

    $stmt = $con->prepare("UPDATE tbl_project_notes SET note = ?, updated_at = NOW() WHERE noteID = ? AND projectID = ?");
    $stmt->bind_param("sii", $note, $noteID, $projectID);

    if ($stmt->execute()) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
            <div class="bg-success me-3 icon-item"><span class="fas fa-check text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Note updated successfully.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
            <div class="bg-danger me-3 icon-item"><span class="fas fa-times text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Error: ' . safe_db_error($con->error) . '</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
    }
    header("Location: project-details?projectID=" . $encodedPID);
    exit();
}
