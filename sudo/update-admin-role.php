<?php
include "check-login.php";
csrf_verify_or_json_die();
requireCapability($currentAdminRole, 'manage_admins', 'json');
require_once __DIR__ . '/../email-template.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

$targetId = isset($_POST['admin_id']) ? (int) $_POST['admin_id'] : 0;
$newRole = $_POST['role'] ?? '';

if ($targetId <= 0 || !in_array($newRole, ADMIN_ROLES, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

$stmt = mysqli_prepare($con, "SELECT email, username, role FROM tbladmin WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $targetId);
mysqli_stmt_execute($stmt);
$targetRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$targetRow) {
    echo json_encode(['success' => false, 'message' => 'Admin not found.']);
    exit();
}

// Prevent self-demotion/self-lockout via this endpoint - a superadmin who
// accidentally drops their own role would have no way back in without a
// raw DB edit, exactly the problem this whole feature exists to remove.
if ($targetRow['email'] === $_SESSION['odmsaid']) {
    echo json_encode(['success' => false, 'message' => "You can't change your own role - ask another superadmin."]);
    exit();
}

$oldRole = $targetRow['role'] ?: 'pending';

$stmt2 = mysqli_prepare($con, "UPDATE tbladmin SET role = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt2, 'si', $newRole, $targetId);

if (mysqli_stmt_execute($stmt2)) {
    // First-time approval (pending -> anything else) gets an email - this
    // is the account's only signal that they can now log in at all, since
    // sudo/login.php rejects 'pending' accounts before a session is ever
    // established.
    if ($oldRole === 'pending' && $newRole !== 'pending') {
        send_admin_approved_email($targetRow['email'], $targetRow['username'], $newRole);
    }
    echo json_encode(['success' => true, 'message' => 'Role updated to ' . ucfirst($newRole) . '.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update role: ' . safe_db_error(mysqli_error($con))]);
}
mysqli_stmt_close($stmt2);

function send_admin_approved_email($toEmail, $username, $role) {
    $loginUrl = rtrim(env('APP_URL'), '/') . '/sudo/login';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USER');
        $mail->Password = env('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) env('SMTP_PORT', 587);

        $mail->setFrom(env('MAIL_FROM_ADDRESS'), 'iTasker Admin');
        $mail->addAddress($toEmail, $username);

        $mail->isHTML(true);
        $mail->Subject = "You're approved - iTasker Admin";
        $mail->Body = render_email_html(
            'Account Approved',
            '<p>Hi ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Good news - your iTasker admin account has been approved with the <strong>' . htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8') . '</strong> role. You can now log in.</p>',
            'Log In Now',
            $loginUrl
        );
        $mail->AltBody = "Your iTasker admin account has been approved with the " . ucfirst($role) . " role. Log in: $loginUrl";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Admin-approved email failed to send to {$toEmail}: " . $mail->ErrorInfo);
        return false;
    }
}
