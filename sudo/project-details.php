<?php include "head.php";

if (isset($_GET['projectID'])) {
    $projectID = (int) base64_decode($_GET['projectID']);
} else {
    $_SESSION['alert'] = '<div class="alert alert-warning border-0 d-flex align-items-center" role="alert">
        <div class="bg-warning me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
        <p class="mb-0 flex-1">Invalid Project ID!</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
    header("Location: projects"); exit;
}
?>
<title>Project Details</title>
<?php include "navi.php";
$status = "OK"; $msg = "";

if (isset($_SESSION['alert'])) { echo $_SESSION['alert']; unset($_SESSION['alert']); }

// ── Fetch project ──────────────────────────────────────────────────────────
$stmtP = $con->prepare("SELECT * FROM tbl_projects WHERE projectID = ? AND is_deleted = 0");
$stmtP->bind_param("i", $projectID);
$stmtP->execute();
$rowP = $stmtP->get_result()->fetch_assoc();
if (!$rowP) {
    echo '<div class="alert alert-danger">No project found.</div>';
    include "footer.php"; exit;
}
$pName       = htmlspecialchars($rowP['projectName']);
$pDesc       = htmlspecialchars($rowP['projectDescription']);
$pStatus     = $rowP['projectStatus'];
$pPeriod     = $rowP['projectPeriod'];
$pBudget     = $rowP['projectAmount'];
$pCreated    = date("F j, Y", strtotime($rowP['created_at']));
$pAchieved   = $rowP['is_achieved'];
$pCompleted  = $rowP['completed_at'];
$encodedID   = base64_encode($projectID);

// ── Aggregates ──────────────────────────────────────────────────────────────
$stmtA = $con->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type='Income'  THEN amount ELSE 0 END),0) AS totalIncome,
        COALESCE(SUM(CASE WHEN type='Expense' THEN amount ELSE 0 END),0) AS totalExpenses
    FROM tbl_project_transactions WHERE projectID=?");
$stmtA->bind_param("i", $projectID);
$stmtA->execute();
$agg           = $stmtA->get_result()->fetch_assoc();
$totalIncome   = $agg['totalIncome'];
$totalExpenses = $agg['totalExpenses'];
$netBalance    = $totalIncome - $totalExpenses;
$budgetLeft    = $pBudget - $totalExpenses;
$spentPct      = $pBudget > 0 ? min(($totalExpenses / $pBudget) * 100, 100) : 0;
$isOver        = $totalExpenses > $pBudget;
$coveragePct   = $totalExpenses > 0 ? min(($totalIncome / $totalExpenses) * 100, 100) : 0;

// ── Due date warning ────────────────────────────────────────────────────────
$today       = new DateTime();
$dueDate     = new DateTime($pPeriod);
$daysLeft    = (int) $today->diff($dueDate)->format('%r%a'); // negative = overdue
$isPast      = $daysLeft < 0;
$isUrgent    = !$isPast && $daysLeft <= 7;

// ── Badges / helpers ────────────────────────────────────────────────────────
if ($pAchieved == 1)   $statusBadge = '<span class="badge badge-subtle-success">Achieved</span>';
elseif ($pStatus == 0) $statusBadge = '<span class="badge badge-subtle-warning">Active</span>';
else                   $statusBadge = '<span class="badge badge-subtle-secondary">Inactive</span>';

$achievedBadge  = $pAchieved == 1
    ? '<span class="badge badge-subtle-success"><span class="fas fa-check me-1"></span>Yes</span>'
    : '<span class="badge badge-subtle-secondary">Not yet</span>';
$netClass       = $netBalance >= 0 ? 'text-success' : 'text-danger';
$budgetLeftClass= $budgetLeft >= 0 ? 'text-success' : 'text-danger';
$progressClass  = $isOver ? 'bg-danger' : ($spentPct >= 80 ? 'bg-warning' : 'bg-primary');

// File icon helper
function fileIcon(string $mime): string {
    if (str_starts_with($mime, 'image/'))              return 'fa-file-image text-info';
    if ($mime === 'application/pdf')                    return 'fa-file-pdf text-danger';
    if (str_contains($mime, 'word'))                   return 'fa-file-word text-primary';
    if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) return 'fa-file-excel text-success';
    if ($mime === 'text/csv')                           return 'fa-file-csv text-success';
    return 'fa-file text-secondary';
}
function formatBytes(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes/1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes/1024, 1) . ' KB';
    return $bytes . ' B';
}
?>

