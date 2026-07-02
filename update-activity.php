<?php
// Prevent any output before JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

try {
    if (isset($_SESSION['sessionWriter'])) {
        $_SESSION['last_activity'] = time();
        $response = [
            'success' => true,
            'timestamp' => $_SESSION['last_activity']
        ];
    } else {
        $response = [
            'success' => false,
            'error' => 'No active session'
        ];
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'Server error occurred'
    ];
    error_log("Update activity error: " . $e->getMessage());
}

// Clear any buffered output and send JSON
ob_clean();
echo json_encode($response);
exit();
?>