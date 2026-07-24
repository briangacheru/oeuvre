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

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Manage Admins</h5>
                <p class="text-600 fs-10 mb-0">Assign a role to each admin account. New self-registrations start as
                    <strong>pending</strong> (zero access) until approved here.</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 fs-10">
                        <thead class="bg-body-tertiary">
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Registered</th>
                                <th>Role</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <?php $isSelf = ($admin['email'] === $_SESSION['odmsaid']); ?>
                                <tr data-admin-id="<?php echo (int) $admin['id']; ?>">
                                    <td><?php echo htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($admin['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $admin['AdminRegdate'] ? date('jS M Y', strtotime($admin['AdminRegdate'])) : '—'; ?></td>
                                    <td>
                                        <?php if ($isSelf): ?>
                                            <span class="badge rounded-pill badge-subtle-primary"><?php echo htmlspecialchars(ucfirst($admin['role'] ?: 'pending'), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="fs-11 text-500 ms-1">(you)</span>
                                        <?php else: ?>
                                            <select class="form-select form-select-sm role-select" style="width:auto; display:inline-block;">
                                                <?php foreach (ADMIN_ROLES as $roleOption): ?>
                                                    <option value="<?php echo $roleOption; ?>" <?php echo ($admin['role'] === $roleOption) ? 'selected' : ''; ?>><?php echo ucfirst($roleOption); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$isSelf): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary save-role-btn">Save</button>
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
</script>

<?php include "footer.php"; ?>
