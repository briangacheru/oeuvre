<?php
require_once __DIR__ . '/../env.php';

$servername = env('DB_HOST', 'localhost');
$username = env('DB_USER');
$password = env('DB_PASS', '');
$dbname = env('DB_NAME');

// Create connection
$con = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}
mysqli_set_charset($con, "utf8mb4");

?>
