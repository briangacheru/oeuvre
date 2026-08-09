<?php
require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/../db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../email-template.php';

// Enhanced features configuration with hour-based thresholds
$config = [
    'admin_email' => env('ADMIN_EMAIL'),
    'from_email' => env('MAIL_FROM_ADDRESS'),
    'from_name' => 'itasker',
    'company_logo' => 'https://web.monkbrian.com/assets/img/team/itasker-email-header2.png',
    'base_url' => 'https://web.monkbrian.com/sudo/',
    'alert_levels' => [
        'warning' => ['hours' => 3, 'color' => '#ff9800', 'priority' => 'Low'],
        'urgent' => ['hours' => 6, 'color' => '#f44336', 'priority' => 'Medium'],
        'critical' => ['hours' => 999, 'color' => '#d32f2f', 'priority' => 'High'] // Anything above 6 hours
    ]
];

// Function to calculate hours late
function calculateHoursLate($dueDate) {
    $due = new DateTime($dueDate);
    $now = new DateTime();

    if ($due >= $now) {
        return 0; // Not late
    }

    $interval = $now->diff($due);
    $hoursLate = ($interval->days * 24) + $interval->h + ($interval->i / 60);

    return round($hoursLate, 1);
}

// Function to determine alert level based on hours late
function getAlertLevel($hoursLate, $config) {
    if ($hoursLate <= 3) {
        return 'warning';
    } elseif ($hoursLate > 3 && $hoursLate <= 6) {
        return 'urgent';
    } else {
        return 'critical';
    }
}

// Function to format hours display
function formatHoursDisplay($hoursLate) {
    if ($hoursLate < 1) {
        return round($hoursLate * 60) . ' minutes';
    } elseif ($hoursLate < 24) {
        return round($hoursLate, 1) . ' hours';
    } else {
        $days = floor($hoursLate / 24);
        $remainingHours = round($hoursLate % 24, 1);
        if ($remainingHours == 0) {
            return $days . ' day' . ($days > 1 ? 's' : '');
        } else {
            return $days . ' day' . ($days > 1 ? 's' : '') . ', ' . $remainingHours . ' hours';
        }
    }
}

// Function to get priority color
function getPriorityColor($alertLevel, $config) {
    return $config['alert_levels'][$alertLevel]['color'];
}

// Function to get priority text
function getPriorityText($alertLevel, $config) {
    return $config['alert_levels'][$alertLevel]['priority'];
}

