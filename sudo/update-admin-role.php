<?php
include "check-login.php";
csrf_verify_or_json_die();
requireCapability($currentAdminRole, 'manage_admins', 'json');

header('Content-Type: application/json');

$targetId = isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : 0;
$newRole = $_POST['role'] ?? '';

if ($targetId <= 0 || !in_array($newRole, ADMIN_ROLES, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

$stmt = mysqli_prepare($con, "SELECT email FROM tbladmin WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $targetId);
mysqli_stmt_execute($stmt);
$targetRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$targetRow) {
    echo json_encode(['success' => false, 'message' => 'Admin not found.']);
    exit();
}

// Prevent self-demotion/self-lockout via this endpoint - a superadmin who
// accidentally drops their own role would have no way back in without a
// raw DB edit, exactly the problem this whole feature exists to remove.
if ($targetRow['email'] === $_SESSION['odmsaid']) {
    echo json_encode(['success' => false, 'message' => "You can't change your own role - ask another superadmin."]);
    exit();
}

$stmt2 = mysqli_prepare($con, "UPDATE tbladmin SET role = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt2, 'si', $newRole, $targetId);

if (mysqli_stmt_execute($stmt2)) {
    echo json_encode(['success' => true, 'message' => 'Role updated to ' . ucfirst($newRole) . '.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update role: ' . safe_db_error(mysqli_error($con))]);
}
mysqli_stmt_close($stmt2);
