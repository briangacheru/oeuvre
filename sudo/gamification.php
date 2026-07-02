<?php include "head.php";?>
    <title>Gamification |iTasker</title>
<?php include "navi.php";?><div id="alert-container"></div>
<?php
// 9. Gamification System Implementation
$gamificationData = [];

if (!empty($taskWriter)) {
    // Initialize or get writer stats
    $statsQuery = "SELECT * FROM tbl_writer_stats WHERE writer_username = ?";
    $stmt = mysqli_prepare($con, $statsQuery);
    mysqli_stmt_bind_param($stmt, 's', $taskWriter);
    mysqli_stmt_execute($stmt);
    $statsResult = mysqli_stmt_get_result($stmt);
    $writerStats = mysqli_fetch_assoc($statsResult);

    if (!$writerStats) {
        // Create initial stats record
        $createStatsQuery = "INSERT INTO tbl_writer_stats (writer_username) VALUES (?)";
        $stmt = mysqli_prepare($con, $createStatsQuery);
        mysqli_stmt_bind_param($stmt, 's', $taskWriter);
        mysqli_stmt_execute($stmt);

        // Get the newly created record
        $stmt = mysqli_prepare($con, $statsQuery);
        mysqli_stmt_bind_param($stmt, 's', $taskWriter);
        mysqli_stmt_execute($stmt);
        $statsResult = mysqli_stmt_get_result($stmt);
        $writerStats = mysqli_fetch_assoc($statsResult);
    }

    // Calculate current task performance score
    $taskPerformanceScore = 0;

    // Base completion score
    if ($taskStatus == 'Completed') {
        $taskPerformanceScore += 100;

        // Bonus for early completion
        if (isset($taskMetrics['completed_early']) && $taskMetrics['completed_early']) {
            $taskPerformanceScore += 25;
        }

        // Quality bonus based on complexity
        if ($complexityScore > 70) {
            $taskPerformanceScore += 20; // High complexity bonus
        }

        // Payment bonus
        if ($is_paid == 1) {
            $taskPerformanceScore += 10;
        }
    } elseif ($taskStatus == 'Submitted') {
        $taskPerformanceScore += 75;

        // Early submission bonus
        if (isset($taskMetrics['submitted_early']) && $taskMetrics['submitted_early']) {
            $taskPerformanceScore += 15;
        }
    } elseif ($taskStatus == 'In Progress' && !$isLate) {
        $taskPerformanceScore += 25; // Progress bonus
    }

    // Deduct points for being late
    if ($isLate && $taskStatus != 'Completed') {
        $taskPerformanceScore -= 30;
    }

    $gamificationData['current_task_score'] = max(0, $taskPerformanceScore);

    // Calculate writer level (every 1000 points = 1 level)
    $currentLevel = floor($writerStats['total_points'] / 1000) + 1;
    $experienceInCurrentLevel = $writerStats['total_points'] % 1000;
    $experienceNeededForNextLevel = 1000 - $experienceInCurrentLevel;

    // Calculate completion streak
    $streakQuery = "SELECT 
        COUNT(*) as recent_completed,
        MAX(completed_on) as last_completion,
        SUM(CASE WHEN completed_on < due_date THEN 1 ELSE 0 END) as early_completions
        FROM tbltasks 
        WHERE writer = ? 
        AND status = 'Completed' 
        AND completed_on >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY completed_on DESC";

    $stmt = mysqli_prepare($con, $streakQuery);
    mysqli_stmt_bind_param($stmt, 's', $taskWriter);
    mysqli_stmt_execute($stmt);
    $streakResult = mysqli_stmt_get_result($stmt);
    $streakData = mysqli_fetch_assoc($streakResult);

    // Get consecutive completion streak
    $consecutiveStreakQuery = "SELECT completed_on, due_date 
        FROM tbltasks 
        WHERE writer = ? 
        AND status = 'Completed' 
        ORDER BY completed_on DESC 
        LIMIT 20";

    $stmt = mysqli_prepare($con, $consecutiveStreakQuery);
    mysqli_stmt_bind_param($stmt, 's', $taskWriter);
    mysqli_stmt_execute($stmt);
    $consecutiveResult = mysqli_stmt_get_result($stmt);

    $currentStreak = 0;
    $maxStreak = 0;
    $streakCount = 0;

    while ($row = mysqli_fetch_assoc($consecutiveResult)) {
        if ($row['completed_on'] <= $row['due_date']) {
            $streakCount++;
        } else {
            if ($streakCount > 0) {
                $maxStreak = max($maxStreak, $streakCount);
                if ($currentStreak == 0) {
                    $currentStreak = $streakCount;
                }
            }
            $streakCount = 0;
        }
    }

    if ($streakCount > 0) {
        $maxStreak = max($maxStreak, $streakCount);
        if ($currentStreak == 0) {
            $currentStreak = $streakCount;
        }
    }

    // Quality score calculation
    $qualityScore = 0;
    if ($writerAnalytics['total_tasks'] > 0) {
        $completionRate = $writerAnalytics['completion_rate'];
        $earlyCompletionRate = $writerAnalytics['early_completion_rate'];
        $avgCompletionTime = $writerAnalytics['avg_completion_days'] ?? 7;

        // Quality formula: completion rate + early completion bonus - time penalty
        $qualityScore = ($completionRate * 0.4) + ($earlyCompletionRate * 0.3) + (max(0, 10 - $avgCompletionTime) * 5);
        $qualityScore = min(100, max(0, $qualityScore));
    }

    // Get recent achievements
    $achievementsQuery = "SELECT * FROM tbl_writer_achievements 
        WHERE writer_username = ? 
        AND is_active = 1 
        ORDER BY earned_at DESC 
        LIMIT 10";

    $stmt = mysqli_prepare($con, $achievementsQuery);
    mysqli_stmt_bind_param($stmt, 's', $taskWriter);
    mysqli_stmt_execute($stmt);
    $achievementsResult = mysqli_stmt_get_result($stmt);
    $recentAchievements = [];

    while ($achievement = mysqli_fetch_assoc($achievementsResult)) {
        $recentAchievements[] = $achievement;
    }

    // Determine badges based on performance
    $badges = [];

    // Completion badges
    if ($writerAnalytics['total_tasks'] >= 50) {
        $badges[] = ['name' => 'Veteran Writer', 'icon' => 'fa-medal', 'color' => 'warning', 'description' => '50+ Tasks Completed'];
    } elseif ($writerAnalytics['total_tasks'] >= 25) {
        $badges[] = ['name' => 'Experienced Writer', 'icon' => 'fa-trophy', 'color' => 'info', 'description' => '25+ Tasks Completed'];
    } elseif ($writerAnalytics['total_tasks'] >= 10) {
        $badges[] = ['name' => 'Rising Star', 'icon' => 'fa-star', 'color' => 'primary', 'description' => '10+ Tasks Completed'];
    }

    // Quality badges
    if ($qualityScore >= 90) {
        $badges[] = ['name' => 'Excellence Master', 'icon' => 'fa-crown', 'color' => 'warning', 'description' => '90+ Quality Score'];
    } elseif ($qualityScore >= 80) {
        $badges[] = ['name' => 'Quality Specialist', 'icon' => 'fa-gem', 'color' => 'success', 'description' => '80+ Quality Score'];
    }

    // Streak badges
    if ($maxStreak >= 10) {
        $badges[] = ['name' => 'Consistency King', 'icon' => 'fa-fire', 'color' => 'danger', 'description' => '10+ Task Streak'];
    } elseif ($maxStreak >= 5) {
        $badges[] = ['name' => 'Reliable Writer', 'icon' => 'fa-check-circle', 'color' => 'success', 'description' => '5+ Task Streak'];
    }

    // Speed badges
    if (isset($writerAnalytics['avg_completion_days']) && $writerAnalytics['avg_completion_days'] <= 2) {
        $badges[] = ['name' => 'Speed Demon', 'icon' => 'fa-bolt', 'color' => 'warning', 'description' => 'Avg 2 Days Completion'];
    }

    // Recent performance badge
    if ($taskPerformanceScore >= 120) {
        $badges[] = ['name' => 'Task Ace', 'icon' => 'fa-certificate', 'color' => 'primary', 'description' => 'Exceptional Performance'];
    }

    // Level titles
    $levelTitles = [
        1 => 'Novice Writer',
        2 => 'Apprentice Scribe',
        3 => 'Skilled Author',
        4 => 'Expert Writer',
        5 => 'Master Wordsmith',
        6 => 'Elite Contributor',
        7 => 'Writing Virtuoso',
        8 => 'Literary Sage',
        9 => 'Grand Master',
        10 => 'Legendary Author'
    ];

    $gamificationData = [
        'writer_stats' => $writerStats,
        'current_level' => $currentLevel,
        'level_title' => $levelTitles[min($currentLevel, 10)] ?? 'Supreme Writer',
        'experience_current' => $experienceInCurrentLevel,
        'experience_needed' => $experienceNeededForNextLevel,
        'experience_percentage' => ($experienceInCurrentLevel / 1000) * 100,
        'quality_score' => round($qualityScore, 1),
        'current_streak' => $currentStreak,
        'max_streak' => $maxStreak,
        'badges' => $badges,
        'recent_achievements' => $recentAchievements,
        'current_task_score' => $taskPerformanceScore,
        'points_breakdown' => [
            'completion_bonus' => $taskStatus == 'Completed' ? 100 : ($taskStatus == 'Submitted' ? 75 : 25),
            'early_bonus' => (isset($taskMetrics['completed_early']) && $taskMetrics['completed_early']) ? 25 : 0,
            'complexity_bonus' => $complexityScore > 70 ? 20 : 0,
            'payment_bonus' => $is_paid == 1 ? 10 : 0,
            'late_penalty' => $isLate && $taskStatus != 'Completed' ? -30 : 0
        ]
    ];
}
?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Update <span class="text-info fw-medium"> Version</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0">
                    <form class="row flex-lg-column flex-xxl-row gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
                        <div class="col-auto">
                        </div>
                        <div class="col-md-auto position-relative">
                            <h6 class="mb-1 text-primary"></h6>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Gamification Dashboard Card -->
