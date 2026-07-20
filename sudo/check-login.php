<?php
require_once __DIR__ . '/../shared-functions.php';
ob_start();
ini_set('session.gc_maxlifetime', 604800); // 7 days in seconds
ini_set('session.cookie_lifetime', 604800); // 7 days in seconds
session_set_cookie_params(604800); // 7 days
require_once __DIR__ . '/session-name.php';
session_start();
require_once __DIR__ . '/../env.php';
$appDebug = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
error_reporting(E_ALL);
ini_set('log_errors', 1); // Log errors to file
ini_set('error_log', __DIR__ . '/php-errors.log');
date_default_timezone_set('Africa/Nairobi');
require_once('dbcon.php');
require_once('functions.php');
function check_login() {
    if (!isset($_SESSION['odmsaid']) || strlen($_SESSION['odmsaid']) == 0) {
        // Store current page for redirect
        $redirect_url = urlencode($_SERVER['REQUEST_URI']);

        $_SESSION["id"] = "";
        header("Location: login?redirect=" . $redirect_url);
        exit();
    }
}

// Function to format file size

function updateUserStatus($email, $userType, $isOnline) {
    global $con;
    $table = $userType === 'admin' ? 'tbladmin' : 'tblwriters';
    $lastSeen = $isOnline ? 'NOW()' : 'NOW()';

    $query = "UPDATE $table SET is_online = ?, last_seen = $lastSeen WHERE email = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("is", $isOnline, $email);
    $stmt->execute();
    $stmt->close();
}

function logout() {
    if (isset($_SESSION['odmsaid'])) {
        $email = $_SESSION['odmsaid'];
        $userType = 'admin'; // Adjust if necessary
        updateUserStatus($email, $userType, false);
    }
    session_unset();     // Unset $_SESSION variables
    session_destroy();   // Destroy session data on the server
    setcookie('PHPSESSID', '', time() - 3600, '/'); // Destroy session data in the cookie
}

$self = $_SERVER["PHP_SELF"];
$allowed_pages = ['login.php', 'reset-password.php', 'forgot-password.php', 'public-task-view.php', 'verify-login-code.php'];

// Get current script name
$currentScript = basename($_SERVER['PHP_SELF']);

// List of AJAX endpoints that shouldn't update last page tracking
$ajaxEndpoints = [
    'check_new_tasks.php',
    'logout_device.php',
    'get_notification_counts.php',
    'extend-session.php',
    'mark_task_read.php',
    'mark_all_tasks_read.php',
    'get-task-details.php',
    'update-task-acknowledgment.php',
    'mark-writer-comments-read.php',
    'get_amount_due.php',
    'notification_update.php',
    'get_sidebar_counts.php',
    'get-new-comments.php',
    'get_message_edits.php',
    'get_shared_files.php',
    'mark-comments-read.php',
    'complete-task.php',
    'confirm-paid.php',
    'toggle_favorite.php',
    'add-task-comment.php',
    'chart-data.php',
    'transaction-cost-chart.php',
    'fetch-savings-goals.php',
    'delete_file.php',
    'submit-task.php',
    'submit_task.php',
    'update-task.php',
    'pin_reset.php',
    'update-od.php',
    'update-writer.php',
    'update-task-writer.php',
    'get_invoice_items.php',
];

// Store current page for redirect (but not for login pages or AJAX endpoints)
if (!in_array(basename($_SERVER['PHP_SELF']), $allowed_pages) && !in_array($currentScript, $ajaxEndpoints)) {
    $_SESSION['last_page'] = $_SERVER['REQUEST_URI'];
}

if (stripos($self, 'index.php') !== false) {
    if (!isset($_SESSION['odmsaid']) || (isset($_SESSION['odmsaid']) && strlen($_SESSION['odmsaid']) == 0)) {
        $redirect_url = urlencode($_SERVER['REQUEST_URI']);
        header("Location: login?redirect=" . $redirect_url);
        exit();
    }
} elseif (array_reduce($allowed_pages, fn($carry, $page) => $carry || stripos($self, $page) !== false, false)) {
    if (isset($_SESSION['odmsaid']) && strlen($_SESSION['odmsaid']) > 0) {
        header('Location: index');
        exit();
    }
}

// Define session timeout duration - 24 hours
$session_timeout_duration = 604800; // 7 days in seconds (7 * 24 * 60 * 60)

// Check if last_activity is set
if (isset($_SESSION['last_activity'])) {
    // Check if the session is older than 7 days
    if (time() - $_SESSION['last_activity'] > $session_timeout_duration) {
        // Get the last page before logout
        $last_page = $_SESSION['last_page'] ?? 'index';

        // Store the redirect URL before destroying session
        $redirect_url = urlencode($last_page);

        // Logout the user
        logout();

        // Redirect to login page with last page parameter. timeout=1 tells
        // login.php this is a returning-after-expiry login, which requires
        // an emailed verification code (see login.php / verify-login-code.php).
        header("Location: login?redirect=" . $redirect_url . "&timeout=1");
        exit();
    }
}

// Update last activity time stamp
$_SESSION['last_activity'] = time();

// Update user status to online
if (isset($_SESSION['odmsaid'])) {
    $email = $_SESSION['odmsaid'];
    $userType = 'admin'; // Adjust if necessary
    updateUserStatus($email, $userType, true);
}

// If the "Remember Me" cookie is set, log the user in
if (!isset($_SESSION['odmsaid']) && isset($_COOKIE['rememberme'])) {
    // Look for the user with this remember token
    $rememberToken = $_COOKIE['rememberme'];
    $selectUserSql = "SELECT email FROM tbladmin WHERE remember_token = ?";
    $stmt = $con->prepare($selectUserSql);
    $stmt->bind_param('s', $rememberToken);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();

        // A valid remember-me token alone isn't enough - the browser must
        // also carry this account's known-device cookie, or a stolen/copied
        // remember-me token would silently re-establish a session with no
        // verification at all. An unrecognized device falls through here and
        // continues on to a full password + emailed-code login instead.
        $deviceToken = $_COOKIE['admin_device_token'] ?? null;
        if (is_known_device_token($con, 'tbladmin_known_devices', 'admin_email', $row['email'], $deviceToken)) {
            $_SESSION['odmsaid'] = $row['email']; // Log the user in by setting the session variable
            require_once 'session_tracker.php';
            record_login_session($dbh, $_SESSION['odmsaid']);
            remember_device($con, 'tbladmin_known_devices', 'admin_email', $row['email'], 'admin_device_token', $deviceToken);

            // Update user status to online
            $email = $_SESSION['odmsaid'];
            $userType = 'admin'; // Adjust if necessary
            updateUserStatus($email, $userType, true);

            // Redirect back to the last page if set
            if (isset($_SESSION['last_page'])) {
                $last_page = $_SESSION['last_page'];
                unset($_SESSION['last_page']); // Clear the stored last page
                header("Location: $last_page");
                exit();
            }
        }
    }
    $stmt->close();
}
?>