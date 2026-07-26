<?php include "head.php"; ?>
<?php requireCapability($currentAdminRole, 'manage_admins'); ?>
    <title>iTasker | Manage Admins</title>
<?php
include "navi.php";

// New self-registrations (see register.php) default to role='pending' and
// have zero capabilities until a superadmin approves them here - there was
// previously no in-app way to do this at all (see
// db-migrations/2026_07_24_add_admin_roles.sql).
$admins = [];
$result = mysqli_query($con, "SELECT id, username, email, role, AdminRegdate FROM tbladmin ORDER BY AdminRegdate DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $admins[] = $row;
    }
}
?>

<div class="card shadow-none border mb-3">
    <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);">
    </div>
    <!--/.bg-holder-->

    <div class="card-header z-1">
        <div class="row flex-between-center gx-0">
            <div class="col-lg-auto d-flex align-items-center">
                <h4 class="mb-0 text-primary fw-bold">Manage <span class="text-info fw-medium">Admins</span></h4>
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

<div class="row g-3 mb-3">
    <div class="col">
        <div class="card mb-3">
            <div class="card-header">
                <p class="text-600 fs-10 mb-0">Assign a role to each admin account. New self-registrations start as
                    <strong>pending</strong> (zero access) until approved here. Superadmin accounts are locked here -
                    change or remove one directly in the database if that's ever genuinely needed.</p>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables='{"order": []}'>
                        <thead class="bg-200">
                            <tr>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Username</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Email</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Registered</th>
                                <th class="text-900 no-sort pe-1 align-middle white-space-nowrap">Role</th>
                                <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            <?php foreach ($admins as $admin): ?>
                                <?php
                                $isSelf = ($admin['email'] === $_SESSION['odmsaid']);
                                $isSuperadmin = ($admin['role'] === 'superadmin');
                                $isLocked = $isSelf || $isSuperadmin; // neither editable nor deletable here
                                ?>
                                <tr class="hover-actions-trigger hover-bg-100<?php echo $isSuperadmin ? ' opacity-50' : ''; ?>" data-admin-id="<?php echo (int) $admin['id']; ?>">
                                    <td class="align-middle white-space-nowrap"><?php echo htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="align-middle white-space-nowrap"><?php echo htmlspecialchars($admin['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="align-middle white-space-nowrap"><?php echo $admin['AdminRegdate'] ? date('jS M Y', strtotime($admin['AdminRegdate'])) : '—'; ?></td>
                                    <td class="align-middle white-space-nowrap">
                                        <?php if ($isLocked): ?>
                                            <span class="badge rounded-pill badge-subtle-primary"><?php echo htmlspecialchars(ucfirst($admin['role'] ?: 'pending'), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if ($isSelf): ?>
                                                <span class="fs-11 text-500 ms-1">(you)</span>
                                            <?php elseif ($isSuperadmin): ?>
                                                <span class="fs-11 text-500 ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Superadmin accounts can't be edited or deleted from this page.">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <select class="form-select form-select-sm role-select" style="width:auto; display:inline-block;">
                                                <?php foreach (ADMIN_ROLES as $roleOption): ?>
                                                    <option value="<?php echo $roleOption; ?>" <?php echo ($admin['role'] === $roleOption) ? 'selected' : ''; ?>><?php echo ucfirst($roleOption); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="btn btn-sm btn-outline-primary save-role-btn ms-1">Save</button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle white-space-nowrap text-end position-relative">
                                        <?php if (!$isLocked): ?>
                                            <div class="hover-actions bg-100 top-50 end-0 translate-middle-y">
                                                <button type="button" class="btn btn-outline-danger bg-danger icon-item rounded-3 fs-11 icon-item-sm delete-admin-btn"
                                                        data-admin-username="<?php echo htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Delete Admin">
                                                    <span class="fas fa-trash"></span>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.save-role-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var row = btn.closest('tr');
        var adminId = row.getAttribute('data-admin-id');
        var role = row.querySelector('.role-select').value;
        btn.disabled = true;
        fetch('update-admin-role', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'admin_id=' + encodeURIComponent(adminId) + '&role=' + encodeURIComponent(role) + '&csrf_token=' + encodeURIComponent(GLOBAL_CSRF_TOKEN)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                if (data.success) {
                    showToast(data.message || 'Role updated.', 'success');
                } else {
                    showToast(data.message || 'Failed to update role.', 'danger');
                }
            })
            .catch(function () {
                btn.disabled = false;
                showToast('Failed to update role.', 'danger');
            });
    });
});

document.querySelectorAll('.delete-admin-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var row = btn.closest('tr');
        var adminId = row.getAttribute('data-admin-id');
        var username = btn.getAttribute('data-admin-username');
        if (!confirm('Delete admin account "' + username + '"? This cannot be undone.')) {
            return;
        }
        btn.disabled = true;
        fetch('delete-admin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'admin_id=' + encodeURIComponent(adminId) + '&csrf_token=' + encodeURIComponent(GLOBAL_CSRF_TOKEN)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showToast(data.message || 'Admin deleted.', 'success');
                    row.remove();
                } else {
                    btn.disabled = false;
                    showToast(data.message || 'Failed to delete admin.', 'danger');
                }
            })
            .catch(function () {
                btn.disabled = false;
                showToast('Failed to delete admin.', 'danger');
            });
    });
});
</script>

<?php include "footer.php"; ?>
