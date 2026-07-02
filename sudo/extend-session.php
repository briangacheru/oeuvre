<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['odmsaid']) || strlen($_SESSION['odmsaid']) == 0) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check for extend parameter in both POST and GET data
$extend = $_POST['extend'] ?? $_GET['extend'] ?? null;

if (isset($extend) && $extend === 'true') {
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();

    // Update user status in database
    include('dbcon.php');
    if (isset($_SESSION['odmsaid'])) {
        $email = $_SESSION['odmsaid'];
        $updateStatusSql = "UPDATE tbladmin SET is_online = 1, last_seen = NOW() WHERE email = ?";
        $stmt = $con->prepare($updateStatusSql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Session extended']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>