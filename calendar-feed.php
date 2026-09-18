<?php
// Writer iCalendar (.ics) subscription feed - one event per task due date.
// Deliberately session-less: Google/Apple/Outlook fetch subscribed feeds
// anonymously, so access is by the per-writer secret token minted from the
// "Subscribe" dialog on calendar.php (get_calendar_feed_token()).
// Regenerating the token there revokes any previously shared URL.
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/dbcon.php';
require_once __DIR__ . '/shared-functions.php';
date_default_timezone_set('Africa/Nairobi');

$token = (string) ($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
    http_response_code(404);
    exit('Not found');
}

// Token guessing is infeasible (192 bits) but keep scrapers polite anyway.
try {
    if (!check_rate_limit($con, 'calendar_feed', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 60, 600)) {
        http_response_code(429);
        exit('Too many requests');
    }
} catch (\mysqli_sql_exception $e) {
    // rate-limit table not migrated yet - don't block the feed over it
}

try {
    $stmt = $con->prepare("SELECT email, username FROM tblwriters WHERE calendar_token = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $writer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (\mysqli_sql_exception $e) {
    $writer = null; // migration pending
}
if (!$writer) {
    http_response_code(404);
    exit('Not found');
}

$stmt = $con->prepare("SELECT id, topic, due_date, status, pages FROM tbltasks
                        WHERE is_deleted = 0 AND email = ? AND status != 'Draft'
                        ORDER BY due_date DESC LIMIT 1000");
$stmt->bind_param('s', $writer['email']);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$viewBase = rtrim(env('APP_URL', ''), '/') . '/view-task';
$ics = render_ics_calendar('iTasker - ' . ($writer['username'] ?: 'My tasks'), build_task_calendar_events($rows, $viewBase, false));

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="itasker-tasks.ics"');
header('Cache-Control: private, max-age=300');
echo $ics;
