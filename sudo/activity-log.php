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
    'login'       => 'badge-subtle-success',
    'logout'      => 'badge-subtle-secondary',
    'task_view'   => 'badge-subtle-info',
    'task_submit' => 'badge-subtle-primary',
    'page_view'   => 'badge-subtle-warning',
];
$actionLabel = [
    'login'       => 'Login',
    'logout'      => 'Logout',
    'task_view'   => 'Task Viewed',
    'task_submit' => 'Task Submitted',
    'page_view'   => 'Page Viewed',
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
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Action</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Identifier</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Hits in Window</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Most Recent</th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            <?php foreach ($rateLimits as $rl): ?>
                                <tr>
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
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Time</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Email</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Action</th>
                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Details</th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            <?php foreach ($activity as $a): ?>
                                <tr>
                                    <td class="align-middle white-space-nowrap"><?php echo date('M j, g:i:s A', strtotime($a['created_at'])); ?></td>
                                    <td class="align-middle white-space-nowrap"><?php echo htmlspecialchars($a['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="align-middle white-space-nowrap">
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
    </div>
</div>

<?php include "footer.php"; ?>
