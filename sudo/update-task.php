<?php
require_once __DIR__ . '/../env.php';
include 'check-login.php';
csrf_verify_or_json_die();
require_once 'spaces-helper.php';

// AJAX file deletion (before form submission)
if (isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    header('Content-Type: application/json');

    if (!isset($_POST['file_id']) || empty($_POST['file_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'File ID is required']);
        exit;
    }

    $fileId = (int)$_POST['file_id'];

    try {
        // Use the same query structure as your existing code
        $deleteFileQuery = 'UPDATE tbl_task_files SET is_deleted = 1 WHERE id = ?';
        $deleteStmt = mysqli_prepare($con, $deleteFileQuery);
        mysqli_stmt_bind_param($deleteStmt, 'i', $fileId);

        if (mysqli_stmt_execute($deleteStmt)) {
            $affectedRows = mysqli_stmt_affected_rows($deleteStmt);
            mysqli_stmt_close($deleteStmt);

            if ($affectedRows > 0) {
                echo json_encode(['status' => 'success', 'message' => 'File removed successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'File not found or already deleted']);
            }
        } else {
            mysqli_stmt_close($deleteStmt);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete file']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }

    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../email-template.php';

// Builds a "what changed" HTML box (old value struck through, new value in
// blue) comparing the task's pre-edit row against the newly-posted values.
// Returns '' if nothing meaningfully changed, so callers can skip the box
// entirely. Dates/numbers are compared by value, not raw string, so
// re-saving the same due date/price in a different format isn't flagged.
function buildTaskChangeSummary($oldRow, $new) {
    if (!$oldRow) {
        return '';
    }

    $labels = [
        'topic' => 'Topic',
        'subject' => 'Subject',
        'account' => 'Account',
        'due_date' => 'Due Date',
        'pages' => 'Pages',
        'cpp' => 'Price per Page',
        'writer' => 'Writer',
    ];

    $rows = '';
    foreach ($labels as $field => $label) {
        $oldVal = trim((string) ($oldRow[$field] ?? ''));
        $newVal = trim((string) ($new[$field] ?? ''));

        if ($field === 'due_date') {
            $oldTs = strtotime($oldVal);
            $newTs = strtotime($newVal);
            if ($oldTs !== false && $newTs !== false && $oldTs === $newTs) {
                continue;
            }
        } elseif ($field === 'cpp' || $field === 'pages') {
            if ((float) $oldVal === (float) $newVal) {
                continue;
            }
        } elseif (strcasecmp($oldVal, $newVal) === 0) {
            continue;
        }

        $displayOld = $oldVal !== '' ? htmlspecialchars($oldVal, ENT_QUOTES, 'UTF-8') : '<em>none</em>';
        $displayNew = $newVal !== '' ? htmlspecialchars($newVal, ENT_QUOTES, 'UTF-8') : '<em>none</em>';
        if ($field === 'cpp') {
            $displayOld = 'Ksh ' . $displayOld;
            $displayNew = 'Ksh ' . $displayNew;
        }

        $rows .= "<tr>"
            . "<td style='padding:6px 10px;font-size:14px;color:#555;border-bottom:1px solid #eee;'>{$label}</td>"
            . "<td style='padding:6px 10px;font-size:14px;color:#999;text-decoration:line-through;border-bottom:1px solid #eee;'>{$displayOld}</td>"
            . "<td style='padding:6px 10px;font-size:14px;color:#0073e6;font-weight:bold;border-bottom:1px solid #eee;'>{$displayNew}</td>"
            . "</tr>";
    }

    $oldDescription = trim((string) ($oldRow['description'] ?? ''));
    $newDescription = trim((string) ($new['description'] ?? ''));
    if (strcasecmp($oldDescription, $newDescription) !== 0) {
        $rows .= "<tr>"
            . "<td style='padding:6px 10px;font-size:14px;color:#555;'>Description</td>"
            . "<td colspan='2' style='padding:6px 10px;font-size:14px;color:#0073e6;font-weight:bold;'>Updated</td>"
            . "</tr>";
    }

    if ($rows === '') {
        return '';
    }

    return "<div style='background:#f8f9fa;padding:15px;border-radius:5px;margin:15px 0;border-left:4px solid #0073e6;'>"
        . "<p style='margin:0 0 8px 0;'><strong>What Changed</strong></p>"
        . "<table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;'>{$rows}</table>"
        . "</div>";
}

if ($_POST['action'] == 'submitForm') {
    $requiredFields = ['topic', 'subject', 'account', 'description', 'writer', 'email', 'due_date', 'cpp', 'pages'];

    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => "The field {$field} is required."]);
            exit;
        }
    }

    // Note: string fields below are passed as-is to a parameterized query,
    // which handles escaping itself. Do not mysqli_real_escape_string() these
    // before binding - that double-escapes the value (e.g. a real newline in
    // the description becomes the literal text "\r\n" in storage).
    $taskId = mysqli_real_escape_string($con, $_POST['taskId']);
    $topic = $_POST['topic'];
    $subject = $_POST['subject'];
    $account = $_POST['account'];
    $description = $_POST['description'];
    $writer = $_POST['writer'];
    $writerEmail = $_POST['email'];

    // Fallback: if email is empty (e.g. hidden field not submitted), fetch from DB
    if (empty($writerEmail) && !empty($writer)) {
        $emailFallbackQuery = mysqli_query($con, "SELECT email FROM tblwriters WHERE username='" . mysqli_real_escape_string($con, $writer) . "' LIMIT 1");
        if ($emailFallbackRow = mysqli_fetch_assoc($emailFallbackQuery)) {
            $writerEmail = $emailFallbackRow['email'];
        }
    }
    $status = $_POST['status'];
    $due_date = $_POST['due_date'];
    $cpp = $_POST['cpp'];
    $pages = $_POST['pages'];
    $is_confirmed = mysqli_real_escape_string($con, $_POST['is_confirmed']);
    $publish = mysqli_real_escape_string($con, $_POST['publish']);
    $admin_acknowledged = mysqli_real_escape_string($con, $_POST['admin_acknowledged']);
    $acknowledged = mysqli_real_escape_string($con, $_POST['acknowledged']);
    $sendEmail = isset($_POST['sendEmail']) ? mysqli_real_escape_string($con, $_POST['sendEmail']) : '0';

    // Snapshot the task's pre-edit row. Used below to (a) decide whether this
    // is a fresh transition into "In Revision" (bumps the revision counter),
    // (b) tell a duplicated/never-emailed task's first send apart from a
    // later edit of an already-notified task, and (c) build a "what changed"
    // summary for the update email. SELECT * (not named columns) so a
    // not-yet-migrated first_notified_at column just comes back absent
    // rather than failing the query.
    $oldRow = null;
    if ($prevStmt = mysqli_prepare($con, "SELECT * FROM tbltasks WHERE id = ?")) {
        mysqli_stmt_bind_param($prevStmt, 'i', $taskId);
        mysqli_stmt_execute($prevStmt);
        $prevResult = mysqli_stmt_get_result($prevStmt);
        $oldRow = mysqli_fetch_assoc($prevResult);
        mysqli_stmt_close($prevStmt);
    }
    $wasInRevision = $oldRow ? ($oldRow['status'] === 'In Revision') : null;
    $isFirstNotification = empty($oldRow['first_notified_at'] ?? null);

    // Update the main task record (without file columns)
    $sql = 'UPDATE tbltasks SET topic=?, subject=?, account=?, description=?, writer=?, email=?, status=?, due_date=?, cpp=?, pages=?, is_confirmed=?, publish =?, admin_acknowledged=?, acknowledged=? WHERE id=?';

    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 'ssssssssssiiiii', $topic, $subject, $account, $description, $writer, $writerEmail, $status, $due_date, $cpp, $pages, $is_confirmed, $publish, $admin_acknowledged, $acknowledged, $taskId);

        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) >= 0) { // Changed to >= 0 to handle cases where no changes were made

                // A fresh transition into "In Revision" starts a new resubmission
                // cycle - bump revision_count so files submitted after this point
                // can be badged "Revision 1", "Revision 2", etc. Separate,
                // best-effort UPDATE: if revision_count doesn't exist yet, this
                // silently no-ops rather than failing the task save above.
                if ($status === 'In Revision' && $wasInRevision === false) {
                    if ($revStmt = mysqli_prepare($con, "UPDATE tbltasks SET revision_count = revision_count + 1 WHERE id = ?")) {
                        mysqli_stmt_bind_param($revStmt, 'i', $taskId);
                        mysqli_stmt_execute($revStmt);
                        mysqli_stmt_close($revStmt);
                    }
                }

                // Handle file operations
                // 1. Mark removed files as deleted
                $removedFileIds = !empty($_POST['removedFileIds']) ? json_decode($_POST['removedFileIds'], true) : [];
                if (!empty($removedFileIds)) {
                    foreach ($removedFileIds as $fileId) {
                        $deleteFileQuery = 'UPDATE tbl_task_files SET is_deleted = 1 WHERE id = ? AND task_id = ?';
                        $deleteStmt = mysqli_prepare($con, $deleteFileQuery);
                        mysqli_stmt_bind_param($deleteStmt, 'ii', $fileId, $taskId);
                        mysqli_stmt_execute($deleteStmt);
                        mysqli_stmt_close($deleteStmt);
                    }
                }

                // 2. Add new uploaded files
                $uploadedFiles = [];
                if (isset($_POST['uploadedFiles']) && !empty($_POST['uploadedFiles'])) {
                    $uploadedFilesData = json_decode($_POST['uploadedFiles'], true);
                    if (is_array($uploadedFilesData)) {
                        foreach ($uploadedFilesData as $fileData) {
                            // Bound via prepared statement below - do not pre-escape (see note above).
                            $fileName = basename($fileData['filePath']);
                            $originalFileName = $fileData['fileName'];
                            $filePath = $fileData['filePath'];
                            $fileUrl = $fileData['fileUrl'];
                            $fileSize = (int)$fileData['fileSize'];

                            // Insert file record into tbl_task_files
                            $fileInsertQuery = "INSERT INTO tbl_task_files (task_id, file_name, original_file_name, file_path, file_url, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, 'task', ?)";
                            $fileStmt = mysqli_prepare($con, $fileInsertQuery);
                            mysqli_stmt_bind_param($fileStmt, 'issssis', $taskId, $fileName, $originalFileName, $filePath, $fileUrl, $fileSize, $writer);

                            if (mysqli_stmt_execute($fileStmt)) {
                                // Store file data for email attachments
                                $uploadedFiles[] = $fileData;
                            } else {
                                error_log('Failed to insert file record: ' . mysqli_stmt_error($fileStmt));
                            }
                            mysqli_stmt_close($fileStmt);
                        }
                    }
                }

                $emailStatus = '';

                if ($sendEmail == '1') {
                    $encodedId = encode_task_id($taskId);
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

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Task #' . $taskId . ': ' . $topic . ' (' . $account . ')';


                        // Email Body via shared template
                        $taskDetailsUrl = 'https://web.monkbrian.com/view-task?task_id=' . $encodedId;
                        $displayStatus = ($status == 'Draft' ? 'Unconfirmed' : $status);

                        // A duplicated task (or any task published for the first time) has
                        // never had an assignment email - send the same "New Task Assigned"
                        // wording/layout as submit-task.php instead of "Task Updated", since
                        // this is genuinely the writer's first notification.
                        if ($isFirstNotification) {
                            $emailTitle = "New $displayStatus Task Assigned";
                            $emailBody = "
                                <p>Hello <span class='highlight'>$writer</span>,</p>
                                <p>A new task has been assigned to you. Below are the details:</p>
                                <p><strong>Status:</strong> <span class='highlight'>$displayStatus</span></p>
                                <p><strong>Topic:</strong> <span class='highlight'>$topic</span></p>
                                <p><strong>Subject:</strong> $subject</p>
                                <p><strong>Due Date:</strong> <span class='highlight'>$due_date</span></p>
                                <p><strong>Pages:</strong> $pages</p>
                                <p><strong>Price per Page:</strong> Ksh $cpp</p>
                                <p><strong>Description:</strong> <span class='highlight'>$description</span></p>";
                            $altIntro = "New $displayStatus Task Assigned\n\nHello $writer,\n\nA new task has been assigned to you.\n\n";
                        } else {
                            $emailTitle = "Task Updated - $displayStatus";
                            $changesSummaryHtml = buildTaskChangeSummary($oldRow, [
                                'topic' => $topic, 'subject' => $subject, 'account' => $account,
                                'due_date' => $due_date, 'pages' => $pages, 'cpp' => $cpp,
                                'description' => $description, 'writer' => $writer,
                            ]);
                            $emailBody = "
                                <p>Hello <span class='highlight'>$writer</span>,</p>
                                <p>Your task has been updated.</p>"
                                . $changesSummaryHtml . "
                                <p>Below are the current details:</p>
                                <p><strong>Status:</strong> <span class='highlight'>$displayStatus</span></p>
                                <p><strong>Topic:</strong> <span class='highlight'>$topic</span></p>
                                <p><strong>Subject:</strong> $subject</p>
                                <p><strong>Due Date:</strong> <span class='highlight'>$due_date</span></p>
                                <p><strong>Pages:</strong> $pages</p>
                                <p><strong>Price per Page:</strong> Ksh $cpp</p>
                                <p><strong>Description:</strong> $description</p>";
                            $altIntro = "Task Update: $displayStatus\n\nHello $writer,\n\nYour task has been updated.\n\n";
                        }

                        $mail->Body = render_email_html(
                            $emailTitle,
                            $emailBody,
                            'View More Task Details',
                            $taskDetailsUrl,
                            "For any questions, contact <a href='mailto:bryo4419@gmail.com'>bryo4419@gmail.com</a>"
                        );

                        $mail->AltBody = $altIntro
                            . "Status: $displayStatus\n"
                            . "Topic: $topic\n"
                            . "Subject: $subject\n"
                            . "Due Date: $due_date\n"
                            . "Pages: $pages\n"
                            . "Price per Page: Ksh $cpp\n"
                            . "Description: $description\n\n"
                            . "View Task Details: $taskDetailsUrl\n\n"
                            . "For any questions, contact bryo4419@gmail.com";

                        $mail->send();
                        $emailStatus = 'Email sent successfully.';

                        // Best-effort: mark first notification sent, same no-op-if-missing
                        // guard as submit-task.php.
                        if ($isFirstNotification) {
                            if ($notifyStmt = mysqli_prepare($con, "UPDATE tbltasks SET first_notified_at = NOW() WHERE id = ? AND first_notified_at IS NULL")) {
                                mysqli_stmt_bind_param($notifyStmt, 'i', $taskId);
                                mysqli_stmt_execute($notifyStmt);
                                mysqli_stmt_close($notifyStmt);
                            }
                        }

                        // Clean up temporary files
                        foreach ($tempFiles as $tempFile) {
                            @unlink($tempFile);
                        }
                    } catch (Exception $e) {
                        $fullError = $mail->ErrorInfo ?: $e->getMessage();
                        error_log('Email Error: ' . $fullError);
                        $emailStatus = "Email sending failed: " . $fullError;
                    }
                }

                header('Content-Type: application/json');
                $response = [
                    'status' => 'success',
                    'message' => 'Task updated successfully.',
                    'task_id' => encode_task_id($taskId)
                ];

                if (!empty($emailStatus)) {
                    $response['emailStatus'] = $emailStatus;
                }

                echo json_encode($response);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'No changes were made or task not found.']);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_stmt_error($stmt)]);
        }
        mysqli_stmt_close($stmt);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . safe_db_error(mysqli_error($con))]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No action performed.']);
}
?>