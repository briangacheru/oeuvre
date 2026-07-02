<?php include "head.php";?>
    <title>iTasker | Invoice Logs</title>
<?php include "navi.php";

// ── Handle delete ─────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $delId = (int) base64_decode($_GET['delete']);
    if ($delId > 0) {
        mysqli_query($con, "DELETE FROM tbl_invoice_logs WHERE id = $delId");
        $_SESSION['alert'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-1"></i> Invoice log deleted.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
    }
    header('Location: invoice-logs');
    exit;
}
?>

    <!-- Page header -->
    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);"></div>
        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Invoice <span class="text-info fw-medium">Email Logs</span></h4>
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

// ── Fetch all logs ────────────────────────────────────────────────────────
$logs = mysqli_query($con,
    "SELECT * FROM tbl_invoice_logs ORDER BY sent_at DESC"
);
$totalLogs = mysqli_num_rows($logs);
?>

    <!-- Stats strip -->
    <div class="row g-3 mb-3">
        <div class="col-sm-4">
            <div class="card shadow-none border h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:46px;height:46px;background:rgba(0,115,230,.1);">
                        <span class="fas fa-paper-plane text-primary fs-5"></span>
                    </div>
                    <div>
                        <p class="mb-0 text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">Total Emails Sent</p>
                        <h4 class="mb-0 fw-bold text-primary"><?php echo $totalLogs; ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <?php
            $uniqueWriters = mysqli_fetch_assoc(mysqli_query($con,
                "SELECT COUNT(DISTINCT writer_name) AS cnt FROM tbl_invoice_logs"
            ));
            ?>
            <div class="card shadow-none border h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:46px;height:46px;background:rgba(0,210,210,.1);">
                        <span class="fas fa-users text-info fs-5"></span>
                    </div>
                    <div>
                        <p class="mb-0 text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">Writers Invoiced</p>
                        <h4 class="mb-0 fw-bold text-info"><?php echo $uniqueWriters['cnt']; ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <?php
            $totalPaid = mysqli_fetch_assoc(mysqli_query($con,
                "SELECT SUM(amount_payable) AS total FROM tbl_invoice_logs"
            ));
            ?>
            <div class="card shadow-none border h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:46px;height:46px;background:rgba(0,200,83,.1);">
                        <span class="fas fa-wallet text-success fs-5"></span>
                    </div>
                    <div>
                        <p class="mb-0 text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">Total Amount Invoiced</p>
                        <h4 class="mb-0 fw-bold text-success">Ksh <?php echo number_format((float)($totalPaid['total'] ?? 0), 2); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs table -->
    <div class="row g-3 mb-3">
        <div class="col">
            <div class="card mb-3">
                <div class="card-body p-0">
                    <div class="card shadow-none">
                        <div class="card-header">
                            <div class="row flex-between-center">
                                <div class="col-auto align-self-center">
                                    <h5 class="mb-0">Sent Invoice Records</h5>
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-0 pt-0">
                            <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables="data-datatables">
                                <thead class="bg-200">
                                <tr>
                                    <th class="text-900 sort pe-1 align-middle white-space-nowrap">#</th>
                                    <th class="text-900 sort pe-1 align-middle white-space-nowrap">Writer</th>
                                    <th class="text-900 sort pe-1 align-middle white-space-nowrap text-end">Tasks Total</th>
                                    <th class="text-900 sort pe-1 align-middle white-space-nowrap text-end">Bonuses</th>
                                    <th class="text-900 sort pe-1 align-middle white-space-nowrap text-end">Overdrafts</th>
                                    <th class="text-900 sort pe-1 align-middle white-space-nowrap text-end">Amount Payable</th>
                                    <th class="text-900 sort pe-1 align-middle white-space-nowrap text-center">Sent At</th>
                                    <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                                </tr>
                                </thead>
                                <tbody class="list">
                                <?php
                                // Re-run query for table rows (num_rows already consumed pointer)
                                $logsTable = mysqli_query($con,
                                    "SELECT * FROM tbl_invoice_logs ORDER BY sent_at DESC"
                                );
                                $cnt = 1;
                                while ($row = mysqli_fetch_assoc($logsTable)):
                                    $encodedId = base64_encode($row['id']);
                                    $taskCount     = (int) $row['task_count'];
                                    $bonusCount    = (int) $row['bonus_count'];
                                    $overdraftCount = (int) $row['overdraft_count'];
                                    ?>
                                    <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100 log-row"
                                        style="cursor: pointer;"
                                        data-bs-toggle="modal" data-bs-target="#log-detail-modal"
                                        data-log-id="<?php echo $row['id']; ?>"
                                        data-log='<?php echo htmlspecialchars(json_encode([
                                            "writer"         => $row["writer_name"],
                                            "email"          => $row["writer_email"],
                                            "sent_at"        => date("jS M Y, h:i A", strtotime($row["sent_at"] . ' UTC')),
                                            "tasks_total"    => number_format((float)$row["tasks_total"], 2),
                                            "bonus_total"    => number_format((float)$row["bonus_total"], 2),
                                            "overdraft_total"=> number_format((float)$row["overdraft_total"], 2),
                                            "amount_payable" => number_format((float)$row["amount_payable"], 2),
                                            "task_count"     => $taskCount,
                                            "bonus_count"    => $bonusCount,
                                            "overdraft_count"=> $overdraftCount,
                                            "notes"          => $row["notes"] ?? "",
                                        ]), ENT_QUOTES, "UTF-8"); ?>'
                                        onclick="handleLogRowClick(event, this);">

                                        <td class="align-middle white-space-nowrap text-900"><?php echo $cnt; ?></td>
                                        <td class="align-middle white-space-nowrap fw-semi-bold text-900"><?php echo htmlspecialchars($row['writer_name']); ?></td>
                                        <td class="align-middle white-space-nowrap text-end text-900">
                                            Ksh <?php echo number_format((float)$row['tasks_total'], 2); ?>
                                            <span class="ms-1 badge badge-subtle-primary rounded-pill"><?php echo $taskCount; ?></span>
                                        </td>
                                        <td class="align-middle white-space-nowrap text-end">
                                            <?php if ($bonusCount > 0): ?>
                                                <span class="text-info fw-semibold">Ksh <?php echo number_format((float)$row['bonus_total'], 2); ?></span>
                                                <span class="ms-1 badge badge-subtle-info rounded-pill"><?php echo $bonusCount; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle white-space-nowrap text-end">
                                            <?php if ($overdraftCount > 0): ?>
                                                <span class="text-danger fw-semibold">Ksh <?php echo number_format((float)$row['overdraft_total'], 2); ?></span>
                                                <span class="ms-1 badge badge-subtle-danger rounded-pill"><?php echo $overdraftCount; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle white-space-nowrap text-end fw-bold text-success">
                                            Ksh <?php echo number_format((float)$row['amount_payable'], 2); ?>
                                        </td>
                                        <td class="align-middle text-center white-space-nowrap text-900">
                                            <?php echo date("jS M, Y h:i A", strtotime($row['sent_at'] . ' UTC')); ?>
                                        </td>
                                        <td class="align-middle white-space-nowrap text-end position-relative">
                                            <div class="hover-actions bg-100">
                                                <!-- View details -->
                                                <a class="btn bg-primary-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"
                                                   href="#" data-bs-toggle="modal" data-bs-target="#log-detail-modal"
                                                   data-bs-placement="top" title="View details"
                                                   data-log-id="<?php echo $row['id']; ?>"
                                                   data-log='<?php echo htmlspecialchars(json_encode([
                                                       "writer"         => $row["writer_name"],
                                                       "email"          => $row["writer_email"],
                                                       "sent_at"        => date("jS M Y, h:i A", strtotime($row["sent_at"] . ' UTC')),
                                                       "tasks_total"    => number_format((float)$row["tasks_total"], 2),
                                                       "bonus_total"    => number_format((float)$row["bonus_total"], 2),
                                                       "overdraft_total"=> number_format((float)$row["overdraft_total"], 2),
                                                       "amount_payable" => number_format((float)$row["amount_payable"], 2),
                                                       "task_count"     => $taskCount,
                                                       "bonus_count"    => $bonusCount,
                                                       "overdraft_count"=> $overdraftCount,
                                                       "notes"          => $row["notes"] ?? "",
                                                   ]), ENT_QUOTES, "UTF-8"); ?>'
                                                   onclick="openLogDetail(this); return false;">
                                                    <span class="fas fa-eye"></span>
                                                </a>
                                                <!-- Delete -->
                                                <a class="btn bg-danger-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"
                                                   href="invoice-logs?delete=<?php echo $encodedId; ?>"
                                                   data-bs-toggle="tooltip" data-bs-placement="top" title="Delete log"
                                                   onclick="return confirm('Delete this invoice log entry?');">
                                                    <span class="fas fa-trash"></span>
                                                </a>
                                            </div>
                                            <div class="dropdown font-sans-serif btn-reveal-trigger">
                                                <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none"
                                                        type="button" data-bs-toggle="dropdown" data-boundary="viewport"
                                                        aria-haspopup="true" aria-expanded="false">
                                                    <span class="fas fa-chevron-left fs-11"></span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $cnt++; endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Detail Modal -->
    <div class="modal fade" id="log-detail-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable mt-6" role="document">
            <div class="modal-content border-0">
                <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1">
                        <h4 class="mb-0 text-white">Invoice Log Details</h4>
                        <p class="mb-0 fs-10 text-white opacity-75" id="log-sent-at"></p>
                    </div>
                    <button class="btn-close position-absolute top-0 end-0 mt-2 me-2 btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 px-5">

                    <!-- Writer info -->
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">Writer</p>
                            <p class="mb-0 fw-bold text-900" id="log-writer"></p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-0 text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">Sent To</p>
                            <p class="mb-0 text-700" id="log-email"></p>
                        </div>
                    </div>
                    <hr class="my-3">

                    <!-- Summary tiles -->
                    <div class="row g-2 mb-4">
                        <div class="col-6 col-sm-3">
                            <div class="p-3 rounded-3 text-center h-100" style="background:rgba(0,115,230,.07);border:1px solid rgba(0,115,230,.15);">
                                <div class="text-muted mb-1" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;">Tasks Total</div>
                                <div class="fw-bold text-primary fs-6" id="log-tasks-total"></div>
                                <div class="text-muted" style="font-size:.68rem;" id="log-task-count"></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-3 rounded-3 text-center h-100" style="background:rgba(0,210,210,.07);border:1px solid rgba(0,210,210,.18);">
                                <div class="text-muted mb-1" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;">Bonuses</div>
                                <div class="fw-bold text-info fs-6" id="log-bonus-total"></div>
                                <div class="text-muted" style="font-size:.68rem;" id="log-bonus-count"></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-3 rounded-3 text-center h-100" style="background:rgba(229,83,83,.07);border:1px solid rgba(229,83,83,.18);">
                                <div class="text-muted mb-1" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;">Overdrafts</div>
                                <div class="fw-bold text-danger fs-6" id="log-overdraft-total"></div>
                                <div class="text-muted" style="font-size:.68rem;" id="log-overdraft-count"></div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-3 rounded-3 text-center h-100" style="background:rgba(0,200,83,.07);border:1px solid rgba(0,200,83,.18);">
                                <div class="text-muted mb-1" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;">Amount Payable</div>
                                <div class="fw-bold text-success fs-5" id="log-amount-payable"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Task items table -->
                    <h6 class="fw-bold text-700 mb-2">
                        <span class="fas fa-file-alt me-1 text-primary"></span> Included Tasks
                    </h6>
                    <div id="log-tasks-loading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        <span class="text-muted small">Loading tasks…</span>
                    </div>
                    <div id="log-tasks-empty" class="text-muted small fst-italic py-2" style="display:none;">
                        No task items recorded for this invoice.
                    </div>
                    <div id="log-tasks-table-wrap" style="display:none;">
                        <table class="table table-sm fs-10 mb-0" id="log-tasks-table">
                            <thead class="bg-200">
                            <tr>
                                <th class="text-900 align-middle white-space-nowrap">Task ID</th>
                                <th class="text-900 align-middle">Topic</th>
                                <th class="text-900 align-middle text-center">Pages</th>
                                <th class="text-900 align-middle text-end">CPP</th>
                                <th class="text-900 align-middle text-end">Amount</th>
                            </tr>
                            </thead>
                            <tbody id="log-tasks-tbody"></tbody>
                        </table>
                    </div>

                    <!-- Notes -->
                    <div id="log-notes-row" class="mt-3" style="display:none;">
                        <hr>
                        <p class="mb-0 text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">Notes</p>
                        <p class="text-muted small mt-1 mb-0" id="log-notes"></p>
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

        // Populate log detail modal — now accepts the anchor element
        function openLogDetail(el) {
            var data  = JSON.parse(el.getAttribute('data-log'));
            var logId = el.getAttribute('data-log-id');

            // Summary tiles
            document.getElementById('log-writer').textContent          = data.writer;
            document.getElementById('log-email').textContent           = data.email;
            document.getElementById('log-sent-at').textContent         = 'Sent: ' + data.sent_at;
            document.getElementById('log-tasks-total').textContent     = 'Ksh ' + data.tasks_total;
            document.getElementById('log-task-count').textContent      = data.task_count + ' task' + (data.task_count != 1 ? 's' : '');
            document.getElementById('log-bonus-total').textContent     = data.bonus_count > 0 ? 'Ksh ' + data.bonus_total : '—';
            document.getElementById('log-bonus-count').textContent     = data.bonus_count > 0 ? data.bonus_count + ' bonus' + (data.bonus_count != 1 ? 'es' : '') : '';
            document.getElementById('log-overdraft-total').textContent = data.overdraft_count > 0 ? '− Ksh ' + data.overdraft_total : '—';
            document.getElementById('log-overdraft-count').textContent = data.overdraft_count > 0 ? data.overdraft_count + ' deduction' + (data.overdraft_count != 1 ? 's' : '') : '';
            document.getElementById('log-amount-payable').textContent  = 'Ksh ' + data.amount_payable;

            // Notes
            var notesRow = document.getElementById('log-notes-row');
            if (data.notes && data.notes.trim() !== '') {
                document.getElementById('log-notes').textContent = data.notes;
                notesRow.style.display = 'block';
            } else {
                notesRow.style.display = 'none';
            }

            // Reset task table state
            var loading  = document.getElementById('log-tasks-loading');
            var empty    = document.getElementById('log-tasks-empty');
            var tableWrap = document.getElementById('log-tasks-table-wrap');
            var tbody    = document.getElementById('log-tasks-tbody');
            loading.style.display   = 'block';
            empty.style.display     = 'none';
            tableWrap.style.display = 'none';
            tbody.innerHTML         = '';

            // Fetch task items for this log
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
                            '<td class="align-middle text-900" style="max-width:220px;white-space:normal;">' + escHtml(item.topic) + '</td>' +
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
        function handleLogRowClick(event, rowEl) {
            // Don't trigger modal if user clicked the dropdown, delete link, or eye icon
            if (event.target.closest('.dropdown') ||
                event.target.closest('.hover-actions a') ||
                event.target.closest('button') ||
                event.target.closest('a')) {
                return;
            }
            openLogDetail(rowEl);
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
    </script>
<?php include "footer.php"; ?>