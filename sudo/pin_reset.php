<?php
require_once __DIR__ . '/../env.php';
// ── Capture raw body FIRST — before ob_start() in check-login.php runs ─────
$_rawInput = file_get_contents('php://input');

include 'check-login.php';
check_login();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

$body   = json_decode($_rawInput, true);
$action = $body['action'] ?? '';

define('APP_BASE_URL',      'https://web.monkbrian.com');
define('TOKEN_TTL_MINUTES', 30);

// ═══════════════════════════════════════════════════════════════════════════
// ACTION 1 — request_reset
// ═══════════════════════════════════════════════════════════════════════════
if ($action === 'request_reset') {

    $adminEmail = $_SESSION['odmsaid'] ?? '';
    if (empty($adminEmail)) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }

    $stmt = $dbh->prepare("SELECT FirstName, email FROM tbladmin WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $adminEmail);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'Admin account not found.']);
        exit;
    }

    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);
    $expires   = date('Y-m-d H:i:s', strtotime('+' . TOKEN_TTL_MINUTES . ' minutes'));

    $upd = $dbh->prepare(
        "UPDATE tbladmin SET pin_reset_token = :token, pin_reset_expires = :expires WHERE email = :email"
    );
    $upd->bindParam(':token',   $tokenHash);
    $upd->bindParam(':expires', $expires);
    $upd->bindParam(':email',   $adminEmail);

    if (!$upd->execute()) {
        echo json_encode(['success' => false, 'message' => 'Could not save reset token. Please try again.']);
        exit;
    }

    $resetUrl    = APP_BASE_URL . '/sudo/pin_reset_form'
        . '?token=' . urlencode($rawToken)
        . '&ref='   . urlencode(base64_encode($adminEmail));
    $firstName   = htmlspecialchars($admin->FirstName);
    $companyLogo = 'https://web.monkbrian.com/assets/img/team/itasker-email-header.png';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = env('SMTP_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('SMTP_USER');
        $mail->Password   = env('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) env('SMTP_PORT', 587);
        $mail->setFrom(env('MAIL_FROM_ADDRESS'), 'iTasker');
        $mail->addAddress($admin->email, $firstName);
        $mail->isHTML(true);
        $mail->addCustomHeader('X-Priority',       '3');
        $mail->addCustomHeader('X-Mailer',         'iTasker v1.0');
        $mail->addCustomHeader('List-Unsubscribe',  '<mailto:support@monkbrian.com>');
        $mail->Subject = 'iTasker - Financial Dashboard PIN Reset';

        $mail->Body = "<!DOCTYPE html><html><head><style>
        body{font-family:Arial,sans-serif;background:#f4f4f4;padding:20px}
        .ec{max-width:600px;background:#fff;margin:0 auto;padding:20px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.1)}
        .eh{text-align:center;border-bottom:2px solid #0073e6;padding-bottom:15px}
        .eh img{max-width:100%;height:auto;max-height:100px}
        .eb{padding:20px}.eb h2{color:#0073e6;text-align:center}
        .eb p{font-size:16px;line-height:1.5;color:#333}
        .hi{font-weight:bold;color:#0073e6}
        .btn{display:block;text-align:center;background:#0073e6;color:#fff;padding:12px;border-radius:5px;text-decoration:none;font-size:16px;font-weight:bold;margin-top:20px}
        .warn{background:#fff3cd;border:1px solid #ffc107;border-radius:5px;padding:10px 15px;font-size:14px;color:#856404;margin-top:15px}
        .ft{text-align:center;padding-top:15px;font-size:12px;color:#777}
        </style></head><body>
        <div class='ec'>
          <div class='eh'><img src='{$companyLogo}' alt='iTasker'></div>
          <div class='eb'>
            <h2>&#128272; PIN Reset Request</h2>
            <p>Hello <span class='hi'>{$firstName}</span>,</p>
            <p>We received a request to reset your <strong>Financial Dashboard PIN</strong>. Click the button below to set a new PIN.</p>
            <a class='btn' href='{$resetUrl}'>Reset My Dashboard PIN</a>
            <div class='warn'>&#9888;&#65039; This link expires in <strong>" . TOKEN_TTL_MINUTES . " minutes</strong>. If you did not request this, ignore this email.</div>
            <p style='font-size:13px;color:#888;margin-top:15px;'>Or copy this URL:<br>
              <a href='{$resetUrl}' style='color:#0073e6;word-break:break-all;'>{$resetUrl}</a></p>
          </div>
          <div class='ft'>
            <p>For help, contact <a href='mailto:support@monkbrian.com'>support@monkbrian.com</a></p>
            <p>&copy; " . date('Y') . " iTasker. All rights reserved.</p>
          </div>
        </div></body></html>";

        $mail->AltBody = "Hello {$firstName},\nReset your Financial Dashboard PIN (expires in " . TOKEN_TTL_MINUTES . " minutes):\n{$resetUrl}\n\nIf you did not request this, ignore this email.\n\niTasker Support";

        $mail->send();
        echo json_encode(['success' => true, 'message' => 'A PIN reset link has been sent to <strong>' . htmlspecialchars($admin->email) . '</strong>. Check your inbox (and spam folder).']);

    } catch (Exception $e) {
        error_log('PIN Reset Email Error: ' . $mail->ErrorInfo);
        echo json_encode(['success' => false, 'message' => 'Email could not be sent. Please try again later.']);
    }
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// ACTION 2 — reset_pin
//   Writes to tbl_dashboard_pin.pin_hash  (same table as pin_api.php)
// ═══════════════════════════════════════════════════════════════════════════
if ($action === 'reset_pin') {

    $rawToken   = $body['token']   ?? '';
    $ref        = $body['ref']     ?? '';
    $newPin     = $body['pin']     ?? '';
    $confirm    = $body['confirm'] ?? '';
    $adminEmail = base64_decode($ref);

    if (empty($adminEmail) || empty($rawToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid reset link.']);
        exit;
    }
    if (!preg_match('/^\d{4,8}$/', $newPin)) {
        echo json_encode(['success' => false, 'message' => 'PIN must be 4–8 numeric digits.']);
        exit;
    }
    if ($newPin !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'PINs do not match.']);
        exit;
    }

    $stmt = $dbh->prepare("SELECT pin_reset_token, pin_reset_expires FROM tbladmin WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $adminEmail);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$row || empty($row->pin_reset_token)) {
        echo json_encode(['success' => false, 'message' => 'No pending reset request found.']);
        exit;
    }
    if (strtotime($row->pin_reset_expires) < time()) {
        echo json_encode(['success' => false, 'message' => 'This reset link has expired. Please request a new one.']);
        exit;
    }
    if (!password_verify($rawToken, $row->pin_reset_token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid or already-used reset link.']);
        exit;
    }

    // Save into tbl_dashboard_pin — mirrors upsertPin() in pin_api.php
    $hashedPin = password_hash($newPin, PASSWORD_DEFAULT);

    $upsert = $dbh->prepare("
        INSERT INTO tbl_dashboard_pin (admin_id, pin_hash)
        VALUES (:aid, :hash)
        ON DUPLICATE KEY UPDATE pin_hash = :hash2, updated_at = NOW()
    ");
    $upsert->bindParam(':aid',   $adminEmail);
    $upsert->bindParam(':hash',  $hashedPin);
    $upsert->bindParam(':hash2', $hashedPin);

    // Invalidate token so link cannot be reused
    $clearToken = $dbh->prepare(
        "UPDATE tbladmin SET pin_reset_token = NULL, pin_reset_expires = NULL WHERE email = :email"
    );
    $clearToken->bindParam(':email', $adminEmail);

    if ($upsert->execute() && $clearToken->execute()) {
        echo json_encode(['success' => true, 'message' => 'PIN reset successfully! You can now log in with your new PIN.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save new PIN. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);