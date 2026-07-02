<?php
include_once('head.php');
include_once('writer-performance-functions.php');

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_settings') {
        $updates = [];
        $settingNames = ['base_bonus_percentage', 'early_completion_bonus', 'quality_bonus_threshold', 'quality_bonus_percentage', 'perfect_month_bonus'];

        foreach ($settingNames as $setting) {
            if (isset($_POST[$setting])) {
                $value = floatval($_POST[$setting]);
                $updateQuery = "UPDATE tbl_bonus_settings SET setting_value = ? WHERE setting_name = ?";
                $stmt = $con->prepare($updateQuery);
                $stmt->bind_param("ds", $value, $setting);
                $stmt->execute();
                $stmt->close();
            }
        }
        $successMessage = "Bonus settings updated successfully!";
    } elseif ($_POST['action'] == 'calculate_monthly_bonuses') {
        $month = intval($_POST['month']);
        $year = intval($_POST['year']);

        $debugInfo = [];
        $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));

        // Step 1: Check total tasks in the specified month
        $totalTasksQuery = "SELECT COUNT(*) as total_tasks 
                           FROM tbltasks 
                           WHERE MONTH(submitted_on) = ? 
                           AND YEAR(submitted_on) = ?
                           AND is_deleted = 0";
        $stmt = $con->prepare($totalTasksQuery);
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $totalTasksResult = $stmt->get_result();
        $totalTasks = $totalTasksResult->fetch_assoc()['total_tasks'];
        $stmt->close();

        $debugInfo[] = "Total tasks in {$monthName}: {$totalTasks}";

        // Step 2: Check completed/submitted tasks
        $completedTasksQuery = "SELECT COUNT(*) as completed_tasks 
                               FROM tbltasks 
                               WHERE status IN ('Completed', 'Submitted')
                               AND MONTH(submitted_on) = ? 
                               AND YEAR(submitted_on) = ?
                               AND is_deleted = 0";
        $stmt = $con->prepare($completedTasksQuery);
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $completedTasksResult = $stmt->get_result();
        $completedTasks = $completedTasksResult->fetch_assoc()['completed_tasks'];
        $stmt->close();

        $debugInfo[] = "Completed/Submitted tasks: {$completedTasks}";

        // Step 3: Check unique writers with completed tasks
        $uniqueWritersQuery = "SELECT COUNT(DISTINCT email) as unique_writers 
                              FROM tbltasks 
                              WHERE status IN ('Completed', 'Submitted')
                              AND MONTH(submitted_on) = ? 
                              AND YEAR(submitted_on) = ?
                              AND is_deleted = 0";
        $stmt = $con->prepare($uniqueWritersQuery);
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $uniqueWritersResult = $stmt->get_result();
        $uniqueWriters = $uniqueWritersResult->fetch_assoc()['unique_writers'];
        $stmt->close();

        $debugInfo[] = "Unique writers with completed tasks: {$uniqueWriters}";

        // Step 4: Check if these writers exist in tblwriters table
        $matchingWritersQuery = "SELECT COUNT(DISTINCT w.id) as matching_writers,
                                GROUP_CONCAT(DISTINCT t.email) as task_emails
                                FROM tbltasks t
                                LEFT JOIN tblwriters w ON w.email COLLATE utf8mb4_general_ci = t.email COLLATE utf8mb4_general_ci
                                WHERE t.status IN ('Completed', 'Submitted')
                                AND MONTH(t.submitted_on) = ? 
                                AND YEAR(t.submitted_on) = ?
                                AND t.is_deleted = 0";
        $stmt = $con->prepare($matchingWritersQuery);
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $matchingResult = $stmt->get_result();
        $matchingData = $matchingResult->fetch_assoc();
        $matchingWriters = $matchingData['matching_writers'];
        $taskEmails = $matchingData['task_emails'];
        $stmt->close();

        $debugInfo[] = "Writers found in tblwriters table: {$matchingWriters}";

        // Step 5: Show sample emails for debugging
        if ($taskEmails) {
            $emailArray = explode(',', $taskEmails);
            $sampleEmails = array_slice($emailArray, 0, 3); // Show first 3 emails
            $debugInfo[] = "Sample writer emails: " . implode(', ', $sampleEmails);
            if (count($emailArray) > 3) {
                $debugInfo[] = "... and " . (count($emailArray) - 3) . " more";
            }
        }

        // Step 6: Get all writers who completed tasks in the specified month (original query)
        $writersQuery = "SELECT DISTINCT w.id, w.email, w.username, w.FirstName, w.LastName 
                        FROM tblwriters w 
                        INNER JOIN tbltasks t ON w.email COLLATE utf8mb4_general_ci = t.email COLLATE utf8mb4_general_ci
                        WHERE t.status IN ('Completed', 'Submitted')
                        AND MONTH(t.submitted_on) = ? 
                        AND YEAR(t.submitted_on) = ?
                        AND t.is_deleted = 0";
        $stmt = $con->prepare($writersQuery);
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $writers = $stmt->get_result();

        $processedCount = 0;
        $processingErrors = [];

        while ($writer = $writers->fetch_assoc()) {
            try {
                if (saveMonthlyBonus($con, $writer['id'], $writer['email'], $month, $year)) {
                    $processedCount++;
                    $debugInfo[] = "✓ Processed bonus for: {$writer['FirstName']} {$writer['LastName']} ({$writer['email']})";
                } else {
                    $processingErrors[] = "✗ Failed to save bonus for: {$writer['FirstName']} {$writer['LastName']} ({$writer['email']})";
                }
            } catch (Exception $e) {
                $processingErrors[] = "✗ Error processing {$writer['email']}: " . $e->getMessage();
            }
        }
        $stmt->close();

        // Create detailed success message
        $successMessage = "<strong>Bonus Calculation Results for {$monthName}</strong><br><br>";

        // Add debug information
        $successMessage .= "<div class='small'>";
        foreach ($debugInfo as $info) {
            $successMessage .= "• {$info}<br>";
        }
        $successMessage .= "</div>";

        // Add processing errors if any
        if (!empty($processingErrors)) {
            $successMessage .= "<br><div class='text-warning'><strong>Processing Issues:</strong><br>";
            foreach ($processingErrors as $error) {
                $successMessage .= "• {$error}<br>";
            }
            $successMessage .= "</div>";
        }

        // Final result
        if ($processedCount > 0) {
            $successMessage .= "<br><div class='text-success'><strong>✓ Successfully calculated bonuses for {$processedCount} writers</strong></div>";
        } else {
            $successMessage .= "<br><div class='text-danger'><strong>⚠ No bonuses were calculated</strong></div>";

            // Provide troubleshooting suggestions
            $successMessage .= "<br><div class='small text-muted'>";
            $successMessage .= "<strong>Possible reasons:</strong><br>";
            if ($totalTasks == 0) {
                $successMessage .= "• No tasks found for {$monthName}<br>";
            } elseif ($completedTasks == 0) {
                $successMessage .= "• No completed/submitted tasks found for {$monthName}<br>";
            } elseif ($uniqueWriters == 0) {
                $successMessage .= "• No writers found with completed tasks<br>";
            } elseif ($matchingWriters == 0) {
                $successMessage .= "• Writers with completed tasks not found in tblwriters table<br>";
                $successMessage .= "• Check if email addresses match between tbltasks and tblwriters tables<br>";
            } else {
                $successMessage .= "• Bonus calculation function (saveMonthlyBonus) may have failed<br>";
                $successMessage .= "• Check database logs for detailed error information<br>";
            }
            $successMessage .= "</div>";
        }
    }
}

