<?php
/**
 * Shared logic between login.php and verify-login-code.php - the writer
 * login can complete either immediately (fresh login) or after an emailed
 * code is verified (returning after a fully-expired session). No top-level
 * executable code here, so it's safe to require from either entry point.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/email-template.php';

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

            $mail->setFrom(env('MAIL_FROM_ADDRESS'), 'iTasker');
            $mail->addAddress($toEmail);

            $mail->isHTML(true);
            $mail->Subject = "Your iTasker verification code";
            // Worded to cover both cases this fires for - a first-ever login
            // from a new device and a returning login after a long break -
            // rather than assuming it's always the latter.
            $mail->Body = render_email_html(
                'Verify it\'s you',
                '<p>For your security, please confirm it\'s really you signing in to your iTasker account. Enter this code to continue:</p>'
                . '<p style="font-size:32px;font-weight:700;letter-spacing:6px;text-align:center;background:#f5f7fa;padding:16px;border-radius:6px;color:#18163a;">' . htmlspecialchars($code) . '</p>'
                . '<p style="font-size:13px;color:#888;">This code expires in 10 minutes. If this wasn\'t you, change your password immediately.</p>'
            );
            $mail->AltBody = "Your iTasker verification code is: $code (expires in 10 minutes)";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Login OTP email failed to send to {$toEmail}: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('finalize_writer_login')) {
    // Establishes the writer session, sets the remember-me cookie if
    // requested, and returns the URL to redirect to. Shared by the
    // immediate-login path in login.php and the post-OTP path in
    // verify-login-code.php.
    function finalize_writer_login($con, $email, $remember, $taskIdParam = null) {
        $_SESSION['sessionWriter'] = $email;
        require_once __DIR__ . '/session_tracker.php';
        record_writer_session($con, $email);
        log_activity($con, 'writer', $email, 'login');

        if ($remember) {
            // Stored raw (not password_hash()'d) - check-login.php looks this
            // up with a direct `WHERE remember_token = ?` equality match,
            // which a randomly-salted bcrypt hash could never satisfy. The
            // token itself is 128 bits of randomness, unguessable either way.
            $rememberToken = bin2hex(random_bytes(16));
            $updateTokenSql = "UPDATE tblwriters SET remember_token = ? WHERE email = ?";
            $stmt = $con->prepare($updateTokenSql);
            $stmt->bind_param('ss', $rememberToken, $email);
            $stmt->execute();

            setcookie('rememberme', $rememberToken, time() + 1209600, '/', '', true, true); // 2 weeks
        }

        updateUserStatus($email, 'writer', true);

        // If arriving from a shared task link, send the writer straight to
        // that task if they have access to it, or flag it if they don't.
        $taskRedirectUrl = resolve_shared_task_redirect($con, $email, $taskIdParam);

        $redirectUrl = 'index.php'; // Default redirect

        if ($taskRedirectUrl !== null) {
            $redirectUrl = $taskRedirectUrl;
        } elseif (isset($_COOKIE['last_page_before_timeout'])) {
            $redirectUrl = $_COOKIE['last_page_before_timeout'];
            setcookie('last_page_before_timeout', '', time() - 420, '/');
        } elseif (isset($_COOKIE['last_page_before_logout'])) {
            $redirectUrl = $_COOKIE['last_page_before_logout'];
            setcookie('last_page_before_logout', '', time() - 420, '/');
        }

        // Ensure redirect URL is safe - only check for external URLs
        if (strpos($redirectUrl, 'http://') === 0 || strpos($redirectUrl, 'https://') === 0) {
            $parsedUrl = parse_url($redirectUrl);
            $currentDomain = $_SERVER['HTTP_HOST'];
            if ($parsedUrl['host'] !== $currentDomain) {
                $redirectUrl = 'index.php';
            }
        }

        // Remove any login.php references to avoid loops
        if (strpos($redirectUrl, 'login.php') !== false) {
            $redirectUrl = 'index.php';
        }

        return $redirectUrl;
    }
}
