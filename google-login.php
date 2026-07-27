<?php
/**
 * Kicks off "Sign in with Google" for the writer app - just builds the
 * authorize URL and redirects. All the real work (token exchange, account
 * lookup/creation, session establishment) happens in google-callback.php.
 */
require_once __DIR__ . '/check-login.php';
require_once __DIR__ . '/google-oauth.php';

if (!google_oauth_configured()) {
    $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
        <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
        <p class="mb-0 flex-1">Google sign-in is not configured. Please log in with your email and password instead.</p>
    </div>';
    header('Location: login.php');
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;
// Carried through to the callback the same way login.php's own form does,
// so a writer arriving via a shared task link and choosing Google still
// lands on that task afterward instead of the default dashboard.
$_SESSION['google_oauth_task_id'] = $_POST['task_id'] ?? $_GET['task_id'] ?? null;

$redirectUri = rtrim(env('APP_URL'), '/') . '/google-callback.php';
header('Location: ' . google_oauth_authorize_url($redirectUri, $state));
exit;
