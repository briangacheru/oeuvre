<?php
include "head.php"; // Include your database connection and session start
csrf_verify_or_redirect();
requireCapability($currentAdminRole, 'operate_finance');

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

        // Payment method fields come from the "Mark as Paid" modal on
        // unpaid-tasks.php. They're optional here (not just required) so
        // any other caller posting to this legacy bulk endpoint without the
        // modal (e.g. all-tasks.php's identical bulk button) still works,
        // falling back to the old paid-now/no-transaction-code behavior.
        $paymentMethod = $_POST['payment_method'] ?? '';
        $transactionCode = null;
        $paymentError = '';

        if ($paymentMethod === 'transaction_code') {
            $transactionCode = trim($_POST['transaction_code'] ?? '');
            $paidOnInput = trim($_POST['paid_on'] ?? '');

            if ($transactionCode === '') {
                $paymentError = 'Please enter the transaction code.';
            } elseif ($paidOnInput === '') {
                $paymentError = 'Please enter the transaction date and time.';
            } else {
                $paidOnDt = DateTime::createFromFormat('Y-m-d\TH:i', $paidOnInput);
                if (!$paidOnDt) {
                    $paymentError = 'Invalid transaction date/time.';
                } else {
                    // paid_on is Nairobi-local (PHP date()) by convention - the
                    // admin-entered value is treated the same way, no timezone
                    // conversion.
                    $paid_on = $paidOnDt->format('Y-m-d H:i:s');
                }
            }
        } elseif ($paymentMethod === 'overdraft') {
            $paid_on = date('Y-m-d H:i:s');
        } else {
            // Legacy fallback: no payment method supplied.
            $paid_on = date('Y-m-d H:i:s');
            $paymentMethod = null;
        }

        if ($paymentError !== '') {
            $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                                      <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
                                      <p class="mb-0 flex-1">' . htmlspecialchars($paymentError, ENT_QUOTES, 'UTF-8') . '</p>
                                      <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>';
            header('Location: unpaid-tasks.php');
            exit;
        }

        // SQL query to update tasks status - same paid_on/transaction_code
        // applied to every selected task individually via the IN() clause.
        $sql = "UPDATE tbltasks SET is_paid = 1, paid_on = ?, payment_method = ?, transaction_code = ? WHERE id IN ($idsString) AND status = 'Completed' AND is_paid = 0";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, 'sss', $paid_on, $paymentMethod, $transactionCode);

        if (mysqli_stmt_execute($stmt)) {
            // Check if any rows were updated
            $paidCount = mysqli_affected_rows($con);
            $submittedCount = count($taskIds);

            if ($paidCount > 0) {
                $taskWord = ($paidCount === 1) ? 'task' : 'tasks';
                $message = '<strong>' . $paidCount . '</strong> ' . $taskWord . ' paid successfully!';

                if ($paidCount < $submittedCount) {
                    $skipped = $submittedCount - $paidCount;
                    $skippedWord = ($skipped === 1) ? 'task was' : 'tasks were';
                    $message .= ' (' . $skipped . ' ' . $skippedWord . ' skipped — already paid or not completed.)';
                }

                $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
                            <div class="bg-success me-3 icon-item"><span class="fas fa-check-circle text-white fs-6"></span></div>
                            <p class="mb-0 flex-1">' . $message . '</p>
                            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
            } else {
                $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
                                        <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
                                        <p class="mb-0 flex-1">No tasks were updated. Please ensure you are selecting unpaid tasks only!</p>
                                        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                      </div>';
            }
        } else {
            $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                                      <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
                                      <p class="mb-0 flex-1">Error updating tasks: ' . safe_db_error(mysqli_error($con)) . '</p>
                                      <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>';
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                                      <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
                                      <p class="mb-0 flex-1">No valid task IDs received!</p>
                                      <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>';
    }
} else {
    $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                                      <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-6"></span></div>
                                      <p class="mb-0 flex-1">No tasks were selected!</p>
                                      <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>';
}

// Redirect back to the tasks page
header('Location: unpaid-tasks.php');
exit;
?>
