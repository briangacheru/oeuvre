<?php
require_once __DIR__ . '/../version-functions.php';
function email_exists($email)
{
    global $con;

    $sql = "SELECT id FROM tbladmin WHERE email = '$email'";

    $result = $con->query($sql);

    if($result->num_rows == 1 ) {
        return true;
    } else {
        return false;
    }
}
function username_exists($username)
{
    global $con;

    $sql = "SELECT id FROM tbladmin WHERE username = '$username'";

    $result = $con->query($sql);

    if($result->num_rows == 1 ) {
        return true;
    } else {
        return false;
    }
}

function get_name($email) {
    global $con;

    $sql = "SELECT username FROM tbladmin WHERE email = '$email'";

    $result = $con->query($sql);

    $row = $result->fetch_assoc();

    return $row["username"];
}

function get_email($email) {
    global $con;

    $sql = "SELECT email FROM tbladmin WHERE email = '$email'";

    $result = $con->query($sql);

    $row = $result->fetch_assoc();

    return $row["email"];
}

function get_picture($email) {
    global $con;

    $sql = "SELECT profile_picture FROM tbladmin WHERE email = '$email'";

    $result = $con->query($sql);

    $row = $result->fetch_assoc();

    return $row["profile_picture"];
}


function set_message($message)
{
    if(!empty($message)){
        $_SESSION['message'] = $message;
    }else {
        $message = "";
    }
}

function display_message() {
    if (isset($_SESSION['message']) && !empty($_SESSION['message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
        <p class="mb-0 flex-1"><strong>Error: </strong>' . $_SESSION['message'] . '</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
        unset($_SESSION['message']);
    }
}

function set_alert($alert) {
    if(!empty($alert)) {
        $_SESSION['alert'] = $alert;
    } else {
        $alert = "";
    }
}

function display_alert() {
    if(isset($_SESSION['alert'])) {
        echo $_SESSION['alert'];
        unset($_SESSION['alert']); // Clear the alert after displaying it
    }
}

function set_subAlert($subAlert) {
    if(!empty($subAlert)) {
        $_SESSION['subAlert'] = $subAlert;
    } else {
        $subAlert = "";
    }
}

function display_subAlert() {
    if(isset($_SESSION['subAlert'])) {
        echo $_SESSION['subAlert'];
        unset($_SESSION['subAlert']); // Clear the alert after displaying it
    }
}
function redirect($location){
    return header("Location: {$location}");
}

function validation_errors($error_message)
{
    $error_message = <<<DELIMITER

<div class="alert alert-danger text-center" role="alert">
  	<strong>Warning!</strong> $error_message
 </div>
DELIMITER;

    set_message($error_message);
}

function logged_in(){
    if(isset($_SESSION['userSession']) || isset($_COOKIE['email'])){
        return true;
    } else {
        return false;
    }
}

/**
 * Updates the version number whenever code is edited
 * @param string $type Type of update: 'patch', 'minor', or 'major'
 * @param string $description Description of the update
 * @return array The new version data
 */
function updateVersionNumber($type = 'patch', $description = '') {
    $versionFile = __DIR__ . '/version.json';

    // Create version file if it doesn't exist
    if (!file_exists($versionFile)) {
        $versionData = [
            'major' => 3,
            'minor' => 0,
            'patch' => 0,
            'lastUpdated' => date('Y-m-d'),
            'description' => $description ?: 'Initial release'
        ];
    } else {
        // Read current version
        $versionData = json_decode(file_get_contents($versionFile), true);

        // If file is invalid, create a new one
        if (json_last_error() !== JSON_ERROR_NONE || !isset($versionData['major'])) {
            $versionData = [
                'major' => 3,
                'minor' => 0,
                'patch' => 0,
                'lastUpdated' => date('Y-m-d'),
                'description' => $description ?: 'Initial release'
            ];
        }
    }

    // Update version based on type
    switch ($type) {
        case 'major':
            $versionData['major']++;
            $versionData['minor'] = 0;
            $versionData['patch'] = 0;
            break;
        case 'minor':
            $versionData['minor']++;
            $versionData['patch'] = 0;
            break;
        case 'patch':
        default:
            $versionData['patch']++;
            break;
    }

    // Update last updated date and description
    $versionData['lastUpdated'] = date('Y-m-d');
    if (!empty($description)) {
        $versionData['description'] = $description;
    }

    // Save updated version
    file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT));

    return $versionData;
}

