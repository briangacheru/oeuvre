<?php
include "check-login.php";
csrf_verify_or_json_die();
requireCapability($currentAdminRole, 'manage_admins', 'json');

header('Content-Type: application/json');

$targetId = isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : 0;

if ($targetId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

$stmt = mysqli_prepare($con, "SELECT email, role FROM tbladmin WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $targetId);
mysqli_stmt_execute($stmt);
$targetRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$targetRow) {
    echo json_encode(['success' => false, 'message' => 'Admin not found.']);
    exit();
}

// Same self-lockout protection as update-admin-role.php.
if ($targetRow['email'] === $_SESSION['odmsaid']) {
    echo json_encode(['success' => false, 'message' => "You can't delete your own account - ask another superadmin."]);
    exit();
}

// Superadmin accounts are never editable or deletable from this page -
// enforced here too, not just by hiding the button in manage-admins.php,
// since the UI hiding it is not itself an access control.
if (($targetRow['role'] ?: 'pending') === 'superadmin') {
    echo json_encode(['success' => false, 'message' => "Superadmin accounts can't be deleted here."]);
    exit();
}

$stmt2 = mysqli_prepare($con, "DELETE FROM tbladmin WHERE id = ?");
mysqli_stmt_bind_param($stmt2, 'i', $targetId);

if (mysqli_stmt_execute($stmt2)) {
    echo json_encode(['success' => true, 'message' => 'Admin account deleted.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete admin: ' . safe_db_error(mysqli_error($con))]);
}
mysqli_stmt_close($stmt2);
