<?php
require_once __DIR__ . '/../env.php';
ob_start();
session_start();
$appDebug = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
error_reporting(E_ALL);
ini_set('log_errors', 1); // Log errors to file
ini_set('error_log', __DIR__ . '/php-errors.log');
date_default_timezone_set('Africa/Nairobi');
include('dbcon.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer autoloader
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
// Email configuration
$email_config = [
    'smtp_host' => env('SMTP_HOST'),
    'smtp_port' => (int) env('SMTP_PORT', 587),
    'smtp_username' => env('SMTP_USER'),
    'smtp_password' => env('SMTP_PASS'),
    'from_email' => env('MAIL_FROM_ADDRESS'),
    'from_name' => 'Reminder System',
    'to_email' => env('ADMIN_EMAIL')
];
// Email helper functions
function sendEmail($to, $subject, $htmlBody, $textBody = '') {
    global $email_config;

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $email_config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $email_config['smtp_username'];
        $mail->Password = $email_config['smtp_password'];
        $mail->SMTPSecure = 'tls';
        $mail->Port = $email_config['smtp_port'];

        // Recipients
        $mail->setFrom($email_config['from_email'], $email_config['from_name']);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Helper function to get setting value
function getSetting($key, $default = '') {
    global $dbh;

    try {
        $stmt = $dbh->prepare('SELECT setting_value FROM reminder_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function getModernEmailTemplate($title, $content, $footerText = '') {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f8f9fa; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { font-size: 28px; font-weight: 600; margin-bottom: 8px; }
            .header p { font-size: 16px; opacity: 0.9; }
            .content { padding: 30px 20px; }
            .reminder-card { background: #f8f9fa; border-left: 4px solid #007bff; padding: 20px; margin: 15px 0; border-radius: 8px; }
            .reminder-card.high { border-left-color: #dc3545; }
            .reminder-card.medium { border-left-color: #ffc107; }
            .reminder-card.low { border-left-color: #28a745; }
            .reminder-card.overdue { border-left-color: #dc3545; background: #fff5f5; }
            .reminder-title { font-size: 18px; font-weight: 600; margin-bottom: 8px; color: #2c3e50; }
            .reminder-meta { font-size: 14px; color: #6c757d; margin-bottom: 10px; }
            .reminder-description { font-size: 15px; color: #495057; }
            .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
            .status-overdue { background: #fee; color: #dc3545; }
            .status-due-today { background: #fff3cd; color: #856404; }
            .status-upcoming { background: #d1ecf1; color: #0c5460; }
            .status-completed { background: #d4edda; color: #155724; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #6c757d; border-top: 1px solid #dee2e6; }
            .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; font-weight: 500; margin: 10px 5px; }
            .btn:hover { background: #0056b3; }
            .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin: 20px 0; }
            .stat-card { background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; text-align: center; }
            .stat-number { font-size: 24px; font-weight: 700; color: #007bff; }
            .stat-label { font-size: 12px; color: #6c757d; text-transform: uppercase; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . htmlspecialchars($title) . '</h1>
                <p>' . date('l, F j, Y') . '</p>
            </div>
            <div class="content">
                ' . $content . '
            </div>
            <div class="footer">
                ' . ($footerText ?: 'This is an automated message from your Reminder System.') . '
                <br><small>© ' . date('Y') . ' Reminder System. All rights reserved.</small>
            </div>
        </div>
    </body>
    </html>';
}

// Updated Morning summary email - respects send_empty_summaries setting
function sendMorningSummaryEmail() {
    // Check if morning summary is enabled
    if (getSetting('morning_summary_enabled', '1') !== '1') {
        return false;
    }
    global $dbh, $email_config;

    $today = date('Y-m-d');

    // Get today's reminders - simplified without recurring instances
    $todayQuery = "
    SELECT *
    FROM reminders r
    WHERE r.reminder_date = ? AND r.is_recurring = 0 AND r.is_completed = 0 AND r.is_dismissed = 0
    ORDER BY reminder_time ASC
    ";

    $stmt = $dbh->prepare($todayQuery);
    $stmt->execute([$today]);
    $todayReminders = $stmt->fetchAll();

    // Get overdue reminders - simplified
    $overdueQuery = "
    SELECT *
    FROM reminders r
    WHERE CONCAT(r.reminder_date, ' ', r.reminder_time) < NOW()
    AND r.is_recurring = 0 AND r.is_completed = 0 AND r.is_dismissed = 0
    ORDER BY reminder_date DESC, reminder_time DESC
    ";

    $stmt = $dbh->prepare($overdueQuery);
    $stmt->execute();
    $overdueReminders = $stmt->fetchAll();

    // Check if we should send empty summaries
    $sendEmptyEnabled = getSetting('send_empty_summaries', '1') === '1';
    $hasReminders = !empty($todayReminders) || !empty($overdueReminders);

    // Only send if we have reminders OR if empty summaries are enabled
    if (!$hasReminders && !$sendEmptyEnabled) {
        echo "Morning summary skipped - no reminders and empty summaries disabled\n";
        return false;
    }

    $content = '<h2 style="color: #2c3e50; margin-bottom: 20px;">Good Morning! Here\'s your reminder summary:</h2>';

    // Statistics
    $content .= '<div class="stats-grid">
    <div class="stat-card">
    <div class="stat-number">' . count($todayReminders) . '</div>
    <div class="stat-label">Due Today</div>
    </div>
    <div class="stat-card">
    <div class="stat-number" style="color: #dc3545;">' . count($overdueReminders) . '</div>
    <div class="stat-label">Overdue</div>
    </div>
    </div>';

    // Check if no reminders at all
    if (empty($todayReminders) && empty($overdueReminders)) {
        $content .= '<div style="text-align: center; padding: 40px 20px; background: #f8f9fa; border-radius: 8px; margin: 20px 0;">
        <h3 style="color: #28a745; margin-bottom: 15px;">🎉 All Clear!</h3>
        <p style="font-size: 16px; color: #6c757d; margin-bottom: 10px;">You have no reminders due today and no overdue items.</p>
        <p style="font-size: 14px; color: #6c757d;">Enjoy your free day! 😊</p>
        </div>';

        $content .= '<div style="text-align: center; margin-top: 30px;">
        <a href="https://web.monkbrian.com/sudo/reminders" class="btn">Add New Reminder</a>
        </div>';
    } else {
        // Today's reminders
        if (!empty($todayReminders)) {
            $content .= '<h3 style="color: #007bff; margin: 25px 0 15px 0;">📅 Due Today (' . count($todayReminders) . ')</h3>';
            foreach ($todayReminders as $reminder) {
                $priorityClass = $reminder['priority'];
                $content .= '<div class="reminder-card ' . $priorityClass . '">
                <div class="reminder-title">' . htmlspecialchars($reminder['title']) . '</div>
                <div class="reminder-meta">
                <span class="status-badge status-due-today">Due Today</span>
                <strong>Time:</strong> ' . date('g:i A', strtotime($reminder['reminder_time'])) . ' |
                <strong>Priority:</strong> ' . ucfirst($reminder['priority']) . ' |
                <strong>Category:</strong> ' . ucfirst($reminder['category']) . '
                </div>';
                if ($reminder['description']) {
                    $content .= '<div class="reminder-description">' . htmlspecialchars($reminder['description']) . '</div>';
                }
                $content .= '</div>';
            }
        }

        // Overdue reminders
        if (!empty($overdueReminders)) {
            $content .= '<h3 style="color: #dc3545; margin: 25px 0 15px 0;">⚠️ Overdue (' . count($overdueReminders) . ')</h3>';
            foreach ($overdueReminders as $reminder) {
                $daysOverdue = (new DateTime())->diff(new DateTime($reminder['reminder_date']))->days;
                $content .= '<div class="reminder-card overdue">
                <div class="reminder-title">' . htmlspecialchars($reminder['title']) . '</div>
                <div class="reminder-meta">
                <span class="status-badge status-overdue">Overdue</span>
                <strong>Was due:</strong> ' . date('M j, Y g:i A', strtotime($reminder['reminder_date'] . ' ' . $reminder['reminder_time'])) . '
                (' . $daysOverdue . ' day' . ($daysOverdue != 1 ? 's' : '') . ' ago) |
                <strong>Priority:</strong> ' . ucfirst($reminder['priority']) . '
                </div>';
                if ($reminder['description']) {
                    $content .= '<div class="reminder-description">' . htmlspecialchars($reminder['description']) . '</div>';
                }
                $content .= '</div>';
            }
        }

        $content .= '<div style="text-align: center; margin-top: 30px;">
        <a href="https://web.monkbrian.com/sudo/reminders" class="btn">View All Reminders</a>
        </div>';
    }

    $subject = 'Morning Reminder Summary - ' . date('F j, Y');
    $htmlBody = getModernEmailTemplate($subject, $content);

    return sendEmail($email_config['to_email'], $subject, $htmlBody);
}

// Updated Evening progress email - respects send_empty_summaries setting
function sendEveningProgressEmail() {
    // Check if evening progress is enabled
    if (getSetting('evening_progress_enabled', '1') !== '1') {
        return false;
    }
    global $dbh, $email_config;

    $today = date('Y-m-d');

    // Get today's reminders with completion status - simplified
    $todayQuery = "
    SELECT *
    FROM reminders r
    WHERE r.reminder_date = ? AND r.is_recurring = 0 AND r.is_dismissed = 0
    ORDER BY is_completed ASC, reminder_time ASC
    ";

    $stmt = $dbh->prepare($todayQuery);
    $stmt->execute([$today]);
    $todayReminders = $stmt->fetchAll();

    // Get overdue reminders - simplified
    $overdueQuery = "
    SELECT *
    FROM reminders r
    WHERE r.reminder_date < ? AND r.is_recurring = 0 AND r.is_completed = 0 AND r.is_dismissed = 0
    ORDER BY reminder_date DESC, reminder_time DESC
    ";

    $stmt = $dbh->prepare($overdueQuery);
    $stmt->execute([$today]);
    $overdueReminders = $stmt->fetchAll();

    // Check if we should send empty summaries
    $sendEmptyEnabled = getSetting('send_empty_summaries', '1') === '1';
    $hasReminders = !empty($todayReminders) || !empty($overdueReminders);

    // Only send if we have reminders OR if empty summaries are enabled
    if (!$hasReminders && !$sendEmptyEnabled) {
        echo "Evening progress skipped - no reminders and empty summaries disabled\n";
        return false;
    }

    $completedToday = array_filter($todayReminders, function($r) { return $r['is_completed']; });
    $incompleteToday = array_filter($todayReminders, function($r) { return !$r['is_completed']; });

    $completionRate = count($todayReminders) > 0 ? round((count($completedToday) / count($todayReminders)) * 100) : 100;

    $content = '<h2 style="color: #2c3e50; margin-bottom: 20px;">🌙 Evening Progress Report</h2>';

    // Progress statistics
    $content .= '<div class="stats-grid">
    <div class="stat-card">
    <div class="stat-number" style="color: #28a745;">' . count($completedToday) . '</div>
    <div class="stat-label">Completed</div>
    </div>
    <div class="stat-card">
    <div class="stat-number" style="color: #ffc107;">' . count($incompleteToday) . '</div>
    <div class="stat-label">Incomplete</div>
    </div>
    <div class="stat-card">
    <div class="stat-number" style="color: #dc3545;">' . count($overdueReminders) . '</div>
    <div class="stat-label">Overdue</div>
    </div>
    <div class="stat-card">
    <div class="stat-number" style="color: #007bff;">' . $completionRate . '%</div>
    <div class="stat-label">Completion Rate</div>
    </div>
    </div>';

    // Check if no reminders at all today
    if (empty($todayReminders) && empty($overdueReminders)) {
        $content .= '<div style="text-align: center; padding: 40px 20px; background: #f8f9fa; border-radius: 8px; margin: 20px 0;">
        <h3 style="color: #28a745; margin-bottom: 15px;">🌟 Perfect Day!</h3>
        <p style="font-size: 16px; color: #6c757d; margin-bottom: 10px;">You had no reminders scheduled today and no overdue items.</p>
        <p style="font-size: 14px; color: #6c757d;">Hope you had a relaxing and productive day! 🎯</p>
        </div>';

        $content .= '<div style="text-align: center; margin-top: 30px;">
        <a href="https://web.monkbrian.com/sudo/reminders" class="btn">Plan Tomorrow</a>
        </div>';
    } else {
        // Show completion message if all done
        if (!empty($todayReminders) && empty($incompleteToday) && empty($overdueReminders)) {
            $content .= '<div style="text-align: center; padding: 30px 20px; background: #d4edda; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;">
            <h3 style="color: #155724; margin-bottom: 15px;">🎉 Excellent Work!</h3>
            <p style="font-size: 16px; color: #155724; margin-bottom: 10px;">You completed all your reminders today with no overdue items!</p>
            <p style="font-size: 14px; color: #155724;">100% completion rate - you\'re crushing it! 💪</p>
            </div>';
        }

        // Completed reminders
        if (!empty($completedToday)) {
            $content .= '<h3 style="color: #28a745; margin: 25px 0 15px 0;">✅ Completed Today (' . count($completedToday) . ')</h3>';
            foreach ($completedToday as $reminder) {
                $content .= '<div class="reminder-card">
                <div class="reminder-title">✓ ' . htmlspecialchars($reminder['title']) . '</div>
                <div class="reminder-meta">
                <span class="status-badge status-completed">Completed</span>
                <strong>Time:</strong> ' . date('g:i A', strtotime($reminder['reminder_time'])) . ' |
                <strong>Priority:</strong> ' . ucfirst($reminder['priority']) . '
                </div>
                </div>';
            }
        }

        // Incomplete reminders
        if (!empty($incompleteToday)) {
            $content .= '<h3 style="color: #ffc107; margin: 25px 0 15px 0;">⏳ Still Pending Today (' . count($incompleteToday) . ')</h3>';
            foreach ($incompleteToday as $reminder) {
                $content .= '<div class="reminder-card">
                <div class="reminder-title">' . htmlspecialchars($reminder['title']) . '</div>
                <div class="reminder-meta">
                <span class="status-badge status-due-today">Pending</span>
                <strong>Time:</strong> ' . date('g:i A', strtotime($reminder['reminder_time'])) . ' |
                <strong>Priority:</strong> ' . ucfirst($reminder['priority']) . '
                </div>
                </div>';
            }
        }

        // Overdue reminders
        if (!empty($overdueReminders)) {
            $content .= '<h3 style="color: #dc3545; margin: 25px 0 15px 0;">⚠️ Still Overdue (' . count($overdueReminders) . ')</h3>';
            foreach (array_slice($overdueReminders, 0, 5) as $reminder) { // Show only first 5
                $daysOverdue = (new DateTime())->diff(new DateTime($reminder['reminder_date']))->days;
                $content .= '<div class="reminder-card overdue">
                <div class="reminder-title">' . htmlspecialchars($reminder['title']) . '</div>
                <div class="reminder-meta">
                <span class="status-badge status-overdue">Overdue</span>
                <strong>Was due:</strong> ' . date('M j', strtotime($reminder['reminder_date'])) . '
                (' . $daysOverdue . ' day' . ($daysOverdue != 1 ? 's' : '') . ' ago)
                </div>
                </div>';
            }
            if (count($overdueReminders) > 5) {
                $content .= '<p style="text-align: center; color: #6c757d; font-style: italic;">... and ' . (count($overdueReminders) - 5) . ' more overdue reminders</p>';
            }
        }

        $content .= '<div style="text-align: center; margin-top: 30px;">
        <a href="https://web.monkbrian.com/sudo/reminders" class="btn">Manage Reminders</a>
        </div>';
    }

    $subject = 'Evening Progress Report - ' . date('F j, Y');
    $htmlBody = getModernEmailTemplate($subject, $content);

    return sendEmail($email_config['to_email'], $subject, $htmlBody);
}

// Updated sendDueReminderEmail to include snooze information
function sendDueReminderEmail($reminder) {
    global $email_config;

    $content = '<h2 style="color: #dc3545; margin-bottom: 20px;">⏰ Reminder Due Now!</h2>';

    $priorityClass = $reminder['priority'];
    $priorityColor = $priorityClass === 'high' ? '#dc3545' : ($priorityClass === 'medium' ? '#ffc107' : '#28a745');

    // Add snooze information if applicable
    $snoozeInfo = '';
    if ($reminder['snooze_count'] > 0) {
        $snoozeInfo = '<div class="alert alert-info d-flex align-items-center mb-3" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <small>This reminder has been snoozed ' . $reminder['snooze_count'] . ' time(s). Last snoozed: ' .
            ($reminder['last_snooze_time'] ? date('M j, Y g:i A', strtotime($reminder['last_snooze_time'])) : 'Unknown') . '</small>
        </div>';
    }

    $content .= $snoozeInfo;

    $content .= '<div class="reminder-card ' . $priorityClass . '" style="border-left-color: ' . $priorityColor . ';">
        <div class="reminder-title">' . htmlspecialchars($reminder['title']) . '</div>
        <div class="reminder-meta">
            <span class="status-badge status-due-today">Due Now</span>
            <strong>Time:</strong> ' . date('g:i A', strtotime($reminder['reminder_time'])) . ' | 
            <strong>Priority:</strong> ' . ucfirst($reminder['priority']) . ' | 
            <strong>Category:</strong> ' . ucfirst($reminder['category']) . '
        </div>';

    if ($reminder['description']) {
        $content .= '<div class="reminder-description">' . htmlspecialchars($reminder['description']) . '</div>';
    }

    $content .= '</div>';

    $content .= '<div style="text-align: center; margin-top: 30px;">
        <a href="https://web.monkbrian.com/sudo/reminders" class="btn" style="margin-right: 10px;">Mark as Complete</a>
        <a href="https://web.monkbrian.com/sudo/reminders" class="btn" style="background-color: #ffc107; color: #212529;">Snooze Reminder</a>
    </div>';

    $subject = 'Reminder Due: ' . $reminder['title'];
    if ($reminder['snooze_count'] > 0) {
        $subject .= ' (Snoozed ' . $reminder['snooze_count'] . 'x)';
    }

    $htmlBody = getModernEmailTemplate($subject, $content);

    return sendEmail($email_config['to_email'], $subject, $htmlBody);
}

// New function to get snooze statistics for analytics
function getSnoozeAnalytics($days = 30) {
    global $dbh;

    $since = date('Y-m-d', strtotime("-{$days} days"));

    $query = "
        SELECT 
            COUNT(*) as total_snoozes,
            AVG(snooze_duration_minutes) as avg_duration,
            MAX(snooze_duration_minutes) as max_duration,
            MIN(snooze_duration_minutes) as min_duration,
            COUNT(DISTINCT reminder_id) as unique_reminders_snoozed
        FROM snooze_history 
        WHERE snooze_time >= ?
    ";

    $stmt = $dbh->prepare($query);
    $stmt->execute([$since]);
    $stats = $stmt->fetch();

    // Get most common snooze durations
    $durationQuery = "
        SELECT snooze_duration_minutes, COUNT(*) as count
        FROM snooze_history 
        WHERE snooze_time >= ?
        GROUP BY snooze_duration_minutes 
        ORDER BY count DESC 
        LIMIT 5
    ";

    $stmt = $dbh->prepare($durationQuery);
    $stmt->execute([$since]);
    $commonDurations = $stmt->fetchAll();

    return [
        'stats' => $stats,
        'common_durations' => $commonDurations,
        'period_days' => $days
    ];
}

// Due reminder notification - simplified without recurring reminders
function checkAndSendDueReminders() {
    // Check if due reminders are enabled
    if (getSetting('due_reminders_enabled', '1') !== '1') {
        echo "Due reminders are disabled.\n";
        return false;
    }

    global $dbh;

    $now = date('Y-m-d H:i:s');
    $sentCount = 0;
    $errorCount = 0;
    $unsnoozedCount = 0;

    try {
        // First, check for reminders that should be unsnoozed
        $unsnoozeQuery = "
            UPDATE reminders 
            SET is_snoozed = 0, snooze_until = NULL 
            WHERE is_snoozed = 1 AND snooze_until <= ?
        ";
        $stmt = $dbh->prepare($unsnoozeQuery);
        $stmt->execute([$now]);
        $unsnoozedCount += $stmt->rowCount();

        // Also unsnooze reminder instances
        $unsnoozeInstanceQuery = "
            UPDATE reminder_instances 
            SET is_snoozed = 0, snooze_until = NULL 
            WHERE is_snoozed = 1 AND snooze_until <= ?
        ";
        $stmt = $dbh->prepare($unsnoozeInstanceQuery);
        $stmt->execute([$now]);
        $unsnoozedCount += $stmt->rowCount();

        if ($unsnoozedCount > 0) {
            echo "Unsnoozed {$unsnoozedCount} reminders.\n";
        }

        // Get reminders that are due now (within the last 5 minutes to current time)
        // Exclude snoozed reminders and those that had emails sent recently
        $query = "
            SELECT * FROM reminders r
            WHERE CONCAT(r.reminder_date, ' ', r.reminder_time) BETWEEN DATE_SUB(?, INTERVAL 5 MINUTE) AND ?
            AND r.is_recurring = 0 
            AND r.is_completed = 0 
            AND r.is_dismissed = 0 
            AND (r.is_snoozed = 0 OR r.is_snoozed IS NULL)
            AND (r.last_email_sent IS NULL OR r.last_email_sent < DATE_SUB(NOW(), INTERVAL 30 MINUTE))
            ORDER BY r.reminder_date ASC, r.reminder_time ASC
        ";

        $stmt = $dbh->prepare($query);
        $stmt->execute([$now, $now]);
        $dueReminders = $stmt->fetchAll();

        echo "Found " . count($dueReminders) . " due reminders to process (excluding snoozed).\n";

        foreach ($dueReminders as $reminder) {
            try {
                // Double-check snooze status before sending
                if ($reminder['is_snoozed'] && $reminder['snooze_until'] && strtotime($reminder['snooze_until']) > time()) {
                    echo "Skipping snoozed reminder: " . $reminder['title'] . " (snoozed until " . $reminder['snooze_until'] . ")\n";
                    continue;
                }

                $result = sendDueReminderEmail($reminder);

                if ($result) {
                    echo "✓ Due reminder email sent for: " . $reminder['title'] . " (ID: " . $reminder['id'] . ")\n";

                    // Update the last_email_sent timestamp
                    $updateQuery = "UPDATE reminders SET last_email_sent = NOW() WHERE id = ?";
                    $updateStmt = $dbh->prepare($updateQuery);
                    $updateStmt->execute([$reminder['id']]);

                    $sentCount++;
                } else {
                    echo "✗ Failed to send email for: " . $reminder['title'] . " (ID: " . $reminder['id'] . ")\n";
                    $errorCount++;
                }
            } catch (Exception $e) {
                echo "✗ Error sending email for reminder ID " . $reminder['id'] . ": " . $e->getMessage() . "\n";
                error_log("Error sending due reminder email for ID " . $reminder['id'] . ": " . $e->getMessage());
                $errorCount++;
            }
        }

        echo "Summary: {$sentCount} emails sent, {$errorCount} errors, {$unsnoozedCount} reminders unsnoozed\n";

        return $sentCount > 0;

    } catch (Exception $e) {
        echo "Database error in checkAndSendDueReminders: " . $e->getMessage() . "\n";
        error_log("Database error in checkAndSendDueReminders: " . $e->getMessage());
        return false;
    }
}

?>





