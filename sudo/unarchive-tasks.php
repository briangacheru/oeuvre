<?php
include "head.php"; // Include your database connection and session start

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['taskIds'])) {

    // The array of task IDs to update
    $taskIds = $_POST['taskIds'];

    // Validation: Ensure each value in $taskIds is an integer
    $taskIds = array_filter($taskIds, function($value) {
        return (is_numeric($value) && (int)$value == $value);
    });

    if (count($taskIds) > 0) {
        // Prepare a string of comma-separated task IDs for the SQL query
        $idsString = implode(',', array_map('intval', $taskIds));

        // SQL query to update tasks status with additional conditions
        $sql = "UPDATE tbltasks SET is_archived = 0 WHERE id IN ($idsString) AND status = 'Completed' AND is_paid = 1";

        if (mysqli_query($con, $sql)) {
            // Check if any rows were updated
            if (mysqli_affected_rows($con) > 0) {
                $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
                                        <div class="bg-success me-3 icon-item"><span class="fas fa-check-circle text-white fs-6"></span></div>
                                        <p class="mb-0 flex-1">Tasks unarchived successfully!</p>
                                        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                      </div>';
            } else {
                $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
                                        <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
                                        <p class="mb-0 flex-1">No tasks were unarchived. Please ensure the tasks are completed and paid!</p>
                                        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                      </div>';
            }
        } else {
            $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                                      <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
                                      <p class="mb-0 flex-1">Error unarchiving tasks: ' . mysqli_error($con) . '</p>
                                      <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>';
        }
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                                      <div class="bg-danger me-3 icon-item"><span the "fas fa-times-circle text-white fs-6"></span></div>
                                      <p class="mb-0 flex-1">No valid task IDs received!</p>
                                      <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>';
    }
} else {
    $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                                      <div class="bg-danger me-3 icon-item"><span class "fas fa-times-circle text-white fs-6"></span></div>
                                      <p class="mb-0 flex-1">No tasks were selected!</p>
                                      <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>';
}

// Redirect back to the tasks page
header('Location: archived-tasks');
exit;
?>
