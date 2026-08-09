<?php
require_once __DIR__ . '/env.php';
include 'check-login.php';
csrf_verify_or_json_die();
require_once 'spaces-helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/email-template.php';

// Function to download a file using cURL
function downloadFile($url, $localPath)
{
    $ch = curl_init(str_replace(' ', '%20', $url));
    $fp = fopen($localPath, 'wb');

    if ($fp === false) {
        error_log("Failed to open local file for writing: $localPath");
        return false;
    }

    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($success === false) {
        error_log('cURL error: ' . curl_error($ch));
    } elseif ($httpCode >= 400) {
        error_log("HTTP error: $httpCode for URL: $url");
        $success = false;
    }

    curl_close($ch);
    fclose($fp);

    return $success;
}

if (isset($_POST['action']) && $_POST['action'] == 'submitForm') {
    // Keyed by the trusted session identity, not $_POST['email'] (a
    // client-controlled hidden field elsewhere in this file) - otherwise
    // a caller could dodge or frame a different writer's bucket just by
    // changing that field.
    $writerKey = $_SESSION['sessionWriter'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!check_rate_limit($con, 'task_submit', $writerKey, 2, 600)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => rate_limit_message($con, 'task_submit', $writerKey, 600, 'submissions')]);
        exit;
    }

    // Ensure taskfiles has at least one file
    if (empty($_POST['uploadedFiles']) || $_POST['uploadedFiles'] === '[]') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'You must submit at least one file.']);
        exit;
    }

    // Retrieve and sanitize input data
    $taskId = isset($_POST['taskId']) ? mysqli_real_escape_string($con, $_POST['taskId']) : '';
    $topic = isset($_POST['topic']) ? mysqli_real_escape_string($con, $_POST['topic']) : '';
    $due = isset($_POST['due']) && !empty($_POST['due']) ? mysqli_real_escape_string($con, $_POST['due']) : 'Not Provided';
    $writer = isset($_POST['writer']) && !empty($_POST['writer']) ? mysqli_real_escape_string($con, $_POST['writer']) : 'Not Provided';
    $account = isset($_POST['account']) ? mysqli_real_escape_string($con, $_POST['account']) : '';
    $writerEmail = isset($_POST['email']) ? mysqli_real_escape_string($con, $_POST['email']) : '';
    $writerComments = isset($_POST['writer_comments']) ? mysqli_real_escape_string($con, $_POST['writer_comments']) : '';
    $sendEmail = isset($_POST['sendEmail']) ? mysqli_real_escape_string($con, $_POST['sendEmail']) : '0';
    $pages = $_POST['pages'] ?? '';
    $cpp = $_POST['cpp'] ?? '';

    // Fetch additional details from the database if needed
    $query = 'SELECT due_date, writer, pages, cpp, revision_count FROM tbltasks WHERE id = ?';
    $revisionCount = 0;
    if ($stmt = mysqli_prepare($con, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $taskId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $due_date, $writer_db, $pages_db, $cpp_db, $revisionCount_db);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        // Use database values if available
        $due = $due_date ?? $due;
        $writer = $writer_db ?? $writer;
        $cpp = $cpp_db ?? $cpp;
        $pages = $pages_db ?? $pages;
        $revisionCount = (int) ($revisionCount_db ?? 0);
    }

    if (empty($taskId) || empty($topic) || empty($account) || empty($writerEmail)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Task ID, Topic, Account, and Email are required.']);
        exit;
    }

    $uploadedFiles = json_decode($_POST['uploadedFiles'], true);
    if (!is_array($uploadedFiles)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid uploaded files data.']);
        exit;
    }

    $submittedOn = date('Y-m-d H:i:s');

    // Start transaction
    mysqli_autocommit($con, FALSE);

    try {
        // Insert new files into tbl_task_files
        foreach ($uploadedFiles as $file) {
            $fileName = basename($file['filePath']);
            $fileUrl = $file['fileUrl'];
            $fileSize = isset($file['fileSize']) ? $file['fileSize'] : 0;
            $originalFileName = isset($file['originalName']) ? $file['originalName'] : $fileName;
            $filePath = $file['filePath']; // This should be the full path from your uploaded files

            // upload_time uses SQL NOW() (UTC) to match the 'task'-type files inserted
            // by sudo/update-task.php, sudo/submit-task.php and sudo/duplicate-task.php,
            // since every reader (view-task.php/sudo/view-task.php) parses this column
            // as UTC (strtotime($x . ' UTC')).
            $insertFileSql = "INSERT INTO tbl_task_files (task_id, file_name, original_file_name, file_path, file_url, file_size, file_type, uploaded_by, upload_time) VALUES (?, ?, ?, ?, ?, ?, 'submitted', ?, NOW())";

            if ($fileStmt = mysqli_prepare($con, $insertFileSql)) {
                mysqli_stmt_bind_param($fileStmt, 'issssis', $taskId, $fileName, $originalFileName, $filePath, $fileUrl, $fileSize, $writer);

                if (!mysqli_stmt_execute($fileStmt)) {
                    throw new Exception('Failed to insert file record: ' . mysqli_stmt_error($fileStmt));
                }

                // revision_number is 0 for a task's first/normal submission
                // (never been sent back for revision) and only becomes >0 -
                // matching tbltasks.revision_count at the time of this
                // submission - once the task has actually been through at
                // least one revision cycle, so the UI can badge only real
                // resubmissions as "Revision N". Separate, best-effort UPDATE:
                // if revision_number doesn't exist yet, this silently no-ops
                // rather than failing the whole submission above.
                if ($revisionCount > 0) {
                    $newFileId = mysqli_insert_id($con);
                    if ($revNumStmt = mysqli_prepare($con, "UPDATE tbl_task_files SET revision_number = ? WHERE id = ?")) {
                        mysqli_stmt_bind_param($revNumStmt, 'ii', $revisionCount, $newFileId);
                        mysqli_stmt_execute($revNumStmt);
                        mysqli_stmt_close($revNumStmt);
                    }
                }

                mysqli_stmt_close($fileStmt);
            } else {
                throw new Exception('File insert database error: ' . safe_db_error(mysqli_error($con)));
            }
        }

        // Update task status
        $sql = "UPDATE tbltasks SET submitted_on=?, status='Submitted' WHERE id=?";

        if ($stmt = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param($stmt, 'si', $submittedOn, $taskId);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to update task: ' . mysqli_stmt_error($stmt));
            }

            if (mysqli_stmt_affected_rows($stmt) == 0) {
                throw new Exception('No changes were made or task not found.');
            }

            mysqli_stmt_close($stmt);

            $submitDetails = ($revisionCount > 0 ? "Resubmission #$revisionCount" : 'Submission') . " - Task #$taskId: $topic";
            log_activity($con, 'writer', $writerKey, 'task_submit', $submitDetails);
        } else {
            throw new Exception('Database error: ' . safe_db_error(mysqli_error($con)));
        }

        // Add writer comment to threaded comments system if provided
        if (!empty($writerComments)) {
            // created_at uses SQL NOW() (UTC) to match add-task-comment.php's write
            // path, so all tbl_task_comments rows share one clock convention.
            $commentSql = "INSERT INTO tbl_task_comments (task_id, user_type, username, comment, created_at) VALUES (?, 'writer', ?, ?, NOW())";

            if ($commentStmt = mysqli_prepare($con, $commentSql)) {
                mysqli_stmt_bind_param($commentStmt, 'iss', $taskId, $writer, $writerComments);

                if (!mysqli_stmt_execute($commentStmt)) {
                    throw new Exception('Failed to add comment: ' . mysqli_stmt_error($commentStmt));
                }

                mysqli_stmt_close($commentStmt);
            } else {
                throw new Exception('Comment database error: ' . safe_db_error(mysqli_error($con)));
            }
        }

        // Commit transaction
        mysqli_commit($con);

        $emailStatus = '';
        if ($sendEmail == '1') {
            $encodedId = encode_task_id($taskId);
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

                $tempFiles = [];
                foreach ($uploadedFiles as $file) {
                    $tempFile = tempnam(sys_get_temp_dir(), 'email_attachment_');
                    $tempFiles[] = $tempFile;

                    if (downloadFile($file['fileUrl'], $tempFile)) {
                        $originalFileName = $file['originalName']; // Remove the fallback to basename
                        $mail->addAttachment($tempFile, $originalFileName);
                    } else {
                        error_log('Failed to download file for email attachment: ' . $file['fileUrl']);
                        array_pop($tempFiles);
                    }
                }

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Task #' . $taskId . ': ' . $topic . ' (' . $account . ')';
                $taskDetailsUrl = 'https://web.monkbrian.com/view-task?task_id=' . $encodedId;

                $emailBody = "
                <p>Hello <span class='highlight'>$writer</span>,</p>
                <p>Task <strong>$taskId</strong> has been submitted successfully. Below are the task details:</p>
                <p><strong>Topic:</strong> <span class='highlight'>$topic</span></p>
                <p><strong>Pages:</strong> $pages</p>
                <p><strong>Price per Page:</strong> Ksh $cpp</p>
                <p><strong>Total Price:</strong> <span class='highlight'>Ksh $total_price</span></p>
                <p><strong>Due Date:</strong> <span class='highlight'>$due_date</span></p>
                <p><strong>Submitted:</strong> <span class='highlight'>$submittedOn</span></p>";

                if (!empty($writerComments)) {
                    $emailBody .= "
                    <div style='background-color:#f8f9fa;padding:15px;border-left:4px solid #0073e6;margin:15px 0;border-radius:4px;'>
                    <p><strong>$writer Comments:</strong></p>
                    <p>" . nl2br(htmlspecialchars($writerComments)) . '</p>
                    </div>';
                }

                $mail->Body = render_email_html(
                    'Task Submitted Successfully!',
                    $emailBody,
                    'View More Task Details',
                    $taskDetailsUrl,
                    "For any questions, contact <a href='mailto:bryo4419@gmail.com'>bryo4419@gmail.com</a>"
                );

                $mail->AltBody = "Task Submitted Successfully!\n\n
                Hello $writer,\n
                Task $taskId has been submitted successfully. Below are the task details:\n
                Topic: $topic\n
                Pages: $pages\n
                Price per Page: Ksh $cpp\n
                Total Price: Ksh $total_price\n
                Due Date: $due_date\n
                Submitted: $submittedOn\n";

                if (!empty($writerComments)) {
                    $mail->AltBody .= "\n$writer Comments:\n" . $writerComments . "\n";
                }

                $mail->AltBody .= "\nView Task Details: $taskDetailsUrl\n\n
                For any questions, contact bryo4419@gmail.com";

                $mail->send();

                // Clean up temporary files
                foreach ($tempFiles as $tempFile) {
                    @unlink($tempFile);
                }

                $emailStatus = 'Email sent successfully.';
            } catch (Exception $e) {
                $emailStatus = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Task submitted successfully. ' . ($sendEmail == '1' ? $emailStatus : ''),
            'task_id' => encode_task_id($taskId)
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($con);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } finally {
        // Restore autocommit
        mysqli_autocommit($con, TRUE);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No action performed.']);
}
?>