<?php
// writer-performance-functions.php
// Writer-facing counterpart to sudo/writer-performance-functions.php - same
// tbl_writer_performance/tbl_writer_levels tables, so a writer sees the exact
// level/metrics an admin already sees when viewing their profile. Trimmed to
// the functions the writer's own profile page actually uses (no bonus
// calculation/save - those are admin-triggered jobs, not a read-only view).

/**
 * Calculate comprehensive writer performance metrics
 */
function calculateWriterPerformance($con, $writerEmail) {
    $tasksQuery = "SELECT
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_tasks,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN status = 'Completed' AND submitted_on < due_date THEN 1 ELSE 0 END) as early_completions,
        SUM(CASE WHEN status = 'Completed' AND submitted_on = due_date THEN 1 ELSE 0 END) as on_time_completions,
        SUM(CASE WHEN status = 'Completed' AND submitted_on > due_date THEN 1 ELSE 0 END) as late_completions,
        AVG(CASE WHEN status = 'Completed' AND submitted_on IS NOT NULL AND create_date IS NOT NULL
            THEN DATEDIFF(submitted_on, create_date) END) as avg_completion_days,
        SUM(CASE WHEN status = 'Completed' AND is_paid = 1 THEN (pages * cpp) ELSE 0 END) as total_earnings
        FROM tbltasks
        WHERE email = ? AND is_deleted = 0";

    $stmt = mysqli_prepare($con, $tasksQuery);
    mysqli_stmt_bind_param($stmt, 's', $writerEmail);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $performance = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $performance['total_tasks'] = intval($performance['total_tasks'] ?? 0);
    $performance['completed_tasks'] = intval($performance['completed_tasks'] ?? 0);
    $performance['cancelled_tasks'] = intval($performance['cancelled_tasks'] ?? 0);
    $performance['in_progress_tasks'] = intval($performance['in_progress_tasks'] ?? 0);
    $performance['on_time_completions'] = intval($performance['on_time_completions'] ?? 0);
    $performance['early_completions'] = intval($performance['early_completions'] ?? 0);
    $performance['late_completions'] = intval($performance['late_completions'] ?? 0);
    $performance['avg_completion_days'] = floatval($performance['avg_completion_days'] ?? 0);
    $performance['total_earnings'] = floatval($performance['total_earnings'] ?? 0);

    $totalTasks = max(1, $performance['total_tasks'] - $performance['cancelled_tasks']); // Exclude cancelled from calculations
    $completedTasks = $performance['completed_tasks'];

    $performance['completion_rate'] = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;
    $performance['on_time_rate'] = $completedTasks > 0 ? round(($performance['on_time_completions'] / $completedTasks) * 100, 2) : 0;
    $performance['avg_completion_days'] = round($performance['avg_completion_days'], 2);

    $performance['current_level'] = getWriterLevel($con, $completedTasks);

    return $performance;
}

/**
 * Get writer level based on completed tasks
 */
function getWriterLevel($con, $completedTasks) {
    $completedTasks = intval($completedTasks ?? 0);

    $levelQuery = "SELECT level_number, level_name, icon_class, icon_color
                   FROM tbl_writer_levels
                   WHERE ? >= min_completed_tasks
                   AND (max_completed_tasks IS NULL OR ? <= max_completed_tasks)
                   ORDER BY level_number DESC
                   LIMIT 1";

    $stmt = mysqli_prepare($con, $levelQuery);
    mysqli_stmt_bind_param($stmt, 'ii', $completedTasks, $completedTasks);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $level = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $level ?: ['level_number' => 1, 'level_name' => 'Rookie', 'icon_class' => 'fas fa-seedling', 'icon_color' => '#28a745'];
}

/**
 * Update or insert writer performance data - keeps tbl_writer_performance in
 * sync with the same table the admin side reads/ranks from.
 */
