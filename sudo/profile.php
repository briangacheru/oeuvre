<?php include "head.php";?>
    <title>iTasker | My Profile</title>
<?php include "navi.php";?>

<?php
$allCompleted = "";
$query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed'";
$result = mysqli_query($con, $query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $count = $row['taskCount'];
    if ($count > 0) {
        $allCompleted = $count; // Set the count to output variable
    } else {
        $allCompleted = "0"; // Set "0" if count is 0
    }
} else {
    $allCompleted = "No data"; // Set "No Data" if query fails
}
?>

<?php
$allProgress = "";
$query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress'";
$result = mysqli_query($con, $query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $count = $row['taskCount'];
    if ($count > 0) {
        $allProgress = $count; // Set the count to output variable
    } else {
        $allProgress = "0"; // Set "0" if count is 0
    }
} else {
    $allProgress = "No data"; // Set "No Data" if query fails
}
?>

<?php
$totalPaidFormatted = "No data"; // Default message if the query fails
$totalPaidRaw = 0; // Raw total for JavaScript
$query = mysqli_query($con, "SELECT SUM(CPP*pages) AS total FROM tbltasks WHERE is_deleted = 0 AND is_paid = 1");
if ($query) {
    $row = mysqli_fetch_array($query);
    if ($row && $row['total'] !== null) {
        $totalPaidRaw = $row['total']; // Keep the raw total
        $totalPaidFormatted = 'Ksh. ' . number_format($row['total'], 2);
    } else {
        $totalPaidFormatted = 'Ksh. 0.00';
    }
} else {
    $totalPaidFormatted = "Error: " . mysqli_error($con);
}
?>

    <style>
        /* Scoped polish for the profile page. Prefixed .pf- so nothing collides with the Falcon theme. */
        .pf-page .pf-name { letter-spacing: -0.01em; }
        .pf-meta { display: flex; flex-wrap: wrap; gap: 0.35rem 1.5rem; margin: 0.5rem 0 1rem; }
        .pf-meta-item { display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; }
        .pf-meta-item .fas { width: 1rem; text-align: center; }

        .pf-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
        @media (min-width: 992px) { .pf-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .pf-stat {
            display: flex; align-items: center; gap: 0.85rem;
            border: 1px solid var(--bs-border-color);
            border-radius: 0.85rem; padding: 0.9rem 1rem;
            background: var(--bs-body-bg);
            transition: box-shadow 0.16s ease, transform 0.16s ease, border-color 0.16s ease;
        }
        .pf-stat:hover { box-shadow: 0 0.5rem 1.25rem rgba(0,0,0,0.06); transform: translateY(-2px); }
        .pf-stat-icon {
            flex: 0 0 auto; width: 2.75rem; height: 2.75rem;
            border-radius: 0.7rem; display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem;
        }
        .pf-stat-value { font-size: 1.4rem; font-weight: 700; line-height: 1.05; }
        .pf-stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.75; }

        .pf-section-title { font-size: 0.95rem; font-weight: 600; }
        .pf-intro p { line-height: 1.7; }

        .pf-table { --bs-table-bg: transparent; }
        .pf-table thead th {
            font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;
            font-weight: 600; border-bottom-width: 1px;
        }
        .pf-table td { vertical-align: middle; }
        .pf-device-chip {
            flex: 0 0 auto; width: 2.4rem; height: 2.4rem; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center; font-size: 0.95rem;
        }
        @media (max-width: 575.98px) {
            .pf-stat { padding: 0.75rem 0.85rem; }
            .pf-stat-value { font-size: 1.2rem; }
        }
    </style>

