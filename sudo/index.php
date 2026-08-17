<?php include "head.php";?>
<?php
if (!function_exists('computeWriterNetPayable')) {
    // Nets completed/unpaid task cost + pending bonus - unsettled overdraft, per writer,
    // then sums only the positive nets. A writer's own overdraft can only offset their
    // own payout — it must never reduce what's owed to a different writer (e.g. one who
    // is invoiced directly and never carries an overdraft balance at all).
    function computeWriterNetPayable($con, $taskStatusCondition) {
        $tasksByWriter = [];
        $tasksResult = mysqli_query($con, "SELECT writer, SUM(CPP*pages) AS total FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND $taskStatusCondition GROUP BY writer");
        if ($tasksResult) {
            while ($row = mysqli_fetch_assoc($tasksResult)) {
                $tasksByWriter[$row['writer']] = (float) $row['total'];
            }
        }

        $bonusByWriter = [];
        $bonusResult = mysqli_query($con, "SELECT writer, SUM(amount) AS total FROM tbloverdrafts WHERE is_settled = 0 AND record_type = 'bonus' AND description = 'Performance Bonus' AND is_deleted = 0 GROUP BY writer");
        if ($bonusResult) {
            while ($row = mysqli_fetch_assoc($bonusResult)) {
                $bonusByWriter[$row['writer']] = (float) $row['total'];
            }
        }

        $overdraftByWriter = [];
        $overdraftResult = mysqli_query($con, "SELECT writer, SUM(amount) AS total FROM tbloverdrafts WHERE is_settled = 0 AND record_type = 'overdraft' AND description = 'iTasker' AND is_deleted = 0 GROUP BY writer");
        if ($overdraftResult) {
            while ($row = mysqli_fetch_assoc($overdraftResult)) {
                $overdraftByWriter[$row['writer']] = (float) $row['total'];
            }
        }

        $writers = array_unique(array_merge(array_keys($tasksByWriter), array_keys($bonusByWriter)));
        $total = 0.0;
        foreach ($writers as $writer) {
            $net = ($tasksByWriter[$writer] ?? 0) + ($bonusByWriter[$writer] ?? 0) - ($overdraftByWriter[$writer] ?? 0);
            if ($net > 0) {
                $total += $net;
            }
        }
        return $total;
    }
}
$aid = $_SESSION['odmsaid'];
$sql = "SELECT * FROM tbladmin WHERE email=:aid";
$query = $dbh->prepare($sql);
$query->bindParam(':aid', $aid, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);
$cnt = 1;