// Get current settings
$settingsQuery = "SELECT * FROM tbl_bonus_settings WHERE is_active = 1 ORDER BY setting_name";
$settingsResult = mysqli_query($con, $settingsQuery);
$settings = [];
while ($row = mysqli_fetch_assoc($settingsResult)) {
    $settings[$row['setting_name']] = $row;
}
?>

    <title>iTasker | Bonus Settings</title>
<?php include "navi.php"; ?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);"></div>
        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Bonus <span class="text-info fw-medium">Settings & Management</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-0">
                    <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#calculateBonusModal">
                        <i class="fas fa-calculator me-1"></i>Calculate Monthly Bonuses
                    </button>
                </div>
            </div>
        </div>
    </div>

<?php if (isset($successMessage)): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="fas fa-info-circle me-2"></i><?php echo $successMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

    <!-- Bonus Settings Configuration -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0 d-flex align-items-center">
                <i class="fas fa-cogs me-2 text-primary"></i>Bonus Configuration
            </h5>
            <p class="mb-0 text-muted">Configure bonus percentages and thresholds for writer performance rewards.</p>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_settings">

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card border-primary h-100">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fas fa-percentage me-1"></i>Base Bonus Settings</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="base_bonus_percentage" class="form-label">Base Bonus Percentage</label>
                                    <div class="input-group">
                                        <input type="number" step="0.1" class="form-control" name="base_bonus_percentage"
                                               id="base_bonus_percentage" value="<?php echo $settings['base_bonus_percentage']['setting_value'] ?? 5.0; ?>" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">Base bonus percentage for on-time task completion</small>
                                </div>

                                <div class="mb-3">
                                    <label for="early_completion_bonus" class="form-label">Early Completion Bonus</label>
                                    <div class="input-group">
                                        <input type="number" step="0.1" class="form-control" name="early_completion_bonus"
                                               id="early_completion_bonus" value="<?php echo $settings['early_completion_bonus']['setting_value'] ?? 2.5; ?>" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">Additional bonus for tasks completed before deadline</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card border-success h-100">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-star me-1"></i>Performance Bonuses</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="quality_bonus_threshold" class="form-label">Quality Bonus Threshold</label>
                                    <div class="input-group">
                                        <input type="number" step="0.1" class="form-control" name="quality_bonus_threshold"
                                               id="quality_bonus_threshold" value="<?php echo $settings['quality_bonus_threshold']['setting_value'] ?? 95.0; ?>" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">Quality score threshold for bonus eligibility</small>
                                </div>

                                <div class="mb-3">
                                    <label for="quality_bonus_percentage" class="form-label">Quality Bonus Percentage</label>
                                    <div class="input-group">
                                        <input type="number" step="0.1" class="form-control" name="quality_bonus_percentage"
                                               id="quality_bonus_percentage" value="<?php echo $settings['quality_bonus_percentage']['setting_value'] ?? 3.0; ?>" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">Additional bonus for high-quality work</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="card border-warning">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="fas fa-trophy me-1"></i>Perfect Month Bonus</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="perfect_month_bonus" class="form-label">Perfect Month Bonus</label>
                                            <div class="input-group">
                                                <input type="number" step="0.1" class="form-control" name="perfect_month_bonus"
                                                       id="perfect_month_bonus" value="<?php echo $settings['perfect_month_bonus']['setting_value'] ?? 10.0; ?>" required>
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted">Bonus for completing ALL tasks on time in a month</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <strong>Perfect Month Criteria:</strong><br>
                                            All tasks completed on or before their due dates
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-save me-1"></i>Update Bonus Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bonus Calculator Preview -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0 d-flex align-items-center">
                <i class="fas fa-calculator me-2 text-info"></i>Bonus Calculator Preview
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Example Calculation</h6>
                    <div class="bg-body-tertiary p-3 rounded">
                        <div class="mb-2"><strong>Scenario:</strong> Writer earns Ksh. 10,000 in a month</div>
                        <div class="mb-2">• Completes 10 tasks total</div>
                        <div class="mb-2">• 3 tasks early, 5 tasks on time, 2 tasks late</div>
                        <div class="mb-2">• Perfect month: No (has late tasks)</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6>Bonus Breakdown</h6>
                    <div class="bg-success-subtle p-3 rounded">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Base Bonus (<?php echo $settings['base_bonus_percentage']['setting_value'] ?? 5.0; ?>%):</span>
                            <span>Ksh. <?php echo number_format((10000 * ($settings['base_bonus_percentage']['setting_value'] ?? 5.0)) / 100, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Early Bonus (3 tasks):</span>
                            <span>Ksh. <?php echo number_format((10000 * ($settings['early_completion_bonus']['setting_value'] ?? 2.5) * 3) / 10 / 100, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Perfect Month:</span>
                            <span>Ksh. 0.00 (has late tasks)</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold">
                            <span>Total Bonus:</span>
                            <span>Ksh. <?php
                                $baseBonus = (10000 * ($settings['base_bonus_percentage']['setting_value'] ?? 5.0)) / 100;
                                $earlyBonus = (10000 * ($settings['early_completion_bonus']['setting_value'] ?? 2.5) * 3) / 10 / 100;
                                echo number_format($baseBonus + $earlyBonus, 2);
                                ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Monthly Bonuses -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 d-flex align-items-center">
                <i class="fas fa-history me-2 text-success"></i>Recent Monthly Bonuses
            </h5>
            <a href="bonus-history.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-eye me-1"></i>View All History
            </a>
        </div>
        <div class="card-body">
            <?php
            $recentBonusesQuery = "SELECT 
            mb.*, w.FirstName, w.LastName 
            FROM tbl_monthly_bonuses mb
            LEFT JOIN tblwriters w ON mb.writer_id = w.id
            ORDER BY mb.year DESC, mb.month DESC 
            LIMIT 10";
            $recentBonuses = mysqli_query($con, $recentBonusesQuery);

            if (mysqli_num_rows($recentBonuses) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                        <tr>
                            <th>Writer</th>
                            <th>Month/Year</th>
                            <th>Tasks</th>
                            <th>On Time</th>
                            <th>Earnings</th>
                            <th>Bonus</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($bonus = mysqli_fetch_assoc($recentBonuses)): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars(($bonus['FirstName'] ?? '') . ' ' . ($bonus['LastName'] ?? '')); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($bonus['writer_email']); ?></small>
                                </td>
                                <td><?php echo date('F Y', mktime(0, 0, 0, $bonus['month'], 1, $bonus['year'])); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $bonus['total_tasks_completed']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $bonus['tasks_completed_on_time']; ?></span>
                                    <?php if ($bonus['tasks_completed_early'] > 0): ?>
                                        <span class="badge bg-primary ms-1" title="Early completions"><?php echo $bonus['tasks_completed_early']; ?>⚡</span>
                                    <?php endif; ?>
                                </td>
                                <td>Ksh. <?php echo number_format($bonus['total_earnings'], 2); ?></td>
                                <td>
                                    <strong class="text-success">Ksh. <?php echo number_format($bonus['total_bonus_amount'], 2); ?></strong>
                                    <br><small class="text-muted"><?php echo $bonus['bonus_percentage']; ?>%</small>
                                </td>
                                <td>
                                    <?php if ($bonus['is_paid']): ?>
                                        <span class="badge bg-success">Paid</span>
                                        <br><small class="text-muted"><?php echo date('M d', strtotime($bonus['paid_on'])); ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calculator fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">No bonus calculations yet</h6>
                    <p class="text-muted">Use the "Calculate Monthly Bonuses" button to generate bonus calculations.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Calculate Monthly Bonus Modal -->
    <div class="modal fade" id="calculateBonusModal" tabindex="-1" aria-labelledby="calculateBonusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="calculateBonusModalLabel">Calculate Monthly Bonuses</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="calculate_monthly_bonuses">

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            This will calculate bonuses for all writers who completed tasks in the selected month. The system will provide detailed feedback about the calculation process.
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bonus_month" class="form-label">Month</label>
                                    <select class="form-select" name="month" id="bonus_month" required>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo $m; ?>" <?php echo ($m == date('n') - 1) ? 'selected' : ''; ?>>
                                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bonus_year" class="form-label">Year</label>
                                    <select class="form-select" name="year" id="bonus_year" required>
                                        <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo ($y == date('Y')) ? 'selected' : ''; ?>>
                                                <?php echo $y; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Note:</strong> This will recalculate bonuses if they already exist for the selected month.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-calculator me-1"></i>Calculate Bonuses
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Real-time bonus calculation preview
        function updateBonusPreview() {
            const baseBonusRate = parseFloat(document.getElementById('base_bonus_percentage').value) || 0;
            const earlyBonusRate = parseFloat(document.getElementById('early_completion_bonus').value) || 0;
            const earnings = 10000; // Example earnings
            const earlyTasks = 3;
            const totalTasks = 10;

            const baseBonus = (earnings * baseBonusRate) / 100;
            const earlyBonus = (earnings * earlyBonusRate * earlyTasks) / totalTasks / 100;
            const totalBonus = baseBonus + earlyBonus;

            // Update preview values (this could be enhanced with actual DOM manipulation)
            console.log('Base Bonus:', baseBonus, 'Early Bonus:', earlyBonus, 'Total:', totalBonus);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners for real-time preview updates
            const inputs = ['base_bonus_percentage', 'early_completion_bonus'];
            inputs.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('input', updateBonusPreview);
                }
            });
        });
    </script>

<?php include "footer.php"; ?>