<?php if (!empty($gamificationData)): ?>
    <div class="card mb-3 gamification-card">
        <div class="card-header position-relative overflow-hidden" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" patternUnits="userSpaceOnUse" width="1" height="1"><circle cx="0.5" cy="0.5" r="0.5" fill="white" opacity="0.05"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;"></div>
        <div class="position-relative d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="me-3 p-2 rounded-circle bg-white bg-opacity-25">
                    <i class="fas fa-trophy text-white fs-4"></i>
                </div>
                <div>
                    <h5 class="mb-0 text-white fw-bold">Writer Performance & Achievements</h5>
                    <small class="text-white-50">
                        <i class="fas fa-layer-group me-1"></i>
                        Level <?php echo $gamificationData['current_level']; ?> - <?php echo $gamificationData['level_title']; ?>
                    </small>
                </div>
            </div>
            <button class="btn btn-light btn-sm shadow-sm hover-lift" onclick="toggleGamification()" style="transition: all 0.3s ease;">
                <i class="fas fa-chevron-down" id="gamification-toggle-icon"></i>
            </button>
        </div>
    </div>

    <div class="card-body" id="gamification-content" style="display: none;">
        <div class="row g-4">

            <!-- Level Progress Section -->
            <div class="col-12">
                <div class="card border border-primary bg-gradient bg-primary-subtle">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <div class="level-avatar position-relative mb-3">
                                    <div class="avatar avatar-5xl">
                                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                            <span class="text-white fw-bold fs-3"><?php echo $gamificationData['current_level']; ?></span>
                                        </div>
                                        <!-- Level ring animation -->
                                        <div class="position-absolute top-0 start-0 w-100 h-100">
                                            <svg width="80" height="80" class="level-progress-ring">
                                                <circle cx="40" cy="40" r="35" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="3"/>
                                                <circle cx="40" cy="40" r="35" fill="none" stroke="#fff" stroke-width="3"
                                                        stroke-dasharray="<?php echo 2 * pi() * 35; ?>"
                                                        stroke-dashoffset="<?php echo 2 * pi() * 35 * (1 - $gamificationData['experience_percentage'] / 100); ?>"
                                                        transform="rotate(-90 40 40)" class="progress-circle"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="text-primary mb-0"><?php echo $gamificationData['level_title']; ?></h6>
                            </div>
                            <div class="col-md-9">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-2">Experience Progress</h6>
                                        <div class="progress mb-2" style="height: 12px;">
                                            <div class="progress-bar bg-gradient bg-primary" role="progressbar"
                                                 style="width: <?php echo $gamificationData['experience_percentage']; ?>%"
                                                 data-bs-toggle="tooltip" title="<?php echo $gamificationData['experience_current']; ?>/1000 XP"></div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $gamificationData['experience_needed']; ?> XP needed for next level
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="text-center">
                                                    <h5 class="text-success mb-0"><?php echo $gamificationData['quality_score']; ?>%</h5>
                                                    <small class="text-muted">Quality Score</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-center">
                                                    <h5 class="text-danger mb-0"><?php echo $gamificationData['current_streak']; ?></h5>
                                                    <small class="text-muted">Current Streak</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Badges and Achievements -->
            <div class="col-md-6">
                <div class="card border border-warning bg-warning-subtle h-100">
                    <div class="card-header bg-warning text-white">
                        <h6 class="mb-0"><i class="fas fa-medal me-2"></i>Badges & Achievements</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($gamificationData['badges'])): ?>
                            <div class="row g-2">
                                <?php foreach ($gamificationData['badges'] as $badge): ?>
                                    <div class="col-12">
                                        <div class="d-flex align-items-center p-2 rounded bg-white shadow-sm hover-shadow">
                                            <div class="badge-icon me-3">
                                                <i class="fas <?php echo $badge['icon']; ?> fa-2x text-<?php echo $badge['color']; ?>"></i>
                                            </div>
                                            <div class="flex-1">
                                                <h6 class="mb-0 text-<?php echo $badge['color']; ?>"><?php echo $badge['name']; ?></h6>
                                                <small class="text-muted"><?php echo $badge['description']; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-medal fa-3x mb-3 opacity-25"></i>
                                <p>No badges earned yet. Complete more tasks to unlock achievements!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Performance Stats -->
            <div class="col-md-6">
                <div class="card border border-info bg-info-subtle h-100">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Performance Stats</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="stat-item text-center p-2 rounded bg-white">
                                    <i class="fas fa-tasks text-primary fa-2x mb-2"></i>
                                    <h5 class="mb-0 text-primary"><?php echo $writerAnalytics['total_tasks'] ?? 0; ?></h5>
                                    <small class="text-muted">Total Tasks</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-item text-center p-2 rounded bg-white">
                                    <i class="fas fa-percentage text-success fa-2x mb-2"></i>
                                    <h5 class="mb-0 text-success"><?php echo $writerAnalytics['completion_rate'] ?? 0; ?>%</h5>
                                    <small class="text-muted">Completion Rate</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-item text-center p-2 rounded bg-white">
                                    <i class="fas fa-fire text-danger fa-2x mb-2"></i>
                                    <h5 class="mb-0 text-danger"><?php echo $gamificationData['max_streak']; ?></h5>
                                    <small class="text-muted">Best Streak</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-item text-center p-2 rounded bg-white">
                                    <i class="fas fa-clock text-warning fa-2x mb-2"></i>
                                    <h5 class="mb-0 text-warning"><?php echo round($writerAnalytics['avg_completion_days'] ?? 0, 1); ?></h5>
                                    <small class="text-muted">Avg Days</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Task Performance -->
            <div class="col-12">
                <div class="card border border-success bg-success-subtle">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-star me-2"></i>Current Task Performance</h6>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4 text-center">
                                <div class="performance-score-display">
                                    <div class="score-circle position-relative mx-auto mb-3" style="width: 100px; height: 100px;">
                                        <svg width="100" height="100">
                                            <circle cx="50" cy="50" r="40" fill="none" stroke="#e9ecef" stroke-width="8"/>
                                            <circle cx="50" cy="50" r="40" fill="none" stroke="#28a745" stroke-width="8"
                                                    stroke-dasharray="<?php echo 2 * pi() * 40; ?>"
                                                    stroke-dashoffset="<?php echo 2 * pi() * 40 * (1 - min($gamificationData['current_task_score'], 150) / 150); ?>"
                                                    transform="rotate(-90 50 50)" class="score-progress"/>
                                        </svg>
                                        <div class="position-absolute top-50 start-50 translate-middle text-center">
                                            <h4 class="text-success mb-0"><?php echo $gamificationData['current_task_score']; ?></h4>
                                            <small class="text-muted">XP</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <h6 class="text-success mb-3">Points Breakdown:</h6>
                                <div class="row g-2">
                                    <?php foreach ($gamificationData['points_breakdown'] as $category => $points): ?>
                                        <?php if ($points != 0): ?>
                                            <div class="col-md-6">
                                                <div class="d-flex justify-content-between align-items-center p-2 rounded bg-white">
                                                    <span class="text-capitalize"><?php echo str_replace('_', ' ', $category); ?>:</span>
                                                    <span class="fw-bold <?php echo $points > 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo $points > 0 ? '+' : ''; ?><?php echo $points; ?> XP
                                                        </span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    </div>
