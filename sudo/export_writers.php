<?php
include "check-login.php"; // Make sure you include your database connection file here

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=all_writers.csv');

$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, array('Writer Id', 'Name', 'Email', 'Phone', 'Verification'));

$query = mysqli_query($con, "SELECT * FROM tblwriters WHERE is_deleted = 0 ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($query)) {
    $is_verified = ($row['is_verified'] == 0) ? 'Unverified' : 'Verified';

    // Populate data rows
    fputcsv($output, array($row["id"], $row["name"], $row["email"], $row["phone"], $is_verified));
}

fclose($output);
exit();
?>
