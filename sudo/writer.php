<?php
include_once('head.php');
include_once('writer-performance-functions.php');

$writerID = isset($_GET['writerID']) ? base64_decode($_GET['writerID']) : null;

if ($writerID) {
    // Fetch writer details
    $stmt = $con->prepare("SELECT * FROM tblwriters WHERE id = ?");
    $stmt->bind_param("i", $writerID);
    $stmt->execute();
    $result = $stmt->get_result();
    $rowWriter = $result->fetch_assoc();

    if ($rowWriter) {
        // Update writer performance
        updateWriterPerformance($con, $writerID, $rowWriter['email']);

        // Get comprehensive performance data
        $performance = calculateWriterPerformance($con, $rowWriter['email']);
        $currentLevel = getWriterLevel($con, $performance['completed_tasks']);
        $levelProgress = calculateLevelProgress($con, $performance['completed_tasks']);

        // Get recent monthly bonus
        $currentMonth = date('n');
        $currentYear = date('Y');
        $lastMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
        $bonusYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;

        $recentBonusQuery = "SELECT * FROM tbl_monthly_bonuses WHERE writer_email = ? AND month = ? AND year = ? LIMIT 1";
        $bonusStmt = $con->prepare($recentBonusQuery);
        $bonusStmt->bind_param("sii", $rowWriter['email'], $lastMonth, $bonusYear);
        $bonusStmt->execute();
        $recentBonus = $bonusStmt->get_result()->fetch_assoc();

        // Display writer profile
        ?>
        <title>iTasker | Writer Profile - <?php echo htmlspecialchars($rowWriter['FirstName'] . ' ' . $rowWriter['LastName']); ?></title>
        <?php include "navi.php"; ?>

        <div class="card mb-3">
            <div class="card-header position-relative min-vh-25 mb-7">
                <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url('../profileimages/<?php echo htmlspecialchars($rowWriter['coverImage'] ?: '1.jpg'); ?>');"></div>
                <div class="avatar avatar-5xl avatar-profile">
                    <img class="rounded-circle img-thumbnail shadow-sm" src="../profileimages/<?php echo htmlspecialchars($rowWriter['Photo'] ?: 'avatar.png'); ?>" width="200" alt="">
                    <!-- Level Badge -->
                    <div class="position-absolute bottom-0 end-0">
                        <div class="badge rounded-pill p-2 shadow-lg" style="background: linear-gradient(135deg, <?php echo $currentLevel['icon_color']; ?>, <?php echo $currentLevel['icon_color']; ?>aa);">
                            <i class="fas <?php echo $currentLevel['icon_class']; ?> text-white me-1"></i>
                            <span class="text-white fw-bold"><?php echo $currentLevel['level_name']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <h4 class="mb-1 text-info">
                            <?php echo htmlspecialchars($rowWriter['FirstName']) . ' ' . htmlspecialchars($rowWriter['LastName']); ?>
                            <span data-bs-toggle="tooltip" data-bs-placement="right" title="<?php echo $rowWriter['is_verified'] ? 'Verified Writer' : 'Unverified Writer'; ?>">
                                <small class="fa fa-<?php echo $rowWriter['is_verified'] ? 'check-circle text-primary' : 'times-circle text-secondary'; ?>" data-fa-transform="shrink-4 down-2"></small>
                            </span>
                        </h4>
                        <h5 class="fs-9 fw-normal text-primary"><?php echo htmlspecialchars($rowWriter['email'] ?? ''); ?></h5>
                        <p class="text-900 mb-1"><?php echo htmlspecialchars($rowWriter['username'] ?? ''); ?></p>
                        <p class="text-900 mb-3"><?php echo htmlspecialchars($rowWriter['phone'] ?? ''); ?></p>

                        <!-- Writer Level and Progress -->
                        <div class="card border-0 bg-body-quaternary mb-3">
                            <div class="card-body py-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas <?php echo $currentLevel['icon_class']; ?> fa-2x me-3" style="color: <?php echo $currentLevel['icon_color']; ?>;"></i>
                                    <div class="flex-1">
                                        <h6 class="mb-0" style="color: <?php echo $currentLevel['icon_color']; ?>;">Level <?php echo $currentLevel['level_number']; ?> - <?php echo $currentLevel['level_name']; ?></h6>
                                        <small class="text-muted"><?php echo $performance['completed_tasks']; ?> tasks completed</small>
                                    </div>
                                </div>

                                <?php if ($levelProgress['progress'] < 100): ?>
                                    <div class="progress mb-1" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $levelProgress['progress']; ?>%; background-color: <?php echo $currentLevel['icon_color']; ?>;"></div>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted"><?php echo $levelProgress['progress']; ?>% to next level</small>
                                        <small class="text-muted"><?php echo $levelProgress['tasks_remaining']; ?> tasks remaining</small>
                                    </div>
                                    <?php if (isset($levelProgress['next_level'])): ?>
                                        <small class="text-success">
                                            <i class="fas <?php echo $levelProgress['next_level']['icon_class']; ?> me-1"></i>
                                            Next: <?php echo $levelProgress['next_level']['level_name']; ?>
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-center">
                                        <span class="badge bg-warning text-dark px-3 py-2">
                                            <i class="fas fa-crown me-1"></i>Maximum Level Achieved!
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="border-bottom border-dashed my-4 d-lg-none"></div>
                    </div>

                    <div class="col ps-2 ps-lg-5">
                        <?php
                        // Calculate last seen and account status
                        $isActive = isset($rowWriter['is_active']) ? $rowWriter['is_active'] : 1;
                        $deactivationReason = isset($rowWriter['deactivation_reason']) ? $rowWriter['deactivation_reason'] : null;
                        $deactivatedAt = isset($rowWriter['deactivated_at']) ? $rowWriter['deactivated_at'] : null;

                        $lastSeenText = 'Unknown';
                        $lastSeenClass = 'secondary';

                        if (isset($rowWriter["last_seen"]) && !empty($rowWriter["last_seen"])) {
                            $lastSeen = new DateTime($rowWriter["last_seen"], new DateTimeZone('UTC'));
                            $lastSeen->setTimezone(new DateTimeZone('Africa/Nairobi'));
                            $now = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
                            $diff = $now->diff($lastSeen);

                            if ($diff->y > 0) {
                                $lastSeenText = $diff->y . " year" . ($diff->y > 1 ? "s" : "") . " ago";
                                $lastSeenClass = 'danger';
                            } elseif ($diff->m > 0) {
                                $lastSeenText = $diff->m . " month" . ($diff->m > 1 ? "s" : "") . " ago";
                                $lastSeenClass = $diff->m >= 3 ? 'danger' : 'warning';
                            } elseif ($diff->days >= 7) {
                                $weeks = floor($diff->days / 7);
                                $lastSeenText = $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
                                $lastSeenClass = 'warning';
                            } elseif ($diff->days > 0) {
                                $lastSeenText = $diff->days . " day" . ($diff->days > 1 ? "s" : "") . " ago";
                                $lastSeenClass = 'info';
                            } elseif ($diff->h > 0) {
                                $lastSeenText = $diff->h . " hour" . ($diff->h > 1 ? "s" : "") . " ago";
                                $lastSeenClass = 'success';
                            } elseif ($diff->i > 0) {
                                $lastSeenText = $diff->i . " minute" . ($diff->i > 1 ? "s" : "") . " ago";
                                $lastSeenClass = 'success';
                            } else {
                                $lastSeenText = "Online Now";
                                $lastSeenClass = 'success';
                            }
                        }
                        ?>
                        <div class="row g-3">
                            <!-- Basic Stats -->
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2" data-bs-toggle="tooltip" data-bs-placement="top" title="Member Since">
                                    <span class="fas fa-calendar-alt fs-8 me-2 text-info" ></span>
                                    <div class="flex-1">
                                        <h6 class="mb-0 text-primary" ><?php echo date("jS F, Y", strtotime($rowWriter['created_at'] . ' UTC')); ?></h6>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2" data-bs-toggle="tooltip" data-bs-placement="top" title="Completed Tasks">
                                    <span class="fas fa-tasks fs-8 me-2 text-success" ></span>
                                    <div class="flex-1">
                                        <h6 class="mb-0 text-primary"><?php echo $performance['completed_tasks']; ?> Completed</h6>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2" data-bs-toggle="tooltip" data-bs-placement="top" title="Tasks In Progress">
                                    <span class="fas fa-spinner fs-8 me-2 text-warning" ></span>
                                    <div class="flex-1">
                                        <h6 class="mb-0 text-primary"><?php echo $performance['in_progress_tasks']; ?> In Progress</h6>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2" data-bs-toggle="tooltip" data-bs-placement="top" title="Total Earnings">
                                    <span class="fas fa-wallet fs-8 me-2 text-success" ></span>
                                    <div class="flex-1">
                                        <h6 class="mb-0 text-primary">Ksh. <?php echo number_format($performance['total_earnings'], 2); ?></h6>
                                    </div>
                                </div>
                            </div>

                            <!-- Last Seen -->
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2" data-bs-toggle="tooltip" data-bs-placement="top" title="Last seen: <?php echo isset($rowWriter['last_seen']) ? date('M j, Y g:i A', strtotime($rowWriter['last_seen'] . ' UTC')): 'Unknown'; ?>">
                                    <span class="fas fa-clock fs-8 me-2 text-<?php echo $lastSeenClass; ?>" ></span>
                                    <div class="flex-1">
                                        <h6 class="mb-0 text-<?php echo $lastSeenClass; ?>"><?php echo $lastSeenText; ?></h6>
                                    </div>
                                </div>
                            </div>

                            <!-- Account Status -->
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2" data-bs-toggle="tooltip" data-bs-placement="top" title="Account Status">
                                    <?php if ($isActive == 1): ?>
                                        <span class="fas fa-check-circle fs-8 me-2 text-success"></span>
                                        <div class="flex-1">
                                            <h6 class="mb-0 text-success">Active</h6>
                                        </div>
                                    <?php else: ?>
                                        <span class="fas fa-power-off fs-8 me-2 text-danger"></span>
                                        <div class="flex-1">
                                            <h6 class="mb-0 text-danger">Deactivated</h6>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Deactivation Alert (if deactivated) -->
                        <?php if ($isActive == 0): ?>
                            <div class="alert alert-danger mt-3 mb-0 py-2">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-ban me-2 mt-1"></i>
                                    <div class="flex-1">
                                        <strong>Account Deactivated</strong>
                                        <?php if ($deactivatedAt): ?>
                                            <br><small>On: <?php echo date("F j, Y \a\\t g:i A", strtotime($deactivatedAt . ' UTC')); ?></small>
                                        <?php endif; ?>
                                        <?php if ($deactivationReason): ?>
                                            <br><small>Reason: <?php echo htmlspecialchars($deactivationReason); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Performance Metrics -->
                        <div class="mt-4">
                            <h6 class="text-primary mb-3">Performance Metrics</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card border-0 bg-success-subtle h-100">
                                        <div class="card-body text-center py-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="fas fa-chart-line fa-2x text-success me-2"></i>
                                                <div>
                                                    <h4 class="mb-0 text-success"><?php echo $performance['completion_rate']; ?>%</h4>
                                                    <small class="text-muted">Completion Rate</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card border-0 bg-info-subtle h-100">
                                        <div class="card-body text-center py-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="fas fa-clock fa-2x text-info me-2"></i>
                                                <div>
                                                    <h4 class="mb-0 text-info"><?php echo $performance['on_time_rate']; ?>%</h4>
                                                    <small class="text-muted">On-Time Rate</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card border-0 bg-warning-subtle h-100">
                                        <div class="card-body text-center py-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="fas fa-tachometer-alt fa-2x text-warning me-2"></i>
                                                <div>
                                                    <h4 class="mb-0 text-warning"><?php echo $performance['avg_completion_days']; ?></h4>
                                                    <small class="text-muted">Avg Days</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card border-0 bg-primary-subtle h-100">
                                        <div class="card-body text-center py-3">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="fas fa-medal fa-2x text-primary me-2"></i>
                                                <div>
                                                    <h4 class="mb-0 text-primary"><?php echo $performance['early_completions']; ?></h4>
                                                    <small class="text-muted">Early Completions</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Bonus Information -->
                        <?php if ($recentBonus): ?>
                            <div class="mt-4">
                                <h6 class="text-primary mb-3">Recent Bonus - <?php echo date('F Y', mktime(0, 0, 0, $lastMonth, 1, $bonusYear)); ?></h6>
                                <div class="card border-0 bg-body-tertiary">
                                    <div class="card-body py-3">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Bonus Amount</small>
                                                <h6 class="mb-0 text-success">Ksh. <?php echo number_format($recentBonus['total_bonus_amount'], 2); ?></h6>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Bonus Rate</small>
                                                <h6 class="mb-0 text-info"><?php echo $recentBonus['bonus_percentage']; ?>%</h6>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <?php echo $recentBonus['tasks_completed_on_time']; ?>/<?php echo $recentBonus['total_tasks_completed']; ?> tasks on time
                                                <?php if ($recentBonus['perfect_month_bonus'] > 0): ?>
                                                    <span class="badge bg-success ms-2">Perfect Month!</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logged-in Devices (admin view of this writer's sessions) -->
        <?php
        $wsCutoff = date('Y-m-d H:i:s', time() - 86400); // active within the last 24h
        $writerSessions = [];
        $wsStmt = mysqli_prepare($con, "SELECT * FROM tblwriter_sessions
                                        WHERE writer_email = ? AND last_activity >= ?
                                        ORDER BY last_activity DESC");
        if ($wsStmt) { // null if tblwriter_sessions doesn't exist yet
            mysqli_stmt_bind_param($wsStmt, 'ss', $rowWriter['email'], $wsCutoff);
            mysqli_stmt_execute($wsStmt);
            $wsRes = mysqli_stmt_get_result($wsStmt);
            while ($wsRow = mysqli_fetch_assoc($wsRes)) { $writerSessions[] = $wsRow; }
            mysqli_stmt_close($wsStmt);
        }
        ?>
        <div class="row g-0">
            <div class="col-lg-12">
                <div class="card mb-3">
                    <div class="card-header bg-body-tertiary d-flex align-items-center">
                        <span class="fas fa-laptop fs-9 me-2 text-info"></span>
                        <h5 class="mb-0 text-info">Logged-in Devices</h5>
                        <span class="badge bg-info-subtle text-info ms-2"><?php echo count($writerSessions); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($writerSessions)) {
                            $lastSeenTxt = 'Unknown';
                            if (!empty($rowWriter['last_seen'])) {
                                $ls = new DateTime($rowWriter['last_seen'], new DateTimeZone('UTC'));
                                $ls->setTimezone(new DateTimeZone('Africa/Nairobi'));
                                $lastSeenTxt = $ls->format('jS M Y, g:i A');
                            }
                            ?>
                            <div class="p-3 text-700">
                                <span class="fas fa-info-circle me-1"></span>
                                Not currently logged in on any device. Last seen: <span class="text-900"><?php echo $lastSeenTxt; ?></span>.
                            </div>
                        <?php } else { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 fs-10">
                                    <thead class="text-700">
                                    <tr>
                                        <th class="ps-3">Device</th>
                                        <th>IP / Location</th>
                                        <th>Signed in</th>
                                        <th>Last active</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($writerSessions as $s) {
                                        $isMobile = (stripos($s['device_label'], 'Mobile') !== false);
                                        $icon = $isMobile ? 'mobile-alt' : 'desktop';
                                        ?>
                                        <tr>
                                            <td class="ps-3">
                                                <span class="fas fa-<?php echo $icon; ?> me-2 text-info"></span>
                                                <?php echo htmlspecialchars($s['device_label']); ?>
                                            </td>
                                            <td>
                                                <div class="text-900"><?php echo htmlspecialchars($s['ip_address']); ?></div>
                                                <?php if (!empty($s['location'])) { ?>
                                                    <div class="fs-11 text-500"><span class="fas fa-map-marker-alt me-1"></span><?php echo htmlspecialchars($s['location']); ?></div>
                                                <?php } ?>
                                            </td>
                                            <td class="text-700"><?php echo date("jS M Y, g:i A", strtotime($s['login_time'])); ?></td>
                                            <td class="text-700"><?php echo date("jS M Y, g:i A", strtotime($s['last_activity'])); ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Initialize tooltips
            document.addEventListener('DOMContentLoaded', function() {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });
        </script>

        <?php
    } else {
        echo '<div class="alert alert-danger">Writer not found.</div>';
    }

    $stmt->close();
} else {
    echo '<div class="alert alert-danger">Invalid writer ID.</div>';
}
?>

        <!-- Performance Analysis Card -->
        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header bg-body-tertiary">
                        <h5 class="mb-0 text-info d-flex align-items-center">
                            <i class="fas fa-chart-bar me-2"></i>Performance Analysis
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Task Breakdown -->
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">Task Breakdown</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-success">Completed:</span>
                                    <span class="fw-bold"><?php echo $performance['completed_tasks']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-warning">In Progress:</span>
                                    <span class="fw-bold"><?php echo $performance['in_progress_tasks']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-danger">Cancelled:</span>
                                    <span class="fw-bold"><?php echo $performance['cancelled_tasks']; ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span class="text-primary fw-bold">Total:</span>
                                    <span class="fw-bold"><?php echo $performance['total_tasks']; ?></span>
                                </div>
                            </div>

                            <!-- Completion Analysis -->
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">Completion Analysis</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-success">On Time:</span>
                                    <span class="fw-bold"><?php echo $performance['on_time_completions']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-info">Early:</span>
                                    <span class="fw-bold"><?php echo $performance['early_completions']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-warning">Late:</span>
                                    <span class="fw-bold"><?php echo $performance['late_completions']; ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span class="text-primary fw-bold">Success Rate:</span>
                                    <span class="fw-bold text-<?php echo $performance['on_time_rate'] >= 80 ? 'success' : ($performance['on_time_rate'] >= 60 ? 'warning' : 'danger'); ?>">
                                        <?php echo $performance['on_time_rate']; ?>%
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Performance Rating -->
                        <div class="mt-4">
                            <h6 class="text-primary mb-3">Performance Rating</h6>
                            <?php
                            $overallScore = ($performance['completion_rate'] * 0.4) + ($performance['on_time_rate'] * 0.6);
                            $ratingClass = $overallScore >= 90 ? 'success' : ($overallScore >= 70 ? 'warning' : 'danger');
                            $ratingText = $overallScore >= 90 ? 'Excellent' : ($overallScore >= 70 ? 'Good' : 'Needs Improvement');
                            ?>
                            <div class="d-flex align-items-center">
                                <div class="flex-1 me-3">
                                    <div class="progress" style="height: 15px;">
                                        <div class="progress-bar bg-<?php echo $ratingClass; ?>" role="progressbar" style="width: <?php echo $overallScore; ?>%"></div>
                                    </div>
                                </div>
                                <span class="badge bg-<?php echo $ratingClass; ?> px-3 py-2">
                                    <?php echo round($overallScore, 1); ?>% - <?php echo $ratingText; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Card -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-body-tertiary">
                        <h5 class="mb-0 text-info d-flex align-items-center">
                            <i class="fas fa-history me-2"></i>Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get recent tasks
                        $recentTasksQuery = "SELECT topic, status, completed_on, due_date, pages, cpp 
                                           FROM tbltasks 
                                           WHERE email = ? AND is_deleted = 0 
                                           ORDER BY create_date DESC 
                                           LIMIT 5";
                        $recentStmt = $con->prepare($recentTasksQuery);
                        $recentStmt->bind_param("s", $rowWriter['email']);
                        $recentStmt->execute();
                        $recentTasks = $recentStmt->get_result();

                        if ($recentTasks->num_rows > 0):
                            while ($task = $recentTasks->fetch_assoc()):
                                $statusClass = $task['status'] == 'Completed' ? 'success' :
                                    ($task['status'] == 'In Progress' ? 'warning' : 'secondary');
                                ?>
                                <div class="d-flex align-items-start mb-3">
                                    <div class="me-3">
                                        <span class="badge bg-<?php echo $statusClass; ?> rounded-pill">
                                            <i class="fas fa-<?php echo $task['status'] == 'Completed' ? 'check' : 'clock'; ?>"></i>
                                        </span>
                                    </div>
                                    <div class="flex-1">
                                        <h6 class="mb-1 fs-9"><?php echo htmlspecialchars(substr($task['topic'], 0, 30)) . '...'; ?></h6>
                                        <small class="text-muted">
                                            <?php echo $task['pages']; ?> pages • Ksh. <?php echo number_format($task['pages'] * $task['cpp'], 2); ?>
                                        </small>
                                        <?php if ($task['status'] == 'Completed' && $task['completed_on']): ?>
                                            <br><small class="text-<?php echo strtotime($task['completed_on']) <= strtotime($task['due_date']) ? 'success' : 'danger'; ?>">
                                                <?php echo strtotime($task['completed_on']) <= strtotime($task['due_date']) ? 'On Time' : 'Late'; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile;
                        else: ?>
                            <p class="text-muted text-center">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Writer Introduction -->
        <div class="row g-0">
            <div class="col-lg-12">
                <div class="card mb-3">
                    <div class="card-header bg-body-tertiary">
                        <h5 class="mb-0 text-info">Professional Profile</h5>
                    </div>
                    <div class="card-body text-justify">
                        <p class="mb-0 text-primary">Dedicated, passionate, and accomplished Full Stack Developer with 9+ years of progressive experience working as an Independent Contractor for Google and developing and growing my educational social network that helps others learn programming, web design, game development, networking.</p>
                        <div class="collapse show" id="profile-intro">
                            <p class="mt-3 text-primary">I've acquired a wide depth of knowledge and expertise in using my technical skills in programming, computer science, software development, and mobile app development to developing solutions to help organizations increase productivity, and accelerate business performance.</p>
                            <p class="text-primary">It's great that we live in an age where we can share so much with technology but I'm ready for the next phase of my career, with a healthy balance between the virtual world and a workplace where I help others face-to-face.</p>
                            <p class="mb-0 text-primary">There's always something new to learn, especially in IT-related fields. People like working with me because I can explain technology to everyone, from staff to executives who need me to tie together the details and the big picture. I can also implement the technologies that successful projects need.</p>
                        </div>
                    </div>
                    <div class="card-footer bg-body-tertiary p-0 border-top">
                        <button class="btn btn-link d-block w-100 btn-intro-collapse" type="button" data-bs-toggle="collapse" data-bs-target="#profile-intro" aria-expanded="true" aria-controls="profile-intro">
                            Show <span class="less">less<span class="fas fa-chevron-up ms-2 fs-11"></span></span><span class="full">full<span class="fas fa-chevron-down ms-2 fs-11"></span></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php
    $con->close();
    include "footer.php";
?>