<?php
include "db.php"; // or whatever file contains your database connection
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['task_id']) || !isset($input['action']) || $input['action'] !== 'get_user_statuses') {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit();
}

$taskId = (int)$input['task_id'];

if ($taskId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

try {
    // Get all users who have commented on this task
    $commentsQuery = 'SELECT DISTINCT username, user_type FROM tbl_task_comments WHERE task_id = ?';
    $stmt = $con->prepare($commentsQuery);
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $commentsResult = $stmt->get_result();
    $commentUsers = $commentsResult->fetch_all(MYSQLI_ASSOC);

    $statuses = [];

    foreach ($commentUsers as $user) {
        $userKey = $user['user_type'] . '_' . $user['username'];
        $onlineData = getUserOnlineStatus($con, $user['user_type'], $user['username']);

        // Build tooltip content
        $tooltipContent = "<strong>" . htmlspecialchars($user['username']) . "</strong><br>";
        $tooltipContent .= "Status: " . $onlineData['status_text'];

        if ($onlineData['last_seen'] && $onlineData['is_online'] == 0) {
            $tooltipContent .= "<br><small>Last seen: " . date('M j, Y g:i A', strtotime($onlineData['last_seen'])) . "</small>";
        }

        $statuses[$userKey] = [
            'status_class' => $onlineData['status_class'],
            'status_text' => $onlineData['status_text'],
            'tooltip_content' => $tooltipContent,
            'is_online' => $onlineData['is_online']
        ];
    }

    echo json_encode([
        'success' => true,
        'statuses' => $statuses,
        'count' => count($statuses)
    ]);

} catch (Exception $e) {
    error_log("Error in get-online-status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

/**
 * Get user online status and last seen information
 * @param mysqli $con Database connection
 * @param string $userType User type ('admin' or 'writer')
 * @param string $username Username to check
 * @param string|null $userEmail Optional email for better matching
 * @return array Online status data
 */
function getUserOnlineStatus($con, $userType, $username, $userEmail = null) {
    $onlineData = [
        'is_online' => 0,
        'last_seen' => null,
        'status_text' => 'Offline',
        'status_class' => 'bg-secondary'
    ];

    try {
        if ($userType === 'admin') {
            // Check admin online status
            $adminQuery = "SELECT is_online, last_seen FROM tbladmin WHERE username = ? OR AdminName = ? OR CONCAT(FirstName, ' ', LastName) = ? LIMIT 1";
            $stmt = mysqli_prepare($con, $adminQuery);
            if ($stmt) {
                $fullName = $username; // In case the username is actually the full name
                mysqli_stmt_bind_param($stmt, 'sss', $username, $username, $fullName);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if ($row = mysqli_fetch_assoc($result)) {
                    $onlineData['is_online'] = (int)$row['is_online'];
                    $onlineData['last_seen'] = $row['last_seen'];
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            // Check writer online status
            $writerQuery = "SELECT is_online, last_seen FROM tblwriters WHERE username = ? OR email = ? LIMIT 1";
            $stmt = mysqli_prepare($con, $writerQuery);
            if ($stmt) {
                $emailToCheck = $userEmail ?: $username;
                mysqli_stmt_bind_param($stmt, 'ss', $username, $emailToCheck);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if ($row = mysqli_fetch_assoc($result)) {
                    $onlineData['is_online'] = (int)$row['is_online'];
                    $onlineData['last_seen'] = $row['last_seen'];
                }
                mysqli_stmt_close($stmt);
            }
        }

        // Determine status based on online flag and last seen
        if ($onlineData['is_online'] == 1) {
            $onlineData['status_text'] = 'Online';
            $onlineData['status_class'] = 'bg-success';
        } else if ($onlineData['last_seen']) {
            $lastSeenTime = new DateTime($onlineData['last_seen']);
            $currentTime = new DateTime();
            $timeDiff = $currentTime->diff($lastSeenTime);

            // Calculate time difference
            if ($timeDiff->days > 0) {
                $onlineData['status_text'] = $timeDiff->days == 1 ? '1 day ago' : $timeDiff->days . ' days ago';
                $onlineData['status_class'] = 'bg-secondary';
            } else if ($timeDiff->h > 0) {
                $onlineData['status_text'] = $timeDiff->h == 1 ? '1 hour ago' : $timeDiff->h . ' hours ago';
                $onlineData['status_class'] = 'bg-warning';
            } else if ($timeDiff->i > 0) {
                $onlineData['status_text'] = $timeDiff->i == 1 ? '1 minute ago' : $timeDiff->i . ' minutes ago';
                $onlineData['status_class'] = 'bg-info';
            } else {
                $onlineData['status_text'] = 'Just now';
                $onlineData['status_class'] = 'bg-success';
            }
        } else {
            $onlineData['status_text'] = 'Unknown';
            $onlineData['status_class'] = 'bg-secondary';
        }

    } catch (Exception $e) {
        error_log("Error getting online status for {$userType} {$username}: " . $e->getMessage());
        // Return default offline status on error
    }

    return $onlineData;
}

/**
 * Optional: Update current user's online status
 * Call this function periodically to keep users marked as online
 */
function updateCurrentUserOnlineStatus($con) {
    try {
        if (isset($_SESSION['odmsaid'])) {
            // Update admin online status
            $updateQuery = "UPDATE tbladmin SET is_online = 1, last_seen = NOW() WHERE id = ?";
            $stmt = $con->prepare($updateQuery);
            $stmt->bind_param('i', $_SESSION['odmsaid']);
            $stmt->execute();
            $stmt->close();
        } elseif (isset($_SESSION['sessionWriter'])) {
            // Update writer online status
            $updateQuery = "UPDATE tblwriters SET is_online = 1, last_seen = NOW() WHERE username = ?";
            $stmt = $con->prepare($updateQuery);
            $stmt->bind_param('s', $_SESSION['sessionWriter']);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error updating current user online status: " . $e->getMessage());
    }
}

// Optional: Update current user's status when they check for others
updateCurrentUserOnlineStatus($con);
?>