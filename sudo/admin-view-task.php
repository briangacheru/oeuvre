<?php include "head.php";
if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        if ($bytes == 0 || $bytes == '0' || $bytes === null) return 'Unknown size';
        $bytes = (int)$bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}

if (!function_exists('getFileIconClass')) {
    function getFileIconClass($extension) {
        $iconMap = [
            'pdf' => 'fas fa-file-pdf text-danger',
            'doc' => 'fas fa-file-word text-primary',
            'docx' => 'fas fa-file-word text-primary',
            'xls' => 'fas fa-file-excel text-success',
            'xlsx' => 'fas fa-file-excel text-success',
            'txt' => 'fas fa-file-alt text-secondary',
            'jpg' => 'fas fa-file-image text-info',
            'jpeg' => 'fas fa-file-image text-info',
            'png' => 'fas fa-file-image text-info',
            'gif' => 'fas fa-file-image text-info',
            'webp' => 'fas fa-file-image text-info',
        ];
        return $iconMap[$extension] ?? 'fas fa-file text-muted';
    }
}

if (isset($_GET['task_id'])) {
    $encodedId = $_GET['task_id'];
    $taskId = base64_decode($encodedId);
} else {
    $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
        <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
        <p class="mb-0 flex-1">Invalid task ID!</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
    header("Location: index");
    exit;
}

// Define variables for task data
$taskTopic = $taskSubject = $taskAccount = $taskCreatedOn = $taskStatus = $taskIsPaid = $taskDescription = $taskWriter = $taskWriterEmail = $taskDueDate = $taskCPP = $taskPages = $taskSubmitTime = $submittedOn = $completedOn = '';

// Retrieve the task data from the database
$sql2 = "SELECT * FROM tbltasks WHERE id='$taskId'";
$result = mysqli_query($con, $sql2);

// CHECK IF TASK EXISTS - CRITICAL FIX
if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
        <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
        <p class="mb-0 flex-1">Task not found!</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
    header("Location: index");
    exit;
}

$rowTask = mysqli_fetch_array($result);

// DOUBLE CHECK - if fetch failed, redirect
if (!$rowTask) {
    $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
        <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
        <p class="mb-0 flex-1">Task not found!</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
    header("Location: index");
    exit;
}

// NOW it's safe to access the task data
$id = base64_encode($rowTask["id"]);
$taskTopic = $rowTask["topic"];
$taskSubject = $rowTask["subject"];
$taskAccount = $rowTask["account"];
$taskCreatedOn = $rowTask["create_date"];
$taskStatus = $rowTask["status"];
$taskIsPaid = $rowTask["is_paid"];
$taskDescription = $rowTask["description"];
$taskWriter = $rowTask["writer"];
$taskWriterEmail = $rowTask["email"];
$taskDueDate = $rowTask["due_date"];
$taskCPP = $rowTask["cpp"];
$taskPages = $rowTask["pages"];
$taskSubmitTime = $rowTask['submitted_on'];
$submittedOn = $rowTask['submitted_on'];
$completedOn = $rowTask['completed_on'];

// Continue with the rest of your code...
$due_date = new DateTime($rowTask['due_date']);
$currentDateTime = new DateTime();
$interval = $currentDateTime->diff($due_date);
$isLate = ($due_date < $currentDateTime) ? true : false;
// Determine badge based on task status
$statusBadge = '';
switch ($rowTask["status"]) {
    case 'In Progress':
        $statusBadge = '<div class="badge rounded-pill badge-subtle-warning fs-11">In progress<span class="fas fa-stream ms-1" data-fa-transform="shrink-2"></span></div>';
        break;
    case 'In Revision':
        $statusBadge = '<span class="badge badge rounded-pill badge-subtle-warning">In Revision<span class="ms-1 fas fa-flag" data-fa-transform="shrink-2"></span></span>';
        break;
    case 'Cancelled':
        $statusBadge = '<div class="badge rounded-pill badge-subtle-danger fs-11">Cancelled<span class="fas fa-ban ms-1" data-fa-transform="shrink-2"></span></div>';
        break;
    case 'Draft':
        $statusBadge = '<div class="badge rounded-pill badge-subtle-danger fs-11">Draft<span class="fas fa-edit ms-1" data-fa-transform="shrink-2"></span></div>';
        break;
    case 'Unconfirmed':
        $statusBadge = '<div class="badge rounded-pill badge-subtle-primary fs-11">Unconfirmed<span class="fas fa-question ms-1" data-fa-transform="shrink-2"></span></div>';
        break;
    case 'Submitted':
        $statusBadge = '<div class="badge rounded-pill badge-subtle-info fs-11">Submitted<span class="fas fa-file ms-1" data-fa-transform="shrink-2"></span></div>';
        break;
    case 'Completed':
        $statusBadge = '<div class="badge rounded-pill badge-subtle-success fs-11">Completed<span class="fas fa-check ms-1" data-fa-transform="shrink-2"></span></div>';
        break;
}
if ($isLate && $rowTask["status"] === 'In Progress') {
    $statusBadge .= ' <span class="badge badge rounded-pill badge-subtle-danger">Late<span class="ms-1 fa fa-exclamation-triangle" data-fa-transform="shrink-2"></span></span>';
}
// Correctly retrieve is_paid status from the $rowTask
$is_paid = $rowTask['is_paid']; // Assuming 'is_paid' is the column name in your database
// Determine badge based on payment status
$statusBadgeClass = ($is_paid == 1) ? 'badge-subtle-success' : 'badge-subtle-warning';
$statusBadgeText = ($is_paid == 1) ? 'Paid' : 'Unpaid';
$statusBadgePay = "<span class='badge badge rounded-pill $statusBadgeClass'>$statusBadgeText</span>";

$is_confirmed = $rowTask['is_confirmed']; // Assuming 'is_confirmed' is the column name in your database
if ($is_confirmed == 0) {
    $confirmationClass = 'bg-light';
    $confirmationText = 'Confirmed';
} elseif ($is_confirmed == 1) {
    $confirmationClass = 'bg-primary';
    $confirmationText = 'Unconfirmed';
} elseif ($is_confirmed == 2) {
    $confirmationClass = 'bg-danger';
    $confirmationText = 'Declined';
}
$confirmation = "<span class='badge badge rounded-pill $confirmationClass'>$confirmationText</span>";

$publish = $rowTask['publish'];
if ($publish == !1) {
    $publishText = 'Unpublished';
}
?>
<?php
// Determine current user type and ID
$currentUserType = 'admin'; // default
$currentUserId = null;

// Check if user is admin
$adminCheckQuery = "SELECT id FROM tbladmin WHERE email = '" . mysqli_real_escape_string($con, $_SESSION['odmsaid']) . "'";
$adminResult = mysqli_query($con, $adminCheckQuery);

if ($adminResult && mysqli_num_rows($adminResult) > 0) {
    $currentUserType = 'admin';
    $adminData = mysqli_fetch_assoc($adminResult);
    $currentUserId = $adminData['id'];
} else {
    // Check if user is writer
    $writerCheckQuery = "SELECT id FROM tblwriters WHERE email = '" . mysqli_real_escape_string($con, $_SESSION['odmsaid']) . "'";
    $writerResult = mysqli_query($con, $writerCheckQuery);

    if ($writerResult && mysqli_num_rows($writerResult) > 0) {
        $currentUserType = 'writer';
        $writerData = mysqli_fetch_assoc($writerResult);
        $currentUserId = $writerData['id'];
    }
}

// Determine receiver based on sender type
if ($currentUserType === 'admin') {
    // Admin sends to writer assigned to this task
    // Get writer ID from task
    if (!empty($taskWriterEmail)) {
        $writerIdQuery = "SELECT id FROM tblwriters WHERE email = '" . mysqli_real_escape_string($con, $taskWriterEmail) . "'";
        $writerIdResult = mysqli_query($con, $writerIdQuery);

        if ($writerIdResult && mysqli_num_rows($writerIdResult) > 0) {
            $writerIdData = mysqli_fetch_assoc($writerIdResult);
            $receiverId = $writerIdData['id'];
        } else {
            $receiverId = 0; // Fallback
        }
    } else {
        $receiverId = 0; // No writer email available
    }
    $receiverType = 'writer';
} else {
    // Writer sends to admin
    $adminIdQuery = "SELECT id FROM tbladmin ORDER BY id ASC LIMIT 1";
    $adminIdResult = mysqli_query($con, $adminIdQuery);

    if ($adminIdResult && mysqli_num_rows($adminIdResult) > 0) {
        $adminIdData = mysqli_fetch_assoc($adminIdResult);
        $receiverId = $adminIdData['id'];
    } else {
        $receiverId = 1; // Fallback to admin ID 1
    }
    $receiverType = 'admin';
}
?>
<?php
// Get all comments for this task
$commentsQuery = "SELECT * FROM tbl_task_comments WHERE task_id = ? ORDER BY created_at ASC";
$commentsStmt = mysqli_prepare($con, $commentsQuery);
mysqli_stmt_bind_param($commentsStmt, 'i', $taskId);
mysqli_stmt_execute($commentsStmt);
$commentsResult = mysqli_stmt_get_result($commentsStmt);

$comments = [];
while ($row = mysqli_fetch_assoc($commentsResult)) {
    $comments[] = $row;
}
mysqli_stmt_close($commentsStmt);

// Calculate unread messages using already determined currentUserType from above
$unreadCount = 0;
$firstUnreadIndex = -1;

foreach ($comments as $index => $comment) {
    // Count unread messages from the opposite user type
    if ($currentUserType === 'admin' && $comment['user_type'] === 'writer' && $comment['is_read'] == 0) {
        $unreadCount++;
        if ($firstUnreadIndex === -1) $firstUnreadIndex = $index;
    } elseif ($currentUserType === 'writer' && $comment['user_type'] === 'admin' && $comment['is_read'] == 0) {
        $unreadCount++;
        if ($firstUnreadIndex === -1) $firstUnreadIndex = $index;
    }
}

// Determine conversation status
$conversationStatus = 'Active';
$statusIcon = 'fa-circle';
$statusClass = 'text-success';

if (in_array($taskStatus, ['Completed', 'Cancelled'])) {
    $conversationStatus = 'Closed';
    $statusIcon = 'fa-lock';
    $statusClass = 'text-secondary';
} elseif (!empty($comments)) {
    $lastComment = end($comments);
    $lastCommentTime = strtotime($lastComment['created_at'] . ' UTC');
    $timeDiff = time() - $lastCommentTime;

    if ($timeDiff > 86400) { // More than 24 hours
        $conversationStatus = 'Quiet';
        $statusIcon = 'fa-circle';
        $statusClass = 'text-warning';
    }
}
?>
<?php
// 1. Calculate task timeline metrics
$taskMetrics = [];

// Convert dates for calculations
$createdDate = new DateTime($taskCreatedOn);
$dueDate = new DateTime($taskDueDate);
$currentDate = new DateTime();

// Basic timeline calculations
$totalTimeAllotted = max(1, $createdDate->diff($dueDate)->days); // Ensure minimum 1 day
$timeElapsed = $createdDate->diff($currentDate)->days;
$timeRemaining = $currentDate->diff($dueDate)->days;

// Completion timeline
if ($taskStatus == 'Completed' && !empty($completedOn)) {
    $completedDate = new DateTime($completedOn);
    $actualCompletionTime = $createdDate->diff($completedDate)->days;
    $taskMetrics['completion_efficiency'] = $totalTimeAllotted > 0 ? round(($actualCompletionTime / $totalTimeAllotted) * 100, 1) : 0;
    $taskMetrics['completed_early'] = $completedDate < $dueDate;
    $taskMetrics['days_early_late'] = abs($completedDate->diff($dueDate)->days);
}

// Submission timeline
if ($taskStatus == 'Submitted' && !empty($submittedOn)) {
    $submittedDate = new DateTime($submittedOn);
    $submissionTime = $createdDate->diff($submittedDate)->days;
    $taskMetrics['submission_efficiency'] = $totalTimeAllotted > 0 ? round(($submissionTime / $totalTimeAllotted) * 100, 1) : 0;
    $taskMetrics['submitted_early'] = $submittedDate < $dueDate;
}

// Progress metrics
$taskMetrics['progress_percentage'] = $totalTimeAllotted > 0 ? min(100, round(($timeElapsed / $totalTimeAllotted) * 100, 1)) : 100;
$taskMetrics['urgency_level'] = $timeRemaining <= 1 ? 'critical' : ($timeRemaining <= 3 ? 'high' : ($timeRemaining <= 7 ? 'medium' : 'low'));

// 2. Writer performance analytics for this specific task
$writerAnalytics = [];
if (!empty($taskWriter)) {
    // Get writer's overall stats
    $writerStatsQuery = "SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_tasks,
        AVG(CASE WHEN status = 'Completed' AND completed_on IS NOT NULL AND due_date IS NOT NULL 
            THEN DATEDIFF(completed_on, create_date) END) as avg_completion_days,
        SUM(CASE WHEN status = 'Completed' AND completed_on < due_date THEN 1 ELSE 0 END) as early_completions,
        SUM(pages) as total_pages_written,
        AVG(cpp) as avg_cpp
        FROM tbltasks 
        WHERE writer = ?";

    $stmt = mysqli_prepare($con, $writerStatsQuery);
    mysqli_stmt_bind_param($stmt, 's', $taskWriter);
    mysqli_stmt_execute($stmt);
    $writerResult = mysqli_stmt_get_result($stmt);
    $writerAnalytics = mysqli_fetch_assoc($writerResult);

    // Calculate additional metrics
    if ($writerAnalytics['total_tasks'] > 0) {
        $writerAnalytics['completion_rate'] = round(($writerAnalytics['completed_tasks'] / $writerAnalytics['total_tasks']) * 100, 1);
        $writerAnalytics['early_completion_rate'] = $writerAnalytics['completed_tasks'] > 0 ? round(($writerAnalytics['early_completions'] / $writerAnalytics['completed_tasks']) * 100, 1) : 0;
    } else {
        $writerAnalytics['completion_rate'] = 0;
        $writerAnalytics['early_completion_rate'] = 0;
    }

    // Get writer's recent performance trend (last 10 tasks)
    $recentTasksQuery = "SELECT status, 
        DATEDIFF(COALESCE(completed_on, submitted_on, NOW()), create_date) as days_taken,
        CASE WHEN completed_on < due_date THEN 1 ELSE 0 END as completed_early
        FROM tbltasks 
        WHERE writer = ? 
        ORDER BY create_date DESC 
        LIMIT 10";

    $stmt = mysqli_prepare($con, $recentTasksQuery);
    mysqli_stmt_bind_param($stmt, 's', $taskWriter);
    mysqli_stmt_execute($stmt);
    $recentResult = mysqli_stmt_get_result($stmt);

    $recentTasks = [];
    while ($row = mysqli_fetch_assoc($recentResult)) {
        $recentTasks[] = $row;
    }
    $writerAnalytics['recent_performance'] = $recentTasks;
}

// 3. Subject/Category analytics
$subjectAnalytics = [];
$subjectStatsQuery = "SELECT 
    COUNT(*) as total_tasks,
    AVG(DATEDIFF(COALESCE(completed_on, NOW()), create_date)) as avg_completion_time,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
    AVG(cpp) as avg_cpp,
    AVG(pages) as avg_pages
    FROM tbltasks 
    WHERE subject = ?";

$stmt = mysqli_prepare($con, $subjectStatsQuery);
mysqli_stmt_bind_param($stmt, 's', $taskSubject);
mysqli_stmt_execute($stmt);
$subjectResult = mysqli_stmt_get_result($stmt);
$subjectAnalytics = mysqli_fetch_assoc($subjectResult);

// 4. Financial analytics for this task
$financialAnalytics = [
    'task_value' => $taskPages * $taskCPP,
    'cpp_comparison' => 0,
    'value_comparison' => 0
];

// Compare with average CPP
$avgCppQuery = "SELECT AVG(cpp) as avg_cpp FROM tbltasks WHERE subject = ?";
$stmt = mysqli_prepare($con, $avgCppQuery);
mysqli_stmt_bind_param($stmt, 's', $taskSubject);
mysqli_stmt_execute($stmt);
$avgCppResult = mysqli_stmt_get_result($stmt);
$avgCppData = mysqli_fetch_assoc($avgCppResult);

if ($avgCppData['avg_cpp'] > 0 && $taskCPP > 0) {
    $financialAnalytics['cpp_comparison'] = round((($taskCPP / $avgCppData['avg_cpp']) - 1) * 100, 1);
} else {
    $financialAnalytics['cpp_comparison'] = 0;
}

// 5. File and activity analytics
$fileAnalytics = [];

// Count task files
$taskFilesCount = mysqli_num_rows(mysqli_query($con, "SELECT id FROM tbl_task_files WHERE task_id = '$taskId' AND file_type = 'task' AND is_deleted = 0"));
$submittedFilesCount = mysqli_num_rows(mysqli_query($con, "SELECT id FROM tbl_task_files WHERE task_id = '$taskId' AND file_type = 'submitted' AND is_deleted = 0"));

// Count comments
$commentsCount = mysqli_num_rows(mysqli_query($con, "SELECT id FROM tbl_task_comments WHERE task_id = '$taskId'"));
$adminCommentsCount = mysqli_num_rows(mysqli_query($con, "SELECT id FROM tbl_task_comments WHERE task_id = '$taskId' AND user_type = 'admin'"));
$writerCommentsCount = mysqli_num_rows(mysqli_query($con, "SELECT id FROM tbl_task_comments WHERE task_id = '$taskId' AND user_type = 'writer'"));

