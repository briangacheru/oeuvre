<?php
include "check-login.php"; // Make sure you include your database connection file here

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=paid_tasks.csv');

$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, array('Task #', 'Topic', 'Pages', 'CPP', 'Amount', 'Status'));

$query=mysqli_query($con,"select * from tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND is_paid = 1 ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($query)) {
    $totalprice = $row["cpp"] * $row["pages"];
    $status = $row["status"]; // Simplified for CSV
    $is_paid = ($row['is_paid'] == 1) ? 'Paid' : 'Unpaid';

    // Populate data rows
    fputcsv($output, array($row["id"], $row["topic"], $row["pages"], $row["cpp"], number_format($totalprice, 2), $is_paid));
}

fclose($output);
exit();
?>
