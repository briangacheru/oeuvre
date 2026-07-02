<?php
ob_start();
error_reporting(0); // Suppress errors for JSON response
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');

$response = ['success' => false];

try {
    // Check if writer session exists
    if (isset($_SESSION['sessionWriter']) && !empty($_SESSION['sessionWriter'])) {
        // Update last activity time in session
        $_SESSION['last_activity'] = time();

        // Include database connection
        include_once('dbcon.php');

        if (isset($con)) {
            $email = $_SESSION['sessionWriter'];

            // Update writer status in database
            $updateStatusSql = "UPDATE tblwriters SET is_online = 1, last_seen = NOW() WHERE email = ?";
            $stmt = $con->prepare($updateStatusSql);

            if ($stmt) {
                $stmt->bind_param('s', $email);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Session extended successfully';
                    $response['new_activity_time'] = $_SESSION['last_activity'];
                } else {
                    $response['error'] = 'Failed to update user status';
                    error_log("Failed to update writer status for: " . $email);
                }

                $stmt->close();
            } else {
                $response['error'] = 'Database prepare failed';
                error_log("Database prepare failed for writer session extension");
            }
        } else {
            $response['error'] = 'Database connection not available';
            error_log("Database connection not available for writer session extension");
        }
    } else {
        $response['error'] = 'No active writer session found';
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = 'Server error occurred';
    // Log the actual error for debugging
    error_log("Writer session extension error: " . $e->getMessage());
}

// Clear any buffered output and send JSON
ob_clean();
echo json_encode($response);
exit();
?>