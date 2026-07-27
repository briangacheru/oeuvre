<?php
/**
 * Handles Google's redirect back after "Sign in with Google" for the
 * writer app. Logs an existing (or newly-linked) account in directly, or -
 * if registration is open - creates a new writer account the same way
 * register.php does, then logs in.
 *
 * Google's own auth already strongly verifies the person controls that
 * Google account/email, so unlike login.php this path never requires the
 * emailed one-time code, even from a brand-new device. It still records
 * the device via remember_device() so any later password-based login to
 * the same account benefits from the existing known-device trust window.
 */
require_once __DIR__ . '/check-login.php';
require_once __DIR__ . '/google-oauth.php';
require_once __DIR__ . '/login-helpers.php';

function google_callback_fail($message) {
    $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
        <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
        <p class="mb-0 flex-1">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
    header('Location: login.php');
    exit;
}

$expectedState = $_SESSION['google_oauth_state'] ?? null;
$taskIdParam = $_SESSION['google_oauth_task_id'] ?? null;
unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_task_id']);

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!check_rate_limit($con, 'login_writer', $clientIp, 10, 600)) {
    google_callback_fail(rate_limit_message($con, 'login_writer', $clientIp, 600, 'login attempts'));
}

if (isset($_GET['error'])) {
    google_callback_fail('Google sign-in was cancelled.');
}

$submittedState = $_GET['state'] ?? '';
if (empty($submittedState) || empty($expectedState) || !hash_equals($expectedState, $submittedState)) {
    google_callback_fail('Your Google sign-in request could not be verified. Please try again.');
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    google_callback_fail('Google did not return an authorization code.');
}

$redirectUri = rtrim(env('APP_URL'), '/') . '/google-callback.php';
$profile = google_oauth_fetch_profile($code, $redirectUri);
if (!$profile) {
    google_callback_fail('Could not verify your Google account. Please try again.');
}
if (empty($profile['email_verified'])) {
    google_callback_fail('Your Google account\'s email address is not verified.');
}

$googleId = $profile['sub'];
$email = $profile['email'];

// Look up by google_id first (already linked from a previous Google login),
// falling back to email (an existing password account signing in with
// Google for the first time - link it rather than erroring or creating a
// duplicate row).
$stmt = $con->prepare("SELECT email, google_id FROM tblwriters WHERE google_id = ? OR email = ? LIMIT 1");
$stmt->bind_param('ss', $googleId, $email);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    if (empty($existing['google_id'])) {
        $upd = $con->prepare("UPDATE tblwriters SET google_id = ? WHERE email = ?");
        $upd->bind_param('ss', $googleId, $existing['email']);
        $upd->execute();
    }
    $email = $existing['email'];
} else {
    $regResult = mysqli_query($con, "SELECT regStatus FROM tblsettings WHERE id = 1");
    $regRow = $regResult ? mysqli_fetch_assoc($regResult) : null;
    if (!$regRow || (int) $regRow['regStatus'] !== 1) {
        google_callback_fail('Registration is currently closed.');
    }

    $username = google_oauth_derive_username($profile['name'] ?? '', $email);
    $dupe = $con->prepare("SELECT id FROM tblwriters WHERE username = ?");
    $dupe->bind_param('s', $username);
    $dupe->execute();
    if ($dupe->get_result()->num_rows > 0) {
        $username .= '_' . substr($googleId, -6);
    }

    $insert = $con->prepare("INSERT INTO tblwriters (username, email, google_id) VALUES (?, ?, ?)");
    $insert->bind_param('sss', $username, $email, $googleId);
    if (!$insert->execute()) {
        error_log('google-callback.php: writer insert failed for ' . $email . ': ' . safe_db_error($con->error));
        google_callback_fail('Could not create your account. Please try again.');
    }

    send_writer_welcome_emails($email, $username);
}

$deviceToken = $_COOKIE['writer_device_token'] ?? null;
remember_device($con, 'tblwriter_known_devices', 'writer_email', $email, 'writer_device_token', $deviceToken);

$redirectUrl = finalize_writer_login($con, $email, false, $taskIdParam);
header('Location: ' . $redirectUrl);
exit;
