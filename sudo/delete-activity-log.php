<?php
// Bulk-deletes selected tbl_activity_log rows for the activity-log.php bulk
// select UI. This is the persistent writer activity history (unlike the
// live tbl_rate_limits view), so deletion here is by row id.
include "head.php";
requireCapability($currentAdminRole, 'view_activity_log');
csrf_verify_or_redirect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['activity_ids']) && is_array($_POST['activity_ids'])) {
    $ids = array_values(array_filter(array_map('intval', $_POST['activity_ids'])));
    $deleted = 0;

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = mysqli_prepare($con, "DELETE FROM tbl_activity_log WHERE id IN ($placeholders)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$ids);
            if (mysqli_stmt_execute($stmt)) {
                $deleted = mysqli_stmt_affected_rows($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($deleted > 0) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
            <div class="bg-success me-3 icon-item"><span class="fas fa-check-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Deleted ' . $deleted . ' activity log entr' . ($deleted === 1 ? 'y' : 'ies') . '.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">No matching activity log entries found.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
} else {
    $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
        <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
        <p class="mb-0 flex-1">No activity log entries were selected!</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

header('Location: activity-log');
exit;
