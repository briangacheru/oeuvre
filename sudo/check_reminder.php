<?php
date_default_timezone_set('Africa/Nairobi');
require_once 'dbcon.php';
header('Content-Type: application/json');

try {
    // Get current time
    $now = new DateTime();

    // First, get NON-recurring reminders only
    $sql1 = "SELECT id, title, description, reminder_date, reminder_time, priority, category, 
                    is_recurring, is_completed, is_dismissed, advance_notification, email_frequency,
                    'reminder' as source_type, id as source_id
             FROM reminders 
             WHERE is_completed = 0 
             AND is_dismissed = 0 
             AND is_recurring = 0
             AND CONCAT(reminder_date, ' ', reminder_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 10 HOUR)";

    // Second, get reminder instances for recurring reminders ONLY
    $sql2 = "SELECT r.id, r.title, r.description, ri.instance_date as reminder_date, 
                    ri.instance_time as reminder_time, r.priority, r.category, 
                    r.is_recurring, ri.is_completed, ri.is_dismissed, r.advance_notification, 
                    r.email_frequency, 'instance' as source_type, ri.id as source_id
             FROM reminders r
             INNER JOIN reminder_instances ri ON r.id = ri.reminder_id
             WHERE ri.is_completed = 0 
             AND ri.is_dismissed = 0 
             AND r.is_recurring = 1
             AND CONCAT(ri.instance_date, ' ', ri.instance_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 10 HOUR)";

    // Combine both queries
    $sql = "($sql1) UNION ($sql2) ORDER BY reminder_date ASC, reminder_time ASC";

    $stmt = $dbh->prepare($sql);
    $stmt->execute();
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter reminders that are actually within 10 hours and remove any potential duplicates
    $urgentReminders = [];
    $seenReminders = []; // Track to avoid duplicates

    foreach ($reminders as $reminder) {
        $reminderDateTime = new DateTime($reminder['reminder_date'] . ' ' . $reminder['reminder_time']);
        $timeDiff = $reminderDateTime->getTimestamp() - $now->getTimestamp();
        $hoursRemaining = $timeDiff / 3600; // Convert to hours

        // Only include reminders with less than 10 hours remaining and more than 0 (not overdue)
        if ($hoursRemaining <= 10 && $hoursRemaining > 0) {
            // Create a unique key to prevent duplicates
            $uniqueKey = $reminder['id'] . '_' . $reminder['reminder_date'] . '_' . $reminder['reminder_time'] . '_' . $reminder['source_type'];

            if (!isset($seenReminders[$uniqueKey])) {
                $reminder['hours_remaining'] = $hoursRemaining;
                $reminder['is_instance'] = ($reminder['source_type'] === 'instance');
                $reminder['instance_id'] = $reminder['source_type'] === 'instance' ? $reminder['source_id'] : null;
                $urgentReminders[] = $reminder;
                $seenReminders[$uniqueKey] = true;
            }
        }
    }

    echo json_encode($urgentReminders);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>