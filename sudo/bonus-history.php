<?php
include_once('head.php');

// Handle bonus payment updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'mark_paid') {
        $bonusId = intval($_POST['bonus_id']);
        $updateQuery = "UPDATE tbl_monthly_bonuses SET is_paid = 1, paid_on = NOW() WHERE id = ?";
        $stmt = $con->prepare($updateQuery);
        $stmt->bind_param("i", $bonusId);

        if ($stmt->execute()) {
            $successMessage = "Bonus marked as paid successfully!";
        } else {
            $errorMessage = "Failed to update bonus status.";
        }
        $stmt->close();
    } elseif ($_POST['action'] == 'bulk_mark_paid') {
        $bonusIds = $_POST['bonus_ids'] ?? [];
        $paidCount = 0;

        foreach ($bonusIds as $bonusId) {
            $bonusId = intval($bonusId);
            $updateQuery = "UPDATE tbl_monthly_bonuses SET is_paid = 1, paid_on = NOW() WHERE id = ?";
            $stmt = $con->prepare($updateQuery);
            $stmt->bind_param("i", $bonusId);

            if ($stmt->execute()) {
                $paidCount++;
            }
            $stmt->close();
        }

        $successMessage = "Marked {$paidCount} bonuses as paid successfully!";
    }
}

