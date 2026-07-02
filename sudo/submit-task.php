<?php
require_once __DIR__ . '/../env.php';
ob_start();

include "check-login.php";
csrf_verify_or_json_die();
require_once 'spaces-helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Function to send clean JSON response
function sendJsonResponse($data) {
    // Clear any unwanted output
    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Function to handle errors gracefully
function handleError($message) {
    error_log("Task submission error: " . $message);
    sendJsonResponse(['status' => 'error', 'message' => $message]);
}

// Check database connection
if (!$con) {
    handleError("Database connection failed: " . mysqli_connect_error());
}

if (!isset($_POST['action']) || $_POST['action'] !== 'submitForm') {
    handleError('No action performed.');
}

// Validate required fields
$requiredFields = ['topic', 'subject', 'account', 'description', 'writer', 'email', 'due_date', 'cpp', 'pages'];

foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        handleError("The field {$field} is required.");
    }
}

try {
    // Sanitize input data
    $topic = mysqli_real_escape_string($con, $_POST['topic']);
    $subject = mysqli_real_escape_string($con, $_POST['subject']);
    $account = mysqli_real_escape_string($con, $_POST['account']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $writer = mysqli_real_escape_string($con, $_POST['writer']);
    $writerEmail = mysqli_real_escape_string($con, $_POST['email']);
    $due_date = mysqli_real_escape_string($con, $_POST['due_date']);
    $cpp = mysqli_real_escape_string($con, $_POST['cpp']);
    $pages = mysqli_real_escape_string($con, $_POST['pages']);
    $is_confirmed = mysqli_real_escape_string($con, $_POST['is_confirmed']);
    $publish = mysqli_real_escape_string($con, $_POST['publish']);

    // Extract writer name from the combined value
    $writerInfo = explode('|', $writer);
    $writerName = $writerInfo[0];

    // Determine the status based on publish and is_confirmed values
    if ($publish == 0) {
        $status = 'Draft'; // Not published, always draft
    } else {
        $status = ($is_confirmed == 0) ? 'In Progress' : 'Draft';
    }

    // Prepare SQL statement
    $sql = 'INSERT INTO tbltasks (topic, subject, account, description, writer, email, due_date, cpp, pages, is_confirmed, publish, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        throw new Exception("Database prepare error: " . safe_db_error(mysqli_error($con)));
    }

    // Bind parameters and execute statement
    mysqli_stmt_bind_param($stmt, 'ssssssssssss', $topic, $subject, $account, $description, $writerName, $writerEmail, $due_date, $cpp, $pages, $is_confirmed, $publish, $status);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Database execution error: " . mysqli_stmt_error($stmt));
    }

    // Check if insert was successful
    if (mysqli_stmt_affected_rows($stmt) <= 0) {
        throw new Exception("Failed to create task - no rows affected");
    }

    $task_id = mysqli_insert_id($con);
    $encodedId = base64_encode((string)$task_id);

    // Handle file uploads - Insert into tbl_task_files table
    $uploadedFiles = [];
    if (isset($_POST['uploadedFiles']) && !empty($_POST['uploadedFiles'])) {
        $uploadedFilesData = json_decode($_POST['uploadedFiles'], true);
        if (is_array($uploadedFilesData)) {
            foreach ($uploadedFilesData as $fileData) {
                $fileName = mysqli_real_escape_string($con, basename($fileData['filePath']));
                $originalFileName = mysqli_real_escape_string($con, $fileData['fileName']);
                $filePath = mysqli_real_escape_string($con, $fileData['filePath']);
                $fileUrl = mysqli_real_escape_string($con, $fileData['fileUrl']);
                $fileSize = (int)$fileData['fileSize'];

                // Insert file record into tbl_task_files
                $fileInsertQuery = "INSERT INTO tbl_task_files (task_id, file_name, original_file_name, file_path, file_url, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, 'task', 'Admin')";
                $fileStmt = mysqli_prepare($con, $fileInsertQuery);

                if ($fileStmt) {
                    mysqli_stmt_bind_param($fileStmt, 'issssi', $task_id, $fileName, $originalFileName, $filePath, $fileUrl, $fileSize);

                    if (mysqli_stmt_execute($fileStmt)) {
                        $uploadedFiles[] = $fileData;
                    } else {
                        error_log('Failed to insert file record: ' . mysqli_stmt_error($fileStmt));
                    }
                    mysqli_stmt_close($fileStmt);
                }
            }
        }
    }

    $emailSent = false;

    // Send email only if publish is set to 1
    if ($publish == 1) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = env('SMTP_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = env('SMTP_USER');
            $mail->Password = env('SMTP_PASS');
            $mail->SMTPSecure = 'tls';
            $mail->Port = (int) env('SMTP_PORT', 587);

            // Recipients
            $mail->setFrom(env('MAIL_FROM_ADDRESS'), 'itasker');
            $mail->addAddress($writerEmail);
            $mail->addBCC(env('ADMIN_EMAIL'), 'iTasker Admin');
            $mail->addCustomHeader('X-Priority', '3');
            $mail->addCustomHeader('X-Mailer', 'iTasker v1.0');
            $mail->addCustomHeader('List-Unsubscribe', '<mailto:support@monkbrian.com>');

            // Handle attachments
            $tempFiles = [];
            if (!empty($uploadedFiles)) {
                foreach ($uploadedFiles as $fileData) {
                    try {
                        // Create temp directory if it doesn't exist
                        $tempDir = sys_get_temp_dir();
                        if (empty($tempDir) || !is_writable($tempDir)) {
                            $tempDir = dirname(__FILE__) . '/temp';
                            if (!is_dir($tempDir)) {
                                mkdir($tempDir, 0755, true);
                            }
                        }

                        // Generate unique temp file path
                        $tempFile = $tempDir . '/' . uniqid('email_attachment_') . '_' . basename($fileData['fileName']);

                        // URL encode to handle spaces and special characters
                        $encodedUrl = str_replace(' ', '%20', $fileData['fileUrl']);

                        $ch = curl_init($encodedUrl);
                        $fp = fopen($tempFile, 'wb');

                        if ($fp !== false) {
                            curl_setopt($ch, CURLOPT_FILE, $fp);
                            curl_setopt($ch, CURLOPT_HEADER, 0);
                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                            $success = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                            fclose($fp);
                            curl_close($ch);

                            if ($success !== false && $httpCode == 200 && file_exists($tempFile) && filesize($tempFile) > 0) {
                                $mail->addAttachment($tempFile, $fileData['fileName']);
                                $tempFiles[] = $tempFile;
                            } else {
                                error_log('cURL error or empty file: ' . curl_error($ch) . ' for URL: ' . $encodedUrl . ' HTTP Code: ' . $httpCode);
                                // Clean up failed temp file
                                if (file_exists($tempFile)) {
                                    @unlink($tempFile);
                                }
                            }
                        } else {
                            error_log("Failed to open temp file: $tempFile");
                        }
                    } catch (Exception $e) {
                        error_log("Error processing attachment: " . $e->getMessage());
                    }
                }
            }

            // Email content
            $mail->isHTML(true);
            $mail->Subject = 'Task #' . $task_id . ': ' . $topic . ' (' . $account . ')';


            // Email Body with Logo and Modern Formatting
            $companyLogo = 'https://web.monkbrian.com/assets/img/team/itasker-email-header.png';
            $taskDetailsUrl = 'https://web.monkbrian.com/view-task?task_id=' . $encodedId;

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
                color: #ffff;
                padding: 12px;
                border-radius: 5px;
                text-decoration: none;
                font-size: 16px;
                font-weight: bold;
                margin-top: 20px;
                transition: background 0.3s ease-in-out, color 0.3s ease-in-out;
            }
            .btn:hover {
                background: #005bb5;
                color: #ffff !important;
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
                    <img src='{$companyLogo}' alt='itasker logo'>
                </div>
                <div class='email-content'>
                    <h2>New " . ($status == 'Draft' ? 'Unconfirmed' : $status) . " Task Assigned</h2>
                    <p>Hello <span class='highlight'>$writerName</span>,</p>
                    <p>A new task has been assigned to you. Below are the details:</p>
                    <p><strong>Status:</strong> <span class='highlight'>" . ($status == 'Draft' ? 'Unconfirmed' : $status) . "</span></p>
                    <p><strong>Topic:</strong> <span class='highlight'>$topic</span></p>
                    <p><strong>Subject:</strong> $subject</p>
                    <p><strong>Due Date:</strong> <span class='highlight'>$due_date</span></p>
                    <p><strong>Pages:</strong> $pages</p>
                    <p><strong>Price per Page:</strong> Ksh $cpp</p>
                    <p><strong>Description:</strong> <span class='highlight'>$description</span></p>
                    
                    <a class='btn' href='$taskDetailsUrl'>View More Task Details</a>
                </div>
                <div class='footer'>
                    <p>For any questions, contact <a href='mailto:bryo4419@gmail.com'>bryo4419@gmail.com</a></p>
                    <p>&copy; " . date('Y') . ' iTasker. All rights reserved.</p>
                </div>
            </div>
            </body>
            </html>';

            $mail->AltBody = 'New ' . ($status == 'Draft' ? 'Unconfirmed' : $status) . "Task Assigned\n\n
            Hello $writerName,\n
            A new task has been assigned to you.\n
            Status: " . ($status == 'Draft' ? 'Unconfirmed' : $status) . "\n
            Topic: $topic\n
            Subject: $subject\n
            Due Date: $due_date\n
            Pages: $pages\n
            Price per Page: Ksh $cpp\n
            Description: $description\n
            View Task Details: $taskDetailsUrl\n\n
            For any questions, contact bryo4419@gmail.com";

            $mail->send();
            $emailSent = true;

            // Clean up temp files
            foreach ($tempFiles as $tempFile) {
                @unlink($tempFile);
            }

        } catch (Exception $e) {
            error_log("Email sending failed. Mailer Error: {$mail->ErrorInfo}");
            $emailSent = false;
        }
    }

    // Determine success message based on publish status
    if ($publish == 1) {
        $emailStatus = $emailSent ? 'Email sent successfully.' : 'Email sending failed.';
        $successMessage = "Task created and published successfully. {$emailStatus}";
    } else {
        $successMessage = "Task saved as draft successfully. No email sent.";
    }

    // Send success response
    sendJsonResponse([
        'status' => 'success',
        'message' => $successMessage,
        'task_id' => $encodedId,
        'emailSent' => $emailSent
    ]);

} catch (Exception $e) {
    error_log("Task creation failed: " . $e->getMessage());
    handleError("Database error: " . $e->getMessage());
} finally {
    // Clean up resources
    if (isset($stmt) && $stmt) {
        mysqli_stmt_close($stmt);
    }
}
?>