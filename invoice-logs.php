<?php include "head.php";?>
    <title>My Invoices | iTasker</title>
<?php include "navi.php";

// ── Resolve writer from session ───────────────────────────────────────────
$writerEmail = '';
$writerName  = '';

if (isset($_SESSION['sessionWriter'])) {
    $writerEmail = $_SESSION['sessionWriter'];

    $wStmt = mysqli_prepare($con,
        "SELECT username FROM tblwriters WHERE email = ? AND is_deleted = 0 LIMIT 1"
    );
    mysqli_stmt_bind_param($wStmt, 's', $writerEmail);
    mysqli_stmt_execute($wStmt);
    $wResult = mysqli_stmt_get_result($wStmt);
    $wRow    = mysqli_fetch_assoc($wResult);
    $writerName = $wRow['username'] ?? '';
    mysqli_stmt_close($wStmt);
}

// Redirect if session missing
if (empty($writerName)) {
    header('Location: login');
    exit;
}

// ── Stats for the strip ───────────────────────────────────────────────────
$statRow = mysqli_fetch_assoc(mysqli_query($con,
    "SELECT
        COUNT(*)                        AS total_invoices,
        SUM(tasks_total)                AS total_tasks,
        SUM(bonus_total)                AS total_bonuses,
        SUM(overdraft_total)            AS total_overdrafts,
        SUM(amount_payable)             AS total_payable
     FROM tbl_invoice_logs
     WHERE writer_name = '" . mysqli_real_escape_string($con, $writerName) . "'"
));
?>

    <!-- Page header -->
    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block"
             style="background-image:url(assets/img/illustrations/corner-6.png);"></div>
        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">My <span class="text-info fw-medium">Invoices</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0">
                    <h6 class="mb-1 badge rounded-pill badge-subtle-info">
                        <?php echo date("jS F Y"); ?> | <span id="timeDisplay"></span>
                    </h6>
                </div>
            </div>
        </div>
    </div>