if ($query->rowCount() > 0) {
    foreach ($results as $rowAdmin) {
        if (isApprovedAdminRole($currentAdminRole)) {
            // $rowAdmin gets reused/overwritten by later mysqli_fetch_assoc() calls below,
            // so capture the admin's username now for use anywhere later in this view.
            $adminUsername = $rowAdmin->username;

?>
<title>iTasker | Dashboard - <?php echo htmlspecialchars($rowAdmin->username, ENT_QUOTES, 'UTF-8'); ?></title>
<?php include "navi.php";?>
            <?php
            if (isset($_SESSION['alert'])) {
                echo $_SESSION['alert'];
                unset($_SESSION['alert']); // Clear the alert message
            }
            ?>
<div class="d-flex justify-content-end mb-2">
    <div class="btn-group btn-group-sm shadow-sm" role="group" aria-label="Dashboard view toggle" id="dashboardViewToggle">
        <button type="button" class="btn btn-outline-primary" id="btnDashboardOld"><i class="fas fa-th-large me-1"></i>Classic View</button>
        <button type="button" class="btn btn-outline-primary" id="btnDashboardNew"><i class="fas fa-chart-pie me-1"></i>New View</button>
    </div>
</div>
<div id="dashboardOldView">
<div class="row  g-3 mb-3">
    <div class="col">
        <div class="card h-lg-100 overflow-hidden">
            <div class="card-body p-0">
                <div class="card bg-transparent-50 overflow-hidden">
                    <div class="card-header position-relative">
                        <div class="bg-holder d-none d-md-block bg-card z-1" style="background-image:url(../assets/img/illustrations/tasking.png);background-size:230px;background-position:right bottom;z-index:-1;">
                        </div>
                        <!--/.bg-holder-->

                        <div class="position-relative z-2">
                            <div>
                                <?php
                                // Get the current hour in 24-hour format
                                $currentHour = date('G');
                                // Initialize greeting variable
                                $greeting = '';
                                // Determine the part of the day and set the appropriate greeting
                                if ($currentHour < 12) {
                                    $greeting = 'Good Morning';
                                } elseif ($currentHour < 18) {
                                    $greeting = 'Good Afternoon';
                                } else {
                                    $greeting = 'Good Evening';
                                }
                                ?>

                                <div class="row flex-between-center">
                                    <div class="col">
                                        <div class="d-flex">
                                            <h3 class="text-primary mb-1"><?php echo $greeting; ?>, <span class="text-info"><?php echo htmlspecialchars($rowAdmin->username, ENT_QUOTES, 'UTF-8'); ?>!</span></h3>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center">
                                        <h4 class="text-800 mb-1"><span class="badge rounded-pill badge-subtle-success" id="timeDisplay"></span></span></h4>
                                    </div>
                                </div>
                                <p class="mb-2">Here’s what happening with your tasks today </p>
                            </div>
                            <div class="d-flex py-3">
                                <div class="pe-3">
                                    <p class="text-600 fs-10 fw-medium">Tasks due Today</p>
                                    <?php
                                    $todayTasks = "";
                                    // Added condition to filter tasks posted today
                                    $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND DATE(due_date) <= CURDATE() AND status = 'In Progress'";
                                    $result = mysqli_query($con, $query);
                                    if ($result) {
                                        $rowAdmin = mysqli_fetch_assoc($result);
                                        $count = $rowAdmin['taskCount'];
                                        if ($count > 0) {
                                            $todayTasks = $count; // Set the count to output variable
                                        } else {
                                            $todayTasks = "0"; // Set "0" if count is 0
                                        }
                                    } else {
                                        $todayTasks = "No data"; // Set "No Data" if query fails
                                    }
                                    ?>
                                    <h4 class="text-800 mb-0"><span class="badge rounded-pill badge-subtle-success"><?php echo $todayTasks; ?></span></h4>
                                </div>
                                <?php if (adminCan($currentAdminRole, 'operate_finance')): ?>
                                <div class="ps-3">
                                    <p class="text-600 fs-10">Completed | Unpaid</p>
                                    <?php
                                    // Net per writer (own bonus + own unpaid work - own overdraft, floored at 0),
                                    // then summed — so a writer with no overdraft (invoice-only) isn't shorted
                                    // by another writer's unrelated overdraft balance.
                                    $amount_due = computeWriterNetPayable($con, "status = 'Completed'");

                                    // Company-wide totals (used by the "Total Overdraft Amount" / "Total Bonus" cards below).
                                    $query2 = mysqli_query($con, "SELECT SUM(amount) AS total2 FROM tbloverdrafts WHERE is_settled = 0 AND description = 'iTasker' AND record_type = 'overdraft' AND is_deleted = 0");
                                    $result2 = mysqli_fetch_assoc($query2);
                                    $totalOverdrafts = (float) $result2['total2'];

                                    $query5 = mysqli_query($con, "SELECT SUM(amount) AS total5 FROM tbloverdrafts WHERE is_settled = 0 AND record_type = 'bonus' AND description = 'Performance Bonus' AND is_deleted = 0");
                                    $result5 = mysqli_fetch_assoc($query5);
                                    $totalBonus = (float) $result5['total5'];
                                    ?>
                                    <h4 class="text-800 mb-0"><span class="badge rounded-pill badge-subtle-info">Ksh. <?php echo number_format($amount_due, 2, '.', ','); ?></span></h4>
                                    <div class="form-text">Invoice updated
                                        <?php
                                        $query = mysqli_query($con, "SELECT created_at FROM tbloverdrafts 
                                            WHERE is_settled = 0 AND description = 'iTasker' AND is_deleted = 0
                                            ORDER BY created_at DESC 
                                            LIMIT 1");

                                        if($query) {
                                            $row = mysqli_fetch_assoc($query);

                                            if($row) {
                                                // Set timezone to Africa/Nairobi
                                                date_default_timezone_set('Africa/Nairobi');

                                                $created_at = new DateTime($row["created_at"]);
                                                $now = new DateTime(); // This will now use Africa/Nairobi timezone
                                                $interval = $now->diff($created_at);

                                                if ($interval->y > 0) {
                                                    echo $interval->y . " year" . ($interval->y > 1 ? "s" : "") . " ago";
                                                } elseif ($interval->m > 0) {
                                                    echo $interval->m . " month" . ($interval->m > 1 ? "s" : "") . " ago";
                                                } elseif ($interval->d > 6) {
                                                    $weeks = floor($interval->d / 7);
                                                    echo $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
                                                } elseif ($interval->d > 0) {
                                                    echo $interval->d . " day" . ($interval->d > 1 ? "s" : "") . " ago";
                                                } elseif ($interval->h > 0) {
                                                    echo $interval->h . " hour" . ($interval->h > 1 ? "s" : "") . " ago";
                                                } elseif ($interval->i > 0) {
                                                    echo $interval->i . " minute" . ($interval->i > 1 ? "s" : "") . " ago";
                                                } else {
                                                    echo $interval->s . " second" . ($interval->s > 1 ? "s" : "") . " ago";
                                                }
                                            } else {
                                                echo "All invoices settled.";
                                            }
                                        } else {
                                            echo "Error fetching invoice information";
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="ps-3">
                                    <p class="text-600 fs-10">Submitted|Completed|Unpaid </p>
                                    <?php
                                    // Same per-writer netting as the widget above, including submitted tasks.
                                    $amount_due1 = computeWriterNetPayable($con, "status IN ('Submitted', 'Completed')");
                                    ?>
                                    <h4 class="text-800 mb-0"><span class="badge rounded-pill badge-subtle-info">Ksh. <?php echo number_format($amount_due1, 2, '.', ','); ?></span></h4>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <ul class="mb-0 list-unstyled list-group font-sans-serif">

                            <?php
                            $todoClasses = "";
                            $query = "SELECT COUNT(*) as todoCount FROM tbltodos WHERE status = 'in_progress'";
                            $result = mysqli_query($con, $query);
                            if ($result) {
                                $rowAdmin = mysqli_fetch_assoc($result);
                                $count = $rowAdmin['todoCount'];
                                if ($count > 0) {
                                    $todoClasses = $count; // Set the count to output variable
                                } else {
                                    $todoClasses = "0"; // Set "0" if count is 0
                                }
                            } else {
                                $todoClasses = "No data"; // Set "No Data" if query fails
                            }
                            ?>
                            <?php if ($todoClasses >= 1): ?>
                                <li class="list-group-item mb-0 rounded-0 py-3 px-x1 list-group-item-success text-700  border-0">
                                    <div class="row flex-between-center">
                                        <div class="col">
                                            <div class="d-flex">
                                                <div class="fas fa-circle mt-1 fs-11 text-success"></div>
                                                <p class="fs-10 ps-2 mb-0">You have <strong><?php echo $todoClasses?> classes</strong> in progress</p>
                                            </div>
                                        </div>
                                        <div class="col-auto d-flex align-items-center"><a class="fs-10 fw-medium text-success-emphasis" href="todo">View to-do list<i class="fas fa-chevron-right ms-1 fs-11"></i></a></div>
                                    </div>
                                </li>
                            <?php endif; ?>

                            <?php
                            $allDeclined = "";
                            $query = "SELECT COUNT(*) as taskDeclined FROM tbltasks WHERE is_deleted = 0 AND (writer = 'Draft' OR status = 'Draft') AND is_confirmed = 2";
                            $result = mysqli_query($con, $query);
                            if ($result) {
                                $rowAdmin = mysqli_fetch_assoc($result);
                                $count = $rowAdmin['taskDeclined'];
                                if ($count > 0) {
                                    $allDeclined = $count; // Set the count to output variable
                                } else {
                                    $allDeclined = "0"; // Set "0" if count is 0
                                }
                            } else {
                                $allDeclined = "No data"; // Set "No Data" if query fails
                            }
                            ?>
                            <?php if ($allDeclined >= 1): ?>
                                <li class="list-group-item mb-0 rounded-0 py-3 px-x1 list-group-item-danger border-x-0 border-top-0">
                                    <div class="row flex-between-center">
                                        <div class="col">
                                            <div class="d-flex">
                                                <div class="fas fa-circle mt-1 fs-11"></div>
                                                <p class="fs-10 ps-2 mb-0"><strong><?php echo $allDeclined; ?> tasks</strong> are declined</p>
                                            </div>
                                        </div>
                                        <div class="col-auto d-flex align-items-center">
                                            <a class="fs-10 fw-medium text-warning-emphasis" href="drafts">
                                                View declined tasks<i class="fas fa-chevron-right ms-1 fs-11"></i>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            <?php endif; ?>

                            <?php if ($lateTasksCount >= 1): ?>
                            <li class="list-group-item mb-0 rounded-0 py-3 px-x1 list-group-item-warning border-x-0 border-top-0">
                                <div class="row flex-between-center">
                                    <div class="col">
                                        <div class="d-flex">
                                            <div class="fas fa-circle mt-1 fs-11"></div>
                                            <p class="fs-10 ps-2 mb-0"><strong><?php echo $lateTasksCount; ?> tasks</strong> are late</p>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center"><a class="fs-10 fw-medium text-warning-emphasis" href="tasks-in-progress">View late tasks<i class="fas fa-chevron-right ms-1 fs-11"></i></a></div>
                                </div>
                            </li>
                            <?php endif; ?>
                            <?php
                            $allUnpaid = "";
                            $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND status = 'Completed'";
                            $result = mysqli_query($con, $query);
                            if ($result) {
                                $rowAdmin = mysqli_fetch_assoc($result);
                                $count = $rowAdmin['taskCount'];
                                if ($count > 0) {
                                    $allUnpaid = $count; // Set the count to output variable
                                } else {
                                    $allUnpaid = "0"; // Set "0" if count is 0
                                }
                            } else {
                                $allUnpaid = "No data"; // Set "No Data" if query fails
                            }
                            ?>
                            <?php if ($allUnpaid >= 1): ?>
                            <li class="list-group-item mb-0 rounded-0 py-3 px-x1 list-group-item-info text-700 border-x-0 border-top-0">
                                <div class="row flex-between-center">
                                    <div class="col">
                                        <div class="d-flex">
                                            <div class="fas fa-circle mt-1 fs-11 text-primary"></div>
                                            <p class="fs-10 ps-2 mb-0"><strong><?php echo $allUnpaid ?> tasks</strong> are unpaid</p>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center"><a class="fs-10 fw-medium" href="unpaid-tasks">View unpaid tasks<i class="fas fa-chevron-right ms-1 fs-11"></i></a></div>
                                </div>
                            </li>
                            <?php endif; ?>
                            <?php
                            $allSubmitted = "";
                            $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted'";
                            $result = mysqli_query($con, $query);
                            if ($result) {
                                $rowAdmin = mysqli_fetch_assoc($result);
                                $count = $rowAdmin['taskCount'];
                                if ($count > 0) {
                                    $allSubmitted = $count; // Set the count to output variable
                                } else {
                                    $allSubmitted = "0"; // Set "0" if count is 0
                                }
                            } else {
                                $allSubmitted = "No data"; // Set "No Data" if query fails
                            }
                            ?>
                            <?php if ($allSubmitted >= 1): ?>
                            <li class="list-group-item mb-0 rounded-0 py-3 px-x1 list-group-item-success text-700  border-0">
                                <div class="row flex-between-center">
                                    <div class="col">
                                        <div class="d-flex">
                                            <div class="fas fa-circle mt-1 fs-11 text-success"></div>
                                            <p class="fs-10 ps-2 mb-0"><strong><?php echo $allSubmitted?> tasks</strong> need to be completed</p>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center"><a class="fs-10 fw-medium text-success-emphasis" href="submitted-tasks">View submitted tasks<i class="fas fa-chevron-right ms-1 fs-11"></i></a></div>
                                </div>
                            </li>
                            <?php endif; ?>
                            <?php
                            $pendingWriters = "";
                            $query = "SELECT COUNT(*) as pendingCount FROM tblwriters WHERE is_verified = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                            $result = mysqli_query($con, $query);
                            if ($result) {
                                $row = mysqli_fetch_assoc($result);
                                $count = $row['pendingCount'];
                                if ($count > 0) {
                                    $pendingWriters = $count;
                                } else {
                                    $pendingWriters = "0";
                                }
                            } else {
                                $pendingWriters = "No data";
                            }
                            ?>
                            <?php if ($pendingWriters >= 1): ?>
                                <li class="list-group-item mb-0 rounded-0 py-3 px-x1 list-group-item-warning text-700 border-0">
                                    <div class="row flex-between-center">
                                        <div class="col">
                                            <div class="d-flex">
                                                <div class="fas fa-circle mt-1 fs-11 text-warning"></div>
                                                <p class="fs-10 ps-2 mb-0">
                                                    <strong><?php echo $pendingWriters; ?> new writer<?php echo ($pendingWriters > 1) ? 's' : ''; ?></strong> awaiting verification
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-auto d-flex align-items-center">
                                            <a class="fs-10 fw-medium text-warning-emphasis" href="usermanagement">
                                                Review now<i class="fas fa-chevron-right ms-1 fs-11"></i>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            <?php endif; ?>
                            <?php
                            // Fetch the current registration status
                            $query = "SELECT regStatus FROM tblsettings WHERE id = 1"; // writer registration
                            $result = mysqli_query($con, $query);
                            $currentStatus = mysqli_fetch_assoc($result)['regStatus'];
                            $currentStatusText = $currentStatus == 1 ? 'OPEN' : 'CLOSED';
                            $badgeClass = $currentStatus == 1 ? 'badge-subtle-success' : 'badge-subtle-danger';
                            ?>

                            <!-- Display the div if regStatus is 0 -->
                            <?php if ($currentStatus == 0): ?>
                                <li class="list-group-item mb-0 rounded-0 py-3 px-x1 list-group-item-danger border-x-0 border-top-0">
                                    <div class="row flex-between-center">
                                        <div class="col">
                                            <div class="d-flex">
                                                <div class="fas fa-circle mt-1 fs-11"></div>
                                                <p class="fs-10 ps-2 mb-0">Writer registration <strong>is CLOSED</strong></p>
                                            </div>
                                        </div>
                                        <div class="col-auto d-flex align-items-center">
                                            <a class="fs-10 fw-medium text-warning-emphasis" href="settings">
                                                Open registration<i class="fas fa-chevron-right ms-1 fs-11"></i>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            <?php endif; ?>
                            <?php
                            // Fetch the current registration status
                            $query = "SELECT regStatus FROM tblsettings WHERE id = 2"; // admin registration
                            $result = mysqli_query($con, $query);
                            $currentStatus = mysqli_fetch_assoc($result)['regStatus'];
                            $currentStatusText = $currentStatus == 1 ? 'OPEN' : 'CLOSED';
                            $badgeClass = $currentStatus == 1 ? 'badge-subtle-success' : 'badge-subtle-danger';
                            ?>

                            <!-- Display the div if regStatus is 0 -->
                            <?php if ($currentStatus == 0): ?>
                                <li class="list-group-item mb-0 rounded-0 py-3 px-x1 list-group-item-danger border-x-0 border-top-0">
                                    <div class="row flex-between-center">
                                        <div class="col">
                                            <div class="d-flex">
                                                <div class="fas fa-circle mt-1 fs-11"></div>
                                                <p class="fs-10 ps-2 mb-0">Admin registration <strong>is CLOSED</strong></p>
                                            </div>
                                        </div>
                                        <div class="col-auto d-flex align-items-center">
                                            <a class="fs-10 fw-medium text-warning-emphasis" href="settings">
                                                Open registration<i class="fas fa-chevron-right ms-1 fs-11"></i>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mb-3">
    <div class=" col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-1.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $allTasks = "";
                $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0";
                $result = mysqli_query($con, $query);
                if ($result) {
                    $rowAdmin = mysqli_fetch_assoc($result);
                    $count = $rowAdmin['taskCount'];
                    if ($count > 0) {
                        $allTasks = $count; // Set the count to output variable
                    } else {
                        $allTasks = "0"; // Set "0" if count is 0
                    }
                } else {
                    $allTasks = "No data"; // Set "No Data" if query fails
                }
                ?>
                <h6>All Tasks</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-warning" data-countup='{"endValue":<?php echo $allTasks; ?>,"decimalPlaces":0}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-warning" href="all-tasks">See tasks<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
    <div class=" col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $allDrafts = "";
                $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND (writer = 'Draft' OR status = 'Draft')";
                $result = mysqli_query($con, $query);
                if ($result) {
                    $rowAdmin = mysqli_fetch_assoc($result);
                    $count = $rowAdmin['taskCount'];
                    if ($count > 0) {
                        $allDrafts = $count; // Set the count to output variable
                    } else {
                        $allDrafts = "0"; // Set "0" if count is 0
                    }
                } else {
                    $allDrafts = "No data"; // Set "No Data" if query fails
                }
                ?>
                <h6>Draft Tasks</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-info" data-countup='{"endValue":<?php echo $allDrafts; ?>,"decimalPlaces":0}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-info" href="draft-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-3.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $allUnconfirmed = "";
                $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_confirmed = 1";
                $result = mysqli_query($con, $query);
                if ($result) {
                    $rowAdmin = mysqli_fetch_assoc($result);
                    $count = $rowAdmin['taskCount'];
                    if ($count > 0) {
                        $allUnconfirmed = $count; // Set the count to output variable
                    } else {
                        $allUnconfirmed = "0"; // Set "0" if count is 0
                    }
                } else {
                    $allUnconfirmed = "No data"; // Set "No Data" if query fails
                }
                ?>
                <h6>Unconfirmed Tasks</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-primary" data-countup='{"endValue":<?php echo $allUnconfirmed; ?>}'>0</div><a class="fw-semi-bold fs-10 text-nowrap" href="unconfirmed">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mb-3">
                <div class=" col-md-4">
                    <div class="card overflow-hidden" style="min-width: 12rem">
                        <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-1.png);">
                        </div>
                        <!--/.bg-holder-->

                        <div class="card-body position-relative">
                            <?php
                            $allProgress = "";
                            $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress'";
                            $result = mysqli_query($con, $query);
                            if ($result) {
                                $rowAdmin = mysqli_fetch_assoc($result);
                                $count = $rowAdmin['taskCount'];
                                if ($count > 0) {
                                    $allProgress = $count; // Set the count to output variable
                                } else {
                                    $allProgress = "0"; // Set "0" if count is 0
                                }
                            } else {
                                $allProgress = "No data"; // Set "No Data" if query fails
                            }
                            ?>
                            <h6>Tasks in progress</h6>
                            <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-warning" data-countup='{"endValue":<?php echo $allProgress; ?>}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-warning" href="tasks-in-progress">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
                        </div>
                    </div>
                </div>
                <div class=" col-md-4">
                    <div class="card overflow-hidden" style="min-width: 12rem">
                        <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);">
                        </div>
                        <!--/.bg-holder-->

                        <div class="card-body position-relative">
                            <?php
                            $allSubmitted = "";
                            $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted'";
                            $result = mysqli_query($con, $query);
                            if ($result) {
                                $rowAdmin = mysqli_fetch_assoc($result);
                                $count = $rowAdmin['taskCount'];
                                if ($count > 0) {
                                    $allSubmitted = $count; // Set the count to output variable
                                } else {
                                    $allSubmitted = "0"; // Set "0" if count is 0
                                }
                            } else {
                                $allSubmitted = "No data"; // Set "No Data" if query fails
                            }
                            ?>
                            <h6>Submitted Tasks</h6>
                            <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-info" data-countup='{"endValue":<?php echo $allSubmitted; ?>}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-info" href="submitted-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card overflow-hidden" style="min-width: 12rem">
                        <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-3.png);">
                        </div>
                        <!--/.bg-holder-->

                        <div class="card-body position-relative">
                            <?php
                            $allCompleted = "";
                            $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed'";
                            $result = mysqli_query($con, $query);
                            if ($result) {
                                $rowAdmin = mysqli_fetch_assoc($result);
                                $count = $rowAdmin['taskCount'];
                                if ($count > 0) {
                                    $allCompleted = $count; // Set the count to output variable
                                } else {
                                    $allCompleted = "0"; // Set "0" if count is 0
                                }
                            } else {
                                $allCompleted = "No data"; // Set "No Data" if query fails
                            }
                            ?>
                            <h6>Completed Tasks</h6>
                            <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-primary" data-countup='{"endValue":<?php echo $allCompleted; ?>}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-primary" href="completed-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
                        </div>
                    </div>
                </div>
            </div>
<div class="row g-3 mb-3">
    <div class=" col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-1.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $allPaid = "";
                $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_paid = 1";
                $result = mysqli_query($con, $query);
                if ($result) {
                    $rowAdmin = mysqli_fetch_assoc($result);
                    $count = $rowAdmin['taskCount'];
                    if ($count > 0) {
                        $allPaid = $count; // Set the count to output variable
                    } else {
                        $allPaid = "0"; // Set "0" if count is 0
                    }
                } else {
                    $allPaid = "No data"; // Set "No Data" if query fails
                }
                ?>
                <h6>Paid Tasks</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-warning" data-countup='{"endValue":<?php echo $allPaid; ?>}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-warning" href="paid-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
    <div class=" col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $allUnpaid = "";
                $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND status = 'Completed'";
                $result = mysqli_query($con, $query);
                if ($result) {
                    $rowAdmin = mysqli_fetch_assoc($result);
                    $count = $rowAdmin['taskCount'];
                    if ($count > 0) {
                        $allUnpaid = $count; // Set the count to output variable
                    } else {
                        $allUnpaid = "0"; // Set "0" if count is 0
                    }
                } else {
                    $allUnpaid = "No data"; // Set "No Data" if query fails
                }
                ?>
                <h6>Unpaid Tasks</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-info" data-countup='{"endValue":<?php echo $allUnpaid; ?>}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-info" href="unpaid-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-3.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $allCancelled = "";
                $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 1";
                $result = mysqli_query($con, $query);
                if ($result) {
                    $rowAdmin = mysqli_fetch_assoc($result);
                    $count = $rowAdmin['taskCount'];
                    if ($count > 0) {
                        $allCancelled = $count; // Set the count to output variable
                    } else {
                        $allCancelled = "0"; // Set "0" if count is 0
                    }
                } else {
                    $allCancelled = "No data"; // Set "No Data" if query fails
                }
                ?>
                <h6>Cancelled Tasks</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-primary" data-countup='{"endValue":<?php echo $allCancelled; ?>}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-primary" href="cancelled-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
</div>
<?php if (adminCan($currentAdminRole, 'operate_finance')): ?>
<div class="row g-3 mb-3">
    <div class=" col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-1.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $totalPaidFormatted = "No data"; // Default message if the query fails
                $totalPaidRaw = 0; // Raw total for JavaScript
                $totalPaidShortened = "0"; // Shortened version
                $query = mysqli_query($con, "SELECT SUM(CPP*pages) AS total FROM tbltasks WHERE is_deleted = 0 AND is_paid = 1");
                if ($query) {
                    $rowAdmin = mysqli_fetch_array($query);
                    if ($rowAdmin && $rowAdmin['total'] !== null) {
                        $totalPaidRaw = $rowAdmin['total']; // Keep the raw total
                        $totalPaidFormatted = 'Ksh. ' . number_format($rowAdmin['total'], 2);
                        // Create shortened version
                        if ($totalPaidRaw >= 1000000) {
                            $totalPaidShortened = 'Ksh. ' . number_format($totalPaidRaw / 1000000, 2) . 'M';
                        } elseif ($totalPaidRaw >= 1000) {
                            $totalPaidShortened = 'Ksh. ' . number_format($totalPaidRaw / 1000, 2) . 'K';
                        } else {
                            $totalPaidShortened = 'Ksh. ' . number_format($totalPaidRaw, 2);
                        }
                    } else {
                        $totalPaidFormatted = 'Ksh. 0.00';
                        $totalPaidShortened = 'Ksh. 0.00';
                    }
                } else {
                    $totalPaidFormatted = "Error: " . safe_db_error(mysqli_error($con));
                    $totalPaidShortened = "Error";
                }
                ?>
                <h6>Total Paid Amount</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-primary"
                     data-bs-toggle="tooltip" data-bs-placement="right" title="<?php echo $totalPaidFormatted; ?>">
                    <?php echo $totalPaidShortened; ?>
                </div>
                <a class="fw-semi-bold fs-10 text-nowrap text-warning" href="paid-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
    <div class=" col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $totalUnPaidFormatted = "No data"; // Default message if the query fails
                $totalUnPaidRaw = 0; // Raw total for JavaScript
                $query = mysqli_query($con, "select sum(CPP*pages) as total  from tbltasks  WHERE is_deleted = 0 AND is_paid = 0 AND status = 'Completed'");
                if ($query) {
                    $rowAdmin = mysqli_fetch_array($query);
                    if ($rowAdmin && $rowAdmin['total'] !== null) {
                        $totalUnPaidRaw = $rowAdmin['total']; // Keep the raw total
                        $totalUnPaidFormatted = 'Ksh. ' . number_format($rowAdmin['total'], 2);
                    } else {
                        $totalUnPaidFormatted = 'Ksh. 0.00';
                    }
                } else {
                    $totalUnPaidFormatted = "Error: " . safe_db_error(mysqli_error($con));
                }
                ?>
                <h6>Total Unpaid Amount</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-info" data-countup='{"endValue":<?php echo $totalUnPaidRaw; ?>,"decimalPlaces":2,"prefix":"Ksh. "}'>0</div>
                <a class="fw-semi-bold fs-10 text-nowrap text-info" href="unpaid-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-3.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <h6>Total Amount Due</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-primary" data-countup='{"endValue":<?php echo $amount_due; ?>,"decimalPlaces":2,"prefix":"Ksh. "}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-primary" href="completed-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<div class="row g-3 mb-3">
    <?php if (adminCan($currentAdminRole, 'operate_tasks')): ?>
    <div class=" col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-1.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $sql ="SELECT id from tblwriters where is_verified=1 AND is_deleted = 0";
                $query = $dbh -> prepare($sql);
                $query->execute();
                $results=$query->fetchAll(PDO::FETCH_OBJ);
                $totalusersquery=$query->rowCount();

                // Writers online = last_seen within the last 5 minutes
                $onlineWritersList = [];
                $sqlOnline = "SELECT username FROM tblwriters 
                  WHERE is_deleted = 0 
                  AND is_active = 1 
                  AND is_verified = 1 
                  AND last_seen IS NOT NULL 
                  AND last_seen >= (UTC_TIMESTAMP() - INTERVAL 5 MINUTE)
                  ORDER BY username ASC";
                $qOnline = $dbh->prepare($sqlOnline);
                $qOnline->execute();
                $onlineRows = $qOnline->fetchAll(PDO::FETCH_OBJ);
                $onlineCount = $qOnline->rowCount();
                foreach ($onlineRows as $w) {
                    $onlineWritersList[] = htmlentities($w->username);
                }
                $tooltipText = $onlineCount > 0
                    ? implode(", ", $onlineWritersList)
                    : "No writers online right now";
                ?>
                <h6>
                    Verified Writers
                    <span class="ms-2 badge rounded-pill badge-subtle-success"
                          data-bs-toggle="tooltip"
                          data-bs-placement="right"
                          data-bs-html="true"
                          title="<?php echo $tooltipText; ?>"
                          style="cursor: help;">
            <span class="fas fa-circle text-success me-1" style="font-size:6px; vertical-align: middle;"></span>
            <?php echo $onlineCount; ?> online
        </span>
                </h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-warning" data-countup='{"endValue":<?php echo htmlentities($totalusersquery);?>}'>0</div>
                <a class="fw-semi-bold fs-10 text-nowrap text-warning" href="usermanagement">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (adminCan($currentAdminRole, 'operate_finance')): ?>
    <div class=" col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <h6>Total Overdraft Amount</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-info" data-countup='{"endValue":<?php echo $totalOverdrafts; ?>,"decimalPlaces":2,"prefix":"Ksh. "}'>0</div>
                <a class="fw-semi-bold fs-10 text-nowrap text-info" href="overdraft">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-3.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <h6>Total Bonus</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-primary" data-countup='{"endValue":<?php echo $totalBonus; ?>,"decimalPlaces":2,"prefix":"Ksh. "}'>0</div>
                <a class="fw-semi-bold fs-10 text-nowrap text-primary" href="bonus-history">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
<?php
// ---- New interactive dashboard view (same data as Classic View above, different presentation) ----
$writerRegStatus = 1;
$q = mysqli_query($con, "SELECT regStatus FROM tblsettings WHERE id = 1");
if ($q && ($r = mysqli_fetch_assoc($q))) { $writerRegStatus = (int) $r['regStatus']; }
$adminRegStatus = 1;
$q = mysqli_query($con, "SELECT regStatus FROM tblsettings WHERE id = 2");
if ($q && ($r = mysqli_fetch_assoc($q))) { $adminRegStatus = (int) $r['regStatus']; }
?>
<div id="dashboardNewView" style="display:none">
    <style>
        .itk-hero{border-radius:1rem;background:linear-gradient(135deg, var(--falcon-primary) 0%, var(--falcon-info) 100%);color:#fff;position:relative;overflow:hidden}
        .itk-hero .bg-holder{opacity:.15}
        .itk-hero-pill{background:rgba(255,255,255,.15);border-radius:.65rem;backdrop-filter:blur(2px)}
        .itk-section-title{font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600;color:rgba(var(--falcon-gray-600-rgb),1);margin:1.5rem 0 .75rem}
        .itk-stat-card{border-radius:.75rem;border:none;transition:transform .2s ease, box-shadow .2s ease;cursor:pointer;height:100%}
        .itk-stat-card:hover{transform:translateY(-4px);box-shadow:0 1rem 2rem rgba(0,0,0,.12)}
        .itk-icon{width:2.75rem;height:2.75rem;border-radius:.65rem;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex:none}
        .itk-activity-item{border-left:3px solid transparent;transition:background-color .15s ease;border-radius:.5rem}
        .itk-activity-item:hover{background-color:rgba(var(--falcon-gray-100-rgb),1)}
        .itk-pulse-dot{position:relative;display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--falcon-success)}
        .itk-pulse-dot::after{content:'';position:absolute;inset:-5px;border-radius:50%;border:2px solid var(--falcon-success);animation:itkPulse 1.6s ease-out infinite}
        @keyframes itkPulse{0%{transform:scale(.4);opacity:.9}100%{transform:scale(2.2);opacity:0}}
    </style>

    <div class="card itk-hero mb-3">
        <div class="bg-holder d-none d-md-block bg-card" style="background-image:url(../assets/img/illustrations/tasking.png);background-size:230px;background-position:right bottom;"></div>
        <div class="card-body position-relative">
            <div class="row flex-between-center">
                <div class="col">
                    <h3 class="text-white mb-1"><?php echo $greeting; ?>, <?php echo htmlspecialchars($adminUsername, ENT_QUOTES, 'UTF-8'); ?>!</h3>
                    <p class="mb-0 opacity-75">Here's a fresh look at what's happening today.</p>
                </div>
                <div class="col-auto">
                    <span class="badge rounded-pill bg-white text-primary fs-9" id="timeDisplayNew"></span>
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-auto">
                    <div class="itk-hero-pill px-3 py-2">
                        <div class="fs-10 opacity-75">Due Today</div>
                        <div class="fs-6 fw-bold"><?php echo $todayTasks; ?></div>
                    </div>
                </div>
                <?php if (adminCan($currentAdminRole, 'operate_finance')): ?>
                <div class="col-auto">
                    <div class="itk-hero-pill px-3 py-2">
                        <div class="fs-10 opacity-75">Completed | Unpaid</div>
                        <div class="fs-6 fw-bold">Ksh. <?php echo number_format($amount_due, 2, '.', ','); ?></div>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="itk-hero-pill px-3 py-2">
                        <div class="fs-10 opacity-75">Submitted | Completed | Unpaid</div>
                        <div class="fs-6 fw-bold">Ksh. <?php echo number_format($amount_due1, 2, '.', ','); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="itk-section-title">Task Pipeline</div>
    <div class="row g-3">
        <?php
        $pipelineCards = [
            ['label' => 'All Tasks', 'value' => $allTasks, 'icon' => 'fa-layer-group', 'color' => 'warning', 'href' => 'all-tasks'],
            ['label' => 'Draft', 'value' => $allDrafts, 'icon' => 'fa-edit', 'color' => 'info', 'href' => 'draft-tasks'],
            ['label' => 'Unconfirmed', 'value' => $allUnconfirmed, 'icon' => 'fa-question-circle', 'color' => 'primary', 'href' => 'unconfirmed'],
            ['label' => 'In Progress', 'value' => $allProgress, 'icon' => 'fa-spinner', 'color' => 'warning', 'href' => 'tasks-in-progress'],
            ['label' => 'Submitted', 'value' => $allSubmitted, 'icon' => 'fa-paper-plane', 'color' => 'info', 'href' => 'submitted-tasks'],
            ['label' => 'Completed', 'value' => $allCompleted, 'icon' => 'fa-check-circle', 'color' => 'success', 'href' => 'completed-tasks'],
            ['label' => 'Paid', 'value' => $allPaid, 'icon' => 'fa-hand-holding-usd', 'color' => 'success', 'href' => 'paid-tasks'],
            ['label' => 'Unpaid', 'value' => $allUnpaid, 'icon' => 'fa-money-bill-wave', 'color' => 'danger', 'href' => 'unpaid-tasks'],
            ['label' => 'Cancelled', 'value' => $allCancelled, 'icon' => 'fa-ban', 'color' => 'secondary', 'href' => 'cancelled-tasks'],
        ];
        foreach ($pipelineCards as $c) {
        ?>
        <div class="col-6 col-md-4 col-xl-3">
            <a href="<?php echo $c['href']; ?>" class="text-decoration-none">
                <div class="card itk-stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="itk-icon bg-<?php echo $c["color"]; ?>-subtle text-<?php echo $c['color']; ?>">
                            <i class="fas <?php echo $c['icon']; ?>"></i>
                        </div>
                        <div>
                            <p class="text-600 fs-10 mb-1"><?php echo $c['label']; ?></p>
                            <h5 class="mb-0 text-<?php echo $c['color']; ?>" data-countup='{"endValue":<?php echo (int) $c['value']; ?>,"decimalPlaces":0}'>0</h5>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php } ?>
    </div>

    <div class="row g-3 gx-lg-4 mt-1 mb-6">
        <div class="col-lg-5 mb-3 mb-lg-0">
            <div class="itk-section-title">Status Snapshot</div>
            <div class="card itk-stat-card" style="cursor:default">
                <div class="card-body">
                    <div id="dashboardNewDonut" style="height:19rem;"></div>
                    <p class="text-600 fs-10 mb-0 text-center">Live snapshot across task states</p>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="itk-section-title">Alerts &amp; Activity</div>
            <div class="card itk-stat-card" style="cursor:default">
                <div class="card-body">
                    <?php $hasActivity = false; ?>
                    <?php if ($todoClasses >= 1): $hasActivity = true; ?>
                    <a href="todo" class="d-flex align-items-center gap-3 p-2 mb-1 itk-activity-item text-decoration-none" style="border-color:var(--falcon-success)">
                        <div class="itk-icon bg-success-subtle text-success"><i class="fas fa-tasks"></i></div>
                        <div class="flex-1">
                            <p class="mb-0 fs-9 text-800"><strong><?php echo $todoClasses; ?> classes</strong> in progress</p>
                        </div>
                        <i class="fas fa-chevron-right fs-11 text-500"></i>
                    </a>
                    <?php endif; ?>

                    <?php if ($allDeclined >= 1): $hasActivity = true; ?>
                    <a href="drafts" class="d-flex align-items-center gap-3 p-2 mb-1 itk-activity-item text-decoration-none" style="border-color:var(--falcon-danger)">
                        <div class="itk-icon bg-danger-subtle text-danger"><i class="fas fa-times-circle"></i></div>
                        <div class="flex-1">
                            <p class="mb-0 fs-9 text-800"><strong><?php echo $allDeclined; ?> tasks</strong> are declined</p>
                        </div>
                        <i class="fas fa-chevron-right fs-11 text-500"></i>
                    </a>
                    <?php endif; ?>

                    <?php if ($lateTasksCount >= 1): $hasActivity = true; ?>
                    <a href="tasks-in-progress" class="d-flex align-items-center gap-3 p-2 mb-1 itk-activity-item text-decoration-none" style="border-color:var(--falcon-warning)">
                        <div class="itk-icon bg-warning-subtle text-warning"><i class="fas fa-clock"></i></div>
                        <div class="flex-1">
                            <p class="mb-0 fs-9 text-800"><strong><?php echo $lateTasksCount; ?> tasks</strong> are late</p>
                        </div>
                        <i class="fas fa-chevron-right fs-11 text-500"></i>
                    </a>
                    <?php endif; ?>

                    <?php if ($allUnpaid >= 1): $hasActivity = true; ?>
                    <a href="unpaid-tasks" class="d-flex align-items-center gap-3 p-2 mb-1 itk-activity-item text-decoration-none" style="border-color:var(--falcon-primary)">
                        <div class="itk-icon bg-primary-subtle text-primary"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="flex-1">
                            <p class="mb-0 fs-9 text-800"><strong><?php echo $allUnpaid; ?> tasks</strong> are unpaid</p>
                        </div>
                        <i class="fas fa-chevron-right fs-11 text-500"></i>
                    </a>
                    <?php endif; ?>

                    <?php if ($allSubmitted >= 1): $hasActivity = true; ?>
                    <a href="submitted-tasks" class="d-flex align-items-center gap-3 p-2 mb-1 itk-activity-item text-decoration-none" style="border-color:var(--falcon-success)">
                        <div class="itk-icon bg-success-subtle text-success"><i class="fas fa-paper-plane"></i></div>
                        <div class="flex-1">
                            <p class="mb-0 fs-9 text-800"><strong><?php echo $allSubmitted; ?> tasks</strong> need to be completed</p>
                        </div>
                        <i class="fas fa-chevron-right fs-11 text-500"></i>
                    </a>
                    <?php endif; ?>

                    <?php if ($pendingWriters >= 1): $hasActivity = true; ?>
                    <a href="usermanagement" class="d-flex align-items-center gap-3 p-2 mb-1 itk-activity-item text-decoration-none" style="border-color:var(--falcon-warning)">
                        <div class="itk-icon bg-warning-subtle text-warning"><i class="fas fa-user-clock"></i></div>
                        <div class="flex-1">
                            <p class="mb-0 fs-9 text-800"><strong><?php echo $pendingWriters; ?> new writer<?php echo ($pendingWriters > 1) ? 's' : ''; ?></strong> awaiting verification</p>
                        </div>
                        <i class="fas fa-chevron-right fs-11 text-500"></i>
                    </a>
                    <?php endif; ?>

                    <?php if ($writerRegStatus == 0): $hasActivity = true; ?>
                    <a href="settings" class="d-flex align-items-center gap-3 p-2 mb-1 itk-activity-item text-decoration-none" style="border-color:var(--falcon-danger)">
                        <div class="itk-icon bg-danger-subtle text-danger"><i class="fas fa-lock"></i></div>
                        <div class="flex-1">
                            <p class="mb-0 fs-9 text-800">Writer registration <strong>is CLOSED</strong></p>
                        </div>
                        <i class="fas fa-chevron-right fs-11 text-500"></i>
                    </a>
                    <?php endif; ?>

                    <?php if ($adminRegStatus == 0): $hasActivity = true; ?>
                    <a href="settings" class="d-flex align-items-center gap-3 p-2 mb-1 itk-activity-item text-decoration-none" style="border-color:var(--falcon-danger)">
                        <div class="itk-icon bg-danger-subtle text-danger"><i class="fas fa-lock"></i></div>
                        <div class="flex-1">
                            <p class="mb-0 fs-9 text-800">Admin registration <strong>is CLOSED</strong></p>
                        </div>
                        <i class="fas fa-chevron-right fs-11 text-500"></i>
                    </a>
                    <?php endif; ?>

                    <?php if (!$hasActivity): ?>
                    <div class="text-center text-500 py-4">
                        <i class="fas fa-check-circle fs-4 mb-2"></i>
                        <p class="mb-0 fs-9">All caught up — no alerts right now.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (adminCan($currentAdminRole, 'operate_finance')): ?>
    <div class="itk-section-title">Finance Overview</div>
    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <div class="card itk-stat-card" style="cursor:default">
                <div class="card-body">
                    <p class="text-600 fs-10 mb-1">Paid vs Unpaid</p>
                    <?php
                    $paidUnpaidTotal = $totalPaidRaw + $totalUnPaidRaw;
                    $paidPct = $paidUnpaidTotal > 0 ? round(($totalPaidRaw / $paidUnpaidTotal) * 100) : 0;
                    ?>
                    <div class="progress mb-2" style="height:8px">
                        <div class="progress-bar bg-success" style="width:<?php echo $paidPct; ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between fs-10 text-600">
                        <span>Paid <?php echo $paidPct; ?>%</span>
                        <span><?php echo 100 - $paidPct; ?>% Unpaid</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-6 col-xl-3">
            <a href="paid-tasks" class="text-decoration-none">
                <div class="card itk-stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="itk-icon bg-success-subtle text-success"><i class="fas fa-hand-holding-usd"></i></div>
                        <div>
                            <p class="text-600 fs-10 mb-1">Total Paid</p>
                            <h6 class="mb-0 text-success" data-bs-toggle="tooltip" title="<?php echo $totalPaidFormatted; ?>"><?php echo $totalPaidShortened; ?></h6>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-6 col-xl-3">
            <a href="unpaid-tasks" class="text-decoration-none">
                <div class="card itk-stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="itk-icon bg-danger-subtle text-danger"><i class="fas fa-money-bill-wave"></i></div>
                        <div>
                            <p class="text-600 fs-10 mb-1">Total Unpaid</p>
                            <h6 class="mb-0 text-danger" data-countup='{"endValue":<?php echo $totalUnPaidRaw; ?>,"decimalPlaces":2,"prefix":"Ksh. "}'>0</h6>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-6 col-xl-3">
            <a href="completed-tasks" class="text-decoration-none">
                <div class="card itk-stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="itk-icon bg-primary-subtle text-primary"><i class="fas fa-file-invoice-dollar"></i></div>
                        <div>
                            <p class="text-600 fs-10 mb-1">Total Amount Due</p>
                            <h6 class="mb-0 text-primary" data-countup='{"endValue":<?php echo $amount_due; ?>,"decimalPlaces":2,"prefix":"Ksh. "}'>0</h6>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-6 col-xl-3">
            <a href="overdraft" class="text-decoration-none">
                <div class="card itk-stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="itk-icon bg-info-subtle text-info"><i class="fas fa-balance-scale"></i></div>
                        <div>
                            <p class="text-600 fs-10 mb-1">Total Overdraft</p>
                            <h6 class="mb-0 text-info" data-countup='{"endValue":<?php echo $totalOverdrafts; ?>,"decimalPlaces":2,"prefix":"Ksh. "}'>0</h6>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-6 col-xl-3">
            <a href="bonus-history" class="text-decoration-none">
                <div class="card itk-stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="itk-icon bg-warning-subtle text-warning"><i class="fas fa-gift"></i></div>
                        <div>
                            <p class="text-600 fs-10 mb-1">Total Bonus</p>
                            <h6 class="mb-0 text-warning" data-countup='{"endValue":<?php echo $totalBonus; ?>,"decimalPlaces":2,"prefix":"Ksh. "}'>0</h6>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php if (adminCan($currentAdminRole, 'operate_tasks')): ?>
        <div class="col-6 col-md-6 col-xl-3">
            <a href="usermanagement" class="text-decoration-none">
                <div class="card itk-stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="itk-icon bg-secondary-subtle text-secondary"><i class="fas fa-user-check"></i></div>
                        <div class="flex-1">
                            <p class="text-600 fs-10 mb-1 d-flex align-items-center gap-2">Verified Writers
                                <span data-bs-toggle="tooltip" data-bs-html="true" title="<?php echo $tooltipText; ?>" style="cursor:help">
                                    <span class="itk-pulse-dot"></span> <?php echo $onlineCount; ?> online
                                </span>
                            </p>
                            <h6 class="mb-0 text-secondary" data-countup='{"endValue":<?php echo (int) $totalusersquery; ?>}'>0</h6>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var t = document.getElementById('timeDisplayNew');
    if (t) { setInterval(function () { t.textContent = new Date().toLocaleTimeString(); }, 1000); }

    var oldView = document.getElementById('dashboardOldView');
    var newView = document.getElementById('dashboardNewView');
    var btnOld = document.getElementById('btnDashboardOld');
    var btnNew = document.getElementById('btnDashboardNew');
    var donutInstance = null;

    function getComputedTextColor(cls) {
        var span = document.createElement('span');
        span.className = cls;
        span.style.display = 'none';
        document.body.appendChild(span);
        var color = getComputedStyle(span).color;
        document.body.removeChild(span);
        return color;
    }

    function renderDonut() {
        if (!window.echarts || donutInstance) return;
        var el = document.getElementById('dashboardNewDonut');
        if (!el) return;
        donutInstance = echarts.init(el);
        donutInstance.setOption({
            tooltip: { trigger: 'item' },
            legend: { bottom: 4, top: 'auto', itemWidth: 10, itemHeight: 10, textStyle: { fontSize: 12, color: getComputedTextColor('text-800') } },
            series: [{
                type: 'pie',
                center: ['50%', '38%'],
                radius: ['48%', '68%'],
                avoidLabelOverlap: true,
                label: { show: false },
                labelLine: { show: false },
                data: [
                    { value: <?php echo (int) $allDrafts; ?>, name: 'Draft', itemStyle: { color: '#39afd1' } },
                    { value: <?php echo (int) $allUnconfirmed; ?>, name: 'Unconfirmed', itemStyle: { color: '#2c7be5' } },
                    { value: <?php echo (int) $allProgress; ?>, name: 'In Progress', itemStyle: { color: '#f5803e' } },
                    { value: <?php echo (int) $allSubmitted; ?>, name: 'Submitted', itemStyle: { color: '#00d27a' } }
                ]
            }]
        });
    }

    function setView(view) {
        if (view === 'new') {
            oldView.style.display = 'none';
            newView.style.display = '';
            btnNew.classList.add('active');
            btnOld.classList.remove('active');
            renderDonut();
            if (donutInstance) { setTimeout(function () { donutInstance.resize(); }, 50); }
        } else {
            oldView.style.display = '';
            newView.style.display = 'none';
            btnOld.classList.add('active');
            btnNew.classList.remove('active');
        }
        try { localStorage.setItem('itasker_dashboard_view', view); } catch (e) {}
    }

    btnOld.addEventListener('click', function () { setView('old'); });
    btnNew.addEventListener('click', function () { setView('new'); });
    window.addEventListener('resize', function () { if (donutInstance) donutInstance.resize(); });

    var saved = 'old';
    try { saved = localStorage.getItem('itasker_dashboard_view') || 'old'; } catch (e) {}
    setView(saved);
});
</script>
    <?php
} else {
    echo '
<div class="row-cols-lg-12">
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <h4 class="alert-heading">Notification</h4>
        <p>Your account needs to be verified first</p>
            <hr>
            <p class="mb-0">Update your <a href="profile">Profile</a> in the mean time.</p>
    </div>
</div>';}}}?>

<?php
include "footer.php";
?>




