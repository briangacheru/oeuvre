<?php
require_once __DIR__ . '/env.php';
require_once 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

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

    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                padding: 10px;
                margin: 0;
            }
            .email-container {
                max-width: 800px;
                background: #ffffff;
                margin: 0 auto;
                border-radius: 8px;
                box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
             .email-head {
                text-align: center;
                background: white;
            }
            .email-head img {
                max-width: 800px;
                height: auto;
            }
            
            .summary-section {
                background: #f8f9fa;
                padding: 20px;
                border-bottom: 1px solid #dee2e6;
            }
            .summary-cards {
                display: flex;
                justify-content: space-around;
                flex-wrap: wrap;
                gap: 15px;
            }
            .summary-card {
                background: white;
                padding: 15px;
                border-radius: 8px;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                min-width: 120px;
            }
            .summary-card h3 {
                margin: 0 0 5px 0;
                font-size: 24px;
                font-weight: bold;
            }
            .summary-card p {
                margin: 0;
                color: #666;
                font-size: 12px;
            }
            .email-content {
                padding: 20px;
            }
            .task-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .task-table th {
                background: #0073e6;
                color: white;
                padding: 12px 8px;
                text-align: left;
                font-size: 14px;
            }
            .task-table td {
                padding: 12px 8px;
                border-bottom: 1px solid #dee2e6;
                font-size: 13px;
            }
            .task-table tr:hover {
                background-color: #f8f9fa;
            }
            .priority-badge {
                padding: 4px 8px;
                border-radius: 12px;
                color: white;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
            }
            .status-badge {
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: bold;
            }
            .hours-late {
                font-weight: bold;
                color: #d32f2f;
            }
            .btn {
                display: inline-block;
                background: #0073e6;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                text-decoration: none;
                font-weight: bold;
                margin: 5px;
            }
            .btn:hover {
                background: #005bb5;
                color: white !important;
            }
            .footer {
                text-align: center;
                padding: 20px;
                background: #f8f9fa;
                color: #666;
                font-size: 12px;
            }
            .alert-section {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 5px;
                padding: 15px;
                margin: 20px 0;
            }
            .time-breakdown {
                background: #e3f2fd;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
            @media (max-width: 600px) {
                .summary-cards {
                    flex-direction: column;
                }
                .task-table {
                    font-size: 11px;
                }
                .task-table th, .task-table td {
                    padding: 8px 4px;
                }
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='email-head'>
                <img src='{$config['company_logo']}' alt='itasker Logo'>
            </div>
            <div class='email-header'>
                        <h1>🚨 Late Tasks Alert</h1>
                        <p style='margin: 5px 0 0 0; opacity: 0.9;'>{$currentDate}</p>
            </div>

            <div class='email-content'>
                
                <table class='task-table'>
                    <thead>
                        <tr>
                            <th>Task ID</th>
                            <th>Topic</th>
                            <th>Writer</th>
                            <th>Late By</th>
                            <th>Priority</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>";

    foreach ($lateTasksData as $task) {
        $priorityColor = getPriorityColor($task['alert_level'], $config);
        $priorityText = getPriorityText($task['alert_level'], $config);
        $taskUrl = $config['base_url'] . "view-task?task_id=" . base64_encode($task['id']);

        $statusColor = match($task['status']) {
            'In Progress' => '#17a2b8',
            'Unconfirmed' => '#6c757d',
            'Draft' => '#dc3545',
            default => '#6c757d'
        };
        $hoursLateDisplay = formatHoursDisplay($task['hours_late']);

        $body .= "
        <tr>
            <td><strong>#{$task['id']}</strong></td>
            <td style='max-width: 200px;'>" . htmlspecialchars(substr($task['topic'], 0, 50)) . (strlen($task['topic']) > 50 ? '...' : '') . "</td>
            <td>" . htmlspecialchars($task['account']) . " - " . htmlspecialchars($task['writer']) . "</td>
            <td class='hours-late'>{$hoursLateDisplay}</td>
            <td><span class='priority-badge' style='background-color: {$priorityColor};'>{$priorityText}</span></td>
            <td><a href='{$taskUrl}' class='btn' style='font-size: 11px; padding: 6px 12px;'>View Task</a></td>
        </tr>";
    }

    $body .= "
                    </tbody>
                </table>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$config['base_url']}index' class='btn' style='font-size: 16px; padding: 12px 24px;'>📊 View Dashboard</a>
                    <a href='{$config['base_url']}all-tasks' class='btn' style='font-size: 16px; padding: 12px 24px;'>📋 Manage All Tasks</a>
                </div>
              
            </div>

            <div class='footer'>
                <p><strong>itasker Automated Reminder System</strong></p>
                <p>This is an automated message. For support, contact <a href='mailto:{$config['admin_email']}'>{$config['admin_email']}</a></p>
                <p>&copy; " . date('Y') . " itasker. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";

    return $body;
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
function logActivity($message, $logFile = 'late_tasks_log.txt') {
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