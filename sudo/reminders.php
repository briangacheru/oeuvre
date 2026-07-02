<?php
include('check-login.php');
function formatSnoozeDuration($minutes) {
    if ($minutes < 60) {
        return $minutes . ' minutes';
    } elseif ($minutes < 1440) {
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        $text = $hours . ' hour' . ($hours > 1 ? 's' : '');
        if ($remainingMinutes > 0) {
            $text .= ' ' . $remainingMinutes . ' min';
        }
        return $text;
    } else {
        $days = floor($minutes / 1440);
        $remainingHours = floor(($minutes % 1440) / 60);
        $text = $days . ' day' . ($days > 1 ? 's' : '');
        if ($remainingHours > 0) {
            $text .= ' ' . $remainingHours . ' hour' . ($remainingHours > 1 ? 's' : '');
        }
        return $text;
    }
}

function getSnoozeDurationIcon($minutes) {
    if ($minutes <= 30) return 'fas fa-clock';
    if ($minutes <= 120) return 'fas fa-hourglass-half';
    if ($minutes <= 480) return 'fas fa-sun';
    return 'fas fa-calendar-day';
}

// Update the getSetting function to include in reminder_functions.php if not already there
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

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'add_reminder':
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $advance_notification = (int)$_POST['advance_notification'];
            $email_frequency = $_POST['email_frequency'];
            $priority = $_POST['priority'];
            $category = sanitize($_POST['category']);
            $reminder_type = $_POST['reminder_type'];

            try {
                $dbh->beginTransaction();

                if ($reminder_type === 'multiple_days') {
                    // Handle multiple days selection
                    $selected_dates = json_decode($_POST['selected_dates'], true);
                    $reminder_time = $_POST['reminder_time']; // This should be set by JavaScript

                    if (empty($selected_dates)) {
                        throw new Exception('Please select at least one date');
                    }

                    foreach ($selected_dates as $date) {
                        $stmt = $dbh->prepare('INSERT INTO reminders (title, description, reminder_date, reminder_time, advance_notification, email_frequency, priority, category, is_recurring) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)');
                        $stmt->execute([$title, $description, $date, $reminder_time, $advance_notification, $email_frequency, $priority, $category]);
                    }

                    $message = 'Reminder added for ' . count($selected_dates) . ' days!';

                } elseif ($reminder_type === 'recurring') {
                    // Handle recurring reminders
                    $reminder_date = $_POST['reminder_date'];
                    $reminder_time = $_POST['reminder_time']; // This should be set by JavaScript
                    $recurring_days = (int)$_POST['recurring_days'];
                    $end_date = $_POST['end_date'] ?: null;

                    $stmt = $dbh->prepare('INSERT INTO reminders (title, description, reminder_date, reminder_time, is_recurring, recurring_days, end_date, advance_notification, email_frequency, priority, category) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$title, $description, $reminder_date, $reminder_time, $recurring_days, $end_date, $advance_notification, $email_frequency, $priority, $category]);

                    $reminder_id = $dbh->lastInsertId();

                    // Generate recurring instances
                    if ($recurring_days > 0) {
                        $current_date = new DateTime($reminder_date);
                        $end_date_obj = $end_date ? new DateTime($end_date) : new DateTime('+1 year');

                        while ($current_date <= $end_date_obj) {
                            $stmt = $dbh->prepare('INSERT INTO reminder_instances (reminder_id, instance_date, instance_time) VALUES (?, ?, ?)');
                            $stmt->execute([$reminder_id, $current_date->format('Y-m-d'), $reminder_time]);
                            $current_date->add(new DateInterval('P' . $recurring_days . 'D'));
                        }
                    }

                    $message = 'Recurring reminder added!';

                } else {
                    // Handle single day reminder
                    $reminder_date = $_POST['reminder_date'];
                    $reminder_time = $_POST['reminder_time']; // This should be set by JavaScript from single_time field

                    $stmt = $dbh->prepare('INSERT INTO reminders (title, description, reminder_date, reminder_time, advance_notification, email_frequency, priority, category, is_recurring) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)');
                    $stmt->execute([$title, $description, $reminder_date, $reminder_time, $advance_notification, $email_frequency, $priority, $category]);

                    $message = 'Reminder added!';
                }

                $dbh->commit();
                echo json_encode(['success' => true, 'message' => $message]);

            } catch (Exception $e) {
                $dbh->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'complete_reminder':
            $id = (int)$_POST['id'];
            $stmt = $dbh->prepare('UPDATE reminders SET is_completed = 1 WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            exit;

        case 'quick_add_reminder':
            // Lightweight inline add: title + date + time + category only
            try {
                $title = sanitize($_POST['title']);
                if ($title === '') {
                    throw new Exception('Title is required');
                }
                $reminder_date = $_POST['reminder_date'] ?: date('Y-m-d');
                $reminder_time = $_POST['reminder_time'] ?: '09:00';
                $category = sanitize($_POST['category'] ?? 'general');
                $priority = $_POST['priority'] ?? 'medium';

                $stmt = $dbh->prepare('INSERT INTO reminders (title, description, reminder_date, reminder_time, advance_notification, email_frequency, priority, category, is_recurring) VALUES (?, "", ?, ?, 0, "none", ?, ?, 0)');
                $stmt->execute([$title, $reminder_date, $reminder_time, $priority, $category]);
                $newId = $dbh->lastInsertId();

                echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Reminder added!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'reschedule_reminder':
            // For drag-to-reschedule from the list
            try {
                $id = (int)$_POST['id'];
                $new_date = $_POST['new_date'];
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
                    throw new Exception('Invalid date');
                }
                $stmt = $dbh->prepare('UPDATE reminders SET reminder_date = ? WHERE id = ?');
                $stmt->execute([$new_date, $id]);
                echo json_encode(['success' => true, 'message' => 'Rescheduled!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_reminder':
            $id = (int)$_POST['id'];
            $stmt = $dbh->prepare('SELECT * FROM reminders WHERE id = ?');
            $stmt->execute([$id]);
            $reminder = $stmt->fetch();

            // If it's a recurring reminder, get related instances
            $instances = [];
            if ($reminder && $reminder['is_recurring']) {
                $instanceStmt = $dbh->prepare('SELECT * FROM reminder_instances WHERE reminder_id = ? ORDER BY instance_date ASC');
                $instanceStmt->execute([$id]);
                $instances = $instanceStmt->fetchAll();
            }

            echo json_encode([
                'success' => true,
                'reminder' => $reminder,
                'instances' => $instances
            ]);
            exit;

        case 'check_multiple_days':
            $title = sanitize($_POST['title']);
            $time = $_POST['time'];
            $category = sanitize($_POST['category']);
            $exclude_id = (int)$_POST['exclude_id'];

            $stmt = $dbh->prepare('SELECT COUNT(*) FROM reminders WHERE title = ? AND reminder_time = ? AND category = ? AND id != ? AND is_recurring = 0');
            $stmt->execute([$title, $time, $category, $exclude_id]);
            $count = $stmt->fetchColumn();

            echo json_encode(['success' => true, 'count' => $count]);
            exit;

        case 'update_reminder':
            $id = (int)$_POST['id'];
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $reminder_date = $_POST['reminder_date'];
            $reminder_time = $_POST['reminder_time'];
            $priority = $_POST['priority'];
            $category = sanitize($_POST['category']);
            $advance_notification = (int)$_POST['advance_notification'];
            $email_frequency = $_POST['email_frequency'];

            try {
                $dbh->beginTransaction();

                // First, get the current reminder to check its type
                $stmt = $dbh->prepare('SELECT * FROM reminders WHERE id = ?');
                $stmt->execute([$id]);
                $current_reminder = $stmt->fetch();

                if (!$current_reminder) {
                    throw new Exception('Reminder not found');
                }

                // Check if this is a recurring reminder
                if ($current_reminder['is_recurring']) {
                    $dbh->rollBack();
                    echo json_encode([
                        'success' => false,
                        'message' => 'This is a recurring reminder. Please use the recurring reminder update function.',
                        'reminder_type' => 'recurring'
                    ]);
                    exit;
                }

                // Check if this is part of a multiple-day reminder set
                $stmt = $dbh->prepare('
                SELECT COUNT(*) as count FROM reminders 
                WHERE title = ? 
                AND reminder_time = ? 
                AND is_recurring = 0 
                AND ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) <= 60
            ');
                $stmt->execute([
                    $current_reminder['title'],
                    $current_reminder['reminder_time'],
                    $current_reminder['created_at']
                ]);
                $related_count = $stmt->fetchColumn();

                if ($related_count > 1) {
                    $dbh->rollBack();
                    echo json_encode([
                        'success' => false,
                        'message' => 'This is part of a multiple-day reminder set. Please use the multiple-day reminder update function.',
                        'reminder_type' => 'multiple_days'
                    ]);
                    exit;
                }

                // This is a single reminder, proceed with update
                $stmt = $dbh->prepare('
                UPDATE reminders 
                SET title = ?, description = ?, reminder_date = ?, reminder_time = ?, 
                    priority = ?, category = ?, advance_notification = ?, email_frequency = ?, 
                    updated_at = NOW() 
                WHERE id = ? AND is_recurring = 0
            ');

                $result = $stmt->execute([
                    $title, $description, $reminder_date, $reminder_time,
                    $priority, $category, $advance_notification, $email_frequency, $id
                ]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('No reminder was updated. Please check if the reminder exists and is not recurring.');
                }

                $dbh->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Single reminder updated!',
                    'reminder_type' => 'single'
                ]);

            } catch (Exception $e) {
                $dbh->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'update_recurring_reminder':
            $id = (int)$_POST['id'];
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $reminder_date = $_POST['reminder_date'] ?? $_POST['recurring_start_date']; // Handle both field names
            $reminder_time = $_POST['reminder_time'] ?? $_POST['recurring_time']; // Handle both field names
            $priority = $_POST['priority'];
            $category = sanitize($_POST['category']);
            $advance_notification = (int)$_POST['advance_notification'];
            $email_frequency = $_POST['email_frequency'];
            $recurring_days = (int)$_POST['recurring_days'];
            $end_date = $_POST['end_date'] ?: null;

            try {
                $dbh->beginTransaction();

                // Update the main recurring reminder
                $stmt = $dbh->prepare('UPDATE reminders SET title = ?, description = ?, reminder_date = ?, reminder_time = ?, priority = ?, category = ?, advance_notification = ?, email_frequency = ?, recurring_days = ?, end_date = ? WHERE id = ?');
                $stmt->execute([$title, $description, $reminder_date, $reminder_time, $priority, $category, $advance_notification, $email_frequency, $recurring_days, $end_date, $id]);

                // Delete existing instances
                $stmt = $dbh->prepare('DELETE FROM reminder_instances WHERE reminder_id = ?');
                $stmt->execute([$id]);

                // Regenerate instances with new settings
                if ($recurring_days > 0) {
                    $current_date = new DateTime($reminder_date);
                    $end_date_obj = $end_date ? new DateTime($end_date) : new DateTime('+1 year');

                    while ($current_date <= $end_date_obj) {
                        $stmt = $dbh->prepare('INSERT INTO reminder_instances (reminder_id, instance_date, instance_time) VALUES (?, ?, ?)');
                        $stmt->execute([$id, $current_date->format('Y-m-d'), $reminder_time]);
                        $current_date->add(new DateInterval('P' . $recurring_days . 'D'));
                    }
                }

                $dbh->commit();
                echo json_encode(['success' => true, 'message' => 'Recurring reminder updated!']);
            } catch (Exception $e) {
                $dbh->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'update_multiple_days_reminder':
            $id = (int)$_POST['id'];
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $reminder_time = $_POST['reminder_time'];
            $priority = $_POST['priority'];
            $category = sanitize($_POST['category']);
            $advance_notification = (int)$_POST['advance_notification'];
            $email_frequency = $_POST['email_frequency'];
            $selected_dates = json_decode($_POST['selected_dates'], true);

            if (empty($selected_dates)) {
                echo json_encode(['success' => false, 'message' => 'Please select at least one date']);
                exit;
            }

            try {
                $dbh->beginTransaction();

                // Get all reminders that belong to this multiple-day group
                // We'll use the original reminder's title and creation time to identify the group
                $stmt = $dbh->prepare('SELECT * FROM reminders WHERE id = ?');
                $stmt->execute([$id]);
                $original_reminder = $stmt->fetch();

                if (!$original_reminder) {
                    throw new Exception('Reminder not found');
                }

                // Find all reminders with the same title, time, and similar creation time (within 1 minute)
                // This helps identify reminders that were created together as a multiple-day set
                $stmt = $dbh->prepare('
                SELECT id FROM reminders 
                WHERE title = ? 
                AND reminder_time = ? 
                AND is_recurring = 0 
                AND ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) <= 60
            ');
                $stmt->execute([
                    $original_reminder['title'],
                    $original_reminder['reminder_time'],
                    $original_reminder['created_at']
                ]);
                $related_reminders = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Delete all related reminders
                if (!empty($related_reminders)) {
                    $placeholders = str_repeat('?,', count($related_reminders) - 1) . '?';
                    $stmt = $dbh->prepare("DELETE FROM reminders WHERE id IN ($placeholders)");
                    $stmt->execute($related_reminders);
                }

                // Create new reminders for selected dates
                foreach ($selected_dates as $date) {
                    $stmt = $dbh->prepare('INSERT INTO reminders (title, description, reminder_date, reminder_time, advance_notification, email_frequency, priority, category, is_recurring, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())');
                    $stmt->execute([$title, $description, $date, $reminder_time, $advance_notification, $email_frequency, $priority, $category]);
                }

                $dbh->commit();
                echo json_encode(['success' => true, 'message' => 'Multiple-day reminder updated!']);
            } catch (Exception $e) {
                $dbh->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'get_reminder_details':
            $id = (int)$_POST['id'];

            try {
                // Get the main reminder
                $stmt = $dbh->prepare('SELECT * FROM reminders WHERE id = ?');
                $stmt->execute([$id]);
                $reminder = $stmt->fetch();

                if (!$reminder) {
                    echo json_encode(['success' => false, 'message' => 'Reminder not found']);
                    exit;
                }

                $response = [
                    'success' => true,
                    'reminder' => $reminder,
                    'type' => 'single'
                ];

                // Check if it's a recurring reminder
                if ($reminder['is_recurring']) {
                    $response['type'] = 'recurring';

                    // Get instances
                    $stmt = $dbh->prepare('SELECT * FROM reminder_instances WHERE reminder_id = ? ORDER BY instance_date ASC');
                    $stmt->execute([$id]);
                    $response['instances'] = $stmt->fetchAll();
                } else {
                    // Check if it's part of a multiple-day reminder set
                    $stmt = $dbh->prepare('
                    SELECT id, reminder_date FROM reminders 
                    WHERE title = ? 
                    AND reminder_time = ? 
                    AND is_recurring = 0 
                    AND ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) <= 60
                    ORDER BY reminder_date ASC
                ');
                    $stmt->execute([
                        $reminder['title'],
                        $reminder['reminder_time'],
                        $reminder['created_at']
                    ]);
                    $related_reminders = $stmt->fetchAll();

                    if (count($related_reminders) > 1) {
                        $response['type'] = 'multiple_days';
                        $response['related_reminders'] = $related_reminders;
                        $response['selected_dates'] = array_column($related_reminders, 'reminder_date');
                    }
                }

                echo json_encode($response);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'check_multiple_days_reminder':
            $id = (int)$_POST['id'];

            try {
                $stmt = $dbh->prepare('SELECT * FROM reminders WHERE id = ?');
                $stmt->execute([$id]);
                $reminder = $stmt->fetch();

                if (!$reminder) {
                    echo json_encode(['success' => false, 'message' => 'Reminder not found']);
                    exit;
                }

                // Check for related reminders (multiple days)
                $stmt = $dbh->prepare('
                SELECT id, reminder_date FROM reminders 
                WHERE title = ? 
                AND reminder_time = ? 
                AND is_recurring = 0 
                AND ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) <= 60
                ORDER BY reminder_date ASC
            ');
                $stmt->execute([
                    $reminder['title'],
                    $reminder['reminder_time'],
                    $reminder['created_at']
                ]);
                $related_reminders = $stmt->fetchAll();

                echo json_encode([
                    'success' => true,
                    'is_multiple_days' => count($related_reminders) > 1,
                    'related_reminders' => $related_reminders,
                    'selected_dates' => array_column($related_reminders, 'reminder_date')
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        case 'dismiss_reminder':
            $id = (int)$_POST['id'];
            $stmt = $dbh->prepare('UPDATE reminders SET is_dismissed = 1 WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            exit;

        case 'dismiss_reminder_instance':
            $instance_id = (int)$_POST['instance_id'];
            $stmt = $dbh->prepare('UPDATE reminder_instances SET is_dismissed = 1 WHERE id = ?');
            $stmt->execute([$instance_id]);
            echo json_encode(['success' => true, 'message' => 'Reminder instance dismissed!']);
            exit;

        case 'restore_reminder':
            $id = (int)$_POST['id'];
            $source_type = $_POST['source_type'] ?? 'reminder';

            if ($source_type === 'instance') {
                $instance_id = (int)$_POST['instance_id'];
                $stmt = $dbh->prepare('UPDATE reminder_instances SET is_dismissed = 0 WHERE id = ?');
                $stmt->execute([$instance_id]);
                $message = 'Reminder instance restored!';
            } else {
                $stmt = $dbh->prepare('UPDATE reminders SET is_dismissed = 0 WHERE id = ?');
                $stmt->execute([$id]);
                $message = 'Reminder restored!';
            }

            echo json_encode(['success' => true, 'message' => $message]);
            exit;

        case 'complete_reminder_instance':
            $instance_id = (int)$_POST['instance_id'];
            $stmt = $dbh->prepare('UPDATE reminder_instances SET is_completed = 1 WHERE id = ?');
            $stmt->execute([$instance_id]);
            echo json_encode(['success' => true, 'message' => 'Reminder instance completed!']);
            exit;

        case 'delete_reminder':
            $id = (int)$_POST['id'];
            $stmt = $dbh->prepare('DELETE FROM reminders WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            exit;

        case 'delete_reminder_instance':
            $instance_id = (int)$_POST['id'];
            $stmt = $dbh->prepare('DELETE FROM reminder_instances WHERE id = ?');
            $stmt->execute([$instance_id]);
            echo json_encode(['success' => true]);
            exit;

        case 'get_calendar_reminders':
            try {
                // Get all reminders and instances for calendar display with category icons
                $sql = "
            SELECT 
                r.id,
                r.title,
                r.description,
                r.reminder_date,
                r.reminder_time,
                r.priority,
                r.category,
                r.advance_notification,
                r.email_frequency,
                r.is_recurring,
                r.is_completed,
                r.is_dismissed,
                'reminder' as source_type,
                NULL as instance_id,
                NULL as reminder_id,
                rc.icon as category_icon,
                rc.color as category_color
            FROM reminders r
            LEFT JOIN reminder_categories rc ON r.category = rc.name
            WHERE r.is_recurring = 0
            
            UNION ALL
            
            SELECT 
                r.id as id,
                r.title,
                r.description,
                ri.instance_date as reminder_date,
                ri.instance_time as reminder_time,
                r.priority,
                r.category,
                r.advance_notification,
                r.email_frequency,
                r.is_recurring,
                ri.is_completed,
                ri.is_dismissed,
                'instance' as source_type,
                ri.id as instance_id,
                ri.reminder_id,
                rc.icon as category_icon,
                rc.color as category_color
            FROM reminder_instances ri 
            JOIN reminders r ON ri.reminder_id = r.id 
            LEFT JOIN reminder_categories rc ON r.category = rc.name
            
            ORDER BY reminder_date ASC, reminder_time ASC
        ";

                $stmt = $dbh->prepare($sql);
                $stmt->execute();
                $reminders = $stmt->fetchAll();

                echo json_encode([
                    'success' => true,
                    'reminders' => $reminders
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error fetching calendar reminders: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'bulk_delete_reminders':
            try {
                $ids = json_decode($_POST['ids'], true);
                if (empty($ids) || !is_array($ids)) {
                    throw new Exception('No reminders selected');
                }

                $dbh->beginTransaction();

                // First check if these IDs exist in reminder_instances table
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $dbh->prepare("SELECT id FROM reminder_instances WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $instance_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // The remaining IDs should be from reminders table
                $reminder_ids = array_diff($ids, $instance_ids);

                // Delete recurring instances from reminder_instances table
                if (!empty($instance_ids)) {
                    $instance_placeholders = str_repeat('?,', count($instance_ids) - 1) . '?';
                    $stmt = $dbh->prepare("DELETE FROM reminder_instances WHERE id IN ($instance_placeholders)");
                    $stmt->execute($instance_ids);
                }

                // Delete non-recurring reminders from reminders table
                if (!empty($reminder_ids)) {
                    $reminder_placeholders = str_repeat('?,', count($reminder_ids) - 1) . '?';
                    $stmt = $dbh->prepare("DELETE FROM reminders WHERE id IN ($reminder_placeholders)");
                    $stmt->execute($reminder_ids);
                }

                $dbh->commit();

                echo json_encode([
                    'success' => true,
                    'message' => count($ids) . ' reminder(s) deleted'
                ]);
            } catch (Exception $e) {
                $dbh->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'bulk_complete_reminders':
            try {
                $ids = json_decode($_POST['ids'], true);
                if (empty($ids) || !is_array($ids)) {
                    throw new Exception('No reminders selected');
                }

                $dbh->beginTransaction();

                // Split IDs across reminder_instances vs reminders
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $dbh->prepare("SELECT id FROM reminder_instances WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $instance_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $reminder_ids = array_diff($ids, $instance_ids);

                $total = 0;

                if (!empty($instance_ids)) {
                    $iph = str_repeat('?,', count($instance_ids) - 1) . '?';
                    $stmt = $dbh->prepare("UPDATE reminder_instances SET is_completed = 1 WHERE id IN ($iph)");
                    $stmt->execute($instance_ids);
                    $total += $stmt->rowCount();
                }

                if (!empty($reminder_ids)) {
                    $rph = str_repeat('?,', count($reminder_ids) - 1) . '?';
                    $stmt = $dbh->prepare("UPDATE reminders SET is_completed = 1 WHERE id IN ($rph)");
                    $stmt->execute($reminder_ids);
                    $total += $stmt->rowCount();
                }

                $dbh->commit();

                echo json_encode([
                    'success' => true,
                    'message' => $total . ' reminder(s) marked complete'
                ]);
            } catch (Exception $e) {
                $dbh->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'save_reminder_settings':
            try {
                $settings = [
                    'morning_summary_enabled' => $_POST['morning_summary_enabled'],
                    'evening_progress_enabled' => $_POST['evening_progress_enabled'],
                    'due_reminders_enabled' => $_POST['due_reminders_enabled'],
                    'send_empty_summaries' => $_POST['send_empty_summaries'],
                    'notification_email' => sanitize($_POST['notification_email']),
                    'email_format' => $_POST['email_format']
                ];

                foreach ($settings as $key => $value) {
                    $stmt = $dbh->prepare('INSERT INTO reminder_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
                    $stmt->execute([$key, $value, $value]);
                }

                echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error saving settings: ' . $e->getMessage()]);
            }
            exit;

        case 'get_reminder_settings':
            try {
                $stmt = $dbh->query('SELECT setting_key, setting_value FROM reminder_settings');
                $settings = [];
                while ($row = $stmt->fetch()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }

                echo json_encode(['success' => true, 'settings' => $settings]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading settings']);
            }
            exit;

        case 'reset_reminder_settings':
            try {
                $defaultSettings = [
                    'morning_summary_enabled' => '1',
                    'evening_progress_enabled' => '1',
                    'due_reminders_enabled' => '1',
                    'send_empty_summaries' => '1',
                    'notification_email' => 'bryo4419@gmail.com',
                    'email_format' => 'html'
                ];

                foreach ($defaultSettings as $key => $value) {
                    $stmt = $dbh->prepare('INSERT INTO reminder_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
                    $stmt->execute([$key, $value, $value]);
                }

                echo json_encode(['success' => true, 'message' => 'Settings reset to defaults']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error resetting settings']);
            }
            exit;

        case 'snooze_reminder':
            $id = (int)$_POST['id'];
            $minutes = (int)$_POST['minutes'];
            $source_type = $_POST['source_type'] ?? 'reminder';
            $instance_id = isset($_POST['instance_id']) ? (int)$_POST['instance_id'] : null;

            // Validate snooze duration
            $allowedSnoozeOptions = explode(',', getSetting('snooze_options', '15,30,60,120,480,1440'));
            if (!in_array($minutes, $allowedSnoozeOptions)) {
                echo json_encode(['success' => false, 'message' => 'Invalid snooze duration']);
                exit;
            }

            // Check max snooze count
            $maxSnoozeCount = (int)getSetting('max_snooze_count', '5');

            try {
                $dbh->beginTransaction();

                if ($source_type === 'instance' && $instance_id) {
                    // Handle recurring reminder instance
                    $stmt = $dbh->prepare('SELECT snooze_count FROM reminder_instances WHERE id = ?');
                    $stmt->execute([$instance_id]);
                    $current = $stmt->fetch();

                    if ($current && $current['snooze_count'] >= $maxSnoozeCount) {
                        throw new Exception('Maximum snooze limit reached for this reminder');
                    }

                    $snoozeUntil = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));

                    $stmt = $dbh->prepare('
                UPDATE reminder_instances 
                SET is_snoozed = 1, snooze_until = ?, snooze_count = snooze_count + 1, last_snooze_time = NOW()
                WHERE id = ?
            ');
                    $stmt->execute([$snoozeUntil, $instance_id]);

                    // Log snooze history
                    $stmt = $dbh->prepare('
                INSERT INTO snooze_history (reminder_id, instance_id, snooze_duration_minutes, snooze_time, snooze_until) 
                VALUES (?, ?, ?, NOW(), ?)
            ');
                    $stmt->execute([$id, $instance_id, $minutes, $snoozeUntil]);

                } else {
                    // Handle regular reminder
                    $stmt = $dbh->prepare('SELECT snooze_count FROM reminders WHERE id = ?');
                    $stmt->execute([$id]);
                    $current = $stmt->fetch();

                    if ($current && $current['snooze_count'] >= $maxSnoozeCount) {
                        throw new Exception('Maximum snooze limit reached for this reminder');
                    }

                    $snoozeUntil = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));

                    $stmt = $dbh->prepare('
                UPDATE reminders 
                SET is_snoozed = 1, snooze_until = ?, snooze_count = snooze_count + 1, last_snooze_time = NOW()
                WHERE id = ?
            ');
                    $stmt->execute([$snoozeUntil, $id]);

                    // Log snooze history
                    $stmt = $dbh->prepare('
                INSERT INTO snooze_history (reminder_id, snooze_duration_minutes, snooze_time, snooze_until) 
                VALUES (?, ?, NOW(), ?)
            ');
                    $stmt->execute([$id, $minutes, $snoozeUntil]);
                }

                $dbh->commit();

                // Format the snooze duration for display
                $snoozeText = formatSnoozeDuration($minutes);

                echo json_encode([
                    'success' => true,
                    'message' => "Reminder snoozed for {$snoozeText}",
                    'snooze_until' => $snoozeUntil,
                    'snooze_count' => ($current['snooze_count'] ?? 0) + 1,
                    'max_snoozes' => $maxSnoozeCount
                ]);

            } catch (Exception $e) {
                $dbh->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_snooze_options':
            $snoozeOptions = getSetting('snooze_options', '15,30,60,120,480,1440');
            $maxSnoozeCount = (int)getSetting('max_snooze_count', '5');

            $options = [];
            foreach (explode(',', $snoozeOptions) as $minutes) {
                $minutes = (int)$minutes;
                $options[] = [
                    'minutes' => $minutes,
                    'label' => formatSnoozeDuration($minutes),
                    'icon' => getSnoozeDurationIcon($minutes)
                ];
            }

            echo json_encode([
                'success' => true,
                'options' => $options,
                'max_snooze_count' => $maxSnoozeCount
            ]);
            exit;

        case 'unsnooze_reminder':
            $id = (int)$_POST['id'];
            $source_type = $_POST['source_type'] ?? 'reminder';
            $instance_id = isset($_POST['instance_id']) ? (int)$_POST['instance_id'] : null;

            try {
                if ($source_type === 'instance' && $instance_id) {
                    $stmt = $dbh->prepare('
                UPDATE reminder_instances 
                SET is_snoozed = 0, snooze_until = NULL 
                WHERE id = ?
            ');
                    $stmt->execute([$instance_id]);
                } else {
                    $stmt = $dbh->prepare('
                UPDATE reminders 
                SET is_snoozed = 0, snooze_until = NULL 
                WHERE id = ?
            ');
                    $stmt->execute([$id]);
                }

                echo json_encode(['success' => true, 'message' => 'Reminder unnoozed successfully']);

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error unsnoozing reminder']);
            }
            exit;

        case 'get_snooze_stats':
            $id = (int)$_POST['id'];
            $instance_id = isset($_POST['instance_id']) ? (int)$_POST['instance_id'] : null;

            try {
                // Get snooze history
                $query = "
            SELECT snooze_duration_minutes, snooze_time, snooze_until 
            FROM snooze_history 
            WHERE reminder_id = ?
        ";
                $params = [$id];

                if ($instance_id) {
                    $query .= " AND instance_id = ?";
                    $params[] = $instance_id;
                }

                $query .= " ORDER BY snooze_time DESC LIMIT 5";

                $stmt = $dbh->prepare($query);
                $stmt->execute($params);
                $history = $stmt->fetchAll();

                // Get current snooze status
                if ($instance_id) {
                    $stmt = $dbh->prepare('SELECT is_snoozed, snooze_until, snooze_count FROM reminder_instances WHERE id = ?');
                    $stmt->execute([$instance_id]);
                } else {
                    $stmt = $dbh->prepare('SELECT is_snoozed, snooze_until, snooze_count FROM reminders WHERE id = ?');
                    $stmt->execute([$id]);
                }
                $current = $stmt->fetch();

                echo json_encode([
                    'success' => true,
                    'current_status' => $current,
                    'history' => $history,
                    'max_snooze_count' => (int)getSetting('max_snooze_count', '5')
                ]);

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error getting snooze stats']);
            }
            exit;

        case 'reset_snooze_count':
            $id = (int)$_POST['id'];
            $source_type = $_POST['source_type'] ?? 'reminder';
            $instance_id = isset($_POST['instance_id']) ? (int)$_POST['instance_id'] : null;

            try {
                if ($source_type === 'instance' && $instance_id) {
                    $stmt = $dbh->prepare('
                UPDATE reminder_instances 
                SET snooze_count = 0, is_snoozed = 0, snooze_until = NULL 
                WHERE id = ?
            ');
                    $stmt->execute([$instance_id]);
                } else {
                    $stmt = $dbh->prepare('
                UPDATE reminders 
                SET snooze_count = 0, is_snoozed = 0, snooze_until = NULL 
                WHERE id = ?
            ');
                    $stmt->execute([$id]);
                }

                echo json_encode(['success' => true, 'message' => 'Snooze count reset successfully']);

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error resetting snooze count']);
            }
            exit;

        case 'get_snooze_analytics':
            $days = (int)($_POST['days'] ?? 30);

            try {
                $since = date('Y-m-d', strtotime("-{$days} days"));

                // Get overall snooze statistics
                $statsQuery = "
            SELECT 
                COUNT(*) as total_snoozes,
                AVG(snooze_duration_minutes) as avg_duration,
                COUNT(DISTINCT reminder_id) as unique_reminders_snoozed,
                DATE(snooze_time) as snooze_date,
                COUNT(*) as daily_count
            FROM snooze_history 
            WHERE snooze_time >= ?
            GROUP BY DATE(snooze_time)
            ORDER BY snooze_date DESC
        ";

                $stmt = $dbh->prepare($statsQuery);
                $stmt->execute([$since]);
                $dailyStats = $stmt->fetchAll();

                // Get most snoozed reminders
                $topSnoozeQuery = "
            SELECT 
                r.title,
                r.category,
                COUNT(sh.id) as snooze_count,
                AVG(sh.snooze_duration_minutes) as avg_duration
            FROM snooze_history sh
            JOIN reminders r ON sh.reminder_id = r.id
            WHERE sh.snooze_time >= ?
            GROUP BY sh.reminder_id
            ORDER BY snooze_count DESC
            LIMIT 5
        ";

                $stmt = $dbh->prepare($topSnoozeQuery);
                $stmt->execute([$since]);
                $topSnoozed = $stmt->fetchAll();

                // Get snooze duration preferences
                $durationQuery = "
            SELECT 
                snooze_duration_minutes,
                COUNT(*) as usage_count
            FROM snooze_history 
            WHERE snooze_time >= ?
            GROUP BY snooze_duration_minutes 
            ORDER BY usage_count DESC
        ";

                $stmt = $dbh->prepare($durationQuery);
                $stmt->execute([$since]);
                $durationPrefs = $stmt->fetchAll();

                echo json_encode([
                    'success' => true,
                    'period_days' => $days,
                    'daily_stats' => $dailyStats,
                    'top_snoozed' => $topSnoozed,
                    'duration_preferences' => $durationPrefs
                ]);

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error getting snooze analytics']);
            }
            exit;

        case 'update_snooze_settings':
            try {
                $maxSnoozeCount = (int)$_POST['max_snooze_count'];
                $snoozeOptions = $_POST['snooze_options']; // Comma-separated values

                // Validate inputs
                if ($maxSnoozeCount < 1 || $maxSnoozeCount > 20) {
                    throw new Exception('Max snooze count must be between 1 and 20');
                }

                // Validate snooze options
                $options = explode(',', $snoozeOptions);
                foreach ($options as $option) {
                    $minutes = (int)trim($option);
                    if ($minutes < 1 || $minutes > 1440) {
                        throw new Exception('Snooze options must be between 1 and 1440 minutes');
                    }
                }

                // Update settings
                $stmt = $dbh->prepare('INSERT INTO reminder_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');

                $stmt->execute(['max_snooze_count', $maxSnoozeCount, $maxSnoozeCount]);
                $stmt->execute(['snooze_options', $snoozeOptions, $snoozeOptions]);

                echo json_encode(['success' => true, 'message' => 'Snooze settings updated successfully']);

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

// Also update your existing queries to include snooze information
// Update the main reminders query to exclude currently snoozed reminders

// In your main query section, modify the where conditions:
            $where_conditions[] = '(is_snoozed = 0 OR is_snoozed IS NULL OR snooze_until <= NOW())';
            $instance_where_conditions[] = '(ri.is_snoozed = 0 OR ri.is_snoozed IS NULL OR ri.snooze_until <= NOW())';

// Also add snooze information to your calendar reminders query
        case 'get_calendar_reminders':
            try {
                // Updated query to include snooze information
                $sql = "
            SELECT 
                r.id,
                r.title,
                r.description,
                r.reminder_date,
                r.reminder_time,
                r.priority,
                r.category,
                r.advance_notification,
                r.email_frequency,
                r.is_recurring,
                r.is_completed,
                r.is_dismissed,
                r.is_snoozed,
                r.snooze_until,
                r.snooze_count,
                'reminder' as source_type,
                NULL as instance_id,
                NULL as reminder_id,
                rc.icon as category_icon,
                rc.color as category_color
            FROM reminders r
            LEFT JOIN reminder_categories rc ON r.category = rc.name
            
            ORDER BY reminder_date ASC, reminder_time ASC
        ";

                $stmt = $dbh->prepare($sql);
                $stmt->execute();
                $reminders = $stmt->fetchAll();

                echo json_encode([
                    'success' => true,
                    'reminders' => $reminders
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error fetching calendar reminders: ' . $e->getMessage()
                ]);
            }
            exit;
    }
}

// Get reminders
$filter = $_GET['filter'] ?? 'today';
$category = $_GET['category'] ?? '';

// Build where conditions for main query (combining filter and category)
$where_conditions = ['1=1'];
$instance_where_conditions = ['1=1'];
$params = [];

// Add category filtering
if (!empty($category)) {
    $where_conditions[] = "category = :category";
    $instance_where_conditions[] = "r.category = :category";
    $params['category'] = $category;
}

// Add filter conditions
// FIX: Active views must exclude currently-snoozed reminders.
// A reminder is "currently snoozed" when is_snoozed = 1 AND snooze_until > NOW().
$snooze_cond_main     = '(is_snoozed = 0 OR is_snoozed IS NULL OR snooze_until <= NOW())';
$snooze_cond_instance = '(ri.is_snoozed = 0 OR ri.is_snoozed IS NULL OR ri.snooze_until <= NOW())';

switch ($filter) {
    case 'today':
        $where_conditions[] = 'reminder_date = CURDATE() AND is_completed = 0 AND is_dismissed = 0 AND ' . $snooze_cond_main;
        $instance_where_conditions[] = 'ri.instance_date = CURDATE() AND ri.is_completed = 0 AND ri.is_dismissed = 0 AND ' . $snooze_cond_instance;
        break;
    case 'upcoming':
        $where_conditions[] = 'reminder_date >= CURDATE() AND is_completed = 0 AND is_dismissed = 0 AND ' . $snooze_cond_main;
        $instance_where_conditions[] = 'ri.instance_date >= CURDATE() AND ri.is_completed = 0 AND ri.is_dismissed = 0 AND ' . $snooze_cond_instance;
        break;
    case 'overdue':
        $where_conditions[] = 'CONCAT(reminder_date, " ", reminder_time) <= NOW() AND is_completed = 0 AND is_dismissed = 0 AND ' . $snooze_cond_main;
        $instance_where_conditions[] = 'CONCAT(ri.instance_date, " ", ri.instance_time) <= NOW() AND ri.is_completed = 0 AND ri.is_dismissed = 0 AND ' . $snooze_cond_instance;
        break;
    case 'completed':
        $where_conditions[] = 'is_completed = 1 AND is_dismissed = 0';
        $instance_where_conditions[] = 'ri.is_completed = 1 AND ri.is_dismissed = 0';
        break;
    case 'dismissed':
        $where_conditions[] = 'is_dismissed = 1';
        $instance_where_conditions[] = '(ri.is_dismissed = 1)';
        break;
    case 'all':
    default:
        $where_conditions[] = 'is_completed = 0 AND ' . $snooze_cond_main;
        $instance_where_conditions[] = 'ri.is_completed = 0 AND ' . $snooze_cond_instance;
        break;
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? max(12, min(100, (int)$_GET['per_page'])) : 12; // Default 12, min 12, max 100
$offset = ($page - 1) * $per_page;

// Get total count (including instances)
$count_sql = "
        SELECT COUNT(*) FROM (
            SELECT id FROM reminders WHERE is_recurring = 0 AND " . implode(' AND ', $where_conditions) . "
            UNION ALL
            SELECT ri.id 
            FROM reminder_instances ri 
            JOIN reminders r ON ri.reminder_id = r.id 
            WHERE " . implode(' AND ', $instance_where_conditions) . "
        ) as combined_count
    ";

$count_stmt = $dbh->prepare($count_sql);
$count_stmt->execute($params);
$total_reminders = $count_stmt->fetchColumn();
$total_pages = ceil($total_reminders / $per_page);

// Get combined reminders and instances
$sql = "
        SELECT 
            id,
            title,
            description,
            reminder_date,
            reminder_time,
            advance_notification,
            email_frequency,
            priority,
            category,
            is_recurring,
            recurring_days,
            end_date,
            is_completed,
            is_dismissed,
            created_at,
            'reminder' as source_type,
            NULL as instance_id,
            NULL as reminder_id
        FROM reminders 
        WHERE is_recurring = 0 AND " . implode(' AND ', $where_conditions) . "
        
        UNION ALL
        
        SELECT 
            r.id as id,
            r.title,
            r.description,
            ri.instance_date as reminder_date,
            ri.instance_time as reminder_time,
            r.advance_notification,
            r.email_frequency,
            r.priority,
            r.category,
            r.is_recurring,
            r.recurring_days,
            r.end_date,
            ri.is_completed,
            ri.is_dismissed,
            r.created_at,
            'instance' as source_type,
            ri.id as instance_id,
            ri.reminder_id
        FROM reminder_instances ri 
        JOIN reminders r ON ri.reminder_id = r.id 
        WHERE " . implode(' AND ', $instance_where_conditions) . "
        
        ORDER BY reminder_date ASC, reminder_time ASC 
        LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

$stmt = $dbh->prepare($sql);
$stmt->execute($params);
$reminders = $stmt->fetchAll();

// Update statistics to include instances with category filtering
$category_condition = !empty($category) ? " AND category = ?" : "";
$category_condition_instances = !empty($category) ? " AND r.category = ?" : "";

$stats = [];

// Total count
$sql = "
        SELECT COUNT(*) FROM (
            SELECT id FROM reminders WHERE is_recurring = 0 AND is_completed = 0" . $category_condition . "
            UNION ALL
            SELECT ri.id FROM reminder_instances ri 
            JOIN reminders r ON ri.reminder_id = r.id 
            WHERE ri.is_dismissed = 0" . $category_condition_instances . "
        ) as total_count
    ";
$stmt = $dbh->prepare($sql);
if (!empty($category)) {
    $stmt->execute([$category, $category]);
} else {
    $stmt->execute();
}
$stats['total'] = $stmt->fetchColumn();

// Today count
$sql = "
        SELECT COUNT(*) FROM (
            SELECT id FROM reminders WHERE is_recurring = 0 AND is_completed = 0 AND reminder_date = CURDATE() AND is_dismissed = 0 AND (is_snoozed = 0 OR is_snoozed IS NULL OR snooze_until <= NOW())" . $category_condition . "
            UNION ALL
            SELECT ri.id FROM reminder_instances ri 
            JOIN reminders r ON ri.reminder_id = r.id 
            WHERE ri.instance_date = CURDATE() AND ri.is_dismissed = 0 AND (ri.is_snoozed = 0 OR ri.is_snoozed IS NULL OR ri.snooze_until <= NOW())" . $category_condition_instances . "
        ) as today_count
    ";
$stmt = $dbh->prepare($sql);
if (!empty($category)) {
    $stmt->execute([$category, $category]);
} else {
    $stmt->execute();
}
$stats['today'] = $stmt->fetchColumn();

// Overdue count
$sql = "
        SELECT COUNT(*) FROM (
            SELECT id FROM reminders 
            WHERE is_recurring = 0 
            AND CONCAT(reminder_date, ' ', reminder_time) <= NOW() 
            AND is_completed = 0 
            AND is_dismissed = 0
            AND (is_snoozed = 0 OR is_snoozed IS NULL OR snooze_until <= NOW())" . $category_condition . "
            UNION ALL
            SELECT ri.id FROM reminder_instances ri 
            JOIN reminders r ON ri.reminder_id = r.id 
            WHERE CONCAT(ri.instance_date, ' ', ri.instance_time) <= NOW() 
            AND ri.is_completed = 0 
            AND ri.is_dismissed = 0
            AND (ri.is_snoozed = 0 OR ri.is_snoozed IS NULL OR ri.snooze_until <= NOW())" . $category_condition_instances . "
        ) as overdue_count
    ";
$stmt = $dbh->prepare($sql);
if (!empty($category)) {
    $stmt->execute([$category, $category]);
} else {
    $stmt->execute();
}
$stats['overdue'] = $stmt->fetchColumn();

// Upcoming count
$sql = "
        SELECT COUNT(*) FROM (
            SELECT id FROM reminders 
            WHERE is_recurring = 0 
            AND reminder_date >= CURDATE() 
              AND is_completed = 0
            AND is_dismissed = 0
            AND (is_snoozed = 0 OR is_snoozed IS NULL OR snooze_until <= NOW())" . $category_condition . "
            UNION ALL
            SELECT ri.id FROM reminder_instances ri 
            JOIN reminders r ON ri.reminder_id = r.id 
            WHERE ri.instance_date > CURDATE()
            AND ri.is_completed = 0 AND ri.is_dismissed = 0
            AND (ri.is_snoozed = 0 OR ri.is_snoozed IS NULL OR ri.snooze_until <= NOW())" . $category_condition_instances . "
        ) as upcoming_count
    ";
$stmt = $dbh->prepare($sql);
if (!empty($category)) {
    $stmt->execute([$category, $category]);
} else {
    $stmt->execute();
}
$stats['upcoming'] = $stmt->fetchColumn();

// Dismissed count
$sql = "
        SELECT COUNT(*) FROM (
            SELECT id FROM reminders WHERE is_dismissed = 1" . $category_condition . "
            UNION ALL
            SELECT ri.id FROM reminder_instances ri 
            JOIN reminders r ON ri.reminder_id = r.id 
            WHERE ri.is_dismissed = 1" . $category_condition_instances . "
        ) as dismissed_count
    ";
$stmt = $dbh->prepare($sql);
if (!empty($category)) {
    $stmt->execute([$category, $category]);
} else {
    $stmt->execute();
}
$stats['dismissed'] = $stmt->fetchColumn();

// Completed count
$sql = "
        SELECT COUNT(*) FROM (
            SELECT id FROM reminders WHERE is_recurring = 0 AND is_completed = 1" . $category_condition . "
            UNION ALL
            SELECT ri.id FROM reminder_instances ri 
            JOIN reminders r ON ri.reminder_id = r.id 
            WHERE ri.is_completed = 1" . $category_condition_instances . "
        ) as completed_count
    ";
$stmt = $dbh->prepare($sql);
if (!empty($category)) {
    $stmt->execute([$category, $category]);
} else {
    $stmt->execute();
}
$stats['completed'] = $stmt->fetchColumn();

// Get categories
$categories = $dbh->query("SELECT DISTINCT category FROM reminders WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);


// Get categories
if ($action === 'get_categories') {
    try {
        $stmt = $dbh->query("SELECT * FROM reminder_categories ORDER BY is_default DESC, name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'categories' => $categories
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching categories: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Add category
if ($action === 'add_category') {
    try {
        $name = trim($_POST['name']);
        $icon = $_POST['icon'];
        $color = $_POST['color'];

        // Validate inputs
        if (empty($name)) {
            throw new Exception('Category name is required');
        }

        // Check if category already exists
        $stmt = $dbh->prepare("SELECT id FROM reminder_categories WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            throw new Exception('Category already exists');
        }

        // Insert new category
        $stmt = $dbh->prepare("INSERT INTO reminder_categories (name, icon, color) VALUES (?, ?, ?)");
        $stmt->execute([$name, $icon, $color]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Update category
if ($action === 'update_category') {
    try {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $icon = $_POST['icon'];
        $color = $_POST['color'];

        // Validate inputs
        if (empty($name)) {
            throw new Exception('Category name is required');
        }

        // Check if category name already exists (excluding current category)
        $stmt = $dbh->prepare("SELECT id FROM reminder_categories WHERE name = ? AND id != ?");
        $stmt->execute([$name, $id]);
        if ($stmt->fetch()) {
            throw new Exception('Category name already exists');
        }

        // Update category
        $stmt = $dbh->prepare("UPDATE reminder_categories SET name = ?, icon = ?, color = ? WHERE id = ?");
        $stmt->execute([$name, $icon, $color, $id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Delete category
if ($action === 'delete_category') {
    try {
        $id = $_POST['id'];

        // Check if category is default
        $stmt = $dbh->prepare("SELECT is_default FROM reminder_categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();

        if ($category && $category['is_default']) {
            throw new Exception('Cannot delete default categories');
        }

        // Check if category is being used
        $stmt = $dbh->prepare("SELECT COUNT(*) as count FROM reminders WHERE category = (SELECT name FROM reminder_categories WHERE id = ?)");
        $stmt->execute([$id]);
        $usage = $stmt->fetch();

        if ($usage['count'] > 0) {
            throw new Exception('Cannot delete category that is being used by reminders');
        }

        // Delete category
        $stmt = $dbh->prepare("DELETE FROM reminder_categories WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

?>
<?php
// Check if 'session' exists before using it
if (isset($_SESSION['odmsaid'])) {
    $aid = $_SESSION['odmsaid'];
} else {
    header('Location: login.php');
    exit();
}


// Fetch userID from the database using the email stored in the session
$userQuery = mysqli_query($con, "SELECT id FROM tbladmin WHERE email = '$aid'");
$userResult = mysqli_fetch_assoc($userQuery);
$userID = $userResult['id']; // Get the userID

// Query to fetch unread messages details by userID
$unreadMessagesQuery = mysqli_query($con, "SELECT * FROM chat_messages WHERE is_read = 0 AND receiver_id = '$userID' ORDER BY timestamp ASC");

$unreadMessages = []; // Initialize array to hold unread messages data
while ($message = mysqli_fetch_assoc($unreadMessagesQuery)) {
    $unreadMessages[] = $message; // Add each unread message to the array
}

$unreadMessagesCount = count($unreadMessages); // Count the number of unread messages
?>
    <!DOCTYPE html>
    <html data-bs-theme="light" lang="en-US" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reminders</title>
    <style>
        /* === Reminders: Split-View Redesign === */
        .stat-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 0.9rem;
            border-radius: 999px;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-body-bg);
            color: var(--bs-body-color);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }
        .stat-pill:hover {
            background: var(--bs-body-tertiary-bg);
            transform: translateY(-1px);
            text-decoration: none;
        }
        .stat-pill.active {
            border-width: 2px;
            font-weight: 600;
        }
        .stat-pill .badge { font-size: 0.7rem; padding: 0.15em 0.55em; font-weight: 600; }

        /* Split layout heights */
        .reminders-split .reminders-list-card,
        .reminders-split .reminder-detail-card {
            min-height: 600px;
        }
        @media (min-width: 992px) {
            .reminders-split .reminders-list-card,
            .reminders-split .reminder-detail-card {
                height: calc(100vh - 280px);
                min-height: 500px;
            }
        }

        .reminders-list-card { display: flex; flex-direction: column; overflow: hidden; }
        .reminders-list-scroll {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 0;
        }

        /* Group headers */
        .reminder-group { }
        .reminder-group-header {
            position: sticky;
            top: 0;
            background: var(--bs-body-bg);
            padding: 0.6rem 1rem;
            border-bottom: 1px solid var(--bs-border-color);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            z-index: 5;
            transition: background-color 0.15s ease;
        }
        .reminder-group-header.drop-active {
            background: var(--bs-primary-bg-subtle);
            outline: 2px dashed var(--bs-primary);
            outline-offset: -4px;
        }

        /* Each row */
        .reminder-item {
            padding: 0.85rem 1rem 0.85rem 1.1rem;
            border-bottom: 1px solid var(--bs-border-color);
            cursor: pointer;
            transition: background-color 0.12s ease;
            user-select: none;
        }
        .reminder-item:hover {
            background: var(--bs-body-tertiary-bg);
        }
        .reminder-item.is-selected {
            background: var(--bs-primary-bg-subtle);
        }
        .reminder-item.is-completed { opacity: 0.7; }
        .reminder-item.dragging {
            opacity: 0.45;
            transform: scale(0.98);
        }
        .reminder-accent {
            position: absolute;
            top: 0; bottom: 0; left: 0;
            width: 3px;
        }
        .reminder-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        .reminder-cat-icon {
            width: 36px; height: 36px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: 0.95rem;
        }
        .reminder-main { min-width: 0; }
        .reminder-title {
            font-weight: 600;
            font-size: 0.95rem;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .reminder-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            margin-top: 0.15rem;
        }
        .reminder-priority-dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .priority-high   { background: var(--bs-danger); }
        .priority-medium { background: var(--bs-warning); }
        .priority-low    { background: var(--bs-secondary); }

        /* Quick action buttons: show on hover */
        .reminder-quick-actions {
            display: flex;
            gap: 0.25rem;
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        .reminder-item:hover .reminder-quick-actions,
        .reminder-item.is-selected .reminder-quick-actions { opacity: 1; }
        @media (max-width: 768px) {
            .reminder-quick-actions { opacity: 1; } /* always visible on touch */
        }
        .qa-btn {
            width: 30px; height: 30px;
            padding: 0;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 6px;
        }

        /* Detail pane */
        .reminder-detail-card { overflow: hidden; display: flex; flex-direction: column; }
        #reminderDetailContent { overflow-y: auto; flex: 1 1 auto; }
        .detail-hero {
            padding: 1.5rem 1.75rem 1rem;
            border-bottom: 1px solid var(--bs-border-color);
        }
        .detail-section {
            padding: 1rem 1.75rem;
            border-bottom: 1px solid var(--bs-border-color);
        }
        .detail-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--bs-secondary-color);
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        .detail-actions {
            padding: 1rem 1.75rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Tab nav refresh */
        #reminderTabs .nav-link {
            border: 0;
            color: var(--bs-secondary-color);
            font-weight: 500;
            border-bottom: 2px solid transparent;
            border-radius: 0;
        }
        #reminderTabs .nav-link.active {
            background: transparent;
            color: var(--bs-primary);
            border-bottom-color: var(--bs-primary);
        }

        /* Quick add bar focus state */
        .quick-add-bar { background: var(--bs-body-bg); }
        #quickAddForm.expanded .quick-add-meta { display: flex !important; }

        /* Scrollbar polish */
        .reminders-list-scroll::-webkit-scrollbar,
        #reminderDetailContent::-webkit-scrollbar { width: 6px; }
        .reminders-list-scroll::-webkit-scrollbar-thumb,
        #reminderDetailContent::-webkit-scrollbar-thumb {
            background: var(--bs-border-color);
            border-radius: 3px;
        }

        .min-width-0 { min-width: 0; }
    </style>
    <?php include 'navi.php'; ?>
    <!-- Header -->
    <div class='card shadow-none border mb-3'>
        <div class='bg-holder bg-card d-none d-md-block'
             style='background-image:url(../assets/img/illustrations/corner-6.png);'>
        </div>
        <!--/.bg-holder-->

        <div class='card-header z-1'>
            <div class='row flex-between-center gx-0'>
                <div class='col-lg-auto d-flex align-items-center'>
                    <h4 class='mb-0 text-primary fw-bold'>My<span class='text-info fw-medium'> Reminders</span>
                    </h4>
                </div>
                <div class='col-lg-auto pt-3 pt-lg-0'>
                    <form class="$rowTask flex-lg-column flex-xxl-$rowTask gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
                        <div class='col-auto'>
                        </div>
                        <div class='col-md-auto position-relative'>
                            <h6 class='mb-1 badge rounded-pill badge-subtle-info'><?php echo date('jS F Y'); ?> |
                                <span id="timeDisplay"></span></h6>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="card mb-3">
        <div class="card-body p-0">
            <nav class="nav nav-tabs border-0" id="reminderTabs" role="tablist">
                <a class="nav-link active px-4 py-3" id="list-tab" data-bs-toggle="tab" href="#list-view" role="tab" aria-controls="list-view" aria-selected="true">
                    <i class="fas fa-list me-2"></i>List View
                </a>
                <a class="nav-link px-4 py-3" id="calendar-tab" data-bs-toggle="tab" href="#calendar-view" role="tab" aria-controls="calendar-view" aria-selected="false">
                    <i class="fas fa-calendar-alt me-2"></i>Calendar View
                </a>
                <a class="nav-link px-4 py-3" id="settings-tab" data-bs-toggle="tab" href="#settings-view" role="tab" aria-controls="settings-view" aria-selected="false">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
                <a class="nav-link px-4 py-3" id="snooze-tab" data-bs-toggle="tab" href="#snooze-view" role="tab" aria-controls="snooze-view" aria-selected="false">
                    <i class="fas fa-cog me-2"></i>Snooze
                </a>
            </nav>
        </div>
    </div>


    <!-- Main Content -->
    <div class="col mb-3">
        <!-- Tab Content -->
        <div class="tab-content" id="reminderTabContent">
            <!-- List View Tab -->
            <!-- List View Tab (Redesigned: Split View) -->
            <div class="tab-pane fade show active" id="list-view" role="tabpanel" aria-labelledby="list-tab">

                <!-- Stats Pills Row (compact, replaces old big stat cards) -->
                <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                    <a href="?filter=today<?= !empty($category) ? '&category=' . urlencode($category) : '' ?>"
                       class="stat-pill <?= $filter === 'today' ? 'active border-info text-info' : '' ?>">
                        <i class="fas fa-calendar-day me-1"></i>Today
                        <span class="badge bg-info bg-opacity-25 text-info ms-1"><?= $stats['today'] ?></span>
                    </a>
                    <a href="?filter=upcoming<?= !empty($category) ? '&category=' . urlencode($category) : '' ?>"
                       class="stat-pill <?= $filter === 'upcoming' ? 'active border-primary text-primary' : '' ?>">
                        <i class="fas fa-arrow-right me-1"></i>Upcoming
                        <span class="badge bg-primary bg-opacity-25 text-primary ms-1"><?= $stats['upcoming'] ?></span>
                    </a>
                    <a href="?filter=overdue<?= !empty($category) ? '&category=' . urlencode($category) : '' ?>"
                       class="stat-pill <?= $filter === 'overdue' ? 'active border-danger text-danger' : '' ?>">
                        <i class="fas fa-exclamation-triangle me-1"></i>Overdue
                        <span class="badge bg-danger bg-opacity-25 text-danger ms-1"><?= $stats['overdue'] ?></span>
                    </a>
                    <a href="?filter=completed<?= !empty($category) ? '&category=' . urlencode($category) : '' ?>"
                       class="stat-pill <?= $filter === 'completed' ? 'active border-success text-success' : '' ?>">
                        <i class="fas fa-check-circle me-1"></i>Done
                        <span class="badge bg-success bg-opacity-25 text-success ms-1"><?= $stats['completed'] ?></span>
                    </a>
                    <a href="?filter=dismissed<?= !empty($category) ? '&category=' . urlencode($category) : '' ?>"
                       class="stat-pill <?= $filter === 'dismissed' ? 'active border-warning text-warning' : '' ?>">
                        <i class="fas fa-eye-slash me-1"></i>Dismissed
                        <span class="badge bg-warning bg-opacity-25 text-warning ms-1"><?= $stats['dismissed'] ?></span>
                    </a>
                    <a href="?filter=all<?= !empty($category) ? '&category=' . urlencode($category) : '' ?>"
                       class="stat-pill <?= $filter === 'all' ? 'active border-secondary' : '' ?>">
                        <i class="fas fa-layer-group me-1"></i>All
                        <span class="badge bg-secondary bg-opacity-25 text-secondary ms-1"><?= $stats['total'] ?></span>
                    </a>

                    <div class="ms-auto d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#manageCategoriesModal" title="Manage categories">
                            <i class="fas fa-tags"></i>
                        </button>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addReminderModal">
                            <i class="fas fa-plus me-1"></i>Full Form
                        </button>
                    </div>
                </div>

                <!-- Split View: List (left) + Detail (right) -->
                <div class="row g-3 reminders-split">
                    <!-- LEFT PANE: List -->
                    <div class="col-12 col-lg-5 col-xl-4">
                        <div class="card reminders-list-card border-0 shadow-sm">
                            <!-- Search + Filter Bar -->
                            <div class="card-header bg-body-tertiary border-0 p-3">
                                <div class="position-relative mb-2">
                                    <i class="fas fa-search position-absolute top-50 translate-middle-y ms-3 text-muted" style="font-size:0.85rem;"></i>
                                    <input type="text" id="reminderSearch" class="form-control ps-5"
                                           placeholder="Search reminders..." autocomplete="off">
                                    <button type="button" id="reminderSearchClear"
                                            class="btn btn-sm position-absolute top-50 end-0 translate-middle-y me-2 d-none"
                                            style="background:transparent;border:0;">
                                        <i class="fas fa-times text-muted"></i>
                                    </button>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if (!empty($categories)): ?>
                                        <select id="categoryFilter" class="form-select form-select-sm" style="flex:1;min-width:0;">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                                    <?= ucfirst(htmlspecialchars($cat)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                    <select id="priorityFilter" class="form-select form-select-sm" style="flex:1;min-width:0;">
                                        <option value="">All Priority</option>
                                        <option value="high">High</option>
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Inline Quick Add -->
                            <div class="quick-add-bar p-3 border-bottom">
                                <form id="quickAddForm" class="d-flex gap-2 flex-wrap align-items-center" autocomplete="off">
                                    <input type="text" name="title" id="quickAddTitle" class="form-control form-control-sm"
                                           placeholder="+ Quick add a reminder..." style="flex:1 1 100%;" required>
                                    <div class="d-flex gap-2 w-100 quick-add-meta" style="display:none !important;">
                                        <input type="date" name="reminder_date" id="quickAddDate"
                                               value="<?= date('Y-m-d') ?>" class="form-control form-control-sm" style="flex:1;">
                                        <input type="time" name="reminder_time" id="quickAddTime"
                                               value="09:00" class="form-control form-control-sm" style="flex:1;">
                                        <select name="category" id="quickAddCategory" class="form-select form-select-sm" style="flex:1;">
                                            <?php if (!empty($categories)): ?>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= htmlspecialchars($cat) ?>"><?= ucfirst(htmlspecialchars($cat)) ?></option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option value="general">General</option>
                                            <?php endif; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Bulk Action Bar (hidden when empty, toggled by existing JS) -->
                            <div class="bulk-actions px-3 py-2 bg-warning bg-opacity-10 border-bottom" style="display:none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" id="selectAllReminders">
                                        <label class="form-check-label small" for="selectAllReminders">
                                            <span id="selectedCount">0</span> selected
                                        </label>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-success" id="bulkCompleteBtn" title="Mark selected as complete">
                                            <i class="fas fa-check me-1"></i>Complete
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" title="Delete selected">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" id="clearSelectionBtn" title="Clear">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Grouped Reminder List -->
                            <div class="reminders-list-scroll" id="reminders-container">
                                <?php if (empty($reminders)): ?>
                                    <div class="text-center py-5 px-3">
                                        <i class="fas fa-bell-slash fa-2x text-muted mb-3 opacity-50"></i>
                                        <h6 class="text-muted fw-light mb-2">No reminders found</h6>
                                        <p class="text-muted small mb-3">Use the quick-add bar above or open the full form.</p>
                                    </div>
                                <?php else: ?>
                                    <?php
                                    // Group reminders by relative date bucket
                                    $today_str = date('Y-m-d');
                                    $tomorrow_str = date('Y-m-d', strtotime('+1 day'));
                                    $week_end_str = date('Y-m-d', strtotime('+7 days'));

                                    $groups = [
                                        'overdue'  => ['label' => 'Overdue',   'icon' => 'fa-exclamation-triangle', 'color' => 'danger',    'items' => []],
                                        'today'    => ['label' => 'Today',     'icon' => 'fa-calendar-day',         'color' => 'info',      'items' => []],
                                        'tomorrow' => ['label' => 'Tomorrow',  'icon' => 'fa-sun',                  'color' => 'primary',   'items' => []],
                                        'week'     => ['label' => 'This Week', 'icon' => 'fa-calendar-week',        'color' => 'primary',   'items' => []],
                                        'later'    => ['label' => 'Later',     'icon' => 'fa-calendar',             'color' => 'secondary', 'items' => []],
                                        'done'     => ['label' => 'Completed', 'icon' => 'fa-check-circle',         'color' => 'success',   'items' => []],
                                    ];

                                    // Pre-load category color/icon map once
                                    $categoryStmtAll = $dbh->query("SELECT name, icon, color FROM reminder_categories");
                                    $categoryData = [];
                                    while ($cat = $categoryStmtAll->fetch(PDO::FETCH_ASSOC)) {
                                        $categoryData[$cat['name']] = ['icon' => $cat['icon'], 'color' => $cat['color']];
                                    }

                                    $now_dt = new DateTime();
                                    foreach ($reminders as $r) {
                                        $dt = new DateTime($r['reminder_date'] . ' ' . $r['reminder_time']);
                                        $date_only = $r['reminder_date'];
                                        if ($r['is_completed']) {
                                            $bucket = 'done';
                                        } elseif ($dt < $now_dt) {
                                            $bucket = 'overdue';
                                        } elseif ($date_only === $today_str) {
                                            $bucket = 'today';
                                        } elseif ($date_only === $tomorrow_str) {
                                            $bucket = 'tomorrow';
                                        } elseif ($date_only <= $week_end_str) {
                                            $bucket = 'week';
                                        } else {
                                            $bucket = 'later';
                                        }
                                        $groups[$bucket]['items'][] = $r;
                                    }
                                    ?>

                                    <?php foreach ($groups as $bucket_key => $group):
                                        if (empty($group['items'])) continue; ?>
                                        <div class="reminder-group" data-bucket="<?= $bucket_key ?>">
                                            <div class="reminder-group-header" data-droptarget="<?= $bucket_key ?>">
                                                <i class="fas <?= $group['icon'] ?> text-<?= $group['color'] ?> me-2"></i>
                                                <span class="fw-semibold text-<?= $group['color'] ?>"><?= $group['label'] ?></span>
                                                <span class="badge bg-<?= $group['color'] ?> bg-opacity-15 text-<?= $group['color'] ?> ms-2"><?= count($group['items']) ?></span>
                                            </div>

                                            <?php foreach ($group['items'] as $reminder):
                                                $datetime = new DateTime($reminder['reminder_date'] . ' ' . $reminder['reminder_time']);
                                                $is_overdue   = $datetime < $now_dt && !$reminder['is_completed'];
                                                $is_due_soon  = $datetime->diff($now_dt)->days == 0 && $datetime > $now_dt;

                                                $accent = 'primary';
                                                if ($reminder['is_completed'])      $accent = 'success';
                                                elseif ($is_overdue)                $accent = 'danger';
                                                elseif ($is_due_soon)               $accent = 'warning';

                                                $catIcon  = $categoryData[$reminder['category']]['icon']  ?? 'fas fa-bell';
                                                $catColor = $categoryData[$reminder['category']]['color'] ?? '#007bff';

                                                // Encode the full reminder as JSON for the right-pane renderer
                                                $reminder_payload = htmlspecialchars(json_encode([
                                                    'id'                   => $reminder['id'],
                                                    'title'                => $reminder['title'],
                                                    'description'          => $reminder['description'],
                                                    'reminder_date'        => $reminder['reminder_date'],
                                                    'reminder_time'        => $reminder['reminder_time'],
                                                    'priority'             => $reminder['priority'],
                                                    'category'             => $reminder['category'],
                                                    'is_completed'         => (int)$reminder['is_completed'],
                                                    'is_dismissed'         => (int)($reminder['is_dismissed'] ?? 0),
                                                    'is_recurring'         => (int)$reminder['is_recurring'],
                                                    'recurring_days'       => $reminder['recurring_days'] ?? null,
                                                    'advance_notification' => $reminder['advance_notification'],
                                                    'email_frequency'      => $reminder['email_frequency'],
                                                    'source_type'          => $reminder['source_type'] ?? 'reminder',
                                                    'instance_id'          => $reminder['instance_id'] ?? null,
                                                    'reminder_id'          => $reminder['reminder_id'] ?? $reminder['id'],
                                                    'cat_icon'             => $catIcon,
                                                    'cat_color'            => $catColor,
                                                    'accent'               => $accent,
                                                    'is_overdue'           => $is_overdue,
                                                    'is_due_soon'          => $is_due_soon,
                                                ]), ENT_QUOTES, 'UTF-8');
                                                ?>
                                                <div class="reminder-item position-relative <?= $reminder['is_completed'] ? 'is-completed' : '' ?>"
                                                     data-id="<?= $reminder['id'] ?>"
                                                     data-title="<?= htmlspecialchars(strtolower($reminder['title'])) ?>"
                                                     data-category="<?= htmlspecialchars($reminder['category']) ?>"
                                                     data-priority="<?= htmlspecialchars($reminder['priority']) ?>"
                                                     data-reminder='<?= $reminder_payload ?>'
                                                     draggable="true">
                                                    <div class="reminder-accent bg-<?= $accent ?>"></div>

                                                    <div class="reminder-row">
                                                        <!-- Checkbox (kept for bulk-delete compatibility) -->
                                                        <input type="checkbox" class="form-check-input reminder-checkbox flex-shrink-0"
                                                               value="<?= $reminder['id'] ?>"
                                                               onclick="event.stopPropagation();">

                                                        <!-- Category icon -->
                                                        <div class="reminder-cat-icon flex-shrink-0"
                                                             style="background-color: <?= $catColor ?>;"
                                                             title="<?= ucfirst(htmlspecialchars($reminder['category'])) ?>">
                                                            <i class="<?= $catIcon ?>"></i>
                                                        </div>

                                                        <!-- Main content -->
                                                        <div class="reminder-main flex-grow-1 min-width-0">
                                                            <div class="reminder-title <?= $reminder['is_completed'] ? 'text-decoration-line-through text-muted' : '' ?>">
                                                                <?= htmlspecialchars($reminder['title']) ?>
                                                                <?php if ($reminder['is_recurring']): ?>
                                                                    <i class="fas fa-redo text-info ms-1" style="font-size:0.7rem;" title="Recurring"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="reminder-meta">
                                                        <span class="text-muted">
                                                            <i class="fas fa-clock me-1"></i><?= date('g:i A', strtotime($reminder['reminder_time'])) ?>
                                                        </span>
                                                                <span class="reminder-priority-dot priority-<?= $reminder['priority'] ?>" title="<?= ucfirst($reminder['priority']) ?> priority"></span>
                                                                <?php if ($is_overdue): ?>
                                                                    <span class="text-danger small fw-semibold ms-1">
                                                                <?php
                                                                $d = $now_dt->diff($datetime);
                                                                echo $d->days > 0 ? $d->days . 'd overdue' : 'overdue';
                                                                ?>
                                                            </span>
                                                                <?php elseif ($is_due_soon): ?>
                                                                    <span class="text-warning small fw-semibold ms-1">
                                                                <?php
                                                                $d = $datetime->diff($now_dt);
                                                                $hours = $d->h + ($d->days * 24);
                                                                echo $hours > 0 ? "in {$hours}h" : "in {$d->i}m";
                                                                ?>
                                                            </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <!-- Quick actions (visible on hover) -->
                                                        <div class="reminder-quick-actions flex-shrink-0">
                                                            <?php if ($reminder['is_dismissed']): ?>
                                                                <button type="button" class="btn btn-sm btn-light qa-btn" title="Undismiss / Restore"
                                                                        onclick="event.stopPropagation();restoreReminder(<?= $reminder['id'] ?>, '<?= $reminder['source_type'] ?? 'reminder' ?>', <?= $reminder['instance_id'] ?? 'null' ?>);return false;">
                                                                    <i class="fas fa-undo text-success"></i>
                                                                </button>
                                                            <?php elseif (!$reminder['is_completed']): ?>
                                                                <button type="button" class="btn btn-sm btn-light qa-btn" title="Mark complete"
                                                                        onclick="event.stopPropagation();<?= $reminder['source_type'] === 'instance' ? 'completeReminderInstance(' . $reminder['instance_id'] . ')' : 'completeReminder(' . $reminder['id'] . ')' ?>">
                                                                    <i class="fas fa-check text-success"></i>
                                                                </button>
                                                                <div class="dropdown d-inline-block">
                                                                    <button class="btn btn-sm btn-light qa-btn" type="button" data-bs-toggle="dropdown" title="Snooze" onclick="event.stopPropagation();">
                                                                        <i class="fas fa-clock text-info"></i>
                                                                    </button>
                                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                                        <li><h6 class="dropdown-header">Snooze for</h6></li>
                                                                        <?php
                                                                        $allowedSnooze = array_filter(array_map('intval', explode(',', getSetting('snooze_options', '15,30,60,120,480,1440'))));
                                                                        foreach ($allowedSnooze as $sm):
                                                                            $sLabel = formatSnoozeDuration($sm);
                                                                            ?>
                                                                            <li><a class="dropdown-item" href="#" onclick="event.stopPropagation();quickSnooze(<?= $reminder['id'] ?>, <?= $sm ?>);return false;"><?= $sLabel ?></a></li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="dropdown d-inline-block">
                                                                <button class="btn btn-sm btn-light qa-btn" type="button" data-bs-toggle="dropdown" title="More" onclick="event.stopPropagation();">
                                                                    <i class="fas fa-ellipsis-v"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                                    <?php if ($filter === 'dismissed'): ?>
                                                                        <li><a class="dropdown-item" href="#" onclick="event.stopPropagation();restoreReminder(<?= $reminder['id'] ?>, '<?= $reminder['source_type'] ?? 'reminder' ?>', <?= $reminder['instance_id'] ?? 'null' ?>);return false;">
                                                                                <i class="fas fa-undo me-2 text-success"></i>Restore
                                                                            </a></li>
                                                                    <?php else: ?>
                                                                        <?php if (!$reminder['is_completed']): ?>
                                                                            <li><a class="dropdown-item" href="#" onclick="event.stopPropagation();editReminder(<?= $reminder['source_type'] === 'instance' ? $reminder['reminder_id'] : (is_numeric($reminder['id']) ? $reminder['id'] : "'" . $reminder['id'] . "'") ?>);return false;">
                                                                                    <i class="fas fa-edit me-2 text-primary"></i>Edit
                                                                                </a></li>
                                                                        <?php endif; ?>
                                                                        <li><a class="dropdown-item" href="#" onclick="event.stopPropagation();<?= $reminder['source_type'] === 'instance' && !empty($reminder['instance_id']) ? 'dismissReminderInstance(' . $reminder['instance_id'] . ')' : 'dismissReminder(' . $reminder['id'] . ')' ?>;return false;">
                                                                                <i class="fas fa-eye-slash me-2 text-warning"></i>Dismiss
                                                                            </a></li>
                                                                    <?php endif; ?>
                                                                    <li><hr class="dropdown-divider my-1"></li>
                                                                    <li><a class="dropdown-item text-danger" href="#" onclick="event.stopPropagation();deleteReminder(<?= $reminder['id'] ?>);return false;">
                                                                            <i class="fas fa-trash me-2"></i>Delete
                                                                        </a></li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Footer: per-page + pagination -->
                            <?php if (!empty($reminders) && $total_pages > 1): ?>
                                <div class="card-footer bg-body-tertiary border-0 p-2">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <small class="text-muted">Show</small>
                                            <select class="form-select form-select-sm" style="width:auto;" onchange="changePerPage(this.value)">
                                                <?php foreach ([12, 24, 36, 48, 60, 100] as $opt): ?>
                                                    <option value="<?= $opt ?>" <?= $per_page == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <nav>
                                            <ul class="pagination pagination-sm mb-0">
                                                <?php if ($page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                                            <i class="fas fa-chevron-left"></i>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <li class="page-item active"><span class="page-link"><?= $page ?> / <?= $total_pages ?></span></li>
                                                <?php if ($page < $total_pages): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                                            <i class="fas fa-chevron-right"></i>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- RIGHT PANE: Detail -->
                    <div class="col-12 col-lg-7 col-xl-8">
                        <div class="card reminder-detail-card border-0 shadow-sm">
                            <div id="reminderDetailEmpty" class="text-center py-5 px-4">
                                <div class="mb-3">
                                    <i class="fas fa-hand-pointer fa-3x text-muted opacity-25"></i>
                                </div>
                                <h5 class="text-muted fw-light">Select a reminder</h5>
                                <p class="text-muted small mb-0">Click any reminder on the left to see its full details, or drag it onto a date group to reschedule.</p>
                            </div>
                            <div id="reminderDetailContent" class="d-none">
                                <!-- Populated by JS when a reminder is clicked -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendar View Tab -->
            <div class="tab-pane fade" id="calendar-view" role="tabpanel" aria-labelledby="calendar-tab">
                <div class="card shadow-none border mb-3">
                    <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);"></div>
                    <div class="card-header z-1">
                        <div class="row flex-between-center gx-0">
                            <div class="col-lg-auto d-flex align-items-center">
                                <h4 class="mb-0 text-primary fw-bold">Reminders <span class="text-info fw-medium">Calendar</span></h4>
                            </div>
                            <div class="col-md-auto p-3">
                                <form class="row align-items-center g-3">
                                    <div class="col-md-auto position-relative">
                                        <div class="dropdown font-sans-serif me-md-2">
                                            <button class="btn btn-falcon-default text-600 btn-sm dropdown-toggle dropdown-caret-none" type="button" id="reminder-view-selector" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <span id="reminder-current-view">Month View</span>
                                                <svg class="svg-inline--fa fa-sort fa-w-10 ms-2 fs-10" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="sort" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
                                                    <path fill="currentColor" d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41zm255-105L177 64c-9.4-9.4-24.6-9.4-33.9 0L24 183c-15.1 15.1-4.4 41 17 41h238c21.4 0 32.1-25.9 17-41z"></path>
                                                </svg>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="reminder-view-selector">
                                                <a class="dropdown-item d-flex justify-content-between active" href="#" data-fc-view="dayGridMonth">Month View<span class="icon-check"></span></a>
                                                <a class="dropdown-item d-flex justify-content-between" href="#" data-fc-view="timeGridWeek">Week View<span class="icon-check"></span></a>
                                                <a class="dropdown-item d-flex justify-content-between" href="#" data-fc-view="timeGridDay">Day View<span class="icon-check"></span></a>
                                                <a class="dropdown-item d-flex justify-content-between" href="#" data-fc-view="listWeek">List View<span class="icon-check"></span></a>
                                                <a class="dropdown-item d-flex justify-content-between" href="#" data-fc-view="year">Year View<span class="icon-check"></span></a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card overflow-hidden">
                    <div class="card-body p-0 scrollbar m-3">
                        <div class="calendar-outline" id="reminderCalendar"></div>
                    </div>
                </div>

                <!-- Reminder Modal -->
                <div class="modal fade" id="reminderModal" tabindex="-1" aria-labelledby="reminderModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content position-relative border-0">
                            <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                                <div class="position-relative z-1">
                                    <h4 class="mb-0 text-white" id="reminderModalLabel">Reminder Details</h4>
                                </div>
                                <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body py-4 px-5" id="reminderDetails">
                                <!-- Reminder details will be inserted here -->
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-success" id="completeReminderBtn">
                                    <i class="fas fa-check me-1"></i>Mark Complete
                                </button>
                                <button type="button" class="btn btn-warning" id="dismissReminderBtn">
                                    <i class="fas fa-eye-slash me-1"></i>Dismiss
                                </button>
                                <button type="button" class="btn btn-primary" id="editReminderBtn">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Settings View Tab -->
            <div class="tab-pane fade" id="settings-view" role="tabpanel" aria-labelledby="settings-tab">
                <div class="card shadow-none border mb-3">
                    <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);"></div>
                    <div class="card-header z-1">
                        <div class="row flex-between-center gx-0">
                            <div class="col-lg-auto d-flex align-items-center">
                                <h4 class="mb-0 text-primary fw-bold">Reminder <span class="text-info fw-medium">Settings</span></h4>
                            </div>
                            <div class="col-lg-auto pt-3 pt-lg-0">
                                <small class="text-muted">Configure your reminder notification preferences</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Email Notifications Settings -->
                    <div class="col-12">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0 d-flex align-items-center">
                                    <i class="fas fa-envelope text-primary me-2"></i>
                                    Email Notification Settings
                                </h5>
                                <small class="text-muted">Control when and how you receive reminder emails</small>
                            </div>
                            <div class="card-body">
                                <form id="reminderSettingsForm">
                                    <div class="row g-4">
                                        <!-- Morning Summary Email -->
                                        <div class="col-md-6">
                                            <div class="setting-item p-3 border rounded">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 fw-semibold">
                                                            <i class="fas fa-sun text-warning me-2"></i>
                                                            Morning Summary Email
                                                        </h6>
                                                        <p class="text-muted small mb-2">
                                                            Receive a daily summary of today's reminders and overdue items every morning at 8:00 AM
                                                        </p>
                                                        <div class="d-flex align-items-center text-muted small">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <span>Sent daily at 8:00 AM</span>
                                                        </div>
                                                    </div>
                                                    <div class="form-check form-switch ms-3">
                                                        <input class="form-check-input" type="checkbox" id="morning_summary_enabled" name="morning_summary_enabled" checked>
                                                        <label class="form-check-label" for="morning_summary_enabled"></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Evening Progress Email -->
                                        <div class="col-md-6">
                                            <div class="setting-item p-3 border rounded">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 fw-semibold">
                                                            <i class="fas fa-moon text-info me-2"></i>
                                                            Evening Progress Email
                                                        </h6>
                                                        <p class="text-muted small mb-2">
                                                            Get an evening report showing completed, incomplete, and overdue reminders at 11:00 PM
                                                        </p>
                                                        <div class="d-flex align-items-center text-muted small">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <span>Sent daily at 11:00 PM</span>
                                                        </div>
                                                    </div>
                                                    <div class="form-check form-switch ms-3">
                                                        <input class="form-check-input" type="checkbox" id="evening_progress_enabled" name="evening_progress_enabled" checked>
                                                        <label class="form-check-label" for="evening_progress_enabled"></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Due Reminders Email -->
                                        <div class="col-md-6">
                                            <div class="setting-item p-3 border rounded">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 fw-semibold">
                                                            <i class="fas fa-bell text-danger me-2"></i>
                                                            Due Reminder Notifications
                                                        </h6>
                                                        <p class="text-muted small mb-2">
                                                            Receive instant email notifications when reminders become due (checked every 5 minutes)
                                                        </p>
                                                        <div class="d-flex align-items-center text-muted small">
                                                            <i class="fas fa-sync me-1"></i>
                                                            <span>Checked every 5 minutes</span>
                                                        </div>
                                                    </div>
                                                    <div class="form-check form-switch ms-3">
                                                        <input class="form-check-input" type="checkbox" id="due_reminders_enabled" name="due_reminders_enabled" checked>
                                                        <label class="form-check-label" for="due_reminders_enabled"></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Send Even If No Reminders -->
                                        <div class="col-md-6">
                                            <div class="setting-item p-3 border rounded">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 fw-semibold">
                                                            <i class="fas fa-paper-plane text-success me-2"></i>
                                                            Send Empty Summaries
                                                        </h6>
                                                        <p class="text-muted small mb-2">
                                                            Send morning and evening emails even when you have no reminders scheduled
                                                        </p>
                                                        <div class="d-flex align-items-center text-muted small">
                                                            <i class="fas fa-info-circle me-1"></i>
                                                            <span>Applies to summary emails</span>
                                                        </div>
                                                    </div>
                                                    <div class="form-check form-switch ms-3">
                                                        <input class="form-check-input" type="checkbox" id="send_empty_summaries" name="send_empty_summaries" checked>
                                                        <label class="form-check-label" for="send_empty_summaries"></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Email Configuration -->
                                    <div class="mt-4 pt-4 border-top">
                                        <h6 class="mb-3 fw-semibold">
                                            <i class="fas fa-at text-primary me-2"></i>
                                            Email Configuration
                                        </h6>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Notification Email Address</label>
                                                <input type="email" class="form-control" id="notification_email" name="notification_email" value="bryo4419@gmail.com">
                                                <small class="text-muted">Email address where notifications will be sent</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Email Format</label>
                                                <select class="form-select" id="email_format" name="email_format">
                                                    <option value="html" selected>HTML (Rich formatting)</option>
                                                    <option value="text">Plain Text</option>
                                                </select>
                                                <small class="text-muted">Choose your preferred email format</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Save Button -->
                                    <div class="mt-4 pt-3 border-top text-end">
                                        <button type="button" class="btn btn-outline-secondary me-2" id="resetSettingsBtn">
                                            <i class="fas fa-undo me-1"></i>Reset to Defaults
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Save Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Snooze Tab -->
            <div class="tab-pane fade" id="snooze-view" role="tabpanel" aria-labelledby="snooze-tab">
                <div class="col-12">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0 d-flex align-items-center">
                                <i class="fas fa-clock text-warning me-2"></i>
                                Snooze Settings
                            </h5>
                            <small class="text-muted">Configure snooze behavior and limits</small>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <!-- Max Snooze Count -->
                                <div class="col-md-6">
                                    <div class="setting-item p-3 border rounded">
                                        <div class="mb-3">
                                            <h6 class="mb-2 fw-semibold">
                                                <i class="fas fa-hashtag text-warning me-2"></i>
                                                Maximum Snooze Count
                                            </h6>
                                            <p class="text-muted small mb-3">
                                                How many times a reminder can be snoozed before requiring action
                                            </p>
                                            <div class="row align-items-center">
                                                <div class="col-8">
                                                    <input type="range" class="form-range" id="maxSnoozeCount"
                                                           min="1" max="10" value="5" step="1">
                                                </div>
                                                <div class="col-4">
                                                    <input type="number" class="form-control form-control-sm"
                                                           id="maxSnoozeCountInput" min="1" max="20" value="5">
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between text-muted small mt-1">
                                                <span>1 (Strict)</span>
                                                <span id="snoozeCountLabel">5 times</span>
                                                <span>10+ (Flexible)</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Snooze Options -->
                                <div class="col-md-6">
                                    <div class="setting-item p-3 border rounded">
                                        <div class="mb-3">
                                            <h6 class="mb-2 fw-semibold">
                                                <i class="fas fa-list text-info me-2"></i>
                                                Available Snooze Options
                                            </h6>
                                            <p class="text-muted small mb-3">
                                                Customize the quick snooze duration options (in minutes)
                                            </p>
                                            <div class="snooze-options-container">
                                                <div class="row g-2" id="snoozeOptionsInputs">
                                                    <!-- Dynamic inputs will be generated here -->
                                                </div>
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addSnoozeOption()">
                                                        <i class="fas fa-plus me-1"></i>Add Option
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetSnoozeOptionsToDefault()">
                                                        <i class="fas fa-undo me-1"></i>Reset to Default
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Snooze Analytics Preview -->
                                <div class="col-12">
                                    <div class="setting-item p-3 border rounded">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="mb-2 fw-semibold">
                                                    <i class="fas fa-chart-bar text-success me-2"></i>
                                                    Snooze Usage Analytics
                                                </h6>
                                                <p class="text-muted small mb-0">
                                                    View your snooze patterns and productivity insights
                                                </p>
                                            </div>
                                            <button class="btn btn-outline-success btn-sm" onclick="showSnoozeAnalytics()">
                                                <i class="fas fa-chart-line me-1"></i>View Full Analytics
                                            </button>
                                        </div>

                                        <div class="row g-3" id="snoozeAnalyticsPreview">
                                            <div class="col-md-3">
                                                <div class="text-center p-2 bg-light rounded">
                                                    <div class="h5 mb-0 text-warning" id="totalSnoozes">-</div>
                                                    <small class="text-muted">Total Snoozes (30d)</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-center p-2 bg-light rounded">
                                                    <div class="h5 mb-0 text-info" id="avgSnoozeDuration">-</div>
                                                    <small class="text-muted">Avg Duration</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-center p-2 bg-light rounded">
                                                    <div class="h5 mb-0 text-primary" id="uniqueReminders">-</div>
                                                    <small class="text-muted">Unique Reminders</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-center p-2 bg-light rounded">
                                                    <div class="h5 mb-0 text-success" id="mostUsedDuration">-</div>
                                                    <small class="text-muted">Preferred Duration</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Save Snooze Settings Button -->
                            <div class="mt-4 pt-3 border-top text-end">
                                <button type="button" class="btn btn-outline-secondary me-2" id="resetSnoozeSettingsBtn">
                                    <i class="fas fa-undo me-1"></i>Reset Snooze Settings
                                </button>
                                <button type="button" class="btn btn-warning" id="saveSnoozeSettingsBtn">
                                    <i class="fas fa-save me-1"></i>Save Snooze Settings
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Add Reminder Modal -->
    <div class="modal fade" id="addReminderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Reminder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="reminderForm">
                    <div class="modal-body">
                        <!-- Reminder Type Tabs -->
                        <div class="reminder-type-tabs mb-3">
                            <button type="button" class="btn btn-outline-primary reminder-type-tab active me-2" data-type="single">
                                <i class="fas fa-calendar-day me-1"></i> Single Day
                            </button>
                            <button type="button" class="btn btn-outline-primary reminder-type-tab me-2" data-type="multiple_days">
                                <i class="fas fa-calendar-check me-1"></i> Multiple Days
                            </button>
                            <button type="button" class="btn btn-outline-primary reminder-type-tab" data-type="recurring">
                                <i class="fas fa-redo me-1"></i> Recurring
                            </button>
                        </div>

                        <input type="hidden" name="reminder_type" id="reminder_type" value="single">

                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Title *</label>
                                    <input type="text" class="form-control" name="title" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="form-select" name="priority">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>

                        <!-- Single Day Options -->
                        <div id="single-day-options">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Date *</label>
                                        <input type="date" class="form-control" name="single_reminder_date" id="single_date" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Time *</label>
                                        <input type="time" class="form-control" name="single_reminder_time" id="single_time" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Multiple Days Options -->
                        <div id="multiple-days-options" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Select Multiple Dates *</label>
                                <input type="text" class="form-control" id="multiple-date-picker"
                                       placeholder="Click to select multiple dates">
                                <input type="hidden" name="selected_dates" id="selected_dates">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Selected Dates:</label>
                                <div class="selected-dates" id="selected-dates-display">
                                    <small class="text-muted">No dates selected</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Time *</label>
                                <input type="time" class="form-control" name="multiple_reminder_time" id="multiple_time">
                            </div>
                        </div>

                        <!-- Recurring Options -->
                        <div id="recurring-options" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Start Date *</label>
                                        <input type="date" class="form-control" name="recurring_reminder_date" id="recurring_date">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Time *</label>
                                        <input type="time" class="form-control" name="recurring_reminder_time" id="recurring_time">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Repeat every (days)</label>
                                        <input type="number" class="form-control" name="recurring_days" min="1" value="1">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php
                                        $stmt = $dbh->query("SELECT * FROM reminder_categories ORDER BY is_default DESC, name ASC");
                                        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($categories as $cat):
                                            ?>
                                            <option value="<?= $cat['name'] ?>" data-icon="<?= $cat['icon'] ?>" data-color="<?= $cat['color'] ?>">
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Advance Notification</label>
                                    <select class="form-select" name="advance_notification">
                                        <option value="0">None</option>
                                        <option value="30">30 minutes before</option>
                                        <option value="60">1 hour before</option>
                                        <option value="120">2 hours before</option>
                                        <option value="240">4 hours before</option>
                                        <option value="1440">1 day before</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Frequency</label>
                            <select class="form-select" name="email_frequency">
                                <option value="none">No Email</option>
                                <option value="once">Once</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Reminder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Reminder Modal -->
    <div class="modal fade" id="editReminderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Reminder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editReminderForm">
                    <input type="hidden" name="id" id="edit_reminder_id">
                    <input type="hidden" name="original_type" id="edit_original_type">
                    <div class="modal-body">
                        <!-- Reminder Type Display -->
                        <div class="mb-3">
                            <label class="form-label">Reminder Type</label>
                            <div class="alert alert-info d-flex align-items-center" id="edit_type_display">
                                <i class="fas fa-info-circle me-2"></i>
                                <span id="edit_type_text">Loading...</span>
                            </div>
                        </div>

                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Title *</label>
                                    <input type="text" class="form-control" name="title" id="edit_title" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="form-select" name="priority" id="edit_priority">
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>

                        <!-- Single Day Editing -->
                        <div id="edit_single_day" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Date *</label>
                                        <input type="date" class="form-control" name="reminder_date" id="edit_date">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Time *</label>
                                        <input type="time" class="form-control" name="reminder_time" id="edit_time">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recurring Reminder Editing -->
                        <div id="edit_recurring" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Start Date *</label>
                                        <input type="date" class="form-control" name="recurring_start_date" id="edit_recurring_start_date">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Time *</label>
                                        <input type="time" class="form-control" name="recurring_time" id="edit_recurring_time">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Repeat every (days)</label>
                                        <input type="number" class="form-control" name="recurring_days" id="edit_recurring_days" min="1">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="recurring_end_date" id="edit_recurring_end_date">
                                    </div>
                                </div>
                            </div>

                            <!-- Recurring Instances Management -->
                            <div class="mb-3">
                                <label class="form-label">Manage Instances</label>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Note:</strong> Changes to recurring settings will regenerate all future instances. Completed instances will be preserved.
                                </div>
                                <div id="recurring_instances_list" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                    <!-- Instances will be loaded here -->
                                </div>
                            </div>
                        </div>

                        <!-- Multiple Days Editing -->
                        <div id="edit_multiple_days" style="display: none;">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Multiple Days Reminder:</strong> This reminder was created for multiple specific dates. You can edit the basic information, but to change dates, you'll need to create a new reminder.
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Time *</label>
                                <input type="time" class="form-control" name="multiple_days_time" id="edit_multiple_days_time">
                            </div>
                        </div>

                        <!-- Common Settings -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-select" name="category" id="edit_category" required>
                                        <option value="">Select Category</option>
                                        <?php
                                        $stmt = $dbh->query("SELECT * FROM reminder_categories ORDER BY is_default DESC, name ASC");
                                        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($categories as $cat):
                                            ?>
                                            <option value="<?= $cat['name'] ?>" data-icon="<?= $cat['icon'] ?>" data-color="<?= $cat['color'] ?>">
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Advance Notification</label>
                                    <select class="form-select" name="advance_notification" id="edit_advance_notification">
                                        <option value="0">None</option>
                                        <option value="30">30 minutes before</option>
                                        <option value="60">1 hour before</option>
                                        <option value="120">2 hours before</option>
                                        <option value="240">4 hours before</option>
                                        <option value="1440">1 day before</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Frequency</label>
                            <select class="form-select" name="email_frequency" id="edit_email_frequency">
                                <option value="none">No Email</option>
                                <option value="once">Once</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Reminder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Category Management Modal -->
    <div class="modal fade" id="manageCategoriesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tags me-2"></i>Manage Categories
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Add New Category Form -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-plus me-2"></i>Add New Category</h6>
                        </div>
                        <div class="card-body">
                            <form id="addCategoryForm">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Category Name</label>
                                        <input type="text" class="form-control" id="categoryName" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Icon</label>
                                        <div class="input-group">
                                        <span class="input-group-text">
                                            <i id="selectedIcon" class="fas fa-tag"></i>
                                        </span>
                                            <input type="text" class="form-control" id="categoryIcon" value="fas fa-tag" readonly>
                                            <button type="button" class="btn btn-outline-secondary" onclick="showIconPicker()">
                                                Choose
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Color</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color" id="categoryColor" value="#007bff">
                                            <input type="text" class="form-control" id="categoryColorText" value="#007bff">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Add Category
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Existing Categories -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-list me-2"></i>Existing Categories</h6>
                        </div>
                        <div class="card-body">
                            <div id="categoriesList">
                                <!-- Categories will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Icon Picker Modal -->
    <div class="modal fade" id="iconPickerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Choose an Icon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2" id="iconGrid">
                        <!-- Icons will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editCategoryForm">
                        <input type="hidden" id="editCategoryId">
                        <div class="mb-3">
                            <label class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="editCategoryName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Icon</label>
                            <div class="input-group">
                            <span class="input-group-text">
                                <i id="editSelectedIcon" class="fas fa-tag"></i>
                            </span>
                                <input type="text" class="form-control" id="editCategoryIcon" readonly>
                                <button type="button" class="btn btn-outline-secondary" onclick="showIconPicker('edit')">
                                    Choose
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="editCategoryColor">
                                <input type="text" class="form-control" id="editCategoryColorText">
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Snooze Analytics Modal -->
    <div class="modal fade" id="snoozeAnalyticsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-line text-success me-2"></i>
                        Snooze Analytics Dashboard
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Time Period Selector -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="mb-0">Analytics Period</h6>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="analyticsPeriod" id="period7" value="7">
                            <label class="btn btn-outline-primary btn-sm" for="period7">7 Days</label>

                            <input type="radio" class="btn-check" name="analyticsPeriod" id="period30" value="30" checked>
                            <label class="btn btn-outline-primary btn-sm" for="period30">30 Days</label>

                            <input type="radio" class="btn-check" name="analyticsPeriod" id="period90" value="90">
                            <label class="btn btn-outline-primary btn-sm" for="period90">90 Days</label>
                        </div>
                    </div>

                    <!-- Summary Stats -->
                    <div class="row g-3 mb-4" id="detailedAnalyticsStats">
                        <!-- Will be populated by JavaScript -->
                    </div>

                    <!-- Charts Section -->
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Daily Snooze Activity</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="dailySnoozeChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Duration Preferences</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="durationChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Snoozed Reminders -->
                    <div class="mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Most Snoozed Reminders</h6>
                            </div>
                            <div class="card-body">
                                <div id="topSnoozedList">
                                    <!-- Will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        let selectedDates = [];
        let multipleDatePicker;
        let selectedReminders = new Set();

        // Initialize date pickers with proper timezone handling
        flatpickr("input[type='date']", {
            minDate: "today",
            dateFormat: "Y-m-d",
            // Ensure dates are handled in local timezone
            parseDate: function(datestr, format) {
                return new Date(datestr + 'T00:00:00');
            },
            formatDate: function(date, format) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
        });

        // Initialize multiple date picker
        multipleDatePicker = flatpickr("#multiple-date-picker", {
            mode: "multiple",
            minDate: "today",
            dateFormat: "Y-m-d",
            onChange: function (selectedDatesArray, dateStr, instance) {
                selectedDates = selectedDatesArray.map(date => {
                    // Ensure we get the date in local timezone without conversion
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                });
                updateSelectedDatesDisplay();
                document.getElementById('selected_dates').value = JSON.stringify(selectedDates);
            }
        });

        // Handle individual checkbox changes
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('reminder-checkbox')) {
                const reminderId = e.target.value;

                if (e.target.checked) {
                    selectedReminders.add(reminderId);
                } else {
                    selectedReminders.delete(reminderId);
                }

                updateBulkActions();
                updateSelectAllState();
            }
        });

        // Handle select all checkbox
        document.getElementById('selectAllReminders').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.reminder-checkbox');
            const isChecked = this.checked;

            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
                const reminderId = checkbox.value;

                if (isChecked) {
                    selectedReminders.add(reminderId);
                } else {
                    selectedReminders.delete(reminderId);
                }
            });

            updateBulkActions();
        });

        // Update bulk actions visibility and count
        function updateBulkActions() {
            const bulkActions = document.querySelector('.bulk-actions');
            const selectedCount = document.getElementById('selectedCount');

            if (selectedReminders.size > 0) {
                bulkActions.style.display = 'block';
                selectedCount.textContent = selectedReminders.size;
            } else {
                bulkActions.style.display = 'none';
            }
        }

        // Update select all checkbox state
        function updateSelectAllState() {
            const selectAllCheckbox = document.getElementById('selectAllReminders');
            const allCheckboxes = document.querySelectorAll('.reminder-checkbox');
            const checkedCheckboxes = document.querySelectorAll('.reminder-checkbox:checked');

            if (checkedCheckboxes.length === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedCheckboxes.length === allCheckboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
                selectAllCheckbox.checked = false;
            }
        }

        // Handle bulk delete
        document.getElementById('bulkDeleteBtn').addEventListener('click', function() {
            if (selectedReminders.size === 0) {
                showToast('Warning!', 'No reminders selected', 'warning');
                return;
            }

            // Create confirmation modal
            const confirmModal = `
        <div class="modal fade" id="bulkDeleteConfirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title text-danger">
                            <i class="fas fa-trash me-2"></i>Delete Multiple Reminders
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Are you sure you want to permanently delete <strong>${selectedReminders.size}</strong> selected reminder(s)?</p>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <small><strong>Warning:</strong> This action cannot be undone. All selected reminders will be permanently removed.</small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-danger" id="confirmBulkDeleteBtn">
                            <i class="fas fa-trash me-1"></i>Yes, Delete All
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

            // Remove existing modal if any
            const existingModal = document.getElementById('bulkDeleteConfirmModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', confirmModal);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('bulkDeleteConfirmModal'));
            modal.show();

            // Handle confirm button click
            document.getElementById('confirmBulkDeleteBtn').addEventListener('click', function() {
                const formData = new FormData();
                formData.append('action', 'bulk_delete_reminders');
                formData.append('ids', JSON.stringify([...selectedReminders]));

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            modal.hide();
                            showToast('Success!', data.message, 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('Error!', data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        showToast('Error!', 'Something went wrong!', 'danger');
                    });
            });

            // Clean up modal when hidden
            document.getElementById('bulkDeleteConfirmModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        });

        // Handle clear selection
        document.getElementById('clearSelectionBtn').addEventListener('click', function() {
            selectedReminders.clear();
            document.querySelectorAll('.reminder-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateBulkActions();
            updateSelectAllState();
        });

        // Handle per page change
        function changePerPage(perPage) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', perPage);
            url.searchParams.delete('page'); // Reset to first page
            window.location.href = url.toString();
        }

        // Handle reminder type tabs
        document.querySelectorAll('.reminder-type-tab').forEach(tab => {
            tab.addEventListener('click', function () {
                // Remove active class from all tabs
                document.querySelectorAll('.reminder-type-tab').forEach(t => t.classList.remove('active'));
                // Add active class to clicked tab
                this.classList.add('active');

                const type = this.dataset.type;
                document.getElementById('reminder_type').value = type;

                // Hide all option divs
                document.getElementById('single-day-options').style.display = 'none';
                document.getElementById('multiple-days-options').style.display = 'none';
                document.getElementById('recurring-options').style.display = 'none';

                // Show relevant options
                if (type === 'single') {
                    document.getElementById('single-day-options').style.display = 'block';
                    document.getElementById('single_time').required = true;
                    document.getElementById('multiple_time').required = false;
                    document.getElementById('recurring_time').required = false;
                } else if (type === 'multiple_days') {
                    document.getElementById('multiple-days-options').style.display = 'block';
                    document.getElementById('single_time').required = false;
                    document.getElementById('multiple_time').required = true;
                    document.getElementById('recurring_time').required = false;
                } else if (type === 'recurring') {
                    document.getElementById('recurring-options').style.display = 'block';
                    document.getElementById('single_time').required = false;
                    document.getElementById('multiple_time').required = false;
                    document.getElementById('recurring_time').required = true;
                }
            });
        });

        // Update selected dates display
        function updateSelectedDatesDisplay() {
            const container = document.getElementById('selected-dates-display');

            if (selectedDates.length === 0) {
                container.innerHTML = '<small class="text-muted">No dates selected</small>';
                return;
            }

            container.innerHTML = selectedDates.map(date => {
                const formattedDate = new Date(date).toLocaleDateString();
                return `<span class="date-tag">${formattedDate}<span class="remove-date" onclick="removeDate('${date}')">&times;</span></span>`;
            }).join('');
        }

        // Remove date from selection
        function removeDate(dateToRemove) {
            selectedDates = selectedDates.filter(date => date !== dateToRemove);
            multipleDatePicker.setDate(selectedDates);
            updateSelectedDatesDisplay();
            document.getElementById('selected_dates').value = JSON.stringify(selectedDates);
        }

        // Form submission
        document.getElementById('reminderForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'add_reminder');

            // Validation for multiple days
            const reminderType = document.getElementById('reminder_type').value;
            if (reminderType === 'multiple_days' && selectedDates.length === 0) {
                showToast('Error!', 'Please select at least one date', 'danger');
                return;
            }

            // Handle different reminder types and set correct time and date values
            if (reminderType === 'multiple_days') {
                formData.delete('reminder_date'); // Remove single date field
                const timeValue = document.getElementById('multiple_time').value;
                formData.set('reminder_time', timeValue);
            } else if (reminderType === 'single') {
                // Get values from single day form
                const dateValue = document.getElementById('single_date').value;
                const timeValue = document.getElementById('single_time').value;

                if (!dateValue) {
                    showToast('Error!', 'Please select a date', 'danger');
                    return;
                }

                formData.set('reminder_date', dateValue);
                formData.set('reminder_time', timeValue);
            } else if (reminderType === 'recurring') {
                // Get values from recurring form
                const dateValue = document.getElementById('recurring_date').value;
                const timeValue = document.getElementById('recurring_time').value;

                if (!dateValue) {
                    showToast('Error!', 'Please select a start date', 'danger');
                    return;
                }

                formData.set('reminder_date', dateValue);
                formData.set('reminder_time', timeValue);
            }

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Success!', data.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('addReminderModal')).hide();
                        setTimeout(() => location.reload(), 5000);
                    } else {
                        showToast('Error!', data.message, 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error!', 'Something went wrong!', 'danger');
                });
        });

        // Reset form when modal is closed
        document.getElementById('addReminderModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('reminderForm').reset();
            selectedDates = [];
            multipleDatePicker.clear();
            updateSelectedDatesDisplay();

            // Reset to single day tab
            document.querySelectorAll('.reminder-type-tab').forEach(t => t.classList.remove('active'));
            document.querySelector('.reminder-type-tab[data-type="single"]').classList.add('active');
            document.getElementById('reminder_type').value = 'single';
            document.getElementById('single-day-options').style.display = 'block';
            document.getElementById('multiple-days-options').style.display = 'none';
            document.getElementById('recurring-options').style.display = 'none';
        });

        // Action functions
        function completeReminder(id) {
            const formData = new FormData();
            formData.append('action', 'complete_reminder');
            formData.append('id', id);

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Success!', 'Reminder marked as complete!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('Error!', data.message || 'Failed to complete reminder', 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error!', 'Something went wrong!', 'danger');
                    console.error('Error:', error);
                });
        }

        function dismissReminder(id) {
            // Create custom confirmation modal
            const confirmModal = `
        <div class="modal fade" id="dismissConfirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title text-warning">
                            <i class="fas fa-eye-slash me-2"></i>Dismiss Reminder
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Are you sure you want to dismiss this reminder?</p>
                        <div class="alert alert-warning d-flex align-items-center" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Dismissed reminders will be hidden from your list but can be restored later.</small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-warning" id="confirmDismissBtn">
                            <i class="fas fa-eye-slash me-1"></i>Yes, Dismiss
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

            // Remove existing modal if any
            const existingModal = document.getElementById('dismissConfirmModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', confirmModal);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('dismissConfirmModal'));
            modal.show();

            // Handle confirm button click
            document.getElementById('confirmDismissBtn').addEventListener('click', function() {
                const formData = new FormData();
                formData.append('action', 'dismiss_reminder');
                formData.append('id', id);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            modal.hide();
                            showToast('Success!', 'Reminder dismissed!', 'success');
                            setTimeout(() => location.reload(), 5000);
                        } else {
                            showToast('Error!', 'Failed to dismiss reminder', 'danger');
                        }
                    })
                    .catch(error => {
                        showToast('Error!', 'Something went wrong!', 'danger');
                    });
            });

            // Clean up modal when hidden
            document.getElementById('dismissConfirmModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        function dismissReminderInstance(instanceId) {
            // Create custom confirmation modal
            const confirmModal = `
        <div class="modal fade" id="dismissConfirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title text-warning">
                            <i class="fas fa-eye-slash me-2"></i>Dismiss Reminder
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Are you sure you want to dismiss this reminder?</p>
                        <div class="alert alert-warning d-flex align-items-center" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Dismissed reminders will be hidden from your list but can be restored later.</small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-warning" id="confirmDismissBtn">
                            <i class="fas fa-eye-slash me-1"></i>Yes, Dismiss
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

            // Remove existing modal if any
            const existingModal = document.getElementById('dismissConfirmModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', confirmModal);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('dismissConfirmModal'));
            modal.show();

            // Handle confirm button click
            document.getElementById('confirmDismissBtn').addEventListener('click', function() {
                const formData = new FormData();
                formData.append('action', 'dismiss_reminder_instance');
                formData.append('instance_id', instanceId);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            modal.hide();
                            showToast('Success!', 'Reminder dismissed!', 'success');
                            setTimeout(() => location.reload(), 5000);
                        } else {
                            showToast('Error!', 'Failed to dismiss reminder', 'danger');
                        }
                    })
                    .catch(error => {
                        showToast('Error!', 'Something went wrong!', 'danger');
                    });
            });

            // Clean up modal when hidden
            document.getElementById('dismissConfirmModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        function deleteReminder(id) {
            // Create custom confirmation modal
            const confirmModal = `
        <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title text-danger">
                            <i class="fas fa-trash me-2"></i>Delete Reminder
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Are you sure you want to permanently delete this reminder?</p>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <small><strong>Warning:</strong> This action cannot be undone. The reminder will be permanently removed from your account.</small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                            <i class="fas fa-trash me-1"></i>Yes, Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

            // Remove existing modal if any
            const existingModal = document.getElementById('deleteConfirmModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', confirmModal);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            modal.show();

            // Handle confirm button click
            document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
                const formData = new FormData();
                formData.append('action', 'delete_reminder');
                formData.append('id', id);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            modal.hide();
                            showToast('Success!', 'Reminder deleted!', 'success');
                            setTimeout(() => location.reload(), 5000);
                        } else {
                            showToast('Error!', 'Failed to delete reminder', 'danger');
                        }
                    })
                    .catch(error => {
                        showToast('Error!', 'Something went wrong!', 'danger');
                    });
            });

            // Clean up modal when hidden
            document.getElementById('deleteConfirmModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        function completeReminderInstance(instanceId) {
            const formData = new FormData();
            formData.append('action', 'complete_reminder_instance');
            formData.append('instance_id', instanceId);

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Success!', 'Reminder instance completed!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('Error!', data.message || 'Failed to complete reminder instance', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error!', 'An error occurred', 'error');
                });
        }

        // Updated edit reminder function
        function editReminder(id) {
            const formData = new FormData();
            formData.append('action', 'get_reminder_details');
            formData.append('id', id);

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const reminder = data.reminder;
                        const type = data.type;
                        const instances = data.instances || [];
                        const selectedDates = data.selected_dates || [];

                        // Fill basic information
                        document.getElementById('edit_reminder_id').value = reminder.id;
                        document.getElementById('edit_title').value = reminder.title;
                        document.getElementById('edit_description').value = reminder.description || '';
                        document.getElementById('edit_priority').value = reminder.priority;
                        document.getElementById('edit_category').value = reminder.category;
                        document.getElementById('edit_advance_notification').value = reminder.advance_notification;
                        document.getElementById('edit_email_frequency').value = reminder.email_frequency;

                        // Store the reminder type for form submission
                        document.getElementById('editReminderForm').dataset.reminderType = type;
                        document.getElementById('edit_original_type').value = type;

                        // Hide all edit sections first
                        document.getElementById('edit_single_day').style.display = 'none';
                        document.getElementById('edit_recurring').style.display = 'none';
                        document.getElementById('edit_multiple_days').style.display = 'none';

                        // Show appropriate section based on type
                        if (type === 'recurring') {
                            document.getElementById('edit_type_text').innerHTML = '<i class="fas fa-redo me-2"></i>Recurring Reminder';
                            document.getElementById('edit_recurring').style.display = 'block';

                            document.getElementById('edit_recurring_start_date').value = reminder.reminder_date;
                            document.getElementById('edit_recurring_time').value = reminder.reminder_time;
                            document.getElementById('edit_recurring_days').value = reminder.recurring_days;
                            document.getElementById('edit_recurring_end_date').value = reminder.end_date || '';

                            // Load instances
                            loadRecurringInstances(instances);

                        } else if (type === 'multiple_days') {
                            document.getElementById('edit_type_text').innerHTML = `<i class="fas fa-calendar-check me-2"></i>Multiple Days Reminder (${selectedDates.length} dates)`;
                            document.getElementById('edit_multiple_days').style.display = 'block';
                            document.getElementById('edit_multiple_days_time').value = reminder.reminder_time;

                            // Show selected dates info
                            const datesInfo = selectedDates.map(date => new Date(date).toLocaleDateString()).join(', ');
                            const alertDiv = document.querySelector('#edit_multiple_days .alert');
                            alertDiv.innerHTML = `
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Multiple Days Reminder:</strong> This reminder is set for the following dates: ${datesInfo}.
                    You can edit the basic information and time, but to change dates, you'll need to create a new reminder.
                `;

                        } else {
                            document.getElementById('edit_type_text').innerHTML = '<i class="fas fa-calendar-day me-2"></i>Single Day Reminder';
                            document.getElementById('edit_single_day').style.display = 'block';
                            document.getElementById('edit_date').value = reminder.reminder_date;
                            document.getElementById('edit_time').value = reminder.reminder_time;
                        }

                        new bootstrap.Modal(document.getElementById('editReminderModal')).show();
                    } else {
                        showToast('Error!', data.message, 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error!', 'Failed to load reminder details', 'danger');
                    console.error('Error:', error);
                });
        }

        function loadRecurringInstances(instances) {
            const container = document.getElementById('recurring_instances_list');

            if (instances.length === 0) {
                container.innerHTML = '<p class="text-muted mb-0">No instances found</p>';
                return;
            }

            const instancesHtml = instances.map(instance => {
                const date = new Date(instance.instance_date + ' ' + instance.instance_time);
                const isCompleted = instance.is_completed == 1;
                const isDismissed = instance.is_dismissed == 1;

                let statusBadge = '<span class="badge bg-primary">Pending</span>';
                if (isCompleted) {
                    statusBadge = '<span class="badge bg-success">Completed</span>';
                } else if (isDismissed) {
                    statusBadge = '<span class="badge bg-warning">Dismissed</span>';
                }

                return `
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div>
                    <strong>${date.toLocaleDateString()}</strong> at ${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                </div>
                <div>
                    ${statusBadge}
                </div>
            </div>
        `;
            }).join('');

            container.innerHTML = instancesHtml;
        }

        // Updated edit form submission
        document.getElementById('editReminderForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const reminderType = this.dataset.reminderType || document.getElementById('edit_original_type').value || 'single';

            // Determine which action to use based on reminder type
            let action = 'update_reminder'; // default for single reminders

            if (reminderType === 'recurring') {
                action = 'update_recurring_reminder';
                // Map the form fields to the expected parameter names
                formData.set('reminder_date', document.getElementById('edit_recurring_start_date').value);
                formData.set('reminder_time', document.getElementById('edit_recurring_time').value);
                formData.set('recurring_days', document.getElementById('edit_recurring_days').value);
                formData.set('end_date', document.getElementById('edit_recurring_end_date').value);

                formData.append('action', action);
                submitEditForm(formData, action);

            } else if (reminderType === 'multiple_days') {
                action = 'update_multiple_days_reminder';

                // For multiple days, we need to get the current selected dates
                const currentId = document.getElementById('edit_reminder_id').value;

                // Create a new FormData for the get request
                const getFormData = new FormData();
                getFormData.append('action', 'get_reminder_details');
                getFormData.append('id', currentId);

                // Get current dates and update with new time
                fetch('', {
                    method: 'POST',
                    body: getFormData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.selected_dates) {
                            formData.set('selected_dates', JSON.stringify(data.selected_dates));
                            formData.set('reminder_time', document.getElementById('edit_multiple_days_time').value);
                            formData.append('action', action);

                            // Submit the form
                            submitEditForm(formData, action);
                        } else {
                            showToast('Error!', 'Failed to get current dates for multiple days reminder', 'danger');
                        }
                    })
                    .catch(error => {
                        showToast('Error!', 'Failed to update multiple days reminder', 'danger');
                        console.error('Error:', error);
                    });
                return; // Exit early since we're handling submission in the callback

            } else {
                // Single day reminder
                formData.append('action', action);
                submitEditForm(formData, action);
            }
        });

        // Helper function to submit the edit form
        function submitEditForm(formData, action) {
            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Success!', data.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('editReminderModal')).hide();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('Error!', data.message, 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error!', 'Something went wrong!', 'danger');
                    console.error('Error:', error);
                });
        }

        // Toast function
        function showToast(title, message, type = 'info') {
            const toastId = 'toast-' + Date.now();
            const toast = `
                            <div class="toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
                                <div class="toast-body bg-${type} text-white d-flex align-items-center justify-content-between p-3">
                                    <div class="d-flex align-items-center">
                                        <strong class="me-2">${title}</strong>
                                        <span>${message}</span>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                            </div>
                            `;

            document.getElementById('toast-container').insertAdjacentHTML('beforeend', toast);
            const toastElement = new bootstrap.Toast(document.getElementById(toastId));
            toastElement.show();
        }


        // Check for due reminders every minute
        setInterval(checkDueReminders, 600000);

        function checkDueReminders() {
            fetch('check_reminder')
                .then(response => response.json())
                .then(data => {
                    data.forEach(reminder => {
                        // Calculate time remaining
                        const reminderDateTime = new Date(reminder.reminder_date + ' ' + reminder.reminder_time);
                        const now = new Date();
                        const timeDiff = reminderDateTime - now;
                        const hoursRemaining = timeDiff / (1000 * 60 * 60);

                        // Show persistent toast for reminders with less than 10 hours remaining
                        if (hoursRemaining <= 10 && hoursRemaining > 0 && !reminder.is_completed) {
                            showUrgentReminderToast(reminder, hoursRemaining);
                        }
                    });
                });
        }

        function completeReminderFromToast(id, toastId) {
            // Get the reminder data to check if it's an instance
            const toastElement = document.getElementById(toastId);
            const isInstance = toastElement.dataset.isInstance === 'true';
            const instanceId = toastElement.dataset.instanceId;

            const formData = new FormData();

            if (isInstance) {
                formData.append('action', 'complete_reminder_instance');
                formData.append('instance_id', instanceId);
                formData.append('reminder_id', id);
            } else {
                formData.append('action', 'complete_reminder');
                formData.append('id', id);
            }

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the urgent toast
                        const toast = document.getElementById(toastId);
                        if (toast) {
                            bootstrap.Toast.getInstance(toast).hide();
                            setTimeout(() => toast.remove(), 300);
                        }
                        const message = isInstance ? 'Reminder instance marked as complete!' : 'Reminder marked as complete!';
                        showToast('Success!', message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    }
                });
        }

        function dismissReminderFromToast(id, toastId) {
            // Get the reminder data to check if it's an instance
            const toastElement = document.getElementById(toastId);
            const isInstance = toastElement.dataset.isInstance === 'true';
            const instanceId = toastElement.dataset.instanceId;

            // Create confirmation modal for dismiss from toast
            const confirmModal = `
        <div class="modal fade" id="dismissFromToastModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title text-warning">
                            <i class="fas fa-eye-slash me-2"></i>Dismiss Urgent Reminder
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Are you sure you want to dismiss this urgent reminder?</p>
                        <div class="alert alert-warning d-flex align-items-center" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>${isInstance ? 'This will dismiss only this instance of the recurring reminder.' : 'This reminder is due soon. Dismissing will hide it from your urgent notifications.'}</small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-warning" id="confirmDismissFromToastBtn">
                            <i class="fas fa-eye-slash me-1"></i>Yes, Dismiss
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

            document.body.insertAdjacentHTML('beforeend', confirmModal);
            const modal = new bootstrap.Modal(document.getElementById('dismissFromToastModal'));
            modal.show();

            document.getElementById('confirmDismissFromToastBtn').addEventListener('click', function() {
                const formData = new FormData();

                if (isInstance) {
                    formData.append('action', 'dismiss_reminder_instance');
                    formData.append('instance_id', instanceId);
                    formData.append('reminder_id', id);
                } else {
                    formData.append('action', 'dismiss_reminder');
                    formData.append('id', id);
                }

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            modal.hide();
                            // Remove the urgent toast
                            const toast = document.getElementById(toastId);
                            if (toast) {
                                bootstrap.Toast.getInstance(toast).hide();
                                setTimeout(() => toast.remove(), 300);
                            }
                            const message = isInstance ? 'Reminder instance dismissed!' : 'Urgent reminder dismissed!';
                            showToast('Success!', message, 'success');
                            setTimeout(() => location.reload(), 1500);
                        }
                    });
            });

            document.getElementById('dismissFromToastModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        function showUrgentReminderToast(reminder, hoursRemaining) {
            const toastId = 'urgent-toast-' + reminder.id + (reminder.is_instance ? '-instance-' + reminder.instance_id : '');

            // Check if toast already exists for this reminder
            if (document.getElementById(toastId)) {
                return; // Don't create duplicate toasts
            }

            // Format time remaining
            let timeText = '';
            if (hoursRemaining < 1) {
                const minutesRemaining = Math.floor((hoursRemaining * 60));
                timeText = minutesRemaining > 0 ? `${minutesRemaining} minutes` : 'Less than a minute';
            } else {
                const hours = Math.floor(hoursRemaining);
                const minutes = Math.floor((hoursRemaining - hours) * 60);
                timeText = minutes > 0 ? `${hours}h ${minutes}m` : `${hours} hours`;
            }

            const priorityClass = reminder.priority === 'high' ? 'danger' :
                reminder.priority === 'medium' ? 'warning' : 'info';

            const recurringBadge = reminder.is_recurring ?
                `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
            <i class="fas fa-redo me-1"></i>${reminder.is_instance ? 'Recurring Instance' : 'Recurring'}
        </span>` : '';

            const toast = `
    <div class="toast urgent-reminder-toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true"
         data-bs-autohide="false" data-is-instance="${reminder.is_instance || false}"
         data-instance-id="${reminder.instance_id || ''}">
        <div class="toast-header bg-${priorityClass} text-white">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong class="me-auto">Urgent Reminder</strong>
            <small class="text-white-50">${timeText} remaining</small>
            <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            <div class="d-flex align-items-start">
                <div class="flex-grow-1">
                    <h6 class="mb-1 fw-bold">${reminder.title}</h6>
                    ${reminder.description ? `<p class="mb-2 text-muted small">${reminder.description}</p>` : ''}
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-${priorityClass} bg-opacity-10 text-${priorityClass} border border-${priorityClass} border-opacity-25">
                            <i class="fas fa-flag me-1"></i>${reminder.priority.charAt(0).toUpperCase() + reminder.priority.slice(1)}
                        </span>
                        <span class="badge bg-light text-dark border">
                            <i class="fas fa-tag me-1"></i>${reminder.category.charAt(0).toUpperCase() + reminder.category.slice(1)}
                        </span>
                        ${recurringBadge}
                    </div>
                    <div class="d-flex align-items-center text-muted small mb-2">
                        <i class="fas fa-calendar me-1"></i>
                        <span>${new Date(reminder.reminder_date + ' ' + reminder.reminder_time).toLocaleString()}</span>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-success btn-sm flex-fill" onclick="completeReminderFromToast(${reminder.id}, '${toastId}')">
                    <i class="fas fa-check me-1"></i>Mark Complete
                </button>
                <button class="btn btn-warning btn-sm flex-fill" onclick="dismissReminderFromToast(${reminder.id}, '${toastId}')">
                    <i class="fas fa-eye-slash me-1"></i>Dismiss
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="snoozeReminder(${reminder.id}, '${toastId}')">
                    <i class="fas fa-clock me-1"></i>Snooze
                </button>
            </div>
        </div>
    </div>
    `;

            document.getElementById('toast-container').insertAdjacentHTML('beforeend', toast);
            const toastElement = new bootstrap.Toast(document.getElementById(toastId));
            toastElement.show();
        }

        function snoozeReminder(id, toastId) {
            // Create snooze options modal
            const snoozeModal = `
        <div class="modal fade" id="snoozeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title text-info">
                            <i class="fas fa-clock me-2"></i>Snooze Reminder
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">How long would you like to snooze this reminder?</p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" onclick="applySnooze(${id}, '${toastId}', 15)">
                                <i class="fas fa-clock me-2"></i>15 minutes
                            </button>
                            <button class="btn btn-outline-primary" onclick="applySnooze(${id}, '${toastId}', 30)">
                                <i class="fas fa-clock me-2"></i>30 minutes
                            </button>
                            <button class="btn btn-outline-primary" onclick="applySnooze(${id}, '${toastId}', 60)">
                                <i class="fas fa-clock me-2"></i>1 hour
                            </button>
                            <button class="btn btn-outline-primary" onclick="applySnooze(${id}, '${toastId}', 120)">
                                <i class="fas fa-clock me-2"></i>2 hours
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

            document.body.insertAdjacentHTML('beforeend', snoozeModal);
            const modal = new bootstrap.Modal(document.getElementById('snoozeModal'));
            modal.show();

            document.getElementById('snoozeModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        function applySnooze(id, toastId, minutes) {
            // Hide the urgent toast temporarily
            const toast = document.getElementById(toastId);
            if (toast) {
                bootstrap.Toast.getInstance(toast).hide();
                setTimeout(() => toast.remove(), 300);
            }

            // Close snooze modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('snoozeModal'));
            modal.hide();

            showToast('Snoozed!', `Reminder snoozed for ${minutes} minutes`, 'info');

            // Set timeout to show the toast again after snooze period
            setTimeout(() => {
                checkDueReminders(); // This will re-check and show the toast if still urgent
            }, minutes * 60 * 1000);
        }

        function restoreReminder(id, sourceType = 'reminder', instanceId = null) {
            const formData = new FormData();
            formData.append('action', 'restore_reminder');
            formData.append('id', id);
            formData.append('source_type', sourceType);

            if (instanceId) {
                formData.append('instance_id', instanceId);
            }

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Success!', data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('Error!', 'Failed to restore reminder', 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error!', 'Something went wrong!', 'danger');
                });
        }

        // Initial check
        checkDueReminders();

        // Available icons for categories
        const availableIcons = [
            'fas fa-briefcase', 'fas fa-user', 'fas fa-home', 'fas fa-heart', 'fas fa-star',
            'fas fa-graduation-cap', 'fas fa-car', 'fas fa-plane', 'fas fa-shopping-cart', 'fas fa-gift',
            'fas fa-camera', 'fas fa-music', 'fas fa-book', 'fas fa-gamepad', 'fas fa-coffee',
            'fas fa-utensils', 'fas fa-dumbbell', 'fas fa-running', 'fas fa-bicycle', 'fas fa-swimmer',
            'fas fa-dollar-sign', 'fas fa-tag', 'fas fa-book-open', 'fas fa-university', 'fas fa-school',
            'fas fa-chalkboard-teacher', 'fas fa-pencil-alt', 'fas fa-pen', 'fas fa-highlighter', 'fas fa-bookmark',
            'fas fa-certificate', 'fas fa-award', 'fas fa-trophy', 'fas fa-brain', 'fas fa-lightbulb',
            'fas fa-microscope', 'fas fa-flask', 'fas fa-calculator', 'fas fa-globe', 'fas fa-language',
            'fas fa-laptop', 'fas fa-desktop', 'fas fa-tablet-alt', 'fas fa-file-alt', 'fas fa-clipboard-list',
            'fas fa-calendar-alt', 'fas fa-clock', 'fas fa-stopwatch', 'fas fa-chart-line', 'fas fa-puzzle-piece',
            'fas fa-cogs', 'fas fa-atom', 'fas fa-dna', 'fas fa-seedling', 'fas fa-palette',
            'fas fa-video', 'fas fa-headphones', 'fas fa-comments', 'fas fa-users', 'fas fa-user-graduate',
            'fas fa-chalkboard', 'fas fa-ruler', 'fas fa-compass', 'fas fa-map', 'fas fa-search',
            'fas fa-question-circle', 'fas fa-exclamation-circle', 'fas fa-info-circle'
        ];

        // Load categories when modal opens
        $('#manageCategoriesModal').on('show.bs.modal', function() {
            loadCategories();
        });

        // Load categories list
        function loadCategories() {
            $.post('reminders', {
                action: 'get_categories'
            }, function(response) {
                if (response.success) {
                    displayCategories(response.categories);
                } else {
                    showToast('Error', 'Failed to load categories', 'danger');
                }
            }, 'json').fail(function() {
                showToast('Error', 'Failed to connect to server', 'danger');
            });
        }

        // Display categories in the list
        function displayCategories(categories) {
            let html = '';
            categories.forEach(category => {
                html += `
            <div class="d-flex align-items-center justify-content-between p-3 border rounded mb-2">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="${category.icon}" style="color: ${category.color}; font-size: 1.2rem;"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">${category.name}</h6>
                        <small class="text-muted">${category.icon} • ${category.color}</small>
                    </div>
                    ${category.is_default ? '<span class="badge badge-subtle-info ms-2">Default</span>' : ''}
                </div>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="editCategory(${category.id}, '${category.name}', '${category.icon}', '${category.color}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${!category.is_default ? `
                        <button class="btn btn-outline-danger" onclick="deleteCategory(${category.id}, '${category.name}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
            });
            $('#categoriesList').html(html);
        }

        // Show icon picker
        function showIconPicker(mode = 'add') {
            let html = '';
            availableIcons.forEach(icon => {
                html += `
            <div class="col-2 col-md-1">
                <button type="button" class="btn btn-outline-secondary w-100 p-2" onclick="selectIcon('${icon}', '${mode}')">
                    <i class="${icon}"></i>
                </button>
            </div>
        `;
            });
            $('#iconGrid').html(html);
            $('#iconPickerModal').modal('show');
        }

        // Select icon
        function selectIcon(icon, mode) {
            if (mode === 'edit') {
                $('#editCategoryIcon').val(icon);
                $('#editSelectedIcon').attr('class', icon);
            } else {
                $('#categoryIcon').val(icon);
                $('#selectedIcon').attr('class', icon);
            }
            $('#iconPickerModal').modal('hide');
        }

        // Color picker sync
        $('#categoryColor').on('input', function() {
            $('#categoryColorText').val($(this).val());
        });

        $('#categoryColorText').on('input', function() {
            $('#categoryColor').val($(this).val());
        });

        $('#editCategoryColor').on('input', function() {
            $('#editCategoryColorText').val($(this).val());
        });

        $('#editCategoryColorText').on('input', function() {
            $('#editCategoryColor').val($(this).val());
        });

        // Add category form submission
        $('#addCategoryForm').on('submit', function(e) {
            e.preventDefault();

            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Adding...').prop('disabled', true);

            const formData = {
                action: 'add_category',
                name: $('#categoryName').val(),
                icon: $('#categoryIcon').val(),
                color: $('#categoryColor').val()
            };

            $.post('reminders', formData, function(response) {
                if (response.success) {
                    // Reset form
                    $('#addCategoryForm')[0].reset();
                    $('#categoryIcon').val('fas fa-tag');
                    $('#selectedIcon').attr('class', 'fas fa-tag');
                    $('#categoryColor').val('#007bff');
                    $('#categoryColorText').val('#007bff');

                    // Reload categories
                    loadCategories();

                    // Show success toast
                    showToast('Success', 'Category added!', 'success');
                } else {
                    showToast('Error', response.message || 'Error adding category', 'danger');
                }
            }, 'json').fail(function() {
                showToast('Error', 'Failed to connect to server', 'danger');
            }).always(function() {
                submitBtn.html(originalText).prop('disabled', false);
            });
        });

        // Edit category
        function editCategory(id, name, icon, color) {
            $('#editCategoryId').val(id);
            $('#editCategoryName').val(name);
            $('#editCategoryIcon').val(icon);
            $('#editSelectedIcon').attr('class', icon);
            $('#editCategoryColor').val(color);
            $('#editCategoryColorText').val(color);
            $('#editCategoryModal').modal('show');
        }

        // Edit category form submission
        $('#editCategoryForm').on('submit', function(e) {
            e.preventDefault();

            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...').prop('disabled', true);

            const formData = {
                action: 'update_category',
                id: $('#editCategoryId').val(),
                name: $('#editCategoryName').val(),
                icon: $('#editCategoryIcon').val(),
                color: $('#editCategoryColor').val()
            };

            $.post('reminders', formData, function(response) {
                if (response.success) {
                    $('#editCategoryModal').modal('hide');
                    loadCategories();
                    showToast('Success', 'Category updated!', 'success');
                } else {
                    showToast('Error', response.message || 'Error updating category', 'danger');
                }
            }, 'json').fail(function() {
                showToast('Error', 'Failed to connect to server', 'danger');
            }).always(function() {
                submitBtn.html(originalText).prop('disabled', false);
            });
        });

        // Delete category
        function deleteCategory(id, name) {
            // Create a custom confirmation toast instead of browser confirm
            const confirmToastId = 'confirm-toast-' + Date.now();
            const confirmToast = `
        <div class="toast" id="${confirmToastId}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
            <div class="toast-body bg-warning text-dark d-flex flex-column p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <strong>Confirm Delete</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <p class="mb-3">Are you sure you want to delete the category "${name}"? This action cannot be undone.</p>
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="dismissConfirmToast('${confirmToastId}')">Cancel</button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteCategory(${id}, '${confirmToastId}')">
                        <i class="fas fa-trash me-1"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    `;

            document.getElementById('toast-container').insertAdjacentHTML('beforeend', confirmToast);
            const toastElement = new bootstrap.Toast(document.getElementById(confirmToastId));
            toastElement.show();
        }

        // Dismiss confirmation toast
        function dismissConfirmToast(toastId) {
            const toastElement = bootstrap.Toast.getInstance(document.getElementById(toastId));
            if (toastElement) {
                toastElement.hide();
            }
        }

        // Confirm delete category
        function confirmDeleteCategory(id, toastId) {
            // Dismiss confirmation toast
            dismissConfirmToast(toastId);

            // Show loading toast
            showToast('Deleting', 'Deleting category...', 'info');

            $.post('reminders', {
                action: 'delete_category',
                id: id
            }, function(response) {
                if (response.success) {
                    loadCategories();
                    showToast('Success', 'Category deleted!', 'success');
                } else {
                    showToast('Error', response.message || 'Error deleting category', 'danger');
                }
            }, 'json').fail(function() {
                showToast('Error', 'Failed to connect to server', 'danger');
            });
        }

    </script>
    <script>
        // Calendar functionality
        let reminderCalendar;

        // Fetch reminders data for calendar
        function fetchRemindersForCalendar() {
            return fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_calendar_reminders'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        return data.reminders.map(reminder => {
                            // Determine status and badge info
                            const reminderDateTime = new Date(reminder.reminder_date + ' ' + reminder.reminder_time);
                            const now = new Date();
                            const today = new Date();
                            today.setHours(0, 0, 0, 0);
                            const reminderDate = new Date(reminder.reminder_date);
                            reminderDate.setHours(0, 0, 0, 0);

                            let status = 'upcoming';
                            let badgeIcon = 'U';
                            let backgroundColor = '';
                            let borderColor = '';
                            let textColor = '';

                            // Determine status and Bootstrap badge colors
                            if (reminder.is_dismissed == 1) {
                                status = 'dismissed';
                                badgeIcon = 'D';
                                backgroundColor = 'var(--bs-warning)';
                                borderColor = 'var(--bs-warning)';
                                textColor = 'var(--bs-dark)';
                            } else if (reminder.is_completed == 1) {
                                status = 'completed';
                                badgeIcon = '✓';
                                backgroundColor = 'var(--bs-success)';
                                borderColor = 'var(--bs-success)';
                                textColor = 'white';
                            } else if (reminderDateTime < now) {
                                status = 'overdue';
                                badgeIcon = '!';
                                backgroundColor = 'var(--bs-danger)';
                                borderColor = 'var(--bs-danger)';
                                textColor = 'white';
                            } else if (reminderDate.getTime() === today.getTime()) {
                                status = 'today';
                                badgeIcon = 'T';
                                backgroundColor = 'var(--bs-info)';
                                borderColor = 'var(--bs-info)';
                                textColor = 'white';
                            } else {
                                // Upcoming - use priority colors
                                switch (reminder.priority) {
                                    case 'high':
                                        backgroundColor = 'var(--bs-danger)';
                                        borderColor = 'var(--bs-danger)';
                                        textColor = 'white';
                                        break;
                                    case 'medium':
                                        backgroundColor = 'var(--bs-warning)';
                                        borderColor = 'var(--bs-warning)';
                                        textColor = 'var(--bs-dark)';
                                        break;
                                    case 'low':
                                        backgroundColor = 'var(--bs-secondary)';
                                        borderColor = 'var(--bs-secondary)';
                                        textColor = 'white';
                                        break;
                                    default:
                                        backgroundColor = 'var(--bs-primary)';
                                        borderColor = 'var(--bs-primary)';
                                        textColor = 'white';
                                }
                            }

                            return {
                                id: reminder.id,
                                title: reminder.title,
                                start: reminder.reminder_date + 'T' + reminder.reminder_time,
                                end: reminder.reminder_date + 'T23:59:59', // Ensure event ends on same day
                                backgroundColor: backgroundColor,
                                borderColor: borderColor,
                                textColor: textColor,
                                extendedProps: {
                                    description: reminder.description,
                                    priority: reminder.priority,
                                    category: reminder.category,
                                    category_icon: reminder.category_icon || 'fas fa-bell', // Use category icon or fallback
                                    category_color: reminder.category_color,
                                    advance_notification: reminder.advance_notification,
                                    email_frequency: reminder.email_frequency,
                                    is_completed: reminder.is_completed,
                                    is_dismissed: reminder.is_dismissed,
                                    is_recurring: reminder.is_recurring,
                                    source_type: reminder.source_type,
                                    instance_id: reminder.instance_id,
                                    status: status,
                                    badgeIcon: badgeIcon
                                }
                            };
                        });
                    }
                    return [];
                })
                .catch(error => {
                    console.error('Error fetching reminders:', error);
                    return [];
                });
        }

        // Initialize reminder calendar when tab is shown
        document.getElementById('calendar-tab').addEventListener('shown.bs.tab', function (e) {
            if (!reminderCalendar) {
                const calendarEl = document.getElementById('reminderCalendar');

                fetchRemindersForCalendar().then(events => {
                    reminderCalendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'dayGridMonth',
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: ''
                        },
                        views: {
                            year: {
                                type: 'dayGrid',
                                duration: { years: 1 },
                                buttonText: 'Year'
                            }
                        },
                        events: events,
                        eventClick: function (info) {
                            showReminderModal(info.event);
                        },
                        eventContent: function (info) {
                            const content = document.createElement('div');
                            content.classList.add('d-flex', 'align-items-center', 'justify-content-between', 'p-1', 'rounded', 'small', 'h-100', 'w-100');

                            // Apply Bootstrap badge-subtle classes based on status
                            const reminder = info.event.extendedProps;
                            let badgeClass = '';

                            switch(reminder.status) {
                                case 'today':
                                    badgeClass = 'badge-subtle-info';
                                    break;
                                case 'completed':
                                    badgeClass = 'badge-subtle-success';
                                    break;
                                case 'overdue':
                                    badgeClass = 'badge-subtle-danger';
                                    break;
                                case 'dismissed':
                                    badgeClass = 'badge-subtle-warning';
                                    break;
                                default: // upcoming
                                    switch(reminder.priority) {
                                        case 'high':
                                            badgeClass = 'badge-subtle-danger';
                                            break;
                                        case 'medium':
                                            badgeClass = 'badge-subtle-warning';
                                            break;
                                        case 'low':
                                            badgeClass = 'badge-subtle-secondary';
                                            break;
                                        default:
                                            badgeClass = 'badge-subtle-primary';
                                    }
                            }

                            content.classList.add('badge', badgeClass);

                            // Use category icon, but override with status-specific icons when appropriate
                            let icon = reminder.category_icon || 'fas fa-bell';

                            if (reminder.is_completed) {
                                icon = 'fas fa-check-circle';
                            } else if (reminder.is_dismissed) {
                                icon = 'fas fa-eye-slash';
                            } else if (reminder.is_recurring) {
                                // Show both category icon and recurring indicator
                                icon = `${reminder.category_icon || 'fas fa-bell'} me-1"></i><i class="fas fa-redo`;
                            }

                            content.innerHTML = `
        <div class="flex-grow-1 text-truncate">
            <i class="${icon} me-1"></i><strong>${info.event.title}</strong>
        </div>
        <div class="calendar-event-badge-icon ms-1" title="Status: ${reminder.status.charAt(0).toUpperCase() + reminder.status.slice(1)}">
            ${reminder.badgeIcon}
        </div>
    `;

                            return { domNodes: [content] };
                        }
                    });

                    reminderCalendar.render();
                });

                // Handle view selector for reminders
                document.querySelectorAll('#reminder-view-selector + .dropdown-menu .dropdown-item').forEach(function(item) {
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        const view = this.getAttribute('data-fc-view');
                        reminderCalendar.changeView(view);
                        document.getElementById('reminder-current-view').textContent = this.textContent.trim();

                        document.querySelectorAll('#reminder-view-selector + .dropdown-menu .dropdown-item').forEach(function(item) {
                            item.classList.remove('active');
                        });
                        this.classList.add('active');
                    });
                });
            }
        });

        // Show reminder modal with details
        function showReminderModal(event) {
            const reminder = event.extendedProps;
            const reminderDate = new Date(event.start);
            const formattedDate = reminderDate.toLocaleDateString();
            const formattedTime = reminderDate.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });

            // Status badges
            const badges = {
                'high': '<span class="badge badge-subtle-danger">High Priority</span>',
                'medium': '<span class="badge badge-subtle-warning">Medium Priority</span>',
                'low': '<span class="badge badge-subtle-secondary">Low Priority</span>'
            };

            let statusBadge = '';
            if (reminder.is_completed) {
                statusBadge = '<span class="badge badge-subtle-success"><i class="fas fa-check me-1"></i>Completed</span>';
            } else if (reminder.is_dismissed) {
                statusBadge = '<span class="badge badge-subtle-warning"><i class="fas fa-eye-slash me-1"></i>Dismissed</span>';
            } else {
                // Show specific status based on the calculated status
                switch(reminder.status) {
                    case 'today':
                        statusBadge = '<span class="badge badge-subtle-info"><i class="fas fa-calendar-day me-1"></i>Due Today</span>';
                        break;
                    case 'overdue':
                        statusBadge = '<span class="badge badge-subtle-danger"><i class="fas fa-exclamation-triangle me-1"></i>Overdue</span>';
                        break;
                    default:
                        statusBadge = '<span class="badge badge-subtle-primary"><i class="fas fa-clock me-1"></i>Upcoming</span>';
                }
            }

            // Get category icon and color, with fallbacks
            const categoryIcon = reminder.category_icon || 'fas fa-tag';
            const categoryColor = reminder.category_color || '#007bff';

            // Convert hex color to RGB for background opacity
            const hexToRgb = (hex) => {
                const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
                return result ? {
                    r: parseInt(result[1], 16),
                    g: parseInt(result[2], 16),
                    b: parseInt(result[3], 16)
                } : {r: 0, g: 123, b: 255}; // fallback to blue
            };

            const rgb = hexToRgb(categoryColor);
            const categoryBgColor = `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.1)`;

            const reminderDetails = `
        <div class="modal-body px-card pb-card pt-1 fs-9">
            <div class="d-flex mt-3">
                <span class="fa-stack ms-n1 me-3">
                    <i class="fas fa-circle fa-stack-2x" style="color: ${categoryBgColor};"></i>
                    <i class="${categoryIcon} fa-stack-1x" style="color: ${categoryColor};"></i>
                </span>
                <div class="flex-1">
                    <h5 class="mb-1">${event.title}</h5>
                    ${reminder.description ? `<p class="text-muted mb-0">${reminder.description}</p>` : ''}
                </div>
            </div>
            <div class="d-flex mt-3">
                <span class="fa-stack ms-n1 me-3">
                    <i class="fas fa-circle fa-stack-2x text-200"></i>
                    <i class="fas fa-calendar-check fa-stack-1x text-primary"></i>
                </span>
                <div class="flex-1">
                    <p class="mb-0">Due: ${formattedDate}, ${formattedTime}</p>
                </div>
            </div>
            <div class="d-flex mt-3">
                <span class="fa-stack ms-n1 me-3">
                    <i class="fas fa-circle fa-stack-2x text-200"></i>
                    <i class="fas fa-flag fa-stack-1x text-primary"></i>
                </span>
                <div class="flex-1">
                    <p class="mb-0">${badges[reminder.priority]}</p>
                </div>
            </div>
            <div class="d-flex mt-3">
                <span class="fa-stack ms-n1 me-3">
                    <i class="fas fa-circle fa-stack-2x" style="color: ${categoryBgColor};"></i>
                    <i class="${categoryIcon} fa-stack-1x" style="color: ${categoryColor};"></i>
                </span>
                <div class="flex-1">
                    <p class="mb-0">
                        <span class="badge border-0 px-2 py-1" style="background-color: ${categoryColor}; color: white;">
                            <i class="${categoryIcon} me-1"></i>${reminder.category}
                        </span>
                    </p>
                </div>
            </div>
            <div class="d-flex mt-3">
                <span class="fa-stack ms-n1 me-3">
                    <i class="fas fa-circle fa-stack-2x text-200"></i>
                    <i class="fas fa-info-circle fa-stack-1x text-primary"></i>
                </span>
                <div class="flex-1">
                    <p class="mb-0">${statusBadge}</p>
                </div>
            </div>
            ${reminder.is_recurring ? `
            <div class="d-flex mt-3">
                <span class="fa-stack ms-n1 me-3">
                    <i class="fas fa-circle fa-stack-2x text-200"></i>
                    <i class="fas fa-redo fa-stack-1x text-primary"></i>
                </span>
                <div class="flex-1">
                    <p class="mb-0"><span class="badge badge-subtle-info">Recurring Reminder</span></p>
                </div>
            </div>
            ` : ''}
        </div>
    `;

            document.getElementById('reminderDetails').innerHTML = reminderDetails;

            // Set up action buttons
            const completeBtn = document.getElementById('completeReminderBtn');
            const dismissBtn = document.getElementById('dismissReminderBtn');
            const editBtn = document.getElementById('editReminderBtn');

            // Hide/show buttons based on status
            if (reminder.is_completed) {
                completeBtn.style.display = 'none';
            } else {
                completeBtn.style.display = 'inline-block';
                completeBtn.onclick = () => {
                    if (reminder.source_type === 'instance') {
                        completeReminderInstance(reminder.instance_id);
                    } else {
                        completeReminder(event.id);
                    }
                    bootstrap.Modal.getInstance(document.getElementById('reminderModal')).hide();
                };
            }

            if (reminder.is_dismissed) {
                dismissBtn.textContent = 'Restore';
                dismissBtn.className = 'btn btn-success';
                dismissBtn.innerHTML = '<i class="fas fa-undo me-1"></i>Restore';
                dismissBtn.onclick = () => {
                    restoreReminder(event.id, reminder.source_type, reminder.instance_id);
                    bootstrap.Modal.getInstance(document.getElementById('reminderModal')).hide();
                };
            } else {
                dismissBtn.textContent = 'Dismiss';
                dismissBtn.className = 'btn btn-warning';
                dismissBtn.innerHTML = '<i class="fas fa-eye-slash me-1"></i>Dismiss';
                dismissBtn.onclick = () => {
                    if (reminder.source_type === 'instance') {
                        dismissReminderInstance(reminder.instance_id);
                    } else {
                        dismissReminder(event.id);
                    }
                    bootstrap.Modal.getInstance(document.getElementById('reminderModal')).hide();
                };
            }

            editBtn.onclick = () => {
                bootstrap.Modal.getInstance(document.getElementById('reminderModal')).hide();
                editReminder(reminder.source_type === 'instance' ? reminder.reminder_id : event.id);
            };

            const reminderModal = new bootstrap.Modal(document.getElementById('reminderModal'));
            reminderModal.show();
        }

        // Restore reminder function
        function restoreReminder(id, sourceType, instanceId) {
            const formData = new FormData();
            formData.append('action', 'restore_reminder');
            formData.append('id', id);
            formData.append('source_type', sourceType || 'reminder');
            if (instanceId) {
                formData.append('instance_id', instanceId);
            }

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Success!', data.message, 'success');
                        // Refresh calendar
                        if (reminderCalendar) {
                            fetchRemindersForCalendar().then(events => {
                                reminderCalendar.removeAllEvents();
                                reminderCalendar.addEventSource(events);
                            });
                        }
                    } else {
                        showToast('Error!', data.message, 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error!', 'Something went wrong!', 'danger');
                });
        }
    </script>
    <script>
        // Settings form handling
        document.getElementById('reminderSettingsForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'save_reminder_settings');

            // Convert checkboxes to 1/0 values
            const checkboxes = ['morning_summary_enabled', 'evening_progress_enabled', 'due_reminders_enabled', 'send_empty_summaries'];
            checkboxes.forEach(checkbox => {
                const element = document.getElementById(checkbox);
                formData.set(checkbox, element.checked ? '1' : '0');
            });

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Success!', 'Settings saved!', 'success');
                    } else {
                        showToast('Error!', data.message || 'Failed to save settings', 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error!', 'Something went wrong!', 'danger');
                });
        });

        // Reset settings
        document.getElementById('resetSettingsBtn').addEventListener('click', function() {
            if (confirm('Are you sure you want to reset all settings to defaults?')) {
                const formData = new FormData();
                formData.append('action', 'reset_reminder_settings');

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Success!', 'Settings reset to defaults!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('Error!', 'Failed to reset settings', 'danger');
                        }
                    });
            }
        });

        // Load settings on page load
        function loadReminderSettings() {
            const formData = new FormData();
            formData.append('action', 'get_reminder_settings');

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const settings = data.settings;

                        // Set checkbox values
                        document.getElementById('morning_summary_enabled').checked = settings.morning_summary_enabled === '1';
                        document.getElementById('evening_progress_enabled').checked = settings.evening_progress_enabled === '1';
                        document.getElementById('due_reminders_enabled').checked = settings.due_reminders_enabled === '1';
                        document.getElementById('send_empty_summaries').checked = settings.send_empty_summaries === '1';

                        // Set other values
                        document.getElementById('notification_email').value = settings.notification_email || '';
                        document.getElementById('email_format').value = settings.email_format || 'html';
                    }
                });
        }

        // Load settings when settings tab is shown
        document.getElementById('settings-tab').addEventListener('shown.bs.tab', function() {
            loadReminderSettings();
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Activate tab based on URL hash on page load
            const hash = window.location.hash;
            if (hash) {
                const tabLink = document.querySelector(`a[href="${hash}"]`);
                if (tabLink) {
                    const tab = new bootstrap.Tab(tabLink);
                    tab.show();
                }
            }

            // Update URL hash when a tab is clicked
            const tabLinks = document.querySelectorAll('#reminderTabs a.nav-link');
            tabLinks.forEach(link => {
                link.addEventListener('shown.bs.tab', function (event) {
                    history.replaceState(null, null, event.target.getAttribute('href'));
                });
            });
        });
    </script>
    <script>
        // Enhanced JavaScript functions for snooze functionality
        // Add these to your existing JavaScript in reminders.php

        // Global variables for snooze
        let snoozeOptions = [];
        let maxSnoozeCount = 5;

        // Load snooze options on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSnoozeOptions();
        });

        // Load snooze options from server
        function loadSnoozeOptions() {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_snooze_options'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        snoozeOptions = data.options;
                        maxSnoozeCount = data.max_snooze_count;
                    }
                })
                .catch(error => {
                    console.error('Error loading snooze options:', error);
                });
        }

        // Enhanced snooze reminder function
        function snoozeReminder(id, toastId, reminderData = null) {
            // Get reminder data from toast element if not provided
            if (!reminderData) {
                const toastElement = document.getElementById(toastId);
                reminderData = {
                    isInstance: toastElement.dataset.isInstance === 'true',
                    instanceId: toastElement.dataset.instanceId,
                    snoozeCount: parseInt(toastElement.dataset.snoozeCount || '0')
                };
            }

            // Check if max snooze limit reached
            if (reminderData.snoozeCount >= maxSnoozeCount) {
                showAdvancedSnoozeModal(id, toastId, reminderData);
                return;
            }

            // Show snooze options modal
            showSnoozeModal(id, toastId, reminderData);
        }

        // Show snooze options modal
        function showSnoozeModal(id, toastId, reminderData) {
            // Create enhanced snooze modal
            const snoozeModal = `
        <div class="modal fade" id="snoozeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title text-info">
                            <i class="fas fa-clock me-2"></i>Snooze Reminder
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="snooze-info mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">Snooze Count:</small>
                                <span class="badge ${reminderData.snoozeCount >= maxSnoozeCount - 1 ? 'bg-warning' : 'bg-info'}">
                                    ${reminderData.snoozeCount}/${maxSnoozeCount}
                                </span>
                            </div>
                            ${reminderData.snoozeCount >= maxSnoozeCount - 1 ?
                '<div class="alert alert-warning py-2"><small><i class="fas fa-exclamation-triangle me-1"></i>Last snooze available!</small></div>' : ''
            }
                        </div>

                        <p class="mb-3">How long would you like to snooze this reminder?</p>

                        <div class="row g-2" id="snoozeOptionsGrid">
                            ${generateSnoozeOptionsHTML()}
                        </div>

                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <button class="btn btn-outline-secondary btn-sm" onclick="showSnoozeStats(${id}, ${reminderData.instanceId || 'null'})">
                                    <i class="fas fa-chart-line me-1"></i>View History
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="showCustomSnoozeInput()">
                                    <i class="fas fa-edit me-1"></i>Custom Time
                                </button>
                            </div>
                        </div>

                        <!-- Custom snooze input (initially hidden) -->
                        <div class="mt-3 d-none" id="customSnoozeSection">
                            <div class="card card-body bg-light">
                                <div class="row g-2">
                                    <div class="col-8">
                                        <input type="number" class="form-control form-control-sm" id="customSnoozeMinutes"
                                               placeholder="Minutes" min="1" max="1440">
                                    </div>
                                    <div class="col-4">
                                        <button class="btn btn-info btn-sm w-100" onclick="applyCustomSnooze(${id}, '${toastId}', ${JSON.stringify(reminderData).replace(/"/g, '&quot;')})">
                                            Apply
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted mt-1">Enter 1-1440 minutes (max 24 hours)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

            // Remove existing modal if any
            const existingModal = document.getElementById('snoozeModal');
            if (existingModal) {
                existingModal.remove();
            }

            document.body.insertAdjacentHTML('beforeend', snoozeModal);
            const modal = new bootstrap.Modal(document.getElementById('snoozeModal'));
            modal.show();

            // Add click handlers for snooze options
            snoozeOptions.forEach(option => {
                const button = document.getElementById(`snooze-${option.minutes}`);
                if (button) {
                    button.addEventListener('click', () => {
                        modal.hide();
                        applySnooze(id, toastId, option.minutes, reminderData);
                    });
                }
            });

            // Clean up modal when hidden
            document.getElementById('snoozeModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Generate snooze options HTML
        function generateSnoozeOptionsHTML() {
            return snoozeOptions.map(option => `
        <div class="col-6">
            <button type="button" class="btn btn-outline-primary w-100 py-2" id="snooze-${option.minutes}">
                <i class="${option.icon} mb-1 d-block"></i>
                <small>${option.label}</small>
            </button>
        </div>
    `).join('');
        }

        // Show custom snooze input
        function showCustomSnoozeInput() {
            const section = document.getElementById('customSnoozeSection');
            section.classList.toggle('d-none');
            if (!section.classList.contains('d-none')) {
                document.getElementById('customSnoozeMinutes').focus();
            }
        }

        // Apply custom snooze
        function applyCustomSnooze(id, toastId, reminderData) {
            const minutes = parseInt(document.getElementById('customSnoozeMinutes').value);

            if (!minutes || minutes < 1 || minutes > 1440) {
                showToast('Invalid Input', 'Please enter a value between 1 and 1440 minutes', 'warning');
                return;
            }

            const modal = bootstrap.Modal.getInstance(document.getElementById('snoozeModal'));
            modal.hide();

            applySnooze(id, toastId, minutes, reminderData);
        }

        // Enhanced apply snooze function
        function applySnooze(id, toastId, minutes, reminderData) {
            const formData = new FormData();
            formData.append('action', 'snooze_reminder');
            formData.append('id', id);
            formData.append('minutes', minutes);
            formData.append('source_type', reminderData.isInstance ? 'instance' : 'reminder');

            if (reminderData.instanceId) {
                formData.append('instance_id', reminderData.instanceId);
            }

            // Show loading state
            showToast('Snoozing...', 'Processing your snooze request', 'info');

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hide the urgent toast
                        const toast = document.getElementById(toastId);
                        if (toast) {
                            const toastInstance = bootstrap.Toast.getInstance(toast);
                            if (toastInstance) {
                                toastInstance.hide();
                            }
                            setTimeout(() => toast.remove(), 300);
                        }

                        // Show success message with snooze details
                        const snoozeUntil = new Date(data.snooze_until).toLocaleString();
                        showSnoozeSuccessToast(data.message, snoozeUntil, data.snooze_count, data.max_snoozes);

                        // Schedule re-check when snooze expires
                        const snoozeEndTime = new Date(data.snooze_until).getTime();
                        const now = new Date().getTime();
                        const timeUntilUnsnooze = snoozeEndTime - now;

                        if (timeUntilUnsnooze > 0) {
                            setTimeout(() => {
                                checkDueReminders(); // Re-check for due reminders when snooze expires
                            }, timeUntilUnsnooze + 5000); // Add 5 seconds buffer
                        }

                    } else {
                        showToast('Snooze Failed', data.message, 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error', 'Failed to snooze reminder', 'danger');
                    console.error('Snooze error:', error);
                });
        }

        // Show enhanced success toast for snooze
        function showSnoozeSuccessToast(message, snoozeUntil, snoozeCount, maxSnoozes) {
            const toastId = 'snooze-success-' + Date.now();
            const progressPercent = (snoozeCount / maxSnoozes) * 100;

            const toast = `
        <div class="toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="8000">
            <div class="toast-body bg-success text-white d-flex flex-column p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Reminder Snoozed!</strong>
                    </div>
                    <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="toast"></button>
                </div>

                <div class="mb-2">
                    <small>${message}</small>
                    <br>
                    <small><i class="fas fa-clock me-1"></i>Will remind again: ${snoozeUntil}</small>
                </div>

                <div class="snooze-progress mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small>Snooze Usage</small>
                        <small>${snoozeCount}/${maxSnoozes}</small>
                    </div>
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-light" role="progressbar" style="width: ${progressPercent}%"
                             aria-valuenow="${progressPercent}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    ${snoozeCount >= maxSnoozes - 1 ? '<small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Approaching snooze limit!</small>' : ''}
                </div>
            </div>
        </div>
    `;

            document.getElementById('toast-container').insertAdjacentHTML('beforeend', toast);
            const toastElement = new bootstrap.Toast(document.getElementById(toastId));
            toastElement.show();
        }

        // Show advanced snooze modal when limit is reached
        function showAdvancedSnoozeModal(id, toastId, reminderData) {
            const advancedModal = `
        <div class="modal fade" id="advancedSnoozeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title text-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>Snooze Limit Reached
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning d-flex align-items-center mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>This reminder has been snoozed ${maxSnoozeCount} times. Consider taking action!</small>
                        </div>

                        <p class="mb-3">What would you like to do with this reminder?</p>

                        <div class="d-grid gap-2">
                            <button class="btn btn-success" onclick="completeReminderFromAdvanced(${id}, '${toastId}', ${JSON.stringify(reminderData).replace(/"/g, '&quot;')})">
                                <i class="fas fa-check me-2"></i>Mark as Complete
                            </button>

                            <button class="btn btn-warning" onclick="dismissReminderFromAdvanced(${id}, '${toastId}', ${JSON.stringify(reminderData).replace(/"/g, '&quot;')})">
                                <i class="fas fa-eye-slash me-2"></i>Dismiss Reminder
                            </button>

                            <button class="btn btn-primary" onclick="editReminderFromAdvanced(${id})">
                                <i class="fas fa-edit me-2"></i>Edit Reminder
                            </button>

                            <button class="btn btn-outline-secondary" onclick="resetSnoozeCount(${id}, '${toastId}', ${JSON.stringify(reminderData).replace(/"/g, '&quot;')})">
                                <i class="fas fa-undo me-2"></i>Reset Snooze Count
                            </button>
                        </div>

                        <div class="mt-3 pt-3 border-top">
                            <button class="btn btn-link btn-sm w-100" onclick="showSnoozeStats(${id}, ${reminderData.instanceId || 'null'})">
                                <i class="fas fa-chart-line me-1"></i>View Snooze History
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

            document.body.insertAdjacentHTML('beforeend', advancedModal);
            const modal = new bootstrap.Modal(document.getElementById('advancedSnoozeModal'));
            modal.show();

            document.getElementById('advancedSnoozeModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Complete reminder from advanced modal
        function completeReminderFromAdvanced(id, toastId, reminderData) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('advancedSnoozeModal'));
            modal.hide();

            if (reminderData.isInstance) {
                completeReminderInstance(reminderData.instanceId);
            } else {
                completeReminder(id);
            }

            // Remove the urgent toast
            const toast = document.getElementById(toastId);
            if (toast) {
                bootstrap.Toast.getInstance(toast)?.hide();
                setTimeout(() => toast.remove(), 300);
            }
        }

        // Dismiss reminder from advanced modal
        function dismissReminderFromAdvanced(id, toastId, reminderData) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('advancedSnoozeModal'));
            modal.hide();

            if (reminderData.isInstance) {
                dismissReminderInstance(reminderData.instanceId);
            } else {
                dismissReminder(id);
            }

            // Remove the urgent toast
            const toast = document.getElementById(toastId);
            if (toast) {
                bootstrap.Toast.getInstance(toast)?.hide();
                setTimeout(() => toast.remove(), 300);
            }
        }

        // Edit reminder from advanced modal
        function editReminderFromAdvanced(id) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('advancedSnoozeModal'));
            modal.hide();
            editReminder(id);
        }

        // Reset snooze count (admin function)
        function resetSnoozeCount(id, toastId, reminderData) {
            if (!confirm('Are you sure you want to reset the snooze count? This will allow snoozing again.')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'reset_snooze_count');
            formData.append('id', id);
            formData.append('source_type', reminderData.isInstance ? 'instance' : 'reminder');

            if (reminderData.instanceId) {
                formData.append('instance_id', reminderData.instanceId);
            }

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('advancedSnoozeModal'));
                        modal.hide();
                        showToast('Reset Successful', 'Snooze count has been reset', 'success');

                        // Update reminder data and show snooze modal
                        reminderData.snoozeCount = 0;
                        snoozeReminder(id, toastId, reminderData);
                    } else {
                        showToast('Reset Failed', data.message, 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error', 'Failed to reset snooze count', 'danger');
                });
        }

        // Show snooze statistics modal
        function showSnoozeStats(id, instanceId) {
            const formData = new FormData();
            formData.append('action', 'get_snooze_stats');
            formData.append('id', id);

            if (instanceId) {
                formData.append('instance_id', instanceId);
            }

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displaySnoozeStatsModal(data);
                    } else {
                        showToast('Error', 'Failed to load snooze statistics', 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error', 'Failed to load snooze statistics', 'danger');
                });
        }

        // Display snooze statistics modal
        function displaySnoozeStatsModal(data) {
            const current = data.current_status;
            const history = data.history;

            let historyHTML = '';
            if (history.length > 0) {
                historyHTML = history.map(item => {
                    const snoozeTime = new Date(item.snooze_time).toLocaleString();
                    const snoozeUntil = new Date(item.snooze_until).toLocaleString();
                    const duration = formatSnoozeDuration(item.snooze_duration_minutes);

                    return `
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <small class="text-muted">${snoozeTime}</small>
                        <br>
                        <span class="badge bg-info">${duration}</span>
                    </div>
                    <small class="text-muted">Until: ${snoozeUntil}</small>
                </div>
            `;
                }).join('');
            } else {
                historyHTML = '<div class="text-center text-muted py-3">No snooze history found</div>';
            }

            const statsModal = `
        <div class="modal fade" id="snoozeStatsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title text-info">
                            <i class="fas fa-chart-line me-2"></i>Snooze History
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Current Status -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Current Status</h6>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="h5 mb-0 ${current.is_snoozed ? 'text-warning' : 'text-success'}">${current.is_snoozed ? 'Snoozed' : 'Active'}</div>
                                        <small class="text-muted">Status</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h5 mb-0 text-info">${current.snooze_count || 0}</div>
                                        <small class="text-muted">Total Snoozes</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h5 mb-0 text-primary">${data.max_snooze_count - (current.snooze_count || 0)}</div>
                                        <small class="text-muted">Remaining</small>
                                    </div>
                                </div>
                                ${current.is_snoozed && current.snooze_until ? `
                                    <div class="mt-2 pt-2 border-top">
                                        <small class="text-muted">Snoozed until: <strong>${new Date(current.snooze_until).toLocaleString()}</strong></small>
                                    </div>
                                ` : ''}
                            </div>
                        </div>

                        <!-- History -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Recent Snooze History</h6>
                            </div>
                            <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                ${historyHTML}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

            document.body.insertAdjacentHTML('beforeend', statsModal);
            const modal = new bootstrap.Modal(document.getElementById('snoozeStatsModal'));
            modal.show();

            document.getElementById('snoozeStatsModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Helper function to format snooze duration for display
        function formatSnoozeDuration(minutes) {
            if (minutes < 60) {
                return minutes + ' min';
            } else if (minutes < 1440) {
                const hours = Math.floor(minutes / 60);
                const remainingMinutes = minutes % 60;
                return hours + 'h' + (remainingMinutes > 0 ? ' ' + remainingMinutes + 'm' : '');
            } else {
                const days = Math.floor(minutes / 1440);
                const remainingHours = Math.floor((minutes % 1440) / 60);
                return days + 'd' + (remainingHours > 0 ? ' ' + remainingHours + 'h' : '');
            }
        }

        // Enhanced showUrgentReminderToast with snooze count and improved UI
        function showUrgentReminderToast(reminder, hoursRemaining) {
            const toastId = 'urgent-toast-' + reminder.id + (reminder.is_instance ? '-instance-' + reminder.instance_id : '');

            // Check if toast already exists for this reminder
            if (document.getElementById(toastId)) {
                return; // Don't create duplicate toasts
            }

            // Skip if snoozed
            if (reminder.is_snoozed && reminder.snooze_until && new Date(reminder.snooze_until) > new Date()) {
                return;
            }

            // Format time remaining
            let timeText = '';
            if (hoursRemaining < 1) {
                const minutesRemaining = Math.floor((hoursRemaining * 60));
                timeText = minutesRemaining > 0 ? `${minutesRemaining} minutes` : 'Less than a minute';
            } else {
                const hours = Math.floor(hoursRemaining);
                const minutes = Math.floor((hoursRemaining - hours) * 60);
                timeText = minutes > 0 ? `${hours}h ${minutes}m` : `${hours} hours`;
            }

            const priorityClass = reminder.priority === 'high' ? 'danger' :
                reminder.priority === 'medium' ? 'warning' : 'info';

            const recurringBadge = reminder.is_recurring ?
                `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
            <i class="fas fa-redo me-1"></i>${reminder.is_instance ? 'Recurring Instance' : 'Recurring'}
        </span>` : '';

            // Snooze information
            const snoozeCount = reminder.snooze_count || 0;
            const snoozeBadge = snoozeCount > 0 ?
                `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
            <i class="fas fa-clock me-1"></i>Snoozed ${snoozeCount}x
        </span>` : '';

            const toast = `
        <div class="toast urgent-reminder-toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true"
             data-bs-autohide="false" data-is-instance="${reminder.is_instance || false}"
             data-instance-id="${reminder.instance_id || ''}" data-snooze-count="${snoozeCount}">
            <div class="toast-header bg-${priorityClass} text-white">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong class="me-auto">Urgent Reminder</strong>
                <small class="text-white-50">${timeText} remaining</small>
                <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <div class="d-flex align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1 fw-bold">${reminder.title}</h6>
                        ${reminder.description ? `<p class="mb-2 text-muted small">${reminder.description}</p>` : ''}
                        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                            <span class="badge bg-${priorityClass} bg-opacity-10 text-${priorityClass} border border-${priorityClass} border-opacity-25">
                                <i class="fas fa-flag me-1"></i>${reminder.priority.charAt(0).toUpperCase() + reminder.priority.slice(1)}
                            </span>
                            <span class="badge bg-light text-dark border">
                                <i class="fas fa-tag me-1"></i>${reminder.category.charAt(0).toUpperCase() + reminder.category.slice(1)}
                            </span>
                            ${recurringBadge}
                            ${snoozeBadge}
                        </div>
                        <div class="d-flex align-items-center text-muted small mb-2">
                            <i class="fas fa-calendar me-1"></i>
                            <span>${new Date(reminder.reminder_date + ' ' + reminder.reminder_time).toLocaleString()}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3 flex-wrap">
                    <button class="btn btn-success btn-sm flex-fill" onclick="completeReminderFromToast(${reminder.id}, '${toastId}')">
                        <i class="fas fa-check me-1"></i>Complete
                    </button>
                    <button class="btn btn-warning btn-sm flex-fill" onclick="dismissReminderFromToast(${reminder.id}, '${toastId}')">
                        <i class="fas fa-eye-slash me-1"></i>Dismiss
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="snoozeReminder(${reminder.id}, '${toastId}', {isInstance: ${reminder.is_instance || false}, instanceId: ${reminder.instance_id || 'null'}, snoozeCount: ${snoozeCount}})"
                            ${snoozeCount >= maxSnoozeCount ? 'title="Snooze limit reached - click for options"' : ''}>
                        <i class="fas fa-clock me-1"></i>${snoozeCount >= maxSnoozeCount ? 'Options' : 'Snooze'}
                    </button>
                </div>
            </div>
        </div>
    `;

            document.getElementById('toast-container').insertAdjacentHTML('beforeend', toast);
            const toastElement = new bootstrap.Toast(document.getElementById(toastId));
            toastElement.show();
        }
    </script>
    <script>
        // Snooze Settings JavaScript
        let currentSnoozeOptions = ['15', '30', '60', '120', '480', '1440'];

        // Initialize snooze settings
        document.addEventListener('DOMContentLoaded', function() {
            loadSnoozeSettings();
            loadSnoozeAnalyticsPreview();

            // Sync range and number inputs
            const rangeInput = document.getElementById('maxSnoozeCount');
            const numberInput = document.getElementById('maxSnoozeCountInput');
            const label = document.getElementById('snoozeCountLabel');

            rangeInput.addEventListener('input', function() {
                numberInput.value = this.value;
                label.textContent = this.value + ' time' + (this.value != 1 ? 's' : '');
            });

            numberInput.addEventListener('input', function() {
                rangeInput.value = Math.min(10, Math.max(1, this.value));
                label.textContent = this.value + ' time' + (this.value != 1 ? 's' : '');
            });
        });

        // Load current snooze settings
        function loadSnoozeSettings() {
            // Load from existing getSetting calls or make AJAX request
            const maxSnoozeCount = <?= getSetting('max_snooze_count', '5') ?>;
            const snoozeOptions = '<?= getSetting('snooze_options', '15,30,60,120,480,1440') ?>';

            document.getElementById('maxSnoozeCount').value = maxSnoozeCount;
            document.getElementById('maxSnoozeCountInput').value = maxSnoozeCount;
            document.getElementById('snoozeCountLabel').textContent = maxSnoozeCount + ' time' + (maxSnoozeCount != 1 ? 's' : '');

            currentSnoozeOptions = snoozeOptions.split(',');
            renderSnoozeOptionsInputs();
        }

        // Render snooze options inputs
        function renderSnoozeOptionsInputs() {
            const container = document.getElementById('snoozeOptionsInputs');
            container.innerHTML = '';

            currentSnoozeOptions.forEach((option, index) => {
                const minutes = parseInt(option);
                const formattedDuration = formatSnoozeDuration(minutes);

                container.innerHTML += `
            <div class="col-lg-4 col-md-6">
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control" value="${minutes}"
                           min="1" max="1440" onchange="updateSnoozeOption(${index}, this.value)">
                    <span class="input-group-text">${formattedDuration}</span>
                    <button class="btn btn-outline-danger" type="button" onclick="removeSnoozeOption(${index})"
                            ${currentSnoozeOptions.length <= 3 ? 'disabled title="Minimum 3 options required"' : ''}>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
            });
        }

        // Update snooze option value
        function updateSnoozeOption(index, value) {
            const minutes = parseInt(value);
            if (minutes >= 1 && minutes <= 1440) {
                currentSnoozeOptions[index] = minutes.toString();
                renderSnoozeOptionsInputs();
            }
        }

        // Add new snooze option
        function addSnoozeOption() {
            if (currentSnoozeOptions.length < 8) {
                currentSnoozeOptions.push('60'); // Default to 1 hour
                renderSnoozeOptionsInputs();
            } else {
                showToast('Limit Reached', 'Maximum 8 snooze options allowed', 'warning');
            }
        }

        // Remove snooze option
        function removeSnoozeOption(index) {
            if (currentSnoozeOptions.length > 3) {
                currentSnoozeOptions.splice(index, 1);
                renderSnoozeOptionsInputs();
            }
        }

        // Reset to default snooze options
        function resetSnoozeOptionsToDefault() {
            currentSnoozeOptions = ['15', '30', '60', '120', '480', '1440'];
            renderSnoozeOptionsInputs();
        }

        // Save snooze settings
        document.getElementById('saveSnoozeSettingsBtn').addEventListener('click', function() {
            const maxSnoozeCount = document.getElementById('maxSnoozeCountInput').value;
            const snoozeOptions = currentSnoozeOptions.join(',');

            const formData = new FormData();
            formData.append('action', 'update_snooze_settings');
            formData.append('max_snooze_count', maxSnoozeCount);
            formData.append('snooze_options', snoozeOptions);

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Success!', 'Snooze settings saved!', 'success');
                        // Reload snooze options for the toast system
                        loadSnoozeOptions();
                    } else {
                        showToast('Error!', data.message, 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error!', 'Failed to save snooze settings', 'danger');
                })
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-save me-1"></i>Save Snooze Settings';
                });
        });

        // Load snooze analytics preview
        function loadSnoozeAnalyticsPreview() {
            const formData = new FormData();
            formData.append('action', 'get_snooze_analytics');
            formData.append('days', '30');

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateAnalyticsPreview(data);
                    }
                })
                .catch(error => {
                    console.error('Error loading snooze analytics:', error);
                });
        }

        // Update analytics preview
        function updateAnalyticsPreview(data) {
            const totalSnoozes = data.daily_stats.reduce((sum, day) => sum + parseInt(day.daily_count), 0);
            const avgDuration = data.duration_preferences.length > 0 ?
                Math.round(data.duration_preferences.reduce((sum, pref) => sum + (pref.snooze_duration_minutes * pref.usage_count), 0) / totalSnoozes) : 0;
            const uniqueReminders = data.top_snoozed.length;
            const mostUsedDuration = data.duration_preferences.length > 0 ?
                formatSnoozeDuration(data.duration_preferences[0].snooze_duration_minutes) : 'N/A';

            document.getElementById('totalSnoozes').textContent = totalSnoozes;
            document.getElementById('avgSnoozeDuration').textContent = avgDuration > 0 ? formatSnoozeDuration(avgDuration) : 'N/A';
            document.getElementById('uniqueReminders').textContent = uniqueReminders;
            document.getElementById('mostUsedDuration').textContent = mostUsedDuration;
        }

        // Show full snooze analytics modal
        function showSnoozeAnalytics() {
            const modal = new bootstrap.Modal(document.getElementById('snoozeAnalyticsModal'));
            modal.show();
            loadFullAnalytics(30); // Default to 30 days

            // Add event listeners for period selection
            document.querySelectorAll('input[name="analyticsPeriod"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        loadFullAnalytics(parseInt(this.value));
                    }
                });
            });
        }

        // Load full analytics data
        function loadFullAnalytics(days) {
            const formData = new FormData();
            formData.append('action', 'get_snooze_analytics');
            formData.append('days', days);

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderFullAnalytics(data);
                    }
                })
                .catch(error => {
                    console.error('Error loading full analytics:', error);
                });
        }

        // Render full analytics (you'll need Chart.js for the charts)
        function renderFullAnalytics(data) {
            // Update detailed stats
            const totalSnoozes = data.daily_stats.reduce((sum, day) => sum + parseInt(day.daily_count), 0);
            const avgDuration = totalSnoozes > 0 ?
                Math.round(data.duration_preferences.reduce((sum, pref) => sum + (pref.snooze_duration_minutes * pref.usage_count), 0) / totalSnoozes) : 0;

            document.getElementById('detailedAnalyticsStats').innerHTML = `
        <div class="col-md-3">
            <div class="text-center p-3 bg-primary bg-opacity-10 rounded">
                <div class="h4 mb-0 text-primary">${totalSnoozes}</div>
                <small class="text-muted">Total Snoozes</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center p-3 bg-info bg-opacity-10 rounded">
                <div class="h4 mb-0 text-info">${avgDuration > 0 ? formatSnoozeDuration(avgDuration) : 'N/A'}</div>
                <small class="text-muted">Average Duration</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center p-3 bg-success bg-opacity-10 rounded">
                <div class="h4 mb-0 text-success">${data.top_snoozed.length}</div>
                <small class="text-muted">Unique Reminders</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center p-3 bg-warning bg-opacity-10 rounded">
                <div class="h4 mb-0 text-warning">${data.period_days}</div>
                <small class="text-muted">Days Analyzed</small>
            </div>
        </div>
    `;

            // Render top snoozed reminders
            let topSnoozedHTML = '';
            if (data.top_snoozed.length > 0) {
                topSnoozedHTML = data.top_snoozed.map((reminder, index) => `
            <div class="d-flex justify-content-between align-items-center py-2 ${index < data.top_snoozed.length - 1 ? 'border-bottom' : ''}">
                <div>
                    <strong>${reminder.title}</strong>
                    <br>
                    <small class="text-muted">
                        <i class="fas fa-tag me-1"></i>${reminder.category} •
                        <i class="fas fa-clock me-1"></i>Avg: ${formatSnoozeDuration(Math.round(reminder.avg_duration))}
                    </small>
                </div>
                <span class="badge bg-warning">${reminder.snooze_count} snoozes</span>
            </div>
        `).join('');
            } else {
                topSnoozedHTML = '<div class="text-center text-muted py-3">No snooze data available for this period</div>';
            }

            document.getElementById('topSnoozedList').innerHTML = topSnoozedHTML;

            // Note: Chart rendering would require Chart.js library
            // You can add Chart.js integration here if needed
        }
    </script>

    <script>
        /* ========================================
           Reminders: Split-View Interactivity
           ======================================== */
        // Server-validated snooze durations (kept in sync with reminder_settings.snooze_options)
        window.__snoozeOptionsHtml = (function() {
            const opts = [
                <?php
                $allowedSnoozeJS = array_filter(array_map('intval', explode(',', getSetting('snooze_options', '15,30,60,120,480,1440'))));
                foreach ($allowedSnoozeJS as $sm) {
                    echo "        { m: " . $sm . ", label: " . json_encode(formatSnoozeDuration($sm)) . " },\n";
                }
                ?>
            ];
            return opts.map(function(o){
                return '<li><a class="dropdown-item js-snooze-item" href="#" data-minutes="' + o.m + '">' + o.label + '</a></li>';
            }).join('');
        })();

        (function() {
            'use strict';

            // ----- DOM refs -----
            const container       = document.getElementById('reminders-container');
            const detailEmpty     = document.getElementById('reminderDetailEmpty');
            const detailContent   = document.getElementById('reminderDetailContent');
            const searchInput     = document.getElementById('reminderSearch');
            const searchClear     = document.getElementById('reminderSearchClear');
            const categoryFilter  = document.getElementById('categoryFilter');
            const priorityFilter  = document.getElementById('priorityFilter');
            const quickAddForm    = document.getElementById('quickAddForm');
            const quickAddTitle   = document.getElementById('quickAddTitle');

            if (!container) return; // page didn't render the list pane

            // ============================================
            // 1) Row click -> render detail pane
            // ============================================
            container.addEventListener('click', function(e) {
                // Ignore clicks on interactive children
                if (e.target.closest('.reminder-quick-actions, .reminder-checkbox, .dropdown, button, a, input')) return;
                const row = e.target.closest('.reminder-item');
                if (!row) return;
                selectReminderRow(row);
            });

            function selectReminderRow(row) {
                document.querySelectorAll('.reminder-item.is-selected').forEach(el => el.classList.remove('is-selected'));
                row.classList.add('is-selected');
                try {
                    const data = JSON.parse(row.dataset.reminder);
                    renderDetail(data);
                } catch (err) {
                    console.error('Bad reminder payload', err);
                }
            }

            function escapeHtml(s) {
                if (s === null || s === undefined) return '';
                return String(s).replace(/[&<>"']/g, m => ({
                    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
                }[m]));
            }

            function fmtDate(iso) {
                if (!iso) return '';
                const d = new Date(iso + 'T00:00:00');
                return d.toLocaleDateString(undefined, { weekday:'long', year:'numeric', month:'long', day:'numeric' });
            }
            function fmtTime(t) {
                if (!t) return '';
                const [h, m] = t.split(':').map(Number);
                const d = new Date(); d.setHours(h, m, 0, 0);
                return d.toLocaleTimeString(undefined, { hour:'numeric', minute:'2-digit' });
            }
            function fmtAdvance(min) {
                if (!min || min == 0) return 'None';
                if (min < 60) return min + ' min before';
                const h = Math.floor(min / 60), r = min % 60;
                return h + 'h' + (r ? ' ' + r + 'm' : '') + ' before';
            }

            function renderDetail(r) {
                detailEmpty.classList.add('d-none');
                detailContent.classList.remove('d-none');

                const accentColor = r.accent || 'primary';
                const priorityLabel = (r.priority || 'medium').charAt(0).toUpperCase() + (r.priority || 'medium').slice(1);
                const statusBadge = r.is_completed
                    ? `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check-circle me-1"></i>Completed</span>`
                    : r.is_dismissed
                        ? `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="fas fa-eye-slash me-1"></i>Dismissed</span>`
                        : r.is_overdue
                            ? `<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fas fa-exclamation-triangle me-1"></i>Overdue</span>`
                            : r.is_due_soon
                                ? `<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="fas fa-bell me-1"></i>Due Soon</span>`
                                : `<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fas fa-clock me-1"></i>Upcoming</span>`;

                const actionButtons = r.is_dismissed ? `
            <button class="btn btn-success" onclick="restoreReminder(${r.id}, '${r.source_type || 'reminder'}', ${r.instance_id !== null && r.instance_id !== undefined ? r.instance_id : 'null'})">
                <i class="fas fa-undo me-1"></i>Undismiss
            </button>
            <button class="btn btn-outline-danger ms-auto" onclick="deleteReminder(${r.id})">
                <i class="fas fa-trash me-1"></i>Delete
            </button>
        ` : r.is_completed ? `
            <button class="btn btn-outline-danger" onclick="deleteReminder(${r.id})"><i class="fas fa-trash me-1"></i>Delete</button>
        ` : `
            <button class="btn btn-success" onclick="${r.source_type === 'instance' ? `completeReminderInstance(${r.instance_id})` : `completeReminder(${r.id})`}">
                <i class="fas fa-check me-1"></i>Mark Complete
            </button>
            <button class="btn btn-primary" onclick="editReminder(${r.source_type === 'instance' ? r.reminder_id : r.id})">
                <i class="fas fa-edit me-1"></i>Edit
            </button>
            <div class="btn-group">
                <button class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-clock me-1"></i>Snooze</button>
                <ul class="dropdown-menu">
                    ${window.__snoozeOptionsHtml || '<li><a class="dropdown-item" href="#" onclick="quickSnooze(' + r.id + ', 15);return false;">15 minutes</a></li>'}
                </ul>
            </div>
            <button class="btn btn-outline-warning" onclick="${r.source_type === 'instance' && r.instance_id ? `dismissReminderInstance(${r.instance_id})` : `dismissReminder(${r.id})`}">
                <i class="fas fa-eye-slash me-1"></i>Dismiss
            </button>
            <button class="btn btn-outline-danger ms-auto" onclick="deleteReminder(${r.id})">
                <i class="fas fa-trash"></i>
            </button>
        `;

                detailContent.innerHTML = `
            <div class="detail-hero border-top border-${accentColor}" style="border-top-width:4px !important;">
                <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                    ${statusBadge}
                    <div class="d-flex gap-1">
                        <span class="badge" style="background-color:${r.cat_color};color:#fff;">
                            <i class="${r.cat_icon} me-1"></i>${escapeHtml(r.category)}
                        </span>
                        ${r.is_recurring ? '<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fas fa-redo me-1"></i>Recurring</span>' : ''}
                    </div>
                </div>
                <h3 class="fw-bold mb-2 ${r.is_completed ? 'text-decoration-line-through text-muted' : ''}">${escapeHtml(r.title)}</h3>
                ${r.description ? `<p class="text-muted mb-0">${escapeHtml(r.description)}</p>` : '<p class="text-muted small fst-italic mb-0">No description</p>'}
            </div>

            <div class="detail-section">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="detail-label">Date</div>
                        <div class="fw-semibold"><i class="fas fa-calendar text-${accentColor} me-2"></i>${fmtDate(r.reminder_date)}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="detail-label">Time</div>
                        <div class="fw-semibold"><i class="fas fa-clock text-${accentColor} me-2"></i>${fmtTime(r.reminder_time)}</div>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="detail-label">Priority</div>
                        <div class="fw-semibold">
                            <span class="reminder-priority-dot priority-${r.priority} me-2"></span>${priorityLabel}
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="detail-label">Email Notifications</div>
                        <div class="fw-semibold">
                            <i class="fas fa-envelope text-muted me-2"></i>${escapeHtml((r.email_frequency || 'none').charAt(0).toUpperCase() + (r.email_frequency || 'none').slice(1))}
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="detail-label">Advance Notification</div>
                        <div class="fw-semibold"><i class="fas fa-bell text-muted me-2"></i>${fmtAdvance(r.advance_notification)}</div>
                    </div>
                    ${r.is_recurring && r.recurring_days ? `
                    <div class="col-sm-6">
                        <div class="detail-label">Repeats Every</div>
                        <div class="fw-semibold"><i class="fas fa-redo text-muted me-2"></i>${r.recurring_days} day${r.recurring_days > 1 ? 's' : ''}</div>
                    </div>` : ''}
                </div>
            </div>

            <div class="detail-actions">${actionButtons}</div>
        `;
            }

            // ============================================
            // 2) Live search + filter
            // ============================================
            function applyFilters() {
                const q       = (searchInput?.value || '').trim().toLowerCase();
                const catVal  = categoryFilter?.value || '';
                const priVal  = priorityFilter?.value || '';

                let visibleCount = 0;
                document.querySelectorAll('.reminder-item').forEach(row => {
                    const title = row.dataset.title || '';
                    const cat   = row.dataset.category || '';
                    const pri   = row.dataset.priority || '';
                    const match = (!q || title.includes(q))
                        && (!catVal || cat === catVal)
                        && (!priVal || pri === priVal);
                    row.style.display = match ? '' : 'none';
                    if (match) visibleCount++;
                });

                // Hide groups that have no visible items
                document.querySelectorAll('.reminder-group').forEach(group => {
                    const visible = group.querySelectorAll('.reminder-item:not([style*="display: none"])').length;
                    group.style.display = visible > 0 ? '' : 'none';
                });

                if (searchClear) searchClear.classList.toggle('d-none', q === '');
            }

            if (searchInput) {
                let t;
                searchInput.addEventListener('input', () => { clearTimeout(t); t = setTimeout(applyFilters, 120); });
            }
            if (searchClear) {
                searchClear.addEventListener('click', () => { searchInput.value = ''; applyFilters(); searchInput.focus(); });
            }
            // Category filter triggers a server reload so stats + pagination stay accurate
            if (categoryFilter) {
                categoryFilter.addEventListener('change', function() {
                    const params = new URLSearchParams(window.location.search);
                    const val = this.value;
                    if (val) params.set('category', val); else params.delete('category');
                    params.delete('page');
                    window.location.search = params.toString();
                });
            }
            // Priority is local-only (cheaper)
            if (priorityFilter) priorityFilter.addEventListener('change', applyFilters);

            // ============================================
            // 3) Inline quick-add
            // ============================================
            if (quickAddTitle) {
                quickAddTitle.addEventListener('focus', () => quickAddForm.classList.add('expanded'));
                document.addEventListener('click', (e) => {
                    if (!quickAddForm.contains(e.target) && quickAddTitle.value.trim() === '') {
                        quickAddForm.classList.remove('expanded');
                    }
                });
            }
            if (quickAddForm) {
                quickAddForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const fd = new FormData(quickAddForm);
                    fd.append('action', 'quick_add_reminder');
                    fd.append('priority', 'medium');
                    fetch('', { method:'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                if (typeof showToast === 'function') showToast('Added', data.message || 'Reminder added!', 'success');
                                setTimeout(() => location.reload(), 400);
                            } else {
                                if (typeof showToast === 'function') showToast('Error', data.message || 'Failed', 'danger');
                                else alert(data.message || 'Failed');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            if (typeof showToast === 'function') showToast('Error', 'Network error', 'danger');
                        });
                });
            }

            // ============================================
            // 4) Drag-to-reschedule
            // ============================================
            document.querySelectorAll('.reminder-item').forEach(item => {
                item.addEventListener('dragstart', (e) => {
                    item.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', item.dataset.id);
                });
                item.addEventListener('dragend', () => {
                    item.classList.remove('dragging');
                    document.querySelectorAll('.reminder-group-header.drop-active').forEach(el => el.classList.remove('drop-active'));
                });
            });

            function bucketToDate(bucket) {
                const today = new Date(); today.setHours(0,0,0,0);
                const pad = n => String(n).padStart(2, '0');
                const fmt = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
                switch (bucket) {
                    case 'today':    return fmt(today);
                    case 'tomorrow': { const d = new Date(today); d.setDate(d.getDate()+1); return fmt(d); }
                    case 'week':     { const d = new Date(today); d.setDate(d.getDate()+3); return fmt(d); }
                    case 'later':    { const d = new Date(today); d.setDate(d.getDate()+14); return fmt(d); }
                    case 'overdue':  { const d = new Date(today); d.setDate(d.getDate()-1); return fmt(d); }
                    case 'done':     return null; // cannot drag to "done" group
                    default: return null;
                }
            }

            document.querySelectorAll('.reminder-group-header').forEach(header => {
                header.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    header.classList.add('drop-active');
                });
                header.addEventListener('dragleave', () => header.classList.remove('drop-active'));
                header.addEventListener('drop', (e) => {
                    e.preventDefault();
                    header.classList.remove('drop-active');
                    const id = e.dataTransfer.getData('text/plain');
                    const bucket = header.dataset.droptarget;
                    const newDate = bucketToDate(bucket);
                    if (!id || !newDate) return;

                    const fd = new FormData();
                    fd.append('action', 'reschedule_reminder');
                    fd.append('id', id);
                    fd.append('new_date', newDate);
                    fetch('', { method:'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                if (typeof showToast === 'function') showToast('Rescheduled', `Moved to ${newDate}`, 'success');
                                setTimeout(() => location.reload(), 350);
                            } else {
                                if (typeof showToast === 'function') showToast('Error', data.message || 'Failed', 'danger');
                            }
                        });
                });
            });

            // Delegated handler for snooze options rendered into the detail pane
            document.addEventListener('click', function(e) {
                const link = e.target.closest('.js-snooze-item');
                if (!link) return;
                e.preventDefault();
                const minutes = parseInt(link.dataset.minutes, 10);
                const detailHost = link.closest('#reminderDetailContent');
                if (!detailHost) return;
                const selected = document.querySelector('.reminder-item.is-selected');
                if (!selected) return;
                const id = parseInt(selected.dataset.id, 10);
                if (!Number.isFinite(id) || !Number.isFinite(minutes)) return;
                window.quickSnooze(id, minutes);
            });

            // ============================================
            // 5) Quick snooze (used by buttons in rows + detail pane)
            // Field name MUST be 'minutes' (backend: case 'snooze_reminder' reads $_POST['minutes'])
            // Durations MUST come from the server-allowed list: 15,30,60,120,480,1440
            // ============================================
            window.quickSnooze = function(id, minutes) {
                const fd = new FormData();
                fd.append('action', 'snooze_reminder');
                fd.append('id', id);
                fd.append('minutes', minutes);
                fetch('', { method:'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            if (typeof showToast === 'function') showToast('Snoozed', data.message || 'Reminder snoozed', 'success');
                            setTimeout(() => location.reload(), 400);
                        } else {
                            if (typeof showToast === 'function') showToast('Error', data.message || 'Failed to snooze', 'danger');
                            else alert(data.message || 'Failed to snooze');
                        }
                    })
                    .catch(err => {
                        console.error('Snooze failed', err);
                        if (typeof showToast === 'function') showToast('Error', 'Network error', 'danger');
                    });
            };

            // ============================================
            // 6) Bulk-action bar visibility is owned by the original JS
            // (see updateBulkActions / selectAllReminders / bulkDeleteBtn handlers above).
            // We just don't re-bind here, to avoid double-fires.
            // ============================================

            // Bulk Mark Complete (new — mirrors bulk delete pattern)
            const bulkCompleteBtn = document.getElementById('bulkCompleteBtn');
            if (bulkCompleteBtn) {
                bulkCompleteBtn.addEventListener('click', function() {
                    // selectedReminders is a Set declared in the original script higher up
                    if (typeof selectedReminders === 'undefined' || selectedReminders.size === 0) {
                        if (typeof showToast === 'function') showToast('Nothing selected', 'Pick at least one reminder', 'warning');
                        return;
                    }
                    if (!confirm(`Mark ${selectedReminders.size} reminder(s) as complete?`)) return;

                    bulkCompleteBtn.disabled = true;
                    const originalHTML = bulkCompleteBtn.innerHTML;
                    bulkCompleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Working...';

                    const fd = new FormData();
                    fd.append('action', 'bulk_complete_reminders');
                    fd.append('ids', JSON.stringify([...selectedReminders]));

                    fetch('', { method:'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                if (typeof showToast === 'function') showToast('Done', data.message, 'success');
                                setTimeout(() => location.reload(), 400);
                            } else {
                                if (typeof showToast === 'function') showToast('Error', data.message || 'Failed', 'danger');
                                else alert(data.message || 'Failed');
                                bulkCompleteBtn.disabled = false;
                                bulkCompleteBtn.innerHTML = originalHTML;
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            if (typeof showToast === 'function') showToast('Error', 'Network error', 'danger');
                            bulkCompleteBtn.disabled = false;
                            bulkCompleteBtn.innerHTML = originalHTML;
                        });
                });
            }

            // ============================================
            // 7) Auto-select first reminder on load (desktop only)
            // ============================================
            if (window.matchMedia('(min-width: 992px)').matches) {
                const first = document.querySelector('.reminder-item');
                if (first) selectReminderRow(first);
            }
        })();
    </script>

<?php include "footer.php"; ?>