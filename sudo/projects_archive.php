<?php include "head.php";?>
    <title>Projects Archive</title>
<?php include "navi.php";
$status = "OK"; $msg = "";
?>

<?php
if (isset($_SESSION['alert'])) { echo $_SESSION['alert']; unset($_SESSION['alert']); }
?>

    <!-- Page Header -->
    <div class="card shadow-none border mb-3">
        <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
            <div class="d-flex align-items-center gap-2">
                <a href="projects" class="btn btn-sm btn-outline-secondary">
                    <span class="fas fa-chevron-left fs-11 me-1"></span>Active Projects
                </a>
                <h5 class="mb-0 text-primary"><span class="fas fa-trophy me-1"></span>Achieved Projects Archive</h5>
            </div>
        </div>
    </div>

<?php
// Summary totals across all achieved projects
$sqlSummary = "
        SELECT
            COUNT(*) AS totalProjects,
            COALESCE(SUM(p.projectAmount), 0) AS totalBudget,
            COALESCE(SUM(CASE WHEN t.type='Expense' THEN t.amount ELSE 0 END), 0) AS totalSpent,
            COALESCE(SUM(CASE WHEN t.type='Income'  THEN t.amount ELSE 0 END), 0) AS totalIncome
        FROM tbl_projects p
        LEFT JOIN tbl_project_transactions t ON t.projectID = p.projectID
        WHERE p.is_achieved = 1 AND p.is_deleted = 0";
