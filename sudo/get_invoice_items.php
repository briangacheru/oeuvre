<?php
ob_start();
include "head.php";

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

$logId = isset($_GET['log_id']) ? (int) $_GET['log_id'] : 0;

if ($logId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid log ID.']);
    exit;
}

$items = [];
$result = mysqli_query($con,
    "SELECT task_id, topic, pages, cpp, amount
     FROM tbl_invoice_log_items
     WHERE log_id = $logId
     ORDER BY id ASC"
);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = [
            'task_id' => (int)   $row['task_id'],
            'topic'   =>         $row['topic'],
            'pages'   => (float)   $row['pages'],
            'cpp'     => (float) $row['cpp'],
            'amount'  => (float) $row['amount'],
        ];
    }
}

echo json_encode(['success' => true, 'items' => $items]);