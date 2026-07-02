<?php
ob_start();
ini_set('session.gc_maxlifetime', 86400); // 24 hours in seconds
ini_set('session.cookie_lifetime', 86400); // 24 hours in seconds
session_set_cookie_params(86400); // 24 hours
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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
function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }
    return $bytes;
}

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
$allowed_pages = ['login.php', 'reset-password.php', 'forgot-password.php', 'public-task-view.php'];

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
    'get_notification_counts.php'
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
$session_timeout_duration = 86400; // 24 hours in seconds (24 * 60 * 60)

// Check if last_activity is set
if (isset($_SESSION['last_activity'])) {
    // Check if the session is older than 24 hours
    if (time() - $_SESSION['last_activity'] > $session_timeout_duration) {
        // Get the last page before logout
        $last_page = $_SESSION['last_page'] ?? 'index';

        // Add session timeout logging
        //error_log("Session timeout - Last activity: " . (time() - $_SESSION['last_activity']) . " seconds ago. Redirecting to: " . $last_page);

        // Store the redirect URL before destroying session
        $redirect_url = urlencode($last_page);

        // Logout the user
        logout();

        // Redirect to login page with last page parameter
        header("Location: login?redirect=" . $redirect_url);
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
        $_SESSION['odmsaid'] = $row['email']; // Log the user in by setting the session variable
        require_once 'session_tracker.php';
        record_login_session($dbh, $_SESSION['odmsaid']);

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
    $stmt->close();
}
?>