<?php
// Returns the given writer's tasks, for the chat's "Link to Task" picker.
session_start();
include "dbcon.php";
header('Content-Type: application/json');

if (!isset($_SESSION['odmsaid']) || empty($_SESSION['odmsaid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

$partnerId = filter_var($_GET['partner_id'] ?? null, FILTER_VALIDATE_INT);
if ($partnerId === false || $partnerId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid partner ID']);
    exit();
}

try {
    $writerStmt = mysqli_prepare($con, "SELECT username FROM tblwriters WHERE id = ?");
    mysqli_stmt_bind_param($writerStmt, 'i', $partnerId);
    mysqli_stmt_execute($writerStmt);
    $writer = mysqli_fetch_assoc(mysqli_stmt_get_result($writerStmt));
    mysqli_stmt_close($writerStmt);

    if (!$writer) {
        echo json_encode(['status' => 'error', 'message' => 'Writer not found']);
        exit();
    }

    $stmt = mysqli_prepare($con, "
        SELECT id, topic FROM tbltasks
        WHERE writer = ? AND is_deleted = 0
        ORDER BY create_date DESC
        LIMIT 50
    ");
    mysqli_stmt_bind_param($stmt, 's', $writer['username']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $tasks = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tasks[] = ['id' => (int)$row['id'], 'topic' => $row['topic']];
    }
    mysqli_stmt_close($stmt);

    echo json_encode(['status' => 'success', 'tasks' => $tasks]);
} catch (Exception $e) {
    error_log('get_linkable_tasks error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
