<?php
// Bulk-deletes selected tbl_rate_limits rows for the activity-log.php bulk
// select UI. A "bucket" there is an (action, identifier) pair - see the
// GROUP BY in activity-log.php - so deletion matches on that pair rather
// than a single row id, clearing every row in the bucket at once.
include "head.php";
requireCapability($currentAdminRole, 'view_activity_log');
csrf_verify_or_redirect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['rl_action']) && !empty($_POST['rl_identifier'])
    && is_array($_POST['rl_action']) && is_array($_POST['rl_identifier'])
    && count($_POST['rl_action']) === count($_POST['rl_identifier'])) {

    $actions = $_POST['rl_action'];
    $identifiers = $_POST['rl_identifier'];
    $deleted = 0;

    $stmt = mysqli_prepare($con, "DELETE FROM tbl_rate_limits WHERE action = ? AND identifier = ?");
    if ($stmt) {
        for ($i = 0; $i < count($actions); $i++) {
            $action = (string) $actions[$i];
            $identifier = (string) $identifiers[$i];
            mysqli_stmt_bind_param($stmt, 'ss', $action, $identifier);
            if (mysqli_stmt_execute($stmt)) {
                $deleted += mysqli_stmt_affected_rows($stmt);
            }
        }
        mysqli_stmt_close($stmt);
    }

    if ($deleted > 0) {
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
            <div class="bg-success me-3 icon-item"><span class="fas fa-check-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">Cleared ' . $deleted . ' rate limit record(s).</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">No matching rate limit records found - they may have already aged out.</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
} else {
    $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
        <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
        <p class="mb-0 flex-1">No rate limit records were selected!</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

header('Location: activity-log');
exit;
