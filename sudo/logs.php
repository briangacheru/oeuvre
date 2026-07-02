<?php
include "head.php";
require_once('../activity_logger.php');

$logger = new Logger($con);

// Get filter parameters
$userEmail = $_GET['user_email'] ?? '';
$activityType = $_GET['activity_type'] ?? '';
$severity = $_GET['severity'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Get logs
$logs = $logger->getLogs($limit, $offset, $userEmail, $activityType, $severity, $startDate, $endDate);
$stats = $logger->getLogStats();
?>

    <title>Activity Logs | Admin Panel</title>
<?php include "navi.php"; ?>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Activity Logs</h5>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">User Email</label>
                    <input type="email" class="form-control" name="user_email" value="<?php echo htmlspecialchars($userEmail); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Activity Type</label>
                    <select class="form-select" name="activity_type">
                        <option value="">All Types</option>
                        <option value="LOGIN_SUCCESS" <?php echo $activityType === 'LOGIN_SUCCESS' ? 'selected' : ''; ?>>Login Success</option>
                        <option value="LOGIN_FAILED" <?php echo $activityType === 'LOGIN_FAILED' ? 'selected' : ''; ?>>Login Failed</option>
                        <option value="LOGOUT_SUCCESS" <?php echo $activityType === 'LOGOUT_SUCCESS' ? 'selected' : ''; ?>>Logout</option>
                        <option value="TASK_VIEWED" <?php echo $activityType === 'TASK_VIEWED' ? 'selected' : ''; ?>>Task Viewed</option>
                        <option value="TASK_SUBMITTED" <?php echo $activityType === 'TASK_SUBMITTED' ? 'selected' : ''; ?>>Task Submitted</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Severity</label>
                    <select class="form-select" name="severity">
                        <option value="">All Severities</option>
                        <option value="info" <?php echo $severity === 'info' ? 'selected' : ''; ?>>Info</option>
                        <option value="warning" <?php echo $severity === 'warning' ? 'selected' : ''; ?>>Warning</option>
                        <option value="error" <?php echo $severity === 'error' ? 'selected' : ''; ?>>Error</option>
                        <option value="security" <?php echo $severity === 'security' ? 'selected' : ''; ?>>Security</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block">Filter</button>
                </div>
            </form>

            <!-- Logs Table -->
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>User</th>
                        <th>Activity</th>
                        <th>Description</th>
                        <th>Severity</th>
                        <th>IP Address</th>
                        <th>Details</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['user_email'] ?? 'System'); ?></td>
                            <td><?php echo htmlspecialchars($log['activity_type']); ?></td>
                            <td><?php echo htmlspecialchars($log['activity_description']); ?></td>
                            <td>
                                <?php
                                $badgeClass = [
                                    'info' => 'bg-info',
                                    'warning' => 'bg-warning',
                                    'error' => 'bg-danger',
                                    'security' => 'bg-dark'
                                ][$log['severity']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($log['severity']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td>
                                <?php if ($log['additional_data']): ?>
                                    <button class="btn btn-sm btn-outline-primary" onclick="showDetails('<?php echo htmlspecialchars($log['additional_data']); ?>')">
                                        View Details
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php if (count($logs) === $limit): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Activity Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre id="detailsContent"></pre>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showDetails(data) {
            try {
                const parsed = JSON.parse(data);
                document.getElementById('detailsContent').textContent = JSON.stringify(parsed, null, 2);
                new bootstrap.Modal(document.getElementById('detailsModal')).show();
            } catch (e) {
                document.getElementById('detailsContent').textContent = data;
                new bootstrap.Modal(document.getElementById('detailsModal')).show();
            }
        }
    </script>

<?php include "footer.php"; ?>