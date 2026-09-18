<?php
// ══════════════════════════════════════════════════════════════════
//  task_status_summary.php
//  Twice-daily snapshot of work still in flight: tasks currently
//  "In Progress" (with their due dates) and tasks "Submitted" but not
//  yet marked Completed. Sent only when at least one such task exists
//  - a quiet night produces no email.
//
//  Meant to run at 18:00 and 00:00 Africa/Nairobi time; the script
//  itself doesn't gate on the hour, so both crontab lines below just
//  point at the same file.
//
//  Crontab example:
//    0 18 * * * /usr/local/bin/ea-php82 /home/monkbria/web.monkbrian.com/cron/task_status_summary.php >> /home/monkbria/web.monkbrian.com/cron/task_status_summary.log 2>&1
//    0 0  * * * /usr/local/bin/ea-php82 /home/monkbria/web.monkbrian.com/cron/task_status_summary.php >> /home/monkbria/web.monkbrian.com/cron/task_status_summary.log 2>&1
// ══════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../shared-functions.php';
require_once __DIR__ . '/../email-template.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Africa/Nairobi');

$config = [
    'admin_email' => env('ADMIN_EMAIL'),
    'from_email' => env('MAIL_FROM_ADDRESS'),
    'from_name' => 'itasker',
    'base_url' => 'https://web.monkbrian.com/sudo/',
];

function logLine($msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

function buildTaskRow($task, $dateLabel, $dateValue, $isOverdue) {
    $thStyle = "padding:10px 8px;border-bottom:1px solid #dee2e6;font-size:13px;";
    $dateColor = $isOverdue ? '#d32f2f' : '#495057';
    $taskUrl = 'https://web.monkbrian.com/sudo/view-task?task_id=' . encode_task_id($task['id']);

    return "
    <tr>
        <td style='$thStyle'><strong>#{$task['id']}</strong></td>
        <td style='$thStyle max-width:220px;'>" . htmlspecialchars(substr($task['topic'], 0, 50)) . (strlen($task['topic']) > 50 ? '...' : '') . "</td>
        <td style='$thStyle'>" . htmlspecialchars($task['account']) . " - " . htmlspecialchars($task['writer']) . "</td>
        <td style='$thStyle color:{$dateColor};font-weight:" . ($isOverdue ? 'bold' : 'normal') . ";'>{$dateLabel}: {$dateValue}</td>
        <td style='$thStyle'><a href='{$taskUrl}' class='btn' style='display:inline-block;margin:0;font-size:11px;padding:6px 12px;'>View Task</a></td>
    </tr>";
}

function generateSummaryBody($inProgressTasks, $submittedTasks) {
    $thStyle = "background:#0073e6;color:white;padding:12px 8px;text-align:left;font-size:14px;";
    $now = new DateTime();

    $body = '';

    if (!empty($inProgressTasks)) {
        $body .= "<h3 style='color:#17a2b8;margin:20px 0 10px 0;'>&#128221; In Progress (" . count($inProgressTasks) . ")</h3>
        <table style='width:100%;border-collapse:collapse;margin-bottom:10px;'>
            <thead><tr>
                <th style='$thStyle'>Task ID</th><th style='$thStyle'>Topic</th><th style='$thStyle'>Writer</th><th style='$thStyle'>Due</th><th style='$thStyle'>Action</th>
            </tr></thead><tbody>";
        foreach ($inProgressTasks as $task) {
            $due = new DateTime($task['due_date']);
            $isOverdue = $due < $now;
            $dueDisplay = date('M j, Y g:i A', strtotime($task['due_date']));
            $body .= buildTaskRow($task, $isOverdue ? 'Overdue since' : 'Due', $dueDisplay, $isOverdue);
        }
        $body .= "</tbody></table>";
    }

    if (!empty($submittedTasks)) {
        $body .= "<h3 style='color:#6f42c1;margin:20px 0 10px 0;'>&#128228; Submitted - Awaiting Completion (" . count($submittedTasks) . ")</h3>
        <table style='width:100%;border-collapse:collapse;margin-bottom:10px;'>
            <thead><tr>
                <th style='$thStyle'>Task ID</th><th style='$thStyle'>Topic</th><th style='$thStyle'>Writer</th><th style='$thStyle'>Submitted</th><th style='$thStyle'>Action</th>
            </tr></thead><tbody>";
        foreach ($submittedTasks as $task) {
            $submittedDisplay = !empty($task['submitted_on']) ? date('M j, Y g:i A', strtotime($task['submitted_on'])) : 'Unknown';
            $body .= buildTaskRow($task, 'Submitted', $submittedDisplay, false);
        }
        $body .= "</tbody></table>";
    }

    $body .= "<div style='text-align:center;margin:30px 0;'>
        <a href='https://web.monkbrian.com/sudo/tasks-in-progress' class='btn' style='display:inline-block;width:auto;margin:5px;font-size:16px;padding:12px 24px;'>View In Progress</a>
        <a href='https://web.monkbrian.com/sudo/submitted-tasks' class='btn' style='display:inline-block;width:auto;margin:5px;font-size:16px;padding:12px 24px;'>View Submitted</a>
    </div>";

    return $body;
}

function generatePlainTextBody($inProgressTasks, $submittedTasks) {
    $text = "TASK STATUS SUMMARY - " . date('Y-m-d H:i:s') . "\n" . str_repeat("=", 50) . "\n\n";

    if (!empty($inProgressTasks)) {
        $text .= "IN PROGRESS (" . count($inProgressTasks) . "):\n" . str_repeat("-", 40) . "\n";
        foreach ($inProgressTasks as $task) {
            $text .= "#{$task['id']} {$task['topic']} - {$task['writer']} - Due: " . date('M j, Y g:i A', strtotime($task['due_date'])) . "\n";
        }
        $text .= "\n";
    }

    if (!empty($submittedTasks)) {
        $text .= "SUBMITTED - AWAITING COMPLETION (" . count($submittedTasks) . "):\n" . str_repeat("-", 40) . "\n";
        foreach ($submittedTasks as $task) {
            $submittedDisplay = !empty($task['submitted_on']) ? date('M j, Y g:i A', strtotime($task['submitted_on'])) : 'Unknown';
            $text .= "#{$task['id']} {$task['topic']} - {$task['writer']} - Submitted: {$submittedDisplay}\n";
        }
    }

    return $text;
}

function sendSummaryEmail($inProgressTasks, $submittedTasks, $config) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USER');
        $mail->Password = env('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) env('SMTP_PORT', 587);

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($config['admin_email']);

        $totalCount = count($inProgressTasks) + count($submittedTasks);
        $mail->isHTML(true);
        $mail->Subject = "Task Status Summary (" . date('g:i A') . ") - {$totalCount} Task" . ($totalCount != 1 ? 's' : '') . " Open";

        $bodyContent = generateSummaryBody($inProgressTasks, $submittedTasks);
        $mail->Body = render_email_html('Task Status Summary', $bodyContent, null, null, "This is an automated message. For support, contact <a href='mailto:{$config['admin_email']}'>{$config['admin_email']}</a>");
        $mail->AltBody = generatePlainTextBody($inProgressTasks, $submittedTasks);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Task status summary email failed: {$mail->ErrorInfo}");
        return false;
    }
}

