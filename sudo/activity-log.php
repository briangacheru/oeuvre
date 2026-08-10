<?php include "head.php"; ?>
<?php requireCapability($currentAdminRole, 'view_activity_log'); ?>
    <title>iTasker | Activity Log</title>
<?php
include "navi.php";

// ── Rate limits: a live view of tbl_rate_limits, NOT a history - rows are
// actively purged by check_rate_limit() as they age out of their bucket's
// window (see shared-functions.php), so this only ever shows recent/
// current activity, grouped per action+identifier.
$rateLimits = [];
// Grouped, so there's no single row id - MAX(id) per bucket stands in for
// it (equivalent to ordering by most-recently-active bucket).
$result = mysqli_query($con, "
    SELECT action, identifier, COUNT(*) AS hits, MAX(created_at) AS most_recent, MAX(id) AS latest_id
    FROM tbl_rate_limits
    GROUP BY action, identifier
    ORDER BY latest_id DESC
    LIMIT 200
");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $rateLimits[] = $row;
    }
}

// ── Writer activity: the actual persistent log (login, logout, task
// viewed, task submitted/resubmitted). Capped to the most recent 500
// events so this page stays fast as the table grows.
$activity = [];
$result = mysqli_query($con, "
    SELECT id, actor_type, email, action, details, created_at
    FROM tbl_activity_log
    ORDER BY id DESC
    LIMIT 500
");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $activity[] = $row;
    }
}

$actionBadgeClass = [
    'login'        => 'badge-subtle-success',
    'logout'       => 'badge-subtle-secondary',
    'task_view'    => 'badge-subtle-info',
    'task_accept'  => 'badge-subtle-success',
    'task_decline' => 'badge-subtle-danger',
    'task_submit'  => 'badge-subtle-primary',
    'page_view'    => 'badge-subtle-warning',
];
$actionLabel = [
    'login'        => 'Login',
    'logout'       => 'Logout',
    'task_view'    => 'Task Viewed',
    'task_accept'  => 'Task Accepted',
    'task_decline' => 'Task Declined',
    'task_submit'  => 'Task Submitted',
    'page_view'    => 'Page Viewed',
];
?>

<div class="card shadow-none border mb-3">
    <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);">
    </div>
    <!--/.bg-holder-->

    <div class="card-header z-1">
        <div class="row flex-between-center gx-0">
            <div class="col-lg-auto d-flex align-items-center">
                <h4 class="mb-0 text-primary fw-bold">Activity <span class="text-info fw-medium">Log</span></h4>
            </div>
            <div class="col-lg-auto pt-3 pt-lg-0">
                <form class="row flex-lg-column flex-xxl-row gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
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
    unset($_SESSION['alert']);
}
?>

