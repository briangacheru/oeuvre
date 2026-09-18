<?php
include "head.php";
requireCapability($currentAdminRole, 'operate_tasks');
?>
    <title>iTasker | Extension Requests</title>
<?php include "navi.php"; ?>

<?php
$pendingResult = mysqli_query($con, "SELECT r.*, t.topic, t.subject FROM tbl_task_extension_requests r
    LEFT JOIN tbltasks t ON t.id = r.task_id
    WHERE r.status = 'pending' ORDER BY r.created_at ASC");
$resolvedResult = mysqli_query($con, "SELECT r.*, t.topic, t.subject FROM tbl_task_extension_requests r
    LEFT JOIN tbltasks t ON t.id = r.task_id
    WHERE r.status != 'pending' ORDER BY r.resolved_at DESC LIMIT 100");
?>

<div class="card shadow-none border mb-3">
    <div class="card-header">
        <h4 class="mb-0"><i class="fas fa-calendar-plus me-2 text-warning"></i>Deadline Extension Requests</h4>
        <p class="text-secondary fs-9 mb-0">Writers ask for more time on a task here; approving updates the task's due date automatically and emails the writer.</p>
    </div>
    <div class="card-body pt-0">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pendingTab">Pending <span class="badge bg-warning text-dark ms-1"><?php echo mysqli_num_rows($pendingResult); ?></span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#resolvedTab">Resolved</button></li>
        </ul>
        <div class="tab-content">
            <!-- Pending - same table.data-table + hover-actions convention as
                 sudo/tasks-in-progress.php. See [[oeuvre-datatables-gotcha]]:
                 data-datatables must be real JSON ({"order": []}), not the
                 literal placeholder string, or DataTables silently falls back
                 to its own default sort instead of the query's ORDER BY. -->
            <div class="tab-pane fade show active" id="pendingTab">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables='{"order": []}'>
                        <thead class="bg-200">
                        <tr>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Task #</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Writer</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Current Due</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Requested Due</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Reason</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Requested</th>
                            <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                        </tr>
                        </thead>
                        <tbody class="list">
                        <?php while ($r = mysqli_fetch_assoc($pendingResult)): $encId = encode_task_id($r['task_id']); ?>
                            <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                                <td class="align-middle white-space-nowrap fw-semi-bold text-900">
                                    <a href="view-task?task_id=<?php echo $encId; ?>">#<?php echo (int) $r['task_id']; ?></a>
                                    <p class="fw-semi-bold mb-0 text-500 fs-11"><?php echo htmlspecialchars($r['topic'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                </td>
                                <td class="align-middle white-space-nowrap text-900"><?php echo htmlspecialchars($r['writer_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="align-middle white-space-nowrap"><?php echo date('d M Y, g:i A', strtotime($r['current_due_date'])); ?></td>
                                <td class="align-middle white-space-nowrap text-warning fw-semibold"><?php echo date('d M Y, g:i A', strtotime($r['requested_due_date'])); ?></td>
                                <td class="align-middle" style="max-width:260px;"><?php echo nl2br(htmlspecialchars($r['reason'], ENT_QUOTES, 'UTF-8')); ?></td>
                                <td class="align-middle white-space-nowrap"><?php echo date('d M Y, g:i A', strtotime($r['created_at'])); ?></td>
                                <td class="align-middle white-space-nowrap text-end position-relative">
                                    <div class="hover-actions bg-100">
                                        <button type="button" class="btn bg-success-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm" onclick="resolveExtension(<?php echo (int) $r['id']; ?>, 'approve')" data-bs-toggle="tooltip" data-bs-placement="top" title="Approve"><span class="fas fa-check"></span></button>
                                        <button type="button" class="btn bg-danger-subtle icon-item rounded-3 fs-11 icon-item-sm" onclick="resolveExtension(<?php echo (int) $r['id']; ?>, 'deny')" data-bs-toggle="tooltip" data-bs-placement="top" title="Deny"><span class="fas fa-times"></span></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="resolvedTab">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables='{"order": []}'>
                        <thead class="bg-200">
                        <tr>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Task #</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Writer</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Requested Due</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Status</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Resolved By</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Resolved</th>
                        </tr>
                        </thead>
                        <tbody class="list">
                        <?php while ($r = mysqli_fetch_assoc($resolvedResult)): $encId = encode_task_id($r['task_id']); ?>
                            <tr>
                                <td class="align-middle white-space-nowrap fw-semi-bold text-900"><a href="view-task?task_id=<?php echo $encId; ?>">#<?php echo (int) $r['task_id']; ?></a></td>
                                <td class="align-middle white-space-nowrap text-900"><?php echo htmlspecialchars($r['writer_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="align-middle white-space-nowrap"><?php echo date('d M Y, g:i A', strtotime($r['requested_due_date'])); ?></td>
                                <td class="align-middle white-space-nowrap"><span class="badge rounded-pill <?php echo $r['status'] == 'approved' ? 'badge-subtle-success' : 'badge-subtle-danger'; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                <td class="align-middle white-space-nowrap"><?php echo htmlspecialchars($r['resolved_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="align-middle white-space-nowrap"><?php echo $r['resolved_at'] ? date('d M Y, g:i A', strtotime($r['resolved_at'])) : ''; ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resolveExtensionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="resolveExtensionForm">
                <input type="hidden" name="request_id" id="resolveRequestId">
                <input type="hidden" name="decision" id="resolveDecision">
                <div class="modal-header">
                    <h5 class="modal-title" id="resolveExtensionModalTitle">Resolve Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label fs-9">Note to writer (optional)</label>
                        <textarea class="form-control" name="admin_response" rows="2" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="confirmResolveBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let resolveModal;
document.addEventListener('DOMContentLoaded', function () {
    resolveModal = new bootstrap.Modal(document.getElementById('resolveExtensionModal'));

    // The Resolved tab's DataTable initializes while its tab-pane is still
    // display:none (assets/js/theme.js auto-inits every [data-datatables] on
    // page load, before either tab is switched to), so jQuery DataTables
    // measures a zero-width container and its columns render squished until
    // something tells it to recalculate. Re-adjust once that tab is actually
    // shown, same fix DataTables' own docs recommend for tables inside
    // Bootstrap tabs.
    const resolvedTabBtn = document.querySelector('[data-bs-target="#resolvedTab"]');
    if (resolvedTabBtn && typeof jQuery !== 'undefined') {
        resolvedTabBtn.addEventListener('shown.bs.tab', function () {
            const $table = jQuery('#resolvedTab table.data-table');
            if (jQuery.fn.DataTable && $table.length && jQuery.fn.DataTable.isDataTable($table)) {
                $table.DataTable().columns.adjust().draw(false);
            }
        });
    }
});

function resolveExtension(requestId, decision) {
    document.getElementById('resolveRequestId').value = requestId;
    document.getElementById('resolveDecision').value = decision;
    document.getElementById('resolveExtensionModalTitle').textContent = decision === 'approve' ? 'Approve Extension Request' : 'Deny Extension Request';
    document.getElementById('confirmResolveBtn').className = 'btn ' + (decision === 'approve' ? 'btn-success' : 'btn-danger');
    resolveModal.show();
}

document.getElementById('resolveExtensionForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = document.getElementById('confirmResolveBtn');
    btn.disabled = true;
    const formData = new FormData(this);
    formData.append('csrf_token', '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>');

    fetch('resolve-task-extension', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                resolveModal.hide();
                setTimeout(() => location.reload(), 1000);
            } else {
                btn.disabled = false;
            }
        })
        .catch(() => { showToast('Something went wrong.', 'error'); btn.disabled = false; });
});
</script>

<?php include "footer.php"; ?>
