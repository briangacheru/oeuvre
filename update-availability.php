<?php
// Writer AJAX endpoint: set their own availability status (available/busy/
// away), shown next to their name in the writer picker on
// sudo/create-task.php. See db-migrations/2026_09_15_add_interactive_features.sql.
require_once __DIR__ . '/shared-functions.php';
ob_start();
include 'check-login.php';
csrf_verify_or_json_die();
ob_clean();
header('Content-Type: application/json');

if (empty($_SESSION['sessionWriter'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
$aid = $_SESSION['sessionWriter'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$status = $_POST['status'] ?? '';
if (!in_array($status, ['available', 'busy', 'away'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

try {
    $stmt = $con->prepare("UPDATE tblwriters SET availability_status = ?, availability_updated_at = NOW() WHERE email = ?");
    $stmt->bind_param('ss', $status, $aid);
    $ok = $stmt->execute();
    $stmt->close();
} catch (\mysqli_sql_exception $e) {
    echo json_encode(['success' => false, 'message' => 'This feature needs db-migrations/2026_09_15_add_interactive_features.sql to be run.']);
    exit;
}

echo json_encode(['success' => (bool) $ok, 'message' => $ok ? 'Updated.' : 'Failed to update.']);