<?php endif; ?>

<script>
    // Gamification Functions
    function toggleGamification() {
        const content = document.getElementById('gamification-content');
        const icon = document.getElementById('gamification-toggle-icon');

        if (content.style.display === 'none') {
            content.style.display = 'block';
            content.style.animation = 'slideDown 0.4s ease';
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');

            // Trigger progress animations
            setTimeout(() => {
                animateProgressCircles();
                animateCounters();
            }, 200);
        } else {
            content.style.animation = 'slideUp 0.4s ease';
            setTimeout(() => {
                content.style.display = 'none';
            }, 400);
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    }

    function animateProgressCircles() {
        const progressCircles = document.querySelectorAll('.progress-circle, .score-progress');
        progressCircles.forEach(circle => {
            const circumference = 2 * Math.PI * 35; // Assuming radius 35
            circle.style.strokeDasharray = circumference;
            circle.style.strokeDashoffset = circumference;

            setTimeout(() => {
                const progress = circle.getAttribute('data-progress') || 0;
                const offset = circumference - (progress / 100) * circumference;
                circle.style.strokeDashoffset = offset;
            }, 100);
        });
    }

    function animateCounters() {
        const counters = document.querySelectorAll('.stat-item h5');
        counters.forEach(counter => {
            const target = parseInt(counter.textContent);
            let current = 0;
            const increment = target / 30; // 30 frames for smooth animation

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    counter.textContent = target;
                    clearInterval(timer);
                } else {
                    counter.textContent = Math.floor(current);
                }
            }, 50);
        });
    }

    function showXPGain(amount, element) {
        const xpIndicator = document.createElement('div');
        xpIndicator.className = 'position-absolute badge bg-success text-white xp-gain';
        xpIndicator.style.cssText = `
                top: -30px;
                right: -10px;
                z-index: 1000;
                font-size: 12px;
                pointer-events: none;
            `;
        xpIndicator.textContent = `+${amount} XP`;

        element.style.position = 'relative';
        element.appendChild(xpIndicator);

        setTimeout(() => {
            xpIndicator.style.opacity = '0';
            xpIndicator.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                if (xpIndicator.parentNode) {
                    xpIndicator.parentNode.removeChild(xpIndicator);
                }
            }, 300);
        }, 2000);
    }

    function showAchievementUnlock(badge) {
        // Create achievement notification
        const notification = document.createElement('div');
        notification.className = 'achievement-notification position-fixed';
        notification.style.cssText = `
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 9999;
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                padding: 30px;
                border-radius: 20px;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                min-width: 300px;
            `;

        notification.innerHTML = `
                <div class="achievement-unlock">
                    <i class="fas ${badge.icon} fa-4x mb-3 text-warning"></i>
                    <h4 class="text-white mb-2">Achievement Unlocked!</h4>
                    <h5 class="text-warning mb-2">${badge.name}</h5>
                    <p class="text-white-50 mb-0">${badge.description}</p>
                </div>
            `;

        document.body.appendChild(notification);

        // Auto-remove after 4 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translate(-50%, -50%) scale(0.8)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 4000);

        // Add confetti effect
        createConfetti();
    }

    function createConfetti() {
        const colors = ['#667eea', '#764ba2', '#ffd700', '#ff6b6b', '#4ecdc4'];
        const confettiContainer = document.createElement('div');
        confettiContainer.className = 'confetti-container';
        confettiContainer.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9998;
                pointer-events: none;
            `;

        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.style.cssText = `
                    position: absolute;
                    width: 10px;
                    height: 10px;
                    background: ${colors[Math.floor(Math.random() * colors.length)]};
                    top: -10px;
                    left: ${Math.random() * 100}%;
                    animation: confettiFall ${2 + Math.random() * 3}s linear forwards;
                    transform: rotate(${Math.random() * 360}deg);
                `;
            confettiContainer.appendChild(confetti);
        }

        document.body.appendChild(confettiContainer);

        setTimeout(() => {
            if (confettiContainer.parentNode) {
                confettiContainer.parentNode.removeChild(confettiContainer);
            }
        }, 5000);
    }

    function updateWriterLevel(newLevel, oldLevel) {
        if (newLevel > oldLevel) {
            showLevelUp(newLevel);
        }
    }

    function showLevelUp(level) {
        const levelUpNotification = document.createElement('div');
        levelUpNotification.className = 'level-up-notification position-fixed';
        levelUpNotification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 9999;
                background: linear-gradient(45deg, #ffd700, #ffed4e);
                color: #333;
                padding: 20px 30px;
                border-radius: 15px;
                box-shadow: 0 15px 35px rgba(255, 215, 0, 0.3);
                animation: levelUpSlide 0.5s ease-out;
            `;

        levelUpNotification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-level-up-alt fa-2x text-warning me-3"></i>
                    <div>
                        <h5 class="mb-0 text-dark">Level Up!</h5>
                        <p class="mb-0 text-dark">You've reached Level ${level}!</p>
                    </div>
                </div>
            `;

        document.body.appendChild(levelUpNotification);

        setTimeout(() => {
            levelUpNotification.style.animation = 'levelUpSlide 0.5s ease-out reverse';
            setTimeout(() => {
                if (levelUpNotification.parentNode) {
                    levelUpNotification.parentNode.removeChild(levelUpNotification);
                }
            }, 500);
        }, 4000);
    }

    function trackTaskCompletion() {
        const taskId = <?php echo $taskId; ?>;
        const taskScore = <?php echo isset($gamificationData['current_task_score']) ? $gamificationData['current_task_score'] : 0; ?>;

        if (taskScore > 0) {
            // Send XP update to server
            fetch('update-gamification', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    task_id: taskId,
                    action: 'award_xp',
                    xp_amount: taskScore
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show XP gain animation
                        const scoreElement = document.querySelector('.performance-score-display');
                        if (scoreElement) {
                            showXPGain(taskScore, scoreElement);
                        }

                        // Check for level up
                        if (data.level_up) {
                            updateWriterLevel(data.new_level, data.old_level);
                        }

                        // Check for new achievements
                        if (data.new_achievements && data.new_achievements.length > 0) {
                            data.new_achievements.forEach((achievement, index) => {
                                setTimeout(() => {
                                    showAchievementUnlock(achievement);
                                }, index * 1000);
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating gamification:', error);
                });
        }
    }

    // Enhanced CSS animations for gamification
    function injectGamificationStyles() {
        const style = document.createElement('style');
        style.textContent = `
                @keyframes confettiFall {
                    to {
                        transform: translateY(100vh) rotate(720deg);
                        opacity: 0;
                    }
                }

                @keyframes levelUpSlide {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }

                .hover-lift-strong {
                    transition: all 0.3s ease;
                }

                .hover-lift-strong:hover {
                    transform: translateY(-8px);
                    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
                }
            `;
        document.head.appendChild(style);
    }

    // Initialize gamification features
    function initializeGamification() {
        injectGamificationStyles();

        // Auto-expand gamification if writer has achievements
        <?php if (!empty($gamificationData['badges']) || $gamificationData['current_task_score'] > 100): ?>
        setTimeout(() => {
            toggleGamification();
        }, 1000);
        <?php endif; ?>

        // Track task completion if status changed
        const taskStatus = '<?php echo $taskStatus; ?>';
        if (taskStatus === 'Completed' || taskStatus === 'Submitted') {
            setTimeout(() => {
                trackTaskCompletion();
            }, 2000);
        }
    }

    // Add gamification initialization to existing DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        initializeGamification();
    });
</script>
<?php
include "footer.php";
?>