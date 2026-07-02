<?php
require_once __DIR__ . '/env.php';
include "check-login.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

function sendEmail($writer, $pages, $cpp, $due_date, $writerEmail, $taskId, $action, $topic, $account) {
    $encodedId = base64_encode((string)$taskId);
    $total_price = $pages * $cpp;
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
        $mail->addAddress($writerEmail);
        $mail->addBCC(env('ADMIN_EMAIL'), 'iTasker Admin');
        $mail->addCustomHeader('X-Priority', '3');
        $mail->addCustomHeader('X-Mailer', 'iTasker v1.0');
        $mail->addCustomHeader('List-Unsubscribe', '<mailto:support@monkbrian.com>');

        // Content
        $status = $action == 'accept' ? 'ACCEPTED' : 'DECLINED';
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = 'Task ID: ' . $taskId . ' - ' . $topic . ' - [ ' . $account . ' ] ';

// Email Body with Logo and Modern Formatting
        $companyLogo = 'https://web.monkbrian.com/assets/img/team/itasker-email-header.png';
        $taskDetailsUrl = "https://web.monkbrian.com/view-task?task_id=" . $encodedId;

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
                                        color: #0073e6;
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
                                    .btn {
                                        display: block;
                                        text-align: center;
                                        background: #0073e6;
                                        color: #ffffff;
                                        padding: 12px;
                                        border-radius: 5px;
                                        text-decoration: none;
                                        font-size: 16px;
                                        font-weight: bold;
                                        margin-top: 20px;
                                        transition: background 0.3s ease-in-out, color 0.3s ease-in-out;
                                    }
                                    .btn:hover {
                                        background: #005bb5; /* Darker blue on hover */
                                        color: #ffffff !important;
                                    }
                                    .footer {
                                        text-align: center;
                                        padding-top: 15px;
                                        font-size: 12px;
                                        color: #777;
                                    }
                                </style>
                            </head>
                            <body>
                                <div class='email-container'>
                                    <div class='email-header'>
                                        <img src='{$companyLogo}' alt='Company Logo'>
                                    </div>
                                    <div class='email-content'>
                                        <h2>Task ID: $taskId has been $status.</h2>
                                        <p>Hello <span class='highlight'>$writer</span>,</p>
                                        <p>Below are the task details:</p>
                                        <p><strong>Topic:</strong> <span class='highlight'>$topic</span></p>
                                        <p><strong>Pages:</strong> $pages</p>
                                        <p><strong>Price per Page:</strong> Ksh $cpp</p>
                                        <p><strong>Total Price:</strong> <span class='highlight'>Ksh $total_price</span></p>
                                        <p><strong>Due Date:</strong> <span class='highlight'>$due_date</span></p>
                                        
                                        <a class='btn' href='$taskDetailsUrl'>View More Task Details</a>
                                    </div>
                                    <div class='footer'>
                                        <p>For any questions, contact <a href='mailto:bryo4419@gmail.com'>bryo4419@gmail.com</a></p>
                                        <p>&copy; " . date('Y') . " iTasker. All rights reserved.</p>
                                    </div>
                                </div>
                            </body>
                            </html>";

        $mail->AltBody = "Task ID: $taskId has been $status.\n\n
                    Hello $writer,\n
                    Below are the task details:\n
                    Topic: $topic\n
                    Pages: $pages\n
                    Price per Page: Ksh $cpp\n
                    Total Price: Ksh $total_price\n
                    Due Date: $due_date\n
                    View Task Details: $taskDetailsUrl\n\n
                    For any questions, contact bryo4419@gmail.com";

        $mail->send();

    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

if (isset($_GET['task_id']) && isset($_GET['action'])) {
    $encodedId = $_GET['task_id'];
    $taskId = (int) base64_decode($encodedId);
    $action = $_GET['action'];

    // Fetch task details
    $sql = "SELECT * FROM tbltasks WHERE id = '$taskId'";
    $result = mysqli_query($con, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $task = mysqli_fetch_assoc($result);
        $writerEmail = $task['email'];
        $topic = $task['topic'];
        $account = $task['account'];
        $writer = $task['writer'];
        $pages = $task['pages'];
        $cpp = $task['cpp'];
        $due_date = $task['due_date'];

    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                                    <div class="bg-danger me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
                                    <p class="mb-0 flex-1">Task not found!</p>
                                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
        header('Location: view-task');
        exit();
    }

    if ($action == 'accept') {
        // Update the task to be accepted
        $sql = "UPDATE tbltasks SET is_confirmed = 0, status = 'In Progress' WHERE id = '$taskId'";
    } elseif ($action == 'decline') {
        // Update the task to be declined
        $sql = "UPDATE tbltasks SET is_confirmed = 2, status = 'Draft' WHERE id = '$taskId'";
    }

    if (mysqli_query($con, $sql)) {
        // Send email notification
        sendEmail($writer, $pages, $cpp, $due_date, $writerEmail, $taskId, $action, $topic, $account);

        $status = $action == 'accept' ? 'accepted' : 'declined';
        $_SESSION['alert'] = '<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
                                    <div class="bg-success me-3 icon-item"><span class="fas fa-check-circle text-white fs-6"></span></div>
                                    <p class="mb-0 flex-1">Task status updated successfully! The task has been ' . $status . '.</p>
                                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
    } else {
        $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                                    <div class="bg-danger me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
                                    <p class="mb-0 flex-1">Error updating task status!</p>
                                    <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
    }

    header('Location: view-task?task_id=' . $encodedId);
    exit();
} else {
    $_SESSION['alert'] = '<div class="alert alert-danger border-0 d-flex align-items-center" role="alert">
                                <div class="bg-danger me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
                                <p class="mb-0 flex-1">Invalid request!</p>
                                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
    header('Location: view-task');
    exit();
}
?>
