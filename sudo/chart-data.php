<?php
require 'check-login.php';

$filter = $_GET['filter'] ?? 'monthly';
$query = '';

// Prepare the query based on filter
if ($filter === 'daily') {
    $query = "
        SELECT 
            DATE(expenseDate) AS period, 
            SUM(CASE WHEN category = 'Income' THEN amount + IFNULL(transactionCost, 0) ELSE 0 END) AS income,
            SUM(CASE WHEN category = 'Expense' THEN amount + IFNULL(transactionCost, 0) ELSE 0 END) AS expenses,
            SUM(CASE WHEN category = 'Savings' THEN amount ELSE 0 END) AS savings
        FROM tblbudget
        WHERE is_deleted = 0 AND DATE(expenseDate) >= CURDATE() - INTERVAL 30 DAY
        GROUP BY DATE(expenseDate)
        ORDER BY DATE(expenseDate) ASC
    ";
} elseif ($filter === 'weekly') {
    $query = "
        SELECT 
            CONCAT(YEAR(expenseDate), ' W', LPAD(WEEK(expenseDate, 1), 2, '0')) AS period,
            SUM(CASE WHEN category = 'Income' THEN amount + IFNULL(transactionCost, 0) ELSE 0 END) AS income,
            SUM(CASE WHEN category = 'Expense' THEN amount + IFNULL(transactionCost, 0) ELSE 0 END) AS expenses,
            SUM(CASE WHEN category = 'Savings' THEN amount ELSE 0 END) AS savings
        FROM tblbudget
        WHERE is_deleted = 0 AND expenseDate >= CURDATE() - INTERVAL 90 DAY
        GROUP BY YEAR(expenseDate), WEEK(expenseDate, 1)
        ORDER BY YEAR(expenseDate) ASC, WEEK(expenseDate, 1) ASC
    ";
} elseif ($filter === 'yearly') {
    $query = "
        SELECT 
            YEAR(expenseDate) AS period,
            SUM(CASE WHEN category = 'Income' THEN amount + IFNULL(transactionCost, 0) ELSE 0 END) AS income,
            SUM(CASE WHEN category = 'Expense' THEN amount + IFNULL(transactionCost, 0) ELSE 0 END) AS expenses,
            SUM(CASE WHEN category = 'Savings' THEN amount ELSE 0 END) AS savings
        FROM tblbudget
        WHERE is_deleted = 0
        GROUP BY YEAR(expenseDate)
        ORDER BY YEAR(expenseDate) ASC
    ";
} else { // Monthly
    $query = "
        SELECT 
            DATE_FORMAT(expenseDate, '%Y-%m') AS period,
            SUM(CASE WHEN category = 'Income' THEN amount + IFNULL(transactionCost, 0) ELSE 0 END) AS income,
            SUM(CASE WHEN category = 'Expense' THEN amount + IFNULL(transactionCost, 0) ELSE 0 END) AS expenses,
            SUM(CASE WHEN category = 'Savings' THEN amount ELSE 0 END) AS savings
        FROM tblbudget
        WHERE is_deleted = 0
        GROUP BY DATE_FORMAT(expenseDate, '%Y-%m')
        ORDER BY DATE_FORMAT(expenseDate, '%Y-%m') ASC
    ";
}

$result = $con->query($query);

// Check for errors
if (!$result) {
    error_log("Chart Data Query Error: " . $con->error);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'Database query failed',
        'sql_error' => $con->error
    ]);
    exit;
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Log results for debugging
error_log("Chart Data - Filter: $filter, Rows: " . count($data));

header('Content-Type: application/json');
echo json_encode($data);
?>