/**
 * Gets the current version data
 * @return array The current version data
 */
function getVersionData() {
    $versionFile = __DIR__ . '/version.json';

    // Check if version file exists
    if (!file_exists($versionFile)) {
        // Create default version file
        $versionData = [
            'major' => 3,
            'minor' => 0,
            'patch' => 0,
            'lastUpdated' => date('Y-m-d'),
            'description' => 'Initial release'
        ];
        file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT));
        return $versionData;
    }

    // Read current version
    $versionData = json_decode(file_get_contents($versionFile), true);

    // Check if parsing was successful
    if (json_last_error() !== JSON_ERROR_NONE || !isset($versionData['major'])) {
        // Create default version file
        $versionData = [
            'major' => 3,
            'minor' => 0,
            'patch' => 0,
            'lastUpdated' => date('Y-m-d'),
            'description' => 'Initial release'
        ];
        file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT));
    }

    return $versionData;
}

// If this file is called directly, update the version
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    $type = isset($_GET['type']) ? $_GET['type'] : 'patch';
    $description = isset($_GET['description']) ? $_GET['description'] : '';
    $versionData = updateVersionNumber($type, $description);
    $versionString = "v{$versionData['major']}.{$versionData['minor']}.{$versionData['patch']}";
    echo "Version updated to $versionString";
}

function formatFileSize($bytes) {
    if ($bytes === null || $bytes === '') return 'Unknown size';

    $bytes = (int)$bytes;
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
}

// Function to calculate time ago
function time_ago($timestamp) {
    $currentTime = time();
    $timeDifference = $currentTime - strtotime($timestamp);

    $seconds = $timeDifference;
    $minutes = round($timeDifference / 60);
    $hours = round($timeDifference / 3600);
    $days = round($timeDifference / 86400);
    $weeks = round($timeDifference / 604800);
    $months = round($timeDifference / 2419200);
    $years = round($timeDifference / 29030400);

    if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return $minutes . " minutes ago";
    } else if ($hours <= 24) {
        return $hours . " hours ago";
    } else if ($days <= 7) {
        return $days . " days ago";
    } else if ($weeks <= 4) {
        return $weeks . " weeks ago";
    } else if ($months <= 12) {
        return $months . " months ago";
    } else {
        return $years . " years ago";
    }
}

function timeAgo($datetime)
{
    $commentTime = new DateTime($datetime);
    $now = new DateTime();
    $interval = $now->diff($commentTime);

    if ($interval->y > 0) {
        return $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
    } elseif ($interval->m > 0) {
        return $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
    } elseif ($interval->d > 0) {
        return $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
    } elseif ($interval->h > 0) {
        return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
    } elseif ($interval->i > 0) {
        return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}

function timeSubAgo($datetime, $showFullDateAfter = 31) { // Show full date after 7 days
    $time = time() - strtotime($datetime);
    $days = floor($time / 86400);

    // Show full date if older than specified days
    if ($days > $showFullDateAfter) {
        return date('M j, Y g:i A', strtotime($datetime));
    }

    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    return $days . 'd ago';
}

// Helper functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDateTime($date, $time) {
    return date('M j, Y g:i A', strtotime($date . ' ' . $time));
}

function getPriorityBadge($priority) {
    $badges = [
        'low' => 'badge-success',
        'medium' => 'badge-warning',
        'high' => 'badge-danger'
    ];
    return $badges[$priority] ?? 'badge-secondary';
}



?>