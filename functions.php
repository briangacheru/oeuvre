<?php
function email_exists($email)
{
    global $con;

    $sql = "SELECT id FROM tblwriters WHERE email = '$email'";

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

    $sql = "SELECT id FROM tblwriters WHERE username = '$username'";

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
 * Gets the current version number from the sudo/version.json file
 * @return string The current version number
 */
function getVersionNumber() {
    $versionFile = __DIR__ . '/sudo/version.json';

    // Check if version file exists
    if (!file_exists($versionFile)) {
        return "v1.0.0"; // Default version if file doesn't exist
    }

    // Read current version
    $versionData = json_decode(file_get_contents($versionFile), true);

    // Check if parsing was successful
    if (json_last_error() !== JSON_ERROR_NONE || !isset($versionData['major'])) {
        return "v1.0.0"; // Default version if file is invalid
    }

    // Return formatted version string
    return "v{$versionData['major']}.{$versionData['minor']}.{$versionData['patch']}";
}

function check_login() {
    if (!isset($_SESSION['sessionWriter']) || strlen($_SESSION['sessionWriter']) == 0) {
        $host = $_SERVER['HTTP_HOST'];
        $uri  = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $extra = "login.php";
        $_SESSION["id"] = "";
        header("Location: http://$host$uri/$extra");
        exit();
    }
}



function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }
    return $bytes;
}

function updateUserStatus($email, $userType, $isOnline) {
    global $con;
    $table = $userType === 'admin' ? 'tblwriters' : 'tblwriters';
    $lastSeen = $isOnline ? 'NOW()' : 'NOW()';

    $query = "UPDATE $table SET is_online = ?, last_seen = $lastSeen WHERE email = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("is", $isOnline, $email);
    $stmt->execute();
    $stmt->close();
}

function logout() {

    if (isset($_SESSION['sessionWriter'])) {
        $email = $_SESSION['sessionWriter'];
        $userType = 'admin'; // Adjust if necessary
        updateUserStatus($email, $userType, false);

        // Store last page in cookie before destroying session
        if (isset($_SESSION['last_page']) || isset($_SERVER['REQUEST_URI'])) {
            $lastPage = isset($_SESSION['last_page']) ? $_SESSION['last_page'] : $_SERVER['REQUEST_URI'];
            setcookie('last_page_before_logout', $lastPage, time() + 300, '/', '', isset($_SERVER["HTTPS"]), true);
        }
    }

    session_unset();    // Unset $_SESSION variables
    session_destroy();   // Destroy session data on the server
    setcookie('PHPSESSID', '', time() - 3600, '/'); // Destroy session data in the cookie
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

function timeDueIn($datetime, $showFullDateAfter = 31, $returnArray = false) {
    $currentTime = time();
    $dueTime = strtotime($datetime);
    $timeDiff = $dueTime - $currentTime;

    $absDays = abs(floor($timeDiff / 86400));
    $cssClass = '';

    if ($absDays > $showFullDateAfter) {
        $text = date('M j, Y g:i A', $dueTime);
        $cssClass = 'text-muted';
    } elseif ($timeDiff < 0) {
        // Overdue
        $absTime = abs($timeDiff);
        if ($absTime < 3600) $text = floor($absTime/60) . 'mins overdue';
        elseif ($absTime < 86400) $text = floor($absTime/3600) . 'hrs overdue';
        else $text = $absDays . 'days overdue';
        $cssClass = 'text-danger';
    } else {
        // Due in future
        if ($timeDiff < 3600) {
            $text = floor($timeDiff/60) . 'mins left';
            $cssClass = 'text-danger'; // Very urgent
        } elseif ($timeDiff < 86400) {
            $text = floor($timeDiff/3600) . 'hrs left';
            $cssClass = 'text-warning'; // Urgent
        } elseif ($absDays <= 3) {
            $text = $absDays . 'days left';
            $cssClass = 'text-warning'; // Soon
        } else {
            $text = $absDays . 'days left';
            $cssClass = 'text-success'; // Plenty of time
        }
    }

    return $returnArray ? ['text' => $text, 'class' => $cssClass] : $text;
}


?>