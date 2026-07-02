<?php
// writer-performance-functions.php

/**
 * Calculate comprehensive writer performance metrics
 */
function calculateWriterPerformance($con, $writerEmail) {
    // Get all tasks for this writer (excluding deleted and cancelled)
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

    // Calculate rates
    $totalTasks = max(1, $performance['total_tasks'] - $performance['cancelled_tasks']); // Exclude cancelled from calculations
    $completedTasks = $performance['completed_tasks'];

    $performance['completion_rate'] = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;
    $performance['on_time_rate'] = $completedTasks > 0 ? round(($performance['on_time_completions'] / $completedTasks) * 100, 2) : 0;
    $performance['avg_completion_days'] = round($performance['avg_completion_days'], 2);

    // Determine current level
    $performance['current_level'] = getWriterLevel($con, $completedTasks);

    return $performance;
}

/**
 * Get writer level based on completed tasks
 */
function getWriterLevel($con, $completedTasks) {
    // Ensure completed tasks is never null
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
 * Update or insert writer performance data
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

    // Fixed: 14 parameters, 14 type characters: i,s,i,i,i,i,i,i,i,d,d,d,i,d
    mysqli_stmt_bind_param($stmt, 'isiiiiiiiiddid',
        $writerId,                                      // i - integer
        $writerEmail,                                   // s - string
        $totalTasks,                                    // i - integer
        $completedTasks,                                // i - integer
        $cancelledTasks,                                // i - integer
        $inProgressTasks,                               // i - integer
        $onTimeCompletions,                             // i - integer
        $earlyCompletions,                              // i - integer
        $lateCompletions,                               // i - integer
        $completionRate,                                // d - double
        $onTimeRate,                                    // d - double
        $avgCompletionDays,                             // d - double
        $currentLevel,                                  // i - integer
        $totalEarnings                                  // d - double
    );

    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $success;
}

/**
 * Fixed calculateMonthlyBonus function
 */
function calculateMonthlyBonus($con, $writerEmail, $month, $year) {
    // Ensure parameters are valid
    $writerEmail = trim($writerEmail ?? '');
    $month = intval($month ?? 0);
    $year = intval($year ?? 0);

    if (empty($writerEmail) || $month < 1 || $month > 12 || $year < 2020) {
        return [
            'total_tasks_completed' => 0,
            'tasks_completed_on_time' => 0,
            'tasks_completed_early' => 0,
            'tasks_completed_late' => 0,
            'total_earnings' => 0.0,
            'early_earnings' => 0.0,
            'on_time_earnings' => 0.0,
            'late_earnings' => 0.0,
            'base_bonus_amount' => 0.0,
            'early_completion_bonus' => 0.0,
            'perfect_month_bonus' => 0.0,
            'total_bonus_amount' => 0.0,
            'bonus_percentage' => 0.0
        ];
    }

    // Get bonus settings
    $settingsQuery = "SELECT setting_name, setting_value FROM tbl_bonus_settings WHERE is_active = 1";
    $settingsResult = mysqli_query($con, $settingsQuery);
    $settings = [];

    if ($settingsResult) {
        while ($row = mysqli_fetch_assoc($settingsResult)) {
            $settings[$row['setting_name']] = floatval($row['setting_value']);
        }
    }

    $monthlyQuery = "SELECT 
        COUNT(*) as total_completed,
        SUM(CASE WHEN submitted_on < due_date THEN 1 ELSE 0 END) as early_completions,
        SUM(CASE WHEN submitted_on = due_date THEN 1 ELSE 0 END) as on_time_completions,
        SUM(CASE WHEN submitted_on > due_date THEN 1 ELSE 0 END) as late_completions,
        SUM(pages * cpp) as total_earnings,
        SUM(CASE WHEN submitted_on < due_date THEN (pages * cpp) ELSE 0 END) as early_earnings,
        SUM(CASE WHEN submitted_on = due_date THEN (pages * cpp) ELSE 0 END) as on_time_earnings,
        SUM(CASE WHEN submitted_on > due_date THEN (pages * cpp) ELSE 0 END) as late_earnings
        FROM tbltasks 
        WHERE email = ? 
        AND status IN ('Completed', 'Submitted')
        AND MONTH(submitted_on) = ? 
        AND YEAR(submitted_on) = ?
        AND is_deleted = 0";

    $stmt = mysqli_prepare($con, $monthlyQuery);
    if (!$stmt) {
        error_log("ERROR: Failed to prepare monthly query: " . mysqli_error($con));
        return [
            'total_tasks_completed' => 0,
            'tasks_completed_on_time' => 0,
            'tasks_completed_early' => 0,
            'tasks_completed_late' => 0,
            'total_earnings' => 0.0,
            'early_earnings' => 0.0,
            'on_time_earnings' => 0.0,
            'late_earnings' => 0.0,
            'base_bonus_amount' => 0.0,
            'early_completion_bonus' => 0.0,
            'perfect_month_bonus' => 0.0,
            'total_bonus_amount' => 0.0,
            'bonus_percentage' => 0.0
        ];
    }

    mysqli_stmt_bind_param($stmt, 'sii', $writerEmail, $month, $year);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $monthlyData = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $totalCompleted = intval($monthlyData['total_completed'] ?? 0);
    $onTimeCompletions = intval($monthlyData['on_time_completions'] ?? 0);
    $earlyCompletions = intval($monthlyData['early_completions'] ?? 0);
    $lateCompletions = intval($monthlyData['late_completions'] ?? 0);
    $totalEarnings = floatval($monthlyData['total_earnings'] ?? 0);
    $earlyEarnings = floatval($monthlyData['early_earnings'] ?? 0);
    $onTimeEarnings = floatval($monthlyData['on_time_earnings'] ?? 0);
    $lateEarnings = floatval($monthlyData['late_earnings'] ?? 0);

    // Calculate bonuses
    $baseBonusPercentage = floatval($settings['base_bonus_percentage'] ?? 5.0);
    $earlyBonusPercentage = floatval($settings['early_completion_bonus'] ?? 2.5);
    $perfectMonthPercentage = floatval($settings['perfect_month_bonus'] ?? 10.0);

    $baseBonusAmount = ($totalEarnings * $baseBonusPercentage) / 100;
    $earlyBonusAmount = ($earlyEarnings * $earlyBonusPercentage) / 100;

    // Perfect month bonus if all tasks completed on time or early (no late tasks)
    $perfectMonthBonus = ($totalCompleted > 0 && $lateCompletions == 0) ?
        ($totalEarnings * $perfectMonthPercentage) / 100 : 0;

    $totalBonusAmount = $baseBonusAmount + $earlyBonusAmount + $perfectMonthBonus;
    $bonusPercentage = $totalEarnings > 0 ? ($totalBonusAmount / $totalEarnings) * 100 : 0;

    return [
        'total_tasks_completed' => $totalCompleted,
        'tasks_completed_on_time' => $onTimeCompletions,
        'tasks_completed_early' => $earlyCompletions,
        'tasks_completed_late' => $lateCompletions,
        'total_earnings' => $totalEarnings,
        'early_earnings' => $earlyEarnings,
        'on_time_earnings' => $onTimeEarnings,
        'late_earnings' => $lateEarnings,
        'base_bonus_amount' => $baseBonusAmount,
        'early_completion_bonus' => $earlyBonusAmount,
        'perfect_month_bonus' => $perfectMonthBonus,
        'total_bonus_amount' => $totalBonusAmount,
        'bonus_percentage' => round($bonusPercentage, 2)
    ];
}

