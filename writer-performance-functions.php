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
    // Get all tasks for this writer (excluding deleted and cancelled)
    $baseSelect = "COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_tasks,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN status = 'Completed' AND submitted_on < due_date THEN 1 ELSE 0 END) as early_completions,
        SUM(CASE WHEN status = 'Completed' AND submitted_on = due_date THEN 1 ELSE 0 END) as on_time_completions,
        SUM(CASE WHEN status = 'Completed' AND submitted_on > due_date THEN 1 ELSE 0 END) as late_completions,
        AVG(CASE WHEN status = 'Completed' AND submitted_on IS NOT NULL AND create_date IS NOT NULL
            THEN DATEDIFF(submitted_on, create_date) END) as avg_completion_days,
        SUM(CASE WHEN status = 'Completed' AND is_paid = 1 THEN (pages * cpp) ELSE 0 END) as total_earnings";
    $where = "FROM tbltasks WHERE email = ? AND is_deleted = 0";

    // quality_rating (admin's 1-5 stars per completed task) arrives with
    // db-migrations/2026_09_18_add_task_quality_rating.sql; fall back to the
    // pre-rating query until it has been run.
    try {
        $stmt = mysqli_prepare($con, "SELECT $baseSelect,
            AVG(CASE WHEN status = 'Completed' THEN quality_rating END) as average_quality_rating,
            COUNT(CASE WHEN status = 'Completed' THEN quality_rating END) as rated_tasks
            $where");
    } catch (\mysqli_sql_exception $e) {
        $stmt = mysqli_prepare($con, "SELECT $baseSelect, NULL as average_quality_rating, 0 as rated_tasks $where");
    }
    mysqli_stmt_bind_param($stmt, 's', $writerEmail);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $performance = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Ensure all values are properly set with defaults for null values
    $performance['total_tasks'] = intval($performance['total_tasks'] ?? 0);
    $performance['completed_tasks'] = intval($performance['completed_tasks'] ?? 0);
    $performance['cancelled_tasks'] = intval($performance['cancelled_tasks'] ?? 0);
    $performance['in_progress_tasks'] = intval($performance['in_progress_tasks'] ?? 0);
    $performance['on_time_completions'] = intval($performance['on_time_completions'] ?? 0);
    $performance['early_completions'] = intval($performance['early_completions'] ?? 0);
    $performance['late_completions'] = intval($performance['late_completions'] ?? 0);
    $performance['avg_completion_days'] = floatval($performance['avg_completion_days'] ?? 0);
    $performance['total_earnings'] = floatval($performance['total_earnings'] ?? 0);
    $performance['rated_tasks'] = intval($performance['rated_tasks'] ?? 0);
    // null (not 0) when nothing has been rated yet, so callers can tell
    // "unrated" apart from "rated terribly".
    $performance['average_quality_rating'] = ($performance['rated_tasks'] > 0 && $performance['average_quality_rating'] !== null)
        ? round(floatval($performance['average_quality_rating']), 2) : null;

    // Calculate rates
    $totalTasks = max(1, $performance['total_tasks'] - $performance['cancelled_tasks']); // Exclude cancelled from calculations
    $completedTasks = $performance['completed_tasks'];

    $performance['completion_rate'] = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;
    $performance['on_time_rate'] = $completedTasks > 0 ? round(($performance['on_time_completions'] / $completedTasks) * 100, 2) : 0;
    $performance['avg_completion_days'] = round($performance['avg_completion_days'], 2);

    // Determine current level (task count + optional per-level quality gate)
    $performance['current_level'] = getWriterLevel($con, $completedTasks, $performance['average_quality_rating']);

    return $performance;
}

/**
 * Get writer level based on completed tasks
 */
function getWriterLevel($con, $completedTasks, $avgQualityRating = null) {
    // Ensure completed tasks is never null
    $completedTasks = intval($completedTasks ?? 0);

    // A level may carry an optional min_quality_rating (set on
    // sudo/level-management.php). It only bites once the writer has rated
    // tasks - a null average never blocks a level. If the gate knocks the
    // writer out of their task-count range, they hold the highest lower
    // level whose gate they do pass.
    $hasRating = $avgQualityRating !== null ? 1 : 0;
    $avg = $avgQualityRating !== null ? (float) $avgQualityRating : 0.0;
    $gate = "AND (min_quality_rating IS NULL OR ? = 0 OR ? >= min_quality_rating)";
    $cols = "level_number, level_name, icon_class, icon_color, min_quality_rating";
    try {
        $stmt = mysqli_prepare($con, "SELECT $cols FROM tbl_writer_levels
                   WHERE ? >= min_completed_tasks
                   AND (max_completed_tasks IS NULL OR ? <= max_completed_tasks)
                   $gate ORDER BY level_number DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'iiid', $completedTasks, $completedTasks, $hasRating, $avg);
        mysqli_stmt_execute($stmt);
        $level = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$level && $hasRating) {
            $stmt = mysqli_prepare($con, "SELECT $cols FROM tbl_writer_levels
                       WHERE ? >= min_completed_tasks $gate ORDER BY level_number DESC LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'iid', $completedTasks, $hasRating, $avg);
            mysqli_stmt_execute($stmt);
            $level = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
        }
    } catch (\mysqli_sql_exception $e) {
        // min_quality_rating column not migrated yet - task count only
        $stmt = mysqli_prepare($con, "SELECT level_number, level_name, icon_class, icon_color, NULL as min_quality_rating
                   FROM tbl_writer_levels
                   WHERE ? >= min_completed_tasks
                   AND (max_completed_tasks IS NULL OR ? <= max_completed_tasks)
                   ORDER BY level_number DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ii', $completedTasks, $completedTasks);
        mysqli_stmt_execute($stmt);
        $level = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }

    return $level ?: ['level_number' => 1, 'level_name' => 'Rookie', 'icon_class' => 'fas fa-seedling', 'icon_color' => '#28a745', 'min_quality_rating' => null];
}

