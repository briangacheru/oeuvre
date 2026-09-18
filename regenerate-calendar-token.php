<?php
// "Regenerate link" in calendar.php's Subscribe dialog: mints a fresh
// calendar_token so any previously shared feed URL stops working.
include 'check-login.php';
header('Content-Type: application/json');
$aid = $_SESSION['sessionWriter'] ?? '';
if ($aid === '') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorised.']);
    exit;
}
csrf_verify_or_json_die();

$token = get_calendar_feed_token($con, 'tblwriters', $aid, true);
if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Calendar subscriptions are not available yet (database migration pending).']);
    exit;
}
if (function_exists('log_activity')) {
    log_activity($con, 'writer', $aid, 'calendar_token_regenerated', 'Calendar feed link regenerated');
}
echo json_encode(['success' => true, 'url' => build_calendar_feed_url('/calendar-feed', $token)]);
