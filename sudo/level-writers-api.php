<?php
// Read-only endpoint backing the "Writers by Level" click-through modal in
// level-management.php: given a level number, returns the active writers
// currently sitting at that level (per tbl_writer_performance.current_level).
header('Content-Type: application/json; charset=UTF-8');

include_once 'check-login.php';
requireCapability($currentAdminRole, 'operate_tasks', 'json');

$levelNumber = isset($_GET['level_number']) ? intval($_GET['level_number']) : 0;
if ($levelNumber <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid level_number is required.']);
    exit;
}

$stmt = $con->prepare("SELECT w.username, w.email
                        FROM tbl_writer_performance wp
                        JOIN tblwriters w ON w.id = wp.writer_id
                        WHERE wp.current_level = ? AND w.is_active = 1
                        ORDER BY w.username ASC");
$stmt->bind_param("i", $levelNumber);
$stmt->execute();
$result = $stmt->get_result();

$writers = [];
while ($row = $result->fetch_assoc()) {
    $writers[] = ['username' => $row['username'], 'email' => $row['email']];
}

echo json_encode(['writers' => $writers]);