/**
 * Update or insert writer performance data - keeps tbl_writer_performance in
 * sync with the same table the admin side reads/ranks from.
 */
function updateWriterPerformance($con, $writerId, $writerEmail) {
    // Ensure parameters are not null
    $writerId = intval($writerId ?? 0);
    $writerEmail = trim($writerEmail ?? '');

    // Skip if writer email is empty
    if (empty($writerEmail)) {
        return false;
    }

    $performance = calculateWriterPerformance($con, $writerEmail);

    // Ensure all required values have defaults
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
    $avgQuality = $performance['average_quality_rating']; // null until something is rated
    $ratedTasks = intval($performance['rated_tasks'] ?? 0);

    $baseCols = "writer_id, writer_email, total_tasks, completed_tasks, cancelled_tasks, in_progress_tasks,
         on_time_completions, early_completions, late_completions, completion_rate, on_time_rate,
         average_completion_days, current_level, total_earnings";
    $baseUpdate = "total_tasks = VALUES(total_tasks),
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

    // average_quality_rating / rated_tasks columns arrive with
    // db-migrations/2026_09_18_add_task_quality_rating.sql; until then the
    // pre-rating statement keeps the page working.
    try {
        $stmt = mysqli_prepare($con, "INSERT INTO tbl_writer_performance
            ($baseCols, average_quality_rating, rated_tasks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE $baseUpdate,
            average_quality_rating = VALUES(average_quality_rating),
            rated_tasks = VALUES(rated_tasks)");
        // 16 params: i,s,i,i,i,i,i,i,i,d,d,d,i,d,d,i
        mysqli_stmt_bind_param($stmt, 'isiiiiiiidddiddi',
            $writerId, $writerEmail, $totalTasks, $completedTasks, $cancelledTasks, $inProgressTasks,
            $onTimeCompletions, $earlyCompletions, $lateCompletions, $completionRate, $onTimeRate,
            $avgCompletionDays, $currentLevel, $totalEarnings, $avgQuality, $ratedTasks
        );
    } catch (\mysqli_sql_exception $e) {
        $stmt = mysqli_prepare($con, "INSERT INTO tbl_writer_performance
            ($baseCols)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE $baseUpdate");
        // 14 params: i,s,i,i,i,i,i,i,i,d,d,d,i,d
        mysqli_stmt_bind_param($stmt, 'isiiiiiiidddid',
            $writerId, $writerEmail, $totalTasks, $completedTasks, $cancelledTasks, $inProgressTasks,
            $onTimeCompletions, $earlyCompletions, $lateCompletions, $completionRate, $onTimeRate,
            $avgCompletionDays, $currentLevel, $totalEarnings
        );
    }

    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $success;
}

/**
 * Get next level requirements for a writer
 */
function getNextLevelRequirements($con, $currentCompletedTasks) {
    $currentCompletedTasks = intval($currentCompletedTasks ?? 0);

    $base = "FROM tbl_writer_levels WHERE min_completed_tasks > ? ORDER BY level_number ASC LIMIT 1";
    try {
        $stmt = mysqli_prepare($con, "SELECT level_number, level_name, min_completed_tasks, icon_class, icon_color, min_quality_rating $base");
    } catch (\mysqli_sql_exception $e) {
        // min_quality_rating column not migrated yet
        $stmt = mysqli_prepare($con, "SELECT level_number, level_name, min_completed_tasks, icon_class, icon_color, NULL as min_quality_rating $base");
    }
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
function calculateLevelProgress($con, $completedTasks, $avgQualityRating = null) {
    $completedTasks = intval($completedTasks ?? 0);

    $currentLevel = getWriterLevel($con, $completedTasks, $avgQualityRating);
    $nextLevel = getNextLevelRequirements($con, $completedTasks);

    if (!$nextLevel) {
        return ['progress' => 100, 'tasks_remaining' => 0, 'quality_required' => null, 'quality_met' => true]; // Max level reached
    }

    // Get current level minimum tasks
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

    // Quality gate on the next level: only enforced once the writer has
    // rated tasks (see getWriterLevel()).
    $qualityRequired = isset($nextLevel['min_quality_rating']) && $nextLevel['min_quality_rating'] !== null ? (float) $nextLevel['min_quality_rating'] : null;
    $qualityMet = $qualityRequired === null || $avgQualityRating === null || (float) $avgQualityRating >= $qualityRequired;

    return [
        'progress' => min(100, round($progress, 1)),
        'tasks_remaining' => $tasksRemaining,
        'next_level' => $nextLevel,
        'quality_required' => $qualityRequired,
        'quality_met' => $qualityMet
    ];
}
