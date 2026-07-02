<?php
require_once 'reminder_functions.php';

// Check what type of cron job to run
$action = $argv[1] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'morning_summary':
        if (date('H') == '08') { // 8 AM
            $result = sendMorningSummaryEmail();
            echo $result ? "Morning summary email sent\n" : "Failed to send morning summary\n";
        }
        break;

    case 'evening_progress':
        if (date('H') == '23') {
            $result = sendEveningProgressEmail();
            echo $result ? "Evening summary email sent\n" : "Failed to send evening summary\n";
        }
        break;

    case 'due_reminders':
        checkAndSendDueReminders();
        echo "Due reminder checks completed\n";
        break;

    case 'all':
        // Run all checks
        if (date('H') == '08') {
            $result = sendMorningSummaryEmail();
            echo $result ? "Morning summary email sent\n" : "Failed to send morning summary\n";
        }
        if (date('H') == '23') {
            $result = sendEveningProgressEmail();
            echo $result ? "Evening summary email sent\n" : "Failed to send evening summary\n";
        }
        checkAndSendDueReminders();
        break;

    default:
        echo "Usage: php reminders_cron.php [morning_summary|evening_progress|due_reminders|all]\n";
}

?>