// Enhanced email sending function
function sendLateTaskEmail($lateTasksData, $config) {
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USER');
        $mail->Password = env('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) env('SMTP_PORT', 587);

        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($config['admin_email']);

        // Content
        $totalTasks = count($lateTasksData);
        $totalValue = array_sum(array_column($lateTasksData, 'total_value'));

        // Count tasks by priority
        $priorityCounts = ['warning' => 0, 'urgent' => 0, 'critical' => 0];
        foreach ($lateTasksData as $task) {
            $priorityCounts[$task['alert_level']]++;
        }

        $mail->isHTML(true);
        $mail->Subject = "Late Tasks Alert - {$totalTasks} Overdue Tasks";

        // Enhanced email body
        $mail->Body = generateEmailBody($lateTasksData, $config, $totalTasks, $totalValue, $priorityCounts);
        $mail->AltBody = generatePlainTextBody($lateTasksData, $totalTasks, $totalValue);

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Late task reminder email failed: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to generate HTML email body
function generateEmailBody($lateTasksData, $config, $totalTasks, $totalValue, $priorityCounts) {
    date_default_timezone_set('Africa/Nairobi');
    $currentDate = date('l, F j, Y \a\t g:i A');

    $thStyle = "background:#0073e6;color:white;padding:12px 8px;text-align:left;font-size:14px;";
    $tdStyle = "padding:12px 8px;border-bottom:1px solid #dee2e6;font-size:13px;";

    $body = "
                    <p style='text-align:center;color:#666;margin-top:-10px;'>{$currentDate}</p>
                    <table style='width:100%;border-collapse:collapse;margin-top:20px;'>
                        <thead>
                            <tr>
                                <th style='$thStyle'>Task ID</th>
                                <th style='$thStyle'>Topic</th>
                                <th style='$thStyle'>Writer</th>
                                <th style='$thStyle'>Late By</th>
                                <th style='$thStyle'>Priority</th>
                                <th style='$thStyle'>Action</th>
                            </tr>
                        </thead>
                        <tbody>";

    foreach ($lateTasksData as $task) {
        $priorityColor = getPriorityColor($task['alert_level'], $config);
        $priorityText = getPriorityText($task['alert_level'], $config);
        $taskUrl = $config['base_url'] . "view-task?task_id=" . encode_task_id($task['id']);

        $statusColor = match($task['status']) {
            'In Progress' => '#17a2b8',
            'Unconfirmed' => '#6c757d',
            'Draft' => '#dc3545',
            default => '#6c757d'
        };
        $hoursLateDisplay = formatHoursDisplay($task['hours_late']);

        $body .= "
        <tr>
            <td style='$tdStyle'><strong>#{$task['id']}</strong></td>
            <td style='$tdStyle max-width: 200px;'>" . htmlspecialchars(substr($task['topic'], 0, 50)) . (strlen($task['topic']) > 50 ? '...' : '') . "</td>
            <td style='$tdStyle'>" . htmlspecialchars($task['account']) . " - " . htmlspecialchars($task['writer']) . "</td>
            <td style='$tdStyle font-weight:bold;color:#d32f2f;'>{$hoursLateDisplay}</td>
            <td style='$tdStyle'><span style='padding:4px 8px;border-radius:12px;color:white;font-size:11px;font-weight:bold;text-transform:uppercase;background-color: {$priorityColor};'>{$priorityText}</span></td>
            <td style='$tdStyle'><a href='{$taskUrl}' class='btn' style='display:inline-block;margin:0;font-size: 11px; padding: 6px 12px;'>View Task</a></td>
        </tr>";
    }

    $body .= "
                        </tbody>
                    </table>

                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$config['base_url']}index' class='btn' style='display:inline-block;width:auto;margin:5px;font-size: 16px; padding: 12px 24px;'>📊 View Dashboard</a>
                        <a href='{$config['base_url']}all-tasks' class='btn' style='display:inline-block;width:auto;margin:5px;font-size: 16px; padding: 12px 24px;'>📋 Manage All Tasks</a>
                    </div>";

    return render_email_html('🚨 Late Tasks Alert', $body, null, null, "This is an automated message. For support, contact <a href='mailto:{$config['admin_email']}'>{$config['admin_email']}</a>");
}

// Function to generate plain text email body
function generatePlainTextBody($lateTasksData, $totalTasks, $totalValue) {
    $body = "LATE TASKS ALERT - " . date('Y-m-d H:i:s') . "\n";
    $body .= str_repeat("=", 50) . "\n\n";
    $body .= "Summary:\n";
    $body .= "- Total Late Tasks: {$totalTasks}\n";
    $body .= "- Total Value: Ksh. " . number_format($totalValue) . "\n\n";
    $body .= "Priority Breakdown:\n";
    $body .= "- Low Priority (≤3 hrs late): " . count(array_filter($lateTasksData, fn($t) => $t['alert_level'] === 'warning')) . "\n";
    $body .= "- Medium Priority (3-6 hrs late): " . count(array_filter($lateTasksData, fn($t) => $t['alert_level'] === 'urgent')) . "\n";
    $body .= "- High Priority (>6 hrs late): " . count(array_filter($lateTasksData, fn($t) => $t['alert_level'] === 'critical')) . "\n\n";
    $body .= "Late Tasks Details:\n";
    $body .= str_repeat("-", 50) . "\n";

    foreach ($lateTasksData as $task) {
        $hoursLateDisplay = formatHoursDisplay($task['hours_late']);
        $body .= "Task ID: #{$task['id']}\n";
        $body .= "Topic: {$task['topic']}\n";
        $body .= "Writer: {$task['writer']}\n";
        $body .= "Due Date: " . date('M j, Y g:i A', strtotime($task['due_date'])) . "\n";
        $body .= "Time Late: {$hoursLateDisplay}\n";
        $body .= "Priority: " . getPriorityText($task['alert_level'], []) . "\n";
        $body .= "Status: {$task['status']}\n";
        $body .= "Value: Ksh. " . number_format($task['total_value']) . "\n";
        $body .= str_repeat("-", 30) . "\n";
    }

    return $body;
}

// Function to log reminder activity
function logActivity($message, $logFile = __DIR__ . '/late_tasks_log.txt') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Function to send SMS notification (optional - requires SMS service)
function sendSMSAlert($phoneNumber, $message) {
    // Implement SMS sending logic here using services like Twilio, Africa's Talking, etc.
    // This is a placeholder for SMS functionality
    logActivity("SMS alert would be sent to {$phoneNumber}: {$message}");
}

// Main execution
try {
    $currentDateTime = new DateTime();

    // Enhanced query to get late tasks with hour calculations
    $sql = "SELECT t.id, t.topic, t.subject, t.account, t.writer, t.email, t.due_date, 
                   t.pages, t.cpp, t.status, t.create_date, t.is_confirmed,
                   (t.pages * t.cpp) as total_value,
                   TIMESTAMPDIFF(HOUR, t.due_date, NOW()) as hours_late_int,
                   TIMESTAMPDIFF(MINUTE, t.due_date, NOW()) as minutes_late_total
            FROM tbltasks t 
            WHERE t.due_date < NOW() 
            AND t.status IN ('In Progress', 'Unconfirmed', 'Draft') 
            AND t.is_confirmed != 2
            ORDER BY hours_late_int DESC, total_value DESC";

    $result = mysqli_query($con, $sql);

    if (mysqli_num_rows($result) > 0) {
        $lateTasksData = [];
        $totalValue = 0;

        while ($row = mysqli_fetch_array($result)) {
            // Calculate precise hours late (including minutes as decimal)
            $hoursLate = $row['minutes_late_total'] / 60;
            $alertLevel = getAlertLevel($hoursLate, $config);

            $taskData = [
                'id' => $row['id'],
                'topic' => $row['topic'],
                'subject' => $row['subject'],
                'account' => $row['account'],
                'writer' => $row['writer'],
                'email' => $row['email'],
                'due_date' => $row['due_date'],
                'pages' => $row['pages'],
                'cpp' => $row['cpp'],
                'status' => $row['status'],
                'hours_late' => $hoursLate,
                'total_value' => $row['total_value'],
                'alert_level' => $alertLevel
            ];

            $lateTasksData[] = $taskData;
            $totalValue += $row['total_value'];
        }

        // Send email notification
        if (sendLateTaskEmail($lateTasksData, $config)) {
            $message = "Late task reminder sent successfully. Found " . count($lateTasksData) . " late tasks worth Ksh. " . number_format($totalValue);
            echo $message . "\n";
            logActivity($message);

            // Optional: Send SMS for critical tasks (>6 hours late)
            $criticalTasks = array_filter($lateTasksData, function($task) {
                return $task['alert_level'] === 'critical';
            });

            if (!empty($criticalTasks)) {
                $smsMessage = "URGENT: " . count($criticalTasks) . " critical tasks are >6 hours overdue. Check email for details.";
                // sendSMSAlert('+254700000000', $smsMessage); // Uncomment and add your phone number
                logActivity("Critical tasks alert: " . count($criticalTasks) . " tasks >6 hours late");
            }

        } else {
            $errorMessage = "Failed to send late task reminder email";
            echo $errorMessage . "\n";
            logActivity($errorMessage);
        }

    } else {
        $message = "No late tasks found";
        echo $message . "\n";
        logActivity($message);
    }

} catch (Exception $e) {
    $errorMessage = "Error in late task reminder: " . $e->getMessage();
    echo $errorMessage . "\n";
    logActivity($errorMessage);
    error_log($errorMessage);
}

mysqli_close($con);
?>