$sumRow = $con->query($sqlSummary)->fetch_assoc();
$sumNet = $sumRow['totalIncome'] - $sumRow['totalSpent'];
$sumSaved = $sumRow['totalBudget'] - $sumRow['totalSpent'];
?>

    <!-- Archive Summary Cards -->
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xxl-3">
            <div class="card h-100 font-sans-serif">
                <div class="card-header bg-body-tertiary py-2">
                    <h6 class="mb-0 text-600"><span class="fas fa-trophy text-warning me-1"></span>Projects Completed</h6>
                </div>
                <div class="card-body">
                    <h4 class="text-primary mb-0"><?php echo number_format($sumRow['totalProjects']); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xxl-3">
            <div class="card h-100 font-sans-serif">
                <div class="card-header bg-body-tertiary py-2">
                    <h6 class="mb-0 text-600"><span class="fas fa-arrow-down text-danger me-1"></span>Total Spent</h6>
                </div>
                <div class="card-body">
                    <h4 class="text-danger mb-1">Ksh <?php echo number_format($sumRow['totalSpent'], 2); ?></h4>
                    <span class="badge badge-subtle-info fs-11">vs Ksh <?php echo number_format($sumRow['totalBudget'], 2); ?> budgeted</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xxl-3">
            <div class="card h-100 font-sans-serif">
                <div class="card-header bg-body-tertiary py-2">
                    <h6 class="mb-0 text-600"><span class="fas fa-arrow-up text-success me-1"></span>Total Income</h6>
                </div>
                <div class="card-body">
                    <h4 class="text-success mb-0">Ksh <?php echo number_format($sumRow['totalIncome'], 2); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xxl-3">
            <div class="card h-100 font-sans-serif">
                <div class="card-header bg-body-tertiary py-2">
                    <h6 class="mb-0 text-600"><span class="fas fa-balance-scale me-1"></span>Overall Net</h6>
                </div>
                <div class="card-body">
                    <h4 class="<?php echo $sumNet >= 0 ? 'text-success' : 'text-danger'; ?> mb-1">
                        <?php echo ($sumNet >= 0 ? '+' : ''); ?>Ksh <?php echo number_format(abs($sumNet), 2); ?>
                    </h4>
                    <span class="badge <?php echo $sumSaved >= 0 ? 'badge-subtle-success' : 'badge-subtle-danger'; ?> fs-11">
                        <?php echo $sumSaved >= 0
                            ? 'Ksh ' . number_format($sumSaved, 2) . ' saved vs budget'
                            : 'Ksh ' . number_format(abs($sumSaved), 2) . ' over budget total'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Achieved Projects Table -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card overflow-hidden">
                <div class="card-header bg-body-tertiary py-2">
                    <h5 class="mb-0 text-primary">All Achieved Projects</h5>
                </div>
                <div class="card-body px-0 pt-0">
                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables="data-datatables"
                           data-datatables-language='{"emptyTable":"No transactions yet. Add your first one above."}'>
                        <thead class="bg-200">
                        <tr>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Project</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Budget (Ksh)</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Final Spend (Ksh)</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Income (Ksh)</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Net</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">vs Budget</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Due Date</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Completed</th>
                            <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                        </tr>
                        </thead>
                        <tbody class="list">
                        <?php
                        $sqlArc = "
                            SELECT
                                p.projectID, p.projectName, p.projectDescription,
                                p.projectAmount, p.projectPeriod, p.completed_at,
                                COALESCE(SUM(CASE WHEN t.type='Expense' THEN t.amount ELSE 0 END), 0) AS totalSpent,
                                COALESCE(SUM(CASE WHEN t.type='Income'  THEN t.amount ELSE 0 END), 0) AS totalIncome
                            FROM tbl_projects p
                            LEFT JOIN tbl_project_transactions t ON t.projectID = p.projectID
                            WHERE p.is_achieved = 1 AND p.is_deleted = 0
                            GROUP BY p.projectID, p.projectName, p.projectDescription, p.projectAmount, p.projectPeriod, p.completed_at
                            ORDER BY p.completed_at DESC, p.projectID DESC";
                        $resArc = $con->query($sqlArc);

                        if ($resArc && $resArc->num_rows > 0) {
                            while ($a = $resArc->fetch_assoc()) {
                                $aID      = $a['projectID'];
                                $aName    = htmlspecialchars($a['projectName']);
                                $aDesc    = htmlspecialchars($a['projectDescription']);
                                $aBudget  = $a['projectAmount'];
                                $aSpent   = $a['totalSpent'];
                                $aIncome  = $a['totalIncome'];
                                $aNet     = $aIncome - $aSpent;
                                $aSaved   = $aBudget - $aSpent;
                                $aDue     = date("M j, Y", strtotime($a['projectPeriod']));
                                $aComp    = $a['completed_at'] ? date("M j, Y", strtotime($a['completed_at'])) : '—';
                                $encID    = base64_encode($aID);
                                $netClass = $aNet >= 0 ? 'text-success' : 'text-danger';
                                $savClass = $aSaved >= 0 ? 'text-success' : 'text-danger';
                                $netBadge = $aNet >= 0
                                    ? '<span class="badge badge-subtle-success fs-11">+Ksh ' . number_format($aNet, 2) . '</span>'
                                    : '<span class="badge badge-subtle-danger fs-11">−Ksh ' . number_format(abs($aNet), 2) . '</span>';
                                $savBadge = $aSaved >= 0
                                    ? '<span class="badge badge-subtle-success fs-11">Saved Ksh ' . number_format($aSaved, 2) . '</span>'
                                    : '<span class="badge badge-subtle-danger fs-11">Over Ksh ' . number_format(abs($aSaved), 2) . '</span>';
                                echo "
                                <tr class='hover-actions-trigger btn-reveal-trigger hover-bg-100'>
                                    <td class='align-middle'>
                                        <h6 class='mb-0 fw-semi-bold text-nowrap'>
                                            <a class='text-900' href='project-details?projectID={$encID}'>{$aName}</a>
                                        </h6>
                                        <p class='mb-0 text-500 fs-11'>{$aDesc}</p>
                                    </td>
                                    <td class='align-middle fw-semi-bold'>" . number_format($aBudget, 2) . "</td>
                                    <td class='align-middle text-danger fw-semi-bold'>" . number_format($aSpent, 2) . "</td>
                                    <td class='align-middle text-success fw-semi-bold'>" . number_format($aIncome, 2) . "</td>
                                    <td class='align-middle'>{$netBadge}</td>
                                    <td class='align-middle'>{$savBadge}</td>
                                    <td class='align-middle text-500'>{$aDue}</td>
                                    <td class='align-middle text-500'>{$aComp}</td>
                                    <td class='align-middle white-space-nowrap text-end position-relative'>
                                        <div class='hover-actions bg-100'>
                                            <a href='project-details?projectID={$encID}' class='btn btn-outline-primary icon-item rounded-3 fs-11 icon-item-sm' title='View'>
                                                <span class='fas fa-eye'></span>
                                            </a>
                                        </div>
                                        <div class='dropdown font-sans-serif btn-reveal-trigger'>
                                            <button class='btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none' type='button' data-bs-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
                                                <span class='fas fa-chevron-left fs-11'></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>";
                            }
                        } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php include "footer.php"; ?>