<!-- ── Due date / overdue warning banner ────────────────── -->
<?php if (!$pAchieved): ?>
    <?php if ($isPast): ?>
        <div class="alert alert-danger border-0 d-flex align-items-center mb-3" role="alert">
            <div class="bg-danger me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-6"></span></div>
            <p class="mb-0 flex-1">
                <strong>This project is overdue</strong> — due date was <strong><?php echo date("F j, Y", strtotime($pPeriod)); ?></strong>
                (<?php echo abs($daysLeft); ?> day<?php echo abs($daysLeft) != 1 ? 's' : ''; ?> ago).
            </p>
        </div>
    <?php elseif ($isUrgent): ?>
        <div class="alert alert-warning border-0 d-flex align-items-center mb-3" role="alert">
            <div class="bg-warning me-3 icon-item"><span class="fas fa-clock text-white fs-6"></span></div>
            <p class="mb-0 flex-1">
                <strong>Due soon</strong> — <?php echo $daysLeft; ?> day<?php echo $daysLeft != 1 ? 's' : ''; ?> remaining until <strong><?php echo date("F j, Y", strtotime($pPeriod)); ?></strong>.
            </p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- ── Achieved Summary Banner ─────────────────────────── -->
<?php if ($pAchieved): ?>
    <div class="alert alert-success border-0 mb-3" role="alert">
        <div class="d-flex align-items-center mb-2">
            <div class="bg-success me-3 icon-item flex-shrink-0"><span class="fas fa-trophy text-white fs-6"></span></div>
            <h6 class="mb-0 text-success">Project Achieved &amp; Completed</h6>
        </div>
        <?php if ($pCompleted): ?>
            <p class="mb-2 ms-5 fs-10 text-600">Completed on <strong><?php echo date("F j, Y", strtotime($pCompleted)); ?></strong></p>
        <?php endif; ?>
        <div class="row g-2 ms-5">
            <div class="col-auto">
                <span class="badge badge-subtle-success fs-11">
                    Final Spend: Ksh <?php echo number_format($totalExpenses, 2); ?>
                </span>
            </div>
            <div class="col-auto">
                <span class="badge badge-subtle-info fs-11">
                    Budget: Ksh <?php echo number_format($pBudget, 2); ?>
                </span>
            </div>
            <div class="col-auto">
                <span class="badge <?php echo $budgetLeft >= 0 ? 'badge-subtle-success' : 'badge-subtle-danger'; ?> fs-11">
                    <?php echo $budgetLeft >= 0
                        ? 'Saved: Ksh ' . number_format($budgetLeft, 2)
                        : 'Overrun: Ksh ' . number_format(abs($budgetLeft), 2); ?>
                </span>
            </div>
            <div class="col-auto">
                <span class="badge <?php echo $netBalance >= 0 ? 'badge-subtle-success' : 'badge-subtle-danger'; ?> fs-11">
                    Net <?php echo $netBalance >= 0 ? 'Profit' : 'Loss'; ?>: Ksh <?php echo number_format(abs($netBalance), 2); ?>
                </span>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="card shadow-none border mb-3">
    <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
        <div class="d-flex align-items-center gap-2">
            <a href="projects" class="btn btn-sm btn-outline-secondary">
                <span class="fas fa-chevron-left fs-11 me-1"></span>Projects
            </a>
            <h5 class="mb-0 text-primary"><?php echo $pName; ?></h5>
            <?php echo $statusBadge; ?>
        </div>
        <span class="text-600 fs-10">Budget: <strong>Ksh <?php echo number_format($pBudget, 2); ?></strong></span>
    </div>
</div>