function updateWriterPerformance($con, $writerId, $writerEmail) {
    $writerId = intval($writerId ?? 0);
    $writerEmail = trim($writerEmail ?? '');

    if (empty($writerEmail)) {
        return false;
    }

    $performance = calculateWriterPerformance($con, $writerEmail);

    $totalTasks = intval($performance['total_tasks'] ?? 0);
    $completedTasks = intval($performance['completed_tasks'] ?? 0);
    $cancelledTasks = intval($performance['cancelled_tasks'] ?? 0);
    $inProgressTasks = intval($performance['in_progress_tasks'] ?? 0);
    $onTimeCompletions = intval($performance['on_time_completions'] ?? 0);
    $earlyCompletions = intval($performance['early_completions'] ?? 0);
    $lateCompletions = intval($performance['late_completions'] ?? 0);
    $completionRate = floatval($performance['completion_rate'] ?? 0.0);
    $onTimeRate = floatval($performance['on_time_rate'] ?? 0.0);
    $avgCompletionDays = floatval($performance['avg_completion_days'] ?? 0.0);
    $currentLevel = intval($performance['current_level']['level_number'] ?? 1);
    $totalEarnings = floatval($performance['total_earnings'] ?? 0.0);

    $updateQuery = "INSERT INTO tbl_writer_performance
        (writer_id, writer_email, total_tasks, completed_tasks, cancelled_tasks, in_progress_tasks,
         on_time_completions, early_completions, late_completions, completion_rate, on_time_rate,
         average_completion_days, current_level, total_earnings)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        total_tasks = VALUES(total_tasks),
        completed_tasks = VALUES(completed_tasks),
        cancelled_tasks = VALUES(cancelled_tasks),
        in_progress_tasks = VALUES(in_progress_tasks),
        on_time_completions = VALUES(on_time_completions),
        early_completions = VALUES(early_completions),
        late_completions = VALUES(late_completions),
        completion_rate = VALUES(completion_rate),
        on_time_rate = VALUES(on_time_rate),
        average_completion_days = VALUES(average_completion_days),
        current_level = VALUES(current_level),
        total_earnings = VALUES(total_earnings)";

    $stmt = mysqli_prepare($con, $updateQuery);
    mysqli_stmt_bind_param($stmt, 'isiiiiiiiiddid',
        $writerId, $writerEmail, $totalTasks, $completedTasks, $cancelledTasks, $inProgressTasks,
        $onTimeCompletions, $earlyCompletions, $lateCompletions, $completionRate, $onTimeRate,
        $avgCompletionDays, $currentLevel, $totalEarnings
    );

    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $success;
}

/**
 * Get next level requirements for a writer
 */
function getNextLevelRequirements($con, $currentCompletedTasks) {
    $currentCompletedTasks = intval($currentCompletedTasks ?? 0);

    $nextLevelQuery = "SELECT level_number, level_name, min_completed_tasks, icon_class, icon_color
                       FROM tbl_writer_levels
                       WHERE min_completed_tasks > ?
                       ORDER BY level_number ASC
                       LIMIT 1";

    $stmt = mysqli_prepare($con, $nextLevelQuery);
    mysqli_stmt_bind_param($stmt, 'i', $currentCompletedTasks);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $nextLevel = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $nextLevel;
}

/**
 * Calculate progress to next level
 */
function calculateLevelProgress($con, $completedTasks) {
    $completedTasks = intval($completedTasks ?? 0);

    $currentLevel = getWriterLevel($con, $completedTasks);
    $nextLevel = getNextLevelRequirements($con, $completedTasks);

    if (!$nextLevel) {
        return ['progress' => 100, 'tasks_remaining' => 0]; // Max level reached
    }

    $currentLevelQuery = "SELECT min_completed_tasks FROM tbl_writer_levels WHERE level_number = ?";
    $stmt = mysqli_prepare($con, $currentLevelQuery);
    $levelNum = intval($currentLevel['level_number'] ?? 1);
    mysqli_stmt_bind_param($stmt, 'i', $levelNum);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $currentLevelData = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $currentLevelMin = intval($currentLevelData['min_completed_tasks'] ?? 0);

    $tasksInCurrentLevel = $completedTasks - $currentLevelMin;
    $tasksRequiredForNext = intval($nextLevel['min_completed_tasks']) - $currentLevelMin;
    $progress = $tasksRequiredForNext > 0 ? ($tasksInCurrentLevel / $tasksRequiredForNext) * 100 : 100;
    $tasksRemaining = max(0, intval($nextLevel['min_completed_tasks']) - $completedTasks);

    return [
        'progress' => min(100, round($progress, 1)),
        'tasks_remaining' => $tasksRemaining,
        'next_level' => $nextLevel
    ];
}
