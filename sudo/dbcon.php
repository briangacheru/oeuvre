<?php
require_once __DIR__ . '/../env.php';

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME'));

// Establish database connection
try {
    $dbh = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
    );
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit("Error: " . $e->getMessage());
}

// Create MySQLi connection
$con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (mysqli_connect_errno()) {
    echo "Connection Fail: " . mysqli_connect_error();
    exit();
}

mysqli_set_charset($con, "utf8mb4");
?>
