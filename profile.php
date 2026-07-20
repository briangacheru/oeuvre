<?php
include "head.php";
include "writer-performance-functions.php";
?>

<?php
$allCompleted = "";
$query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND email ='$aid'";
$result = mysqli_query($con, $query);
if ($result) {
    $rowProfile = mysqli_fetch_assoc($result);
    $count = $rowProfile['taskCount'];
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
$query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND email ='$aid'";
$result = mysqli_query($con, $query);
if ($result) {
    $rowProfile = mysqli_fetch_assoc($result);
    $count = $rowProfile['taskCount'];
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
$allInRevision = "";
$query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Revision' AND email ='$aid'";
$result = mysqli_query($con, $query);
if ($result) {
    $rowProfile = mysqli_fetch_assoc($result);
    $count = $rowProfile['taskCount'];
    $allInRevision = $count > 0 ? $count : "0";
} else {
    $allInRevision = "No data";
}
?>

<?php
$totalPaidFormatted = "No data"; // Default message if the query fails
$totalPaidRaw = 0; // Raw total for JavaScript
$query = mysqli_query($con, "SELECT SUM(CPP*pages) AS total FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND email ='$aid'");
if ($query) {
    $rowProfile = mysqli_fetch_array($query);
    if ($rowProfile && $rowProfile['total'] !== null) {
        $totalPaidRaw = $rowProfile['total']; // Keep the raw total
        $totalPaidFormatted = 'Ksh. ' . number_format($rowProfile['total'], 2);
    } else {
        $totalPaidFormatted = 'Ksh. 0.00';
    }
} else {
    $totalPaidFormatted = "Error: " . safe_db_error(mysqli_error($con));
}
?>

<?php
$totalUnpaidFormatted = "Ksh. 0.00";
$query = mysqli_query($con, "SELECT SUM(CPP*pages) AS total FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND is_paid = 0 AND email ='$aid'");
if ($query) {
    $rowProfile = mysqli_fetch_array($query);
    if ($rowProfile && $rowProfile['total'] !== null) {
        $totalUnpaidFormatted = 'Ksh. ' . number_format($rowProfile['total'], 2);
    }
} else {
    $totalUnpaidFormatted = "Error: " . safe_db_error(mysqli_error($con));
}
?>

<?php
$sql = "SELECT * from tblwriters where email=:aid";
$query = $dbh->prepare($sql);
$query->bindParam(':aid', $aid, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);
if ($query->rowCount() > 0) {
    foreach ($results as $rowProfile) {
        // Keep the same writer-performance/level tables the admin side reads from in sync.
        updateWriterPerformance($con, $rowProfile->id, $rowProfile->email);
        $performance = calculateWriterPerformance($con, $rowProfile->email);
        $currentLevel = getWriterLevel($con, $performance['completed_tasks']);
        $levelProgress = calculateLevelProgress($con, $performance['completed_tasks']);

        // Recent bonus (last calendar month, if one was ever calculated for it).
        $currentMonth = date('n');
        $currentYear = date('Y');
        $lastMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
        $bonusYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;
        $recentBonus = null;
        $bonusStmt = mysqli_prepare($con, "SELECT * FROM tbl_monthly_bonuses WHERE writer_email = ? AND month = ? AND year = ? LIMIT 1");
        if ($bonusStmt) {
            mysqli_stmt_bind_param($bonusStmt, 'sii', $rowProfile->email, $lastMonth, $bonusYear);
            mysqli_stmt_execute($bonusStmt);
            $recentBonus = mysqli_stmt_get_result($bonusStmt)->fetch_assoc();
            mysqli_stmt_close($bonusStmt);
        }
        ?>
        <title>My Profile | iTasker</title>
        <?php include "navi.php"; ?>

        <style>
            /* Scoped polish for the profile page. Prefixed .pf- so nothing collides with the Falcon theme. */
            .pf-page .pf-name { letter-spacing: -0.01em; }
            .pf-meta { display: flex; flex-wrap: wrap; gap: 0.35rem 1.5rem; margin: 0.5rem 0 1rem; }
            .pf-meta-item { display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; }
            .pf-meta-item .fas { width: 1rem; text-align: center; }

            .pf-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
            @media (min-width: 992px) { .pf-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
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

            @media (max-width: 575.98px) {
                .pf-stat { padding: 0.75rem 0.85rem; }
                .pf-stat-value { font-size: 1.2rem; }
            }
        </style>

        <div class="pf-page">
        <div class="card mb-3">
            <div class="card-header position-relative min-vh-25 mb-7">
                <?php if ($rowProfile->coverImage == "1.jpg") { ?>
                <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url(profileimages/1.jpg);">
                <?php } else { ?>
                    <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url('profileimages/<?php echo $rowProfile->coverImage; ?>');">
                <?php } ?>
              </div>
              <!--/.bg-holder-->

              <div class="avatar avatar-5xl avatar-profile">
                  <?php
                  if($rowProfile->Photo=="avatar.png")
                  {
                      ?>
                      <img class="rounded-circle img-thumbnail shadow-sm" src="assets/img/team/avatar.png" width="200" alt="" />
                      <?php
                  } else {
                      ?>
                      <img class="rounded-circle img-thumbnail shadow-sm" src="profileimages/<?php  echo $rowProfile->Photo;?>" width="200" alt="">
                      <?php
                  } ?>
              </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex align-items-center mb-1">
                            <h4 class="pf-name mb-0 text-info me-2"><?php echo htmlspecialchars($rowProfile->FirstName . ' ' . $rowProfile->LastName); ?></h4>
                            <?php if ($rowProfile->is_verified == 1) { ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" data-bs-toggle="tooltip" data-bs-placement="top" title="Verified">
                                    <span class="fas fa-check-circle me-1"></span>Verified
                                </span>
                            <?php } else { ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" data-bs-toggle="tooltip" data-bs-placement="top" title="Unverified">
                                    <span class="fas fa-times-circle me-1"></span>Unverified
                                </span>
                            <?php } ?>
                        </div>

                        <div class="pf-meta text-700">
                            <span class="pf-meta-item">
                                <span class="fas fa-envelope text-info"></span>
                                <a class="text-decoration-none text-primary" href="mailto:<?php echo htmlspecialchars($rowProfile->email); ?>"><?php echo htmlspecialchars($rowProfile->email); ?></a>
                            </span>
                            <span class="pf-meta-item">
                                <span class="fas fa-user text-info"></span>
                                <span class="text-primary"><?php echo htmlspecialchars($rowProfile->username, ENT_QUOTES, 'UTF-8'); ?></span>
                            </span>
                            <?php if (!empty($rowProfile->phone)) { ?>
                                <span class="pf-meta-item">
                                    <span class="fas fa-phone text-info"></span>
                                    <span class="text-primary"><?php echo htmlspecialchars($rowProfile->phone, ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            <?php } ?>
                            <span class="pf-meta-item">
                                <span class="fas fa-calendar-day text-info"></span>
                                <span class="text-primary"><?php echo date("jS F, Y", strtotime($rowProfile->created_at)); ?></span>
                            </span>
                        </div>

                        <a class="btn btn-outline-primary btn-sm px-3" type="button" href="settings">
                            <span class="fas fa-pen me-1"></span>Edit Profile
                        </a>
                    </div>
                </div>

                <!-- Writer level and progress -->
                <div class="card border-0 bg-body-quaternary mt-4">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas <?php echo $currentLevel['icon_class']; ?> fa-2x me-3" style="color: <?php echo $currentLevel['icon_color']; ?>;"></i>
                            <div class="flex-1">
                                <h6 class="mb-0" style="color: <?php echo $currentLevel['icon_color']; ?>;">Level <?php echo $currentLevel['level_number']; ?> - <?php echo htmlspecialchars($currentLevel['level_name']); ?></h6>
                                <small class="text-muted"><?php echo $performance['completed_tasks']; ?> tasks completed</small>
                            </div>
                        </div>

                        <?php if ($levelProgress['progress'] < 100): ?>
                            <div class="progress mb-1" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $levelProgress['progress']; ?>%; background-color: <?php echo $currentLevel['icon_color']; ?>;"></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted"><?php echo $levelProgress['progress']; ?>% to next level</small>
                                <small class="text-muted"><?php echo $levelProgress['tasks_remaining']; ?> tasks remaining</small>
                            </div>
                            <?php if (isset($levelProgress['next_level'])): ?>
                                <small class="text-success">
                                    <i class="fas <?php echo $levelProgress['next_level']['icon_class']; ?> me-1"></i>
                                    Next: <?php echo htmlspecialchars($levelProgress['next_level']['level_name']); ?>
                                </small>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center">
                                <span class="badge bg-warning text-dark px-3 py-2">
                                    <i class="fas fa-crown me-1"></i>Maximum Level Achieved!
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Task & earnings stats -->
                <div class="pf-stats mt-4">
                    <div class="pf-stat">
                        <span class="pf-stat-icon bg-success-subtle text-success"><span class="fas fa-check-circle"></span></span>
                        <div>
                            <div class="pf-stat-value text-primary"><?php echo $allCompleted; ?></div>
                            <div class="pf-stat-label text-700">Completed tasks</div>
                        </div>
                    </div>
                    <div class="pf-stat">
                        <span class="pf-stat-icon bg-warning-subtle text-warning"><span class="fas fa-spinner"></span></span>
                        <div>
                            <div class="pf-stat-value text-primary"><?php echo $allProgress; ?></div>
                            <div class="pf-stat-label text-700">In progress</div>
                        </div>
                    </div>
                    <div class="pf-stat">
                        <span class="pf-stat-icon bg-danger-subtle text-danger"><span class="fas fa-flag"></span></span>
                        <div>
                            <div class="pf-stat-value text-primary"><?php echo $allInRevision; ?></div>
                            <div class="pf-stat-label text-700">In revision</div>
                        </div>
                    </div>
                    <div class="pf-stat">
                        <span class="pf-stat-icon bg-info-subtle text-info"><span class="fas fa-wallet"></span></span>
                        <div>
                            <div class="pf-stat-value text-primary" style="font-size:1.1rem;"><?php echo $totalPaidFormatted; ?></div>
                            <div class="pf-stat-label text-700">Total earned</div>
                        </div>
                    </div>
                    <div class="pf-stat">
                        <span class="pf-stat-icon bg-primary-subtle text-primary"><span class="fas fa-hourglass-half"></span></span>
                        <div>
                            <div class="pf-stat-value text-primary" style="font-size:1.1rem;"><?php echo $totalUnpaidFormatted; ?></div>
                            <div class="pf-stat-label text-700">Awaiting payment</div>
                        </div>
                    </div>
                    <div class="pf-stat">
                        <span class="pf-stat-icon bg-secondary-subtle text-secondary"><span class="fas fa-user-clock"></span></span>
                        <div>
                            <div class="pf-stat-value text-primary" style="font-size:1.1rem;"><?php echo date("jS M, Y", strtotime($rowProfile->created_at)); ?></div>
                            <div class="pf-stat-label text-700">Member since</div>
                        </div>
                    </div>
                </div>

                <?php if (false): // Performance Metrics - disabled for now, not deleted ?>
                <!-- Performance metrics -->
                <div class="mt-4">
                    <h6 class="pf-section-title text-primary mb-3">Performance Metrics</h6>
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="card border-0 bg-success-subtle h-100">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-chart-line fa-2x text-success mb-2"></i>
                                    <h4 class="mb-0 text-success"><?php echo $performance['completion_rate']; ?>%</h4>
                                    <small class="text-muted">Completion Rate</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 bg-info-subtle h-100">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-clock fa-2x text-info mb-2"></i>
                                    <h4 class="mb-0 text-info"><?php echo $performance['on_time_rate']; ?>%</h4>
                                    <small class="text-muted">On-Time Rate</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 bg-warning-subtle h-100">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-tachometer-alt fa-2x text-warning mb-2"></i>
                                    <h4 class="mb-0 text-warning"><?php echo $performance['avg_completion_days']; ?></h4>
                                    <small class="text-muted">Avg Days to Finish</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 bg-primary-subtle h-100">
                                <div class="card-body text-center py-3">
                                    <i class="fas fa-medal fa-2x text-primary mb-2"></i>
                                    <h4 class="mb-0 text-primary"><?php echo $performance['early_completions']; ?></h4>
                                    <small class="text-muted">Early Completions</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($recentBonus): ?>
                    <!-- Recent bonus -->
                    <div class="mt-4">
                        <h6 class="pf-section-title text-primary mb-3">Recent Bonus &mdash; <?php echo date('F Y', mktime(0, 0, 0, $lastMonth, 1, $bonusYear)); ?></h6>
                        <div class="card border-0 bg-body-tertiary">
                            <div class="card-body py-3">
                                <div class="row g-3 align-items-center">
                                    <div class="col-6 col-md-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fas fa-gift text-success fs-6 me-2"></span>
                                            <div>
                                                <small class="text-muted d-block">Bonus Amount</small>
                                                <h6 class="mb-0 text-success">Ksh. <?php echo number_format($recentBonus['total_bonus_amount'], 2); ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="d-flex align-items-center">
                                            <span class="fas fa-percentage text-info fs-6 me-2"></span>
                                            <div>
                                                <small class="text-muted d-block">Bonus Rate</small>
                                                <h6 class="mb-0 text-info"><?php echo $recentBonus['bonus_percentage']; ?>%</h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <small class="text-muted">
                                            <span class="fas fa-check-double me-1"></span>
                                            <?php echo $recentBonus['tasks_completed_on_time']; ?>/<?php echo $recentBonus['total_tasks_completed']; ?> tasks on time
                                            <?php if ($recentBonus['perfect_month_bonus'] > 0) { ?>
                                                <span class="badge bg-success ms-2"><span class="fas fa-star me-1"></span>Perfect Month!</span>
                                            <?php } ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
          </div>
        </div>
        <?php
    }
} ?>
<?php
include "footer.php";
?>
