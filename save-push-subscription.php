<?php
// Shared Web Push subscription-save endpoint for BOTH interfaces. Detects
// which session is active (writer's sessionWriter vs admin's odmsaid)
// rather than living in each interface separately, since the request body
// and DB write are identical either way. See push-sw.js,
// assets/js/push-notifications.js, and
// db-migrations/2026_09_15_add_interactive_features.sql
// (tbl_push_subscriptions).
require_once __DIR__ . '/shared-functions.php';
require_once __DIR__ . '/env.php';

// Each interface uses its own session cookie name (see session-name.php /
// sudo/session-name.php) precisely so they don't share one session store.
// This endpoint is called from both, so it has to open the SAME cookie the
// calling page is using. assets/js/push-notifications.js already knows
// which interface it's running in and says so via ?iface= - that's used
// instead of guessing from which cookie happens to be present, since a
// browser can legitimately hold both a writer AND an admin session cookie
// at once (e.g. testing both interfaces), which made the guess wrong.
$iface = $_GET['iface'] ?? '';
if ($iface === 'admin') {
    session_name('itasker_admin');
} elseif ($iface === 'writer') {
    session_name('itasker_writer');
} elseif (isset($_COOKIE['itasker_admin'])) {
    // No ?iface= (e.g. an older cached copy of push-notifications.js) -
    // fall back to the previous best-effort guess.
    session_name('itasker_admin');
} else {
    session_name('itasker_writer');
}
ini_set('session.gc_maxlifetime', 604800);
session_start();

header('Content-Type: application/json');

$userType = null;
$userEmail = null;
if (!empty($_SESSION['sessionWriter'])) {
    $userType = 'writer';
    $userEmail = $_SESSION['sessionWriter'];
} elseif (!empty($_SESSION['odmsaid'])) {
    $userType = 'admin';
    $userEmail = $_SESSION['odmsaid'];
}

if (!$userType) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/dbcon.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// The subscription payload is sent as a JSON body (PushSubscription.toJSON()),
// so the CSRF token travels as a query-string param instead of $_POST -
// csrf_verify() only reads $_POST, so it's checked directly here.
$csrfToken = $_GET['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$raw = json_decode(file_get_contents('php://input'), true);
if (isset($raw['action']) && $raw['action'] === 'unsubscribe') {
    $endpoint = $raw['endpoint'] ?? '';
    if ($endpoint === '') {
        echo json_encode(['success' => false, 'message' => 'Missing endpoint.']);
        exit;
    }
    $hash = hash('sha256', $endpoint);
    $stmt = $con->prepare("DELETE FROM tbl_push_subscriptions WHERE endpoint_hash = ?");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

$endpoint = $raw['endpoint'] ?? '';
$keys = $raw['keys'] ?? [];
$p256dh = $keys['p256dh'] ?? '';
$auth = $keys['auth'] ?? '';

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid subscription payload.']);
    exit;
}

try {
    $hash = hash('sha256', $endpoint);
    $now = date('Y-m-d H:i:s');
    $stmt = $con->prepare("INSERT INTO tbl_push_subscriptions (user_type, user_email, endpoint, endpoint_hash, p256dh, auth, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE user_type = VALUES(user_type), user_email = VALUES(user_email), p256dh = VALUES(p256dh), auth = VALUES(auth)");
    $stmt->bind_param('sssssss', $userType, $userEmail, $endpoint, $hash, $p256dh, $auth, $now);
    $ok = $stmt->execute();
    $stmt->close();
} catch (\mysqli_sql_exception $e) {
    echo json_encode(['success' => false, 'message' => 'Push notifications need db-migrations/2026_09_15_add_interactive_features.sql run first.']);
    exit;
}

echo json_encode(['success' => (bool) $ok]);
