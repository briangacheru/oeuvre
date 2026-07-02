<?php include "head.php";?>
<?php
$aid = $_SESSION['odmsaid'];
$sql = "SELECT * FROM tbladmin WHERE email=:aid";
$query = $dbh->prepare($sql);
$query->bindParam(':aid', $aid, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);
$cnt = 1;

if ($query->rowCount() > 0) {
    foreach ($results as $rowAdmin) {
        if ($rowAdmin->AdminName == "Admin") {

?>
<title>iTasker | Dashboard - <?php echo $rowAdmin->username; ?></title>
<?php include "navi.php";?>
            <?php
            if (isset($_SESSION['alert'])) {
                echo $_SESSION['alert'];
                unset($_SESSION['alert']); // Clear the alert message
            }
            ?>
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
                                            <h3 class="text-primary mb-1"><?php echo $greeting; ?>, <span class="text-info"><?php echo $rowAdmin->username; ?>!</span></h3>
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
                                <div class="ps-3">
                                    <p class="text-600 fs-10">Completed | Unpaid</p>
                                    <?php
                                    // Query to sum CPP*pages for completed, unpaid tasks
                                    $query1 = mysqli_query($con, "SELECT SUM(CPP*pages) AS total FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND status = 'Completed'");
                                    $result1 = mysqli_fetch_assoc($query1);
                                    $totalCompletedTasks = (float) $result1['total']; // Cast to float to ensure arithmetic operation

                                    // Query to sum amount from tbloverdrafts
                                    $query2 = mysqli_query($con, "SELECT SUM(amount) AS total2 FROM tbloverdrafts WHERE is_settled = 0 AND description = 'iTasker' AND record_type = 'overdraft' AND is_deleted = 0");
                                    $result2 = mysqli_fetch_assoc($query2);
                                    $totalOverdrafts = (float) $result2['total2']; // Cast to float to ensure arithmetic operation

                                    $query5 = mysqli_query($con, "SELECT SUM(amount) AS total5 FROM tbloverdrafts WHERE is_settled = 0 AND record_type = 'bonus' AND description = 'Performance Bonus' AND is_deleted = 0");
                                    $result5 = mysqli_fetch_assoc($query5);
                                    $totalBonus = (float) $result5['total5'];

                                    // Calculate amount due by subtracting total completed task costs from total overdrafts
                                    $amount_due = $totalCompletedTasks + $totalBonus - $totalOverdrafts;
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
                                    // Query to sum CPP*pages for completed, unpaid tasks
                                    $query3 = mysqli_query($con, "SELECT SUM(CPP*pages) AS total3 FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND status IN ('Submitted', 'Completed')");
                                    $result3 = mysqli_fetch_assoc($query3);
                                    $totalSubComTasks = (float) $result3['total3']; // Cast to float to ensure arithmetic operation

                                    // Query to sum amount from tbloverdrafts
                                    $query4 = mysqli_query($con, "SELECT SUM(amount) AS total4 FROM tbloverdrafts WHERE is_settled = 0 AND record_type = 'overdraft' AND description = 'iTasker' AND is_deleted = 0");
                                    $result4 = mysqli_fetch_assoc($query4);
                                    $totalOver = (float) $result4['total4']; // Cast to float to ensure arithmetic operation

                                    $query5 = mysqli_query($con, "SELECT SUM(amount) AS total5 FROM tbloverdrafts WHERE is_settled = 0 AND record_type = 'bonus' AND description = 'Performance Bonus' AND is_deleted = 0");
                                    $result5 = mysqli_fetch_assoc($query5);
                                    $totalBonus = (float) $result5['total5'];

                                    // Calculate amount due by subtracting total completed task costs from total overdrafts
                                    $amount_due1 = $totalSubComTasks + $totalBonus - $totalOver;
                                    ?>
                                    <h4 class="text-800 mb-0"><span class="badge rounded-pill badge-subtle-info">Ksh. <?php echo number_format($amount_due1, 2, '.', ','); ?></span></h4>
                                </div>
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
                    $totalPaidFormatted = "Error: " . mysqli_error($con);
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
                    $totalUnPaidFormatted = "Error: " . mysqli_error($con);
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
<div class="row g-3 mb-3">
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
                <a class="fw-semi-bold fs-10 text-nowrap text-primary" href="usermanagement">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
</div>
    <?php
    // AdminName is "Admin", do something for admin
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




