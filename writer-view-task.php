<?php
include "head.php";
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

    $query = "SELECT * FROM tbltasks WHERE id = ? AND email = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("is", $taskId, $aid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
                                <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
                                <p class="mb-0 flex-1">Task not found or access denied!</p>
                                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
        header("Location: all-tasks");
        exit();
    }
} else {
    $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
                            <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
                            <p class="mb-0 flex-1">Invalid task ID!</p>
                            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
    header("Location: all-tasks");
    exit();
}

$taskTopic = $taskSubject = $taskAccount = $taskCreatedOn = $taskStatus = $taskIsPaid = $taskIsConfirmed = $taskDescription = $taskWriter = $taskWriterEmail = $taskDueDate = $taskCPP = $taskPages = $existingFiles = $taskSubmitTime = $submittedOn = $completedOn = '';

$sql2 = "SELECT * FROM tbltasks WHERE id='$taskId'";
$result = mysqli_query($con, $sql2);

if ($rowTask = mysqli_fetch_array($result)) {
    $id = base64_encode($rowTask["id"]);
    $taskTopic = $rowTask["topic"];
    $taskSubject = $rowTask["subject"];
    $taskAccount = $rowTask["account"];
    $taskCreatedOn = $rowTask["create_date"];
    $taskStatus = $rowTask["status"];
    $taskIsPaid = $rowTask["is_paid"];
    $taskIsConfirmed = $rowTask["is_confirmed"];
    $taskDescription = $rowTask["description"];
    $taskWriter = $rowTask["writer"];
    $taskWriterEmail = $rowTask["email"];
    $taskDueDate = $rowTask["due_date"];
    $taskCPP = $rowTask["cpp"];
    $taskPages = $rowTask["pages"];
    $existingFiles = $rowTask['task_files'];
    $submittedFiles = $rowTask['submitted_files'];
    $taskSubmitTime = $rowTask['submitted_on'];
    $submittedOn = $rowTask['submitted_on'];
    $completedOn = $rowTask['completed_on'];
}
$due_date = new DateTime($rowTask['due_date']);
$currentDateTime = new DateTime();
$interval = $currentDateTime->diff($due_date);
$isLate = ($due_date < $currentDateTime) ? true : false;
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
$is_paid = $rowTask['is_paid'];

$statusBadgeClass = ($is_paid == 1) ? 'bg-success' : 'bg-warning';
$statusBadgeText = ($is_paid == 1) ? 'Paid' : 'Unpaid';
$statusBadgePay = "<span class='badge $statusBadgeClass'>$statusBadgeText</span>";

$is_confirmed = $rowTask['is_confirmed'];
$confirmationClass = ($is_confirmed == 0) ? 'bg-light' : 'bg-primary';
$confirmationText = ($is_confirmed == 0) ? 'Confirmed' : 'Unconfirmed';
$confirmation = "<span class='badge $confirmationClass'>$confirmationText</span>";

?>
<?php
// Determine current user type and ID
$currentUserType = 'writer'; // default
$currentUserId = null;

// Check if user is admin
$adminCheckQuery = "SELECT id FROM tblwriters WHERE email = '" . mysqli_real_escape_string($con, $_SESSION['sessionWriter']) . "'";
$adminResult = mysqli_query($con, $adminCheckQuery);

if ($adminResult && mysqli_num_rows($adminResult) > 0) {
    $currentUserType = 'writer';
    $adminData = mysqli_fetch_assoc($adminResult);
    $currentUserId = $adminData['id'];
} else {
    // Check if user is admin
    $writerCheckQuery = "SELECT id FROM tbladmin WHERE email = '" . mysqli_real_escape_string($con, $_SESSION['sessionWriter']) . "'";
    $writerResult = mysqli_query($con, $writerCheckQuery);

    if ($writerResult && mysqli_num_rows($writerResult) > 0) {
        $currentUserType = 'admin';
        $writerData = mysqli_fetch_assoc($writerResult);
        $currentUserId = $writerData['id'];
    }
}

// Determine receiver based on sender type
if ($currentUserType === 'admin') {
    // Admin sends to writer assigned to this task
    // Get writer ID from task
    $writerIdQuery = "SELECT id FROM tblwriters WHERE email = '" . mysqli_real_escape_string($con, $taskWriterEmail) . "'";
    $writerIdResult = mysqli_query($con, $writerIdQuery);

    if ($writerIdResult && mysqli_num_rows($writerIdResult) > 0) {
        $writerIdData = mysqli_fetch_assoc($writerIdResult);
        $receiverId = $writerIdData['id'];
    } else {
        $receiverId = 0; // Fallback
    }
    $receiverType = 'writer';
} else {
    // Writer sends to admin
    // Get the first admin or specific admin for this task
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

// Calculate unread messages
$unreadCount = 0;
$firstUnreadIndex = -1;
$currentUserType = isset($_SESSION['odmsaid']) ? 'admin' : 'writer';

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
    $lastCommentTime = strtotime($lastComment['created_at']);
    $timeDiff = time() - $lastCommentTime;

    if ($timeDiff > 86400) { // More than 24 hours
        $conversationStatus = 'Quiet';
        $statusIcon = 'fa-circle';
        $statusClass = 'text-warning';
    }
}
?>
    <title>View Task #<?php  echo $taskId;?> | iTasker</title>