<?php
if (isset($_SESSION['alert'])) {
    echo $_SESSION['alert'];
    unset($_SESSION['alert']);
}
?>

    <!-- Stats strip -->
    <div class="card-body mb-3 bg-body-tertiary">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 h-100" style="background:rgba(0,115,230,.07);border:1px solid rgba(0,115,230,.15);">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fas fa-paper-plane text-primary"></span>
                        <span class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Invoices Received</span>
                    </div>
                    <div class="fw-bold text-primary fs-4"><?php echo (int)($statRow['total_invoices'] ?? 0); ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 h-100" style="background:rgba(0,210,210,.07);border:1px solid rgba(0,210,210,.18);">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fas fa-gift text-info"></span>
                        <span class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Total Bonuses</span>
                    </div>
                    <div class="fw-bold text-info fs-5">Ksh <?php echo number_format((float)($statRow['total_bonuses'] ?? 0), 2); ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 h-100" style="background:rgba(229,83,83,.07);border:1px solid rgba(229,83,83,.18);">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fas fa-minus-circle text-danger"></span>
                        <span class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Total Overdrafts</span>
                    </div>
                    <div class="fw-bold text-danger fs-5">Ksh <?php echo number_format((float)($statRow['total_overdrafts'] ?? 0), 2); ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 h-100" style="background:rgba(0,200,83,.07);border:1px solid rgba(0,200,83,.18);">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fas fa-wallet text-success"></span>
                        <span class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Total Paid Out</span>
                    </div>
                    <div class="fw-bold text-success fs-5">Ksh <?php echo number_format((float)($statRow['total_payable'] ?? 0), 2); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoices table -->
    <div class="row g-3 mb-3">
        <div class="col">
            <div class="card mb-3">
                <div class="card-body p-0">
                    <div class="tab-content">
                        <div class="tab-pane preview-tab-pane active" role="tabpanel">
                            <div class="card shadow-none">
                                <div class="card-header">
                                    <div class="row flex-between-center">
                                        <div class="col-auto align-self-center">
                                            <h5 class="mb-0">Invoice History</h5>
                                        </div>
                                        <div class="col-auto ms-auto text-end">
                                            <button class="btn btn-falcon-primary btn-sm"
                                                    onclick="exportTableToCSVWithConfirmation('my_invoices.csv')"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="Export as CSV" type="button">
                                                <span class="fas fa-external-link-alt" data-fa-transform="shrink-3 down-2"></span>
                                                <span class="d-none d-sm-inline-block ms-1">Export as CSV</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body px-0 pt-0">
                                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables="data-datatables">
                                        <thead class="bg-200">
                                        <tr>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">#</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap text-end">Tasks Total</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap text-end">Bonuses</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap text-end">Overdrafts</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap text-end">Amount Paid</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap text-center">Date Sent</th>
                                            <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                                        </tr>
                                        </thead>
                                        <tbody class="list">
                                        <?php
                                        $safeWriter = mysqli_real_escape_string($con, $writerName);
                                        $logs = mysqli_query($con,
                                            "SELECT * FROM tbl_invoice_logs
                                             WHERE writer_name = '$safeWriter'
                                             ORDER BY sent_at DESC"
                                        );
                                        $cnt = 1;
                                        if ($logs && mysqli_num_rows($logs) > 0):
                                            while ($log = mysqli_fetch_assoc($logs)):
                                                $taskCount     = (int) $log['task_count'];
                                                $bonusCount    = (int) $log['bonus_count'];
                                                $overdraftCount = (int) $log['overdraft_count'];
                                                ?>
                                                <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100 invoice-row"
                                                    style="cursor: pointer;"
                                                    data-bs-toggle="modal" data-bs-target="#invoice-detail-modal"
                                                    data-log-id="<?php echo $log['id']; ?>"
                                                    data-log='<?php echo htmlspecialchars(json_encode([
                                                        "sent_at"         => date("jS M Y, h:i A", strtotime($log["sent_at"] . ' UTC')),
                                                        "tasks_total"     => number_format((float)$log["tasks_total"], 2),
                                                        "bonus_total"     => number_format((float)$log["bonus_total"], 2),
                                                        "overdraft_total" => number_format((float)$log["overdraft_total"], 2),
                                                        "amount_payable"  => number_format((float)$log["amount_payable"], 2),
                                                        "task_count"      => $taskCount,
                                                        "bonus_count"     => $bonusCount,
                                                        "overdraft_count" => $overdraftCount,
                                                    ]), ENT_QUOTES, "UTF-8"); ?>'
                                                    onclick="handleRowClick(event, this);">
                                                    <td class="align-middle white-space-nowrap ps-3 text-900"><?php echo $cnt; ?></td>
                                                    <td class="align-middle white-space-nowrap text-end text-900">
                                                        Ksh <?php echo number_format((float)$log['tasks_total'], 2); ?>
                                                        <span class="ms-1 badge badge-subtle-primary rounded-pill"><?php echo $taskCount; ?></span>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap text-end">
                                                        <?php if ($bonusCount > 0): ?>
                                                            <span class="text-info fw-semibold">Ksh <?php echo number_format((float)$log['bonus_total'], 2); ?></span>
                                                            <span class="ms-1 badge badge-subtle-info rounded-pill">+<?php echo $bonusCount; ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap text-end">
                                                        <?php if ($overdraftCount > 0): ?>
                                                            <span class="text-danger fw-semibold">Ksh <?php echo number_format((float)$log['overdraft_total'], 2); ?></span>
                                                            <span class="ms-1 badge badge-subtle-danger rounded-pill">-<?php echo $overdraftCount; ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap text-end fw-bold text-success">
                                                        Ksh <?php echo number_format((float)$log['amount_payable'], 2); ?>
                                                    </td>
                                                    <td class="align-middle text-center white-space-nowrap text-900">
                                                        <?php echo date("jS M, Y h:i A", strtotime($log['sent_at'] . ' UTC')); ?>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap text-end position-relative">
                                                        <div class="hover-actions bg-100">
                                                            <a class="btn bg-primary-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"
                                                               href="#"
                                                               data-bs-toggle="modal" data-bs-target="#invoice-detail-modal"
                                                               data-bs-placement="top" title="View details"
                                                               data-log-id="<?php echo $log['id']; ?>"
                                                               data-log='<?php echo htmlspecialchars(json_encode([
                                                                   "sent_at"         => date("jS M Y, h:i A", strtotime($log["sent_at"] . ' UTC')),
                                                                   "tasks_total"     => number_format((float)$log["tasks_total"], 2),
                                                                   "bonus_total"     => number_format((float)$log["bonus_total"], 2),
                                                                   "overdraft_total" => number_format((float)$log["overdraft_total"], 2),
                                                                   "amount_payable"  => number_format((float)$log["amount_payable"], 2),
                                                                   "task_count"      => $taskCount,
                                                                   "bonus_count"     => $bonusCount,
                                                                   "overdraft_count" => $overdraftCount,
                                                               ]), ENT_QUOTES, "UTF-8"); ?>'
                                                               onclick="openInvoiceDetail(this); return false;">
                                                                <span class="fas fa-eye"></span>
                                                            </a>
                                                        </div>
                                                        <div class="dropdown font-sans-serif btn-reveal-trigger">
                                                            <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none"
                                                                    type="button" data-bs-toggle="dropdown"
                                                                    data-boundary="viewport" aria-haspopup="true" aria-expanded="false">
                                                                <span class="fas fa-chevron-left fs-11"></span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php $cnt++; endwhile;
                                        else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    <span class="fas fa-file-invoice fa-2x mb-2 d-block opacity-25"></span>
                                                    No invoices have been sent to you yet.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Detail Modal -->
    <div class="modal fade" id="invoice-detail-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable mt-6" role="document">
            <div class="modal-content border-0">
                <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1">
                        <h4 class="mb-0 text-white">Invoice Details</h4>
                        <p class="mb-0 fs-10 text-white opacity-75" id="inv-sent-at"></p>
                    </div>
                    <button class="btn-close position-absolute top-0 end-0 mt-2 me-2 btn-close-white"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 px-5">

                    <!-- Summary tiles -->
                    <div class="row g-2 mb-4">
                        <div class="col-6 col-sm-3">
                            <div class="p-3 rounded-3 text-center h-100" style="background:rgba(0,115,230,.07);border:1px solid rgba(0,115,230,.15);">
                                <div class="text-muted mb-1" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;">Tasks Total</div>
                                <div class="fw-bold text-primary fs-6" id="inv-tasks-total"></div>
                                <div class="text-muted" style="font-size:.68rem;" id="inv-task-count"></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-3 rounded-3 text-center h-100" style="background:rgba(0,210,210,.07);border:1px solid rgba(0,210,210,.18);">
                                <div class="text-muted mb-1" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;">Bonuses</div>
                                <div class="fw-bold text-info fs-6" id="inv-bonus-total"></div>
                                <div class="text-muted" style="font-size:.68rem;" id="inv-bonus-count"></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-3 rounded-3 text-center h-100" style="background:rgba(229,83,83,.07);border:1px solid rgba(229,83,83,.18);">
                                <div class="text-muted mb-1" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;">Overdrafts</div>
                                <div class="fw-bold text-danger fs-6" id="inv-overdraft-total"></div>
                                <div class="text-muted" style="font-size:.68rem;" id="inv-overdraft-count"></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-3 rounded-3 text-center h-100" style="background:rgba(0,200,83,.07);border:1px solid rgba(0,200,83,.18);">
                                <div class="text-muted mb-1" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;">Amount Paid</div>
                                <div class="fw-bold text-success fs-4" id="inv-amount-payable"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tasks breakdown table -->
                    <h6 class="fw-bold text-700 mb-2">
                        <span class="fas fa-file-alt me-1 text-primary"></span> Tasks Included in This Invoice
                    </h6>
                    <div id="inv-loading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        <span class="text-muted small">Loading tasks…</span>
                    </div>
                    <div id="inv-empty" class="text-muted small fst-italic py-2" style="display:none;">
                        No task details recorded for this invoice.
                    </div>
                    <div id="inv-table-wrap" style="display:none;">
                        <table class="table table-sm fs-10 mb-0">
                            <thead class="bg-200">
                            <tr>
                                <th class="text-900 align-middle white-space-nowrap">Task ID</th>
                                <th class="text-900 align-middle">Topic</th>
                                <th class="text-900 align-middle text-center">Pages</th>
                                <th class="text-900 align-middle text-end">CPP</th>
                                <th class="text-900 align-middle text-end">Amount</th>
                            </tr>
                            </thead>
                            <tbody id="inv-tbody"></tbody>
                        </table>
                    </div>

                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Live clock
        function updateTime() {
            var now = new Date();
            var h = now.getHours().toString().padStart(2,'0');
            var m = now.getMinutes().toString().padStart(2,'0');
            var s = now.getSeconds().toString().padStart(2,'0');
            var el = document.getElementById('timeDisplay');
            if (el) el.textContent = h + ':' + m + ':' + s;
        }
        setInterval(updateTime, 1000);
        updateTime();

        // Auto-dismiss alerts
        setTimeout(function() {
            var alert = document.querySelector('.alert');
            if (alert) { alert.classList.remove('show'); setTimeout(function(){ alert.remove(); }, 150); }
        }, 8000);

        function openInvoiceDetail(el) {
            var data  = JSON.parse(el.getAttribute('data-log'));
            var logId = el.getAttribute('data-log-id');

            // Populate summary tiles
            document.getElementById('inv-sent-at').textContent        = 'Sent: ' + data.sent_at;
            document.getElementById('inv-tasks-total').textContent    = 'Ksh ' + data.tasks_total;
            document.getElementById('inv-task-count').textContent     = data.task_count + ' task' + (data.task_count != 1 ? 's' : '');
            document.getElementById('inv-bonus-total').textContent    = data.bonus_count > 0 ? 'Ksh ' + data.bonus_total : '—';
            document.getElementById('inv-bonus-count').textContent    = data.bonus_count > 0 ? '+' + data.bonus_count + ' bonus' + (data.bonus_count != 1 ? 'es' : '') : '';
            document.getElementById('inv-overdraft-total').textContent = data.overdraft_count > 0 ? '− Ksh ' + data.overdraft_total : '—';
            document.getElementById('inv-overdraft-count').textContent = data.overdraft_count > 0 ? data.overdraft_count + ' deduction' + (data.overdraft_count != 1 ? 's' : '') : '';
            document.getElementById('inv-amount-payable').textContent = 'Ksh ' + data.amount_payable;

            // Reset table state
            var loading   = document.getElementById('inv-loading');
            var empty     = document.getElementById('inv-empty');
            var tableWrap = document.getElementById('inv-table-wrap');
            var tbody     = document.getElementById('inv-tbody');
            loading.style.display   = 'block';
            empty.style.display     = 'none';
            tableWrap.style.display = 'none';
            tbody.innerHTML         = '';

            // Fetch task items
            $.ajax({
                type: 'GET',
                url: 'get_invoice_items',
                data: { log_id: logId },
                dataType: 'json',
                success: function(response) {
                    loading.style.display = 'none';
                    if (!response.success || !response.items || response.items.length === 0) {
                        empty.style.display = 'block';
                        return;
                    }
                    tableWrap.style.display = 'block';
                    response.items.forEach(function(item) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td class="align-middle white-space-nowrap">' +
                            '<span class="badge badge-subtle-primary rounded-pill">#' + item.task_id + '</span>' +
                            '</td>' +
                            '<td class="align-middle text-900" style="max-width:240px;white-space:normal;">' + escHtml(item.topic) + '</td>' +
                            '<td class="align-middle text-center text-900">' + item.pages + '</td>' +
                            '<td class="align-middle text-end text-700">Ksh ' + parseFloat(item.cpp).toFixed(2) + '</td>' +
                            '<td class="align-middle text-end fw-bold text-primary">Ksh ' + parseFloat(item.amount).toFixed(2) + '</td>';
                        tbody.appendChild(tr);
                    });
                },
                error: function() {
                    loading.style.display = 'none';
                    empty.style.display   = 'block';
                    empty.textContent     = 'Could not load task details.';
                }
            });
        }

        function handleRowClick(event, rowEl) {
            // Don't trigger modal if user clicked the dropdown toggle, dropdown items,
            // or the existing eye-icon action button
            if (event.target.closest('.dropdown') ||
                event.target.closest('.hover-actions a') ||
                event.target.closest('button')) {
                return;
            }
            openInvoiceDetail(rowEl);
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function exportTableToCSVWithConfirmation(filename) {
            if (confirm("Export your invoice history as a CSV file?")) {
                exportTableToCSV(filename);
            }
        }

        function exportTableToCSV(filename) {
            var csv = [];
            var rows = document.querySelectorAll("table tr");
            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll("td, th");
                for (var j = 0; j < cols.length; j++) {
                    var cellText = cols[j].innerText.trim();
                    if (cellText !== '') row.push('"' + cellText.replace(/"/g, '""') + '"');
                }
                if (row.length > 0) csv.push(row.join(","));
            }
            var csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
            var link = document.createElement("a");
            link.download = filename;
            link.href = window.URL.createObjectURL(csvFile);
            link.style.display = "none";
            document.body.appendChild(link);
            link.click();
        }
    </script>
<?php include "footer.php"; ?>