<?php
session_start();
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/php-errors.log');
date_default_timezone_set('Africa/Nairobi');

// Include files from parent directory (sudo folder)
include('../sudo/dbcon.php');
include('../sudo/functions.php');
require_once('../sudo/spaces-helper.php');

// Initialize Spaces Helper
$spacesHelper = new SpacesHelper();

// Helper function for file size formatting
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        if ($bytes == 0 || $bytes == '0' || $bytes === null) return 'Unknown size';
        $bytes = (int)$bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}

// Helper function for file icon
if (!function_exists('getFileIconClass')) {
    function getFileIconClass($extension) {
        $iconMap = [
            'pdf' => 'fas fa-file-pdf',
            'doc' => 'fas fa-file-word',
            'docx' => 'fas fa-file-word',
            'xls' => 'fas fa-file-excel',
            'xlsx' => 'fas fa-file-excel',
            'txt' => 'fas fa-file-alt',
            'jpg' => 'fas fa-file-image',
            'jpeg' => 'fas fa-file-image',
            'png' => 'fas fa-file-image',
            'gif' => 'fas fa-file-image',
            'webp' => 'fas fa-file-image',
        ];
        return $iconMap[$extension] ?? 'fas fa-file';
    }
}

// Helper function to make URLs clickable
if (!function_exists('makeLinksClickable')) {
    function makeLinksClickable($text) {
        // Check if text contains HTML tags
        $hasHtml = (strip_tags($text) != $text);

        if ($hasHtml) {
            // Text has HTML - process it carefully
            // Convert <p> tags to proper spacing
            $text = str_replace(['<p>', '</p>'], ['', '<br>'], $text);

            // Remove other HTML tags but keep the content
            $text = strip_tags($text, '<br><a>');

            // Remove excessive line breaks
            $text = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $text);
        } else {
            // Plain text - escape HTML
            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

            // Convert line breaks to <br>
            $text = nl2br($text);
        }

        // Convert URLs to clickable links (works for both HTML and plain text)
        $pattern = '#\b(https?://[^\s<>"]+)#i';
        $text = preg_replace_callback($pattern, function($matches) {
            $url = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            // Shorten very long URLs for display
            $displayUrl = strlen($url) > 60 ? substr($url, 0, 57) . '...' : $url;
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" style="color: #4a4a4a; text-decoration: underline; word-break: break-word; font-weight: 500;">' . $displayUrl . '</a>';
        }, $text);

        return $text;
    }
}

$errorMessage = '';
$taskData = null;

// Check if share token is provided
if (isset($_GET['token'])) {
    $shareToken = $_GET['token'];

    // Decode the token (format: base64(taskId_randomString))
    $decoded = base64_decode($shareToken);
    if ($decoded === false) {
        $errorMessage = 'Invalid share link.';
    } else {
        // Extract task ID from the decoded token
        $parts = explode('_', $decoded);
        if (count($parts) < 2) {
            $errorMessage = 'Invalid share link format.';
        } else {
            $taskId = $parts[0];

            // Fetch task from database
            $query = "SELECT * FROM tbltasks WHERE id = ?";
            $stmt = $con->prepare($query);
            $stmt->bind_param("i", $taskId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                $errorMessage = 'Task not found.';
            } else {
                $taskData = $result->fetch_assoc();
            }
            $stmt->close();
        }
    }
} else {
    $errorMessage = 'No share token provided.';
}

