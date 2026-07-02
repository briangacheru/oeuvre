<?php include "head.php";?>
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
$lastUpdated = $versionData['lastUpdated'];
$description = isset($versionData['description']) ? $versionData['description'] : '';

// Format the date in a more readable format
$formattedDate = date('F j, Y', strtotime($lastUpdated));
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
                                <p class="mb-3">Description: <strong><?php echo htmlspecialchars($description); ?></strong></p>

                                <form method="post">
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
                                        <textarea name="description" class="form-control" rows="3" placeholder="Describe what changed in this version"><?php echo htmlspecialchars($description); ?></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Update Version</button>
                                </form>
                            </div>
                        </div>
                </div>
            </div>
        </div>
    </div>
<?php
include "footer.php";
?>