<?php
require_once('check-login.php');
require_once('session_tracker.php');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['odmsaid'])) {
    $aid = $_SESSION['odmsaid'];
    if (!touch_session($dbh, $aid)) {   // row gone => this device was logged out elsewhere
        session_unset();
        session_destroy();
        header('Location: login');
        exit();
    }
} else {
    header('Location: login.php');
    exit();
}


// Fetch userID from the database using the email stored in the session
$stmt = mysqli_prepare($con, "SELECT id FROM tbladmin WHERE email = ?");
mysqli_stmt_bind_param($stmt, "s", $aid);
mysqli_stmt_execute($stmt);
$userResult = mysqli_stmt_get_result($stmt);
$userRow = mysqli_fetch_assoc($userResult);

if ($userRow === null) {
    session_destroy();
    header('Location: login');
    exit();
}

$userID = $userRow['id'];

// Query to fetch unread messages details by userID
$unreadMessagesQuery = mysqli_query($con, "SELECT * FROM chat_messages WHERE is_read = 0 AND receiver_id = '$userID' ORDER BY timestamp ASC");

$unreadMessages = []; // Initialize array to hold unread messages data
while ($message = mysqli_fetch_assoc($unreadMessagesQuery)) {
    $unreadMessages[] = $message; // Add each unread message to the array
}

$unreadMessagesCount = count($unreadMessages); // Count the number of unread messages
?>
<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
