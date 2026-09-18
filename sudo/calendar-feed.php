<?php
// Admin iCalendar (.ics) subscription feed - every non-deleted task's due
// date, same set sudo/calendar.php shows. Session-less by design (calendar
// clients fetch anonymously); access is by the per-admin secret token
// minted from the "Subscribe" dialog on sudo/calendar.php.
require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/dbcon.php';
require_once __DIR__ . '/../shared-functions.php';
date_default_timezone_set('Africa/Nairobi');

$token = (string) ($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
    http_response_code(404);
    exit('Not found');
}

try {
    if (!check_rate_limit($con, 'calendar_feed', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 60, 600)) {
        http_response_code(429);
        exit('Too many requests');
    }
} catch (\mysqli_sql_exception $e) {
    // rate-limit table not migrated yet - don't block the feed over it
}

try {
    $stmt = $con->prepare("SELECT email, username FROM tbladmin WHERE calendar_token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (\mysqli_sql_exception $e) {
    $admin = null; // migration pending
}
if (!$admin) {
    http_response_code(404);
    exit('Not found');
}

$result = mysqli_query($con, "SELECT id, account, topic, due_date, status, writer, pages FROM tbltasks
                               WHERE is_deleted = 0 ORDER BY due_date DESC LIMIT 2000");
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

$viewBase = rtrim(env('APP_URL', ''), '/') . '/sudo/view-task';
$ics = render_ics_calendar('iTasker - All tasks', build_task_calendar_events($rows, $viewBase, true));

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="itasker-all-tasks.ics"');
header('Cache-Control: private, max-age=300');
echo $ics;