<div class="row g-3 mb-3">
    <div class="col">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Rate Limits</h5>
                <p class="text-600 fs-10 mb-0">Live view of <code>tbl_rate_limits</code> - who's currently hitting which
                    limit, and how many times within the active window. Rows age out automatically as each bucket's
                    window (10 minutes for most actions, 60 seconds for search) passes, so this is recent activity,
                    not a permanent history.</p>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables='{"order": []}'>
                        <thead class="bg-200">
                            <tr>
                                <th class="text-900 no-sort pe-1 align-middle" style="width:2rem;">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" id="rl-select-all">
                                    </div>
                                </th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Action</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Identifier</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Hits in Window</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Most Recent</th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            <?php foreach ($rateLimits as $rl): ?>
                                <tr>
                                    <td class="align-middle">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input rl-row-checkbox" type="checkbox"
                                                   data-action="<?php echo htmlspecialchars($rl['action'], ENT_QUOTES, 'UTF-8'); ?>"
                                                   data-identifier="<?php echo htmlspecialchars($rl['identifier'], ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                    </td>
                                    <td class="align-middle white-space-nowrap"><?php echo htmlspecialchars($rl['action'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="align-middle white-space-nowrap"><?php echo htmlspecialchars($rl['identifier'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="align-middle white-space-nowrap"><?php echo (int) $rl['hits']; ?></td>
                                    <td class="align-middle white-space-nowrap"><?php echo date('M j, g:i:s A', strtotime($rl['most_recent'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rate Limits: bulk actions bar (shown once rows are selected) -->
        <div class="card shadow-none border mb-3 d-none" id="rl-bulk-bar">
            <div class="card-body py-2">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle text-primary me-2 fs-4"></i>
                        <span id="rl-selected-count" class="fw-bold">0</span>
                        <span class="ms-1">rate limit record(s) selected</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-danger btn-sm" id="rl-delete-selected-btn">
                            <i class="fas fa-trash me-1"></i>Delete Selected
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm ms-2" id="rl-clear-selection-btn">
                            <i class="fas fa-times me-1"></i>Clear Selection
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rate Limits: bulk delete confirmation modal -->
        <div class="modal fade" id="rlBulkDeleteModal" tabindex="-1" aria-labelledby="rlBulkDeleteModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2 fs-4"></i>
                            <h5 class="modal-title mb-0" id="rlBulkDeleteModalLabel">Confirm Delete</h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning border-0 mb-3">
                            <strong>Warning:</strong> This action cannot be undone!
                        </div>
                        <p class="mb-3">
                            You are about to clear <strong id="rl-delete-count" class="text-danger">0</strong> rate limit record(s).
                        </p>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="rlConfirmCheckbox">
                            <label class="form-check-label" for="rlConfirmCheckbox">
                                I understand this will permanently clear the selected rate limit records
                            </label>
                        </div>
                        <div id="rl-items-to-delete" class="border rounded p-3 bg-secondary-subtle" style="max-height: 200px; overflow-y: auto;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-danger" id="rlConfirmDeleteBtn" disabled>
                            <i class="fas fa-trash me-1"></i>Delete <span id="rl-delete-count-btn">0</span> Record(s)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Writer Activity</h5>
                <p class="text-600 fs-10 mb-0">Most recent 500 events - logins, logouts, tasks viewed, and
                    submissions/resubmissions.</p>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables='{"order": []}'>
                        <thead class="bg-200">
                            <tr>
                                <th class="text-900 no-sort pe-1 align-middle" style="width:2rem;">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" id="act-select-all">
                                    </div>
                                </th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Time</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Email</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Action</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Details</th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            <?php foreach ($activity as $a): ?>
                                <tr>
                                    <td class="align-middle">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input act-row-checkbox" type="checkbox" value="<?php echo (int) $a['id']; ?>">
                                        </div>
                                    </td>
                                    <td class="align-middle white-space-nowrap"><?php echo date('M j, g:i:s A', strtotime($a['created_at'])); ?></td>
                                    <td class="align-middle white-space-nowrap act-email"><?php echo htmlspecialchars($a['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="align-middle white-space-nowrap act-action">
                                        <span class="badge rounded-pill <?php echo $actionBadgeClass[$a['action']] ?? 'badge-subtle-secondary'; ?>">
                                            <?php echo htmlspecialchars($actionLabel[$a['action']] ?? ucfirst($a['action']), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="align-middle white-space-nowrap"><?php echo htmlspecialchars($a['details'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Writer Activity: bulk actions bar (shown once rows are selected) -->
        <div class="card shadow-none border mb-3 d-none" id="act-bulk-bar">
            <div class="card-body py-2">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle text-primary me-2 fs-4"></i>
                        <span id="act-selected-count" class="fw-bold">0</span>
                        <span class="ms-1">activity log entries selected</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-danger btn-sm" id="act-delete-selected-btn">
                            <i class="fas fa-trash me-1"></i>Delete Selected
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm ms-2" id="act-clear-selection-btn">
                            <i class="fas fa-times me-1"></i>Clear Selection
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Writer Activity: bulk delete confirmation modal -->
        <div class="modal fade" id="actBulkDeleteModal" tabindex="-1" aria-labelledby="actBulkDeleteModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2 fs-4"></i>
                            <h5 class="modal-title mb-0" id="actBulkDeleteModalLabel">Confirm Delete</h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning border-0 mb-3">
                            <strong>Warning:</strong> This action cannot be undone!
                        </div>
                        <p class="mb-3">
                            You are about to permanently delete <strong id="act-delete-count" class="text-danger">0</strong> activity log entries.
                        </p>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="actConfirmCheckbox">
                            <label class="form-check-label" for="actConfirmCheckbox">
                                I understand this will permanently delete the selected activity log entries
                            </label>
                        </div>
                        <div id="act-items-to-delete" class="border rounded p-3 bg-secondary-subtle" style="max-height: 200px; overflow-y: auto;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-danger" id="actConfirmDeleteBtn" disabled>
                            <i class="fas fa-trash me-1"></i>Delete <span id="act-delete-count-btn">0</span> Entries
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function hiddenInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
    }

    function setupBulkSelect(cfg) {
        const selectAll = document.getElementById(cfg.selectAllId);
        const bar = document.getElementById(cfg.barId);
        const countEl = document.getElementById(cfg.countId);
        const deleteBtn = document.getElementById(cfg.deleteBtnId);
        const clearBtn = document.getElementById(cfg.clearBtnId);
        const rowCheckboxes = () => Array.from(document.querySelectorAll('.' + cfg.rowClass));

        function selected() {
            return rowCheckboxes().filter(cb => cb.checked);
        }

        function refresh() {
            const sel = selected();
            const all = rowCheckboxes();
            countEl.textContent = sel.length;
            bar.classList.toggle('d-none', sel.length === 0);
            if (selectAll) {
                selectAll.checked = all.length > 0 && sel.length === all.length;
                selectAll.indeterminate = sel.length > 0 && sel.length < all.length;
            }
        }

        document.addEventListener('change', function (e) {
            if (e.target.classList && e.target.classList.contains(cfg.rowClass)) refresh();
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                rowCheckboxes().forEach(cb => { cb.checked = selectAll.checked; });
                refresh();
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                rowCheckboxes().forEach(cb => { cb.checked = false; });
                if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
                refresh();
            });
        }

        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                cfg.onOpen(selected());
            });
        }

        refresh();
    }

    // ---- Rate Limits bulk delete ----
    // bootstrap.min.js loads later, in footer.php, so the Modal instance is
    // created lazily on first open rather than at script-parse time.
    let rlModal = null;
    const rlConfirmCheckbox = document.getElementById('rlConfirmCheckbox');
    const rlConfirmBtn = document.getElementById('rlConfirmDeleteBtn');
    let rlSelected = [];

    setupBulkSelect({
        selectAllId: 'rl-select-all',
        rowClass: 'rl-row-checkbox',
        barId: 'rl-bulk-bar',
        countId: 'rl-selected-count',
        deleteBtnId: 'rl-delete-selected-btn',
        clearBtnId: 'rl-clear-selection-btn',
        onOpen: function (sel) {
            rlSelected = sel;
            document.getElementById('rl-delete-count').textContent = sel.length;
            document.getElementById('rl-delete-count-btn').textContent = sel.length;
            const list = sel.slice(0, 10).map(cb =>
                `<div class="mb-1 small"><strong>${escapeHtml(cb.dataset.action)}</strong> &mdash; ${escapeHtml(cb.dataset.identifier)}</div>`
            ).join('') + (sel.length > 10 ? `<div class="text-muted small">... and ${sel.length - 10} more</div>` : '');
            document.getElementById('rl-items-to-delete').innerHTML = list;
            rlConfirmCheckbox.checked = false;
            rlConfirmBtn.disabled = true;
            if (!rlModal) rlModal = new bootstrap.Modal(document.getElementById('rlBulkDeleteModal'));
            rlModal.show();
        }
    });

    rlConfirmCheckbox.addEventListener('change', function () {
        rlConfirmBtn.disabled = !this.checked;
    });

    rlConfirmBtn.addEventListener('click', function () {
        if (!rlConfirmCheckbox.checked || rlSelected.length === 0) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete-rate-limits';
        form.appendChild(hiddenInput('csrf_token', GLOBAL_CSRF_TOKEN));
        rlSelected.forEach(cb => {
            form.appendChild(hiddenInput('rl_action[]', cb.dataset.action));
            form.appendChild(hiddenInput('rl_identifier[]', cb.dataset.identifier));
        });
        document.body.appendChild(form);
        form.submit();
    });

    // ---- Writer Activity bulk delete ----
    let actModal = null;
    const actConfirmCheckbox = document.getElementById('actConfirmCheckbox');
    const actConfirmBtn = document.getElementById('actConfirmDeleteBtn');
    let actSelected = [];

    setupBulkSelect({
        selectAllId: 'act-select-all',
        rowClass: 'act-row-checkbox',
        barId: 'act-bulk-bar',
        countId: 'act-selected-count',
        deleteBtnId: 'act-delete-selected-btn',
        clearBtnId: 'act-clear-selection-btn',
        onOpen: function (sel) {
            actSelected = sel;
            document.getElementById('act-delete-count').textContent = sel.length;
            document.getElementById('act-delete-count-btn').textContent = sel.length;
            const list = sel.slice(0, 10).map(cb => {
                const row = cb.closest('tr');
                const email = row.querySelector('.act-email');
                const action = row.querySelector('.act-action');
                return `<div class="mb-1 small"><strong>${escapeHtml(email ? email.textContent.trim() : '')}</strong> &mdash; ${escapeHtml(action ? action.textContent.trim() : '')}</div>`;
            }).join('') + (sel.length > 10 ? `<div class="text-muted small">... and ${sel.length - 10} more</div>` : '');
            document.getElementById('act-items-to-delete').innerHTML = list;
            actConfirmCheckbox.checked = false;
            actConfirmBtn.disabled = true;
            if (!actModal) actModal = new bootstrap.Modal(document.getElementById('actBulkDeleteModal'));
            actModal.show();
        }
    });

    actConfirmCheckbox.addEventListener('change', function () {
        actConfirmBtn.disabled = !this.checked;
    });

    actConfirmBtn.addEventListener('click', function () {
        if (!actConfirmCheckbox.checked || actSelected.length === 0) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete-activity-log';
        form.appendChild(hiddenInput('csrf_token', GLOBAL_CSRF_TOKEN));
        actSelected.forEach(cb => {
            form.appendChild(hiddenInput('activity_ids[]', cb.value));
        });
        document.body.appendChild(form);
        form.submit();
    });
})();
</script>

<?php include "footer.php"; ?>
