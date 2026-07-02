<?php
session_start();
include('dbcon.php');

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['odmsaid']) && !isset($_SESSION['sessionWriter'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get comment ID
$commentId = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;

if ($commentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid comment ID']);
    exit();
}

// Determine current user type
$currentUserType = isset($_SESSION['odmsaid']) ? 'admin' : 'writer';

// Get comment details
$commentQuery = "SELECT user_type, task_id FROM tbl_task_comments WHERE id = ?";
$stmt = mysqli_prepare($con, $commentQuery);
mysqli_stmt_bind_param($stmt, 'i', $commentId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$comment = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$comment) {
    echo json_encode(['success' => false, 'message' => 'Comment not found']);
    exit();
}

// Only mark as read if the current user is NOT the sender
// Admin marks writer messages as read, writer marks admin messages as read
$shouldMarkRead = false;

if ($currentUserType === 'admin' && $comment['user_type'] === 'writer') {
    $shouldMarkRead = true;
} elseif ($currentUserType === 'writer' && $comment['user_type'] === 'admin') {
    $shouldMarkRead = true;
}

if ($shouldMarkRead) {
    // Mark as read
    $updateQuery = "UPDATE tbl_task_comments SET is_read = 1 WHERE id = ?";
    $updateStmt = mysqli_prepare($con, $updateQuery);
    mysqli_stmt_bind_param($updateStmt, 'i', $commentId);
    $success = mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    echo json_encode([
        'success' => $success,
        'comment_id' => $commentId,
        'marked_read' => true
    ]);
} else {
    echo json_encode([
        'success' => true,
        'comment_id' => $commentId,
        'marked_read' => false,
        'message' => 'Not applicable (own message)'
    ]);
}
?>