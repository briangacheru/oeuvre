<?php
/**
 * Shared helper functions used by BOTH the writer (root) and administrator (sudo)
 * interfaces. These are the functions that were byte-identical in functions.php and
 * sudo/functions.php. Interface-specific helpers (email_exists, username_exists, etc.)
 * remain in each interface's own functions file.
 */

if (!function_exists('display_alert')) {
function display_alert() {
    if(isset($_SESSION['alert'])) {
        echo $_SESSION['alert'];
        unset($_SESSION['alert']); // Clear the alert after displaying it
    }
}
}

if (!function_exists('display_message')) {
function display_message() {
    if (isset($_SESSION['message']) && !empty($_SESSION['message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
        <p class="mb-0 flex-1"><strong>Error: </strong>' . $_SESSION['message'] . '</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
        unset($_SESSION['message']);
    }
}
}

if (!function_exists('display_subAlert')) {
function display_subAlert() {
    if(isset($_SESSION['subAlert'])) {
        echo $_SESSION['subAlert'];
        unset($_SESSION['subAlert']); // Clear the alert after displaying it
    }
}
}

if (!function_exists('logged_in')) {
function logged_in(){
    if(isset($_SESSION['userSession']) || isset($_COOKIE['email'])){
        return true;
    } else {
        return false;
    }
}
}

if (!function_exists('redirect')) {
function redirect($location){
    return header("Location: {$location}");
}
}

if (!function_exists('set_alert')) {
function set_alert($alert) {
    if(!empty($alert)) {
        $_SESSION['alert'] = $alert;
    } else {
        $alert = "";
    }
}
}

if (!function_exists('set_message')) {
function set_message($message)
{
    if(!empty($message)){
        $_SESSION['message'] = $message;
    }else {
        $message = "";
    }
}
}

if (!function_exists('set_subAlert')) {
function set_subAlert($subAlert) {
    if(!empty($subAlert)) {
        $_SESSION['subAlert'] = $subAlert;
    } else {
        $subAlert = "";
    }
}
}

if (!function_exists('timeAgo')) {
function timeAgo($datetime)
{
    $commentTime = new DateTime($datetime);
    $now = new DateTime();
    $interval = $now->diff($commentTime);

    if ($interval->y > 0) {
        return $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
    } elseif ($interval->m > 0) {
        return $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
    } elseif ($interval->d > 0) {
        return $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
    } elseif ($interval->h > 0) {
        return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
    } elseif ($interval->i > 0) {
        return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}
}

if (!function_exists('validation_errors')) {
function validation_errors($error_message)
{
    $error_message = <<<DELIMITER

<div class="alert alert-danger text-center" role="alert">
  	<strong>Warning!</strong> $error_message
 </div>
DELIMITER;

    set_message($error_message);
}
}