$fileAnalytics = [
    'task_files' => $taskFilesCount,
    'submitted_files' => $submittedFilesCount,
    'total_comments' => $commentsCount,
    'admin_comments' => $adminCommentsCount,
    'writer_comments' => $writerCommentsCount,
    'communication_ratio' => $commentsCount > 0 ? round(($adminCommentsCount / $commentsCount) * 100, 1) : 0
];

// 6. Task complexity score calculation
$complexityScore = 0;
$complexityFactors = [];

// Page count factor (normalized to 0-25 points)
$pageScore = $taskPages > 0 ? min(25, ($taskPages / 10) * 25) : 0;
$complexityScore += $pageScore;
$complexityFactors[] = "Pages: " . round($pageScore, 1) . "/25";

// File count factor (0-20 points)
$fileScore = min(20, $taskFilesCount * 5);
$complexityScore += $fileScore;
$complexityFactors[] = "Files: " . round($fileScore, 1) . "/20";

// Timeline factor (0-20 points)
$timelineScore = $totalTimeAllotted <= 1 ? 20 : ($totalTimeAllotted <= 3 ? 15 : ($totalTimeAllotted <= 7 ? 10 : 5));
$complexityScore += $timelineScore;
$complexityFactors[] = "Timeline: " . $timelineScore . "/20";

// Description length factor (0-15 points)
$descLength = strlen($taskDescription);
$descScore = $descLength > 0 ? min(15, ($descLength / 500) * 15) : 0;
$complexityScore += $descScore;
$complexityFactors[] = "Description: " . round($descScore, 1) . "/15";

// Subject complexity (0-20 points) - calculated dynamically
$subjectComplexityQuery = "SELECT 
    subject,
    AVG(DATEDIFF(COALESCE(completed_on, submitted_on, NOW()), create_date)) as avg_days,
    AVG(cpp) as avg_cpp,
    COUNT(*) as task_count,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_count
    FROM tbltasks 
    WHERE subject IS NOT NULL AND subject != ''
    GROUP BY subject";

$subjectComplexityResult = mysqli_query($con, $subjectComplexityQuery);
$subjectComplexityScores = [];

while ($row = mysqli_fetch_assoc($subjectComplexityResult)) {
    $avgDays = floatval($row['avg_days']);
    $avgCpp = floatval($row['avg_cpp']);
    $taskCount = intval($row['task_count']);
    $cancelledRate = $taskCount > 0 ? ($row['cancelled_count'] / $taskCount) : 0;

    // Calculate complexity score based on multiple factors
    $dayScore = min(8, $avgDays / 2); // Max 8 points for longer completion times
    $cppScore = min(6, $avgCpp / 200); // Max 6 points for higher cpp rates
    $cancelScore = $cancelledRate * 6; // Max 6 points for higher cancellation rates

    $totalScore = min(20, $dayScore + $cppScore + $cancelScore);
    $subjectComplexityScores[$row['subject']] = round($totalScore, 1);
}

// Get score for current task's subject, or calculate a default
if (isset($subjectComplexityScores[$taskSubject])) {
    $subjectScore = $subjectComplexityScores[$taskSubject];
} else {
    // For new/unknown subjects, assign a moderate score
    $subjectScore = 10;
}

$complexityScore += $subjectScore;
$complexityFactors[] = "Subject: " . $subjectScore . "/20";

// Normalize to 100
$complexityScore = min(100, $complexityScore);

// Determine complexity level
if ($complexityScore >= 80) {
    $complexityLevel = ['level' => 'Very High', 'class' => 'danger', 'icon' => 'fa-exclamation-triangle'];
} elseif ($complexityScore >= 60) {
    $complexityLevel = ['level' => 'High', 'class' => 'warning', 'icon' => 'fa-exclamation-circle'];
} elseif ($complexityScore >= 40) {
    $complexityLevel = ['level' => 'Medium', 'class' => 'info', 'icon' => 'fa-info-circle'];
} else {
    $complexityLevel = ['level' => 'Low', 'class' => 'success', 'icon' => 'fa-check-circle'];
}

// 7. Risk assessment
$riskFactors = [];
$riskScore = 0;

// Timeline risk
if ($isLate && in_array($taskStatus, ['In Progress', 'Unconfirmed'])) {
    $riskFactors[] = "Task is overdue";
    $riskScore += 30;
}

// Writer performance risk
if (isset($writerAnalytics['completion_rate']) && $writerAnalytics['completion_rate'] < 80) {
    $riskFactors[] = "Writer has low completion rate (" . $writerAnalytics['completion_rate'] . "%)";
    $riskScore += 20;
}

// Communication risk
if ($commentsCount == 0 && $taskStatus == 'In Progress') {
    $riskFactors[] = "No communication since task started";
    $riskScore += 15;
}

// Payment risk
if ($taskStatus == 'Completed' && $is_paid == 0) {
    $riskFactors[] = "Completed task not yet paid";
    $riskScore += 25;
}

// High value risk
if ($financialAnalytics['task_value'] > 5000) {
    $riskFactors[] = "High-value task requires attention";
    $riskScore += 10;
}

$riskLevel = $riskScore >= 50 ? 'High' : ($riskScore >= 25 ? 'Medium' : 'Low');
$riskClass = $riskScore >= 50 ? 'danger' : ($riskScore >= 25 ? 'warning' : 'success');

// 8. Recommendations generation
$recommendations = [];

if ($taskStatus == 'In Progress') {
    if ($isLate) {
        $recommendations[] = "Contact writer immediately about overdue task";
    } elseif ($timeRemaining <= 2) {
        $recommendations[] = "Follow up with writer - task due soon";
    }

    if ($commentsCount == 0) {
        $recommendations[] = "Initiate communication to check progress";
    }
}

if ($taskStatus == 'Submitted') {
    $recommendations[] = "Review submitted work and provide feedback promptly";
}

if ($taskStatus == 'Completed' && $is_paid == 0) {
    $recommendations[] = "Process payment for completed task";
}

if (isset($writerAnalytics['completion_rate']) && $writerAnalytics['completion_rate'] < 70) {
    $recommendations[] = "Monitor this writer's performance closely";
}

if ($complexityScore > 70 && $timeRemaining < 3) {
    $recommendations[] = "Consider extending deadline for complex task";
}
?>

    <title>iTasker | View Task #<?php  echo $taskId;?></title>
<?php include "navi.php";
include_once('task-share-helper.php');
if (isset($_GET['task_id'])) {
    $encodedId = $_GET['task_id'];
    $taskId = base64_decode($encodedId);
} else {
    $_SESSION['alert'] ='<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
                                        <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
                                        <p class="mb-0 flex-1">Invalid task ID!</p>
                                        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
}
?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">View <span class="text-info fw-medium">Task Details</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0">
                    <form class="$rowTask flex-lg-column flex-xxl-$rowTask gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
                        <div class="col-auto">
                        </div>
                        <div class="col-md-auto position-relative">
                            <h6 class="mb-1 badge rounded-pill badge-subtle-info"><?php echo date("jS F Y"); ?> | <span id="timeDisplay"></span></h6>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php
