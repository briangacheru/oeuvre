<?php
ob_start();
// Without these, PHP's default session.gc_maxlifetime (often ~24 min) can
// garbage-collect the session data well before the 7-day app-level timeout
// below ever runs, silently logging writers out early. Mirrors sudo/check-login.php.
ini_set('session.gc_maxlifetime', 604800); // 7 days in seconds
ini_set('session.cookie_lifetime', 604800); // 7 days in seconds
session_set_cookie_params(604800); // 7 days
require_once __DIR__ . '/session-name.php';
session_start();
require_once __DIR__ . '/env.php';
$appDebug = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
error_reporting(E_ALL);
ini_set('log_errors', 1); // Log errors to file
ini_set('error_log', __DIR__ . '/php-errors.log');
date_default_timezone_set('Africa/Nairobi');

include('dbcon.php');
include('functions.php');
require_once 'session_tracker.php';

$self = $_SERVER["PHP_SELF"];
$allowed_pages = ['login.php', 'reset-password.php', 'forgot-password.php', 'verify-login-code.php'];
$currentScript = basename($_SERVER['PHP_SELF']);

// AJAX/polling endpoints that shouldn't be recorded as the "last page" used
// to send the writer back after a session timeout.
$ajaxEndpoints = [
    'edit_message.php',
    'delete_message.php',
    'fetch_messages.php',
    'poll_messages.php',
    'get_sent_read_status.php',
    'get_message_edits.php',
    'set_typing_status.php',
    'get_typing_status.php',
    'get_shared_files.php',
    'get_linkable_tasks.php',
    'update_read_status.php',
    'send_message.php',
    'update-task.php',
    'delete-file.php',
    'upload_update.php',
    'update-task-acknowledgement.php',
    'submission_upload.php',
    'add-task-comment.php',
    'mark-admin-comments-read.php',
    'get_invoice_items.php',
];

if (!in_array($currentScript, $allowed_pages) && !in_array($currentScript, $ajaxEndpoints)) {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}

if (stripos($self, 'index.php') !== false) {
    if (!isset($_SESSION['sessionWriter']) || (isset($_SESSION['sessionWriter']) && strlen($_SESSION['sessionWriter']) == 0)) {
        header('Location: login.php');
        exit();
    }
} elseif (array_reduce($allowed_pages, fn($carry, $page) => $carry || stripos($self, $page) !== false, false)) {
    if (isset($_SESSION['sessionWriter']) && strlen($_SESSION['sessionWriter']) > 0) {
        // If they clicked "Sign In" from a shared task link while already logged
        // in, send them to that task (or an access-denied alert) instead of index.
        $taskRedirect = isset($_GET['task_id'])
            ? resolve_shared_task_redirect($con, $_SESSION['sessionWriter'], $_GET['task_id'])
            : null;
        header('Location: ' . ($taskRedirect ?? 'index.php'));
        exit();
    }
}

// Define session timeout duration
$session_timeout_duration = 604800; // 7 days

// Check if last_activity is set
if (isset($_SESSION['last_activity'])) {
    // Check if the session is older than 24 hours
    if (time() - $_SESSION['last_activity'] > $session_timeout_duration) {
        // Store the current page before logging out (prefer the tracked last
        // real page over the current request, which may itself be an AJAX poll)
        $lastPage = $_SESSION['last_page'] ?? $_SERVER['REQUEST_URI'];

        // Store in cookie since session will be destroyed
        setcookie('last_page_before_timeout', $lastPage, time() + 300, '/', '', isset($_SERVER["HTTPS"]), true); // 5 minutes

        // Logout the user
        logout();

        // Redirect to login page with timeout parameter
        header("Location: login.php?timeout=1");
        exit();
    }
}

// Update last activity time stamp
$_SESSION['last_activity'] = time();

// Update user status to online
if (isset($_SESSION['sessionWriter'])) {
    $email = $_SESSION['sessionWriter'];
    $userType = 'writer'; // Adjust if necessary
    updateUserStatus($email, $userType, true);
}

// If the "Remember Me" cookie is set, log the user in
if (!isset($_SESSION['sessionWriter']) && isset($_COOKIE['rememberme'])) {
    // Look for the user with this remember token
    $rememberToken = $_COOKIE['rememberme'];
    $selectUserSql = "SELECT email FROM tblwriters WHERE remember_token = ?";
    $stmt = $con->prepare($selectUserSql);
    $stmt->bind_param('s', $rememberToken);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $_SESSION['sessionWriter'] = $row['email'];
        record_writer_session($con, $row['email']);

        // Log automatic login via remember me token
        if (isset($activityLogger)) {
            $additionalData = [
                'login_method' => 'remember_token',
                'auto_login' => true
            ];
            $activityLogger->logActivity($row['email'], 'login_success', null, $additionalData);
        }

        // Update user status to online
        $email = $_SESSION['sessionWriter'];
        $userType = 'writer';
        updateUserStatus($email, $userType, true);
        touch_writer_session($con, $email);

        // Check for last page cookies and redirect
        if (isset($_COOKIE['last_page_before_timeout'])) {
            $lastPage = $_COOKIE['last_page_before_timeout'];
            setcookie('last_page_before_timeout', '', time() - 3600, '/');
            header("Location: $lastPage");
            exit();
        } elseif (isset($_COOKIE['last_page_before_logout'])) {
            $lastPage = $_COOKIE['last_page_before_logout'];
            setcookie('last_page_before_logout', '', time() - 3600, '/');
            header("Location: $lastPage");
            exit();
        }
    }
}
?>
