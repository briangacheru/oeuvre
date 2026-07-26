<?php include "head.php";?>
<?php csrf_verify_or_redirect(); ?>
<?php requireCapability($currentAdminRole, 'manage_settings'); ?>
    <title>Version Update |iTasker</title>
<?php include "navi.php";?><div id="alert-container"></div>
<?php
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_type'])) {
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $versionData = updateVersionNumber($_POST['update_type'], $description);
        $versionString = "v{$versionData['major']}.{$versionData['minor']}.{$versionData['patch']}";
        $message = "Version updated to $versionString";
    }
}

// Get current version data
$versionData = getVersionData();
$currentVersion = "v{$versionData['major']}.{$versionData['minor']}.{$versionData['patch']}";
$description = $versionData['description'] ?? '';
$formattedDate = date('F j, Y g:i A', strtotime($versionData['created_at']));

// Full history - tbl_changelog is append-only, so unlike the old
// version.json file (a single mutable record), every past bump is still
// here to show.
$history = get_changelog_history($con, 100);
?>
    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Update <span class="text-info fw-medium"> Version</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0">
                    <form class="row flex-lg-column flex-xxl-row gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
                        <div class="col-auto">
                        </div>
                        <div class="col-md-auto position-relative">
                            <h6 class="mb-1 text-primary"></h6>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body bg-body-tertiary">
            <div class="tab-content">
                <div class="tab-pane preview-tab-pane active" >
                        <div class="card mb-3">
                            <div class="card-header bg-body-tertiary">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-success"><?php echo $message; ?></div>
                                <?php endif; ?>

                                <p class="mb-3">Current version: <strong><?php echo $currentVersion; ?></strong></p>
                                <p class="mb-3">Last updated: <strong><?php echo $formattedDate; ?></strong></p>
                                <p class="mb-3">Description: <strong><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></strong></p>

                                <form method="post">
<?= csrf_field() ?>
                                    <div class="form-group mb-3">
                                        <label>Update Type:</label>
                                        <select name="update_type" class="form-control">
                                            <option value="patch">Patch (v<?php echo $versionData['major']; ?>.<?php echo $versionData['minor']; ?>.x) - Bug fixes</option>
                                            <option value="minor">Minor (v<?php echo $versionData['major']; ?>.x.0) - New features</option>
                                            <option value="major">Major (vx.0.0) - Significant changes</option>
                                        </select>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label>Description:</label>
                                        <textarea name="description" class="form-control" rows="3" placeholder="Describe what changed in this version"></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Update Version</button>
                                </form>
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
                <h5 class="mb-0">Version History</h5>
                <p class="text-600 fs-10 mb-0">Every past version bump, most recent first.</p>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables="data-datatables">
                        <thead class="bg-200">
                            <tr>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Version</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Date</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Updated By</th>
                                <th class="text-900 no-sort pe-1 align-middle">Description</th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            <?php foreach ($history as $entry): ?>
                                <tr>
                                    <td class="align-middle white-space-nowrap">v<?php echo (int) $entry['major']; ?>.<?php echo (int) $entry['minor']; ?>.<?php echo (int) $entry['patch']; ?></td>
                                    <td class="align-middle white-space-nowrap"><?php echo date('M j, Y g:i A', strtotime($entry['created_at'])); ?></td>
                                    <td class="align-middle white-space-nowrap"><?php echo htmlspecialchars($entry['created_by'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="align-middle"><?php echo htmlspecialchars($entry['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($history)): ?>
                                <tr><td colspan="4" class="text-center text-500">No version history yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
include "footer.php";
?>
