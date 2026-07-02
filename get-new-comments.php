<?php
session_start();
include('dbcon.php');

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['odmsaid']) && !isset($_SESSION['sessionWriter'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get parameters from GET or POST (GET takes priority)
$taskId = 0;
if (isset($_GET['task_id'])) {
    $taskId = intval($_GET['task_id']);
} elseif (isset($_POST['task_id'])) {
    $taskId = intval($_POST['task_id']);
}

$lastTimestamp = null;
if (isset($_GET['last_timestamp'])) {
    $lastTimestamp = $_GET['last_timestamp'];
} elseif (isset($_POST['last_timestamp'])) {
    $lastTimestamp = $_POST['last_timestamp'];
}

// Validate task ID
if ($taskId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid task ID',
        'debug' => [
            'get_task_id' => $_GET['task_id'] ?? 'not set',
            'post_task_id' => $_POST['task_id'] ?? 'not set',
            'parsed_task_id' => $taskId
        ]
    ]);
    exit();
}

// Determine current user type
$currentUserType = isset($_SESSION['odmsaid']) ? 'admin' : 'writer';
$currentUserEmail = isset($_SESSION['odmsaid']) ? $_SESSION['odmsaid'] : $_SESSION['sessionWriter'];

// Verify user has access to this task
if ($currentUserType === 'writer') {
    $accessQuery = "SELECT id FROM tbltasks WHERE id = ? AND email = ?";
    $accessStmt = mysqli_prepare($con, $accessQuery);
    mysqli_stmt_bind_param($accessStmt, 'is', $taskId, $currentUserEmail);
    mysqli_stmt_execute($accessStmt);
    $accessResult = mysqli_stmt_get_result($accessStmt);

    if (mysqli_num_rows($accessResult) == 0) {
        mysqli_stmt_close($accessStmt);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    mysqli_stmt_close($accessStmt);
}

// Build query based on whether we have a last timestamp
if ($lastTimestamp) {
    $query = "SELECT * FROM tbl_task_comments 
              WHERE task_id = ? AND created_at > ? 
              ORDER BY created_at ASC";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, 'is', $taskId, $lastTimestamp);
} else {
    $query = "SELECT * FROM tbl_task_comments 
              WHERE task_id = ? 
              ORDER BY created_at ASC";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, 'i', $taskId);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$newComments = [];
$latestTimestamp = $lastTimestamp;

while ($comment = mysqli_fetch_assoc($result)) {
    // Update latest timestamp
    $latestTimestamp = $comment['created_at'];

    // Check if this is an unread message for current user
    $isUnread = false;
    if ($currentUserType === 'admin' && $comment['user_type'] === 'writer' && $comment['is_read'] == 0) {
        $isUnread = true;
    } elseif ($currentUserType === 'writer' && $comment['user_type'] === 'admin' && $comment['is_read'] == 0) {
        $isUnread = true;
    }

    // Get profile image
    $profileImage = null;
    $imagePath = null;

    if ($comment['user_type'] === 'admin') {
        $imgQuery = 'SELECT Photo FROM tbladmin WHERE username = ? OR email = ? LIMIT 1';
        $imgStmt = mysqli_prepare($con, $imgQuery);
        mysqli_stmt_bind_param($imgStmt, 'ss', $comment['username'], $comment['username']);
        mysqli_stmt_execute($imgStmt);
        mysqli_stmt_bind_result($imgStmt, $profileImage);
        mysqli_stmt_fetch($imgStmt);
        mysqli_stmt_close($imgStmt);
    } else {
        $imgQuery = 'SELECT Photo FROM tblwriters WHERE username = ? OR email = ? LIMIT 1';
        $imgStmt = mysqli_prepare($con, $imgQuery);
        mysqli_stmt_bind_param($imgStmt, 'ss', $comment['username'], $comment['username']);
        mysqli_stmt_execute($imgStmt);
        mysqli_stmt_bind_result($imgStmt, $profileImage);
        mysqli_stmt_fetch($imgStmt);
        mysqli_stmt_close($imgStmt);
    }

    if ($profileImage && file_exists("../profileimages/" . $profileImage)) {
        $imagePath = "../profileimages/" . $profileImage;
    }

    // Get attachments if any
    $attachments = [];
    if (!empty($comment['file_url'])) {
        $attachments[] = [
            'file_name' => basename($comment['file_url']),
            'file_path' => 'taskfiles/' . $comment['file_url'],
            'file_type' => pathinfo($comment['file_url'], PATHINFO_EXTENSION)
        ];
    }

    // Check for additional attachments
    $attachQuery = "SELECT * FROM tbl_comment_attachments WHERE comment_id = ?";
    if ($attachStmt = mysqli_prepare($con, $attachQuery)) {
        mysqli_stmt_bind_param($attachStmt, 'i', $comment['id']);
        mysqli_stmt_execute($attachStmt);
        $attachResult = mysqli_stmt_get_result($attachStmt);

        while ($attachment = mysqli_fetch_assoc($attachResult)) {
            $attachments[] = [
                'file_name' => $attachment['file_name'],
                'file_path' => $attachment['file_path'],
                'file_type' => pathinfo($attachment['file_name'], PATHINFO_EXTENSION)
            ];
        }
        mysqli_stmt_close($attachStmt);
    }

    $newComments[] = [
        'id' => $comment['id'],
        'user_type' => $comment['user_type'],
        'username' => $comment['username'],
        'comment' => $comment['comment'],
        'created_at' => $comment['created_at'],
        'is_read' => $comment['is_read'],
        'is_unread' => $isUnread,
        'profile_image' => $imagePath,
        'attachments' => $attachments,
        'formatted_date' => date('M d, g:i A', strtotime($comment['created_at']))
    ];
}

mysqli_stmt_close($stmt);

// Return response
echo json_encode([
    'success' => true,
    'comments' => $newComments,
    'count' => count($newComments),
    'latest_timestamp' => $latestTimestamp,
    'current_user_type' => $currentUserType
]);
?>