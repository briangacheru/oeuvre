<?php
ob_start();
include "head.php";
ob_end_clean();
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Validate session
if (!isset($_SESSION['odmsaid']) || empty($_SESSION['odmsaid'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

// Only admins can change the writer
$adminCheckQuery = "SELECT id FROM tbladmin WHERE email = '" . mysqli_real_escape_string($con, $_SESSION['odmsaid']) . "'";
$adminResult = mysqli_query($con, $adminCheckQuery);
if (!$adminResult || mysqli_num_rows($adminResult) === 0) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admins only.']);
    exit;
}

// Validate required POST fields
$taskId  = isset($_POST['task_id']) ? trim($_POST['task_id']) : '';
$writer  = isset($_POST['writer'])  ? trim($_POST['writer'])  : '';
$email   = isset($_POST['email'])   ? trim($_POST['email'])   : '';

if (empty($taskId) || empty($writer) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Sanitize
$taskId = (int) $taskId;
$writer = mysqli_real_escape_string($con, $writer);
$email  = mysqli_real_escape_string($con, $email);

// Confirm task exists
$taskCheckStmt = mysqli_prepare($con, "SELECT id, status FROM tbltasks WHERE id = ?");
mysqli_stmt_bind_param($taskCheckStmt, 'i', $taskId);
mysqli_stmt_execute($taskCheckStmt);
$taskCheckResult = mysqli_stmt_get_result($taskCheckStmt);
$taskRow = mysqli_fetch_assoc($taskCheckResult);
mysqli_stmt_close($taskCheckStmt);

if (!$taskRow) {
    echo json_encode(['success' => false, 'message' => 'Task not found.']);
    exit;
}

// Confirm the selected writer exists and is verified
$writerCheckStmt = mysqli_prepare($con, "SELECT id, username, email FROM tblwriters WHERE username = ? AND email = ? AND is_verified = 1");
mysqli_stmt_bind_param($writerCheckStmt, 'ss', $writer, $email);
mysqli_stmt_execute($writerCheckStmt);
$writerCheckResult = mysqli_stmt_get_result($writerCheckStmt);
$writerRow = mysqli_fetch_assoc($writerCheckResult);
mysqli_stmt_close($writerCheckStmt);

if (!$writerRow) {
    echo json_encode(['success' => false, 'message' => 'Selected writer is not valid or not verified.']);
    exit;
}

// Update the task
$updateStmt = mysqli_prepare($con, "UPDATE tbltasks SET writer = ?, email = ? WHERE id = ?");
mysqli_stmt_bind_param($updateStmt, 'ssi', $writer, $email, $taskId);
$updated = mysqli_stmt_execute($updateStmt);
$affectedRows = mysqli_stmt_affected_rows($updateStmt);
mysqli_stmt_close($updateStmt);

if ($updated && $affectedRows > 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Writer updated successfully to ' . htmlspecialchars($writer) . '.'
    ]);
} elseif ($updated && $affectedRows === 0) {
    echo json_encode([
        'success' => true,
        'message' => 'No changes made. Writer may already be set to ' . htmlspecialchars($writer) . '.'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error. Failed to update writer.']);
}
exit;