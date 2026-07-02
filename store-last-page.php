<?php
// Prevent any output before JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['lastPage'])) {
        $lastPage = $input['lastPage'];

        // Set cookie with more permissive settings for testing
        $cookieSet = setcookie('last_page_before_timeout', $lastPage, time() + 600, '/', '', false, false);

        error_log("Storing last page: " . $lastPage . " - Cookie set: " . ($cookieSet ? 'true' : 'false'));

        $response = [
            'success' => $cookieSet,
            'lastPage' => $lastPage,
            'debug' => 'Cookie set with 10 minute expiry'
        ];
    } else {
        $response = [
            'success' => false,
            'error' => 'No lastPage provided',
            'input' => $input
        ];
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'Server error occurred'
    ];
    error_log("Store last page error: " . $e->getMessage());
}

// Clear any buffered output and send JSON
ob_clean();
echo json_encode($response);
exit();
?>