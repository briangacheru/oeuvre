<?php
require_once __DIR__ . '/env.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

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