/**
 * Save monthly bonus calculation
 */
function saveMonthlyBonus($con, $writerId, $writerEmail, $month, $year) {
    // Ensure parameters are valid
    $writerId = intval($writerId ?? 0);
    $writerEmail = trim($writerEmail ?? '');
    $month = intval($month ?? 0);
    $year = intval($year ?? 0);

    if ($writerId <= 0 || empty($writerEmail) || $month < 1 || $month > 12 || $year < 2020) {
        return false;
    }

    $bonusData = calculateMonthlyBonus($con, $writerEmail, $month, $year);

    if ($bonusData['total_tasks_completed'] == 0) {
        return false; // No tasks completed this month
    }

    $insertQuery = "INSERT INTO tbl_monthly_bonuses 
        (writer_id, writer_email, month, year, total_tasks_completed, tasks_completed_on_time,
         tasks_completed_early, tasks_completed_late, total_earnings, early_earnings, on_time_earnings, late_earnings,
         base_bonus_amount, early_completion_bonus, perfect_month_bonus, total_bonus_amount, bonus_percentage)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        total_tasks_completed = VALUES(total_tasks_completed),
        tasks_completed_on_time = VALUES(tasks_completed_on_time),
        tasks_completed_early = VALUES(tasks_completed_early),
        tasks_completed_late = VALUES(tasks_completed_late),
        total_earnings = VALUES(total_earnings),
        early_earnings = VALUES(early_earnings),
        on_time_earnings = VALUES(on_time_earnings),
        late_earnings = VALUES(late_earnings),
        base_bonus_amount = VALUES(base_bonus_amount),
        early_completion_bonus = VALUES(early_completion_bonus),
        perfect_month_bonus = VALUES(perfect_month_bonus),
        total_bonus_amount = VALUES(total_bonus_amount),
        bonus_percentage = VALUES(bonus_percentage)";

    $stmt = mysqli_prepare($con, $insertQuery);
    mysqli_stmt_bind_param($stmt, 'isiiiiiiddddddddd',
        $writerId, $writerEmail, $month, $year, $bonusData['total_tasks_completed'],
        $bonusData['tasks_completed_on_time'], $bonusData['tasks_completed_early'],
        $bonusData['tasks_completed_late'], $bonusData['total_earnings'],
        $bonusData['early_earnings'], $bonusData['on_time_earnings'], $bonusData['late_earnings'],
        $bonusData['base_bonus_amount'], $bonusData['early_completion_bonus'],
        $bonusData['perfect_month_bonus'], $bonusData['total_bonus_amount'],
        $bonusData['bonus_percentage']
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

    return [
        'progress' => min(100, round($progress, 1)),
        'tasks_remaining' => $tasksRemaining,
        'next_level' => $nextLevel
    ];
}
?>