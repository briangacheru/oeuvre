<?php
include "head.php";
?>
<?php
$aid = $_SESSION['sessionWriter'];
$sql = "SELECT * FROM tblwriters WHERE email=:aid";
$query = $dbh->prepare($sql);
$query->bindParam(':aid', $aid, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);
$cnt = 1;

if ($query->rowCount() > 0) {
foreach ($results as $rowWriter) {
if ($rowWriter->is_verified == 1) {
?>

    <title>Dashboard | iTasker</title>
<?php include "navi.php";?>

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
                        <div class="bg-holder d-none d-md-block bg-card z-1" style="background-image:url(https://i.giphy.com/media/v1.Y2lkPTc5MGI3NjExejMxdm5saGptc3YydGdlODJueDJiOTRlYWJjZzEwaTA0czhkNDJybCZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/VRtHA7ucvzkUMNEN0j/giphy.gif);background-size:230px;background-position:right bottom;z-index:-1;">
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
                                            <?php
                                            // Process username to show only first letter of second name
                                            $nameParts = explode(' ', trim($rowWriter->username));
                                            $displayName = $nameParts[0]; // First name
                                            if (count($nameParts) > 1) {
                                                $displayName .= ' ' . strtoupper(substr($nameParts[1], 0, 1)) . '.';
                                            }
                                            ?>
                                            <h3 class="text-primary mb-1"><?php echo $greeting; ?>, <span class="text-info"><?php echo $displayName; ?></span></h3>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center">
                                        <h5 class="text-800 mb-1"><span class="badge rounded-pill badge-subtle-success" id="timeDisplay"></span></span></h5>
                                    </div>
                                </div>
                                <p>Here’s what happening with your tasks today </p>
                            </div>
                            <div class="d-flex py-3">
                                <div class="pe-3">
                                    <p class="text-900 fs-10 fw-medium">Tasks due Today</p>
                                    <?php
                                    $todayTasks = "";
                                    // Added condition to filter tasks posted today
                                    $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND DATE(due_date) <= CURDATE() AND status ='In Progress' AND email = '$aid'";
                                    $result = mysqli_query($con, $query);
                                    if ($result) {
                                        $rowWriter = mysqli_fetch_assoc($result);
                                        $count = $rowWriter['taskCount'];
                                        if ($count > 0) {
                                            $todayTasks = $count; // Set the count to output variable
                                        } else {
                                            $todayTasks = "0"; // Set "0" if count is 0
                                        }
                                    } else {
                                        $todayTasks = "No data"; // Set "No Data" if query fails
                                    }
                                    ?>
                                    <h5 class="text-800 mb-0"><span class="badge rounded-pill badge-subtle-success"><?php echo $todayTasks; ?></span></h5>
                                </div>
                                <div class="ps-3">
                                    <p class="text-900 fs-10">Total Amount Due (completed tasks)</p>
                                    <?php
                                    // Query to sum CPP*pages for completed, unpaid tasks
                                    $query1 = mysqli_query($con, "SELECT SUM(CPP*pages) AS total FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND status = 'Completed' AND email = '$aid'");
                                    $result1 = mysqli_fetch_assoc($query1);
                                    $totalCompletedTasks = (float) $result1['total']; // Cast to float to ensure arithmetic operation

                                    // Query to sum amount from tbloverdrafts
                                    $query2 = mysqli_query($con, "SELECT SUM(amount) AS total FROM tbloverdrafts WHERE is_deleted = 0 AND is_settled = 0 AND record_type = 'overdraft' AND description = 'iTasker' AND email = '$aid'");
                                    $result2 = mysqli_fetch_assoc($query2);
                                    $totalOverdrafts = (float) $result2['total']; // Cast to float to ensure arithmetic operation

                                    $bonus_query = mysqli_query($con, "SELECT SUM(amount) AS total FROM tbloverdrafts WHERE is_settled = 0 AND is_deleted = 0 AND record_type = 'bonus' AND description = 'Performance Bonus' AND email = '$aid'");
                                    $result3 = mysqli_fetch_assoc($bonus_query);
                                    $totalBonuses = (float) $result3['total'];

                                    $amount_due = $totalCompletedTasks + $totalBonuses - $totalOverdrafts;
                                    ?>
                                    <h5 class="text-800 mb-0"><span class="badge rounded-pill badge-subtle-info">Ksh. <?php echo number_format($amount_due, 2, '.', ','); ?></span></h5>
                                </div>

                                <div class="ps-3">
                                    <p class="text-900 fs-10">Invoice last updated</p>
                                    <?php
                                // Sanitize the email
                                    $aid = mysqli_real_escape_string($con, $aid);

                                    $query = mysqli_query($con, "SELECT created_at FROM tbloverdrafts 
                                    WHERE is_deleted = 0 AND description = 'iTasker' AND email = '$aid' 
                                    ORDER BY created_at DESC 
                                    LIMIT 1");

                                    if($query) {
                                        $row = mysqli_fetch_assoc($query);
                                        ?>
                                        <h5 class="text-800 mb-0">
                                            <a href="invoice-logs" class="text-decoration-none" title="View all invoices">
                                            <span class="badge rounded-pill badge-subtle-warning"
                                                  style="cursor: pointer; transition: transform 0.15s ease, box-shadow 0.15s ease;"
                                                  onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)';"
                                                  onmouseout="this.style.transform=''; this.style.boxShadow='';">
                                            <?php
                                            // Helper for human-readable "x ago"
                                            $renderAgo = function($timestampString) {
                                                $then = new DateTime($timestampString . ' UTC');
                                                $now  = new DateTime('now');
                                                $interval = $now->diff($then);

                                                if ($interval->y > 0) {
                                                    return $interval->y . " year" . ($interval->y > 1 ? "s" : "") . " ago";
                                                } elseif ($interval->m > 0) {
                                                    return $interval->m . " month" . ($interval->m > 1 ? "s" : "") . " ago";
                                                } elseif ($interval->d > 6) {
                                                    $weeks = floor($interval->d / 7);
                                                    return $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
                                                } elseif ($interval->d > 0) {
                                                    return $interval->d . " day" . ($interval->d > 1 ? "s" : "") . " ago";
                                                } elseif ($interval->h > 0) {
                                                    return $interval->h . " hour" . ($interval->h > 1 ? "s" : "") . " ago";
                                                } elseif ($interval->i > 0) {
                                                    return $interval->i . " minute" . ($interval->i > 1 ? "s" : "") . " ago";
                                                } else {
                                                    return max(1, $interval->s) . " second" . ($interval->s > 1 ? "s" : "") . " ago";
                                                }
                                            };

                                            if ($row) {
                                                echo $renderAgo($row["created_at"]);
                                            } else {
                                                // Fallback: check tbl_invoice_logs for the latest sent invoice to this writer
                                                $writerNameRes = mysqli_query(
                                                    $con,
                                                    "SELECT username FROM tblwriters WHERE email = '$aid' AND is_deleted = 0 LIMIT 1"
                                                );
                                                $writerNameRow = $writerNameRes ? mysqli_fetch_assoc($writerNameRes) : null;
                                                $writerName = $writerNameRow['username'] ?? '';

                                                $invoiceFound = false;
                                                if ($writerName !== '') {
                                                    $safeWriter = mysqli_real_escape_string($con, $writerName);
                                                    $invQuery = mysqli_query(
                                                        $con,
                                                        "SELECT sent_at FROM tbl_invoice_logs
                                                                 WHERE writer_name = '$safeWriter'
                                                                 ORDER BY sent_at DESC
                                                                 LIMIT 1"
                                                    );
                                                    if ($invQuery && ($invRow = mysqli_fetch_assoc($invQuery))) {
                                                        echo $renderAgo($invRow['sent_at']);
                                                        $invoiceFound = true;
                                                    }
                                                }

                                                if (!$invoiceFound) {
                                                    echo "No invoice found";
                                                }
                                            }
                                            ?>
                                        </span>
                                        </a>
                                        </h5>
                                        <?php
                                    } else {
                                        echo "Error fetching invoice information";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <ul class="mb-0 list-unstyled list-group font-sans-serif">
                            <?php if ($lateTasksCount >= 1): ?>
                            <li class="list-group-item mb-0 rounded-0 py-3 px-x1 list-group-item-warning border-x-0 border-top-0">
                                <div class="row flex-between-center">
                                    <div class="col">
                                        <div class="d-flex">
                                            <div class="fas fa-circle mt-1 fs-11"></div>
                                            <p class="fs-10 ps-2 mb-0 text-900"> <strong><?php echo $lateTasksCount; ?> tasks</strong> are late</p>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center"><a class="fs-10 fw-medium text-warning-emphasis" href="tasks-in-progress">View tasks<i class="fas fa-chevron-right ms-1 fs-11"></i></a></div>
                                </div>
                            </li>
                            <?php endif; ?>
                            <?php
                            $allUnpaid = "";
                            $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND status = 'Completed' AND email = '$aid'";
                            $result = mysqli_query($con, $query);
                            if ($result) {
                                $rowWriter = mysqli_fetch_assoc($result);
                                $count = $rowWriter['taskCount'];
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
                            <li class="list-group-item mb-0 rounded-0 py-3 px-x1 list-group-item-primary text-700 border-x-0 border-top-0">
                                <div class="row flex-between-center">
                                    <div class="col">
                                        <div class="d-flex">
                                            <div class="fas fa-circle mt-1 fs-11 text-primary"></div>
                                            <p class="fs-10 ps-2 mb-0 text-900"> <strong><?php echo $allUnpaid ?> tasks</strong> are unpaid</p>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center"><a class="fs-10 fw-medium" href="unpaid-tasks">View payments<i class="fas fa-chevron-right ms-1 fs-11"></i></a></div>
                                </div>
                            </li>
                            <?php endif; ?>
                            <?php
                            $allSubmitted = "";
                            $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted' AND email = '$aid'";
                            $result = mysqli_query($con, $query);
                            if ($result) {
                                $rowWriter = mysqli_fetch_assoc($result);
                                $count = $rowWriter['taskCount'];
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
                            <li class="list-group-item mb-0 rounded-0 py-3 px-x1 list-group-item-primary text-700 border-x-0 border-top-0">
                                <div class="row flex-between-center">
                                    <div class="col">
                                        <div class="d-flex">
                                            <div class="fas fa-circle mt-1 fs-11 text-primary"></div>
                                            <p class="fs-10 ps-2 mb-0 text-900"> <strong><?php echo $allSubmitted?> tasks</strong> need to be completed by Admin</p>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center"><a class="fs-10 fw-medium" href="submitted-tasks">View tasks<i class="fas fa-chevron-right ms-1 fs-11"></i></a></div>
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
            <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-1.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $allTasks = "";
                $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE email = '$aid'  AND status != 'Draft' ";
                $result = mysqli_query($con, $query);
                if ($result) {
                    $rowWriter = mysqli_fetch_assoc($result);
                    $count = $rowWriter['taskCount'];
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
            <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-2.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $allProgress = "";
                $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND email = '$aid'";
                $result = mysqli_query($con, $query);
                if ($result) {
                    $rowWriter = mysqli_fetch_assoc($result);
                    $count = $rowWriter['taskCount'];
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
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-info" data-countup='{"endValue":<?php echo $allProgress; ?>}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-info" href="tasks-in-progress">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-3.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $allUnconfirmed = "";
                $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_confirmed = 1 AND email = '$aid'";
                $result = mysqli_query($con, $query);
                if ($result) {
                    $rowWriter = mysqli_fetch_assoc($result);
                    $count = $rowWriter['taskCount'];
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
                <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-1.png);">
                </div>
                <!--/.bg-holder-->

                <div class="card-body position-relative">
                    <?php
                    $allSubmitted = "";
                    $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Submitted' AND email = '$aid'";
                    $result = mysqli_query($con, $query);
                    if ($result) {
                        $rowWriter = mysqli_fetch_assoc($result);
                        $count = $rowWriter['taskCount'];
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
                    <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-warning" data-countup='{"endValue":<?php echo $allSubmitted; ?>}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-warning" href="submitted-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card overflow-hidden" style="min-width: 12rem">
                <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-2.png);">
                </div>
                <!--/.bg-holder-->

                <div class="card-body position-relative">
                    <?php
                    $allCompleted = "";
                    $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND email = '$aid'";
                    $result = mysqli_query($con, $query);
                    if ($result) {
                        $rowWriter = mysqli_fetch_assoc($result);
                        $count = $rowWriter['taskCount'];
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
                    <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-info" data-countup='{"endValue":<?php echo $allCompleted; ?>}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-info" href="completed-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card overflow-hidden" style="min-width: 12rem">
                <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-3.png);">
                </div>
                <!--/.bg-holder-->

                <div class="card-body position-relative">
                    <?php
                    $allCancelled = "";
                    $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 1 AND email = '$aid'";
                    $result = mysqli_query($con, $query);
                    if ($result) {
                        $rowWriter = mysqli_fetch_assoc($result);
                        $count = $rowWriter['taskCount'];
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
            <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-1.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $allPaid = "";
                $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_paid = 1 AND email = '$aid'";
                $result = mysqli_query($con, $query);
                if ($result) {
                    $rowWriter = mysqli_fetch_assoc($result);
                    $count = $rowWriter['taskCount'];
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
            <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-2.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $allUnpaid = "";
                $query = "SELECT COUNT(*) as taskCount FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND status = 'Completed'  AND email = '$aid'";
                $result = mysqli_query($con, $query);
                if ($result) {
                    $rowWriter = mysqli_fetch_assoc($result);
                    $count = $rowWriter['taskCount'];
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
    <div class=" col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-3.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $totalPaidFormatted = "No data"; // Default message if the query fails
                $totalPaidRaw = 0; // Raw total for JavaScript
                $totalPaidShortened = "0"; // Shortened version
                $query = mysqli_query($con, "SELECT SUM(CPP*pages) AS total FROM tbltasks WHERE is_deleted = 0 AND is_paid = 1 AND email = '$aid'");
                if ($query) {
                    $rowWriter = mysqli_fetch_array($query);
                    if ($rowWriter && $rowWriter['total'] !== null) {
                        $totalPaidRaw = $rowWriter['total']; // Keep the raw total
                        $totalPaidFormatted = 'Ksh. ' . number_format($rowWriter['total'], 2);

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
                <a class="fw-semi-bold fs-10 text-nowrap text-primary" href="paid-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class=" col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-1.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <?php
                $totalUnPaidFormatted = "No data"; // Default message if the query fails
                $totalUnPaidRaw = 0; // Raw total for JavaScript
                $query = mysqli_query($con, "select sum(CPP*pages) as total  from tbltasks  WHERE is_deleted = 0 AND is_paid = 0 AND status = 'Completed' AND email = '$aid'");
                if ($query) {
                    $rowWriter = mysqli_fetch_array($query);
                    if ($rowWriter && $rowWriter['total'] !== null) {
                        $totalUnPaidRaw = $rowWriter['total']; // Keep the raw total
                        $totalUnPaidFormatted = 'Ksh. ' . number_format($rowWriter['total'], 2);
                    } else {
                        $totalUnPaidFormatted = 'Ksh. 0.00';
                    }
                } else {
                    $totalUnPaidFormatted = "Error: " . safe_db_error(mysqli_error($con));
                }
                ?>
                <h6>Total Unpaid Amount</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-warning" data-countup='{"endValue":<?php echo $totalUnPaidRaw; ?>,"decimalPlaces":2,"prefix":"Ksh. "}'>0</div>
                <a class="fw-semi-bold fs-10 text-nowrap text-warning" href="unpaid-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
    <div class=" col-md-4">
        <div class="card overflow-hidden" style="min-width: 12rem">
            <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-2.png);">
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
            <div class="bg-holder bg-card" style="background-image:url(assets/img/icons/spot-illustrations/corner-3.png);">
            </div>
            <!--/.bg-holder-->

            <div class="card-body position-relative">
                <h6>Total Amount Due</h6>
                <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-primary" data-countup='{"endValue":<?php echo $amount_due; ?>,"decimalPlaces":2,"prefix":"Ksh. "}'>0</div><a class="fw-semi-bold fs-10 text-nowrap text-primary" href="completed-tasks">See all<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            </div>
        </div>
    </div>
</div>
</div>

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
    </style>

    <div class="card itk-hero mb-3">
        <div class="bg-holder d-none d-md-block bg-card" style="background-image:url(assets/img/illustrations/tasking.png);background-size:230px;background-position:right bottom;"></div>
        <div class="card-body position-relative">
            <div class="row flex-between-center">
                <div class="col">
                    <h3 class="text-white mb-1"><?php echo $greeting; ?>, <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>!</h3>
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
                <div class="col-auto">
                    <div class="itk-hero-pill px-3 py-2">
                        <div class="fs-10 opacity-75">Amount Due (completed tasks)</div>
                        <div class="fs-6 fw-bold">Ksh. <?php echo number_format($amount_due, 2, '.', ','); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="itk-section-title">Task Pipeline</div>
    <div class="row g-3">
        <?php
        $pipelineCardsWriter = [
            ['label' => 'All Tasks', 'value' => $allTasks, 'icon' => 'fa-layer-group', 'color' => 'warning', 'href' => 'all-tasks'],
            ['label' => 'In Progress', 'value' => $allProgress, 'icon' => 'fa-spinner', 'color' => 'info', 'href' => 'tasks-in-progress'],
            ['label' => 'Unconfirmed', 'value' => $allUnconfirmed, 'icon' => 'fa-question-circle', 'color' => 'primary', 'href' => 'unconfirmed'],
            ['label' => 'Submitted', 'value' => $allSubmitted, 'icon' => 'fa-paper-plane', 'color' => 'warning', 'href' => 'submitted-tasks'],
            ['label' => 'Completed', 'value' => $allCompleted, 'icon' => 'fa-check-circle', 'color' => 'info', 'href' => 'completed-tasks'],
            ['label' => 'Cancelled', 'value' => $allCancelled, 'icon' => 'fa-ban', 'color' => 'secondary', 'href' => 'cancelled-tasks'],
            ['label' => 'Paid', 'value' => $allPaid, 'icon' => 'fa-hand-holding-usd', 'color' => 'success', 'href' => 'paid-tasks'],
            ['label' => 'Unpaid', 'value' => $allUnpaid, 'icon' => 'fa-money-bill-wave', 'color' => 'danger', 'href' => 'unpaid-tasks'],
        ];
        foreach ($pipelineCardsWriter as $c) {
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

    <div class="row g-3 mt-1 mb-5">
        <div class="col-lg-5">
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
                    <?php $hasActivityWriter = false; ?>
                    <?php if ($lateTasksCount >= 1): $hasActivityWriter = true; ?>
                    <a href="tasks-in-progress" class="d-flex align-items-center gap-3 p-2 mb-1 itk-activity-item text-decoration-none" style="border-color:var(--falcon-warning)">
                        <div class="itk-icon bg-warning-subtle text-warning"><i class="fas fa-clock"></i></div>
                        <div class="flex-1">
                            <p class="mb-0 fs-9 text-800"><strong><?php echo $lateTasksCount; ?> tasks</strong> are late</p>
                        </div>
                        <i class="fas fa-chevron-right fs-11 text-500"></i>
                    </a>
                    <?php endif; ?>

                    <?php if ($allUnpaid >= 1): $hasActivityWriter = true; ?>
                    <a href="unpaid-tasks" class="d-flex align-items-center gap-3 p-2 mb-1 itk-activity-item text-decoration-none" style="border-color:var(--falcon-primary)">
                        <div class="itk-icon bg-primary-subtle text-primary"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="flex-1">
                            <p class="mb-0 fs-9 text-800"><strong><?php echo $allUnpaid; ?> tasks</strong> are unpaid</p>
                        </div>
                        <i class="fas fa-chevron-right fs-11 text-500"></i>
                    </a>
                    <?php endif; ?>

                    <?php if ($allSubmitted >= 1): $hasActivityWriter = true; ?>
                    <a href="submitted-tasks" class="d-flex align-items-center gap-3 p-2 mb-1 itk-activity-item text-decoration-none" style="border-color:var(--falcon-info)">
                        <div class="itk-icon bg-info-subtle text-info"><i class="fas fa-paper-plane"></i></div>
                        <div class="flex-1">
                            <p class="mb-0 fs-9 text-800"><strong><?php echo $allSubmitted; ?> tasks</strong> need to be completed by Admin</p>
                        </div>
                        <i class="fas fa-chevron-right fs-11 text-500"></i>
                    </a>
                    <?php endif; ?>

                    <?php if (!$hasActivityWriter): ?>
                    <div class="text-center text-500 py-4">
                        <i class="fas fa-check-circle fs-4 mb-2"></i>
                        <p class="mb-0 fs-9">All caught up — no alerts right now.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="itk-section-title">Finance Overview</div>
    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <div class="card itk-stat-card" style="cursor:default">
                <div class="card-body">
                    <p class="text-600 fs-10 mb-1">Paid vs Unpaid</p>
                    <?php
                    $paidUnpaidTotalWriter = $totalPaidRaw + $totalUnPaidRaw;
                    $paidPctWriter = $paidUnpaidTotalWriter > 0 ? round(($totalPaidRaw / $paidUnpaidTotalWriter) * 100) : 0;
                    ?>
                    <div class="progress mb-2" style="height:8px">
                        <div class="progress-bar bg-success" style="width:<?php echo $paidPctWriter; ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between fs-10 text-600">
                        <span>Paid <?php echo $paidPctWriter; ?>%</span>
                        <span><?php echo 100 - $paidPctWriter; ?>% Unpaid</span>
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
    </div>
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

    function renderDonut() {
        if (!window.echarts || donutInstance) return;
        var el = document.getElementById('dashboardNewDonut');
        if (!el) return;
        donutInstance = echarts.init(el);
        donutInstance.setOption({
            tooltip: { trigger: 'item' },
            legend: { bottom: 4, top: 'auto', itemWidth: 10, itemHeight: 10, textStyle: { fontSize: 10 } },
            series: [{
                type: 'pie',
                center: ['50%', '38%'],
                radius: ['48%', '68%'],
                avoidLabelOverlap: true,
                label: { show: false },
                labelLine: { show: false },
                data: [
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
        try { localStorage.setItem('itasker_writer_dashboard_view', view); } catch (e) {}
    }

    btnOld.addEventListener('click', function () { setView('old'); });
    btnNew.addEventListener('click', function () { setView('new'); });
    window.addEventListener('resize', function () { if (donutInstance) donutInstance.resize(); });

    var saved = 'old';
    try { saved = localStorage.getItem('itasker_writer_dashboard_view') || 'old'; } catch (e) {}
    setView(saved);
});
</script>
    <?php
} else {
    header("Location: verification");
    exit();
}
}
}
?>

<?php
include "footer.php";
?>




