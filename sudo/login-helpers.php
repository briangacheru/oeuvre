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
            $mail->Body = '<div style="font-family: Arial, sans-serif; max-width: 480px; margin: 0 auto;">'
                . '<p style="font-size: 15px; color: #333;">Someone signed in to your iTasker admin account after it had been inactive for a while. Enter this code to continue:</p>'
                . '<p style="font-size: 32px; font-weight: 700; letter-spacing: 6px; text-align: center; background: #f5f7fa; padding: 16px; border-radius: 6px; color: #18163a;">' . htmlspecialchars($code) . '</p>'
                . '<p style="font-size: 13px; color: #888;">This code expires in 10 minutes. If this wasn\'t you, change your password immediately.</p>'
                . '</div>';
            $mail->AltBody = "Your iTasker admin verification code is: $code (expires in 10 minutes)";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Login OTP email failed to send to {$toEmail}: " . $mail->ErrorInfo);
            return false;
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
