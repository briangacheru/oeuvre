<?php
include "check-login.php"; // Make sure you include your database connection file here

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=overdraft.csv');

$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, array('OD #', 'Writer', 'Amount', 'Date'));

$query=mysqli_query($con,"select * from tbloverdrafts WHERE is_deleted = 0 AND is_settled = 0 ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($query)) {

    // Populate data rows
    fputcsv($output, array($row["id"], $row["writer"], $row["amount"], $row["od_date"]));
}

fclose($output);
exit();
?>