// Show duplicate task notice with link to original
if (isset($rowTask['is_duplicate']) && $rowTask['is_duplicate'] == 1 && isset($rowTask['original_task_id']) && !empty($rowTask['original_task_id'])) {
    $originalTaskId = $rowTask['original_task_id'];

    // Fetch original task topic
    $originalTaskQuery = "SELECT topic FROM tbltasks WHERE id = ?";
    $originalStmt = mysqli_prepare($con, $originalTaskQuery);
    mysqli_stmt_bind_param($originalStmt, 'i', $originalTaskId);
    mysqli_stmt_execute($originalStmt);
    $originalTaskResult = mysqli_stmt_get_result($originalStmt);

    if ($originalTaskData = mysqli_fetch_assoc($originalTaskResult)) {
        $encodedOriginalId = base64_encode($originalTaskId);
        $originalTaskTopic = htmlspecialchars($originalTaskData['topic']);
        ?>
        <div class="bg-info-subtle border-start border-info border-3 rounded-3 py-2 ps-3 pe-2 mb-3">
            <div class="d-flex align-items-center">
                <span class="fas fa-copy text-info me-2"></span>
                <div>
                    <strong class="text-info">Duplicate Task</strong>
                    <p class="mb-0 small">
                        This is a duplicate of task ID:
                        <a href="view-task?task_id=<?php echo $encodedOriginalId; ?>" class="fw-semibold text-decoration-none">
                            #<?php echo $originalTaskId; ?> - <?php echo $originalTaskTopic; ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    mysqli_stmt_close($originalStmt);
}
?>

    <!-- Display Bootstrap Alerts -->
<?php
if (isset($_GET['message'])) {
    // Sanitize the message to remove any HTML tags
    $message = htmlspecialchars($_GET['message'], ENT_QUOTES, 'UTF-8');
    echo "
                <div class='alert alert-success border-0 d-flex align-items-center' role='alert'>
                    <div class='bg-success me-3 icon-item'><span class='fas fa-check-circle text-white fs-6'></span></div>
                        <p class='mb-0 flex-1'>$message</p>
                    <button class='btn-close' type='button' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>
                <script>
            // Use JavaScript to hide the alert after 5 seconds
            setTimeout(function() {
                var alertElement = document.querySelector('.alert');
                if (alertElement) {
                    alertElement.classList.add('fade'); // Add Bootstrap's fade class
                    alertElement.addEventListener('transitionend', function() {
                        alertElement.remove();
                    });
                }
            }, 5000); // 5000 milliseconds = 5 seconds
        </script>
                ";
}
?>
<?php
if (isset($_SESSION['alert'])) {
    echo $_SESSION['alert'];
    unset($_SESSION['alert']); // Clear the alert message
}
?>

<?php if ($publish != 1): ?>
    <div class="alert alert-danger mt-2">
        <h5 class="mb-0 text-800">
            <?php echo $publishText;?>

        </h5>
    </div>
<?php endif; ?>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;"></div>
    <div class="card mb-3">
        <div class="card-body">
            <div class="row justify-content-between align-items-center">
                <div class="col">
                    <div class="d-flex">
                        <div class="calendar me-2">
                            <span class="calendar-month">
                                <?php // Get the current month and date
                                $currentMonth = date('M'); // Current month abbreviation (e.g., 'Jul')
                                $currentDay = date('d'); // Current day of the month (e.g., '19')
                                echo $currentMonth;?>
                            </span>
                            <span class="calendar-day"><?php echo $currentDay; ?> </span></div>
                        <div class="flex-1 fs-10">
                            <h5 class="mb-sm-0 text-primary fs-7">Task ID:
                                <span class="text-info fw-medium">#<?php  echo $taskId;?></span>
                            </h5>
                            <p class="mb-0">Posted on <span class="text-info ms-2"><?php  echo date("d M Y, g:i A", strtotime($taskCreatedOn));?></span></p>
                            <?php if ($rowTask['acknowledged'] == 0): ?>
                                <p class='mb-0'>Viewed on <span class='badge badge rounded-pill badge-subtle-secondary'> Not Viewed<span class='ms-1 fas fa-eye-slash' data-fa-transform='shrink-2'></span></span></p>
                            <?php elseif ($rowTask['acknowledged'] == 1): ?>
                                <p class='mb-0'>Viewed on <span class='text-info ms-2'><?php echo date('d M Y, g:i A', strtotime($rowTask['acknowledged_at'])); ?></span></p>
                            <?php endif; ?>
                            <div class="fs-9 mt-2 mb-2 text-primary"><strong class="me-2">Status: </strong><?php  echo $statusBadge;?>
                                <?php if ($taskStatus == 'Submitted' && !empty($submittedOn)): ?>
                                    <span class="fs-10 text-info ms-2"><?php echo date("d M Y, g:i A", strtotime($submittedOn)); ?></span>
                                <?php elseif ($taskStatus == 'Completed' && !empty($completedOn)): ?>
                                    <span class="fs-10 text-success ms-2"><?php echo date("d M Y, g:i A", strtotime($completedOn)); ?></span>
                                <?php endif; ?>
                                <?php if ($is_confirmed != 0): ?>
                                    <?php echo $confirmation;?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-auto mt-4 mt-md-0">
                    <a class="btn btn-sm btn-outline-primary me-2" type="button" href="edit-task?task_id=<?php  echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Task">
                        <i class="fas fa-edit" aria-hidden="true"></i>
                        <span class="ms-1 d-none d-sm-inline-block">Edit Task</span>
                    </a>
                    <a class="btn btn-outline-secondary btn-sm mx-2" type="button" href="#" title="Share task" data-bs-toggle="tooltip" data-bs-placement="top" onclick="copyTaskShareLink(<?php echo $taskId; ?>, '<?php echo htmlspecialchars($taskTopic); ?>')">
                        <i class="fas fa-share-alt" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline-block d-xl-none d-xxl-inline-block ms-1">Share Task</span>
                    </a>
                    <a class="btn btn-outline-info btn-sm mx-2" type="button" href="#" data-bs-toggle="modal" data-bs-target="#duplicateModal" data-task-id="<?php echo base64_encode($taskId); ?>" data-task-title="<?php echo htmlspecialchars($rowTask['topic'], ENT_QUOTES); ?>" data-bs-placement="top" title="Duplicate Task">
                        <i class="fas fa-copy" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline-block d-xl-none d-xxl-inline-block ms-1">Duplicate</span>
                    </a>
                    <a class="btn btn-outline-danger btn-sm mx-2" type="button" id="favorite-btn" onclick="toggleFavorite(<?php echo $taskId; ?>)">
                        <i id="favorite-icon" class="fas <?php $is_favorite = $rowTask['is_favorite']; echo ($is_favorite == 1) ? 'fa-heart' : 'fa-heart-broken'; ?>" aria-hidden="true"></i>
                        <span id="favorite-text" class="d-none d-sm-inline-block d-xl-none d-xxl-inline-block ms-1"><?php echo ($is_favorite == 1) ? 'Unfavorite' : 'Favorite'; ?></span>
                    </a>
                    <?php if ($taskStatus =='Submitted'): ?>
                        <a class="btn btn-outline-success btn-sm mx-2" type="button" id="complete-task-btn-<?php echo $taskId; ?>" data-bs-toggle="modal" data-bs-target="#completeTaskModal" title="Complete Task">
                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                            <span id="complete-task-text-<?php echo $taskId; ?>" class="d-none d-sm-inline-block d-xl-none d-xxl-inline-block ms-1">Complete</span>
                        </a>
                    <?php endif; ?>
                    <!-- Complete Task Confirmation Modal -->
                    <div class="modal fade" id="completeTaskModal" tabindex="-1" aria-labelledby="completeTaskModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-success-subtle text-white">
                                    <h5 class="modal-title" id="completeTaskModalLabel">
                                        <i class="fas fa-check-circle me-2"></i>Complete Task
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-1">Are you sure you want to mark this task as completed?</p>
                                    <p class="fw-bold text-primary mb-0">Task ID: #<?php echo $taskId; ?></p>
                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($taskTopic); ?></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-outline-success" id="confirmCompleteBtn" onclick="completeTask('<?php echo $encodedId; ?>', <?php echo $taskId; ?>)">
                                        <i class="fas fa-check me-1"></i>Yes, Complete Task
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Details Card -->
    <div class="card border overflow-hidden mb-3">
        <div class="card-body p-0">

            <!-- Header Section -->
            <div class="p-4 pb-0">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">

                    <!-- Left: Subject + Title + Due -->
                    <div class="flex-grow-1">
                        <p class="text-uppercase fw-semibold mb-1" style="font-size:11px; letter-spacing:.07em; color:var(--falcon-gray-600);">
                            <i class="fas fa-book me-1"></i><?php echo htmlspecialchars($taskSubject); ?>
                        </p>
                        <h2 class="fw-semibold mb-2 text-uppercase" style="font-size:20px; line-height:1.2;">
                            <?php echo htmlspecialchars($taskTopic); ?>
                        </h2>
                        <p class="mb-0" style="font-size:12px; color:var(--falcon-gray-600);">
                            <i class="fas fa-calendar me-1"></i> Due
                            <span class="text-info fw-semibold ms-1">
                            <?php echo date("d M Y, g:i A", strtotime($taskDueDate)); ?>
                        </span>
                        </p>
                    </div>

                    <!-- Right: Status + Cost -->
                    <div class="d-flex flex-column align-items-end gap-2">
                        <!-- Status badge -->
                        <?php
                        $due_date = new DateTime($rowTask['due_date']);
                        $currentDateTime = new DateTime();
                        $isLate = ($due_date < $currentDateTime);

                        if ($taskStatus == 'Completed') {
                            echo '<span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-1" style="font-size:11px;">Completed</span>';
                        } elseif ($taskStatus == 'Cancelled') {
                            echo '<span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-1" style="font-size:11px;">Cancelled</span>';
                        } elseif ($taskStatus == 'Submitted') {
                            echo '<span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-3 py-1" style="font-size:11px;">Submitted</span>';
                        } elseif ($rowTask['is_confirmed'] == 2) {
                            echo '<span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-1" style="font-size:11px;">Declined</span>';
                        } else {
                            if ($isLate) {
                                echo '<span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-1" style="font-size:11px;">Past Due</span>';
                            } else {
                                echo '<span id="time-remaining" class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle px-3 py-1" style="font-size:11px;"></span>';
                            }
                        }
                        ?>

                        <!-- Cost -->
                        <?php $totalCost = $taskPages * $taskCPP; ?>
                        <div class="text-end">
                            <p class="fw-semibold mb-0" style="font-size:20px;">
                                Ksh. <?php echo number_format($totalCost); ?>
                            </p>
                            <p class="mb-0" style="font-size:11px; color:var(--falcon-gray-600);">
                                Ksh. <?php echo htmlspecialchars($taskCPP); ?> per page
                            </p>
                        </div>

                        <!-- Paid status -->
                        <?php if ($taskStatus == 'Completed'): ?>
                            <?php if ($is_paid == 0): ?>
                                <button class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle px-3 py-1"
                                        style="font-size:11px; cursor:pointer;"
                                        onclick="markAsPaidConfirm('<?php echo $encodedId; ?>', <?php echo $taskId; ?>)">
                                    Unpaid
                                </button>
                            <?php else: ?>
                                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-1 d-flex align-items-center gap-1" style="font-size:11px;">
                                <i class="fas fa-check" style="font-size:9px;"></i>
                                Paid
                                <?php if ($is_paid == 1): ?>
                                    &middot; <?php echo date("d M Y", strtotime($rowTask['paid_on'])); ?>
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <hr class="my-3 opacity-10">

            <!-- Footer Chips -->
            <div class="px-4 pb-4 d-flex flex-wrap gap-2 align-items-center">

                <!-- Writer chip -->
                <?php if ($taskWriter):
                    $stmt = $con->prepare("SELECT id, last_seen, email FROM tblwriters WHERE username = ?");
                    $stmt->bind_param("s", $taskWriter);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $writerData = $result->fetch_assoc();

                    if ($writerData):
                        $writerId = $writerData['id'];
                        $lastSeen = $writerData['last_seen'];
                        $writerEmail = $writerData['email'];
                        include_once('writer-performance-functions.php');
                        $writerPerf = calculateWriterPerformance($con, $writerEmail);
                        $writerLevel = getWriterLevel($con, $writerPerf['completed_tasks']);
                        $encodedWriterId = base64_encode($writerId);

                        // Online status logic
                        $statusClass = 'text-secondary'; $statusText = 'Offline'; $isOnline = false;
                        if ($lastSeen) {
                            $lastSeenTime = new DateTime($lastSeen, new DateTimeZone('UTC'));
                            $lastSeenTime->setTimezone(new DateTimeZone('Africa/Nairobi'));
                            $currentTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
                            $timeDiff = $currentTime->diff($lastSeenTime);
                            $totalMinutes = ($timeDiff->days * 24 * 60) + ($timeDiff->h * 60) + $timeDiff->i;
                            if ($totalMinutes <= 1)       { $statusText = 'Online';                   $statusClass = 'text-success'; $isOnline = true; }
                            elseif ($totalMinutes <= 10)  { $statusText = 'Just now';                 $statusClass = 'text-success'; }
                            elseif ($totalMinutes <= 60)  { $statusText = $timeDiff->i . 'm ago';     $statusClass = 'text-info'; }
                            elseif ($timeDiff->days == 0) { $statusText = $timeDiff->h . 'h ago';     $statusClass = 'text-warning'; }
                            elseif ($timeDiff->days == 1) { $statusText = 'Yesterday';                $statusClass = 'text-secondary'; }
                            elseif ($timeDiff->days <= 7) { $statusText = $timeDiff->days . 'd ago';  $statusClass = 'text-secondary'; }
                            else { $statusText = floor($timeDiff->days / 7) . 'w ago';                $statusClass = 'text-secondary'; }
                        }
                        $initials = strtoupper(substr($taskWriter, 0, 2));
                        ?>
                        <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-pill border"
                             style="font-size:12px; background:var(--falcon-gray-100);">
                            <!-- Initials avatar -->
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-semibold"
                                 style="width:22px;height:22px;font-size:10px;background:rgba(var(--falcon-primary-rgb),.15);color:var(--falcon-primary);">
                                <?php echo $initials; ?>
                            </div>
                            <a href="writer.php?writerID=<?php echo $encodedWriterId; ?>"
                               class="fw-semibold text-decoration-none text-body"
                               data-bs-toggle="tooltip" title="View <?php echo htmlspecialchars($taskWriter); ?>'s profile">
                                <?php echo htmlspecialchars($taskWriter); ?>
                            </a>
                            <!-- Level badge -->
                            <span class="badge rounded-pill px-2 py-1" style="font-size:10px;background-color:<?php echo $writerLevel['icon_color']; ?>22;color:<?php echo $writerLevel['icon_color']; ?>;border:1px solid <?php echo $writerLevel['icon_color']; ?>44;">
                            <?php echo $writerLevel['level_name']; ?>
                        </span>
                            <!-- Online dot -->
                            <span class="d-flex align-items-center gap-1 <?php echo $statusClass; ?>" style="font-size:11px;">
                            <i class="<?php echo $isOnline ? 'fas' : 'far'; ?> fa-circle" style="font-size:7px;"></i>
                            <?php echo $statusText; ?>
                        </span>
                        </div>
                        <?php $stmt->close();
                    endif;
                endif; ?>

                <!-- Edit writer (Draft only) -->
                <?php if ($taskStatus == 'Draft'): ?>
                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3"
                            data-bs-toggle="modal" data-bs-target="#editWriterModal">
                        <i class="fas fa-user-edit me-1" style="font-size:11px;"></i>Edit Writer
                    </button>
                <?php endif; ?>

                <!-- Account chip -->
                <div class="d-flex align-items-center gap-1 px-3 py-2 rounded-pill border"
                     style="font-size:12px; background:var(--falcon-gray-100);">
                    <i class="fas fa-user" style="font-size:11px; color:var(--falcon-gray-600);"></i>
                    <span><?php echo htmlspecialchars($taskAccount); ?></span>
                </div>

                <!-- Pages chip -->
                <div class="d-flex align-items-center gap-1 px-3 py-2 rounded-pill border"
                     style="font-size:12px; background:var(--falcon-gray-100);">
                    <i class="fas fa-file" style="font-size:11px; color:var(--falcon-gray-600);"></i>
                    <span><?php echo htmlspecialchars($taskPages); ?> pages</span>
                </div>

            </div>
        </div>
    </div>

<?php
// Fetch all verified writers for the Edit Writer modal
$verifiedWritersResult = mysqli_query($con, "SELECT id, username, email FROM tblwriters WHERE is_verified = 1 ORDER BY username ASC");
$verifiedWriters = [];
while ($vw = mysqli_fetch_assoc($verifiedWritersResult)) {
    $verifiedWriters[] = $vw;
}
?>
    <!-- Edit Writer Modal -->
    <div class="modal fade" id="editWriterModal" tabindex="-1" aria-labelledby="editWriterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-warning-subtle">
                    <h5 class="modal-title" id="editWriterModalLabel">
                        <i class="fas fa-user-edit me-2 text-warning"></i>Change Task Writer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Current writer: <strong class="text-primary"><?php echo $taskWriter ? htmlspecialchars($taskWriter) : '<em>None assigned</em>'; ?></strong>
                    </p>
                    <p class="text-muted small mb-3">Select a verified writer from the list below:</p>

                    <?php if (empty($verifiedWriters)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>No verified writers found.
                        </div>
                    <?php else: ?>
                        <div class="list-group" id="writerSelectionList">
                            <?php foreach ($verifiedWriters as $vw): ?>
                                <button type="button"
                                        class="list-group-item list-group-item-action d-flex align-items-center gap-2 writer-select-btn <?php echo ($taskWriter === $vw['username']) ? 'active' : ''; ?>"
                                        data-writer-username="<?php echo htmlspecialchars($vw['username']); ?>"
                                        data-writer-email="<?php echo htmlspecialchars($vw['email']); ?>">
                                    <span class="fas fa-user-circle fs-5 text-info"></span>
                                    <div class="flex-grow-1 text-start">
                                        <div class="fw-semibold"><?php echo htmlspecialchars($vw['username']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($vw['email']); ?></div>
                                    </div>
                                    <?php if ($taskWriter === $vw['username']): ?>
                                        <span class="badge badge-subtle-success rounded-pill"><i class="fas fa-check me-1"></i>Current</span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirmEditWriterBtn" disabled>
                        <i class="fas fa-save me-1"></i>Save Writer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 order-1 order-lg-0">
            <div class="card mb-3">
                <div class="card-header d-flex bg-body-tertiary align-items-center gap-2">
                    <i class="fas fa-align-left text-primary" style="font-size:13px;"></i>
                    <h6 class="mb-0">Description</h6>
                </div>
                <div class="card-body">
                    <div class="task-description-content">
                        <?php
                        $cleanText = stripslashes($taskDescription);

                        if (strip_tags($cleanText) !== $cleanText) {
                            // --- HTML content from Quill editor ---
                            libxml_use_internal_errors(true);
                            $dom = new DOMDocument();
                            $dom->loadHTML('<?xml encoding="UTF-8">' . $cleanText, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                            libxml_clear_errors();
                            $xpath = new DOMXPath($dom);

                            // Nest indented Quill list items properly
                            foreach ($xpath->query('//ol') as $ol) {
                                $items = [];
                                foreach ($ol->getElementsByTagName('li') as $li) {
                                    $indent = 0;
                                    if (preg_match('/ql-indent-(\d+)/', $li->getAttribute('class'), $m)) {
                                        $indent = (int)$m[1];
                                    }
                                    $items[] = ['element' => $li, 'indent' => $indent];
                                }
                                if (!$items) continue;

                                while ($ol->firstChild) $ol->removeChild($ol->firstChild);

                                $currentLevel = 0;
                                $parentStack  = [$ol];

                                foreach ($items as $item) {
                                    $li     = $item['element'];
                                    $indent = $item['indent'];

                                    $cls = preg_replace('/\s*ql-indent-\d+\s*/', ' ', $li->getAttribute('class'));
                                    $cls = trim($cls);
                                    $cls ? $li->setAttribute('class', $cls) : $li->removeAttribute('class');

                                    if ($indent === 0) {
                                        $parentStack[0]->appendChild($li);
                                        $currentLevel = 0;
                                        $parentStack  = [$parentStack[0]];
                                    } else {
                                        while ($currentLevel >= $indent && count($parentStack) > 1) {
                                            array_pop($parentStack);
                                            $currentLevel--;
                                        }
                                        if ($currentLevel < $indent) {
                                            $lastLi = $xpath->query('.//li[last()]', end($parentStack));
                                            if ($lastLi->length > 0) {
                                                $target   = $lastLi->item(0);
                                                $existing = $xpath->query('./ul', $target);
                                                $nested   = $existing->length > 0
                                                    ? $existing->item(0)
                                                    : $target->appendChild($dom->createElement('ul'));
                                                $nested->appendChild($li);
                                                $parentStack[] = $nested;
                                                $currentLevel  = $indent;
                                            }
                                        }
                                    }
                                }
                            }

                            $html = $dom->saveHTML();
                            $html = preg_replace('/<\?xml[^>]*>/', '', $html);
                            $html = preg_replace('/<\!DOCTYPE[^>]*>/', '', $html);
                            $html = str_replace(['<html>', '</html>', '<body>', '</body>'], '', $html);

                            // Highlight all external links
                            $html = preg_replace_callback(
                                '/<a\s([^>]*)>/i',
                                function ($matches) {
                                    $attrs = $matches[1];
                                    if (!str_contains($attrs, 'class=')) $attrs .= ' class="highlighted-link"';
                                    if (!str_contains($attrs, 'target=')) $attrs .= ' target="_blank"';
                                    if (!str_contains($attrs, 'rel='))    $attrs .= ' rel="noopener noreferrer"';
                                    return '<a ' . trim($attrs) . '>';
                                },
                                $html
                            );

                            echo $html;

                        } else {
                            // --- Plain text ---
                            $escaped = htmlspecialchars($cleanText, ENT_QUOTES, 'UTF-8');
                            $linked  = preg_replace(
                                '/(https?:\/\/[^\s]+)/',
                                '<a href="$1" class="highlighted-link" target="_blank" rel="noopener noreferrer">$1</a>',
                                $escaped
                            );
                            echo nl2br($linked);
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Task Files card section -->
    <div class='col mb-3'>
        <div class='row g-3'>
            <div class='col-xxl-12'>
                <div class='card h-100 h-xxl-auto mt-xxl-3'>
                    <div class='card-header d-flex bg-body-tertiary align-items-center'>
                        <i class='far fa-folder-open me-2 text-primary'></i>
                        <h6 class='mb-0'>Task Files</h6>
                    </div>
                    <div class='card-body position-relative'>
                        <div class='bg-holder bg-card d-none d-md-block'
                             style='background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);'></div>
                        <?php
                        // Get task files from new table
                        $taskFilesQuery = "SELECT * FROM tbl_task_files WHERE task_id = ? AND file_type = 'task' AND is_deleted = 0 ORDER BY upload_time ASC";
                        $stmt = mysqli_prepare($con, $taskFilesQuery);
                        mysqli_stmt_bind_param($stmt, 'i', $taskId);
                        mysqli_stmt_execute($stmt);
                        $taskFilesResult = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($taskFilesResult) > 0) {
                            $fileIndex = 0;
                            while ($fileRow = mysqli_fetch_assoc($taskFilesResult)) {
                                $fileName = $fileRow['original_file_name'];
                                $fileUrl = $fileRow['file_url'];
                                $fileSize = $fileRow['file_size'];
                                $uploadTime = $fileRow['upload_time'];
                                $formattedSize = formatFileSize($fileSize);
                                $formattedDate = date('d M Y, g:i A', strtotime($uploadTime));

                                // Determine thumbnail based on file extension
                                $thumbnailPath = '../assets/img/icons/docs.png';
                                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                                switch (strtolower($fileExtension)) {
                                    case 'pdf':
                                        $thumbnailPath = '../assets/img/icons/pdf.png';
                                        break;
                                    case 'doc':
                                    case 'docx':
                                    case 'rtf':
                                        $thumbnailPath = '../assets/img/icons/word.png';
                                        break;
                                    case 'xls':
                                    case 'xlsx':
                                    case 'csv':
                                        $thumbnailPath = '../assets/img/icons/excel.png';
                                        break;
                                    case 'ppt':
                                    case 'pptx':
                                        $thumbnailPath = '../assets/img/icons/powerpoint.png';
                                        break;
                                    case 'mp4':
                                    case 'avi':
                                    case 'mov':
                                    case 'mkv':
                                    case 'wmv':
                                    case 'flv':
                                    case 'mpeg':
                                    case 'mpg':
                                    case '3gp':
                                    case 'webm':
                                    case 'm4v':
                                        $thumbnailPath = '../assets/img/icons/mp4.png';
                                        break;
                                    case 'jpg':
                                    case 'jpeg':
                                    case 'png':
                                    case 'gif':
                                        $thumbnailPath = '../assets/img/icons/image.png';
                                        break;
                                    case 'zip':
                                    case 'rar':
                                        $thumbnailPath = '../assets/img/icons/zip.png';
                                        break;
                                    default:
                                        $thumbnailPath = '../assets/img/icons/docs.png';
                                        break;
                                }
                                ?>
                                <div class="d-flex mb-3 hover-actions-trigger align-items-center">
                                    <div class="file-thumbnail">
                                        <img class="border h-100 w-100 object-fit-cover rounded-2"
                                             src="<?php echo $thumbnailPath; ?>" alt=""/>
                                    </div>
                                    <div class="ms-3 flex-shrink-1 flex-grow-1">
                                        <h6 class="mb-1">
                                            <a class="stretched-link text-900 fw-semi-bold"
                                               href="<?php echo $fileUrl; ?>"><?php echo htmlspecialchars($fileName); ?></a>
                                        </h6>
                                        <div class="fs-10">
                                            <span class="fw-medium text-600 file-size"><?php echo $formattedSize; ?></span>
                                            <span class='fw-medium text-600 mx-1'>•</span>
                                            <span class="fw-medium text-600"><?php echo $formattedDate; ?></span>
                                        </div>
                                        <div class="hover-actions end-0 top-50 translate-middle-y">
                                            <button class="btn btn-tertiary border-300 btn-sm me-1 text-600"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="View File"
                                                    onclick="previewFile('<?php echo $encodedId; ?>', <?php echo $fileIndex; ?>, 'task')">
                                                <img src="../assets/img/icons/eye.svg" alt="" width="15"/>
                                            </button>
                                            <a class="btn btn-tertiary border-300 btn-sm me-1 text-600"
                                               data-bs-toggle="tooltip" data-bs-placement="top" title="Download"
                                               href="<?php echo $fileUrl; ?>"
                                               download="<?php echo htmlspecialchars($fileName); ?>">
                                                <img src="../assets/img/icons/cloud-download.svg" alt="" width="15"/>
                                            </a>
                                            <button class="btn btn-tertiary border-300 btn-sm me-1 text-600"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Delete File"
                                                    onclick="confirmDeleteFile(<?php echo $fileRow['id']; ?>, '<?php echo htmlspecialchars($fileName, ENT_QUOTES); ?>', '<?php echo $formattedDate; ?>', 'task')">
                                                <img src="../assets/img/icons/delete.svg" alt="" width="15"/>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <hr class="text-200"/>
                                <?php
                                $fileIndex++;
                            }
                            mysqli_stmt_close($stmt);
                        } else {
                            echo '<div>No task files attached.</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submitted Files card section -->
    <div class='col mb-3'>
        <div class='row g-3'>
            <div class='col-xxl-12'>
                <div class='card h-100 h-xxl-auto mt-xxl-3'>
                    <div class='card-header d-flex bg-body-tertiary align-items-center'>
                        <i class='far fa-folder me-2 text-primary'></i>
                        <h6 class='mb-0'>Submitted Files</h6>
                    </div>
                    <div class='card-body position-relative'>
                        <div class='bg-holder bg-card d-none d-md-block'
                             style='background-image:url(../assets/img/icons/spot-illustrations/corner-7.png);'></div>
                        <?php
                        // Get submitted files from new table
                        $submittedFilesQuery = "SELECT * FROM tbl_task_files WHERE task_id = ? AND file_type = 'submitted' AND is_deleted = 0 ORDER BY upload_time ASC";
                        $stmt = mysqli_prepare($con, $submittedFilesQuery);
                        mysqli_stmt_bind_param($stmt, 'i', $taskId);
                        mysqli_stmt_execute($stmt);
                        $submittedFilesResult = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($submittedFilesResult) > 0) {
                            $fileIndex = 0;
                            while ($fileRow = mysqli_fetch_assoc($submittedFilesResult)) {
                                $fileName = $fileRow['original_file_name'];
                                $fileUrl = $fileRow['file_url'];
                                $fileSize = $fileRow['file_size'];
                                $uploadTime = $fileRow['upload_time'];
                                $formattedSize = formatFileSize($fileSize);
                                $formattedDate = date('d M Y, g:i A', strtotime($uploadTime));

                                // Determine thumbnail based on file extension
                                $thumbnailPath = '../assets/img/icons/docs.png';
                                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                                switch (strtolower($fileExtension)) {
                                    case 'pdf':
                                        $thumbnailPath = '../assets/img/icons/pdf.png';
                                        break;
                                    case 'doc':
                                    case 'docx':
                                    case 'rtf':
                                        $thumbnailPath = '../assets/img/icons/word.png';
                                        break;
                                    case 'xls':
                                    case 'xlsx':
                                    case 'csv':
                                        $thumbnailPath = '../assets/img/icons/excel.png';
                                        break;
                                    case 'ppt':
                                    case 'pptx':
                                        $thumbnailPath = '../assets/img/icons/powerpoint.png';
                                        break;
                                    case 'mp4':
                                    case 'avi':
                                    case 'mov':
                                    case 'mkv':
                                    case 'wmv':
                                    case 'flv':
                                    case 'mpeg':
                                    case 'mpg':
                                    case '3gp':
                                    case 'webm':
                                    case 'm4v':
                                        $thumbnailPath = '../assets/img/icons/mp4.png';
                                        break;
                                    case 'jpg':
                                    case 'jpeg':
                                    case 'png':
                                    case 'gif':
                                        $thumbnailPath = '../assets/img/icons/image.png';
                                        break;
                                    case 'zip':
                                    case 'rar':
                                        $thumbnailPath = '../assets/img/icons/zip.png';
                                        break;
                                    default:
                                        $thumbnailPath = '../assets/img/icons/docs.png';
                                        break;
                                }
                                ?>
                                <div class="d-flex mb-3 hover-actions-trigger align-items-center"
                                     data-file-url="<?php echo $fileUrl; ?>">
                                    <div class="file-thumbnail">
                                        <img class="border h-100 w-100 object-fit-cover rounded-2"
                                             src="<?php echo $thumbnailPath; ?>" alt=""/>
                                    </div>
                                    <div class="ms-3 flex-shrink-1 flex-grow-1">
                                        <h6 class="mb-1">
                                            <a class="stretched-link text-900 fw-semi-bold"
                                               href="<?php echo $fileUrl; ?>"><?php echo htmlspecialchars($fileName); ?></a>
                                        </h6>
                                        <div class="fs-10">
                                            <span class="fw-medium text-600"><?php echo $formattedSize; ?></span>
                                            <span class='fw-medium text-600 mx-1'>•</span>
                                            <span class="fw-medium text-600"><?php echo $formattedDate; ?></span>
                                        </div>
                                        <div class="hover-actions end-0 top-50 translate-middle-y">
                                            <button class="btn btn-tertiary border-300 btn-sm me-1 text-600"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="View File"
                                                    onclick="previewFile('<?php echo $encodedId; ?>', <?php echo $fileIndex; ?>, 'submitted')">
                                                <img src="../assets/img/icons/eye.svg" alt="" width="15"/>
                                            </button>
                                            <a class="btn btn-tertiary border-300 btn-sm me-1 text-600"
                                               data-bs-toggle="tooltip" data-bs-placement="top" title="Download"
                                               href="<?php echo $fileUrl; ?>"
                                               download="<?php echo htmlspecialchars($fileName); ?>">
                                                <img src="../assets/img/icons/cloud-download.svg" alt="" width="15"/>
                                            </a>
                                            <button class="btn btn-tertiary border-300 btn-sm me-1 text-600"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Delete File"
                                                    onclick="confirmDeleteFile(<?php echo $fileRow['id']; ?>, '<?php echo htmlspecialchars($fileName, ENT_QUOTES); ?>', '<?php echo $formattedDate; ?>', 'submitted')">
                                                <img src="../assets/img/icons/delete.svg" alt="" width="15"/>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <hr class="text-200"/>
                                <?php
                                $fileIndex++;
                            }
                            mysqli_stmt_close($stmt);
                        } else {
                            echo '<div>No submitted files.</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
// Fetch all comments for this task
$commentsQuery = 'SELECT * FROM tbl_task_comments WHERE task_id = ? ORDER BY created_at ASC';
$stmt = $con->prepare($commentsQuery);
$stmt->bind_param('i', $taskId);
$stmt->execute();
$commentsResult = $stmt->get_result();
$comments = $commentsResult->fetch_all(MYSQLI_ASSOC);

// Count unread messages (assuming admin is viewing - count unread writer messages)
$unreadQuery = 'SELECT COUNT(*) as unread_count FROM tbl_task_comments WHERE task_id = ? AND user_type = ? AND is_read = 0';
$unreadStmt = $con->prepare($unreadQuery);

// Determine which messages to count as unread based on current user type
if (isset($_SESSION['odmsaid'])) {
    // Admin viewing - count unread writer messages
    $countUserType = 'writer';
} else {
    // Writer viewing - count unread admin messages
    $countUserType = 'admin';
}

$unreadStmt->bind_param('is', $taskId, $countUserType);
$unreadStmt->execute();
$unreadResult = $unreadStmt->get_result();
$unreadData = $unreadResult->fetch_assoc();
$unreadCount = $unreadData['unread_count'];

// Determine conversation status based on task status
$conversationStatus = 'Active conversation';
$statusIcon = 'fa-comment-dots';
$statusClass = 'text-success';

if (in_array($taskStatus, ['Completed', 'Cancelled'])) {
    $conversationStatus = 'Closed conversation';
    $statusIcon = 'fa-comment-slash';
    $statusClass = 'text-muted';
} elseif ($taskStatus === 'Draft') {
    $conversationStatus = 'Draft conversation';
    $statusIcon = 'fa-comment-alt';
    $statusClass = 'text-warning';
} elseif ($taskStatus === 'In Revision') {
    $conversationStatus = 'Under review';
    $statusIcon = 'fa-comment-medical';
    $statusClass = 'text-info';
}
?>
<?php
// Function to get user online status and last seen
function getUserOnlineStatus($con, $userType, $username, $userEmail = null) {
    $onlineData = [
        'is_online' => 0,
        'last_seen' => null,
        'status_text' => 'Offline',
        'status_class' => 'bg-secondary'
    ];

    if ($userType === 'admin') {
        // Check admin online status
        $adminQuery = "SELECT is_online, last_seen FROM tbladmin WHERE email = ? OR username = ? LIMIT 1";
        if ($stmt = mysqli_prepare($con, $adminQuery)) {
            $emailToCheck = $userEmail ?: $username;
            mysqli_stmt_bind_param($stmt, 'ss', $emailToCheck, $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                $onlineData['is_online'] = $row['is_online'];
                $onlineData['last_seen'] = $row['last_seen'];
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // Check writer online status
        $writerQuery = "SELECT is_online, last_seen FROM tblwriters WHERE username = ? OR email = ? LIMIT 1";
        if ($stmt = mysqli_prepare($con, $writerQuery)) {
            $emailToCheck = $userEmail ?: $username;
            mysqli_stmt_bind_param($stmt, 'ss', $username, $emailToCheck);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                $onlineData['is_online'] = $row['is_online'];
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
        $lastSeenTime = new DateTime($onlineData['last_seen'], new DateTimeZone('UTC'));
        $lastSeenTime->setTimezone(new DateTimeZone('Africa/Nairobi'));
        $currentTime = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
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

    return $onlineData;
}

// Cache online status for all users in the conversation to avoid repeated queries
$userOnlineStatuses = [];
$userEmails = []; // Cache emails for profile image lookup

foreach ($comments as $comment) {
    $userKey = $comment['user_type'] . '_' . $comment['username'];

    if (!isset($userOnlineStatuses[$userKey])) {
        // Get actual user email and data based on user type
        $userEmailForStatus = null;

        if ($comment['user_type'] === 'writer') {
            // Get writer email from writers table first, then fallback to tasks
            $writerEmailQuery = "SELECT email FROM tblwriters WHERE username = ? LIMIT 1";
            if ($writerEmailStmt = mysqli_prepare($con, $writerEmailQuery)) {
                mysqli_stmt_bind_param($writerEmailStmt, 's', $comment['username']);
                mysqli_stmt_execute($writerEmailStmt);
                mysqli_stmt_bind_result($writerEmailStmt, $userEmailForStatus);
                mysqli_stmt_fetch($writerEmailStmt);
                mysqli_stmt_close($writerEmailStmt);
            }

            // Fallback: try to get from tasks table if not found in writers
            if (!$userEmailForStatus) {
                $taskEmailQuery = "SELECT email FROM tbltasks WHERE writer = ? LIMIT 1";
                if ($taskEmailStmt = mysqli_prepare($con, $taskEmailQuery)) {
                    mysqli_stmt_bind_param($taskEmailStmt, 's', $comment['username']);
                    mysqli_stmt_execute($taskEmailStmt);
                    mysqli_stmt_bind_result($taskEmailStmt, $userEmailForStatus);
                    mysqli_stmt_fetch($taskEmailStmt);
                    mysqli_stmt_close($taskEmailStmt);
                }
            }
        } else {
            // For admin, get email from admin table using the username from comments
            $adminEmailQuery = "SELECT email FROM tbladmin WHERE username = ? OR AdminName = ? OR CONCAT(FirstName, ' ', LastName) = ? LIMIT 1";
            if ($adminEmailStmt = mysqli_prepare($con, $adminEmailQuery)) {
                $fullName = $comment['username']; // In case the username is actually the full name
                mysqli_stmt_bind_param($adminEmailStmt, 'sss', $comment['username'], $comment['username'], $fullName);
                mysqli_stmt_execute($adminEmailStmt);
                mysqli_stmt_bind_result($adminEmailStmt, $userEmailForStatus);
                mysqli_stmt_fetch($adminEmailStmt);
                mysqli_stmt_close($adminEmailStmt);
            }
        }

        // Cache the email for profile image lookup
        $userEmails[$userKey] = $userEmailForStatus;

        // Get online status
        $userOnlineStatuses[$userKey] = getUserOnlineStatus($con, $comment['user_type'], $comment['username'], $userEmailForStatus);
    }
}
?>
    <!-- Task Discussion Card -->
    <div class='row'>
        <div class='col-md-12 col-xxl-12 mb-3'>
            <div class='card shadow-sm border-0 overflow-hidden h-100' style='border-radius: 15px;'>
                <div class='card-header text-white position-relative overflow-hidden bg-body-tertiary'
                    <?php if (empty($comments)): ?>
                        data-bs-toggle="collapse"
                        data-bs-target="#discussionBody"
                        aria-expanded="false"
                        aria-controls="discussionBody"
                        style="cursor: pointer;"
                    <?php endif; ?>>
                    <div class='d-flex justify-content-between align-items-center position-relative' style='z-index: 2;'>
                        <div class='d-flex align-items-center'>
                            <div class="me-2 text-primary">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div>
                                <h6 class='mb-0'>Task Discussion</h6>
                                <small class="text-1000">
                                    <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                    <?php if ($unreadCount > 0): ?>
                                        <span class="badge bg-danger me-2 pulse-animation" style="font-size: 10px;">
                                        <?php echo $unreadCount; ?> unread
                                    </span>
                                    <?php else: ?>
                                        <?php echo count($comments); ?> messages •
                                    <?php endif; ?>
                                    <span class="<?php echo $statusClass; ?>"><?php echo $conversationStatus; ?></span>
                                </small>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <?php if (in_array($taskStatus, ['Completed', 'Cancelled'])): ?>
                                <span class="badge bg-secondary bg-opacity-75 text-white px-3 py-2">
                                <i class="fas fa-lock me-1"></i>Conversation Closed
                            </span>
                            <?php endif; ?>

                            <!-- Collapse chevron — only shown when empty -->
                            <?php if (empty($comments)): ?>
                                <span class="text-muted" id="discussionChevron">
                                <i class="fas fa-chevron-down" style="font-size: 12px; transition: transform .2s;"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Card Body with Comments -->
                <div id="discussionBody" class="<?php echo empty($comments) ? 'collapse' : ''; ?>">
                    <div class='card-body p-0 d-flex flex-column' style='height: 650px;'>
                        <!-- Comments Container (Scrollable) -->
                        <div id="commentsContainer" class="flex-grow-1 px-3 py-3" style='overflow-y: auto; overflow-x: hidden;'>
                            <?php if (empty($comments)): ?>
                                <!-- Empty State -->
                                <div class="text-center py-5">
                                    <div class="mb-4">
                                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 80px; height: 80px;">
                                            <i class="fas fa-comment-alt text-muted fa-2x"></i>
                                        </div>
                                    </div>
                                    <h6 class="text-muted mb-2">No messages yet</h6>
                                    <?php if (!in_array($taskStatus, ['Completed', 'Cancelled'])): ?>
                                        <p class="text-muted small mb-3">Start the conversation by sharing your thoughts or asking questions.</p>
                                    <?php else: ?>
                                        <p class="text-muted small">This conversation is closed as the task has been <?php echo strtolower($taskStatus); ?>.</p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- Unread Messages Summary -->
                                <?php if ($unreadCount > 0): ?>
                                    <div class="alert alert-warning border-0 d-flex align-items-center mb-3" role="alert" style="background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);">
                                        <div class="me-3">
                                            <i class="fas fa-bell fa-lg"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <strong>You have <?php echo $unreadCount; ?> unread message<?php echo $unreadCount > 1 ? 's' : ''; ?></strong>
                                        </div>
                                        <button class="btn btn-sm btn-outline-dark" onclick="scrollToFirstUnread()">
                                            <i class="fas fa-arrow-down me-1"></i>Jump to first
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Messages Loop -->
                                <div class="messages-list">
                                    <?php
                                    $lastDate = null;
                                    foreach ($comments as $index => $comment):
                                        $isAdmin = ($comment['user_type'] === 'admin');
                                        $messageDate = date('Y-m-d', strtotime($comment['created_at'] . ' UTC'));

                                        // Show date separator if date changed
                                        if ($messageDate !== $lastDate):
                                            $lastDate = $messageDate;
                                            $displayDate = '';
                                            $today = date('Y-m-d');
                                            $yesterday = date('Y-m-d', strtotime('-1 day' . ' UTC'));

                                            if ($messageDate === $today) {
                                                $displayDate = 'Today';
                                            } elseif ($messageDate === $yesterday) {
                                                $displayDate = 'Yesterday';
                                            } else {
                                                $displayDate = date('F j, Y', strtotime($messageDate . ' UTC'));
                                            }
                                            ?>
                                            <div class="text-center my-3">
                                <span class="badge bg-info-subtle text-muted px-3 py-2">
                                    <i class="far fa-calendar me-1"></i><?php echo $displayDate; ?>
                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Message Item -->
                                        <div class="comment-item mb-3 <?php echo $firstUnreadIndex === $index ? 'first-unread-message' : ''; ?>"
                                             id="<?php echo $firstUnreadIndex === $index ? 'first-unread-message' : 'comment-' . $comment['id']; ?>"
                                             data-comment-id="<?php echo $comment['id']; ?>"
                                             data-timestamp="<?php echo $comment['created_at']; ?>"
                                            <?php
                                            // Add unread indicator
                                            $isUnread = false;
                                            if ($currentUserType === 'admin' && $comment['user_type'] === 'writer' && $comment['is_read'] == 0) {
                                                $isUnread = true;
                                            } elseif ($currentUserType === 'writer' && $comment['user_type'] === 'admin' && $comment['is_read'] == 0) {
                                                $isUnread = true;
                                            }
                                            if ($isUnread): ?>
                                                data-unread="true"
                                            <?php endif; ?>
                                             style="animation: <?php echo $isUnread ? 'fadeInUp 0.5s ease' : 'none'; ?>;">

                                            <div class="d-flex <?php echo ($comment['user_type'] === $currentUserType) ? 'flex-row-reverse' : 'flex-row'; ?> align-items-start gap-2">

                                                <!-- User Avatar -->
                                                <div class="flex-shrink-0">
                                                    <div class="position-relative">
                                                        <?php
                                                        // Get online status for this user
                                                        $userKey = $comment['user_type'] . '_' . $comment['username'];
                                                        $userStatus = $userOnlineStatuses[$userKey] ?? getUserOnlineStatus($con, $comment['user_type'], $comment['username'], '');

                                                        // Determine status color and text based on last_seen
                                                        $statusColor = 'secondary';
                                                        $statusText = 'Offline';
                                                        $timeDiff = null;
                                                        $onlineThresholdMinutes = 1; // Consider online if active within last 5 minutes

                                                        if ($userStatus['last_seen']) {
                                                            $lastSeenTime = new DateTime($userStatus['last_seen'], new DateTimeZone('UTC'));
                                                            $lastSeenTime->setTimezone(new DateTimeZone('Africa/Nairobi'));
                                                            $now = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
                                                            $timeDiff = $now->diff($lastSeenTime);

                                                            // Calculate total minutes since last seen
                                                            $totalMinutes = ($timeDiff->days * 24 * 60) + ($timeDiff->h * 60) + $timeDiff->i;

                                                            if ($totalMinutes <= $onlineThresholdMinutes) {
                                                                // Active within threshold - consider online
                                                                $statusColor = 'success';
                                                                $statusText = 'Online';
                                                            } elseif ($timeDiff->days > 0) {
                                                                $statusColor = 'secondary';
                                                                $statusText = 'Last seen ' . ($timeDiff->days == 1 ? '1 day ago' : $timeDiff->days . ' days ago');
                                                            } elseif ($timeDiff->h > 0) {
                                                                $statusColor = 'warning';
                                                                $statusText = 'Last seen ' . ($timeDiff->h == 1 ? '1 hour ago' : $timeDiff->h . ' hours ago');
                                                            } else {
                                                                $statusColor = 'info';
                                                                $statusText = 'Last seen ' . ($timeDiff->i == 1 ? '1 minute ago' : $timeDiff->i . ' minutes ago');
                                                            }
                                                        } else {
                                                            // No last_seen recorded
                                                            $statusColor = 'secondary';
                                                            $statusText = 'Offline';
                                                        }

                                                        // Get profile image
                                                        $profileImage = null;
                                                        if ($comment['user_type'] === 'admin') {
                                                            $imgQuery = 'SELECT Photo FROM tbladmin WHERE email = ? OR username = ? LIMIT 1';
                                                            if ($imgStmt = mysqli_prepare($con, $imgQuery)) {
                                                                $userEmailForStatus = isset($_SESSION['odmsaid']) ? $_SESSION['odmsaid'] : '';
                                                                $emailToCheck = $userEmailForStatus ? $userEmailForStatus : $comment['username'];
                                                                mysqli_stmt_bind_param($imgStmt, 'ss', $comment['username'], $emailToCheck);
                                                                mysqli_stmt_execute($imgStmt);
                                                                mysqli_stmt_bind_result($imgStmt, $profileImage);
                                                                mysqli_stmt_fetch($imgStmt);
                                                                mysqli_stmt_close($imgStmt);
                                                            }
                                                        } else {
                                                            $imgQuery = 'SELECT Photo FROM tblwriters WHERE username = ? OR email = ? LIMIT 1';
                                                            if ($imgStmt = mysqli_prepare($con, $imgQuery)) {
                                                                $userEmailForStatus = isset($_SESSION['sessionWriter']) ? $_SESSION['sessionWriter'] : '';
                                                                $emailToCheck = $userEmailForStatus ? $userEmailForStatus : $comment['username'];
                                                                mysqli_stmt_bind_param($imgStmt, 'ss', $comment['username'], $emailToCheck);
                                                                mysqli_stmt_execute($imgStmt);
                                                                mysqli_stmt_bind_result($imgStmt, $profileImage);
                                                                mysqli_stmt_fetch($imgStmt);
                                                                mysqli_stmt_close($imgStmt);
                                                            }
                                                        }

                                                        $imageExists = false;
                                                        if ($profileImage) {
                                                            $imagePath = "../profileimages/" . $profileImage;
                                                            if (file_exists($imagePath)) {
                                                                $imageExists = true;
                                                            }
                                                        }
                                                        ?>

                                                        <!-- Avatar with Tooltip -->
                                                        <div data-bs-toggle="tooltip"
                                                             data-bs-placement="<?php echo $isAdmin ? 'right' : 'left'; ?>"
                                                             data-bs-html="true"
                                                             title="<div class='text-center'>
                    <strong><?php echo htmlspecialchars($comment['username']); ?></strong><br>
                    <span class='badge bg-<?php echo $statusColor; ?> mt-1'>
                        <i class='fas fa-circle'></i> <?php echo $statusText; ?>
                    </span>
                    <?php if ($userStatus['last_seen'] && $userStatus['is_online'] == 0): ?>
                        <br><small class='text-muted mt-1 d-block'><?php echo date('M j, Y g:i A', strtotime($userStatus['last_seen'])); ?></small>
                    <?php endif; ?>
                </div>">

                                                            <?php if ($imageExists): ?>
                                                                <img class="rounded-circle"
                                                                     src="<?php echo $imagePath; ?>"
                                                                     alt="<?php echo htmlspecialchars($comment['username']); ?>"
                                                                     style="width: 45px; height: 45px; object-fit: cover; cursor: pointer; transition: transform 0.2s;">
                                                            <?php else: ?>
                                                                <div class="avatar-name rounded-circle shadow-sm <?php echo $isAdmin ? 'bg-primary' : 'bg-success'; ?>"
                                                                     style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.2s;">
                <span class='fw-bold text-white' style="font-size: 14px;">
                    <?php echo strtoupper(substr($comment['username'], 0, 2)); ?>
                </span>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- Status Indicator Dot -->
                                                            <span class="position-absolute bottom-0 end-0
                     badge rounded-pill bg-<?php echo $statusColor; ?>
                     border border-white border-2
                     <?php echo $userStatus['is_online'] == 1 ? 'pulse-ring' : ''; ?>"
                                                                  style="width: 12px; height: 12px; padding: 0; transform: translate(25%, 25%);">
        </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Message Bubble -->
                                                <div class="flex-grow-1" style="max-width: 75%;">
                                                    <div class="comment-bubble position-relative p-3 shadow-sm <?php echo ($comment['user_type'] === $currentUserType) ? 'bg-success-subtle border border-success-subtle' : 'bg-primary-subtle border border-primary-subtle'; ?>"
                                                         style="border-radius: <?php echo ($comment['user_type'] === $currentUserType) ? '20px 20px 5px 20px' : '20px 20px 20px 5px'; ?>; word-wrap: break-word;">

                                                        <!-- Message Header -->
                                                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                                                            <div class="comment-author fw-bold <?php echo ($comment['user_type'] === $currentUserType) ? 'text-success' : 'text-primary'; ?>" style="font-size: 13px;">
                                                                <i class="fas <?php echo ($comment['user_type'] === $currentUserType) ? 'fa-user-shield' : 'fa-user-edit'; ?> me-1" style="font-size: 11px;"></i>
                                                                <?php echo htmlspecialchars($comment['username']); ?>

                                                                <?php if ($isUnread): ?>
                                                                    <span class="badge bg-danger ms-2 pulse-animation" style="font-size: 9px;">
                                                            <i class="fas fa-envelope me-1"></i>UNREAD
                                                        </span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <!-- Timestamp -->
                                                            <div class="d-flex align-items-center flex-shrink-0">
                                                                <small class="fw-medium text-muted d-flex align-items-center" style="font-size: 11px;">
                                                                    <i class="far fa-clock me-1"></i>
                                                                    <?php echo date('g:i A', strtotime($comment['created_at'] . ' UTC')); ?>

                                                                    <!-- Read Status Ticks -->
                                                                    <span class="ms-2">
                                                           <?php if ($comment['is_read'] == 1): ?>
                                                               <i class="fas fa-check-double text-primary" title="Read" style="font-size: 10px;"></i>
                                                           <?php else: ?>
                                                               <i class="fas fa-check text-muted" title="Delivered" style="font-size: 10px;"></i>
                                                           <?php endif; ?>
                                                       </span>
                                                                </small>
                                                            </div>
                                                        </div>

                                                        <!-- Message Content -->
                                                        <div class="comment-text" style="font-size: 14px;">
                                                            <?php
                                                            $unescaped_comment = stripcslashes($comment['comment']);
                                                            $formatted_comment = nl2br(htmlspecialchars($unescaped_comment));

                                                            // Add link detection
                                                            $formatted_comment = preg_replace(
                                                                '/(https?:\/\/[^\s]+)/',
                                                                '<a href="$1" target="_blank" class="text-decoration-none fw-medium">$1 <i class="fas fa-external-link-alt" style="font-size: 10px;"></i></a>',
                                                                $formatted_comment
                                                            );

                                                            echo $formatted_comment;
                                                            ?>
                                                        </div>

                                                        <!-- File Attachment Display (if exists) -->
                                                        <?php if (!empty($comment['file_url'])): ?>
                                                            <div class="message-attachments mt-3 pt-3 border-top">
                                                                <small class="text-muted d-block mb-2">
                                                                    <i class="fas fa-paperclip"></i> Attachments:
                                                                </small>

                                                                <div class="attachments-grid">
                                                                    <?php
                                                                    // Get file extension
                                                                    $fileExt = strtolower(pathinfo($comment['file_url'], PATHINFO_EXTENSION));
                                                                    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                                                    $isImage = in_array($fileExt, $imageExtensions);

                                                                    if ($isImage):
                                                                        // Display as image with GLightbox
                                                                        ?>
                                                                        <a href="../taskfiles/<?php echo htmlspecialchars($comment['file_url']); ?>"
                                                                           class="glightbox attachment-image"
                                                                           data-gallery="message-<?php echo $comment['id']; ?>"
                                                                           data-glightbox="description: Sent by <?php echo htmlspecialchars($comment['username']); ?>">
                                                                            <img src="../taskfiles/<?php echo htmlspecialchars($comment['file_url']); ?>"
                                                                                 alt="Attachment"
                                                                                 class="img-thumbnail"
                                                                                 style="max-width: 200px; max-height: 200px; object-fit: cover; cursor: pointer; border-radius: 8px;">
                                                                        </a>
                                                                    <?php else:
                                                                        // Display as downloadable file
                                                                        $fileIcon = getFileIconClass($fileExt);
                                                                        ?>
                                                                        <a href="../taskfiles/<?php echo htmlspecialchars($comment['file_url']); ?>"
                                                                           class="attachment-file-badge"
                                                                           download
                                                                           target="_blank">
                                                                            <i class="<?php echo $fileIcon; ?> me-2"></i>
                                                                            <span><?php echo basename($comment['file_url']); ?></span>
                                                                        </a>
                                                                    <?php endif; ?>

                                                                    <?php
                                                                    // Check for additional attachments from message_attachments table
                                                                    $attachQuery = "SELECT * FROM tbl_comment_attachments WHERE comment_id = " . $comment['id'];
                                                                    $attachResult = mysqli_query($con, $attachQuery);

                                                                    if ($attachResult && mysqli_num_rows($attachResult) > 0):
                                                                        while ($attachment = mysqli_fetch_assoc($attachResult)):
                                                                            $attachExt = strtolower(pathinfo($attachment['file_name'], PATHINFO_EXTENSION));
                                                                            $isAttachImage = in_array($attachExt, $imageExtensions);

                                                                            if ($isAttachImage):
                                                                                // Clean the file path - remove any leading path components
                                                                                $cleanFilePath = basename($attachment['file_path']);
                                                                                ?>
                                                                                <a href="../taskfiles/<?php echo htmlspecialchars($cleanFilePath); ?>"
                                                                                   class="glightbox attachment-image"
                                                                                   data-gallery="message-<?php echo $comment['id']; ?>"
                                                                                   data-glightbox="description: <?php echo htmlspecialchars($attachment['file_name']); ?>">
                                                                                    <img src="../taskfiles/<?php echo htmlspecialchars($cleanFilePath); ?>"
                                                                                         alt="<?php echo htmlspecialchars($attachment['file_name']); ?>"
                                                                                         class="img-thumbnail"
                                                                                         style="max-width: 200px; max-height: 200px; object-fit: cover; cursor: pointer; border-radius: 8px;">
                                                                                </a>
                                                                            <?php else:
                                                                                // Clean the file path - remove any leading path components
                                                                                $cleanFilePath = basename($attachment['file_path']);
                                                                                $attachIcon = getFileIconClass($attachExt);
                                                                                ?>
                                                                                <a href="../taskfiles/<?php echo htmlspecialchars($cleanFilePath); ?>"
                                                                                   class="attachment-file-badge"
                                                                                   download
                                                                                   target="_blank">
                                                                                    <i class="<?php echo $attachIcon; ?> me-2"></i>
                                                                                    <span><?php echo htmlspecialchars($attachment['file_name']); ?></span>
                                                                                    <small class="text-muted ms-2">(<?php echo formatFileSize($attachment['file_size']); ?>)</small>
                                                                                </a>
                                                                            <?php
                                                                            endif;
                                                                        endwhile;
                                                                    endif;
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Fixed Input Form at Bottom - Only show if conversation is active -->
                        <?php if (!in_array($taskStatus, ['Completed', 'Cancelled'])): ?>
                            <div class="border-top bg-info-subtle p-3" style="flex-shrink: 0;">
                                <form id="addCommentForm" onsubmit="addComment(event)" enctype="multipart/form-data">
                                    <!-- File Preview Area -->
                                    <div id="filePreview" class="mb-2" style="display: none;">
                                        <div class="alert alert-info d-flex align-items-center justify-content-between mb-2 py-2">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-file me-2"></i>
                                                <span id="fileName" class="small"></span>
                                                <span id="fileSize" class="small text-muted ms-2"></span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeFile()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Selected Files Preview -->
                                    <div id="selectedFilesPreview" style="display: none; margin-bottom: 15px;">
                                        <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 12px; padding: 12px;">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted fw-semibold">
                                                    <i class="fas fa-paperclip me-1"></i>
                                                    <span id="fileCount">0</span> file(s) attached
                                                </small>
                                                <button type="button"
                                                        class="btn btn-sm btn-link text-danger p-0"
                                                        onclick="clearAllFiles()"
                                                        style="text-decoration: none; font-size: 12px;">
                                                    <i class="fas fa-times-circle me-1"></i>Remove all
                                                </button>
                                            </div>
                                            <div id="filesPreviewList" style="max-height: 200px; overflow-y: auto;">
                                                <!-- Files will be added here dynamically -->
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2 align-items-end">
                                        <!-- File Input (Hidden) -->
                                        <input type="file"
                                               id="fileInput"
                                               name="attachments[]"
                                               multiple
                                               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                                               style="display: none;" onchange="handleFileSelect(event)">
                                        <!-- Attach Button -->
                                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('fileInput').click()" title="Attach image">
                                            <i class="fas fa-paperclip"></i>
                                        </button>

                                        <!-- Message Input -->
                                        <div class="flex-grow-1">
                                <textarea class="form-control"
                                          id="commentText"
                                          name="comment"
                                          rows="2"
                                          placeholder="Type your message... (Ctrl+Enter to send)"
                                          style="resize: none; border-radius: 20px;"
                                          onkeydown="handleKeyPress(event)"></textarea>
                                        </div>

                                        <!-- Send Button -->
                                        <button type="submit" class="btn btn-primary px-4" style="border-radius: 20px;">
                                            <i class="fas fa-paper-plane me-1"></i>Send
                                        </button>
                                    </div>

                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Press Ctrl+Enter to send quickly • (Max 10MB)
                                    </small>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- File Preview Modal -->
    <div class='modal fade' id='filePreviewModal' tabindex='-1' aria-labelledby='filePreviewModalLabel' aria-hidden='true'>
        <div class='modal-dialog' style='max-width: 100vw; width: 100vw; height: 100vh; margin: 0;'>
            <div class='modal-content' style='height: 100vh; border-radius: 0;'>
                <div class='modal-header' style='flex-shrink: 0;'>
                    <h5 class='modal-title' id='filePreviewModalLabel'>itasker file preview</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
                <div class='modal-body' id='filePreviewContent'
                     style='flex: 1; display: flex; justify-content: center; align-items: center; padding: 0; overflow: hidden;'>
                    <!-- Preview content will be injected here -->
                    <div id='previewLoading' style='text-align:center;'>
                        <div class='spinner-border text-primary' role='status'>
                            <span class='visually-hidden'>Loading...</span>
                        </div>
                        <p>Loading preview...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Task Duplicate Modal-->
    <div class="modal fade" id="duplicateModal" tabindex="-1" aria-labelledby="duplicateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-info-subtle bg-gradient border-0 text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h5 class="modal-title d-flex align-items-center" id="duplicateModalLabel">
                        <div class="rounded-circle bg-white bg-opacity-25 p-2 me-3">
                            <i class="fas fa-clone fa-lg"></i>
                        </div>
                        <div>
                            <div class="fw-bold">Duplicate Task</div>
                            <small class="opacity-75" style="font-size: 0.85rem;">Create a copy of this task</small>
                        </div>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 d-flex align-items-start mb-4">
                        <i class="fas fa-info-circle text-info me-3 mt-1"></i>
                        <div class="flex-grow-1">
                            <strong>Confirmation Required</strong>
                            <p class="mb-0 small text-muted mt-1">You are about to create a duplicate of the following task. All task details will be copied.</p>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                                            <i class="fas fa-hashtag text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted text-uppercase fw-semibold d-block" style="font-size: 0.75rem; letter-spacing: 0.5px;">Task ID</small>
                                            <span id="modalTaskId" class="fw-bold fs-6"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <hr class="my-2 opacity-25">
                                </div>
                                <div class="col-12">
                                    <div class="d-flex align-items-start">
                                        <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3 mt-1">
                                            <i class="fas fa-tasks text-success"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted text-uppercase fw-semibold d-block mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Task Title</small>
                                            <span id="modalTaskTitle" class="fw-medium"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded p-3 border-start border-4 border-warning">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                            <small class="text-muted mb-0">The duplicated task will appear as a new entry in drafts task list.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0  p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary px-4 shadow-sm" id="confirmDuplicateBtn">
                        <i class="fas fa-clone me-2"></i>Duplicate Task
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Delete File Confirmation Modal -->
    <div class="modal fade" id="deleteFileModal" tabindex="-1" aria-labelledby="deleteFileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title text-white" id="deleteFileModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Delete File
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-trash-alt text-danger" style="font-size: 48px;"></i>
                    </div>
                    <p class="text-center mb-3">Are you sure you want to delete this file?</p>
                    <div class="bg-secondary-subtle rounded p-3 mb-3">
                        <p class="mb-1 text-center">
                            <strong id="deleteFileName"></strong>
                        </p>
                        <p class="mb-0 text-center text-muted fs-10">
                            <i class="far fa-clock me-1"></i>Uploaded: <span id="deleteFileDate"></span>
                        </p>
                    </div>
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <div>
                            <strong>Warning:</strong> This action cannot be undone. The file will be permanently removed and cannot be retrieved.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn" onclick="return deleteFile();">
                        <i class="fas fa-trash-alt me-1"></i>Delete File
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const duplicateModal = document.getElementById('duplicateModal');
            const confirmBtn = document.getElementById('confirmDuplicateBtn');
            const modalTaskId = document.getElementById('modalTaskId');
            const modalTaskTitle = document.getElementById('modalTaskTitle');
            let taskId = ''; // This will store the encoded ID

            duplicateModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                taskId = button.getAttribute('data-task-id'); // Encoded ID
                const taskTitle = button.getAttribute('data-task-title');

                // Decode the task ID for display purposes only
                const decodedTaskId = atob(taskId);

                // Update modal content with animation
                modalTaskId.style.opacity = '0';
                modalTaskTitle.style.opacity = '0';

                setTimeout(() => {
                    modalTaskId.textContent = decodedTaskId; // Show decoded ID
                    modalTaskTitle.textContent = taskTitle;

                    modalTaskId.style.transition = 'opacity 0.3s ease-in';
                    modalTaskTitle.style.transition = 'opacity 0.3s ease-in';
                    modalTaskId.style.opacity = '1';
                    modalTaskTitle.style.opacity = '1';
                }, 100);
            });

            confirmBtn.addEventListener('click', function() {
                // Add loading state
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Duplicating...';

                // Redirect with the encoded task_id parameter
                setTimeout(() => {
                    window.location.href = 'duplicate-task.php?task_id=' + taskId; // Use encoded ID in URL
                }, 300);
            });
        });

        function viewFile(taskId, fileIndex, fileType) {
            const url = `file-viewer.php?task_id=${taskId}&file=${fileIndex}&type=${fileType}`;
            window.open(url, 'fileViewer', 'width=1000,height=700,scrollbars=yes,resizable=yes');
        }

        function toggleFavorite(taskId) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'toggle_favorite', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        const favoriteBtn = document.getElementById('favorite-btn');
                        const favoriteIcon = document.getElementById('favorite-icon');
                        const favoriteText = document.getElementById('favorite-text');

                        let toastMessage = '';
                        if (response.is_favorite == 1) {
                            favoriteIcon.classList.remove('fa-heart-broken');
                            favoriteIcon.classList.add('fa-heart');
                            favoriteText.textContent = 'Unfavorite';
                            toastMessage = 'Task added to favorites!';
                        } else {
                            favoriteIcon.classList.remove('fa-heart');
                            favoriteIcon.classList.add('fa-heart-broken');
                            favoriteText.textContent = 'Favorite';
                            toastMessage = 'Task removed from favorites!';
                        }

                        // Show success toast notification
                        showBootstrapToast(toastMessage, 'success');
                    } else {
                        showBootstrapToast('Failed to update favorite status.', 'danger');
                    }
                } else {
                    showBootstrapToast('An error occurred while updating favorite status.', 'danger');
                }
            };
            xhr.send('task_id=' + taskId);
        }

        function completeTask(encodedId, taskId) {
            const confirmBtn = document.getElementById('confirmCompleteBtn');
            const originalText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
            confirmBtn.disabled = true;

            $.ajax({
                url: 'complete-task',
                type: 'POST',
                data: { task_id: encodedId },
                success: function() {
                    // Hide modal
                    var modalEl = document.getElementById('completeTaskModal');
                    var modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    // Show success toast before redirecting
                    showBootstrapToast('Task completed!', 'success');

                    // Delay redirect to allow toast to be seen
                    setTimeout(function() {
                        window.location.href = 'view-task?task_id=' + encodedId;
                    }, 2000);
                },
                error: function() {
                    confirmBtn.innerHTML = originalText;
                    confirmBtn.disabled = false;
                    showBootstrapToast('An error occurred while completing the task.', 'danger');
                }
            });
        }

        // Simple Bootstrap toast function
        function showBootstrapToast(message, type = 'success') {
            // Remove any existing toast
            const existingToast = document.getElementById('dynamic-toast');
            if (existingToast) {
                existingToast.remove();
            }

            // Create the toast alert
            const toast = document.createElement('div');
            toast.id = 'dynamic-toast';
            toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
    `;
            toast.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

            document.body.appendChild(toast);

            // Auto-dismiss after 4 seconds
            setTimeout(() => {
                const alert = toast.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 4000);

            // Remove toast element when alert is closed
            toast.addEventListener('closed.bs.alert', function() {
                toast.remove();
            });
        }

    </script>
    <script>
        function markAsPaidConfirm(encodedId, taskId) {
            if (confirm('Are you sure you have paid task ID: #' + taskId + '?')) {
                $.ajax({
                    url: 'confirm-paid',
                    type: 'POST',
                    data: { task_id: encodedId },
                    success: function() {
                        // Redirect to the task details page after completing the task
                        window.location.href = 'view-task?task_id=' + encodedId;
                    },
                    error: function() {
                        showToast('An error occurred while marking the task as paid.');
                    }
                });
            }
        }
    </script>
    <script>
        (function () {
            const el = document.getElementById('time-remaining');
            if (!el) return;

            const due = new Date("<?php echo $taskDueDate; ?>").getTime();

            function update() {
                const now = Date.now();
                const diff = due - now;

                if (diff <= 0) {
                    el.textContent = 'Past Due';
                    el.className = el.className.replace(/text-\w+/, '') + ' text-danger';
                    return;
                }

                const days    = Math.floor(diff / 86400000);
                const hours   = Math.floor((diff % 86400000) / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);
                const seconds = Math.floor((diff % 60000) / 1000);

                let text = '';
                if (days > 0)         text = `${days}d ${hours}h ${minutes}m`;
                else if (hours > 0)   text = `${hours}h ${minutes}m ${seconds}s`;
                else if (minutes > 0) text = `${minutes}m ${seconds}s`;
                else                  text = `${seconds}s`;

                el.textContent = text;

                // Swap badge color as deadline approaches
                const cls = el.className.replace(/\b(text-\w+|bg-\w+-subtle|border-\w+-subtle)\b/g, '').trim();
                if (diff < 3600000)         el.className = cls + ' text-danger bg-danger-subtle border-danger-subtle';
                else if (diff < 86400000)   el.className = cls + ' text-warning bg-warning-subtle border-warning-subtle';
                else                        el.className = cls + ' text-success bg-success-subtle border-success-subtle';

                setTimeout(update, 1000);
            }

            update();
        })();
    </script>
    <script>
        function previewFile(taskId, fileIndex, fileType) {
            const modalElement = document.getElementById('filePreviewModal');
            const contentContainer = document.getElementById('filePreviewContent');
            const loadingIndicator = document.getElementById('previewLoading');

            // Clean up any existing modal instance
            const existingModal = bootstrap.Modal.getInstance(modalElement);
            if (existingModal) {
                existingModal.dispose();
            }

            // Create a fresh modal instance
            const modal = new bootstrap.Modal(modalElement);

            // Reset modal content
            contentContainer.innerHTML = '';

            // Create and show loading indicator
            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'previewLoading';
            loadingDiv.style.textAlign = 'center';
            loadingDiv.innerHTML = `
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p>Loading preview...</p>
      `;
            contentContainer.appendChild(loadingDiv);

            // Build the file viewer URL
            const url = `file-viewer?task_id=${taskId}&file=${fileIndex}&type=${fileType}`;

            // Create iframe for preview
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.style.width = '100%';
            iframe.style.height = '95vh';
            iframe.style.border = 'none';

            // When iframe loads, hide loading spinner
            iframe.onload = () => {
                const currentLoading = document.getElementById('previewLoading');
                if (currentLoading) {
                    currentLoading.remove();
                }
            };

            // Add error handling
            iframe.onerror = () => {
                const currentLoading = document.getElementById('previewLoading');
                if (currentLoading) {
                    currentLoading.innerHTML = '<p class="text-danger">Failed to load file preview.</p>';
                }
            };

            // Add iframe to content container
            contentContainer.appendChild(iframe);

            // Clean up when modal is hidden
            modalElement.addEventListener('hidden.bs.modal', function() {
                contentContainer.innerHTML = '';
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) {
                    modalInstance.dispose();
                }
            }, { once: true }); // Use { once: true } to prevent multiple event listeners

            // Show modal
            modal.show();
        }
    </script>
    <script>
        // Enhanced toggle function with smooth animations
        function toggleCommentForm() {
            const form = document.getElementById('commentForm');
            const isVisible = form.style.display !== 'none';

            if (isVisible) {
                // Slide up animation
                form.style.animation = 'slideUp 0.3s ease';
                setTimeout(() => {
                    form.style.display = 'none';
                    document.getElementById('commentText').value = '';
                }, 300);
            } else {
                form.style.display = 'block';
                form.style.animation = 'slideDown 0.3s ease';
                setTimeout(() => {
                    document.getElementById('commentText').focus();
                }, 100);
            }
        }
        // Global variable to store selected files
        let selectedFilesArray = [];

        function addComment(event) {
            event.preventDefault();

            const commentText = document.getElementById('commentText').value.trim();
            const fileInput = document.getElementById('fileInput');

            // Check if message or files are provided
            if (!commentText && selectedFilesArray.length === 0) {
                showToast('Please enter a message or attach a file', 'warning');
                return;
            }

            // Show loading state
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';

            const formData = new FormData();
            formData.append('task_id', <?php echo $taskId; ?>);
            formData.append('comment', commentText);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
            formData.append('receiver_id', '<?php echo $receiverId; ?>');
            formData.append('receiver_type', '<?php echo $receiverType; ?>');

            // Add all selected files
            if (selectedFilesArray.length > 0) {
                selectedFilesArray.forEach((file, index) => {
                    formData.append('attachments[]', file);
                });
            }

            fetch('add-task-comment', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            console.error('Non-JSON response received:', text);
                            throw new Error('Server returned HTML instead of JSON');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                        showToast('Message sent successfully!', 'success');


                        // Scroll to bottom
                        const container = document.getElementById('commentsContainer');
                        if (container) {
                            container.scrollTop = container.scrollHeight;
                        }

                        // Reload after short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        const errorMessage = data && data.message ? data.message : 'Failed to send message';
                        showToast(errorMessage, 'danger');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred while sending the message', 'danger');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
        }

        function handleFileSelect(event) {
            const files = Array.from(event.target.files);

            if (files.length === 0) return;

            files.forEach(file => {
                // Validate file size (10MB max)
                if (file.size > 10 * 1024 * 1024) {
                    showToast(`File "${file.name}" is too large. Maximum size is 10MB.`, 'warning');
                    return;
                }

                // Check for duplicates
                if (selectedFilesArray.some(f => f.name === file.name && f.size === file.size)) {
                    showToast(`File "${file.name}" is already attached.`, 'info');
                    return;
                }

                // Add to array
                selectedFilesArray.push(file);
            });

            // Update preview
            updateFilePreview();
        }

        function updateFilePreview() {
            const previewContainer = document.getElementById('selectedFilesPreview');
            const filesList = document.getElementById('filesPreviewList');
            const fileCount = document.getElementById('fileCount');

            if (!previewContainer || !filesList || !fileCount) return;

            // Clear existing preview
            filesList.innerHTML = '';

            if (selectedFilesArray.length === 0) {
                previewContainer.style.display = 'none';
                return;
            }

            // Show preview container
            previewContainer.style.display = 'block';

            // Update count
            fileCount.textContent = selectedFilesArray.length;

            // Add each file to preview
            selectedFilesArray.forEach((file, index) => {
                const fileItem = createFilePreviewItem(file, index);
                filesList.appendChild(fileItem);
            });
        }

        function createFilePreviewItem(file, index) {
            const fileExt = file.name.split('.').pop().toLowerCase();
            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const isImage = imageExtensions.includes(fileExt);

            const div = document.createElement('div');
            div.className = 'file-preview-item';
            div.style.cssText = 'display: flex; align-items: center; padding: 8px; background: white; border-radius: 8px; margin-bottom: 6px; border: 1px solid #e9ecef; transition: all 0.2s;';

            // Create preview content
            let previewHTML = '';

            if (isImage) {
                // For images, create thumbnail
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = div.querySelector('.file-thumbnail');
                    if (img) {
                        img.src = e.target.result;
                    }
                };
                reader.readAsDataURL(file);

                previewHTML = `
            <img class="file-thumbnail"
                 src=""
                 alt="${escapeHtml(file.name)}"
                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; margin-right: 10px; border: 1px solid #dee2e6;">
        `;
            } else {
                // For documents, show icon
                const iconClass = getFileIconClass(fileExt);
                previewHTML = `
            <div style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                <i class="${iconClass}" style="font-size: 24px;"></i>
            </div>
        `;
            }

            div.innerHTML = `
        ${previewHTML}
        <div style="flex-grow: 1; min-width: 0;">
            <div style="font-size: 13px; font-weight: 500; color: #212529; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(file.name)}">
                ${escapeHtml(file.name)}
            </div>
            <div style="font-size: 11px; color: #6c757d;">
                ${formatFileSize(file.size)}
            </div>
        </div>
        <button type="button"
                class="btn btn-sm btn-link text-danger p-1"
                onclick="removeFileByIndex(${index})"
                style="text-decoration: none; font-size: 16px;"
                title="Remove file">
            <i class="fas fa-times-circle"></i>
        </button>
    `;

            // Hover effect
            div.onmouseenter = function() {
                this.style.background = '#f8f9fa';
                this.style.transform = 'translateX(3px)';
            };
            div.onmouseleave = function() {
                this.style.background = 'white';
                this.style.transform = 'translateX(0)';
            };

            return div;
        }

        function removeFileByIndex(index) {
            if (index >= 0 && index < selectedFilesArray.length) {
                const fileName = selectedFilesArray[index].name;
                selectedFilesArray.splice(index, 1);
                updateFileInput();
                updateFilePreview();
                showToast(`Removed: ${fileName}`, 'info');
            }
        }

        function clearAllFiles() {
            selectedFilesArray = [];
            const fileInput = document.getElementById('fileInput');
            if (fileInput) fileInput.value = '';
            updateFilePreview();
            showToast('All files removed', 'info');
        }

        function updateFileInput() {
            const fileInput = document.getElementById('fileInput');
            if (!fileInput) return;

            const dataTransfer = new DataTransfer();
            selectedFilesArray.forEach(file => {
                dataTransfer.items.add(file);
            });

            fileInput.files = dataTransfer.files;
        }

        function getFileIconClass(ext) {
            const iconMap = {
                'pdf': 'fas fa-file-pdf text-danger',
                'doc': 'fas fa-file-word text-primary',
                'docx': 'fas fa-file-word text-primary',
                'xls': 'fas fa-file-excel text-success',
                'xlsx': 'fas fa-file-excel text-success',
                'txt': 'fas fa-file-alt text-secondary',
                'jpg': 'fas fa-file-image text-info',
                'jpeg': 'fas fa-file-image text-info',
                'png': 'fas fa-file-image text-info',
                'gif': 'fas fa-file-image text-info',
                'webp': 'fas fa-file-image text-info',
            };
            return iconMap[ext] || 'fas fa-file text-muted';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initialize GLightbox for image attachments
        document.addEventListener('DOMContentLoaded', function() {
            const lightbox = GLightbox({
                touchNavigation: true,
                loop: true,
                autoplayVideos: true,
                closeButton: true,
                closeOnOutsideClick: true,
                moreLength: 0
            });

            // Reinitialize after new messages are loaded
            function reinitLightbox() {
                if (typeof GLightbox !== 'undefined') {
                    GLightbox({
                        touchNavigation: true,
                        loop: true,
                        autoplayVideos: true
                    });
                }
            }

            // Call this after AJAX updates if needed
            window.reinitLightbox = reinitLightbox;
        });

        // Toast notification function
        function showToast(message, type = 'info') {
            // Map types to Bootstrap colors
            const typeMap = {
                'success': 'bg-success',
                'danger': 'bg-danger',
                'error': 'bg-danger',
                'warning': 'bg-warning',
                'info': 'bg-info',
                'primary': 'bg-primary'
            };

            // Map types to icons
            const iconMap = {
                'success': 'fas fa-check-circle',
                'danger': 'fas fa-exclamation-circle',
                'error': 'fas fa-exclamation-circle',
                'warning': 'fas fa-exclamation-triangle',
                'info': 'fas fa-info-circle',
                'primary': 'fas fa-bell'
            };

            const bgClass = typeMap[type] || 'bg-info';
            const icon = iconMap[type] || 'fas fa-info-circle';

            // Create unique ID for this toast
            const toastId = 'toast-' + Date.now();

            // Create toast HTML
            const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="${icon} me-2"></i>
                    ${escapeHtml(message)}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

            // Get or create toast container
            let container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                container.style.zIndex = '9999';
                document.body.appendChild(container);
            }

            // Add toast to container
            container.insertAdjacentHTML('beforeend', toastHTML);

            // Get the toast element
            const toastElement = document.getElementById(toastId);

            // Initialize and show toast
            if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                const bsToast = new bootstrap.Toast(toastElement);
                bsToast.show();

                // Remove toast from DOM after it's hidden
                toastElement.addEventListener('hidden.bs.toast', function () {
                    toastElement.remove();
                });
            } else {
                // Fallback if Bootstrap is not available
                toastElement.style.display = 'block';
                toastElement.style.animation = 'slideInRight 0.3s ease';

                setTimeout(() => {
                    toastElement.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => {
                        toastElement.remove();
                    }, 300);
                }, 5000);
            }
        }

        // Enhanced scroll to bottom function with smooth animation
        function scrollToBottom() {
            const container = document.getElementById('commentsContainer');
            container.scrollTo({
                top: container.scrollHeight,
                behavior: 'smooth'
            });

            // Hide scroll indicator
            const indicator = document.getElementById('scrollIndicator');
            if (indicator) {
                indicator.style.display = 'none';
            }
        }

        // Enhanced toast notification function for comments
        function showCommentToast(message, type = 'success') {
            // Remove any existing comment toast
            const existingToast = document.getElementById('comment-toast');
            if (existingToast) {
                existingToast.remove();
            }

            // Create enhanced toast
            const toast = document.createElement('div');
            toast.id = 'comment-toast';
            toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 350px;
        animation: slideInRight 0.4s ease;
    `;

            // Toast icon mapping
            const icons = {
                success: 'fa-check-circle',
                danger: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };

            toast.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show shadow-lg border-0" role="alert" style="border-radius: 12px;">
            <div class="d-flex align-items-center">
                <i class="fas ${icons[type]} me-2 fs-5"></i>
                <div class="flex-1">
                    <span>${message}</span>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

            document.body.appendChild(toast);

            // Auto-dismiss after 4 seconds
            setTimeout(() => {
                const alert = toast.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 4000);

            // Remove toast element when alert is closed
            toast.addEventListener('closed.bs.alert', function() {
                toast.remove();
            });
        }

        // Enhanced keyboard shortcuts
        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                // Ctrl+Enter to send message
                if (e.ctrlKey && e.key === 'Enter') {
                    const commentForm = document.getElementById('addCommentForm');
                    const commentFormVisible = document.getElementById('commentForm').style.display !== 'none';

                    if (commentForm && commentFormVisible) {
                        e.preventDefault();
                        commentForm.dispatchEvent(new Event('submit', { bubbles: true }));
                    }
                }

                // Escape to close comment form
                if (e.key === 'Escape') {
                    const commentForm = document.getElementById('commentForm');
                    if (commentForm && commentForm.style.display !== 'none') {
                        toggleCommentForm();
                    }
                }

                // Ctrl+M to open comment form
                if (e.ctrlKey && e.key === 'm') {
                    e.preventDefault();
                    const commentForm = document.getElementById('commentForm');
                    if (commentForm && commentForm.style.display === 'none') {
                        toggleCommentForm();
                    }
                }
            });
        }

        // Enhanced scroll detection for scroll indicator
        function setupScrollDetection() {
            const container = document.getElementById('commentsContainer');
            if (!container) return;

            container.addEventListener('scroll', function() {
                const indicator = document.getElementById('scrollIndicator');
                if (!indicator) return;

                const isNearBottom = this.scrollTop >= this.scrollHeight - this.clientHeight - 100;

                if (!isNearBottom && this.children.length > 3) {
                    indicator.style.display = 'block';
                    indicator.style.animation = 'fadeIn 0.3s ease';
                } else {
                    indicator.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => {
                        indicator.style.display = 'none';
                    }, 300);
                }
            });
        }

        // Enhanced auto-scroll and comment reading functionality
        function setupAutoScroll() {
            const commentsContainer = document.getElementById('commentsContainer');
            if (commentsContainer && commentsContainer.children.length > 0) {
                // Auto-scroll to bottom with delay for better UX
                setTimeout(() => {
                    scrollToBottom();
                }, 500);
            }
        }

        // Enhanced comment reading functionality
        function markCommentsAsReadOnLoad() {
            const taskId = <?php echo $taskId; ?>;

            fetch('mark-admin-comments-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    task_id: taskId,
                    action: 'mark_admin_comments_read'
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.count > 0) {
                        // Show subtle notification
                        //showCommentToast(`Marked ${data.count} new messages as read`, 'info');

                        // Update UI after delay
                        setTimeout(() => {
                            updateCommentsUI();
                        }, 3000);
                    }
                })
                .catch(error => {
                    console.error('Error marking comments as read:', error);
                });
        }

        // Enhanced comments section observer
        function observeCommentsSection() {
            const commentsSection = document.querySelector('#commentsContainer');

            if (commentsSection) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            // Delay marking as read to ensure user actually sees the messages
                            setTimeout(() => {
                                markCommentsAsReadOnLoad();
                            }, 2000);
                            observer.unobserve(entry.target);
                        }
                    });
                }, {
                    threshold: 0.8,
                    rootMargin: '0px 0px -50px 0px'
                });

                observer.observe(commentsSection);
            }
        }

        // Enhanced UI update function
        function updateCommentsUI() {
            // Remove "NEW" badges with smooth animation - fixed selector
            const unreadBadges = document.querySelectorAll('.unread-message-badge');
            unreadBadges.forEach(badge => {
                // Add fade out animation
                badge.style.transition = 'all 0.5s ease-out';
                badge.style.opacity = '0';
                badge.style.transform = 'scale(0.8)';

                // Remove the element after animation completes
                setTimeout(() => {
                    if (badge.parentNode) {
                        badge.remove();
                    }
                }, 5000);
            });

            // Also remove any pulse animations from remaining unread elements
            const pulseElements = document.querySelectorAll('.pulse-animation');
            pulseElements.forEach(element => {
                element.classList.remove('pulse-animation');
                element.style.animation = 'none';
            });
        }

        // Enhanced textarea auto-resize functionality
        function setupTextareaAutoResize() {
            const textarea = document.getElementById('commentText');
            if (!textarea) return;

            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 150) + 'px';
            });

            // Reset height when form is closed
            textarea.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.style.height = 'auto';
                }
            });
        }

        // Enhanced message interaction handlers
        function setupMessageInteractions() {
            // Add hover effects for message actions
            document.addEventListener('mouseover', function(e) {
                const commentBubble = e.target.closest('.comment-bubble');
                if (commentBubble) {
                    const hoverActions = commentBubble.querySelector('.hover-actions');
                    if (hoverActions) {
                        hoverActions.style.opacity = '1';
                    }
                }
            });

            document.addEventListener('mouseout', function(e) {
                const commentBubble = e.target.closest('.comment-bubble');
                if (commentBubble) {
                    const hoverActions = commentBubble.querySelector('.hover-actions');
                    if (hoverActions) {
                        hoverActions.style.opacity = '0';
                    }
                }
            });
        }

        // Enhanced CSS animations
        function injectEnhancedStyles() {
            const style = document.createElement('style');
            style.textContent = `
        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(0);
                opacity: 1;
            }
            to {
                transform: translateY(-20px);
                opacity: 0;
            }
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        }

        .animate-fade-in {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .comment-bubble:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
            transition: all 0.3s ease;
        }

        #commentsContainer::-webkit-scrollbar {
            width: 6px;
        }

        #commentsContainer::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        #commentsContainer::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        #commentsContainer::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Base avatar styling */
.avatar {
    position: relative;
    display: inline-block;
    transition: all 0.3s ease;
}

.avatar-xl {
    width: 45px !important;
    height: 45px !important;
}

/* Online status indicator using ::after pseudo-element */
.avatar::after {
    content: '';
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}

/* Status-specific colors and animations */
.status-online::after {
    background-color: #198754; /* Success green */
    animation: onlinePulse 2s infinite;
    box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.3);
}

.status-idle::after {
    background-color: #0dcaf0; /* Info blue */
    box-shadow: 0 0 0 2px rgba(13, 202, 240, 0.3);
}

.status-away::after {
    background-color: #ffc107; /* Warning yellow */
    box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.3);
}

.status-offline::after {
    background-color: #6c757d; /* Secondary gray */
    box-shadow: 0 0 0 2px rgba(108, 117, 125, 0.3);
}

/* Pulse animation for online users */
@keyframes onlinePulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.3);
        opacity: 0.7;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

/* Hover effects */
.avatar:hover {
    transform: scale(1.05);
}

.avatar:hover::after {
    transform: scale(1.2);
}

/* Additional status classes for different states */
.status-busy::after {
    background-color: #dc3545; /* Danger red */
    box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.3);
}

.status-dnd::after {
    background-color: #dc3545; /* Do not disturb - red */
    animation: none;
}

/* Enhanced tooltip styling */
.tooltip-inner {
    background-color: rgba(0, 0, 0, 0.9) !important;
    color: white !important;
    padding: 10px 15px !important;
    border-radius: 8px !important;
    font-size: 12px !important;
    max-width: 250px !important;
    text-align: left !important;
}

.tooltip .tooltip-arrow {
    border-top-color: rgba(0, 0, 0, 0.9) !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .avatar-xl {
        width: 40px !important;
        height: 40px !important;
    }

    .avatar::after {
        width: 10px;
        height: 10px;
        border-width: 1px;
    }
}

/* Status legend (optional - can be added to help users understand statuses) */
.status-legend {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    z-index: 1000;
    font-size: 11px;
}

.status-legend.show {
    display: block;
}

.status-legend-item {
    display: flex;
    align-items: center;
    margin-bottom: 4px;
}

.status-legend-item:last-child {
    margin-bottom: 0;
}

.status-legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
}
@keyframes onlinePulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.2);
        opacity: 0.7;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.online-indicator {
    transition: all 0.3s ease;
}

.online-indicator:hover {
    transform: scale(1.2);
}

/* Status-specific styling */
.bg-success.online-indicator {
    box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.3);
}

.bg-warning.online-indicator {
    box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.3);
}

.bg-info.online-indicator {
    box-shadow: 0 0 0 2px rgba(13, 202, 240, 0.3);
}

.bg-secondary.online-indicator {
    box-shadow: 0 0 0 2px rgba(108, 117, 125, 0.3);
}

/* Tooltip styling */
.tooltip-inner {
    background-color: rgba(0, 0, 0, 0.9) !important;
    color: white !important;
    padding: 8px 12px !important;
    border-radius: 6px !important;
    font-size: 12px !important;
    max-width: 200px !important;
}

.tooltip .tooltip-arrow {
    border-top-color: rgba(0, 0, 0, 0.9) !important;
}

        .cursor-pointer {
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .cursor-pointer:hover {
            color: #667eea !important;
        }
    `;
            document.head.appendChild(style);
        }

        // Enhanced JavaScript for unread message handling
        function scrollToFirstUnread() {
            const firstUnread = document.getElementById('first-unread-message');
            if (firstUnread) {
                firstUnread.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

                // Add temporary highlight effect
                firstUnread.style.transform = 'scale(1.02)';
                firstUnread.style.transition = 'transform 0.3s ease';

                setTimeout(() => {
                    firstUnread.style.transform = 'scale(1)';
                }, 1000);
            }
        }

        // Mark unread messages as read when they come into view
        function setupUnreadObserver() {
            const unreadMessages = document.querySelectorAll('[data-unread="true"]');

            if (unreadMessages.length === 0) return;

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const messageElement = entry.target;

                        // Mark as read after 2 seconds of being in view
                        setTimeout(() => {
                            if (entry.isIntersecting) {
                                markMessageAsRead(messageElement);
                            }
                        }, 2000);
                    }
                });
            }, {
                threshold: 0.8,
                rootMargin: '0px 0px -50px 0px'
            });

            unreadMessages.forEach(message => {
                observer.observe(message);
            });
        }

        function markMessageAsRead(messageElement) {
            // Remove unread styling
            messageElement.classList.remove('unread-message-glow');
            messageElement.style.animation = 'none';

            // Remove unread badge
            const badge = messageElement.querySelector('.unread-message-badge');
            if (badge) {
                badge.style.animation = 'fadeOut 0.5s ease';
                setTimeout(() => {
                    badge.remove();
                }, 500);
            }

            // Remove unread indicator
            const indicator = messageElement.querySelector('.position-absolute.top-0.end-0');
            if (indicator) {
                indicator.style.animation = 'fadeOut 0.5s ease';
                setTimeout(() => {
                    indicator.remove();
                }, 500);
            }

            // Update container styling
            const container = messageElement.closest('.comment-item');
            if (container) {
                container.style.background = 'none';
                container.style.animation = 'none';
            }
        }

        // Main initialization function
        function initializeEnhancedTaskDiscussion() {
            // Inject enhanced styles
            injectEnhancedStyles();

            // Setup all enhanced functionalities
            setupKeyboardShortcuts();
            setupScrollDetection();
            setupAutoScroll();
            setupTextareaAutoResize();
            setupMessageInteractions();
            observeCommentsSection();
            setupUnreadObserver();

            // Auto-scroll to first unread if there are unread messages
            const unreadCount = <?php echo $unreadCount; ?>;
            if (unreadCount > 0) {
                setTimeout(() => {
                    scrollToFirstUnread();
                }, 1000);
            }

        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            initializeEnhancedTaskDiscussion();
        });
    </script>
    <script>

        document.addEventListener('DOMContentLoaded', function () {
            const body    = document.getElementById('discussionBody');
            const chevron = document.getElementById('discussionChevron');
            if (!body || !chevron) return;

            body.addEventListener('show.bs.collapse', () => {
                chevron.querySelector('i').style.transform = 'rotate(180deg)';
            });
            body.addEventListener('hide.bs.collapse', () => {
                chevron.querySelector('i').style.transform = 'rotate(0deg)';
            });
        });

        const POLLING_INTERVAL = 5000; // 5 seconds
        let lastTimestamp = null;
        let pollingTimer = null;
        let isPolling = false;

        // Get task ID from existing variable or PHP
        let pollingTaskId;
        if (typeof taskId !== 'undefined') {
            pollingTaskId = taskId; // Use existing taskId variable
        } else {
            pollingTaskId = <?php echo isset($taskId) ? $taskId : '0'; ?>; // Fallback to PHP
        }

        function initializePolling() {
            // Verify task ID is valid
            if (!pollingTaskId || pollingTaskId <= 0) {
                console.error('❌ Invalid task ID - polling disabled');
                return;
            }

            // Get the latest comment timestamp from current page
            const comments = document.querySelectorAll('.comment-item[data-timestamp]');
            if (comments.length > 0) {
                const lastComment = comments[comments.length - 1];
                const timestamp = lastComment.dataset.timestamp;
                if (timestamp) {
                    lastTimestamp = timestamp;
                }
            }

            // Start polling
            startPolling();

        }

        function startPolling() {
            if (isPolling) return;

            isPolling = true;
            pollingTimer = setInterval(checkForNewComments, POLLING_INTERVAL);
        }

        function stopPolling() {
            if (pollingTimer) {
                clearInterval(pollingTimer);
                pollingTimer = null;
            }
            isPolling = false;
        }


        function checkForNewComments() {
            // Verify task ID
            if (!pollingTaskId || pollingTaskId <= 0) {
                console.error('Invalid task ID');
                stopPolling();
                return;
            }

            // Build URL with parameters
            let url = 'get-new-comments?task_id=' + pollingTaskId;
            if (lastTimestamp) {
                url += '&last_timestamp=' + encodeURIComponent(lastTimestamp);
            }

            fetch(url, {
                method: 'GET', // Changed to GET
                credentials: 'same-origin' // Include session cookies
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {

                    // Show debug info if error
                    if (!data.success && data.debug) {
                        console.error('❌ Debug info:', data.debug);
                    }

                    if (data.success && data.comments && data.comments.length > 0) {
                        // Add new comments to the page
                        data.comments.forEach(comment => {
                            addCommentToPage(comment, data.current_user_type);
                        });

                        // Update latest timestamp
                        if (data.latest_timestamp) {
                            lastTimestamp = data.latest_timestamp;
                        }

                        // Show notification
                        showNewMessageNotification(data.comments.length);

                        // Mark messages as read if they're visible
                        setTimeout(markVisibleMessagesAsRead, 500);
                    } else if (!data.success) {
                        console.error('Poll error:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Polling error:', error);
                    // Don't stop polling on error, just log it
                });
        }

        function addCommentToPage(comment, currentUserType) {
            const container = document.getElementById('commentsContainer');
            if (!container) {
                console.error('Comments container not found');
                return;
            }

            // Check if comment already exists (prevent duplicates)
            const existingComment = document.querySelector(`[data-comment-id="${comment.id}"]`);
            if (existingComment) {
                console.log('Comment already exists, skipping:', comment.id);
                return;
            }

            const isAdmin = comment.user_type === 'admin';
            const isCurrentUser = comment.user_type === currentUserType;

            // Create comment HTML
            const commentHTML = createCommentHTML(comment, isAdmin, isCurrentUser);

            // Add to container
            container.insertAdjacentHTML('beforeend', commentHTML);

            console.log('Added new comment:', comment.id);

            // Scroll to bottom (smooth)
            setTimeout(() => {
                container.scrollTo({
                    top: container.scrollHeight,
                    behavior: 'smooth'
                });
            }, 100);

            // Animate new comment
            const newCommentElement = container.lastElementChild;
            if (newCommentElement) {
                newCommentElement.style.animation = 'slideInUp 0.3s ease';
            }

            // Reinitialize GLightbox for new images
            if (typeof GLightbox !== 'undefined') {
                try {
                    GLightbox().reload();
                } catch (e) {
                    console.log('GLightbox reload skipped');
                }
            }
            // Reinitialize read tracking for new messages
            if (typeof window.reinitializeReadTracking === 'function') {
                setTimeout(() => {
                    window.reinitializeReadTracking();
                }, 500);
            }
        }

        function createCommentHTML(comment, isAdmin, isCurrentUser) {
            const alignment = isCurrentUser ? 'flex-row-reverse' : 'flex-row';
            const bubbleColor = isCurrentUser ? 'bg-success-subtle border-success-subtle' : 'bg-primary-subtle border-primary-subtle';
            const bubbleRadius = isCurrentUser ? '20px 20px 5px 20px' : '20px 20px 20px 5px';
            const textColor = isCurrentUser ? 'text-success' : 'text-primary';
            const userIcon = isCurrentUser ? 'fa-user-edit' : 'fa-user-shield';

            // Avatar HTML
            let avatarHTML = '';
            if (comment.profile_image) {
                avatarHTML = `<img class="rounded-circle" src="${escapeHtml(comment.profile_image)}"
                          alt="${escapeHtml(comment.username)}"
                          style="width: 45px; height: 45px; object-fit: cover;">`;
            } else {
                const initials = comment.username.substring(0, 2).toUpperCase();
                const bgClass = isCurrentUser ? 'bg-success' : 'bg-primary';
                avatarHTML = `<div class="avatar-name rounded-circle shadow-sm ${bgClass}"
                          style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                         <span class='fw-bold text-white' style="font-size: 14px;">${initials}</span>
                      </div>`;
            }

            // Unread badge
            let unreadBadge = '';
            if (comment.is_unread && !isCurrentUser) {
                unreadBadge = `<span class="badge bg-danger ms-2 pulse-animation" style="font-size: 9px;">
                          <i class="fas fa-envelope me-1"></i>NEW
                       </span>`;
            }

            // Attachments HTML
            let attachmentsHTML = '';
            if (comment.attachments && comment.attachments.length > 0) {
                attachmentsHTML = '<div class="message-attachments mt-3 pt-3 border-top"><div class="attachments-grid">';

                comment.attachments.forEach(file => {
                    const ext = file.file_type.toLowerCase();
                    const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    const isImage = imageExts.includes(ext);

                    if (isImage) {
                        attachmentsHTML += `
                    <a href="${escapeHtml(file.file_path)}" class="glightbox attachment-image"
                       data-gallery="message-${comment.id}">
                        <img src="${escapeHtml(file.file_path)}" alt="${escapeHtml(file.file_name)}"
                             style="max-width: 200px; border-radius: 8px;">
                    </a>`;
                    } else {
                        const iconClass = getFileIconClass(ext);
                        attachmentsHTML += `
                    <a href="${escapeHtml(file.file_path)}" class="attachment-file-badge" download>
                        <i class="${iconClass}"></i>
                        <span>${escapeHtml(file.file_name)}</span>
                    </a>`;
                    }
                });

                attachmentsHTML += '</div></div>';
            }

            // Format comment text
            const formattedComment = formatCommentText(comment.comment);

            // Read status ticks
            const readStatus = comment.is_read == 1
                ? '<i class="fas fa-check-double text-primary" title="Read" style="font-size: 10px;"></i>'
                : '<i class="fas fa-check text-muted" title="Delivered" style="font-size: 10px;"></i>';

            return `
    <div class="comment-item mb-3 new-message"
         data-comment-id="${comment.id}"
         data-timestamp="${comment.created_at}"
         ${comment.is_unread ? 'data-unread="true"' : ''}>
        <div class="d-flex ${alignment} align-items-start gap-2">
            <!-- Avatar -->
            <div class="flex-shrink-0">
                <div class="position-relative">
                    ${avatarHTML}
                </div>
            </div>

            <!-- Message Bubble -->
            <div class="flex-grow-1" style="max-width: 75%;">
                <div class="comment-bubble position-relative p-3 shadow-sm ${bubbleColor} border"
                     style="border-radius: ${bubbleRadius}; word-wrap: break-word;">

                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                        <div class="comment-author fw-bold ${textColor}" style="font-size: 13px;">
                            <i class="fas ${userIcon} me-1" style="font-size: 11px;"></i>
                            ${escapeHtml(comment.username)}
                            ${unreadBadge}
                        </div>

                        <div class="d-flex align-items-center flex-shrink-0">
                            <small class="fw-medium text-muted d-flex align-items-center" style="font-size: 11px;">
                                <i class="far fa-clock me-1"></i>
                                ${comment.formatted_date}
                                <span class="ms-2">${readStatus}</span>
                            </small>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="comment-text fs-9">
                        ${formattedComment}
                    </div>

                    <!-- Attachments -->
                    ${attachmentsHTML}
                </div>
            </div>
        </div>
    </div>`;
        }

        function formatCommentText(text) {
            if (!text) return '';

            // Escape HTML
            let formatted = escapeHtml(text);

            // Convert newlines to <br>
            formatted = formatted.replace(/\n/g, '<br>');

            // Convert URLs to links
            formatted = formatted.replace(
                /(https?:\/\/[^\s]+)/g,
                '<a href="$1" target="_blank" class="text-decoration-none fw-medium">$1 <i class="fas fa-external-link-alt" style="font-size: 10px;"></i></a>'
            );

            return formatted;
        }

        function getFileIconClass(ext) {
            const iconMap = {
                'pdf': 'fas fa-file-pdf text-danger',
                'doc': 'fas fa-file-word text-primary',
                'docx': 'fas fa-file-word text-primary',
                'xls': 'fas fa-file-excel text-success',
                'xlsx': 'fas fa-file-excel text-success',
                'txt': 'fas fa-file-alt text-secondary',
                'jpg': 'fas fa-file-image text-info',
                'jpeg': 'fas fa-file-image text-info',
                'png': 'fas fa-file-image text-info',
                'gif': 'fas fa-file-image text-info',
                'webp': 'fas fa-file-image text-info'
            };
            return iconMap[ext] || 'fas fa-file text-muted';
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showNewMessageNotification(count) {
            const message = count === 1 ? '1 new message' : `${count} new messages`;
            if (typeof showToast === 'function') {
                showToast(message, 'info');
            } else {
                console.log('📬', message);
            }
        }

        function markVisibleMessagesAsRead() {
            const unreadMessages = document.querySelectorAll('[data-unread="true"]');

            unreadMessages.forEach(message => {
                const rect = message.getBoundingClientRect();
                const container = document.getElementById('commentsContainer');
                const containerRect = container ? container.getBoundingClientRect() : { top: 0, bottom: window.innerHeight };

                const isVisible = rect.top >= containerRect.top && rect.bottom <= containerRect.bottom;

                if (isVisible) {
                    const commentId = message.dataset.commentId;
                    if (commentId) {
                        // Mark as read via AJAX
                        markMessageAsRead(commentId);
                        message.removeAttribute('data-unread');

                        // Update badge
                        const badge = message.querySelector('.pulse-animation');
                        if (badge) {
                            badge.remove();
                        }
                    }
                }
            });
        }

        function setupMessageReadTracking() {
            const container = document.getElementById('commentsContainer');
            if (!container) return;

            // Use Intersection Observer for efficient viewport detection
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const commentItem = entry.target;
                        const commentId = commentItem.dataset.commentId;
                        const isUnread = commentItem.dataset.unread === 'true';

                        // Only mark if it's unread
                        if (commentId && isUnread) {
                            // Delay to ensure user actually sees it
                            setTimeout(() => {
                                // Check if still visible
                                if (isElementInViewport(commentItem)) {
                                    markSingleMessageAsRead(commentId, commentItem);
                                }
                            }, 1000); // 1 second delay
                        }
                    }
                });
            }, {
                root: container,
                rootMargin: '0px',
                threshold: 0.5 // At least 50% visible
            });

            // Observe all unread messages
            const unreadMessages = container.querySelectorAll('[data-unread="true"]');
            unreadMessages.forEach(msg => observer.observe(msg));

            // Store observer for later use
            window.messageReadObserver = observer;

            console.log('📖 Message read tracking initialized for', unreadMessages.length, 'unread messages');
        }

        function isElementInViewport(el) {
            const rect = el.getBoundingClientRect();
            const container = document.getElementById('commentsContainer');
            const containerRect = container.getBoundingClientRect();

            return (
                rect.top >= containerRect.top &&
                rect.bottom <= containerRect.bottom &&
                rect.left >= containerRect.left &&
                rect.right <= containerRect.right
            );
        }

        function markSingleMessageAsRead(commentId, messageElement) {
            // Don't mark our own messages as read
            if (messageElement.classList.contains('message-sent')) {
                return;
            }

            // Don't re-mark already read messages
            if (messageElement.getAttribute('data-unread') !== 'true') {
                return;
            }

            console.log('📧 Marking message as read:', commentId);

            fetch('mark-comment-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'comment_id=' + commentId
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.marked_read) {
                        console.log('✅ Message marked as read in database:', commentId);

                        // Update UI immediately
                        updateMessageReadUI(messageElement);

                        // Update unread count
                        updateUnreadCount();
                    }
                })
                .catch(error => {
                    console.error('Error marking message as read:', error);
                });
        }

        function updateMessageReadUI(messageElement) {
            // Remove unread attribute
            messageElement.removeAttribute('data-unread');

            // Remove unread badge
            const badge = messageElement.querySelector('.pulse-animation');
            if (badge) {
                badge.style.transition = 'opacity 0.3s ease';
                badge.style.opacity = '0';
                setTimeout(() => badge.remove(), 300);
            }

            // Update read status icon
            const statusIcon = messageElement.querySelector('.fa-check');
            if (statusIcon) {
                statusIcon.classList.remove('fa-check', 'text-muted');
                statusIcon.classList.add('fa-check-double', 'text-primary');
                statusIcon.title = 'Read';
            }

            // Remove any highlight/glow effect
            messageElement.classList.remove('unread-message-glow');
            messageElement.style.animation = 'none';
        }

        function updateUnreadCount() {
            // Only count unread messages that are NOT from the current user
            const allMessages = document.querySelectorAll('[data-comment-id]');
            let count = 0;

            allMessages.forEach(msg => {
                const isUnread = msg.getAttribute('data-unread') === 'true';
                const isMine = msg.classList.contains('message-sent'); // Messages sent by current user

                // Only count unread messages from others
                if (isUnread && !isMine) {
                    count++;
                }
            });

            // Update any unread badges in the UI
            const unreadBadges = document.querySelectorAll('.unread-count-badge, .badge.bg-danger');
            unreadBadges.forEach(badge => {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            });

            console.log('📊 Unread count updated:', count);
        }

        function markAllVisibleAsRead() {
            const container = document.getElementById('commentsContainer');
            if (!container) return;

            const unreadMessages = container.querySelectorAll('[data-unread="true"]');
            let markedCount = 0;

            unreadMessages.forEach(messageElement => {
                if (isElementInViewport(messageElement)) {
                    const commentId = messageElement.dataset.commentId;
                    if (commentId) {
                        markSingleMessageAsRead(commentId, messageElement);
                        markedCount++;
                    }
                }
            });

            if (markedCount > 0) {
                console.log(`✅ Marked ${markedCount} visible messages as read`);
            }
        }

        let scrollTimeout;
        function handleCommentsScroll() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                markAllVisibleAsRead();
            }, 500); // Wait 500ms after scrolling stops
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Setup read tracking
            setupMessageReadTracking();

            // Add scroll listener
            const container = document.getElementById('commentsContainer');
            if (container) {
                container.addEventListener('scroll', handleCommentsScroll);
            }

            // Mark visible messages after page loads
            setTimeout(() => {
                markAllVisibleAsRead();
            }, 2000); // 2 second delay after page load

            console.log('✅ Message read tracking system initialized');
        });

        function reinitializeReadTracking() {
            if (window.messageReadObserver) {
                // Observe new unread messages
                const container = document.getElementById('commentsContainer');
                const unreadMessages = container.querySelectorAll('[data-unread="true"]');

                unreadMessages.forEach(msg => {
                    window.messageReadObserver.observe(msg);
                });

                console.log('🔄 Read tracking reinitialized for new messages');
            }
        }

        window.reinitializeReadTracking = reinitializeReadTracking;

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopPolling();
                console.log('⏸️ Auto-update paused (tab hidden)');
            } else {
                startPolling();
                console.log('▶️ Auto-update resumed');
                // Check immediately when tab becomes visible
                checkForNewComments();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit for page to fully load
            setTimeout(() => {
                initializePolling();
            }, 1000);
        });

        window.addEventListener('beforeunload', function() {
            stopPolling();
        });

        // Add CSS animation for new messages
        if (!document.getElementById('polling-animations')) {
            const style = document.createElement('style');
            style.id = 'polling-animations';
            style.textContent = `
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .new-message {
            animation: slideInUp 0.3s ease;
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.6;
            }
        }
    `;
            document.head.appendChild(style);
        }

    </script>
    <script>
        // Delete file variables
        let deleteFileId = null;
        let deleteFileType = null;

        function confirmDeleteFile(fileId, fileName, fileDate, fileType) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            deleteFileId = fileId;
            deleteFileType = fileType;
            document.getElementById('deleteFileName').textContent = fileName;
            document.getElementById('deleteFileDate').textContent = fileDate;

            var deleteModalEl = document.getElementById('deleteFileModal');
            if (deleteModalEl) {
                var deleteModal = new bootstrap.Modal(deleteModalEl);
                deleteModal.show();
            }

            return false;
        }

        function deleteFile() {
            if (event) {
                event.preventDefault();
            }

            if (!deleteFileId) {
                showToast('No file selected', 'warning');
                return false;
            }

            const deleteBtn = document.getElementById('confirmDeleteBtn');
            const originalText = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...';
            deleteBtn.disabled = true;

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'delete-task-file', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    var modalEl = document.getElementById('deleteFileModal');
                    var modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            if (data.success) {
                                showToast(data.message || 'File deleted successfully', 'success');
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            } else {
                                showToast(data.message || 'Failed to delete file', 'danger');
                                deleteBtn.innerHTML = originalText;
                                deleteBtn.disabled = false;
                            }
                        } catch (e) {
                            showToast('Error parsing server response', 'danger');
                            deleteBtn.innerHTML = originalText;
                            deleteBtn.disabled = false;
                        }
                    } else {
                        showToast('Server error: ' + xhr.status, 'danger');
                        deleteBtn.innerHTML = originalText;
                        deleteBtn.disabled = false;
                    }
                }
            };

            xhr.onerror = function() {
                showToast('Network error occurred', 'danger');
                deleteBtn.innerHTML = originalText;
                deleteBtn.disabled = false;
            };

            const params = 'file_id=' + encodeURIComponent(deleteFileId) +
                '&file_type=' + encodeURIComponent(deleteFileType) +
                '&task_id=' + encodeURIComponent('<?php echo $taskId; ?>');

            xhr.send(params);

            return false;
        }
    </script>
    <script>
        function copyTaskUrl() {
            const taskUrl = window.location.href;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(taskUrl).then(function() {
                    showToast('Task link copied to clipboard!', 'success');
                }).catch(function(err) {
                    fallbackCopyToClipboard(taskUrl);
                });
            } else {
                fallbackCopyToClipboard(taskUrl);
            }
        }

        function fallbackCopyToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();

            try {
                document.execCommand('copy');
                showToast('Task link copied to clipboard!', 'success');
            } catch (err) {
                showToast('Failed to copy link. Please copy manually: ' + text, 'warning');
            }

            document.body.removeChild(textArea);
        }

        function copyTaskShareLink(taskId, taskTopic) {
            fetch('../share/generate-share-link?task_id=' + taskId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(data.shareUrl)
                                .then(() => {
                                    showToast('Share link copied to clipboard!', 'success');
                                })
                                .catch(err => {
                                    prompt('Copy this share link:', data.shareUrl);
                                    showToast('Please copy the link manually', 'info');
                                });
                        } else {
                            prompt('Copy this share link:', data.shareUrl);
                            showToast('Link generated successfully', 'info');
                        }
                    } else {
                        showToast('Error: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    showToast('Failed to generate share link. Please try again.', 'danger');
                });
        }
    </script>
    <script>
        (function () {
            let selectedWriterUsername = null;
            let selectedWriterEmail = null;

            document.querySelectorAll('.writer-select-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.writer-select-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    selectedWriterUsername = this.dataset.writerUsername;
                    selectedWriterEmail = this.dataset.writerEmail;
                    document.getElementById('confirmEditWriterBtn').disabled = false;
                });
            });

            document.getElementById('confirmEditWriterBtn').addEventListener('click', function () {
                if (!selectedWriterUsername) return;

                const btn = this;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
                btn.disabled = true;

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'update-task-writer', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            if (data.success) {
                                showToast(data.message || 'Writer updated successfully!', 'success');
                                setTimeout(function () { location.reload(); }, 1500);
                            } else {
                                showToast(data.message || 'Failed to update writer.', 'danger');
                                btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Writer';
                                btn.disabled = false;
                            }
                        } catch (e) {
                            showToast('Unexpected server response.', 'danger');
                            btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Writer';
                            btn.disabled = false;
                        }
                    }
                };

                xhr.onerror = function () {
                    showToast('Network error occurred.', 'danger');
                    btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Writer';
                    btn.disabled = false;
                };

                xhr.send(
                    'task_id=' + encodeURIComponent('<?php echo $taskId; ?>') +
                    '&writer=' + encodeURIComponent(selectedWriterUsername) +
                    '&email=' + encodeURIComponent(selectedWriterEmail)
                );
            });
        })();
    </script>
<?php echo getShareLinkJavaScript(); ?>

<?php
include "footer.php";
?>