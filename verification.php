<?php
include "header.php";

$aid = $_SESSION['sessionWriter'];
$sql = "SELECT * FROM tblwriters WHERE email=:aid";
$query = $dbh->prepare($sql);
$query->bindParam(':aid', $aid, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);

if ($query->rowCount() > 0) {
    foreach ($results as $rowWriter) {
        if ($rowWriter->is_verified == 0) {
            // User is not verified - show verification message
            ?>
            <div class="row-cols-lg-12">
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <h4 class="alert-heading">Notification</h4>
                    <p>Your account needs to be verified first</p>
                    <hr>
                    <p class="mb-0">Update your <a href="profile">Profile</a> in the mean time.</p>
                </div>
            </div>
            <?php
        } else {
            // User is verified - redirect to index.php
            header("Location: index");
            exit();
        }
    }
} else {
    // No user found - redirect to login
    header("Location: login");
    exit();
}

include "footer.php";
?>