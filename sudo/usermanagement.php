<?php
require_once __DIR__ . '/../env.php'; include "head.php";?>
    <title>iTasker | Writer Management</title>
<?php include "navi.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

function sendVerificationEmail($writerName, $writerEmail, $action, $writerId)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USER');
        $mail->Password = env('SMTP_PASS');
        $mail->SMTPSecure = 'tls';
        $mail->Port = (int) env('SMTP_PORT', 587);

        // Recipients
        $mail->setFrom(env('MAIL_FROM_ADDRESS'), 'itasker');
        $mail->addReplyTo(env('ADMIN_EMAIL'), 'itasker admin');
        $mail->addAddress($writerEmail);
        $mail->addAddress(env('ADMIN_EMAIL'), 'itasker admin');

        // Content
        $status = $action == 'verified' ? 'VERIFIED' : 'UNVERIFIED';
        $statusColor = $action == 'verified' ? '#28a745' : '#dc3545';
        $mail->isHTML(true);
        $mail->Subject = 'Account ' . $status . ' - itasker Writer ID: ' . $writerId . ' ';

        // Email Body with Logo and Modern Formatting
        $companyLogo = 'https://web.monkbrian.com/assets/img/team/itasker-email-header.png';
        $dashboardUrl = "https://web.monkbrian.com/login";

        $verificationMessage = $action == 'verified'
            ? "Congratulations! Your writer account has been successfully verified. You can now access all platform features and start receiving tasks."
            : "Your writer account verification has been revoked. Please contact support if you believe this is an error.";

        $actionButton = $action == 'verified'
            ? "<a class='btn' href='$dashboardUrl' style='background: #28a745;'>Access itasker</a>"
            : "<a class='btn' href='mailto:bryo4419@gmail.com' style='background: #dc3545;'>Contact Support</a>";

        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        background-color: #f4f4f4;
                        padding: 20px;
                    }
                    .email-container {
                        max-width: 600px;
                        background: #ffffff;
                        margin: 0 auto;
                        padding: 20px;
                        border-radius: 8px;
                        box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.1);
                    }
                    .email-header {
                        text-align: center;
                        border-bottom: 2px solid #0073e6;
                        padding-bottom: 15px;
                    }
                    .email-header img {
                        max-width: 100%;
                        height: auto;
                        max-height:100px;
                    }
                    .email-content {
                        padding: 20px;
                    }
                    .email-content h2 {
                        color: $statusColor;
                        text-align: center;
                    }
                    .email-content p {
                        font-size: 16px;
                        line-height: 1.5;
                        color: #333;
                    }
                    .highlight {
                        font-weight: bold;
                        color: #0073e6;
                    }
                    .status-badge {
                        display: inline-block;
                        background: $statusColor;
                        color: white;
                        padding: 8px 16px;
                        border-radius: 20px;
                        font-weight: bold;
                        font-size: 14px;
                    }
                    .btn {
                        display: block;
                        text-align: center;
                        color: #ffffff;
                        padding: 12px;
                        border-radius: 5px;
                        text-decoration: none;
                        font-size: 16px;
                        font-weight: bold;
                        margin-top: 20px;
                        transition: opacity 0.3s ease-in-out;
                    }
                    .btn:hover {
                        opacity: 0.8;
                        color: #ffffff !important;
                    }
                    .footer {
                        text-align: center;
                        padding-top: 15px;
                        font-size: 12px;
                        color: #777;
                    }
                    .verification-info {
                        background: #f8f9fa;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 15px 0;
                        border-left: 4px solid $statusColor;
                    }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='email-header'>
                        <img src='{$companyLogo}' alt='Company Logo'>
                    </div>
                    <div class='email-content'>
                        <h2>Account Verification Update</h2>
                        <p>Hello <span class='highlight'>$writerName</span>,</p>
                        <div class='verification-info'>
                            <p style='margin: 0; text-align: center;'>
                                Your account status: <span class='status-badge'>$status</span>
                            </p>
                        </div>
                        <p>$verificationMessage</p>
                        <p><strong>Writer ID:</strong> <span class='highlight'>$writerId</span></p>
                        <p><strong>Email:</strong> <span class='highlight'>$writerEmail</span></p>
                        <p><strong>Status Changed:</strong> <span class='highlight'>" . date('F j, Y \a\t g:i A') . "</span></p>
                        $actionButton
                    </div>
                    <div class='footer'>
                        <p>For any questions, contact <a href='mailto:bryo4419@gmail.com'>bryo4419@gmail.com</a></p>
                        <p>&copy; " . date('Y') . " itasker. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>";
        $mail->AltBody = "Account Verification Update\n\n
                    Hello $writerName,\n\n
                    Your account status: $status\n
                    $verificationMessage\n\n
                    Writer ID: $writerId\n
                    Email: $writerEmail\n
                    Status Changed: " . date('F j, Y \a\t g:i A') . "\n\n
                    Dashboard: $dashboardUrl\n\n
                    For any questions, contact bryo4419@gmail.com";

        $mail->send();

    } catch (Exception $e) {
        error_log("Verification email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

// Function to send deactivation email to admin only
function sendDeactivationEmailToAdmin($writerName, $writerEmail, $writerId, $reason, $isAutomatic = false)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USER');
        $mail->Password = env('SMTP_PASS');
        $mail->SMTPSecure = 'tls';
        $mail->Port = (int) env('SMTP_PORT', 587);

        // Recipients - Admin only
        $mail->setFrom(env('MAIL_FROM_ADDRESS'), 'itasker');
        $mail->addReplyTo(env('ADMIN_EMAIL'), 'itasker admin');
        $mail->addAddress(env('ADMIN_EMAIL'), 'itasker admin');

        // Content
        $deactivationType = $isAutomatic ? 'AUTOMATIC' : 'MANUAL';
        $mail->isHTML(true);
        $mail->Subject = 'Account DEACTIVATED (' . $deactivationType . ') - itasker Writer ID: ' . $writerId;

        // Email Body
        $companyLogo = 'https://web.monkbrian.com/assets/img/team/itasker-email-header.png';

        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        background-color: #f4f4f4;
                        padding: 20px;
                    }
                    .email-container {
                        max-width: 600px;
                        background: #ffffff;
                        margin: 0 auto;
                        padding: 20px;
                        border-radius: 8px;
                        box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.1);
                    }
                    .email-header {
                        text-align: center;
                        border-bottom: 2px solid #0073e6;
                        padding-bottom: 15px;
                    }
                    .email-header img {
                        max-width: 100%;
                        height: auto;
                        max-height:100px;
                    }
                    .email-content {
                        padding: 20px;
                    }
                    .email-content h2 {
                        color: #dc3545;
                        text-align: center;
                    }
                    .email-content p {
                        font-size: 16px;
                        line-height: 1.5;
                        color: #333;
                    }
                    .highlight {
                        font-weight: bold;
                        color: #0073e6;
                    }
                    .status-badge {
                        display: inline-block;
                        background: #dc3545;
                        color: white;
                        padding: 8px 16px;
                        border-radius: 20px;
                        font-weight: bold;
                        font-size: 14px;
                    }
                    .type-badge {
                        display: inline-block;
                        background: " . ($isAutomatic ? '#ffc107' : '#6c757d') . ";
                        color: " . ($isAutomatic ? '#000' : '#fff') . ";
                        padding: 4px 10px;
                        border-radius: 10px;
                        font-weight: bold;
                        font-size: 12px;
                    }
                    .footer {
                        text-align: center;
                        padding-top: 15px;
                        font-size: 12px;
                        color: #777;
                    }
                    .reason-box {
                        background: #fff3cd;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 15px 0;
                        border-left: 4px solid #ffc107;
                    }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='email-header'>
                        <img src='{$companyLogo}' alt='Company Logo'>
                    </div>
                    <div class='email-content'>
                        <h2>Account Deactivation Notice</h2>
                        <p style='text-align: center;'>
                            <span class='status-badge'>DEACTIVATED</span>
                            <span class='type-badge'>$deactivationType</span>
                        </p>
                        <p><strong>Writer ID:</strong> <span class='highlight'>$writerId</span></p>
                        <p><strong>Writer Name:</strong> <span class='highlight'>$writerName</span></p>
                        <p><strong>Email:</strong> <span class='highlight'>$writerEmail</span></p>
                        <p><strong>Deactivated On:</strong> <span class='highlight'>" . date('F j, Y \a\t g:i A') . "</span></p>
                        <div class='reason-box'>
                            <p style='margin: 0;'><strong>Reason for Deactivation:</strong></p>
                            <p style='margin: 5px 0 0 0;'>$reason</p>
                        </div>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " itasker. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>";

        $mail->AltBody = "Account Deactivation Notice\n\n
                    Writer ID: $writerId\n
                    Writer Name: $writerName\n
                    Email: $writerEmail\n
                    Deactivation Type: $deactivationType\n
                    Deactivated On: " . date('F j, Y \a\t g:i A') . "\n
                    Reason: $reason";

        $mail->send();

    } catch (Exception $e) {
        error_log("Deactivation email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

// Auto-deactivate users who haven't logged in for 6 months
function autoDeactivateInactiveUsers($con) {
    $sixMonthsAgo = date('Y-m-d H:i:s', strtotime('-6 months'));

    // Find users who haven't logged in for 6 months and are still active
    $query = mysqli_query($con, "SELECT id, username, email, last_seen FROM tblwriters WHERE last_seen < '$sixMonthsAgo' AND is_active = 1");

    $deactivatedCount = 0;
    while ($row = mysqli_fetch_assoc($query)) {
        $updateQuery = mysqli_query($con, "UPDATE tblwriters SET is_active = 0, is_verified = 0, deactivation_reason = 'Automatic deactivation: No login activity for 6 months', deactivated_at = NOW() WHERE id = '" . $row['id'] . "'");

        if ($updateQuery) {
            // Send email to admin only
            sendDeactivationEmailToAdmin($row['username'], $row['email'], $row['id'], 'Automatic deactivation: No login activity for 6 months (Last seen: ' . $row['last_seen'] . ')', true);
            $deactivatedCount++;
        }
    }

    return $deactivatedCount;
}

// Run auto-deactivation check
$autoDeactivated = autoDeactivateInactiveUsers($con);
if ($autoDeactivated > 0) {
    $_SESSION['auto_deactivation_notice'] = '<div class="alert alert-info alert-dismissible fade show" role="alert"><i class="bi bi-info-circle"></i> ' . $autoDeactivated . ' writer(s) have been automatically deactivated due to 6 months of inactivity.
                     <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
}

$status = "OK";
$msg = "";
if (isset($_GET['delid'])) {
    $cmpid = $_GET['delid'];
    if (is_numeric($cmpid) && !empty($cmpid)) {
        // Perform the delete operation
        $query = mysqli_query($con, "DELETE FROM tblwriters WHERE id='$cmpid'");
        if ($query) {
            $_SESSION['alert'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-check-circle"></i> User record deleted.
                                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
        } else {
            $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-octagon"></i> Error deleting user record.
                                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
            echo "Error: " . mysqli_error($con);
        }
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-octagon"></i> Invalid or missing ID.
                             <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
    }

    header('Location: usermanagement');
    exit;
}

// Writer verification
if (isset($_GET['verifyid'])) {
    $userid = $_GET['verifyid'];
    if (is_numeric($userid) && !empty($userid)) {
        // Get writer details before updating
        $writerQuery = mysqli_query($con, "SELECT username, email, is_verified FROM tblwriters WHERE id='$userid'");
        $writerData = mysqli_fetch_assoc($writerQuery);

        if ($writerData) {
            $currentStatus = $writerData['is_verified'];
            $newStatus = $currentStatus ? 0 : 1; // Toggle status
            $writerName = $writerData['username'];
            $writerEmail = $writerData['email'];

            // Perform the update operation
            $query = mysqli_query($con, "UPDATE tblwriters SET is_verified = '$newStatus' WHERE id='$userid'");
            if ($query) {
                // Send email notification
                $action = $newStatus ? 'verified' : 'unverified';
                sendVerificationEmail($writerName, $writerEmail, $action, $userid);

                $statusText = $newStatus ? 'verified' : 'unverified';
                $_SESSION['alert'] = '<div class="alert alert-success alert-dismissible fade show" role="alert">User verification status updated to ' . $statusText . '. Email notification sent.
                                     <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
            } else {
                $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert">Error updating verification status.
                                     <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
            }
        } else {
            $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert">Writer not found.
                                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
        }
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert">Invalid or missing ID for verification status update.
                             <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
    }

    header('Location: usermanagement');
    exit;
}

// Writer deactivation/reactivation
if (isset($_POST['deactivate_writer'])) {
    $userid = $_POST['writer_id'];
    $reason = mysqli_real_escape_string($con, $_POST['deactivation_reason']);

    if (is_numeric($userid) && !empty($userid) && !empty($reason)) {
        // Get writer details before updating
        $writerQuery = mysqli_query($con, "SELECT username, email, is_active FROM tblwriters WHERE id='$userid'");
        $writerData = mysqli_fetch_assoc($writerQuery);

        if ($writerData) {
            $currentStatus = isset($writerData['is_active']) ? $writerData['is_active'] : 1;
            $writerName = $writerData['username'];
            $writerEmail = $writerData['email'];

            // Deactivate and also set is_verified to 0
            $query = mysqli_query($con, "UPDATE tblwriters SET is_active = 0, is_verified = 0, deactivation_reason = '$reason', deactivated_at = NOW() WHERE id='$userid'");

            if ($query) {
                // Send email to admin only
                sendDeactivationEmailToAdmin($writerName, $writerEmail, $userid, $reason, false);

                $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-circle"></i> Writer account deactivated successfully. Admin notification sent.
                                     <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
            } else {
                $_SESSION['alert'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error deactivating account.
                                     <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
            }
        } else {
            $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert">Writer not found.
                                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
        }
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert">Invalid data. Please provide a reason for deactivation.
                             <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
    }

    header('Location: usermanagement');
    exit;
}

// Writer reactivation
if (isset($_GET['reactivateid'])) {
    $userid = $_GET['reactivateid'];
    if (is_numeric($userid) && !empty($userid)) {
        $query = mysqli_query($con, "UPDATE tblwriters SET is_active = 1, deactivation_reason = NULL, deactivated_at = NULL WHERE id='$userid'");

        if ($query) {
            $_SESSION['alert'] = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="bi bi-check-circle"></i> Writer account reactivated successfully.
                                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
        } else {
            $_SESSION['alert'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error reactivating account.
                                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
        }
    }

    header('Location: usermanagement');
    exit;
}
?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Writer <span class="text-info fw-medium"> Records</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0">
                    <form class="row flex-lg-column flex-xxl-row gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
                        <div class="col-auto">
                        </div>
                        <div class="col-md-auto position-relative">
                            <h6 class="mb-1 badge rounded-pill badge-subtle-info"><?php echo date("jS F Y"); ?> | <span id="timeDisplay"></span></h6>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php
if (isset($_SESSION['auto_deactivation_notice'])) {
    echo $_SESSION['auto_deactivation_notice'];
    unset($_SESSION['auto_deactivation_notice']);
}

if (isset($_SESSION['alert'])) {
    echo $_SESSION['alert'];
    unset($_SESSION['alert']); // Clear the alert message
}
?>
    <div class="row  g-3 mb-3">
    <div class="col">
        <div class="card mb-3">
            <div class="card-body p-0">
                <div class="tab-content">
                    <div class="tab-pane preview-tab-pane active" role="tabpanel" aria-labelledby="tab-dom-41cf422d-2a1d-40e2-b92a-ceac8cdfaca0" id="dom-41cf422d-2a1d-40e2-b92a-ceac8cdfaca0">
                        <div class="card shadow-none">
                            <form id="tasksForm" method="post">
                                <div class="card-header">
                                    <div class="row flex-between-center">
                                        <div class="col-6 col-sm-auto d-flex align-items-center pe-0">
                                        </div>
                                        <div class="col-6 col-sm-auto ms-auto text-end ps-0">
                                            <div class="d-flex align-items-center" id="table-simple-pagination-replace-element">
                                                <button class="btn btn-falcon-primary btn-sm" onclick="exportWriter()" data-bs-toggle="tooltip" data-bs-placement="top" title="Export as CSV" type="button"><span class="fas fa-external-link-alt" data-fa-transform="shrink-3 down-2"></span><span class="d-none d-sm-inline-block ms-1">Export as CSV</span></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body px-0 pt-0">
                                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables="data-datatables">
                                        <thead class="bg-200">
                                        <tr>
                                            <th class="text-900 no-sort white-space-nowrap">
                                                <div class="form-check mb-0 d-flex align-items-center">
                                                    <input class="form-check-input" id="checkbox-select-all" type="checkbox" onclick="selectAllTasks(this)" data-bulk-select='{"body":"table-simple-pagination-body","actions":"table-simple-pagination-actions","replacedElement":"table-simple-pagination-replace-element"}' />
                                                </div>
                                            </th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Writer ID</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Name</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Email</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Date Registered</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Status</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Verification</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Devices</th>
                                            <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                                        </tr>
                                        </thead>
                                        <tbody class="list" id="table-simple-pagination-body">
                                        <?php
                                        $query=mysqli_query($con,"SELECT * FROM tblwriters ORDER BY created_at DESC");

                                        // Active logged-in device count per writer (sessions active within the last 24h).
                                        // Keyed by writer email; will be 0 until writer-login recording is wired.
                                        $writerDeviceCounts = [];
                                        $activeCutoff = date('Y-m-d H:i:s', time() - 86400); // matches the 24h session timeout
                                        $wdcStmt = mysqli_prepare($con, "SELECT writer_email, COUNT(*) AS device_count
                                                                         FROM tblwriter_sessions
                                                                         WHERE last_activity >= ?
                                                                         GROUP BY writer_email");
                                        if ($wdcStmt) { // null if tblwriter_sessions doesn't exist yet -> all counts stay 0
                                            mysqli_stmt_bind_param($wdcStmt, 's', $activeCutoff);
                                            mysqli_stmt_execute($wdcStmt);
                                            $wdcRes = mysqli_stmt_get_result($wdcStmt);
                                            while ($wdcRow = mysqli_fetch_assoc($wdcRes)) {
                                                $writerDeviceCounts[strtolower($wdcRow['writer_email'])] = (int)$wdcRow['device_count'];
                                            }
                                            mysqli_stmt_close($wdcStmt);
                                        }

                                        $cnt=1;
                                        while($row=mysqli_fetch_array($query)) {
                                            $encodedId = base64_encode($row["id"]); // Encode the id
                                            $isActive = isset($row['is_active']) ? $row['is_active'] : 1; // Default to active if column doesn't exist

                                            // Check if registered less than a month ago and not verified
                                            $registeredDate = new DateTime($row['created_at'], new DateTimeZone('UTC'));
                                            $registeredDate->setTimezone(new DateTimeZone('Africa/Nairobi'));
                                            $now = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
                                            $diff = $now->diff($registeredDate);
                                            $isNewAndUnverified = ($diff->m == 0 && $diff->y == 0) && $row['is_verified'] == 0;
                                            ?>
                                            <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                                                <td class="align-middle" style="width: 28px;">
                                                    <div class="form-check mb-0">
                                                        <input class="form-check-input" type="checkbox" id="simple-pagination-item-<?php echo $cnt; ?>" data-bulk-select-row="data-bulk-select-row" value="<?php echo $row['id']; ?>" name="taskIds[]"/>
                                                    </div>
                                                </td>
                                                <td class="align-middle white-space-nowrap "><?php echo $row["id"];?></td>
                                                <td>
                                                    <div class="d-flex align-items-center position-relative">
                                                        <div class="flex-1">
                                                            <h6 class="mb-1 fw-semi-bold text-nowrap"><a class="text-900 stretched-link"  href="writer?writerID=<?php echo $encodedId;?>"><?php echo $row["username"];?></a></h6>
                                                            <p class="fw-semi-bold mb-0 text-500">
                                                                <?php
                                                                if (isset($row["last_seen"]) && !empty($row["last_seen"])) {
                                                                    $lastSeen = new DateTime($row["last_seen"], new DateTimeZone('UTC'));
                                                                    $lastSeen->setTimezone(new DateTimeZone('Africa/Nairobi'));
                                                                    $now = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
                                                                    $diff = $now->diff($lastSeen);

                                                                    if ($diff->y > 0) {
                                                                        echo "Last seen " . $diff->y . "y ago";
                                                                    } elseif ($diff->m > 0) {
                                                                        echo "Last seen " . $diff->m . "mo ago";
                                                                    } elseif ($diff->days >= 7) {
                                                                        $weeks = floor($diff->days / 7);
                                                                        echo "Last seen " . $weeks . "w ago";
                                                                    } elseif ($diff->days > 0) {
                                                                        echo "Last seen " . $diff->days . "d ago";
                                                                    } elseif ($diff->h > 0) {
                                                                        echo "Last seen " . $diff->h . "h ago";
                                                                    } elseif ($diff->i > 0) {
                                                                        echo "Last seen " . $diff->i . "m ago";
                                                                    } else {
                                                                        echo "Online";
                                                                    }
                                                                } else {
                                                                    echo "Last seen: Unknown";
                                                                }
                                                                ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="align-middle white-space-nowrap text-900"><?php echo $row["email"];?></td>
                                                <td class="align-middle white-space-nowrap text-900">
                                                    <?php echo date("jS M, Y", strtotime($row['created_at'] . ' UTC')); ?>
                                                    <?php if ($isNewAndUnverified): ?>
                                                        <br><span class="badge badge-subtle-warning mt-1"><i class="fas fa-clock me-1"></i>New - Pending Activation</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="align-middle white-space-nowrap">
                                                    <?php
                                                    if ($isActive == 1) {
                                                        echo '<span class="badge badge rounded-pill badge-subtle-success">Active<span class="ms-1 fas fa-check-circle" data-fa-transform="shrink-2"></span></span>';
                                                    } else {
                                                        echo '<span class="badge badge rounded-pill badge-subtle-secondary">Deactivated<span class="ms-1 fas fa-power-off" data-fa-transform="shrink-2"></span></span>';
                                                        if (!empty($row['deactivation_reason'])) {
                                                            echo '<br><small class="text-muted" data-bs-toggle="tooltip" title="' . htmlspecialchars($row['deactivation_reason']) . '"><i class="fas fa-info-circle"></i> Hover for reason</small>';
                                                        }
                                                    }
                                                    ?>
                                                </td>
                                                <td class="align-middle white-space-nowrap email">
                                                    <?php
                                                    if ($row['is_verified'] == 1) {
                                                        echo '<span class="badge badge rounded-pill badge-subtle-success">Verified<span class="ms-1 fas fa-check" data-fa-transform="shrink-2"></span></span>';
                                                    } else {
                                                        echo '<span class="badge badge rounded-pill badge-subtle-danger">Unverified<span class="ms-1 fas fa-ban" data-fa-transform="shrink-2"></span></span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="align-middle white-space-nowrap">
                                                    <?php $deviceCount = $writerDeviceCounts[strtolower($row['email'])] ?? 0; ?>
                                                    <?php if ($deviceCount > 0) { ?>
                                                        <span class="badge rounded-pill badge-subtle-info" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo $deviceCount; ?> active device(s)">
                                                            <span class="fas fa-laptop me-1"></span><?php echo $deviceCount; ?>
                                                        </span>
                                                    <?php } else { ?>
                                                        <span class="badge rounded-pill badge-subtle-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="No active devices">
                                                            <span class="fas fa-laptop me-1"></span>0
                                                        </span>
                                                    <?php } ?>
                                                </td>
                                                <td class="align-middle white-space-nowrap text-end position-relative">
                                                    <div class="hover-actions bg-100">
                                                        <a class="btn btn-outline-info bg-info icon-item rounded-3 me-2 fs-11 icon-item-sm" href="writer?writerID=<?php echo $encodedId;?>" data-bs-toggle="tooltip" data-bs-placement="top" title="View Writer"><span class="fas fa-eye"></span></a>
                                                        <a class="btn btn-outline-primary bg-primary icon-item rounded-3 me-2 fs-11 icon-item-sm" data-bs-toggle="modal" href="#user-edit-modal" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Writer" data-writer-id="<?php echo $row['id']; ?>" data-writer="<?php echo $row['username']; ?>" data-email="<?php echo $row['email']; ?>" data-phone="<?php echo $row['phone']; ?>"><span class="far fa-edit"></span></a>
                                                        <button type="button" class="btn btn-outline-danger bg-<?php echo $row['is_verified'] ? 'danger' : 'success'; ?> icon-item rounded-3 me-2 fs-11 icon-item-sm verify-writer-btn"
                                                                data-writer-id="<?php echo $row['id']; ?>"
                                                                data-writer-username="<?php echo htmlspecialchars($row['username']); ?>"
                                                                data-writer-email="<?php echo htmlspecialchars($row['email']); ?>"
                                                                data-is-verified="<?php echo $row['is_verified']; ?>"
                                                                data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo $row['is_verified'] ? 'Unverify' : 'Verify'; ?> Writer">
                                                            <i class="bi bi-<?php echo $row['is_verified'] ? 'x-circle-fill' : 'check-circle-fill'; ?>"></i>
                                                        </button>
                                                        <?php if ($isActive == 1): ?>
                                                            <button type="button" class="btn btn-outline-warning bg-warning icon-item rounded-3 me-2 fs-11 icon-item-sm deactivate-writer-btn"
                                                                    data-writer-id="<?php echo $row['id']; ?>"
                                                                    data-writer-username="<?php echo htmlspecialchars($row['username']); ?>"
                                                                    data-writer-email="<?php echo htmlspecialchars($row['email']); ?>"
                                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Deactivate Account">
                                                                <span class="fas fa-power-off"></span>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-outline-success bg-success icon-item rounded-3 me-2 fs-11 icon-item-sm reactivate-writer-btn"
                                                                    data-writer-id="<?php echo $row['id']; ?>"
                                                                    data-writer-username="<?php echo htmlspecialchars($row['username']); ?>"
                                                                    data-writer-email="<?php echo htmlspecialchars($row['email']); ?>"
                                                                    data-deactivation-reason="<?php echo htmlspecialchars($row['deactivation_reason'] ?? ''); ?>"
                                                                    data-deactivated-at="<?php echo isset($row['deactivated_at']) ? : ''; ?>"
                                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Reactivate Account">
                                                                <i class="fas fa-undo"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($row['is_verified'] == 0) { ?>
                                                            <button type="button" class="btn btn-outline-danger bg-danger icon-item rounded-3 me-2 fs-11 icon-item-sm delete-writer-btn"
                                                                    data-writer-id="<?php echo $row['id']; ?>"
                                                                    data-writer-username="<?php echo htmlspecialchars($row['username']); ?>"
                                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Delete Writer">
                                                                <span class="fas fa-trash"></span>
                                                            </button>
                                                        <?php } ?>
                                                    </div>
                                                    <div class="dropdown font-sans-serif btn-reveal-trigger">
                                                        <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="user-view-edit" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-chevron-left fs-11"></span></button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php
                                            $cnt=$cnt+1;
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="delete-confirmation-modal" tabindex="-1" aria-labelledby="deleteConfirmationLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteConfirmationLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-warning me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone!
                    </div>
                    <p>Are you absolutely sure you want to delete this writer account?</p>
                    <p><strong>Writer:</strong> <span id="writer-to-delete"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="proceed-to-verification">Yes, Proceed to Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Username Verification Modal -->
    <div class="modal fade" id="username-verification-modal" tabindex="-1" aria-labelledby="usernameVerificationLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="usernameVerificationLabel">
                        <i class="fas fa-shield-alt me-2"></i>Verify Username to Delete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Final Step:</strong> Type the exact username to confirm deletion.
                    </div>
                    <p>To confirm deletion, click the Auto-fill button to populate the username: <strong><span id="username-to-verify"></span></strong></p>                    <div class="mb-3">
                        <label for="username-input" class="form-label">Enter Username:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="username-input" placeholder="Click copy button then paste here">
                            <button class="btn btn-outline-secondary" type="button" id="copy-username-btn" data-bs-toggle="tooltip" data-bs-placement="top" title="Auto-fill Username">
                                <i class="fas fa-magic"></i> Auto-fill
                            </button>
                        </div>
                        <div class="form-text text-danger d-none" id="username-error">Username does not match!</div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Username is case-sensitive and must match exactly.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-delete-btn" disabled>Delete Writer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Deactivation Modal -->
    <div class="modal fade" id="deactivation-modal" tabindex="-1" aria-labelledby="deactivationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="deactivationModalLabel">
                        <i class="fas fa-power-off me-2"></i>Deactivate Writer Account
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="usermanagement">
                    <div class="modal-body">
                        <input type="hidden" name="writer_id" id="deactivate-writer-id">
                        <input type="hidden" name="deactivate_writer" value="1">

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> Deactivating this account will also set verification status to unverified.
                        </div>

                        <div class="mb-3">
                            <p><strong>Writer:</strong> <span id="deactivate-writer-name"></span></p>
                            <p><strong>Email:</strong> <span id="deactivate-writer-email"></span></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="deactivation-reason-select">Select Reason for Deactivation <span class="text-danger">*</span></label>
                            <select class="form-select mb-2" id="deactivation-reason-select" onchange="handleReasonChange()">
                                <option value="">-- Select a reason --</option>
                                <option value="Violation of terms of service">Violation of terms of service</option>
                                <option value="Poor quality submissions">Poor quality submissions</option>
                                <option value="Missed deadlines repeatedly">Missed deadlines repeatedly</option>
                                <option value="Plagiarism detected">Plagiarism detected</option>
                                <option value="Inactive for extended period">Inactive for extended period</option>
                                <option value="Writer request">Writer request</option>
                                <option value="Fraudulent activity">Fraudulent activity</option>
                                <option value="Communication issues">Communication issues</option>
                                <option value="other">Other (specify below)</option>
                            </select>
                        </div>

                        <div class="mb-3" id="custom-reason-container" style="display: none;">
                            <label class="form-label" for="custom-reason">Specify Reason</label>
                            <textarea class="form-control" id="custom-reason" rows="3" placeholder="Enter the reason for deactivation..."></textarea>
                        </div>

                        <input type="hidden" name="deactivation_reason" id="final-deactivation-reason">

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> An email notification will be sent to the admin only. The writer will not receive a deactivation email.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning" id="confirm-deactivate-btn" disabled>
                            <i class="fas fa-power-off me-1"></i>Deactivate Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Verification Modal -->
    <div class="modal fade" id="verification-modal" tabindex="-1" aria-labelledby="verificationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" id="verification-modal-header">
                    <h5 class="modal-title" id="verificationModalLabel">
                        <i class="fas fa-user-check me-2"></i><span id="verification-modal-title">Verify Writer Account</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="verification-alert" class="alert">
                        <i id="verification-alert-icon" class="me-2"></i>
                        <span id="verification-alert-text"></span>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar avatar-xl me-3">
                                    <div class="avatar-name rounded-circle bg-soft-primary text-primary">
                                        <span class="fs-0" id="verify-writer-initial"></span>
                                    </div>
                                </div>
                                <div>
                                    <h5 class="mb-0" id="verify-writer-name"></h5>
                                    <p class="text-muted mb-0" id="verify-writer-email"></p>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-6">
                                    <p class="mb-1 text-muted">Writer ID</p>
                                    <p class="fw-bold" id="verify-writer-id"></p>
                                </div>
                                <div class="col-6">
                                    <p class="mb-1 text-muted">Current Status</p>
                                    <p id="verify-current-status"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-secondary">
                        <i class="fas fa-envelope me-2"></i>
                        <strong>Note:</strong> An email notification will be sent to the writer informing them of this status change.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn" id="confirm-verify-btn">
                        <i class="fas fa-check me-1"></i><span id="confirm-verify-text">Verify Writer</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Reactivation Modal -->
    <div class="modal fade" id="reactivation-modal" tabindex="-1" aria-labelledby="reactivationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="reactivationModalLabel">
                        <i class="fas fa-undo me-2"></i>Reactivate Writer Account
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Reactivation:</strong> This will restore the writer's account access. Verification status will remain unchanged.
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar avatar-xl me-3">
                                    <div class="avatar-name rounded-circle bg-soft-success text-success">
                                        <span class="fs-0" id="reactivate-writer-initial"></span>
                                    </div>
                                </div>
                                <div>
                                    <h5 class="mb-0" id="reactivate-writer-name"></h5>
                                    <p class="text-muted mb-0" id="reactivate-writer-email"></p>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-6">
                                    <p class="mb-1 text-muted">Writer ID</p>
                                    <p class="fw-bold" id="reactivate-writer-id"></p>
                                </div>
                                <div class="col-6">
                                    <p class="mb-1 text-muted">Deactivated On</p>
                                    <p class="fw-bold text-danger" id="reactivate-deactivated-at"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-warning mb-3" id="previous-reason-card">
                        <div class="card-header bg-warning-subtle">
                            <h6 class="mb-0"><i class="fas fa-history me-2"></i>Previous Deactivation Reason</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-0" id="reactivate-previous-reason"></p>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> After reactivation, you may need to verify the writer's account separately if required.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn btn-success" id="confirm-reactivate-btn">
                        <i class="fas fa-undo me-1"></i>Reactivate Account
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="user-edit-modal" tabindex="-1" role="dialog" aria-labelledby="user-edit-modal-label" aria-hidden="true">
        <div class="modal-dialog mt-6" role="document">
            <div class="modal-content border-0">
                <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1">
                        <h4 class="mb-0 text-white" id="user-edit-modal-label">View/Edit Writer</h4>
                    </div>
                    <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 px-5">
                    <div id="user-modal-alert" class="alert d-none"></div>
                    <form id="writer-form">
                        <input type="hidden" id="writer-id" name="writer-id">
                        <div class="mb-3">
                            <label class="form-label" for="modal-auth-writer">Username</label>
                            <input class="form-control" type="text" autocomplete="on" name="name" id="modal-auth-writer" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="modal-auth-email">Email</label>
                            <input class="form-control" type="text" autocomplete="on" name="email" id="modal-auth-email" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="modal-auth-phone">Phone</label>
                            <input class="form-control" type="text" autocomplete="on" name="phone" id="modal-auth-phone" />
                        </div>
                        <div class="mb-3">
                            <button class="btn btn-primary d-block w-100 mt-3" type="submit">Update Writer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle deactivation reason selection
        function handleReasonChange() {
            const select = document.getElementById('deactivation-reason-select');
            const customContainer = document.getElementById('custom-reason-container');
            const customReason = document.getElementById('custom-reason');
            const finalReason = document.getElementById('final-deactivation-reason');
            const submitBtn = document.getElementById('confirm-deactivate-btn');

            if (select.value === 'other') {
                customContainer.style.display = 'block';
                customReason.required = true;
                finalReason.value = '';
                submitBtn.disabled = true;
            } else if (select.value !== '') {
                customContainer.style.display = 'none';
                customReason.required = false;
                finalReason.value = select.value;
                submitBtn.disabled = false;
            } else {
                customContainer.style.display = 'none';
                customReason.required = false;
                finalReason.value = '';
                submitBtn.disabled = true;
            }
        }

        // Handle custom reason input
        document.getElementById('custom-reason').addEventListener('input', function() {
            const finalReason = document.getElementById('final-deactivation-reason');
            const submitBtn = document.getElementById('confirm-deactivate-btn');

            if (this.value.trim() !== '') {
                finalReason.value = this.value.trim();
                submitBtn.disabled = false;
            } else {
                finalReason.value = '';
                submitBtn.disabled = true;
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            let currentWriterId = null;
            let currentWriterUsername = null;

            // Handle verify button clicks
            const verifyButtons = document.querySelectorAll('.verify-writer-btn');
            verifyButtons.forEach((button) => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const writerId = this.getAttribute('data-writer-id');
                    const writerUsername = this.getAttribute('data-writer-username');
                    const writerEmail = this.getAttribute('data-writer-email');
                    const isVerified = this.getAttribute('data-is-verified') === '1';

                    // Set modal content
                    document.getElementById('verify-writer-id').textContent = writerId;
                    document.getElementById('verify-writer-name').textContent = writerUsername;
                    document.getElementById('verify-writer-email').textContent = writerEmail;
                    document.getElementById('verify-writer-initial').textContent = writerUsername.charAt(0).toUpperCase();

                    // Set current status badge
                    const statusHtml = isVerified
                        ? '<span class="badge badge-subtle-success">Verified <i class="fas fa-check"></i></span>'
                        : '<span class="badge badge-subtle-danger">Unverified <i class="fas fa-ban"></i></span>';
                    document.getElementById('verify-current-status').innerHTML = statusHtml;

                    // Update modal header and content based on action
                    const modalHeader = document.getElementById('verification-modal-header');
                    const modalTitle = document.getElementById('verification-modal-title');
                    const alertDiv = document.getElementById('verification-alert');
                    const alertIcon = document.getElementById('verification-alert-icon');
                    const alertText = document.getElementById('verification-alert-text');
                    const confirmBtn = document.getElementById('confirm-verify-btn');
                    const confirmText = document.getElementById('confirm-verify-text');

                    if (isVerified) {
                        // Unverify action
                        modalHeader.className = 'modal-header bg-danger text-white';
                        modalTitle.textContent = 'Unverify Writer Account';
                        alertDiv.className = 'alert alert-danger';
                        alertIcon.className = 'fas fa-exclamation-triangle me-2';
                        alertText.textContent = 'You are about to revoke this writer\'s verification status. They will no longer have access to verified writer features.';
                        confirmBtn.className = 'btn btn-danger';
                        confirmBtn.href = 'usermanagement?verifyid=' + writerId;
                        confirmText.textContent = 'Unverify Writer';
                    } else {
                        // Verify action
                        modalHeader.className = 'modal-header bg-success text-white';
                        modalTitle.textContent = 'Verify Writer Account';
                        alertDiv.className = 'alert alert-success';
                        alertIcon.className = 'fas fa-check-circle me-2';
                        alertText.textContent = 'You are about to verify this writer\'s account. They will gain access to all platform features and can start receiving tasks.';
                        confirmBtn.className = 'btn btn-success';
                        confirmBtn.href = 'usermanagement?verifyid=' + writerId;
                        confirmText.textContent = 'Verify Writer';
                    }

                    const verificationModal = new bootstrap.Modal(document.getElementById('verification-modal'));
                    verificationModal.show();
                });
            });

            // Handle reactivate button clicks
            const reactivateButtons = document.querySelectorAll('.reactivate-writer-btn');
            reactivateButtons.forEach((button) => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const writerId = this.getAttribute('data-writer-id');
                    const writerUsername = this.getAttribute('data-writer-username');
                    const writerEmail = this.getAttribute('data-writer-email');
                    const deactivationReason = this.getAttribute('data-deactivation-reason');
                    const deactivatedAt = this.getAttribute('data-deactivated-at');

                    // Set modal content
                    document.getElementById('reactivate-writer-id').textContent = writerId;
                    document.getElementById('reactivate-writer-name').textContent = writerUsername;
                    document.getElementById('reactivate-writer-email').textContent = writerEmail;
                    document.getElementById('reactivate-writer-initial').textContent = writerUsername.charAt(0).toUpperCase();
                    document.getElementById('reactivate-deactivated-at').textContent = deactivatedAt || 'Unknown';

                    // Show/hide previous reason card
                    const reasonCard = document.getElementById('previous-reason-card');
                    if (deactivationReason && deactivationReason.trim() !== '') {
                        reasonCard.style.display = 'block';
                        document.getElementById('reactivate-previous-reason').textContent = deactivationReason;
                    } else {
                        reasonCard.style.display = 'none';
                    }

                    // Set confirm button link
                    document.getElementById('confirm-reactivate-btn').href = 'usermanagement?reactivateid=' + writerId;

                    const reactivationModal = new bootstrap.Modal(document.getElementById('reactivation-modal'));
                    reactivationModal.show();
                });
            });

            // Handle deactivate button clicks
            const deactivateButtons = document.querySelectorAll('.deactivate-writer-btn');
            deactivateButtons.forEach((button) => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const writerId = this.getAttribute('data-writer-id');
                    const writerUsername = this.getAttribute('data-writer-username');
                    const writerEmail = this.getAttribute('data-writer-email');

                    document.getElementById('deactivate-writer-id').value = writerId;
                    document.getElementById('deactivate-writer-name').textContent = writerUsername;
                    document.getElementById('deactivate-writer-email').textContent = writerEmail;

                    // Reset form
                    document.getElementById('deactivation-reason-select').value = '';
                    document.getElementById('custom-reason').value = '';
                    document.getElementById('custom-reason-container').style.display = 'none';
                    document.getElementById('final-deactivation-reason').value = '';
                    document.getElementById('confirm-deactivate-btn').disabled = true;

                    const deactivationModal = new bootstrap.Modal(document.getElementById('deactivation-modal'));
                    deactivationModal.show();
                });
            });

            // Handle delete button clicks
            const deleteButtons = document.querySelectorAll('.delete-writer-btn');
            deleteButtons.forEach((button, index) => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    currentWriterId = this.getAttribute('data-writer-id');
                    currentWriterUsername = this.getAttribute('data-writer-username');

                    // Show writer info in confirmation modal
                    const writerToDeleteElement = document.getElementById('writer-to-delete');
                    if (writerToDeleteElement) {
                        writerToDeleteElement.textContent = currentWriterUsername;
                    }

                    // Show first confirmation modal
                    const modalElement = document.getElementById('delete-confirmation-modal');
                    if (modalElement) {
                        const confirmModal = new bootstrap.Modal(modalElement);
                        confirmModal.show();
                    }
                });
            });

            // Handle copy username button
            const copyUsernameBtn = document.getElementById('copy-username-btn');
            if (copyUsernameBtn) {
                copyUsernameBtn.addEventListener('click', function() {
                    const usernameInput = document.getElementById('username-input');

                    if (currentWriterUsername && usernameInput) {
                        // Directly paste into input field
                        usernameInput.value = currentWriterUsername;
                        usernameInput.focus();

                        // Trigger input event to validate
                        usernameInput.dispatchEvent(new Event('input'));

                        // Visual feedback
                        const originalText = copyUsernameBtn.innerHTML;
                        copyUsernameBtn.innerHTML = '<i class="fas fa-check"></i> Done!';
                        copyUsernameBtn.classList.remove('btn-outline-secondary');
                        copyUsernameBtn.classList.add('btn-success');

                        setTimeout(function() {
                            copyUsernameBtn.innerHTML = originalText;
                            copyUsernameBtn.classList.remove('btn-success');
                            copyUsernameBtn.classList.add('btn-outline-secondary');
                        }, 1500);
                    }
                });
            }

            // Handle proceed to verification
            const proceedBtn = document.getElementById('proceed-to-verification');
            if (proceedBtn) {
                proceedBtn.addEventListener('click', function() {
                    // Hide first modal
                    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('delete-confirmation-modal'));
                    if (confirmModal) {
                        confirmModal.hide();
                    }

                    // Show username verification modal
                    const usernameToVerifyElement = document.getElementById('username-to-verify');
                    if (usernameToVerifyElement) {
                        usernameToVerifyElement.textContent = currentWriterUsername;
                    }

                    document.getElementById('username-input').value = '';
                    document.getElementById('username-error').classList.add('d-none');
                    document.getElementById('confirm-delete-btn').disabled = true;

                    const verificationModal = new bootstrap.Modal(document.getElementById('username-verification-modal'));
                    verificationModal.show();
                });
            }

            // Handle username input validation
            const usernameInput = document.getElementById('username-input');
            if (usernameInput) {
                usernameInput.addEventListener('input', function() {
                    const enteredUsername = this.value;
                    const errorDiv = document.getElementById('username-error');
                    const confirmBtn = document.getElementById('confirm-delete-btn');

                    if (enteredUsername === currentWriterUsername) {
                        errorDiv.classList.add('d-none');
                        confirmBtn.disabled = false;
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        if (enteredUsername.length > 0) {
                            errorDiv.classList.remove('d-none');
                            this.classList.remove('is-valid');
                            this.classList.add('is-invalid');
                        } else {
                            errorDiv.classList.add('d-none');
                            this.classList.remove('is-invalid', 'is-valid');
                        }
                        confirmBtn.disabled = true;
                    }
                });
            }

            // Handle final deletion
            const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', function() {
                    // Hide the verification modal
                    const verificationModal = bootstrap.Modal.getInstance(document.getElementById('username-verification-modal'));
                    if (verificationModal) {
                        verificationModal.hide();
                    }

                    // Redirect to delete URL
                    window.location.href = `usermanagement?delid=${currentWriterId}`;
                });
            }

            // Reset modals when closed
            const deleteConfirmModal = document.getElementById('delete-confirmation-modal');
            if (deleteConfirmModal) {
                deleteConfirmModal.addEventListener('hidden.bs.modal', function() {
                    // Keep variables for verification modal
                });
            }

            const usernameVerificationModal = document.getElementById('username-verification-modal');
            if (usernameVerificationModal) {
                usernameVerificationModal.addEventListener('hidden.bs.modal', function() {
                    document.getElementById('username-input').value = '';
                    document.getElementById('username-error').classList.add('d-none');
                    document.getElementById('username-input').classList.remove('is-valid', 'is-invalid');
                    document.getElementById('confirm-delete-btn').disabled = true;

                    // Reset variables only when verification modal closes
                    currentWriterId = null;
                    currentWriterUsername = null;
                });
            }

            // Existing edit modal functionality
            document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
                button.addEventListener('click', function () {
                    const id = this.getAttribute('data-writer-id');
                    const name = this.getAttribute('data-writer');
                    const email = this.getAttribute('data-email');
                    const phone = this.getAttribute('data-phone');

                    // Now set the data in the modal fields
                    document.getElementById('writer-id').value = id;
                    document.getElementById('modal-auth-writer').value = name;
                    document.getElementById('modal-auth-email').value = email;
                    document.getElementById('modal-auth-phone').value = phone;

                    // Clear any previous alert message
                    let alertDiv = document.getElementById('user-modal-alert');
                    alertDiv.classList.add('d-none');
                    alertDiv.classList.remove('alert-success', 'alert-danger');
                    alertDiv.textContent = '';
                });
            });

            document.getElementById('writer-form').addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);

                fetch('update-writer', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        let alertDiv = document.getElementById('user-modal-alert');
                        if (data.success) {
                            alertDiv.classList.remove('d-none', 'alert-danger');
                            alertDiv.classList.add('alert-success');
                            alertDiv.textContent = data.message;
                            // Refresh the table data
                            $('#table-simple-pagination-body').load(' #table-simple-pagination-body > *');
                            // Hide the modal after 5 seconds
                            setTimeout(function() {
                                $('#user-edit-modal').modal('hide');
                            }, 5000);
                        } else {
                            alertDiv.classList.remove('d-none', 'alert-success');
                            alertDiv.classList.add('alert-danger');
                            alertDiv.textContent = data.message;
                        }
                    })
                    .catch(error => {
                        let alertDiv = document.getElementById('user-modal-alert');
                        alertDiv.classList.remove('d-none', 'alert-success');
                        alertDiv.classList.add('alert-danger');
                        alertDiv.textContent = 'An error occurred: ' + error.message;
                    });
            });
        });

        function exportWriter() {
            var exportWriter = confirm("Do you want to download the exported CSV file?");
            if (exportWriter) {
                window.location.href = 'export_writers';
            }
        }
    </script>
<?php
include "footer.php";
?>