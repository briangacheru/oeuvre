<?php
// Lightweight poll target: returns the same task counts navi.php renders
// into the sidebar badges at page load, so they can be refreshed live
// without a full reload. Query logic mirrors navi.php exactly.
require_once('check-login.php');
header('Content-Type: application/json');

if (!isset($_SESSION['odmsaid']) || empty($_SESSION['odmsaid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

function sidebarCount($con, $sql) {
    // Guards against a not-yet-run migration the same way
    // get_current_version() does in shared-functions.php - PHP 8.1+ mysqli
    // throws on a bad query rather than returning false.
    try {
        $result = mysqli_query($con, $sql);
    } catch (\mysqli_sql_exception $e) {
        return 0;
    }
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    return (int) $row['taskCount'];
}

$counts = [
    'all_tasks' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbltasks"),
    'drafts' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND (writer = 'Draft' OR status = 'Draft')"),
    'unconfirmed' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_confirmed = 1"),
    'in_progress' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress'"),
    'in_revision' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Revision'"),
    'submitted' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted'"),
    'completed' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND is_archived = 0"),
    'cancelled' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 1"),
    'archived' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_archived = 1"),
    'favorite' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_favorite = 1"),
    'unpaid' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND status = 'Completed'"),
    'paid' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND is_paid = 1"),
    'extension_requests' => sidebarCount($con, "SELECT COUNT(*) as taskCount FROM tbl_task_extension_requests WHERE status = 'pending'"),
];

// Unread messages count - matches head.php's $unreadMessagesCount computation exactly
$userStmt = mysqli_prepare($con, "SELECT id FROM tbladmin WHERE email = ?");
mysqli_stmt_bind_param($userStmt, 's', $aid);
mysqli_stmt_execute($userStmt);
$userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt));

$unreadMessagesCount = 0;
if ($userRow) {
    $unreadStmt = mysqli_prepare($con, "SELECT COUNT(*) as unreadCount FROM chat_messages WHERE is_read = 0 AND receiver_id = ?");
    mysqli_stmt_bind_param($unreadStmt, 'i', $userRow['id']);
    mysqli_stmt_execute($unreadStmt);
    $unreadRow = mysqli_fetch_assoc(mysqli_stmt_get_result($unreadStmt));
    $unreadMessagesCount = (int) $unreadRow['unreadCount'];
}
$counts['unread_messages'] = $unreadMessagesCount;

echo json_encode(['status' => 'success', 'counts' => $counts]);