<!-- ── Stat Cards ───────────────────────────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xxl-3">
        <div class="card h-100 font-sans-serif">
            <div class="card-header bg-body-tertiary py-2">
                <h6 class="mb-0 text-600"><span class="fas fa-arrow-down text-danger me-1"></span>Total Expenses</h6>
            </div>
            <div class="card-body">
                <h4 class="text-danger mb-1">Ksh <?php echo number_format($totalExpenses, 2); ?></h4>
                <span class="badge <?php echo $isOver ? 'badge-subtle-danger' : 'badge-subtle-success'; ?> fs-11">
                        <?php echo $isOver ? 'Over budget' : 'Within budget'; ?>
                    </span>
                <div class="mt-3">
                    <div class="d-flex justify-content-between fs-10 text-600 mb-1">
                        <span>Budget used</span><span><?php echo round($spentPct, 1); ?>%</span>
                    </div>
                    <div class="progress" style="height:5px;">
                        <div class="progress-bar <?php echo $progressClass; ?>" style="width:<?php echo round($spentPct, 1); ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xxl-3">
        <div class="card h-100 font-sans-serif">
            <div class="card-header bg-body-tertiary py-2">
                <h6 class="mb-0 text-600"><span class="fas fa-arrow-up text-success me-1"></span>Total Income</h6>
            </div>
            <div class="card-body">
                <h4 class="text-success mb-1">Ksh <?php echo number_format($totalIncome, 2); ?></h4>
                <span class="badge badge-subtle-info fs-11"><?php echo round($coveragePct, 1); ?>% of expenses covered</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xxl-3">
        <div class="card h-100 font-sans-serif">
            <div class="card-header bg-body-tertiary py-2">
                <h6 class="mb-0 text-600"><span class="fas fa-balance-scale me-1"></span>Net Balance</h6>
            </div>
            <div class="card-body">
                <h4 class="<?php echo $netClass; ?> mb-1">
                    <?php echo $netBalance >= 0 ? '+' : ''; ?>Ksh <?php echo number_format(abs($netBalance), 2); ?>
                </h4>
                <span class="badge <?php echo $netBalance >= 0 ? 'badge-subtle-success' : 'badge-subtle-danger'; ?> fs-11">
                        <span class="fas <?php echo $netBalance >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?> me-1"></span>
                        <?php echo $netBalance >= 0 ? 'Profit' : 'Loss'; ?>
                    </span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xxl-3">
        <div class="card h-100 font-sans-serif">
            <div class="card-header bg-body-tertiary py-2">
                <h6 class="mb-0 text-600"><span class="fas fa-wallet me-1"></span>Budget Remaining</h6>
            </div>
            <div class="card-body">
                <h4 class="<?php echo $budgetLeftClass; ?> mb-1">Ksh <?php echo number_format(abs($budgetLeft), 2); ?></h4>
                <?php echo $isOver
                    ? '<span class="badge badge-subtle-danger fs-11">Ksh ' . number_format(abs($budgetLeft), 2) . ' overrun</span>'
                    : '<span class="badge badge-subtle-success fs-11">' . round(100 - $spentPct, 1) . '% left</span>'; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Details + Chart ──────────────────────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-xxl-5">
        <div class="card h-100 font-sans-serif overflow-hidden">

            <!-- Hero strip -->

            <div class="card-header bg-primary-subtle px-4 py-4 d-flex flex-between-center position-relative">
                <div style="position:relative; z-index:1;">
                    <p class="mb-1 fw-bold fs-11 text-uppercase" style="opacity:.75; letter-spacing:.06em;">Project</p>
                    <h5 class="fw-bold mb-2"><?php echo $pName; ?></h5>
                    <p class="fs-10 mb-3">
                        <?php echo $pDesc ?: '<em>No description provided.</em>'; ?>
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <?php echo $statusBadge; ?>
                        <?php echo $achievedBadge; ?>
                        <?php if ($isPast && !$pAchieved): ?>
                            <span class="badge badge-subtle-danger">Overdue</span>
                        <?php elseif ($isUrgent && !$pAchieved): ?>
                            <span class="badge badge-subtle-warning">Due soon</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <i class="fas fa-gem fs-1"></i>
                </div>
            </div>

            <!-- Detail rows -->
            <div class="card-body p-0">

                <div class="d-flex align-items-center px-4 py-3 border-bottom">
                    <div class="icon-item icon-item-sm bg-primary-subtle rounded-circle me-3 flex-shrink-0">
                        <span class="fas fa-calendar-alt text-primary fs-11"></span>
                    </div>
                    <div class="flex-1">
                        <p class="mb-0 fs-11 text-600 text-uppercase fw-bold">Due Date</p>
                        <p class="mb-0 fw-semi-bold fs-10">
                            <?php echo date("F j, Y", strtotime($pPeriod)); ?>
                            <?php if (!$pAchieved): ?>
                                <span class="ms-1 fs-11 <?php echo $isPast ? 'text-danger' : ($isUrgent ? 'text-warning' : 'text-500'); ?>">
                                        (<?php echo $isPast ? abs($daysLeft).'d overdue' : $daysLeft.'d left'; ?>)
                                    </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="d-flex align-items-center px-4 py-3 border-bottom">
                    <div class="icon-item icon-item-sm bg-success-subtle rounded-circle me-3 flex-shrink-0">
                        <span class="fas fa-plus-circle text-success fs-11"></span>
                    </div>
                    <div class="flex-1">
                        <p class="mb-0 fs-11 text-600 text-uppercase fw-bold">Created</p>
                        <p class="mb-0 fw-semi-bold fs-10"><?php echo $pCreated; ?></p>
                    </div>
                </div>

                <?php if ($pAchieved && $pCompleted): ?>
                    <div class="d-flex align-items-center px-4 py-3 border-bottom">
                        <div class="icon-item icon-item-sm bg-success-subtle rounded-circle me-3 flex-shrink-0">
                            <span class="fas fa-trophy text-success fs-11"></span>
                        </div>
                        <div class="flex-1">
                            <p class="mb-0 fs-11 text-600 text-uppercase fw-bold">Completed</p>
                            <p class="mb-0 fw-semi-bold fs-10 text-success"><?php echo date("F j, Y", strtotime($pCompleted)); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex align-items-center px-4 py-3 border-bottom">
                    <div class="icon-item icon-item-sm bg-warning-subtle rounded-circle me-3 flex-shrink-0">
                        <span class="fas fa-coins text-warning fs-11"></span>
                    </div>
                    <div class="flex-1">
                        <p class="mb-0 fs-11 text-600 text-uppercase fw-bold">Budget</p>
                        <p class="mb-0 fw-bold fs-10">Ksh <?php echo number_format($pBudget, 2); ?></p>
                    </div>
                    <div class="text-end">
                        <p class="mb-0 fs-11 text-600">Spent</p>
                        <p class="mb-0 fw-bold fs-10 <?php echo $isOver ? 'text-danger' : 'text-success'; ?>">
                            <?php echo round($spentPct, 1); ?>%
                        </p>
                    </div>
                </div>

                <div class="px-4 py-3">
                    <div class="d-flex justify-content-between fs-11 text-600 mb-1">
                        <span><?php echo $isOver ? 'Over budget' : 'Budget used'; ?></span>
                        <span><?php echo round($spentPct, 1); ?>%</span>
                    </div>
                    <div class="progress" style="height:6px; border-radius:99px;">
                        <div class="progress-bar <?php echo $progressClass; ?>"
                             style="width:<?php echo round($spentPct, 1); ?>%"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="col-xxl-7">
        <div class="card h-100 font-sans-serif">
            <div class="card-header bg-body-tertiary py-2">
                <h6 class="mb-0"><span class="fas fa-chart-pie text-primary me-1"></span>Expense Breakdown by Category</h6>
            </div>
            <div class="card-body p-0">
                <div id="expenseChart" style="width:100%;height:320px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ── Transactions ─────────────────────────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card overflow-hidden">
            <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                <h5 class="mb-0 text-primary">Transactions</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTxnModal">
                    <span class="fas fa-plus fs-11 me-1"></span>Add Transaction
                </button>
            </div>
            <div class="card-body px-0 pt-0">
                <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables="data-datatables"
                       data-datatables-language='{"emptyTable":"No transactions yet. Add your first one above."}'>
                    <thead class="bg-200">
                    <tr>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap">#</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap">Type</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap">Description</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap">Category</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap">Amount (Ksh)</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap">Tag / Wallet</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap">Date</th>
                        <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                    </tr>
                    </thead>
                    <tbody class="list">
                    <?php
                    $stmtT = $con->prepare("SELECT transactionID, type, description, category, amount, tag, transactionDate FROM tbl_project_transactions WHERE projectID=? ORDER BY transactionDate DESC, transactionID DESC");
                    $stmtT->bind_param("i", $projectID);
                    $stmtT->execute();
                    $resT = $stmtT->get_result();
                    if ($resT->num_rows > 0) {
                        $cnt = 1;
                        while ($t = $resT->fetch_assoc()) {
                            $tID   = $t['transactionID'];
                            $isInc = $t['type'] === 'Income';
                            $typeBadge = $isInc
                                ? '<span class="badge badge-subtle-success fs-11"><span class="fas fa-arrow-up me-1"></span>Income</span>'
                                : '<span class="badge badge-subtle-danger fs-11"><span class="fas fa-arrow-down me-1"></span>Expense</span>';
                            $amtClass = $isInc ? 'text-success' : 'text-danger';
                            ?>
                            <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                                <td class="align-middle text-600"><?php echo $cnt; ?></td>
                                <td class="align-middle"><?php echo $typeBadge; ?></td>
                                <td class="align-middle fw-semi-bold"><?php echo htmlspecialchars($t['description']); ?></td>
                                <td class="align-middle text-600"><?php echo htmlspecialchars($t['category'] ?: '—'); ?></td>
                                <td class="align-middle fw-semi-bold <?php echo $amtClass; ?>">
                                    <?php echo ($isInc ? '+' : '−') . ' ' . number_format($t['amount'], 2); ?>
                                </td>
                                <td class="align-middle text-600"><?php echo htmlspecialchars($t['tag'] ?? '—'); ?></td>
                                <td class="align-middle text-500"><?php echo date("M j, Y", strtotime($t['transactionDate'])); ?></td>
                                <td class="align-middle white-space-nowrap text-end position-relative">
                                    <div class="hover-actions bg-100">
                                        <button class="btn btn-outline-primary icon-item rounded-3 me-1 fs-11 icon-item-sm"
                                                data-bs-toggle="modal" data-bs-target="#editTxnModal"
                                                onclick="populateEditTxnModal('<?php echo $tID; ?>','<?php echo addslashes($t['type']); ?>','<?php echo addslashes($t['category']); ?>','<?php echo addslashes($t['description']); ?>','<?php echo $t['amount']; ?>','<?php echo addslashes($t['tag'] ?? ''); ?>','<?php echo $t['transactionDate']; ?>')"
                                                title="Edit"><span class="fas fa-edit"></span></button>
                                        <button class="btn btn-outline-danger icon-item rounded-3 fs-11 icon-item-sm"
                                                data-bs-toggle="modal" data-bs-target="#deleteTxnModal"
                                                onclick="populateDeleteTxnModal('<?php echo $tID; ?>','<?php echo $encodedID; ?>')"
                                                title="Delete"><span class="fas fa-trash"></span></button>
                                    </div>
                                    <div class="dropdown font-sans-serif btn-reveal-trigger">
                                        <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <span class="fas fa-chevron-left fs-11"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php $cnt++;
                        }
                    }  ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Notes + Attachments row ──────────────────────────── -->
