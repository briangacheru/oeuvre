<?php
include "check-login.php"; // Make sure you include your database connection file here

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=all_tasks.csv');

$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, array('Task #', 'Topic', 'Status', 'Account', 'Subject', 'Amount', 'Status'));

$query = mysqli_query($con, "SELECT * FROM tbltasks WHERE is_deleted = 0 ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($query)) {
    $totalprice = $row["cpp"] * $row["pages"];
    $status = $row["status"]; // Simplified for CSV
    $is_paid = ($row['is_paid'] == 1) ? 'Paid' : 'Unpaid';

    // Populate data rows
    fputcsv($output, array($row["id"], $row["topic"], $status, $row["account"], $row["subject"], number_format($totalprice, 2), $is_paid));
}

fclose($output);
exit();
?>
