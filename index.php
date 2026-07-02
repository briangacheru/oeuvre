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
                    $totalPaidFormatted = "Error: " . mysqli_error($con);
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
                    $totalUnPaidFormatted = "Error: " . mysqli_error($con);
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