<div class="row g-3 mb-3">

    <!-- Notes -->
    <div class="col-xxl-6">
        <div class="card h-100">
            <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                <h5 class="mb-0 text-primary"><span class="fas fa-sticky-note me-1"></span>Activity Log</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                    <span class="fas fa-plus fs-11 me-1"></span>Add Note
                </button>
            </div>
            <div class="card-body p-0" style="max-height:400px;overflow-y:auto;">
                <?php
                $stmtN = $con->prepare("SELECT noteID, note, created_at FROM tbl_project_notes WHERE projectID=? ORDER BY created_at DESC");
                $stmtN->bind_param("i", $projectID);
                $stmtN->execute();
                $resN = $stmtN->get_result();
                if ($resN->num_rows > 0) {
                    while ($n = $resN->fetch_assoc()) { ?>
                        <div class="d-flex align-items-start border-bottom px-3 py-2 hover-bg-100">
                            <div class="icon-item icon-item-sm bg-primary-subtle rounded-circle me-2 flex-shrink-0">
                                <span class="fas fa-comment text-primary fs-11"></span>
                            </div>
                            <div class="flex-1">
                                <p class="mb-1 fs-10"><?php echo nl2br(htmlspecialchars($n['note'])); ?></p>
                                <span class="text-500 fs-11"><?php echo date("M j, Y g:i A", strtotime($n['created_at'])); ?></span>
                            </div>
                            <form method="POST" action="delete_project_note" class="ms-2">
                                <input type="hidden" name="noteID" value="<?php echo $n['noteID']; ?>">
                                <input type="hidden" name="projectID" value="<?php echo $encodedID; ?>">
                                <button type="submit" class="btn btn-link text-300 p-0 fs-11" title="Delete note"
                                        onclick="return confirm('Delete this note?')">
                                    <span class="fas fa-times"></span>
                                </button>
                            </form>
                        </div>
                    <?php }
                } else { ?>
                    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-600" style="min-height:180px;">
                        <span class="fas fa-sticky-note fs-2 text-300 mb-2"></span>
                        <span>No notes yet. Add one to log decisions or updates.</span>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Attachments -->
    <div class="col-xxl-6">
        <div class="card h-100">
            <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                <h5 class="mb-0 text-primary"><span class="fas fa-paperclip me-1"></span>Attachments</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadAttachModal">
                    <span class="fas fa-upload fs-11 me-1"></span>Upload File
                </button>
            </div>
            <div class="card-body p-0" style="max-height:400px;overflow-y:auto;">
                <?php
                $stmtAt = $con->prepare("SELECT a.*, t.description AS txnDesc FROM tbl_project_attachments a LEFT JOIN tbl_project_transactions t ON t.transactionID = a.transactionID WHERE a.projectID=? ORDER BY a.uploaded_at DESC");
                $stmtAt->bind_param("i", $projectID);
                $stmtAt->execute();
                $resAt = $stmtAt->get_result();
                if ($resAt->num_rows > 0) {
                    while ($f = $resAt->fetch_assoc()) {
                        $ico = fileIcon($f['mimeType']);
                        $downloadPath = "../uploads/project_attachments/" . $f['storedName'];
                        ?>
                        <?php
                        $isImage = str_starts_with($f['mimeType'], 'image/');
                        $isDoc   = in_array($f['mimeType'], [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/plain','text/csv'
                        ]);
                        $absoluteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                            . '://' . $_SERVER['HTTP_HOST']
                            . '/' . str_replace('../', '', $downloadPath);
                        ?>
                        <div class="d-flex align-items-center border-bottom px-3 py-2 hover-bg-100 hover-actions-trigger position-relative">
                            <div class="icon-item icon-item-sm bg-body-tertiary rounded me-2 flex-shrink-0">
                                <span class="fas <?php echo $ico; ?> fs-9"></span>
                            </div>
                            <div class="flex-1 min-width-0">
                                    <span class="fw-semi-bold fs-10 text-900 text-truncate d-block">
                                        <?php echo htmlspecialchars($f['originalName']); ?>
                                    </span>
                                <span class="text-500 fs-11">
                                        <?php echo formatBytes((int)$f['fileSize']); ?>
                                    <?php if ($f['txnDesc']): ?>
                                        &middot; linked to: <?php echo htmlspecialchars($f['txnDesc']); ?>
                                    <?php endif; ?>
                                        &middot; <?php echo date("M j, Y", strtotime($f['uploaded_at'])); ?>
                                    </span>
                            </div>
                            <!-- Hover-reveal actions -->
                            <div class="hover-actions bg-100 end-0 pe-3">
                                <?php if ($isImage): ?>
                                    <a href="<?php echo $downloadPath; ?>"
                                       class="btn btn-outline-primary icon-item rounded-3 me-1 fs-11 icon-item-sm glightbox"
                                       data-gallery="attachments"
                                       data-title="<?php echo htmlspecialchars($f['originalName']); ?>"
                                       title="View">
                                        <span class="fas fa-eye"></span>
                                    </a>
                                <?php elseif ($isDoc): ?>
                                    <button class="btn btn-outline-primary icon-item rounded-3 me-1 fs-11 icon-item-sm"
                                            title="View"
                                            onclick="openDocViewer('<?php echo addslashes($absoluteUrl); ?>','<?php echo htmlspecialchars(addslashes($f['originalName'])); ?>')">
                                        <span class="fas fa-eye"></span>
                                    </button>
                                <?php else: ?>
                                    <a href="<?php echo $downloadPath; ?>" target="_blank"
                                       class="btn btn-outline-primary icon-item rounded-3 me-1 fs-11 icon-item-sm"
                                       title="View">
                                        <span class="fas fa-eye"></span>
                                    </a>
                                <?php endif; ?>
                                <form method="POST" action="delete_project_attachment" class="d-inline mb-0">
                                    <input type="hidden" name="attachmentID" value="<?php echo $f['attachmentID']; ?>">
                                    <input type="hidden" name="projectID" value="<?php echo $encodedID; ?>">
                                    <button type="submit"
                                            class="btn btn-outline-danger icon-item rounded-3 fs-11 icon-item-sm"
                                            title="Delete"
                                            onclick="return confirm('Delete this attachment?')">
                                        <span class="fas fa-trash"></span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php }
                } else { ?>
                    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-600" style="min-height:180px;">
                        <span class="fas fa-paperclip fs-2 text-300 mb-2"></span>
                        <span>No attachments yet. Upload receipts, quotes, or invoices.</span>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Document Viewer Modal ── -->
<div class="modal fade" id="docViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                <div class="position-relative z-1">
                    <h4 class="mb-0 text-white" id="docViewerTitle">Document Preview</h4>
                </div>
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-2 me-2"
                        data-bs-dismiss="modal" onclick="clearDocViewer()"></button>
            </div>
            <div class="modal-body p-0" style="height:80vh;">
                <iframe id="docViewerFrame" src="" width="100%" height="100%"
                        style="border:none; display:block;"></iframe>
            </div>
            <div class="modal-footer py-2">
                    <span class="text-500 fs-11 me-auto" id="docViewerNote">
                        <span class="fas fa-info-circle me-1"></span>
                        Previewed via Microsoft Office Online Viewer. Download for full formatting.
                    </span>
                <a id="docViewerDownload" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                    <span class="fas fa-download me-1"></span>Download
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal" onclick="clearDocViewer()">Close</button>
            </div>
        </div>
    </div>
</div>



<!-- Add Transaction -->
<div class="modal fade" id="addTxnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="add_project_transaction">
                <input type="hidden" name="projectID" value="<?php echo $projectID; ?>">
                <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1"><h4 class="mb-0 text-white">Add Transaction</h4></div>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type" required>
                            <option value="Expense">Expense</option>
                            <option value="Income">Income</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <input type="text" class="form-control" name="category" placeholder="e.g. Labour, Materials, Sales">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" required placeholder="Brief description">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (Ksh)</label>
                        <input type="number" class="form-control" name="amount" required min="0.01" step="0.01" placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tag / Wallet</label>
                        <input type="text" class="form-control" name="tag" placeholder="e.g. M-Pesa, Cash, Bank">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="transactionDate" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><span class="fas fa-save me-1"></span>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Transaction -->
<div class="modal fade" id="editTxnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="edit_project_transaction">
                <input type="hidden" name="projectID" value="<?php echo $encodedID; ?>">
                <input type="hidden" id="editTxnID" name="transactionID">
                <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1"><h4 class="mb-0 text-white">Edit Transaction</h4></div>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" id="editTxnType" name="type" required>
                            <option value="Expense">Expense</option>
                            <option value="Income">Income</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <input type="text" class="form-control" id="editTxnCategory" name="category" placeholder="e.g. Labour, Materials">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" id="editTxnDescription" name="description" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (Ksh)</label>
                        <input type="number" class="form-control" id="editTxnAmount" name="amount" required min="0.01" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tag / Wallet</label>
                        <input type="text" class="form-control" id="editTxnTag" name="tag">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" id="editTxnDate" name="transactionDate" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><span class="fas fa-save me-1"></span>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Transaction -->
<div class="modal fade" id="deleteTxnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="delete_project_transaction">
                <input type="hidden" id="deleteTxnProjectID" name="projectID">
                <input type="hidden" id="deleteTxnID" name="transactionID">
                <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1"><h4 class="mb-0 text-white">Delete Transaction</h4></div>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to remove this transaction? This cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><span class="fas fa-trash me-1"></span>Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Note -->
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="add_project_note">
                <input type="hidden" name="projectID" value="<?php echo $encodedID; ?>">
                <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1"><h4 class="mb-0 text-white">Add Note</h4></div>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <textarea class="form-control" name="note" rows="5" required placeholder="Record a decision, blocker, or update..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><span class="fas fa-save me-1"></span>Save Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Attachment -->
<div class="modal fade" id="uploadAttachModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="upload_project_attachment" enctype="multipart/form-data">
                <input type="hidden" name="projectID" value="<?php echo $encodedID; ?>">
                <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1"><h4 class="mb-0 text-white">Upload Attachment</h4></div>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">File</label>
                        <input type="file" class="form-control" name="attachment" required
                               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv">
                        <div class="form-text">Max 10 MB. Allowed: images, PDF, Word, Excel, CSV, TXT.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Link to Transaction <span class="text-500">(optional)</span></label>
                        <select class="form-select" name="transactionID">
                            <option value="">— Project level —</option>
                            <?php
                            $stmtTL = $con->prepare("SELECT transactionID, description, transactionDate FROM tbl_project_transactions WHERE projectID=? ORDER BY transactionDate DESC");
                            $stmtTL->bind_param("i", $projectID);
                            $stmtTL->execute();
                            $resTL = $stmtTL->get_result();
                            while ($tl = $resTL->fetch_assoc()) {
                                echo '<option value="' . $tl['transactionID'] . '">'
                                    . date("M j, Y", strtotime($tl['transactionDate'])) . ' — '
                                    . htmlspecialchars($tl['description'])
                                    . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><span class="fas fa-upload me-1"></span>Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function populateEditTxnModal(id, type, category, description, amount, tag, date) {
        document.getElementById('editTxnID').value          = id;
        document.getElementById('editTxnType').value        = type;
        document.getElementById('editTxnCategory').value    = category;
        document.getElementById('editTxnDescription').value = description;
        document.getElementById('editTxnAmount').value      = amount;
        document.getElementById('editTxnTag').value         = tag;
        document.getElementById('editTxnDate').value        = date;
    }
    function populateDeleteTxnModal(txnID, encodedProjectID) {
        document.getElementById('deleteTxnID').value        = txnID;
        document.getElementById('deleteTxnProjectID').value = encodedProjectID;
    }
    function openDocViewer(fileUrl, fileName) {
        var isPdf = fileName.toLowerCase().endsWith('.pdf');
        var isTxt = fileName.toLowerCase().endsWith('.txt') || fileName.toLowerCase().endsWith('.csv');
        var viewerUrl;
        if (isPdf || isTxt) {
            viewerUrl = fileUrl;
            document.getElementById('docViewerNote').innerHTML =
                '<span class="fas fa-info-circle me-1"></span>Viewing directly in browser.';
        } else {
            viewerUrl = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(fileUrl);
            document.getElementById('docViewerNote').innerHTML =
                '<span class="fas fa-info-circle me-1"></span>Previewed via Microsoft Office Online Viewer. Download for full formatting.';
        }
        document.getElementById('docViewerTitle').textContent = fileName;
        document.getElementById('docViewerFrame').src         = viewerUrl;
        document.getElementById('docViewerDownload').href     = fileUrl;
        var modal = new bootstrap.Modal(document.getElementById('docViewerModal'));
        modal.show();
    }
    function clearDocViewer() {
        document.getElementById('docViewerFrame').src = '';
    }
</script>

<?php include "footer.php"; ?>

<script>
    (function () {
        // ── Doc viewer modal cleanup ──
        var docModal = document.getElementById('docViewerModal');
        if (docModal) {
            docModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('docViewerFrame').src = '';
            });
        }

        // ── GLightbox ──
        if (typeof GLightbox !== 'undefined') {
            GLightbox({ selector: '.glightbox', touchNavigation: true, loop: false });
        }

        // ── ECharts donut ──
        var chartDom     = document.getElementById('expenseChart');
        var expenseChart = echarts.init(chartDom);
        var data = [
            <?php
            $stmtC = $con->prepare("SELECT COALESCE(NULLIF(category,''),'Uncategorised') AS category, SUM(amount) AS totalAmount FROM tbl_project_transactions WHERE projectID=? AND type='Expense' GROUP BY category ORDER BY totalAmount DESC");
            $stmtC->bind_param("i", $projectID);
            $stmtC->execute();
            $resC = $stmtC->get_result();
            while ($rc = $resC->fetch_assoc()) {
                echo "{ value: " . $rc['totalAmount'] . ", name: '" . addslashes($rc['category']) . "' },";
            }
            ?>
        ];
        var hasData = data.length > 0;
        expenseChart.setOption({
            backgroundColor: 'transparent',
            graphic: hasData ? [] : [{ type:'text', left:'center', top:'middle', style:{ text:'No expense data yet', fill:'#9da9bb', fontSize:13 } }],
            tooltip: {
                trigger: 'item',
                formatter: function(p) { return p.name + '<br/>Ksh ' + p.value.toLocaleString() + ' (' + p.percent + '%)'; },
                backgroundColor: 'rgba(50,50,50,0.8)',
                textStyle: { color: '#fff', fontSize: 13 }
            },
            legend: { bottom: '2%', left: 'center', textStyle: { fontSize: 12 }, itemWidth: 10, itemHeight: 10, itemGap: 12 },
            series: [{
                name: 'Expenses', type: 'pie', radius: ['38%','66%'], center: ['50%','48%'],
                data: hasData ? data : [],
                label: { show: true, formatter: '{b}\n{d}%', fontSize: 12 },
                labelLine: { length: 12, length2: 8 },
                itemStyle: { borderWidth: 3 },
                emphasis: { itemStyle: { shadowBlur: 16, shadowColor: 'rgba(0,0,0,0.4)' } },
                color: ['#2A7BE4','#00d27a','#e63757','#f5803e','#748194','#27bcfd','#6c757d','#fd7e14','#20c997','#6610f2']
            }]
        });
        window.addEventListener('resize', function () { expenseChart.resize(); });
    })();
</script>