// Prepare task display data
if ($taskData) {
    $taskId = $taskData['id'];
    $taskTopic = $taskData['topic'];
    $taskSubject = $taskData['subject'];
    $taskAccount = $taskData['account'];
    $taskCreatedOn = $taskData['create_date'];
    $taskStatus = $taskData['status'];
    $taskDescription = $taskData['description'];
    $taskWriter = $taskData['writer'];
    $taskDueDate = $taskData['due_date'];
    $taskCPP = $taskData['cpp'];
    $taskPages = $taskData['pages'];
    $submittedOn = $taskData['submitted_on'];
    $completedOn = $taskData['completed_on'];

    // Fetch task files from database
    $taskFiles = [];
    $submittedFilesArray = [];
    $targetTimestamp = strtotime($taskDueDate) * 1000;
    $timerId = 'timer-' . uniqid();

    // Try common table structures
    $possibleTables = [
        ['table' => 'tbl_task_files', 'task_col' => 'task_id', 'type_col' => 'file_type'],
        ['table' => 'task_files', 'task_col' => 'task_id', 'type_col' => 'type'],
        ['table' => 'files', 'task_col' => 'task_id', 'type_col' => 'category'],
    ];

    foreach ($possibleTables as $tableConfig) {
        $checkTable = "SHOW TABLES LIKE '{$tableConfig['table']}'";
        $tableExists = mysqli_query($con, $checkTable);

        if (mysqli_num_rows($tableExists) > 0) {
            // Fetch task files
            $filesQuery = "SELECT * FROM {$tableConfig['table']} WHERE {$tableConfig['task_col']} = ? AND {$tableConfig['type_col']} = 'task' AND is_deleted = 0 ORDER BY id DESC";
            $filesStmt = $con->prepare($filesQuery);
            if ($filesStmt) {
                $filesStmt->bind_param("i", $taskId);
                $filesStmt->execute();
                $filesResult = $filesStmt->get_result();
                while ($row = $filesResult->fetch_assoc()) {
                    $taskFiles[] = $row;
                }
                $filesStmt->close();
            }

            // Fetch submitted files
            $submittedQuery = "SELECT * FROM {$tableConfig['table']} WHERE {$tableConfig['task_col']} = ? AND {$tableConfig['type_col']} = 'submitted' AND is_deleted = 0  ORDER BY id DESC";
            $submittedStmt = $con->prepare($submittedQuery);
            if ($submittedStmt) {
                $submittedStmt->bind_param("i", $taskId);
                $submittedStmt->execute();
                $submittedResult = $submittedStmt->get_result();
                while ($row = $submittedResult->fetch_assoc()) {
                    $submittedFilesArray[] = $row;
                }
                $submittedStmt->close();
            }

            break; // Found the table, stop looking
        }
    }

    // Calculate if late
    $due_date = new DateTime($taskData['due_date']);
    $currentDateTime = new DateTime();
    $interval = $currentDateTime->diff($due_date);
    $isLate = ($due_date < $currentDateTime) ? true : false;

    // Prepare status badge
    $statusClass = '';
    $statusText = '';
    switch ($taskData["status"]) {
        case 'In Progress':
            $statusClass = 'status-progress';
            $statusText = 'In Progress';
            break;
        case 'In Revision':
            $statusClass = 'status-revision';
            $statusText = 'In Revision';
            break;
        case 'Submitted':
            $statusClass = 'status-submitted';
            $statusText = 'Submitted';
            break;
        case 'Completed':
            $statusClass = 'status-completed';
            $statusText = 'Completed';
            break;
        case 'Cancelled':
            $statusClass = 'status-cancelled';
            $statusText = 'Cancelled';
            break;
        default:
            $statusClass = 'status-progress';
            $statusText = $taskData["status"];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $taskData ? 'Task #' . $taskId . ' - ' . htmlspecialchars($taskTopic) : 'Task View'; ?> | iTasker</title>

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicons/favicon.ico">
    <meta name="theme-color" content="#000000">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-gradient: linear-gradient(135deg, #2d2d2d 0%, #000000 100%);
            --secondary-gradient: linear-gradient(135deg, #4a4a4a 0%, #2d2d2d 100%);
            --success-gradient: linear-gradient(135deg, #6b6b6b 0%, #3a3a3a 100%);
            --warning-gradient: linear-gradient(135deg, #5a5a5a 0%, #2a2a2a 100%);
            --card-bg: rgba(255, 255, 255, 0.98);
            --glass-bg: rgba(255, 255, 255, 0.15);
            --glass-border: rgba(255, 255, 255, 0.25);
            --text-primary: #1a202c;
            --text-secondary: #718096;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.16);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #000000 25%, #2d2d2d 50%, #0a0a0a 75%, #1a1a1a 100%);
            background-attachment: fixed;
            color: var(--text-primary);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background Shapes */
        .background-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .shape {
            position: absolute;
            opacity: 0.15;
            animation: float 20s infinite ease-in-out;
        }

        .shape-1 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.4) 0%, transparent 70%);
            top: -200px;
            left: -200px;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            top: 50%;
            right: -300px;
            animation-delay: -7s;
        }

        .shape-3 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.35) 0%, transparent 70%);
            bottom: -150px;
            left: 30%;
            animation-delay: -14s;
        }

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) scale(1);
            }
            33% {
                transform: translate(50px, -50px) scale(1.1);
            }
            66% {
                transform: translate(-30px, 30px) scale(0.9);
            }
        }

        /* Particle System */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 8px;
            height: 8px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.9), 0 0 30px rgba(255, 255, 255, 0.6);
            animation: rise linear infinite;
        }

        @keyframes rise {
            0% {
                transform: translateY(0) translateX(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(100px);
                opacity: 0;
            }
        }

        /* Navigation */
        .nav {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }

        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-logo {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, rgba(255, 255, 255, 0.8) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            letter-spacing: -0.5px;
        }

        .nav-login {
            padding: 10px 24px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .nav-login:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Hero Section */
        .hero {
            padding: 80px 24px;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            animation: fadeInUp 0.8s ease;
        }

        .task-id {
            display: inline-block;
            padding: 8px 20px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
            backdrop-filter: blur(10px);
            letter-spacing: 0.5px;
        }

        .hero-title {
            font-size: 48px;
            font-weight: 800;
            color: white;
            margin-bottom: 16px;
            line-height: 1.2;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .hero-subtitle {
            font-size: 20px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 32px;
            font-weight: 400;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border-radius: 24px;
            font-size: 14px;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .status-progress {
            background: rgba(100, 100, 100, 0.3);
            color: #ffffff;
        }

        .status-revision {
            background: rgba(150, 150, 150, 0.3);
            color: #ffffff;
        }

        .status-submitted {
            background: rgba(120, 120, 120, 0.3);
            color: #ffffff;
        }

        .status-completed {
            background: rgba(180, 180, 180, 0.3);
            color: #ffffff;
        }

        .status-cancelled {
            background: rgba(80, 80, 80, 0.3);
            color: #ffffff;
        }

        /* Container */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 24px 80px;
            position: relative;
            z-index: 10;
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(20px);
            animation: fadeInUp 0.6s ease;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.2);
        }

        .card-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 24px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(135deg, #4a4a4a 0%, #1a1a1a 100%);
            border-radius: 4px;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
        }

        .info-item {
            padding: 20px;
            background: linear-gradient(135deg, rgba(80, 80, 80, 0.08) 0%, rgba(40, 40, 40, 0.08) 100%);
            border-radius: 16px;
            border: 1px solid rgba(120, 120, 120, 0.15);
            transition: all 0.3s ease;
        }

        .info-item:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, rgba(80, 80, 80, 0.15) 0%, rgba(40, 40, 40, 0.15) 100%);
            border-color: rgba(120, 120, 120, 0.25);
        }

        .info-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .info-value.highlight {
            background: linear-gradient(135deg, #4a4a4a 0%, #1a1a1a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        .info-value.alert {
            color: #ef4444;
            font-weight: 700;
        }

        /* Description */
        .description-box {
            padding: 24px;
            background: linear-gradient(135deg, rgba(80, 80, 80, 0.05) 0%, rgba(40, 40, 40, 0.05) 100%);
            border-radius: 16px;
            border: 1px solid rgba(120, 120, 120, 0.12);
            line-height: 1.8;
            color: var(--text-primary);
            font-size: 15px;
        }

        /* File Items */
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: linear-gradient(135deg, rgba(80, 80, 80, 0.08) 0%, rgba(40, 40, 40, 0.08) 100%);
            border-radius: 16px;
            border: 1px solid rgba(120, 120, 120, 0.15);
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .file-item:hover {
            background: linear-gradient(135deg, rgba(80, 80, 80, 0.15) 0%, rgba(40, 40, 40, 0.15) 100%);
            border-color: rgba(120, 120, 120, 0.25);
            transform: translateX(4px);
        }

        .file-item:last-child {
            margin-bottom: 0;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
        }

        .file-icon {
            font-size: 32px;
            color: #4a4a4a;
            width: 48px;
            text-align: center;
        }

        .file-details {
            flex: 1;
        }

        .file-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            word-break: break-word;
        }

        .file-size {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #4a4a4a 0%, #1a1a1a 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
            background: linear-gradient(135deg, #5a5a5a 0%, #2a2a2a 100%);
        }

        .file-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .view-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
            border: none;
            cursor: pointer;
            font-family: inherit;
        }

        .view-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.45);
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        /* File Viewer Modal */
        .file-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(6px);
        }

        .file-modal-overlay.active {
            display: flex;
        }

        .file-modal {
            background: #fff;
            border-radius: 20px;
            width: 100%;
            max-width: 1100px;
            height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
            animation: modalIn 0.25s ease;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.96) translateY(16px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }

        .file-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #fff;
            flex-shrink: 0;
        }

        .file-modal-title {
            font-size: 15px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 70%;
        }

        .file-modal-close {
            background: rgba(255,255,255,0.15);
            border: none;
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            flex-shrink: 0;
        }

        .file-modal-close:hover {
            background: rgba(255,255,255,0.28);
        }

        .file-modal-body {
            flex: 1;
            overflow: hidden;
            background: #f0f0f0;
            position: relative;
        }

        .file-modal-body iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }

        .file-modal-loading {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            background: #f8f9fa;
            color: #4a4a4a;
            font-size: 15px;
            font-weight: 500;
        }

        .file-modal-spinner {
            width: 44px;
            height: 44px;
            border: 4px solid #e0e0e0;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .file-modal-unsupported {
            position: absolute;
            inset: 0;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            background: #f8f9fa;
            text-align: center;
            padding: 32px;
        }

        .file-modal-unsupported i {
            font-size: 56px;
            color: #9ca3af;
        }

        .file-modal-unsupported p {
            font-size: 16px;
            font-weight: 600;
            color: #374151;
        }

        .file-modal-unsupported span {
            font-size: 14px;
            color: #6b7280;
        }

        @media (max-width: 640px) {
            .file-actions {
                flex-direction: column;
                gap: 8px;
            }
            .view-btn, .download-btn {
                width: 100%;
                justify-content: center;
            }
            .file-modal {
                height: 95vh;
                border-radius: 14px;
            }
        }

        /* Notice Box */
        .notice-box {
            background: linear-gradient(135deg, rgba(100, 100, 100, 0.15) 0%, rgba(60, 60, 60, 0.15) 100%);
            border: 2px solid rgba(150, 150, 150, 0.3);
            border-radius: 20px;
            padding: 32px;
            text-align: center;
            color: var(--text-primary);
            margin-top: 32px;
            backdrop-filter: blur(10px);
        }

        .notice-box a {
            color: #4a4a4a;
            font-weight: 600;
            text-decoration: none;
            border-bottom: 2px solid #4a4a4a;
            transition: all 0.3s ease;
        }

        .notice-box a:hover {
            color: #1a1a1a;
            border-bottom-color: #1a1a1a;
        }

        /* Error State */
        .error-state {
            text-align: center;
            padding: 120px 24px;
            position: relative;
            z-index: 10;
        }

        .error-icon {
            font-size: 80px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 24px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        .error-title {
            font-size: 48px;
            font-weight: 800;
            color: white;
            margin-bottom: 16px;
        }

        .error-message {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.9);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Footer */
        .footer {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--glass-border);
            padding: 32px 24px;
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
            margin-top: auto;
            position: relative;
            z-index: 10;
        }

        .countdown-timer {
            font-family: 'Courier New', Courier, monospace; /* Keeps numbers aligned */
            font-weight: 600;
            color: #27ae60; /* Green for active time */
            white-space: nowrap; /* Prevents line breaks during countdown */
            transition: color 0.3s ease;
        }

        /* Style when time is expired */
        .countdown-timer.alert,
        .expired-text {
            color: #c0392b; /* Red for expired */
        }

        /* Optional: Style for low time (e.g., less than 1 hour) */
        .countdown-timer.urgent {
            color: #e67e22; /* Orange */
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card:nth-child(1) {
            animation-delay: 0s;
        }

        .card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .card:nth-child(3) {
            animation-delay: 0.2s;
        }

        .card:nth-child(4) {
            animation-delay: 0.3s;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 32px;
            }

            .hero-subtitle {
                font-size: 16px;
            }

            .card {
                padding: 24px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .file-item {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .download-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Loading Skeleton */
        .skeleton {
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.1) 25%, rgba(255, 255, 255, 0.2) 50%, rgba(255, 255, 255, 0.1) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }
    </style>
</head>
<body>
<!-- Background Animation -->
<div class="background-animation">
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>
</div>

<!-- Particle System -->
<div class="particles" id="particles">
    <!-- Test particles to verify visibility -->
    <div class="particle" style="left: 10%; bottom: -10px; animation-delay: 0s; animation-duration: 25s;"></div>
    <div class="particle" style="left: 30%; bottom: -10px; animation-delay: 2s; animation-duration: 30s;"></div>
    <div class="particle" style="left: 50%; bottom: -10px; animation-delay: 4s; animation-duration: 28s;"></div>
    <div class="particle" style="left: 70%; bottom: -10px; animation-delay: 6s; animation-duration: 32s;"></div>
    <div class="particle" style="left: 90%; bottom: -10px; animation-delay: 8s; animation-duration: 27s;"></div>
</div>

<!-- Navigation -->
<nav class="nav">
    <div class="nav-content">
        <a href="../index" class="nav-logo">iTasker</a>
        <a href="../login" class="nav-login">Sign In</a>
    </div>
</nav>

<?php if ($errorMessage): ?>
    <!-- Error State -->
    <div class="error-state">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h1 class="error-title">Oops!</h1>
        <p class="error-message"><?php echo htmlspecialchars($errorMessage); ?></p>
        <p class="error-message" style="margin-top: 24px;">
            Please check your link and try again.
        </p>
    </div>
<?php elseif ($taskData): ?>
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="task-id">Task #<?php echo $taskId; ?></div>
            <h1 class="hero-title"><?php echo htmlspecialchars($taskTopic); ?></h1>
            <p class="hero-subtitle"><?php echo htmlspecialchars($taskSubject); ?></p>
            <div>
                    <span class="status-badge <?php echo $statusClass; ?>">
                        <?php echo $statusText; ?>
                    </span>
                <?php if ($isLate && $taskData["status"] === 'In Progress'): ?>
                    <span class="status-badge status-cancelled" style="margin-left: 8px;">
                            <i class="fas fa-exclamation-triangle"></i> Overdue
                        </span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <!-- Task Details -->
        <div class="card">
            <h2 class="card-title"><i class="fas fa-info-circle" style="color: #4a4a4a;"></i> Task Details</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Pages</div>
                    <div class="info-value highlight"><?php echo htmlspecialchars($taskPages); ?> pages</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Due Date</div>
                    <div class="info-value <?php echo $isLate ? 'alert' : ''; ?>">
                        <?php echo date('M j, Y h:i A', strtotime($taskDueDate)); ?>
                    </div>
                </div>
                <?php if ($taskStatus = "In Progress"): ?>
                    <div class="info-item">
                        <div class="info-label">Time Remaining</div>
                        <div class="info-value countdown-timer" id="<?php echo $timerId; ?>" data-target="<?php echo $targetTimestamp; ?>">
                            Calculating...
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($submittedOn): ?>
                    <div class="info-item">
                        <div class="info-label">Submitted</div>
                        <div class="info-value"><?php echo date('M j, Y h:i A', strtotime($submittedOn)); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($completedOn): ?>
                    <div class="info-item">
                        <div class="info-label">Completed</div>
                        <div class="info-value"><?php echo date('M j, Y h:i A', strtotime($completedOn)); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Description -->
        <div class="card">
            <h2 class="card-title"><i class="fas fa-align-left" style="color: #4a4a4a;"></i> Description</h2>
            <div class="description-box">
                <?php echo makeLinksClickable($taskDescription); ?>
            </div>
        </div>

        <!-- Task Files -->
        <?php if (count($taskFiles) > 0): ?>
            <div class="card">
                <h2 class="card-title"><i class="fas fa-paperclip" style="color: #4a4a4a;"></i> Task Files (<?php echo count($taskFiles); ?>)</h2>
                <?php foreach ($taskFiles as $file):
                    // Extract file information
                    $fileName = $file['filename'] ?? $file['file_name'] ?? $file['name'] ?? 'Unknown';
                    $fileSize = $file['filesize'] ?? $file['file_size'] ?? $file['size'] ?? 0;
                    $fileUrl = $file['file_url'] ?? $file['url'] ?? $file['spaces_path'] ?? '';
                    $spacesPath = $file['spaces_path'] ?? $file['path'] ?? '';

                    // If no URL, generate it from spaces_path
                    if (empty($fileUrl) && !empty($spacesPath)) {
                        $fileUrl = $spacesHelper->getFileUrl($spacesPath);
                    }

                    $fileSize = formatFileSize($fileSize);
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $iconClass = getFileIconClass($extension);
                    ?>
                    <div class="file-item">
                        <div class="file-info">
                            <i class="<?php echo $iconClass; ?> file-icon"></i>
                            <div class="file-details">
                                <div class="file-name"><?php echo htmlspecialchars($fileName); ?></div>
                                <div class="file-size"><?php echo $fileSize; ?></div>
                            </div>
                        </div>
                        <div class="file-actions">
                            <button class="view-btn"
                                    onclick="openFileViewer('<?php echo htmlspecialchars(addslashes($fileUrl)); ?>', '<?php echo htmlspecialchars(addslashes($fileName)); ?>', '<?php echo $extension; ?>')">
                                <i class="fas fa-eye"></i>
                                View
                            </button>
                            <a href="<?php echo htmlspecialchars($fileUrl); ?>"
                               class="download-btn"
                               target="_blank"
                               rel="noopener noreferrer">
                                <i class="fas fa-download"></i>
                                Download
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Submitted Files -->
        <?php if (count($submittedFilesArray) > 0): ?>
            <div class="card">
                <h2 class="card-title"><i class="fas fa-check-circle" style="color: #4a4a4a;"></i> Submitted Files (<?php echo count($submittedFilesArray); ?>)</h2>
                <?php foreach ($submittedFilesArray as $file):
                    // Extract file information
                    $fileName = $file['filename'] ?? $file['file_name'] ?? $file['name'] ?? 'Unknown';
                    $fileSize = $file['filesize'] ?? $file['file_size'] ?? $file['size'] ?? 0;
                    $fileUrl = $file['file_url'] ?? $file['url'] ?? $file['spaces_path'] ?? '';
                    $spacesPath = $file['spaces_path'] ?? $file['path'] ?? '';

                    // If no URL, generate it from spaces_path
                    if (empty($fileUrl) && !empty($spacesPath)) {
                        $fileUrl = $spacesHelper->getFileUrl($spacesPath);
                    }

                    $fileSize = formatFileSize($fileSize);
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $iconClass = getFileIconClass($extension);
                    ?>
                    <div class="file-item">
                        <div class="file-info">
                            <i class="<?php echo $iconClass; ?> file-icon"></i>
                            <div class="file-details">
                                <div class="file-name"><?php echo htmlspecialchars($fileName); ?></div>
                                <div class="file-size"><?php echo $fileSize; ?></div>
                            </div>
                        </div>
                        <div class="file-actions">
                            <button class="view-btn"
                                    onclick="openFileViewer('<?php echo htmlspecialchars(addslashes($fileUrl)); ?>', '<?php echo htmlspecialchars(addslashes($fileName)); ?>', '<?php echo $extension; ?>')">
                                <i class="fas fa-eye"></i>
                                View
                            </button>
                            <a href="<?php echo htmlspecialchars($fileUrl); ?>"
                               class="download-btn"
                               target="_blank"
                               rel="noopener noreferrer">
                                <i class="fas fa-download"></i>
                                Download
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Notice -->
        <div class="notice-box">
            <i class="fas fa-info-circle" style="font-size: 24px; margin-bottom: 12px; color: white;"></i>
            <div style="font-size: 15px; line-height: 1.6; color: white;">
                This is a read-only view of the task.
                <br>
                <a href="../login">Sign in</a> to access full task management features.
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Footer -->
<footer class="footer">
    <div style="font-size: 15px;">Powered by iTasker &copy; <?php echo date('Y'); ?></div>
    <div style="margin-top: 8px; font-size: 14px;">Designed with ♥</div>
</footer>

<!-- File Viewer Modal -->
<div class="file-modal-overlay" id="fileViewerModal" onclick="handleModalOverlayClick(event)">
    <div class="file-modal">
        <div class="file-modal-header">
            <div class="file-modal-title" id="fileViewerTitle"></div>
            <button class="file-modal-close" onclick="closeFileViewer()" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="file-modal-body" id="fileModalBody">
            <div class="file-modal-loading" id="fileViewerLoading">
                <div class="file-modal-spinner"></div>
                <span>Loading preview…</span>
            </div>
            <div class="file-modal-unsupported" id="fileViewerUnsupported">
                <i class="fas fa-file-alt"></i>
                <p>Preview not available</p>
                <span>This file type cannot be previewed. Please download it to view.</span>
            </div>
            <iframe id="fileViewerFrame" style="display:none;" allowfullscreen></iframe>
        </div>
    </div>
</div>

<script>
    // File extensions supported by Microsoft Office Viewer
    const MS_VIEWER_EXTS  = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'];
    // Extensions viewable directly in the browser iframe
    const BROWSER_EXTS    = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'txt'];

    function openFileViewer(fileUrl, fileName, extension) {
        const modal    = document.getElementById('fileViewerModal');
        const frame    = document.getElementById('fileViewerFrame');
        const loading  = document.getElementById('fileViewerLoading');
        const unsupported = document.getElementById('fileViewerUnsupported');
        const title    = document.getElementById('fileViewerTitle');

        // Reset state
        frame.style.display = 'none';
        frame.src = '';
        loading.style.display  = 'flex';
        unsupported.style.display = 'none';
        title.textContent = fileName;

        const ext = (extension || '').toLowerCase();
        let viewerUrl = '';

        if (MS_VIEWER_EXTS.includes(ext)) {
            // Use Microsoft Office Online Viewer
            viewerUrl = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(fileUrl);
        } else if (BROWSER_EXTS.includes(ext)) {
            // Open directly in the iframe
            viewerUrl = fileUrl;
        } else {
            // Unsupported — show fallback message
            loading.style.display = 'none';
            unsupported.style.display = 'flex';
        }

        if (viewerUrl) {
            frame.src = viewerUrl;
            frame.onload = function() {
                loading.style.display = 'none';
                frame.style.display = 'block';
            };
            frame.onerror = function() {
                loading.style.display = 'none';
                unsupported.style.display = 'flex';
            };
        }

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeFileViewer() {
        const modal = document.getElementById('fileViewerModal');
        const frame = document.getElementById('fileViewerFrame');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        // Small delay so the animation completes before clearing src
        setTimeout(() => { frame.src = ''; }, 300);
    }

    function handleModalOverlayClick(e) {
        if (e.target === document.getElementById('fileViewerModal')) {
            closeFileViewer();
        }
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeFileViewer();
    });

    (function() {
        const timerElement = document.getElementById('<?php echo $timerId; ?>');
        const targetTime = parseInt(timerElement.getAttribute('data-target'));

        function updateTimer() {
            const now = new Date().getTime();
            const distance = targetTime - now;

            // If countdown is finished
            if (distance < 0) {
                timerElement.innerHTML = "0h 0m 0s";
                timerElement.classList.add('alert');
                return;
            }

            // Time calculations
            // Calculate total hours (including days converted to hours)
            const totalHours = Math.floor(distance / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            // Format exactly as requested: 20 hrs 45 mins 14 secs
            const formattedTime = `${totalHours}h ${minutes}m ${seconds}s`;

            timerElement.innerHTML = formattedTime;
        }

        // Run immediately then every second
        updateTimer();
        setInterval(updateTimer, 1000);
    })();
    // Generate floating particles
    function createParticles() {
        const particlesContainer = document.getElementById('particles');
        if (!particlesContainer) {
            console.error('Particles container not found!');
            return;
        }

        const particleCount = 80; // Increased for better visibility
        console.log('Creating ' + particleCount + ' particles...');

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.bottom = '-10px'; // Start from bottom
            particle.style.animationDuration = (Math.random() * 15 + 20) + 's';
            particle.style.animationDelay = Math.random() * 10 + 's';

            // Vary particle sizes
            const size = Math.random() * 4 + 6; // 6-10px
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';

            particlesContainer.appendChild(particle);
        }

        console.log('Particles created:', particlesContainer.children.length);
    }

    // Initialize particles on load
    window.addEventListener('load', createParticles);

    // Add smooth scroll behavior
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
</script>
</body>
</html>