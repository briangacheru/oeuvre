<?php include "head.php";?>
    <title>Overdraft | iTasker</title>
<?php include "navi.php";?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Overdraft & Bonus <span class="text-info fw-medium"> Records</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0">
                    <form class="row flex-lg-column flex-xxl-row gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
                        <div class="col-auto">
                        </div>
                        <div class="col-md-auto position-relative">
                            <h6 class="mb-1 badge rounded-pill badge-subtle-info"><?php echo date("jS F Y"); ?> | <span id="timeDisplay"></span></h6>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php
if (isset($_SESSION['alert'])) {
    echo $_SESSION['alert'];
    unset($_SESSION['alert']); // Clear the alert message
}
?>
    <div class="card-body mb-3 bg-body-tertiary">
        <div class="tab-content">
            <div class="tab-pane preview-tab-pane active show" role="tabpanel">
                <form method="post" id="overdraftFormView">
                    <?php
                    if (isset($_SESSION['sessionWriter'])) {
                        $email = $_SESSION['sessionWriter'];

                        // Get writer name from email
                        $writer_query = "SELECT username FROM tblwriters WHERE email = ? AND is_deleted = 0";
                        $writer_stmt = mysqli_prepare($con, $writer_query);
                        mysqli_stmt_bind_param($writer_stmt, "s", $email);
                        mysqli_stmt_execute($writer_stmt);
                        $writer_result = mysqli_stmt_get_result($writer_stmt);
                        $writer_data = mysqli_fetch_assoc($writer_result);
                        $writer_name = $writer_data['username'] ?? '';

                        // Auto-load financial data for the current writer
                        if (!empty($writer_name)) {
                            // Get completed tasks total
                            $tasks_query = "SELECT SUM(CPP*pages) AS total FROM tbltasks WHERE writer = ? AND is_deleted = 0 AND is_paid = 0 AND status = 'Completed'";
                            $tasks_stmt = mysqli_prepare($con, $tasks_query);
                            mysqli_stmt_bind_param($tasks_stmt, "s", $writer_name);
                            mysqli_stmt_execute($tasks_stmt);
                            $tasks_result = mysqli_stmt_get_result($tasks_stmt);
                            $tasks_row = mysqli_fetch_assoc($tasks_result);
                            $total_tasks = (float) ($tasks_row['total'] ?? 0);

                            // Get total overdrafts (excluding bonuses)
                            $overdraft_query = "SELECT SUM(amount) AS total FROM tbloverdrafts WHERE writer = ? AND is_settled = 0 AND is_deleted = 0 AND (record_type IS NULL OR record_type = 'overdraft')";
                            $overdraft_stmt = mysqli_prepare($con, $overdraft_query);
                            mysqli_stmt_bind_param($overdraft_stmt, "s", $writer_name);
                            mysqli_stmt_execute($overdraft_stmt);
                            $overdraft_result = mysqli_stmt_get_result($overdraft_stmt);
                            $overdraft_row = mysqli_fetch_assoc($overdraft_result);
                            $total_overdrafts = (float) ($overdraft_row['total'] ?? 0);

                            // Get total bonuses
                            $bonus_query = "SELECT SUM(amount) AS total FROM tbloverdrafts WHERE writer = ? AND is_settled = 0 AND is_deleted = 0 AND record_type = 'bonus'";
                            $bonus_stmt = mysqli_prepare($con, $bonus_query);
                            mysqli_stmt_bind_param($bonus_stmt, "s", $writer_name);
                            mysqli_stmt_execute($bonus_stmt);
                            $bonus_result = mysqli_stmt_get_result($bonus_stmt);
                            $bonus_row = mysqli_fetch_assoc($bonus_result);
                            $total_bonuses = (float) ($bonus_row['total'] ?? 0);

                            $amount_due = $total_tasks + $total_bonuses  - $total_overdrafts;
                        } else {
                            $total_tasks = 0;
                            $total_overdrafts = 0;
                            $total_bonuses = 0;
                            $amount_due = 0;
                        }
                    } else {
                        // Session not set
                        $total_tasks = 0;
                        $total_overdrafts = 0;
                        $total_bonuses = 0;
                        $amount_due = 0;
                        $writer_name = '';
                        $email = '';
                    }
                    ?>

                    <div class="row ms-2">
                        <div class="col-md-6">
                            <div class="mb-3 row">
                                <label class="col-sm-6 col-form-label fw-bold">Unpaid Tasks Total:</label>
                                <div class="col-sm-6">
                                    <div class="form-control-plaintext fw-bold text-success">Ksh. <?php echo number_format($total_tasks, 2); ?></div>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-6 col-form-label fw-bold">Total Overdrafts:</label>
                                <div class="col-sm-6">
                                    <div class="form-control-plaintext fw-bold text-warning">Ksh. <?php echo number_format($total_overdrafts, 2); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 row">
                                <label class="col-sm-6 col-form-label fw-bold">Total Bonuses:</label>
                                <div class="col-sm-6">
                                    <div class="form-control-plaintext fw-bold text-info">Ksh. <?php echo number_format($total_bonuses, 2); ?></div>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-6 col-form-label fw-bold">Amount Due:</label>
                                <div class="col-sm-6">
                                    <div class="form-control-plaintext fw-bold <?php echo ($amount_due >= 0) ? 'text-success' : 'text-danger'; ?>">Ksh. <?php echo number_format($amount_due, 2); ?></div>
                                    <small class="text-muted">Unpaid Tasks + Bonuses - Overdrafts</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col">
            <div class="card mb-3">
                <div class="card-body p-0">
                    <div class="tab-content">
                        <div class="tab-pane preview-tab-pane active" role="tabpanel">
                            <div class="card shadow-none">
                                <form id="tasksForm" method="post">
                                    <div class="card-header">
                                        <div class="row flex-between-center">
                                            <div class="col-6 col-sm-auto d-flex align-items-center pe-0">
                                                <div class="btn-group ms-3" role="group">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="filter-all" onclick="filterRecords('all')">All</button>
                                                    <button type="button" class="btn btn-outline-warning btn-sm" id="filter-overdraft" onclick="filterRecords('overdraft')">Overdrafts</button>
                                                    <button type="button" class="btn btn-outline-info btn-sm" id="filter-bonus" onclick="filterRecords('bonus')">Bonuses</button>
                                                </div>
                                            </div>
                                            <div class="col-6 col-sm-auto ms-auto text-end ps-0">
                                                <div class="d-flex align-items-center" id="table-simple-pagination-replace-element">
                                                    <button class="btn btn-falcon-primary btn-sm" onclick="exportTableToCSVWithConfirmation('financial_records.csv')" data-bs-toggle="tooltip" data-bs-placement="top" title="Export as CSV" type="button"><span class="fas fa-external-link-alt" data-fa-transform="shrink-3 down-2"></span><span class="d-none d-sm-inline-block ms-1">Export as CSV</span></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body px-0 pt-0">
                                        <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables="data-datatables">
                                            <thead class="bg-200">
                                            <tr>
                                                <th class="text-900 no-sort white-space-nowrap d-none">
                                                    <div class="form-check mb-0 d-flex align-items-center">
                                                        <input class="form-check-input" id="checkbox-select-all" type="checkbox" onclick="selectAllTasks(this)" data-bulk-select='{"body":"table-simple-pagination-body","actions":"table-simple-pagination-actions","replacedElement":"table-simple-pagination-replace-element"}' />
                                                    </div>
                                                </th>
                                                <th class="text-900 sort pe-1 align-middle white-space-nowrap ps-4">Type</th>
                                                <th class="text-900 sort pe-1 align-middle white-space-nowrap"><span class="ms-3">Amount (Ksh)</span></th>
                                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Description</th>
                                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Transaction Date</th>
                                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Status</th>
                                            </tr>
                                            </thead>
                                            <tbody class="list" id="table-simple-pagination-body">
                                            <?php
                                            if (isset($_SESSION['sessionWriter'])) {
                                                $email = $_SESSION['sessionWriter'];
                                                    $query = mysqli_query($con, "SELECT * FROM tbloverdrafts WHERE is_settled = 0 AND is_deleted = 0 AND email = '$email' ORDER BY od_date DESC");
                                                        } else {
                                                            $query = false;
                                                        }
                                            $cnt = 1;
                                            if ($query && mysqli_num_rows($query) > 0) {
                                            while ($row = mysqli_fetch_array($query)) {
                                                $encodedId = base64_encode($row["id"]);
                                                $recordType = isset($row["record_type"]) && !empty($row["record_type"]) ? $row["record_type"] : 'overdraft';
                                                $badgeClass = ($recordType === 'bonus') ? 'badge-subtle-info' : 'badge-subtle-warning';
                                                $typeDisplay = ($recordType === 'bonus') ? 'Bonus' : 'Overdraft';
                                                $amountClass = ($recordType === 'bonus') ? 'text-info' : 'text-warning';
                                                ?>
                                                <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100 record-row" data-record-type="<?php echo $recordType; ?>">
                                                    <td class="align-middle d-none" style="width: 28px;">
                                                        <div class="form-check mb-0">
                                                            <input class="form-check-input" type="checkbox" id="simple-pagination-item-<?php echo $cnt; ?>" data-bulk-select-row="data-bulk-select-row" value="<?php echo $row['id']; ?>" name="taskIds[]" />
                                                        </div>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap ps-4">
                                                        <span class="badge <?php echo $badgeClass; ?> rounded-pill"><?php echo $typeDisplay; ?></span>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap fw-semi-bold <?php echo $amountClass; ?>">
                                                        <span class="ms-3"><?php echo number_format($row['amount'], 2); ?></span>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap text-900"><?php echo htmlspecialchars($row['description']); ?></td>
                                                    <td class="align-middle white-space-nowrap text-900"><?php echo date("jS M, Y h:i A", strtotime($row['od_date'])); ?></td>
                                                    <td class="align-middle white-space-nowrap">
                                                        <?php if ($recordType === 'bonus'): ?>
                                                            <span class="badge badge-subtle-info rounded-pill">Credited</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-subtle-warning rounded-pill">Active</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php
                                                $cnt = $cnt + 1;
                                            }}
                                            ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set default filter to show all records
            filterRecords('all');
        });

        function clearForm() {
            document.getElementById('overdraftForm').reset();
        }

        function exportTableToCSVWithConfirmation(filename) {
            if (confirm("Are you sure you want to export the table as a CSV file?")) {
                exportTableToCSV(filename);
            }
        }

        function exportTableToCSV(filename) {
            var csv = [];
            var rows = document.querySelectorAll("table tr");

            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll("td, th");

                for (var j = 0; j < cols.length; j++) {
                    // Skip hidden columns and clean up the text
                    var cellText = cols[j].innerText.trim();
                    if (cellText !== '') {
                        row.push('"' + cellText.replace(/"/g, '""') + '"');
                    }
                }

                if (row.length > 0) {
                    csv.push(row.join(","));
                }
            }

            // Download CSV
            downloadCSV(csv.join("\n"), filename);
        }

        function downloadCSV(csv, filename) {
            var csvFile;
            var downloadLink;

            csvFile = new Blob([csv], {type: "text/csv"});

            downloadLink = document.createElement("a");

            downloadLink.download = filename;

            downloadLink.href = window.URL.createObjectURL(csvFile);

            downloadLink.style.display = "none";

            document.body.appendChild(downloadLink);

            downloadLink.click();
        }

        function filterRecords(type) {
            const rows = document.querySelectorAll('.record-row');
            const filterButtons = document.querySelectorAll('[id^="filter-"]');

            // Update button states
            filterButtons.forEach(btn => {
                btn.classList.remove('btn-outline-warning', 'btn-outline-info', 'btn-outline-secondary', 'btn-warning', 'btn-info', 'btn-secondary');
                if (btn.id === `filter-${type}`) {
                    if (type === 'all') btn.classList.add('btn-secondary');
                    else if (type === 'overdraft') btn.classList.add('btn-warning');
                    else if (type === 'bonus') btn.classList.add('btn-info');
                } else {
                    btn.classList.add('btn-outline-secondary');
                }
            });

            // Show/hide rows based on filter
            rows.forEach(row => {
                const recordType = row.getAttribute('data-record-type');
                if (type === 'all' || recordType === type) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
<?php
include "footer.php";
?>