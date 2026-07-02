<?php
session_start();
include 'dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['taskIds']) && is_array($_POST['taskIds'])) {
    $taskIds = $_POST['taskIds'];
    $taskIds = array_map('intval', $taskIds); // Sanitize input

    if (!empty($taskIds)) {
        $placeholders = str_repeat('?,', count($taskIds) - 1) . '?';
        $sql = "UPDATE tbltasks SET admin_acknowledged = 0 WHERE id IN ($placeholders) AND status = 'Submitted'";

        $stmt = mysqli_prepare($con, $sql);
        if ($stmt) {
            $types = str_repeat('i', count($taskIds));
            mysqli_stmt_bind_param($stmt, $types, ...$taskIds);

            if (mysqli_stmt_execute($stmt)) {
                $affectedRows = mysqli_stmt_affected_rows($stmt);
                $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
                    <div class="bg-success me-3 icon-item"><span class="fas fa-check-circle text-white fs-6"></span></div>
                    <p class="mb-0 flex-1">' . $affectedRows . ' task(s) marked as unread successfully!</p>
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
            } else {
                $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                    <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
                    <p class="mb-0 flex-1">Error marking tasks as unread: ' . mysqli_error($con) . '</p>
                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
                <p class="mb-0 flex-1">Database error: ' . mysqli_error($con) . '</p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-triangle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">No tasks selected!</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
} else {
    $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
        <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
        <p class="mb-0 flex-1">Invalid request!</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

// Redirect back to the submitted tasks page
header('Location: submitted-tasks.php');
exit();
?>