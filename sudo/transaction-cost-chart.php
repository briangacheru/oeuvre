<?php
require 'check-login.php';

$filter = $_GET['filter'] ?? 'monthly';
if ($filter === 'yearly') {
    $query = "SELECT YEAR(expenseDate) AS period, SUM(transactionCost) AS totalTransactionCost
                                FROM tblbudget WHERE is_deleted = 0 GROUP BY period
                                UNION ALL
                                SELECT YEAR(od_date) AS period, SUM(transactionCost) AS totalTransactionCost
                                FROM tbloverdrafts WHERE is_deleted = 0
                                GROUP BY period ORDER BY period;";
} else { // Default to monthly
    $query = "SELECT DATE_FORMAT(expenseDate, '%Y-%m') AS period, SUM(transactionCost) AS totalTransactionCost
                                FROM tblbudget WHERE is_deleted = 0 GROUP BY period UNION ALL
                                SELECT DATE_FORMAT(od_date, '%Y-%m') AS period, SUM(transactionCost) AS totalTransactionCost
                                FROM tbloverdrafts WHERE is_deleted = 0 GROUP BY period ORDER BY period;";
}
$result = mysqli_query($con, $query);
$chartData = [];
while ($row = mysqli_fetch_assoc($result)) {
    $period = $row['period'];
    $totalTransactionCost = $row['totalTransactionCost'] ?? 0;

    if (!isset($chartData[$period])) {
        $chartData[$period] = 0;
    }
    $chartData[$period] += $totalTransactionCost;
}
ksort($chartData);
header('Content-Type: application/json');
echo json_encode([
    'categories' => array_keys($chartData), // Time periods
    'seriesData' => array_values($chartData), // Transaction costs
]);
?>