// Main execution
try {
    $inProgressResult = mysqli_query($con, "
        SELECT id, topic, account, writer, due_date
        FROM tbltasks
        WHERE is_deleted = 0 AND status = 'In Progress'
        ORDER BY due_date ASC
    ");
    $inProgressTasks = [];
    while ($row = mysqli_fetch_assoc($inProgressResult)) {
        $inProgressTasks[] = $row;
    }

    $submittedResult = mysqli_query($con, "
        SELECT id, topic, account, writer, due_date, submitted_on
        FROM tbltasks
        WHERE is_deleted = 0 AND status = 'Submitted'
        ORDER BY submitted_on ASC
    ");
    $submittedTasks = [];
    while ($row = mysqli_fetch_assoc($submittedResult)) {
        $submittedTasks[] = $row;
    }

    $totalCount = count($inProgressTasks) + count($submittedTasks);

    if ($totalCount === 0) {
        logLine('No tasks in progress or submitted - summary skipped.');
    } elseif (sendSummaryEmail($inProgressTasks, $submittedTasks, $config)) {
        $message = "Task status summary sent: " . count($inProgressTasks) . " in progress, " . count($submittedTasks) . " submitted.";
        logLine($message);
        log_activity($con, 'system', 'cron', 'task_status_summary_sent', $message);
    } else {
        logLine('FATAL: Failed to send task status summary email.');
        log_activity($con, 'system', 'cron', 'task_status_summary_failed', "In progress: " . count($inProgressTasks) . ", Submitted: " . count($submittedTasks));
        exit(1);
    }
} catch (Exception $e) {
    $errorMessage = "Error in task status summary: " . $e->getMessage();
    logLine($errorMessage);
    error_log($errorMessage);
    exit(1);
}

mysqli_close($con);
