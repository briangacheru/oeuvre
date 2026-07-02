<?php
session_start();
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/php-errors.log');
date_default_timezone_set('Africa/Nairobi');
include('dbcon.php');

// Store the current page or last page before logout
$redirect_after_login = '';
if (isset($_SESSION['last_page'])) {
    $redirect_after_login = $_SESSION['last_page'];
} else {
    // If no last page stored, use the current referrer or default
    $redirect_after_login = $_SERVER['HTTP_REFERER'] ?? 'index';
}

// Always perform logout when this page is accessed
// Update is_online and last_seen before logging out
if (isset($_SESSION['odmsaid'])) {
    $userEmail = $_SESSION['odmsaid'];
    require_once 'session_tracker.php';
    $delSess = $dbh->prepare("DELETE FROM tblsessions WHERE session_id = ?");
    $delSess->execute([session_id()]);
    $lastSeen = gmdate('Y-m-d H:i:s');

    $updateStatusSql = "UPDATE tbladmin SET is_online = 0, last_seen = ? WHERE email = ?";
    $stmt = $con->prepare($updateStatusSql);

    if (!$stmt) {
        echo "Prepare failed: (" . $con->errno . ") " . $con->error;
        exit;
    }

    $stmt->bind_param('ss', $lastSeen, $userEmail);
    if (!$stmt->execute()) {
        echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        exit;
    }
    $stmt->close();

    // Clear the remember token in the database if it's set
    if (isset($_COOKIE['rememberme'])) {
        $updateTokenSql = "UPDATE tbladmin SET remember_token = NULL WHERE email = ?";
        $stmt = $con->prepare($updateTokenSql);

        if (!$stmt) {
            echo "Prepare failed: (" . $con->errno . ") " . $con->error;
            exit;
        }

        $stmt->bind_param('s', $userEmail);
        if (!$stmt->execute()) {
            echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
            exit;
        }
        $stmt->close();
    }
}

// Clear the "Remember Me" cookie if it exists
if (isset($_COOKIE['rememberme'])) {
    setcookie('rememberme', '', time() - 3600, '/', '', isset($_SERVER["HTTPS"]), true);
}
// Log the manual logout event
error_log("User " . ($userEmail ?? 'unknown') . " manually logged out.");
// Clear all session variables
session_unset();
session_destroy();

// Destroy the PHP session cookie
setcookie('PHPSESSID', '', time() - 3600, '/', '', isset($_SERVER["HTTPS"]), true);

// Redirect to login page with the last page as redirect parameter
$redirect_url = urlencode($redirect_after_login);
header("Location: login?redirect=" . $redirect_url);
exit();
?>