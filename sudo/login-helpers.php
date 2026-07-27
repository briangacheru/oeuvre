<?php
/**
 * Shared logic between login.php and verify-login-code.php - the admin
 * login can complete either immediately (fresh login) or after an emailed
 * code is verified (returning after a fully-expired session). No top-level
 * executable code here, so it's safe to require from either entry point.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../email-template.php';

if (!function_exists('send_login_otp_code_email')) {
    function send_login_otp_code_email($toEmail, $code) {
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
            $mail->addAddress($toEmail);

            $mail->isHTML(true);
            $mail->Subject = "Your iTasker admin verification code";
            // Worded to cover both cases this fires for - a first-ever login
            // from a new device and a returning login after a long break -
            // rather than assuming it's always the latter.
            $mail->Body = render_email_html(
                'Verify it\'s you',
                '<p>For your security, please confirm it\'s really you signing in to your iTasker admin account. Enter this code to continue:</p>'
                . '<p style="font-size:32px;font-weight:700;letter-spacing:6px;text-align:center;background:#f5f7fa;padding:16px;border-radius:6px;color:#18163a;">' . htmlspecialchars($code) . '</p>'
                . '<p style="font-size:13px;color:#888;">This code expires in 10 minutes. If this wasn\'t you, change your password immediately.</p>'
            );
            $mail->AltBody = "Your iTasker admin verification code is: $code (expires in 10 minutes)";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Login OTP email failed to send to {$toEmail}: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('send_admin_welcome_emails')) {
    // The "thanks for signing up" email to the new admin plus a
    // notification to the site admin, both pending-approval worded. Shared
    // by register.php (password signup) and google-callback.php (Google
    // signup) - same two emails either way. Best-effort: the account row
    // already exists by the time this runs, so a send failure is logged,
    // never thrown/fatal.
    function send_admin_welcome_emails($email, $username) {
        $loginUrl = rtrim(env('APP_URL'), '/') . '/sudo/login';
        $manageAdminsUrl = rtrim(env('APP_URL'), '/') . '/sudo/manage-admins';

        $user_mail = new PHPMailer(true);
        $user_mail->isSMTP();
        $user_mail->Host = env('SMTP_HOST');
        $user_mail->SMTPAuth = true;
        $user_mail->Username = env('SMTP_USER');
        $user_mail->Password = env('SMTP_PASS');
        $user_mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $user_mail->Port = (int) env('SMTP_PORT', 587);

        $user_mail->setFrom(env('MAIL_FROM_ADDRESS'), 'iTasker Admin');
        $user_mail->addAddress($email, $username);

        $user_mail->isHTML(true);
        $user_mail->Subject = 'Thank you for Signing Up - iTasker Admin';
        $user_mail->Body = render_email_html(
            'Welcome to iTasker',
            '<p>Hi ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Thank you for signing up for admin access to iTasker. Your account is currently <strong>pending approval</strong> from a superadmin - we\'ll email you as soon as you\'re approved and ready to log in.</p>',
            'Go to Login',
            $loginUrl
        );
        $user_mail->AltBody = "Thank you for signing up for iTasker admin access. Your account is pending approval - we'll email you once it's ready. Login: $loginUrl";

        $admin_mail = new PHPMailer(true);
        $admin_mail->isSMTP();
        $admin_mail->Host = env('SMTP_HOST');
        $admin_mail->SMTPAuth = true;
        $admin_mail->Username = env('SMTP_USER');
        $admin_mail->Password = env('SMTP_PASS');
        $admin_mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $admin_mail->Port = (int) env('SMTP_PORT', 587);

        $admin_mail->setFrom(env('MAIL_FROM_ADDRESS'), 'iTasker');
        $admin_mail->addAddress(env('ADMIN_EMAIL'), 'iTasker Admin');

        $admin_mail->isHTML(true);
        $admin_mail->Subject = 'New Admin Registration [iTasker]';
        $admin_mail->Body = render_email_html(
            'New Admin Registration',
            '<p>A new admin account has registered and is awaiting approval:</p>'
            . '<table role="presentation" style="width:100%;font-size:14px;border-collapse:collapse;margin-top:8px;">'
            . '<tr><td style="padding:4px 0;color:#888;width:90px;">Username</td><td style="padding:4px 0;font-weight:600;">' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="padding:4px 0;color:#888;">Email</td><td style="padding:4px 0;font-weight:600;">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="padding:4px 0;color:#888;">Role</td><td style="padding:4px 0;font-weight:600;">Pending Approval</td></tr>'
            . '</table>',
            'Review & Approve',
            $manageAdminsUrl
        );
        $admin_mail->AltBody = "A new admin with email $email has registered (role: Pending Approval). Review at: $manageAdminsUrl";

        $mailErrors = [];
        try {
            $user_mail->send();
        } catch (Exception $e) {
            $mailErrors[] = 'user: ' . $e->getMessage();
        }
        try {
            $admin_mail->send();
        } catch (Exception $e) {
            $mailErrors[] = 'admin: ' . $e->getMessage();
        }
        if ($mailErrors) {
            error_log('send_admin_welcome_emails: registration email send failed for ' . $email . ': ' . implode('; ', $mailErrors));
        }
    }
}

if (!function_exists('finalize_admin_login')) {
    // Establishes the admin session, sets the remember-me cookie if
    // requested, and returns the URL to redirect to. Shared by the
    // immediate-login path in login.php and the post-OTP path in
    // verify-login-code.php.
    function finalize_admin_login($con, $dbh, $email, $remember, $redirectParam = '') {
        $_SESSION['odmsaid'] = $email;
        require_once __DIR__ . '/session_tracker.php';
        record_login_session($dbh, $email);
        enforce_device_limit($dbh, $email, session_id());

        if ($remember) {
            // Stored raw (not password_hash()'d) - check-login.php looks this
            // up with a direct `WHERE remember_token = ?` equality match,
            // which a randomly-salted bcrypt hash could never satisfy. The
            // token itself is 128 bits of randomness, unguessable either way.
            $rememberToken = bin2hex(random_bytes(16));
            $updateTokenSql = "UPDATE tbladmin SET remember_token = ? WHERE email = ?";
            $stmt = $con->prepare($updateTokenSql);
            $stmt->bind_param('ss', $rememberToken, $email);
            $stmt->execute();

            setcookie('rememberme', $rememberToken, time() + 1209600, '/', '', true, true); // 2 weeks
        }

        updateUserStatus($email, 'admin', true);

        $redirectUrl = 'index';
        if (!empty($redirectParam)) {
            $redirect = trim($redirectParam);

            // Security check: ensure it's a safe, same-site relative URL
            if (strpos($redirect, '/') === 0 && strpos($redirect, '//') === false &&
                !preg_match('/[<>"\'\s]|javascript:|data:|vbscript:/i', $redirect) &&
                preg_match('/^[a-zA-Z0-9\-_\/\?&=\.]+$/', $redirect)) {
                $redirectUrl = $redirect;
            }
        }

        return $redirectUrl;
    }
}
