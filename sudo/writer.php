<?php
include_once('head.php');
include_once('writer-performance-functions.php');

$writerID = isset($_GET['writerID']) ? decode_writer_id($_GET['writerID']) : null;

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

        <style>
            /* Scoped polish for the writer profile page. Prefixed .pf- so nothing collides with the Falcon theme. */
            .pf-page .pf-name { letter-spacing: -0.01em; }
            .pf-meta { display: flex; flex-wrap: wrap; gap: 0.35rem 1.5rem; margin: 0.5rem 0 1rem; }
            .pf-meta-item { display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; }
            .pf-meta-item .fas { width: 1rem; text-align: center; }

            .pf-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
            @media (min-width: 992px) { .pf-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            .pf-stat {
                display: flex; align-items: center; gap: 0.85rem;
                border: 1px solid var(--bs-border-color);
                border-radius: 0.85rem; padding: 0.9rem 1rem;
                background: var(--bs-body-bg);
                transition: box-shadow 0.16s ease, transform 0.16s ease, border-color 0.16s ease;
            }
            .pf-stat:hover { box-shadow: 0 0.5rem 1.25rem rgba(0,0,0,0.06); transform: translateY(-2px); }
            .pf-stat-icon {
                flex: 0 0 auto; width: 2.75rem; height: 2.75rem;
                border-radius: 0.7rem; display: flex; align-items: center; justify-content: center;
                font-size: 1.05rem;
            }
            .pf-stat-value { font-size: 1.4rem; font-weight: 700; line-height: 1.05; }
            .pf-stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.75; }

            .pf-section-title { font-size: 0.95rem; font-weight: 600; }

            @media (max-width: 575.98px) {
                .pf-stat { padding: 0.75rem 0.85rem; }
                .pf-stat-value { font-size: 1.2rem; }
            }
        </style>

        <div class="pf-page">
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
                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex align-items-center mb-1">
                            <h4 class="pf-name mb-0 text-info me-2"><?php echo htmlspecialchars($rowWriter['FirstName']) . ' ' . htmlspecialchars($rowWriter['LastName']); ?></h4>
                            <span data-bs-toggle="tooltip" data-bs-placement="right" title="<?php echo $rowWriter['is_verified'] ? 'Verified Writer' : 'Unverified Writer'; ?>">
                                <?php if ($rowWriter['is_verified']) { ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <span class="fas fa-check-circle me-1"></span>Verified
                                    </span>
                                <?php } else { ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                        <span class="fas fa-times-circle me-1"></span>Unverified
                                    </span>
                                <?php } ?>
                            </span>
                        </div>

                        <div class="pf-meta text-700">
                            <span class="pf-meta-item">
                                <span class="fas fa-envelope text-info"></span>
                                <a class="text-decoration-none text-primary" href="mailto:<?php echo htmlspecialchars($rowWriter['email'] ?? ''); ?>"><?php echo htmlspecialchars($rowWriter['email'] ?? ''); ?></a>
                            </span>
                            <span class="pf-meta-item">
                                <span class="fas fa-user text-info"></span>
                                <span class="text-primary"><?php echo htmlspecialchars($rowWriter['username'] ?? ''); ?></span>
                            </span>
                            <?php if (!empty($rowWriter['phone'])) { ?>
                                <span class="pf-meta-item">
                                    <span class="fas fa-phone text-info"></span>
                                    <span class="text-primary"><?php echo htmlspecialchars($rowWriter['phone']); ?></span>
                                </span>
                            <?php } ?>
                        </div>

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
                    </div>
                </div>

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

                <!-- Stats -->
                <div class="pf-stats mt-4">
                    <div class="pf-stat" data-bs-toggle="tooltip" data-bs-placement="top" title="Member Since">
                        <span class="pf-stat-icon bg-info-subtle text-info"><span class="fas fa-calendar-alt"></span></span>
                        <div>
                            <div class="pf-stat-value text-primary" style="font-size:1.1rem;"><?php echo date("jS M, Y", strtotime($rowWriter['created_at'] . ' UTC')); ?></div>
                            <div class="pf-stat-label text-700">Member since</div>
                        </div>
                    </div>
                    <div class="pf-stat" data-bs-toggle="tooltip" data-bs-placement="top" title="Completed Tasks">
                        <span class="pf-stat-icon bg-success-subtle text-success"><span class="fas fa-check-circle"></span></span>
                        <div>
                            <div class="pf-stat-value text-primary"><?php echo $performance['completed_tasks']; ?></div>
                            <div class="pf-stat-label text-700">Completed tasks</div>
                        </div>
                    </div>
                    <div class="pf-stat" data-bs-toggle="tooltip" data-bs-placement="top" title="Tasks In Progress">
                        <span class="pf-stat-icon bg-warning-subtle text-warning"><span class="fas fa-spinner"></span></span>
                        <div>
                            <div class="pf-stat-value text-primary"><?php echo $performance['in_progress_tasks']; ?></div>
                            <div class="pf-stat-label text-700">In progress</div>
                        </div>
                    </div>
                    <div class="pf-stat" data-bs-toggle="tooltip" data-bs-placement="top" title="Total Earnings">
                        <span class="pf-stat-icon bg-info-subtle text-info"><span class="fas fa-wallet"></span></span>
                        <div>
                            <div class="pf-stat-value text-primary" style="font-size:1.1rem;">Ksh. <?php echo number_format($performance['total_earnings'], 2); ?></div>
                            <div class="pf-stat-label text-700">Total earnings</div>
                        </div>
                    </div>
                    <div class="pf-stat" data-bs-toggle="tooltip" data-bs-placement="top" title="Last seen: <?php echo isset($rowWriter['last_seen']) ? date('M j, Y g:i A', strtotime($rowWriter['last_seen'] . ' UTC')) : 'Unknown'; ?>">
                        <span class="pf-stat-icon bg-<?php echo $lastSeenClass; ?>-subtle text-<?php echo $lastSeenClass; ?>"><span class="fas fa-clock"></span></span>
                        <div>
                            <div class="pf-stat-value text-<?php echo $lastSeenClass; ?>" style="font-size:1.1rem;"><?php echo $lastSeenText; ?></div>
                            <div class="pf-stat-label text-700">Last seen</div>
                        </div>
                    </div>
                    <div class="pf-stat" data-bs-toggle="tooltip" data-bs-placement="top" title="Account Status">
                        <?php if ($isActive == 1) { ?>
                            <span class="pf-stat-icon bg-success-subtle text-success"><span class="fas fa-check-circle"></span></span>
                            <div>
                                <div class="pf-stat-value text-success" style="font-size:1.1rem;">Active</div>
                                <div class="pf-stat-label text-700">Account status</div>
                            </div>
                        <?php } else { ?>
                            <span class="pf-stat-icon bg-danger-subtle text-danger"><span class="fas fa-power-off"></span></span>
                            <div>
                                <div class="pf-stat-value text-danger" style="font-size:1.1rem;">Deactivated</div>
                                <div class="pf-stat-label text-700">Account status</div>
                            </div>
                        <?php } ?>
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
                    <h6 class="pf-section-title text-primary mb-3">Performance Metrics</h6>
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
                        <h6 class="pf-section-title text-primary mb-3">Recent Bonus - <?php echo date('F Y', mktime(0, 0, 0, $lastMonth, 1, $bonusYear)); ?></h6>
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

        <!-- Recent Activity -->
        <div class="row g-0">
            <div class="col-lg-12">
                <div class="card mb-3">
                    <div class="card-header bg-body-tertiary">
                        <h5 class="mb-0 text-info d-flex align-items-center">
                            <i class="fas fa-history me-2"></i>Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get recent tasks, with enough context to tell what actually happened
                        // at a glance (client/account, due date, and real timing vs. just an icon).
                        $recentTasksQuery = "SELECT topic, account, status, completed_on, due_date, create_date, pages, cpp
                                           FROM tbltasks
                                           WHERE email = ? AND is_deleted = 0
                                           ORDER BY create_date DESC
                                           LIMIT 5";
                        $recentStmt = $con->prepare($recentTasksQuery);
                        $recentStmt->bind_param("s", $rowWriter['email']);
                        $recentStmt->execute();
                        $recentTasks = $recentStmt->get_result();

                        $statusStyles = [
                            'Completed'   => ['success', 'check'],
                            'In Progress' => ['warning', 'spinner'],
                            'In Revision' => ['danger', 'flag'],
                            'Unconfirmed' => ['primary', 'question'],
                            'Submitted'   => ['info', 'paper-plane'],
                            'Cancelled'   => ['secondary', 'ban'],
                            'Draft'       => ['secondary', 'pencil-alt'],
                        ];

                        if ($recentTasks->num_rows > 0):
                            while ($task = $recentTasks->fetch_assoc()):
                                [$statusClass, $statusIcon] = $statusStyles[$task['status']] ?? ['secondary', 'circle'];
                                $topicDisplay = mb_strimwidth($task['topic'], 0, 40, '…');
                                ?>
                                <div class="d-flex align-items-start mb-3 pb-3 border-bottom border-dashed">
                                    <div class="me-3">
                                        <span class="badge bg-<?php echo $statusClass; ?> rounded-pill">
                                            <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                                        </span>
                                    </div>
                                    <div class="flex-1">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                                            <h6 class="mb-1 fs-9"><?php echo htmlspecialchars($topicDisplay); ?></h6>
                                            <span class="badge bg-<?php echo $statusClass; ?>-subtle text-<?php echo $statusClass; ?> fs-11"><?php echo htmlspecialchars($task['status']); ?></span>
                                        </div>
                                        <?php if (!empty($task['account'])) { ?>
                                            <div class="fs-11 text-500 mb-1"><span class="fas fa-building me-1"></span><?php echo htmlspecialchars($task['account']); ?></div>
                                        <?php } ?>
                                        <small class="text-muted d-block">
                                            <span class="fas fa-file-alt me-1"></span><?php echo $task['pages']; ?> pages
                                            <span class="mx-1">&middot;</span>
                                            <span class="fas fa-money-bill-wave me-1"></span>Ksh. <?php echo number_format($task['pages'] * $task['cpp'], 2); ?>
                                        </small>
                                        <?php if (!empty($task['due_date'])) { ?>
                                            <small class="text-muted d-block">
                                                <span class="fas fa-calendar-day me-1"></span>Due <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                            </small>
                                        <?php } ?>
                                        <?php if ($task['status'] == 'Completed' && $task['completed_on']):
                                            $onTime = strtotime($task['completed_on']) <= strtotime($task['due_date']);
                                            ?>
                                            <small class="text-<?php echo $onTime ? 'success' : 'danger'; ?>">
                                                <span class="fas fa-<?php echo $onTime ? 'check' : 'exclamation-triangle'; ?> me-1"></span>
                                                Completed <?php echo $onTime ? 'on time' : 'late'; ?> (<?php echo date('M j, Y', strtotime($task['completed_on'])); ?>)
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile;
                        else: ?>
                            <p class="text-muted text-center mb-0">No recent activity</p>
                        <?php endif; ?>
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

        <?php
    $con->close();
    include "footer.php";
?>