<?php
$aid=$_SESSION['odmsaid'];
$sql="SELECT * from  tbladmin where email=:aid";
$query = $dbh -> prepare($sql);
$query->bindParam(':aid',$aid,PDO::PARAM_STR);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
    foreach($results as $row)
    {
        ?>
        <div class="pf-page">
        <div class="card mb-3">
            <div class="card-header position-relative min-vh-25 mb-7">
                <?php if ($row->coverImage == "1.jpg") { ?>
                <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url(../profileimages/1.jpg);">
                    <?php } else { ?>
                    <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url('../profileimages/<?php echo $row->coverImage; ?>');">
                        <?php } ?>
                    </div>
                    <!--/.bg-holder-->

                    <div class="avatar avatar-5xl avatar-profile">
                        <?php
                        if($row->Photo=="avatar.png")
                        {
                            ?>
                            <img class="rounded-circle img-thumbnail shadow-sm" src="../assets/img/team/avatar.png" width="200" alt="" />
                            <?php
                        } else {
                            ?>
                            <img class="rounded-circle img-thumbnail shadow-sm" src="../profileimages/<?php  echo $row->Photo;?>" width="200" alt="">
                            <?php
                        } ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-1">
                                <h4 class="pf-name mb-0 text-info me-2"><?php echo $row->FirstName; ?> <?php echo $row->LastName; ?></h4>
                                <?php if ($row->superadmin == 1) { ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle" data-bs-toggle="tooltip" data-bs-placement="top" title="Verified account">
                        <span class="fas fa-check-circle me-1"></span>Verified
                      </span>
                                <?php } else { ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" data-bs-toggle="tooltip" data-bs-placement="top" title="Account not verified">
                        <span class="fas fa-times-circle me-1"></span>Unverified
                      </span>
                                <?php } ?>
                            </div>

                            <div class="pf-meta text-700">
                    <span class="pf-meta-item">
                      <span class="fas fa-envelope text-info"></span>
                      <a class="text-decoration-none text-primary" href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a>
                    </span>
                                <?php if (!empty($row->MobileNumber)) { ?>
                                    <span class="pf-meta-item">
                      <span class="fas fa-phone text-info"></span>
                      <span class="text-primary"><?php echo $row->MobileNumber; ?></span>
                    </span>
                                <?php } ?>
                            </div>

                            <a class="btn btn-primary btn-sm px-3" type="button" href="settings">
                                <span class="fas fa-pen me-1"></span>Edit profile
                            </a>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-6 col-lg-3">
                            <div class="pf-stat h-100">
                                <span class="pf-stat-icon bg-success-subtle text-success"><span class="fas fa-check-circle"></span></span>
                                <div>
                                    <div class="pf-stat-value text-primary"><?php echo $allCompleted; ?></div>
                                    <div class="pf-stat-label text-700">Completed tasks</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="pf-stat h-100">
                                <span class="pf-stat-icon bg-warning-subtle text-warning"><span class="fas fa-spinner"></span></span>
                                <div>
                                    <div class="pf-stat-value text-primary"><?php echo $allProgress; ?></div>
                                    <div class="pf-stat-label text-700">Tasks pending</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="pf-stat h-100">
                                <span class="pf-stat-icon bg-info-subtle text-info"><span class="fas fa-wallet"></span></span>
                                <div>
                                    <div class="pf-stat-value text-primary" style="font-size:1.1rem;"><?php echo $totalPaidFormatted; ?></div>
                                    <div class="pf-stat-label text-700">Total disbursed</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="pf-stat h-100">
                                <span class="pf-stat-icon bg-primary-subtle text-primary"><span class="fas fa-calendar-day"></span></span>
                                <div>
                                    <div class="pf-stat-value text-primary" style="font-size:1rem;">
                                        <?php
                                        $adminRegDate = $row->AdminRegdate;
                                        echo date("jS M, Y", strtotime($adminRegDate));
                                        ?>
                                    </div>
                                    <div class="pf-stat-label text-700">Member since</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $currentSid = session_id();
            $sessQ = $dbh->prepare("SELECT * FROM tblsessions WHERE admin_email = :email ORDER BY last_activity DESC");
            $sessQ->bindParam(':email', $aid, PDO::PARAM_STR);
            $sessQ->execute();
            $sessions = $sessQ->fetchAll(PDO::FETCH_OBJ);
            ?>
            <div class="row g-0">
                <div class="col-lg-12">
                    <div class="card mb-3">
                        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center">
                                <span class="fas fa-shield-alt fs-9 me-2 text-info"></span>
                                <h5 class="pf-section-title mb-0 text-info">Logged-in devices</h5>
                                <span class="badge bg-info-subtle text-info ms-2"><?php echo count($sessions); ?></span>
                            </div>
                            <?php if (count($sessions) > 1) { ?>
                                <form method="post" action="logout_device.php" class="mb-0">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <button type="submit" name="logout_all_others" value="1" class="btn btn-outline-danger btn-sm">
                                        <span class="fas fa-sign-out-alt me-1"></span>Log out all other devices
                                    </button>
                                </form>
                            <?php } ?>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table pf-table table-hover align-middle mb-0">
                                    <thead class="text-700">
                                    <tr>
                                        <th class="ps-3">Device</th>
                                        <th>IP address</th>
                                        <th>Signed in</th>
                                        <th>Last active</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($sessions)) { ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-700 py-4">
                                                <span class="fas fa-info-circle me-1"></span>
                                                No tracked sessions yet. Log out and back in to register this device.
                                            </td>
                                        </tr>
                                    <?php } else {
                                        foreach ($sessions as $s) {
                                            $isCurrent = ($s->session_id === $currentSid);
                                            $isMobile  = (stripos($s->device_label, 'Mobile') !== false);
                                            $icon = $isMobile ? 'mobile-alt' : 'desktop';
                                            ?>
                                            <tr<?php echo $isCurrent ? ' class="table-active"' : ''; ?>>
                                                <td class="ps-3">
                                                    <div class="d-flex align-items-center">
                                                        <span class="pf-device-chip bg-info-subtle text-info me-2"><span class="fas fa-<?php echo $icon; ?>"></span></span>
                                                        <div>
                                                            <div class="text-primary fw-semibold"><?php echo htmlspecialchars($s->device_label); ?></div>
                                                            <?php if ($isCurrent) { ?>
                                                                <span class="badge bg-success-subtle text-success border border-success-subtle">This device</span>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-700"><?php echo htmlspecialchars($s->ip_address); ?></div>
                                                    <?php if (!empty($s->location)) { ?>
                                                        <div class="fs-11 text-500"><span class="fas fa-map-marker-alt me-1"></span><?php echo htmlspecialchars($s->location); ?></div>
                                                    <?php } ?>
                                                </td>
                                                <td class="text-700"><?php echo date("jS M Y, g:i A", strtotime($s->login_time)); ?></td>
                                                <td class="text-700"><?php echo date("jS M Y, g:i A", strtotime($s->last_activity)); ?></td>
                                                <td class="text-end pe-3">
                                                    <form method="post" action="logout_device.php" class="mb-0 d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="session_db_id" value="<?php echo (int)$s->id; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <span class="fas fa-<?php echo $isCurrent ? 'sign-out-alt' : 'times'; ?> me-1"></span><?php echo $isCurrent ? 'Log out' : 'Remove'; ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php } } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div><!--/.pf-page-->
        <?php
    }
} ?>
<?php
include "footer.php";
?>