<?php include "navi.php";?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">View <span class="text-info fw-medium">Task Details</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0">
                    <form class="row flex-lg-column flex-xxl-row gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
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
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message'], ENT_QUOTES, 'UTF-8');
    echo "
        <div class='alert alert-success border-0 d-flex align-items-center' role='alert'>
        <div class='bg-success me-3 icon-item'><span class='fas fa-check-circle text-white fs-6'></span></div>
        <p class='mb-0 flex-1'>$message</p>
        <button class='btn-close' type='button' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>
        <script>
        // Remove the message parameter from URL after displaying
        if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.delete('message');
            window.history.replaceState({}, document.title, url.toString());
        }
        
        // Auto-hide alert after 5 seconds
        setTimeout(function() {
            var alertElement = document.querySelector('.alert-success');
            if (alertElement) {
                alertElement.classList.add('fade');
                alertElement.addEventListener('transitionend', function() {
                    alertElement.remove();
                });
            }
        }, 5000);
        </script>
        ";
}
?>

    <div class="card mb-3">
        <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-5.png);">
        </div>

        <div class="card-body position-relative">
            <div class="row g-2 align-items-sm-center">
                <div class="col-auto"><div class="calendar me-2">
                            <span class="calendar-month">
                                <?php
                                $currentMonth = date('M');
                                $currentDay = date('d');
                                echo $currentMonth;?>
                            </span>
                        <span class="calendar-day"><?php echo $currentDay; ?> </span>
                    </div>
                </div>
                <div class="col">
                    <div class="row align-items-center">
                        <div class="col col-lg-8">
                            <h5 class="mb-sm-0 text-primary fs-7">Task ID: <span class="text-info fw-medium">#<?php  echo $taskId;?></span></h5>
                            <p class="fw-semi-bold fs-10"><span class="me-1">Posted: </span><span class="text-info ms-2"><?php  echo date("d M Y, g:i A", strtotime($taskCreatedOn));?></span>
                            </p>
                            <div class="fs-9 mb-3 mb-sm-0 text-primary">
                                <strong class="me-2">Status: </strong><?php echo $statusBadge; ?>
                                <?php if ($taskStatus == 'Submitted' && !empty($submittedOn)): ?>
                                    <span class="fs-10 text-info ms-2"><?php echo date("d M Y, g:i A", strtotime($submittedOn)); ?></span>
                                <?php elseif ($taskStatus == 'Completed' && !empty($completedOn)): ?>
                                    <span class="fs-10 text-success ms-2"><?php echo date("d M Y, g:i A", strtotime($completedOn)); ?></span>
                                <?php endif; ?>
                                <?php if ($is_confirmed == 1): ?>
                                    <?php echo $confirmation; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-12 col-sm-auto ms-auto">
                            <?php if ($taskStatus == 'In Progress'): ?>
                                <a class="btn btn-outline-primary btn-lg fs-9" href="submission?task_id=<?php $encodedId = $_GET['task_id']; echo $encodedId; ?>#filesSubmission">
                                    <i class="fas fa-upload me-1"></i> Submit Task
                                </a>
                            <?php elseif ($is_confirmed == 1 && $taskStatus != 'Cancelled'): ?>
                                <a class="btn btn-outline-success btn-sm fs-10" href="#" onclick="confirmAction('<?php $encodedId = $_GET['task_id']; echo $encodedId; ?>', 'accept')">
                                    <i class="fas fa-check me-1"></i> Accept Task
                                </a>
                                <a class="btn btn-outline-danger btn-sm fs-10" href="#" onclick="confirmAction('<?php $encodedId = $_GET['task_id']; echo $encodedId; ?>', 'decline')">
                                    <i class="fas fa-times me-1"></i> Decline Task
                                </a>
                            <?php elseif ($taskStatus == 'Submitted' || $taskStatus == 'In Revision'): ?>
                                <a class="btn btn-outline-primary btn-lg fs-9" href="#" onclick="confirmAction('<?php $encodedId = $_GET['task_id']; echo $encodedId; ?>', 'resubmit')">
                                    <i class="fas fa-sync-alt me-1"></i> Resubmit Task
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
$due_date_obj = new DateTime($rowTask['due_date']);
$currentDateTime_obj = new DateTime();
$isLate_card = ($due_date_obj < $currentDateTime_obj);
$remainingSeconds_card = $isLate_card ? 0 : $due_date_obj->getTimestamp() - $currentDateTime_obj->getTimestamp();
$totalCost = $taskPages * $taskCPP;