// Get filter parameters
$filterMonth = $_GET['month'] ?? '';
$filterYear = $_GET['year'] ?? '';
$filterWriter = $_GET['writer'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build query with filters
$whereConditions = [];
$params = [];
$types = '';

if ($filterMonth) {
    $whereConditions[] = "mb.month = ?";
    $params[] = intval($filterMonth);
    $types .= 'i';
}

if ($filterYear) {
    $whereConditions[] = "mb.year = ?";
    $params[] = intval($filterYear);
    $types .= 'i';
}

if ($filterWriter) {
    $whereConditions[] = "(w.FirstName LIKE ? OR w.LastName LIKE ? OR mb.writer_email LIKE ?)";
    $searchTerm = "%{$filterWriter}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if ($filterStatus !== '') {
    $whereConditions[] = "mb.is_paid = ?";
    $params[] = intval($filterStatus);
    $types .= 'i';
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

// Get total stats
$statsQuery = "SELECT 
    COUNT(*) as total_bonuses,
    SUM(mb.total_bonus_amount) as total_amount,
    SUM(CASE WHEN mb.is_paid = 1 THEN mb.total_bonus_amount ELSE 0 END) as paid_amount,
    SUM(CASE WHEN mb.is_paid = 0 THEN mb.total_bonus_amount ELSE 0 END) as pending_amount,
    COUNT(CASE WHEN mb.is_paid = 1 THEN 1 END) as paid_count,
    COUNT(CASE WHEN mb.is_paid = 0 THEN 1 END) as pending_count
    FROM tbl_monthly_bonuses mb
    LEFT JOIN tblwriters w ON mb.writer_id = w.id
    {$whereClause}";

if (!empty($params)) {
    $statsStmt = $con->prepare($statsQuery);
    $statsStmt->bind_param($types, ...$params);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
    $statsStmt->close();
} else {
    $stats = mysqli_fetch_assoc(mysqli_query($con, $statsQuery));
}

// Get bonus history with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$bonusQuery = "SELECT 
    mb.*,
    w.FirstName,
    w.LastName, w.username,
    w.Photo
    FROM tbl_monthly_bonuses mb
    LEFT JOIN tblwriters w ON mb.writer_id = w.id
    {$whereClause}
    ORDER BY mb.year DESC, mb.month DESC, mb.total_bonus_amount DESC
    LIMIT ? OFFSET ?";

$finalParams = $params;
$finalParams[] = $limit;
$finalParams[] = $offset;
$finalTypes = $types . 'ii';

$stmt = $con->prepare($bonusQuery);
if (!empty($finalParams)) {
    $stmt->bind_param($finalTypes, ...$finalParams);
}
$stmt->execute();
$bonusResult = $stmt->get_result();

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM tbl_monthly_bonuses mb LEFT JOIN tblwriters w ON mb.writer_id = w.id {$whereClause}";
if (!empty($params)) {
    $countStmt = $con->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
} else {
    $totalCount = mysqli_fetch_assoc(mysqli_query($con, $countQuery))['total'];
}

$totalPages = ceil($totalCount / $limit);
?>

    <title>iTasker | Bonus History</title>
<?php include "navi.php"; ?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);"></div>
        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Bonus <span class="text-info fw-medium">History & Payments</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0">
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="exportBonusData()">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                        <a href="bonus-settings" class="btn btn-primary">
                            <i class="fas fa-cogs me-1"></i>Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php if (isset($successMessage)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

    <!-- Summary Statistics -->
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card overflow-hidden">
                <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-1.png);"></div>
                <div class="card-body position-relative">
                    <h6>Total Bonuses <span class="badge badge-subtle-warning rounded-pill ms-2"><?php echo $stats['total_bonuses']; ?></span></h6>
                    <div class="display-4 fs-9 mb-2 fw-normal font-sans-serif text-warning" data-countup='{"endValue":<?php echo $stats['total_amount']; ?>,"decimalPlaces":2,"prefix":"Ksh. "}'>Ksh. <?php echo number_format($stats['total_amount'], 2); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card overflow-hidden">
                <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
                <div class="card-body position-relative">
                    <h6>Paid Bonuses <span class="badge badge-subtle-success rounded-pill ms-2"><?php echo $stats['paid_count']; ?></span></h6>
                    <div class="display-4 fs-9 mb-2 fw-normal font-sans-serif text-success">Ksh. <?php echo number_format($stats['paid_amount'], 2); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card overflow-hidden">
                <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-3.png);"></div>
                <div class="card-body position-relative">
                    <h6>Pending Bonuses <span class="badge badge-subtle-danger rounded-pill ms-2"><?php echo $stats['pending_count']; ?></span></h6>
                    <div class="display-4 fs-9 mb-2 fw-normal font-sans-serif text-danger">Ksh. <?php echo number_format($stats['pending_amount'], 2); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card overflow-hidden">
                <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-4.png);"></div>
                <div class="card-body position-relative">
                    <h6>Payment Rate</h6>
                    <div class="display-4 fs-9 mb-2 fw-normal font-sans-serif text-info">
                        <?php echo $stats['total_bonuses'] > 0 ? round(($stats['paid_count'] / $stats['total_bonuses']) * 100, 1) : 0; ?>%
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-filter me-2"></i>Filter & Search
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label for="filter_month" class="form-label">Month</label>
                    <select class="form-select" name="month" id="filter_month">
                        <option value="">All Months</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo ($filterMonth == $m) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter_year" class="form-label">Year</label>
                    <select class="form-select" name="year" id="filter_year">
                        <option value="">All Years</option>
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($filterYear == $y) ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter_writer" class="form-label">Writer</label>
                    <input type="text" class="form-control" name="writer" id="filter_writer"
                           placeholder="Search by name or email" value="<?php echo htmlspecialchars($filterWriter); ?>">
                </div>
                <div class="col-md-2">
                    <label for="filter_status" class="form-label">Status</label>
                    <select class="form-select" name="status" id="filter_status">
                        <option value="">All Status</option>
                        <option value="1" <?php echo ($filterStatus === '1') ? 'selected' : ''; ?>>Paid</option>
                        <option value="0" <?php echo ($filterStatus === '0') ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                        <a href="bonus-history" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bonus History Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-history me-2"></i>Bonus History
                <span class="badge bg-info ms-2"><?php echo number_format($totalCount); ?> records</span>
            </h5>
            <?php if ($stats['pending_count'] > 0): ?>
                <button class="btn btn-success btn-sm" onclick="bulkMarkPaid()">
                    <i class="fas fa-check-double me-1"></i>Mark Selected as Paid
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if ($bonusResult->num_rows > 0): ?>
                <form id="bulkPaymentForm" method="POST">
                    <input type="hidden" name="action" value="bulk_mark_paid">

                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                            <tr>
                                <?php if ($stats['pending_count'] > 0): ?>
                                    <th width="30">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                    </th>
                                <?php endif; ?>
                                <th>Writer</th>
                                <th>Period</th>
                                <th>Performance</th>
                                <th>Earnings</th>
                                <th>Bonus Details</th>
                                <th>Total Bonus</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php while ($bonus = $bonusResult->fetch_assoc()): ?>
                                <tr>
                                    <?php if ($stats['pending_count'] > 0): ?>
                                        <td>
                                            <?php if (!$bonus['is_paid']): ?>
                                                <input type="checkbox" name="bonus_ids[]" value="<?php echo $bonus['id']; ?>" class="bonus-checkbox">
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-m me-2">
                                                <img class="rounded-circle" src="../profileimages/<?php echo $bonus['Photo'] ?: 'avatar.png'; ?>" alt="">
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars(($bonus['FirstName'] ?? '') . ' ' . ($bonus['LastName'] ?? '')); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($bonus['writer_email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo date('F Y', mktime(0, 0, 0, $bonus['month'], 1, $bonus['year'])); ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo date('M d', strtotime($bonus['created_at'])); ?> calculated
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="badge badge-subtle-secondary rounded-sm-pill mb-1"><?php echo $bonus['total_tasks_completed']; ?> tasks</span>
                                            <span class="badge badge-subtle-info rounded-sm-pill mb-1"><?php echo $bonus['tasks_completed_on_time']; ?> on time</span>
                                            <?php if ($bonus['tasks_completed_early'] > 0): ?>
                                                <span class="badge badge-subtle-success rounded-sm-pill"><?php echo $bonus['tasks_completed_early']; ?> early</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>Ksh. <?php echo number_format($bonus['total_earnings'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <small class="d-block">
                                            Base: Ksh. <?php echo number_format($bonus['base_bonus_amount'], 2); ?>
                                        </small>
                                        <?php if ($bonus['early_completion_bonus'] > 0): ?>
                                            <small class="d-block text-primary">
                                                Early: Ksh. <?php echo number_format($bonus['early_completion_bonus'], 2); ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($bonus['perfect_month_bonus'] > 0): ?>
                                            <small class="d-block text-warning">
                                                Perfect: Ksh. <?php echo number_format($bonus['perfect_month_bonus'], 2); ?>
                                                <i class="fas fa-crown ms-1"></i>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <h6 class="text-success mb-0">
                                            Ksh. <?php echo number_format($bonus['total_bonus_amount'], 2); ?>
                                        </h6>
                                        <small class="text-muted"><?php echo $bonus['bonus_percentage']; ?>%</small>
                                    </td>
                                    <td>
                                        <?php if ($bonus['is_paid']): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>Paid
                                            </span>
                                            <br><small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($bonus['paid_on'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i>Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if (!$bonus['is_paid']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success"
                                                        onclick="markSinglePaid(<?php echo $bonus['id']; ?>)"
                                                        title="Mark as Paid">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-info"
                                                    onclick="viewBonusDetails(<?php echo htmlspecialchars(json_encode($bonus)); ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Bonus history pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                    <li class="page-item <?php echo ($p == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>">
                                            <?php echo $p; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>

                        <div class="text-center mt-2">
                            <small class="text-muted">
                                Showing <?php echo (($page - 1) * $limit) + 1; ?> to
                                <?php echo min($page * $limit, $totalCount); ?> of
                                <?php echo number_format($totalCount); ?> records
                            </small>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">No bonus records found</h6>
                    <p class="text-muted">Try adjusting your filter criteria or calculate bonuses for recent months.</p>
                    <a href="bonus-settings" class="btn btn-primary">
                        <i class="fas fa-calculator me-1"></i>Calculate Bonuses
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bonus Details Modal -->
    <div class="modal fade" id="bonusDetailsModal" tabindex="-1" aria-labelledby="bonusDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bonusDetailsModalLabel">Bonus Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="bonusDetailsContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <!-- Footer will be updated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.bonus-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        function bulkMarkPaid() {
            const checkedBoxes = document.querySelectorAll('.bonus-checkbox:checked');

            if (checkedBoxes.length === 0) {
                alert('Please select at least one bonus to mark as paid.');
                return;
            }

            if (confirm(`Are you sure you want to mark ${checkedBoxes.length} bonus(es) as paid?`)) {
                document.getElementById('bulkPaymentForm').submit();
            }
        }

        function markSinglePaid(bonusId) {
            if (confirm('Are you sure you want to mark this bonus as paid?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
            <input type="hidden" name="action" value="mark_paid">
            <input type="hidden" name="bonus_id" value="${bonusId}">
        `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewBonusDetails(bonus) {
            // First, fetch the bonus settings from the database
            fetch('get-bonus-settings')
                .then(response => response.json())
                .then(bonusSettings => {
                    // Extract actual bonus percentages
                    const baseBonusPercentage = bonusSettings.base_bonus_percentage || 5.0;
                    const earlyBonusPercentage = bonusSettings.early_completion_bonus || 2.5;
                    const perfectMonthPercentage = bonusSettings.perfect_month_bonus || 10.0;

                    // Now we have actual earnings from the database
                    const totalEarnings = parseFloat(bonus.total_earnings);
                    const earlyEarnings = parseFloat(bonus.early_earnings || 0);
                    const onTimeEarnings = parseFloat(bonus.on_time_earnings || 0);
                    const lateEarnings = parseFloat(bonus.late_earnings || 0);

                    const content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Writer Information</h6>
                        <p><strong>Name:</strong> ${bonus.FirstName || ''} ${bonus.LastName || ''}</p>
                        <p><strong>Username:</strong> ${bonus.username || 'N/A'}</p>
                        <p><strong>Email:</strong> ${bonus.writer_email}</p>
                        <p><strong>Period:</strong> ${new Date(bonus.year, bonus.month - 1).toLocaleDateString('en-US', {month: 'long', year: 'numeric'})}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Performance Summary</h6>
                        <div class="row g-2">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <span><strong>Total Tasks:</strong></span>
                                    <span>${bonus.total_tasks_completed}</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <span><strong>Early:</strong></span>
                                    <span>${bonus.tasks_completed_early} tasks</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">Early Earnings:</small>
                                    <small class="text-success">Ksh. ${earlyEarnings.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</small>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <span><strong>On Time:</strong></span>
                                    <span>${bonus.tasks_completed_on_time} tasks</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">On-Time Earnings:</small>
                                    <small class="text-info">Ksh. ${onTimeEarnings.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</small>
                                </div>
                            </div>
                            ${bonus.tasks_completed_late > 0 ? `
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <span><strong>Late:</strong></span>
                                    <span>${bonus.tasks_completed_late} tasks</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">Late Earnings:</small>
                                    <small class="text-warning">Ksh. ${lateEarnings.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</small>
                                </div>
                            </div>
                            ` : ''}
                            <div class="col-12">
                                <hr class="my-2">
                                <div class="d-flex justify-content-between">
                                    <span><strong>Total ${new Date(bonus.year, bonus.month - 1).toLocaleDateString('en-US', {month: 'long', year: 'numeric'})} Earnings:</strong></span>
                                    <span><strong>Ksh. ${totalEarnings.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <h6 class="text-primary">Bonus Breakdown</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <td>Base Bonus
                                    <small class="text-muted d-block">${baseBonusPercentage}% × Ksh. ${totalEarnings.toLocaleString()} (${bonus.total_tasks_completed} tasks)</small>
                                </td>
                                <td class="text-end">Ksh. ${parseFloat(bonus.base_bonus_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <td>Early Completion Bonus
                                    <small class="text-muted d-block">${earlyBonusPercentage}% × Ksh. ${earlyEarnings.toLocaleString()} (${bonus.tasks_completed_early} early tasks)</small>
                                </td>
                                <td class="text-end">Ksh. ${parseFloat(bonus.early_completion_bonus).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <td>Perfect Month Bonus
                                    <small class="text-muted d-block">${parseFloat(bonus.perfect_month_bonus) > 0 ?
                        `${perfectMonthPercentage}% × total earnings (no late tasks)` :
                        `0% (has ${bonus.tasks_completed_late || 0} late tasks)`}</small>
                                </td>
                                <td class="text-end">Ksh. ${parseFloat(bonus.perfect_month_bonus).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                            <tr class="table-success">
                                <td><strong>Total ${new Date(bonus.year, bonus.month - 1).toLocaleDateString('en-US', {month: 'long', year: 'numeric'})} Bonus</strong></td>
                                <td class="text-end"><strong>Ksh. ${parseFloat(bonus.total_bonus_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                ${bonus.notes ? `<div class="mt-3"><h6 class="text-primary">Notes</h6><p>${bonus.notes}</p></div>` : ''}
            `;

                    document.getElementById('bonusDetailsContent').innerHTML = content;
                    updateModalFooter(bonus);
                    const modal = new bootstrap.Modal(document.getElementById('bonusDetailsModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error fetching bonus settings:', error);
                    // Fallback to showing modal with default percentages
                    showModalWithDefaults(bonus);
                });
        }

        function updateModalFooter(bonus) {
            const modalFooter = document.querySelector('#bonusDetailsModal .modal-footer');
            modalFooter.innerHTML = `
                <div class="d-flex justify-content-between w-100">
                    <div>
                        <button type="button" class="btn btn-success me-2" onclick="emailBonusReport(${JSON.stringify(bonus).replace(/"/g, '&quot;')})">
                            <i class="fas fa-envelope me-1"></i>Email to Writer
                        </button>
                        <button type="button" class="btn btn-warning" onclick="downloadBonusImage(${JSON.stringify(bonus).replace(/"/g, '&quot;')})">
                            <i class="fas fa-image me-1"></i>Download as Image
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            `;
        }

        // Email bonus report to writer
        function emailBonusReport(bonus) {
            const loadingBtn = event.target;
            const originalText = loadingBtn.innerHTML;
            loadingBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
            loadingBtn.disabled = true;

            fetch('send-bonus-email', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'send_bonus_email',
                    bonus_id: bonus.id,
                    writer_email: bonus.writer_email,
                    writer_name: `${bonus.FirstName || ''} ${bonus.LastName || ''}`.trim(),
                    month: bonus.month,
                    year: bonus.year
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Bonus report emailed successfully!', 'success');
                    } else {
                        showToast('Failed to send email: ' + (data.message || 'Unknown error'), 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error sending email', 'danger');
                })
                .finally(() => {
                    loadingBtn.innerHTML = originalText;
                    loadingBtn.disabled = false;
                });
        }

        // Download bonus report as image
        function downloadBonusImage(bonus) {
            const loadingBtn = event.target;
            const originalText = loadingBtn.innerHTML;
            loadingBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
            loadingBtn.disabled = true;

            // Use html2canvas to capture the modal content
            import('https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js').then(() => {
                const modalContent = document.getElementById('bonusDetailsContent');

                html2canvas(modalContent, {
                    backgroundColor: '#ffffff',
                    scale: 2,
                    logging: false,
                    useCORS: true
                }).then(canvas => {
                    const link = document.createElement('a');
                    link.download = `bonus-report-${bonus.writer_email}-${bonus.month}-${bonus.year}.png`;
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                    showToast('Image downloaded successfully!', 'success');
                }).catch(error => {
                    console.error('Error generating image:', error);
                    showToast('Error generating image', 'danger');
                });
            }).catch(error => {
                console.error('Error loading html2canvas:', error);
                showToast('Error loading image generator', 'danger');
            }).finally(() => {
                loadingBtn.innerHTML = originalText;
                loadingBtn.disabled = false;
            });
        }

        // Toast notification function
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toast-container') || createToastContainer();

            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

            toastContainer.appendChild(toast);

            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();

            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        }

        function exportBonusData() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.location.href = 'export-bonus-data.php?' + params.toString();
        }

        // Auto-refresh pending bonuses every 5 minutes
        if (<?php echo $stats['pending_count']; ?> > 0) {
            setInterval(() => {
                if (document.visibilityState === 'visible') {
                    // Only refresh if page is visible
                    const hasUnsavedChanges = document.querySelectorAll('.bonus-checkbox:checked').length > 0;
                    if (!hasUnsavedChanges) {
                        location.reload();
                    }
                }
            }, 300000); // 5 minutes
        }
    </script>

<?php include "footer.php"; ?>