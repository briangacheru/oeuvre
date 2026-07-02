<?php
ob_start();
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1); // Log errors to file
ini_set('error_log', __DIR__ . '/php-errors.log');
date_default_timezone_set('Africa/Nairobi');

include('dbcon.php');
include('functions.php');
require_once 'session_tracker.php';

$self = $_SERVER["PHP_SELF"];
$allowed_pages = ['login.php', 'reset-password.php', 'forgot-password.php'];

if (stripos($self, 'index.php') !== false) {
    if (!isset($_SESSION['sessionWriter']) || (isset($_SESSION['sessionWriter']) && strlen($_SESSION['sessionWriter']) == 0)) {
        header('Location: login.php');
        exit();
    }
} elseif (array_reduce($allowed_pages, fn($carry, $page) => $carry || stripos($self, $page) !== false, false)) {
    if (isset($_SESSION['sessionWriter']) && strlen($_SESSION['sessionWriter']) > 0) {
        header('Location: index.php');
        exit();
    }
}

// Define session timeout duration
$session_timeout_duration = 86400; // 24 hrs

// Check if last_activity is set
if (isset($_SESSION['last_activity'])) {
    // Check if the session is older than 60 minutes
    if (time() - $_SESSION['last_activity'] > $session_timeout_duration) {
        // Store the current page before logging out
        $lastPage = $_SERVER['REQUEST_URI'];

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
