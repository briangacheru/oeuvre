<?php
include "check-login.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $encodedPID    = $_POST['projectID'];
    $projectID     = (int) base64_decode($encodedPID);
    $transactionID = !empty($_POST['transactionID']) ? (int) $_POST['transactionID'] : null;

    $uploadDir = "../uploads/project_attachments/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if (empty($_FILES['attachment']['name'])) {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">No file selected.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
        header("Location: project-details?projectID=" . $encodedPID);
        exit();
    }

    $allowedMime = [
        'image/jpeg','image/png','image/gif','image/webp',
        'application/pdf',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain','text/csv'
    ];
    $maxSize = 10 * 1024 * 1024; // 10 MB

    $originalName = basename($_FILES['attachment']['name']);
    $mimeType     = mime_content_type($_FILES['attachment']['tmp_name']);
    $fileSize     = $_FILES['attachment']['size'];
    $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $storedName   = bin2hex(random_bytes(16)) . '.' . $ext;

    if (!in_array($mimeType, $allowedMime)) {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
            <div class="bg-danger me-3 icon-item"><span class="fas fa-times text-white fs-6"></span></div>
            <p class="mb-0 flex-1">File type not allowed. Allowed: images, PDF, Word, Excel, CSV, TXT.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
        header("Location: project-details?projectID=" . $encodedPID);
        exit();
    }
    if ($fileSize > $maxSize) {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
            <div class="bg-danger me-3 icon-item"><span class="fas fa-times text-white fs-6"></span></div>
            <p class="mb-0 flex-1">File too large. Maximum size is 10 MB.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
        header("Location: project-details?projectID=" . $encodedPID);
        exit();
    }

    if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $storedName)) {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
            <div class="bg-danger me-3 icon-item"><span class="fas fa-times text-white fs-6"></span></div>
            <p class="mb-0 flex-1">File upload failed. Check server write permissions.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
        header("Location: project-details?projectID=" . $encodedPID);
        exit();
    }

    $stmt = $con->prepare("INSERT INTO tbl_project_attachments
        (projectID, transactionID, originalName, storedName, fileSize, mimeType)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $projectID, $transactionID, $originalName, $storedName, $fileSize, $mimeType);

    if ($stmt->execute()) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
            <div class="bg-success me-3 icon-item"><span class="fas fa-check text-white fs-6"></span></div>
            <p class="mb-0 flex-1">' . htmlspecialchars($originalName) . ' uploaded successfully.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
    } else {
        unlink($uploadDir . $storedName);
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
            <div class="bg-danger me-3 icon-item"><span class="fas fa-times text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Database error: ' . $con->error . '</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
        </div>';
    }
    header("Location: project-details?projectID=" . $encodedPID);
    exit();
}