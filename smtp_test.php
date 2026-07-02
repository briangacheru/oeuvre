<?php
require_once __DIR__ . '/env.php';
// Diagnostic endpoint: require an authenticated session so it can't be
// triggered anonymously to send mail or probe SMTP.
session_start();
if (empty($_SESSION['sessionWriter']) && empty($_SESSION['odmsaid'])) {
    http_response_code(403);
    exit('Forbidden');
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = env('SMTP_HOST');
    $mail->SMTPAuth   = true;
    $mail->Username   = env('SMTP_USER');
    $mail->Password   = env('SMTP_PASS');
    $mail->SMTPSecure = 'tls';
    $mail->Port       = (int) env('SMTP_PORT', 587);

    $mail->setFrom(env('MAIL_FROM_ADDRESS'), 'Test');
    $mail->addAddress(env('ADMIN_EMAIL')); // your email to receive test

    $mail->Subject = 'SMTP Test';
    $mail->Body    = 'This is a test email to check SMTP settings.';

    $mail->send();
    echo 'Test email sent successfully';
} catch (Exception $e) {
    echo "Test email failed: {$mail->ErrorInfo}";
}
?>
