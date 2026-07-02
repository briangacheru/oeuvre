<?php
date_default_timezone_set('Africa/Nairobi');
require_once 'dbcon.php';
header('Content-Type: application/json');
// Get current time
$now = new DateTime();
$current_time = $now->format('Y-m-d H:i:s');

// Check for due reminders (within the next 5 minutes)
$sql = "SELECT r.*, 
        CONCAT(r.reminder_date, ' ', r.reminder_time) as full_datetime,
        TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(r.reminder_date, ' ', r.reminder_time)) as minutes_until_due
        FROM reminders r 
        WHERE r.is_completed = 0 
        AND r.is_dismissed = 0 
        AND CONCAT(r.reminder_date, ' ', r.reminder_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 5 MINUTE)
        ORDER BY r.reminder_date ASC, r.reminder_time ASC";

$stmt = $dbh->prepare($sql);
$stmt->execute();
$due_reminders = $stmt->fetchAll();

// Also check reminder instances for recurring reminders
$sql_instances = "SELECT ri.*, r.title, r.description, r.priority, r.category,
                  CONCAT(ri.instance_date, ' ', ri.instance_time) as full_datetime,
                  TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(ri.instance_date, ' ', ri.instance_time)) as minutes_until_due
                  FROM reminder_instances ri
                  JOIN reminders r ON ri.reminder_id = r.id
                  WHERE ri.is_completed = 0 
                  AND ri.is_dismissed = 0 
                  AND CONCAT(ri.instance_date, ' ', ri.instance_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 5 MINUTE)
                  ORDER BY ri.instance_date ASC, ri.instance_time ASC";

$stmt_instances = $dbh->prepare($sql_instances);
$stmt_instances->execute();
$due_instances = $stmt_instances->fetchAll();

// Combine results
$all_due = array_merge($due_reminders, $due_instances);

// Format response
$response = [];
foreach ($all_due as $reminder) {
    $response[] = [
        'id' => $reminder['id'],
        'title' => $reminder['title'],
        'description' => $reminder['description'] ?? '',
        'priority' => $reminder['priority'],
        'category' => $reminder['category'],
        'datetime' => $reminder['full_datetime'],
        'minutes_until_due' => $reminder['minutes_until_due']
    ];
}

echo json_encode($response);
?>