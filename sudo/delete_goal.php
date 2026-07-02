<?php
include "check-login.php";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $goalID = $_POST['goalID'];
    $query = "UPDATE tblsavingsgoals SET is_deleted = 1 WHERE goalID = $goalID";

    if (mysqli_query($con, $query)) {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center"><p class="mb-0 flex-1">Saving goal deleted successfully!</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center"><p class="mb-0 flex-1">Error: ' . mysqli_error($con) . '</p>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    header("Location: saving-goals");
    exit();
}
?>