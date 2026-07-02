<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');

$response = ['timeRemaining' => 0, 'loggedIn' => false];

try {
    if (isset($_SESSION['sessionWriter']) && isset($_SESSION['last_activity'])) {
        $sessionTimeout = 86400; // 60 minutes
        $timeElapsed = time() - $_SESSION['last_activity'];
        $timeRemaining = $sessionTimeout - $timeElapsed;

        $response['loggedIn'] = true;
        $response['timeRemaining'] = max(0, $timeRemaining);
        $response['debug'] = [
            'current_time' => time(),
            'last_activity' => $_SESSION['last_activity'],
            'time_elapsed' => $timeElapsed,
            'session_timeout' => $sessionTimeout
        ];
    } else {
        $response['loggedIn'] = false;
        $response['debug'] = [
            'session_writer_set' => isset($_SESSION['sessionWriter']),
            'last_activity_set' => isset($_SESSION['last_activity'])
        ];
    }
} catch (Exception $e) {
    $response['loggedIn'] = false;
    $response['error'] = 'Server error occurred';
    error_log("Session check error: " . $e->getMessage());
}

// Clear any buffered output and send JSON
ob_clean();
echo json_encode($response);
exit();
?>