// Determine urgency level for progress ring
$totalDuration = 1;
$elapsed = 1;
if (!$isLate_card && $remainingSeconds_card > 0) {
    // Assume task was created and due, estimate progress from creation
    $createdTs = strtotime($taskCreatedOn);
    $dueTs = $due_date_obj->getTimestamp();
    $nowTs = $currentDateTime_obj->getTimestamp();
    $totalDuration = max(1, $dueTs - $createdTs);
    $elapsed = $nowTs - $createdTs;
}
$progressPct = min(100, max(0, ($elapsed / $totalDuration) * 100));
$ringColor = $progressPct < 50 ? '#22c55e' : ($progressPct < 80 ? '#f59e0b' : '#ef4444');

if ($rowTask['status'] == 'Completed') {
    $timeDiff_card = "<span class='fw-bold'>Completed</span>";
    $ringColor = '#22c55e'; $progressPct = 100;
} elseif ($rowTask['status'] == 'Cancelled') {
    $timeDiff_card = "<span class='fw-bold'>Cancelled</span>";
    $ringColor = '#6b7280'; $progressPct = 100;
} elseif ($rowTask['status'] == 'Submitted') {
    $timeDiff_card = "<span class='fw-bold'>Submitted</span>";
    $ringColor = '#3b82f6'; $progressPct = 100;
} elseif ($rowTask['is_confirmed'] == 2) {
    $timeDiff_card = "<span class='fw-bold'>Declined</span>";
    $ringColor = '#ef4444'; $progressPct = 100;
} elseif ($isLate_card) {
    $timeDiff_card = "<span id='time-remaining-card' class='fw-bold' style='color:#ef4444;'>Past Due</span>";
    $ringColor = '#ef4444'; $progressPct = 100;
} else {
    $timeDiff_card = "<span id='time-remaining-card' class='fw-bold'></span>";
}
$circumference = 2 * M_PI * 42; // r=42
$dashOffset = $circumference * (1 - $progressPct / 100);
?>

    <style>
        .task-details-card {
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            overflow: hidden;
            position: relative;
        }
        .task-details-card::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 220px; height: 220px;
            background: radial-gradient(circle, rgba(59,130,246,0.15) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .task-details-card::after {
            content: '';
            position: absolute;
            bottom: -40px; left: -40px;
            width: 160px; height: 160px;
            background: radial-gradient(circle, rgba(168,85,247,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .task-subject-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2.5px;
            text-transform: uppercase;
        }
        .task-topic-title {
            font-size: clamp(1.2rem, 3vw, 1.7rem);
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }
        .task-stat-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            border-radius: 12px;
            padding: 10px 16px;
            transition: background 0.2s;
        }
        .task-stat-pill .stat-icon {
            width: 30px; height: 30px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }
        .task-stat-pill .stat-label {
            font-size: 10px; font-weight: 600;
            letter-spacing: 1px; text-transform: uppercase;
            line-height: 1;
        }
        .task-stat-pill .stat-value {
            font-size: 13px; font-weight: 700;
            line-height: 1;
        }
        .cost-highlight {
            background: linear-gradient(135deg, rgba(34,197,94,0.15) 0%, rgba(16,185,129,0.1) 100%);
            border: 1px solid rgba(34,197,94,0.25);
            border-radius: 14px;
            padding: 12px 20px;
        }
        .cost-amount {
            font-weight: 900;
            color: #4ade80;
            letter-spacing: -1px;
            line-height: 1;
        }
        .due-section {
            border-radius: 14px;
            padding: 12px 16px;
        }
        .time-ring-wrap { position: relative; width: 100px; height: 100px; flex-shrink: 0; }
        .time-ring-wrap svg { transform: rotate(-90deg); }
        .time-ring-center {
            position: absolute; inset: 0;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 4px;
            text-align: center;
            overflow: hidden;
        }
        .time-ring-center .ring-label { font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(148,163,184,0.6); line-height: 1; margin-bottom: 3px; }
        .time-ring-center #time-remaining-card,
        .time-ring-center span { font-size: 10px !important; font-weight: 700; line-height: 1.2; max-width: 72px; word-break: break-word; display: block; }
        @keyframes ring-overdue-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.35; }
        }
        .ring-overdue { animation: ring-overdue-pulse 1.2s ease-in-out infinite; }
        .copy-btn { cursor: pointer; opacity: 0.5; transition: opacity 0.2s; font-size: 11px; }
        .copy-btn:hover { opacity: 1; }
    </style>

    <div class="card task-details-card mb-3">
        <div class="card-body p-4">
            <div class="row g-4 align-items-start">

                <!-- Left: Topic, subject, time ring -->
                <div class="col-lg-9">
                    <div class="d-flex align-items-start gap-4">
                        <!-- Progress / Time Ring -->
                        <div class="time-ring-wrap d-none d-sm-block">
                            <svg viewBox="0 0 100 100" width="100" height="100" class="<?php echo $isLate_card && !in_array($rowTask['status'], ['Completed','Cancelled','Submitted']) ? 'ring-overdue' : ''; ?>">
                                <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="7"/>
                                <circle cx="50" cy="50" r="42" fill="none"
                                        stroke="<?php echo $ringColor; ?>"
                                        stroke-width="7"
                                        stroke-linecap="round"
                                        stroke-dasharray="<?php echo $circumference; ?>"
                                        stroke-dashoffset="<?php echo $dashOffset; ?>"
                                        style="transition: stroke-dashoffset 1s ease;"/>
                                <?php if ($isLate_card && !in_array($rowTask['status'], ['Completed','Cancelled','Submitted'])): ?>
                                    <!-- Overdue X mark in center of SVG -->
                                    <g transform="translate(50,50)" style="transform-origin:center;">
                                        <line x1="-8" y1="-8" x2="8" y2="8" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                                        <line x1="8" y1="-8" x2="-8" y2="8" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
                                    </g>
                                <?php endif; ?>
                            </svg>
                            <div class="time-ring-center">
                                <span class="ring-label"><?php echo $isLate_card && !in_array($rowTask['status'], ['Completed','Cancelled','Submitted']) ? 'Overdue' : 'Time'; ?></span>
                                <?php echo $timeDiff_card; ?>
                            </div>
                        </div>

                        <div class="flex-grow-1">
                            <div class="task-subject-label mb-1">
                                <i class="fas fa-book me-1" style="color:#64748b;"></i>
                                <?php echo htmlspecialchars($taskSubject); ?>
                            </div>
                            <div class="task-topic-title mb-3"><?php echo htmlspecialchars($taskTopic); ?></div>

                            <!-- Due Date -->
                            <div class="due-section d-flex align-items-center gap-3 mb-3">

                                <div>
                                    <div class="task-subject-label mb-1">Due Date</div>
                                    <div style="font-size:13px; font-weight:600; color:#94a3b8;">
                                        <i class="far fa-calendar-alt me-1 text-info"></i>
                                        <?php echo date("D, d M Y", strtotime($taskDueDate)); ?>
                                        <span class="mx-1 text-muted">·</span>
                                        <i class="far fa-clock me-1 text-info"></i>
                                        <?php echo date("g:i A", strtotime($taskDueDate)); ?>
                                    </div>
                                </div>
                                <?php if ($isLate_card && !in_array($taskStatus, ['Completed','Cancelled','Submitted'])): ?>
                                    <span class="badge" style="background:rgba(239,68,68,0.2); color:#fca5a5; border:1px solid rgba(239,68,68,0.3); font-size:10px; font-weight:700; letter-spacing:1px;">
                                    OVERDUE
                                </span>
                                <?php endif; ?>
                            </div>

                            <!-- Mobile timer -->
                            <div class="d-sm-none mb-2" style="font-size:13px; color:#94a3b8;">
                                <i class="far fa-clock me-1"></i><span id="time-remaining-card-mobile"><?php
                                    if (in_array($rowTask['status'], ['Completed','Cancelled','Submitted'])) {
                                        echo $rowTask['status'];
                                    } elseif ($rowTask['is_confirmed'] == 2) {
                                        echo 'Declined';
                                    } elseif ($isLate_card) {
                                        echo 'Past Due';
                                    }
                                    ?></span>
                            </div>

                            <!-- Payment status -->
                            <?php if ($taskStatus == 'Completed'): ?>
                                <?php if ($is_paid == 0): ?>
                                    <span class="badge" style="background:rgba(245,158,11,0.15); color:#fcd34d; border:1px solid rgba(245,158,11,0.3); padding:6px 14px; font-size:11px;">
                                        <i class="fas fa-exclamation-circle me-1"></i>Unpaid
                                    </span>
                                <?php else: ?>
                                    <?php $paidOn = $rowTask['paid_on']; ?>
                                    <span class="badge" style="background:rgba(34,197,94,0.15); color:#4ade80; border:1px solid rgba(34,197,94,0.3); padding:6px 14px; font-size:11px;">
                                        <i class="fas fa-check-circle me-1"></i>Paid · <?php echo date("d M Y", strtotime($paidOn)); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right: Stats & cost -->
                <div class="col-lg-3">
                    <!-- Stat pills grid -->
                    <div class="mb-3  d-flex flex-column gap-2">
                        <div class="d-flex gap-2">
                            <div class="task-stat-pill flex-grow-1">
                                <div class="stat-icon" style="background:rgba(59,130,246,0.2);">
                                    <i class="fas fa-file-alt" style="color:#93c5fd;"></i>
                                </div>
                                <div>
                                    <div class="stat-label">Pages</div>
                                    <div class="stat-value"><?php echo $taskPages; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Total Cost highlight -->
                    <div class="cost-highlight d-flex align-items-center justify-content-between">
                        <div>
                            <div>
                                <div class="fs-10">CPP (Ksh): <?php echo number_format($taskCPP, 2); ?></div>
                            </div>
                            <div class="stat-label mb-1">Total</div>
                            <div class="cost-amount">Ksh <?php echo number_format($totalCost, 2); ?></div>
                        </div>
                        <div style="opacity:0.4; font-size:2rem;">
                            <i class="fas fa-coins" style="color:#4ade80;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Live countdown for the task detail card ring + mobile fallback
        (function() {
            const dueTs = <?php echo $due_date_obj->getTimestamp(); ?> * 1000;
            const isLate = <?php echo $isLate_card ? 'true' : 'false'; ?>;
            const status = <?php echo json_encode($rowTask['status']); ?>;
            const finalStatuses = ['Completed','Cancelled','Submitted'];

            if (finalStatuses.includes(status) || isLate) return;

            function fmt(ms) {
                if (ms <= 0) return 'Past Due';
                const s = Math.floor(ms / 1000);
                const d = Math.floor(s / 86400);
                const h = Math.floor((s % 86400) / 3600);
                const m = Math.floor((s % 3600) / 60);
                const sec = s % 60;
                if (d > 0) return d + 'd ' + h + 'h ' + m + 'm';
                if (h > 0) return h + 'h ' + m + 'm ' + sec + 's';
                return m + 'm ' + sec + 's';
            }

            function applyColor(el, remaining) {
                if (remaining < 3600000) { el.style.color = '#ef4444'; }
                else if (remaining < 86400000) { el.style.color = '#f59e0b'; }
                else { el.style.color = '#4ade80'; }
            }

            function tick() {
                const remaining = dueTs - Date.now();
                const text = fmt(remaining);

                // Ring element
                const ringEl = document.getElementById('time-remaining-card');
                if (ringEl) { ringEl.textContent = text; applyColor(ringEl, remaining); }

                // Mobile element
                const mobileEl = document.getElementById('time-remaining-card-mobile');
                if (mobileEl) { mobileEl.textContent = text; applyColor(mobileEl, remaining); }
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
    <div class="row ">
        <div class="col-lg-12 order-1 order-lg-0">
            <div class="card mb-3">
                <div class='card-header d-flex bg-body-tertiary align-items-center'>
                    <i class='fas fa-sliders-h me-2 text-primary'></i>
                    <h6 class='mb-0'>Description</h6>
                </div>
                <div class="card-body position-relative">
                    <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(assets/img/icons/spot-illustrations/corner-1.png);">
                    </div>
                    <ul class="list-unstyled position-relative fs-9 p-0 m-0">
                        <li class="mb-2">
                            <div class="d-flex">
                                <dd class="task-description-content">
                                    <?php
                                    $cleanText = stripslashes($taskDescription);
                                    $pattern = '/(https?:\/\/[^\s]+)/';
                                    $replacement = '<a href="$1" class="highlighted-link" target="_blank">$1</a>';
                                    $formattedText = preg_replace($pattern, $replacement, $cleanText);
                                    echo $formattedText;
                                    ?>
                                </dd>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

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
                             style='background-image:url(assets/img/icons/spot-illustrations/corner-2.png);'>
                        </div>
                        <?php
                        $taskFilesQuery = 'SELECT * FROM tbl_task_files WHERE task_id = ? AND file_type = "task" AND is_deleted = 0 ORDER BY upload_time ASC';
                        $stmt = mysqli_prepare($con, $taskFilesQuery);
                        mysqli_stmt_bind_param($stmt, 'i', $taskId);
                        mysqli_stmt_execute($stmt);
                        $taskFilesResult = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($taskFilesResult) > 0) {
                            while ($fileRow = mysqli_fetch_assoc($taskFilesResult)) {
                                $fileName = $fileRow['original_file_name'];
                                $fileUrl = $fileRow['file_url'];
                                $fileSize = $fileRow['file_size'];
                                $uploadTime = $fileRow['upload_time'];
                                $formattedDate = date('d M Y, g:i A', strtotime($uploadTime));

                                $formattedSize = 'Unknown size';
                                if ($fileSize > 0) {
                                    $units = ['B', 'KB', 'MB', 'GB'];
                                    $power = $fileSize > 0 ? floor(log($fileSize, 1024)) : 0;
                                    $formattedSize = round($fileSize / pow(1024, $power), 2) . ' ' . $units[$power];
                                }

                                $thumbnailPath = 'assets/img/icons/docs.png';
                                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                                switch (strtolower($fileExtension)) {
                                    case 'pdf':
                                        $thumbnailPath = 'assets/img/icons/pdf.png';
                                        break;
                                    case 'doc';
                                    case 'docx':
                                    case 'rtf':
                                        $thumbnailPath = 'assets/img/icons/word.png';
                                        break;
                                    case 'xls':
                                    case 'xlsx':
                                    case 'csv':
                                        $thumbnailPath = 'assets/img/icons/excel.png';
                                        break;
                                    case 'ppt':
                                    case 'pptx':
                                        $thumbnailPath = 'assets/img/icons/powerpoint.png';
                                        break;
                                    case 'jpg':
                                    case 'jpeg':
                                    case 'png':
                                    case 'gif':
                                        $thumbnailPath = 'assets/img/icons/image.png';
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
                                        $thumbnailPath = 'assets/img/icons/video.png';
                                        break;
                                    case 'zip':
                                    case 'rar':
                                        $thumbnailPath = 'assets/img/icons/zip.png';
                                        break;
                                    default:
                                        $thumbnailPath = 'assets/img/icons/docs.png';
                                        break;
                                }
                                ?>
                                <div class="d-flex mb-3 hover-actions-trigger align-items-center">
                                    <div class="file-thumbnail"><img
                                                class="border h-100 w-100 object-fit-cover rounded-2"
                                                src="<?php echo $thumbnailPath; ?>" alt=""/></div>
                                    <div class="ms-3 flex-shrink-1 flex-g$rowTask-1">
                                        <h6 class="mb-1"><a class="stretched-link text-900 fw-semi-bold"
                                                            href="<?php echo $fileUrl; ?>"
                                                            target="_blank"><?php echo $fileName; ?></a></h6>
                                        <div class="fs-10">
                                            <span class='fw-medium text-600'><?php echo $formattedSize; ?></span>
                                            <span class='fw-medium text-600 mx-1'>•</span>
                                            <span class="fw-medium text-600"><?php echo $formattedDate; ?></span>
                                        </div>
                                        <div class="hover-actions end-0 top-50 translate-middle-y">
                                            <a class="btn btn-tertiary border-300 btn-sm me-1 text-600"
                                               data-bs-toggle="tooltip" data-bs-placement="top" title="Download"
                                               href="<?php echo $fileUrl; ?>" download="<?php echo $fileName; ?>"><img
                                                        src="assets/img/icons/cloud-download.svg" alt=""
                                                        width="15"/></a>
                                        </div>
                                    </div>
                                </div>
                                <hr class="text-200"/>
                                <?php
                            }
                        } else {
                            echo '<div>No task files attached.</div>';
                        }
                        mysqli_stmt_close($stmt);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                             style='background-image:url(assets/img/icons/spot-illustrations/corner-7.png);'>
                        </div>
                        <?php
                        $submittedFilesQuery = 'SELECT * FROM tbl_task_files WHERE task_id = ? AND file_type = "submitted" AND is_deleted = 0 ORDER BY upload_time ASC';
                        $stmt = mysqli_prepare($con, $submittedFilesQuery);
                        mysqli_stmt_bind_param($stmt, 'i', $taskId);
                        mysqli_stmt_execute($stmt);
                        $submittedFilesResult = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($submittedFilesResult) > 0) {
                            while ($fileRow = mysqli_fetch_assoc($submittedFilesResult)) {
                                $fileName = $fileRow['original_file_name'];
                                $fileUrl = $fileRow['file_url'];
                                $fileSize = $fileRow['file_size'];
                                $uploadTime = $fileRow['upload_time'];
                                $formattedDate = date('d M Y, g:i A', strtotime($uploadTime));

                                $formattedSize = 'Unknown size';
                                if ($fileSize > 0) {
                                    $units = ['B', 'KB', 'MB', 'GB'];
                                    $power = $fileSize > 0 ? floor(log($fileSize, 1024)) : 0;
                                    $formattedSize = round($fileSize / pow(1024, $power), 2) . ' ' . $units[$power];
                                }

                                $thumbnailPath = 'assets/img/icons/docs.png';
                                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                                switch (strtolower($fileExtension)) {
                                    case 'pdf':
                                        $thumbnailPath = 'assets/img/icons/pdf.png';
                                        break;
                                    case 'doc';
                                    case 'docx':
                                    case 'rtf':
                                        $thumbnailPath = 'assets/img/icons/word.png';
                                        break;
                                    case 'xls':
                                    case 'xlsx':
                                    case 'csv':
                                        $thumbnailPath = 'assets/img/icons/excel.png';
                                        break;
                                    case 'ppt':
                                    case 'pptx':
                                        $thumbnailPath = 'assets/img/icons/powerpoint.png';
                                        break;
                                    case 'jpg':
                                    case 'jpeg':
                                    case 'png':
                                    case 'gif':
                                        $thumbnailPath = 'assets/img/icons/image.png';
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
                                        $thumbnailPath = 'assets/img/icons/video.png';
                                        break;
                                    case 'zip':
                                    case 'rar':
                                        $thumbnailPath = 'assets/img/icons/zip.png';
                                        break;
                                    default:
                                        $thumbnailPath = 'assets/img/icons/docs.png';
                                        break;
                                }
                                ?>
                                <div class="d-flex mb-3 hover-actions-trigger align-items-center">
                                    <div class="file-thumbnail"><img
                                                class="border h-100 w-100 object-fit-cover rounded-2"
                                                src="<?php echo $thumbnailPath; ?>" alt=""/></div>
                                    <div class="ms-3 flex-shrink-1 flex-grow-1">
                                        <h6 class="mb-1"><a class="stretched-link text-900 fw-semi-bold"
                                                            href="<?php echo $fileUrl; ?>"
                                                            target="_blank"><?php echo $fileName; ?></a></h6>
                                        <div class="fs-10">
                                            <span class='fw-medium text-600'><?php echo $formattedSize; ?>
                                                <span class='fw-medium text-600 mx-1'>•</span>
                                                <span class="fw-medium text-600"><?php echo $formattedDate; ?></span>
                                        </div>
                                        <div class="hover-actions end-0 top-50 translate-middle-y">
                                            <a class="btn btn-tertiary border-300 btn-sm me-1 text-600"
                                               data-bs-toggle="tooltip" data-bs-placement="top" title="Download"
                                               href="<?php echo $fileUrl; ?>" download="<?php echo $fileName; ?>"><img
                                                        src="assets/img/icons/cloud-download.svg" alt=""
                                                        width="15"/></a>
                                        </div>
                                    </div>
                                </div>
                                <hr class="text-200"/>
                                <?php
                            }
                        } else {
                            echo '<div>No submitted files.</div>';
                        }
                        mysqli_stmt_close($stmt);
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

// Count unread messages (assuming writer is viewing - count unread writer messages)
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
<?php $hasMessages = !empty($comments); ?>
    <div class='row'>
        <div class='col-md-12 col-xxl-12 mb-3'>
            <div class='card shadow-sm border-0 overflow-hidden h-100' style='border-radius: 15px;'>
                <!-- Enhanced Card Header with Gradient -->
                <div class='card-header text-white position-relative overflow-hidden bg-body-tertiary' style="cursor: pointer;" onclick="toggleDiscussion()">
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
                            <!-- Status Badge -->
                            <?php if (in_array($taskStatus, ['Completed', 'Cancelled'])): ?>
                                <span class="badge bg-secondary bg-opacity-75 text-white px-3 py-2">
                                    <i class="fas fa-lock me-1"></i>Conversation Closed
                                </span>
                            <?php endif; ?>

                            <!-- Toggle button: always visible -->
                            <button type="button" id="discussionToggleBtn"
                                    class="btn btn-sm btn-outline-secondary"
                                    style="border-radius: 8px; min-width: 36px;"
                                    title="Toggle discussion"
                                    onclick="event.stopPropagation(); toggleDiscussion();">
                                <i class="fas fa-chevron-up" id="discussionToggleIcon" style="transition: transform 0.3s ease; display:inline-block;"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Card Body: collapsed by default when no messages, open when there are messages -->
                <div id="discussionCardBody" class='card-body p-0' style='display: <?php echo $hasMessages ? "flex" : "none"; ?>; flex-direction: column; height: 650px;'>

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
                                    $messageDate = date('Y-m-d', strtotime($comment['created_at']));

                                    // Show date separator if date changed
                                    if ($messageDate !== $lastDate):
                                        $lastDate = $messageDate;
                                        $displayDate = '';
                                        $today = date('Y-m-d');
                                        $yesterday = date('Y-m-d', strtotime('-1 day'));

                                        if ($messageDate === $today) {
                                            $displayDate = 'Today';
                                        } elseif ($messageDate === $yesterday) {
                                            $displayDate = 'Yesterday';
                                        } else {
                                            $displayDate = date('F j, Y', strtotime($messageDate));
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

                                        <div class="d-flex <?php echo $isAdmin ? 'flex-row' : 'flex-row-reverse'; ?> align-items-start gap-2">

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
                                                        $lastSeenTime = new DateTime($userStatus['last_seen']);
                                                        $now = new DateTime();
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
                                                        $imagePath = "profileimages/" . $profileImage;
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
                                                <div class="comment-bubble position-relative p-3 shadow-sm <?php echo $isAdmin ? 'bg-primary-subtle border border-primary-subtle' : 'bg-success-subtle border border-success-subtle'; ?>"
                                                     style="border-radius: <?php echo $isAdmin ? '20px 20px 20px 5px' : '20px 20px 5px 20px'; ?>; word-wrap: break-word;">

                                                    <!-- Message Header -->
                                                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                                                        <div class="comment-author fw-bold <?php echo $isAdmin ? 'text-primary' : 'text-success'; ?>" style="font-size: 13px;">
                                                            <i class="fas <?php echo $isAdmin ? 'fa-user-shield' : 'fa-user-edit'; ?> me-1" style="font-size: 11px;"></i>
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
                                                                <?php echo date('g:i A', strtotime($comment['created_at'])); ?>

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
                                                                    <a href="taskfiles/<?php echo htmlspecialchars($comment['file_url']); ?>"
                                                                       class="glightbox attachment-image"
                                                                       data-gallery="message-<?php echo $comment['id']; ?>"
                                                                       data-glightbox="description: Sent by <?php echo htmlspecialchars($comment['username']); ?>">
                                                                        <img src="taskfiles/<?php echo htmlspecialchars($comment['file_url']); ?>"
                                                                             alt="Attachment"
                                                                             class="img-thumbnail"
                                                                             style="max-width: 200px; max-height: 200px; object-fit: cover; cursor: pointer; border-radius: 8px;">
                                                                    </a>
                                                                <?php else:
                                                                    // Display as downloadable file
                                                                    $fileIcon = getFileIconClass($fileExt);
                                                                    ?>
                                                                    <a href="taskfiles/<?php echo htmlspecialchars($comment['file_url']); ?>"
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
                                                                            <a href="taskfiles/<?php echo htmlspecialchars($cleanFilePath); ?>"
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
                                                                            <a href="taskfiles/<?php echo htmlspecialchars($cleanFilePath); ?>"
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

    <script>
        (function() {
            var hasMessages = <?php echo $hasMessages ? 'true' : 'false'; ?>;
            var body = document.getElementById('discussionCardBody');
            var icon = document.getElementById('discussionToggleIcon');

            // Default: open when there are messages, collapsed when empty
            var isOpen = hasMessages;

            function setOpen(open) {
                isOpen = open;
                if (body) {
                    body.style.display = open ? 'flex' : 'none';
                    body.style.flexDirection = 'column';
                }
                if (icon) {
                    icon.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
                }
            }

            // Apply initial state (icon direction)
            setOpen(isOpen);

            window.toggleDiscussion = function() {
                setOpen(!isOpen);
            };
        })();
    </script>

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


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const timeElement = document.getElementById('time-remaining');
            let remainingSeconds = <?= $remainingSeconds ?>;

            function updateTime() {
                if (!timeElement) return;
                if (remainingSeconds <= 0) {
                    timeElement.style.color = 'red';
                    timeElement.innerHTML = "Past Due";
                    return;
                }

                const hours = Math.floor(remainingSeconds / 3600);
                const minutes = Math.floor((remainingSeconds % 3600) / 60);
                const seconds = remainingSeconds % 60;

                timeElement.innerHTML = `Time Remaining: ${hours} hrs ${minutes} min ${seconds} sec`;
                timeElement.style.color = 'green';

                remainingSeconds--;
            }

            // Update every second
            setInterval(updateTime, 1000);

            // Initialize immediately
            updateTime();
        });
    </script>
    <script>
        // Initialize tooltips for online indicators
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Optional: Real-time status updates via AJAX (call this periodically)
        function updateOnlineStatuses() {
            const taskId = <?php echo $taskId; ?>;

            fetch('get-online-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    task_id: taskId,
                    action: 'get_user_statuses'
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStatusIndicators(data.statuses);
                    }
                })
                .catch(error => {
                    console.error('Error updating online statuses:', error);
                });
        }

        function updateStatusIndicators(statuses) {
            Object.keys(statuses).forEach(userKey => {
                const indicators = document.querySelectorAll(`[data-user-key="${userKey}"]`);
                const status = statuses[userKey];

                indicators.forEach(indicator => {
                    // Update classes
                    indicator.className = indicator.className.replace(/bg-(success|warning|info|secondary)/, status.status_class.replace('bg-', 'bg-'));

                    // Update tooltip
                    const tooltip = bootstrap.Tooltip.getInstance(indicator);
                    if (tooltip) {
                        tooltip.setContent({
                            '.tooltip-inner': status.tooltip_content
                        });
                    }
                });
            });
        }

        // Update statuses every 30 seconds for active conversations
        <?php if (!in_array($taskStatus, ['Completed', 'Cancelled'])): ?>
        setInterval(updateOnlineStatuses, 30000);
        <?php endif; ?>

    </script>
    <script>
        function confirmAction(taskId, action) {
            if (action === 'accept' || action === 'decline') {
                let actionText = action === 'accept' ? 'accept' : 'decline';
                if (confirm(`Are you sure you want to ${actionText} this task?`)) {
                    window.location.href = `confirmation?task_id=${taskId}&action=${action}`;
                }
            } else if (action === 'resubmit') {
                if (confirm('Are you sure you want to resubmit this task?')) {
                    window.location.href = `resubmission?task_id=${taskId}#filesResubmission`;
                }
            }
        }
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
            const alignment = isAdmin ? 'flex-row' : 'flex-row-reverse';
            const bubbleColor = isAdmin ? 'bg-primary-subtle border-primary-subtle' : 'bg-success-subtle border-success-subtle';
            const bubbleRadius = isAdmin ? '20px 20px 20px 5px' : '20px 20px 5px 20px';
            const textColor = isAdmin ? 'text-primary' : 'text-success';
            const userIcon = isAdmin ? 'fa-user-shield' : 'fa-user-edit';

            // Avatar HTML
            let avatarHTML = '';
            if (comment.profile_image) {
                avatarHTML = `<img class="rounded-circle" src="${escapeHtml(comment.profile_image)}"
                          alt="${escapeHtml(comment.username)}"
                          style="width: 45px; height: 45px; object-fit: cover;">`;
            } else {
                const initials = comment.username.substring(0, 2).toUpperCase();
                const bgClass = isAdmin ? 'bg-primary' : 'bg-success';
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


<?php
include "footer.php";
?>