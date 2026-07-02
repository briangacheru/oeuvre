<?php include "head.php";?>
    <title>iTasker | Paid Tasks</title>
<?php include "navi.php";

$status = "OK";
$msg = "";
?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Paid <span class="text-info fw-medium"> Tasks</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0">
                    <form class="row flex-lg-column flex-xxl-row gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
                        <div class="col-auto">
                        </div>
                        <div class="col-md-auto position-relative">
                            <h6 class="mb-1 badge rounded-pill badge-subtle-info"><?php echo date("jS F Y"); ?> | <span id="timeDisplay"></span></h6>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    if (isset($_SESSION['alert'])) {
        echo $_SESSION['alert'];
        unset($_SESSION['alert']); // Clear the alert message
    }
    ?>
    <div class="row  g-3 mb-3">
        <div class="col">
            <div class="card mb-3">
                <div class="card-body p-0">
                    <div class="tab-content">
                        <div class="tab-pane preview-tab-pane active" role="tabpanel" aria-labelledby="tab-dom-41cf422d-2a1d-40e2-b92a-ceac8cdfaca0" id="dom-41cf422d-2a1d-40e2-b92a-ceac8cdfaca0">
                            <div class="card shadow-none">
                                <form id="tasksForm" method="post">
                                <div class="card-header">
                                    <div class="row flex-between-center">
                                        <div class="col-6 col-sm-auto d-flex align-items-center pe-0">
                                        </div>
                                        <div class="col-6 col-sm-auto ms-auto text-end ps-0">
                                            <div class="d-flex align-items-center" id="table-simple-pagination-replace-element">
                                                <a class="btn btn-falcon-info btn-sm mx-2" href="create-task" data-bs-toggle="tooltip" data-bs-placement="top" title="Create Task" type="button"><span class="fas fa-plus" data-fa-transform="shrink-3 down-2"></span><span class="d-none d-sm-inline-block ms-1">New Task</span></a>
                                                <button class="btn btn-falcon-primary btn-sm" onclick="exportPaid()" data-bs-toggle="tooltip" data-bs-placement="top" title="Export as CSV" type="button"><span class="fas fa-external-link-alt" data-fa-transform="shrink-3 down-2"></span><span class="d-none d-sm-inline-block ms-1">Export as CSV</span></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body px-0 pt-0">
                                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10"  data-datatables="data-datatables">
                                        <thead class="bg-200">
                                        <tr>
                                            <th class="text-900 no-sort white-space-nowrap">
                                                <div class="form-check mb-0 d-flex align-items-center">
                                                    <input class="form-check-input" id="checkbox-select-all" type="checkbox" onclick="selectAllTasks(this)" data-bulk-select='{"body":"table-simple-pagination-body","actions":"table-simple-pagination-actions","replacedElement":"table-simple-pagination-replace-element"}' />
                                                </div>
                                            </th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Task #</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Topic</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Status</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Account</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Subject</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Amount</th>
                                            <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                                        </tr>
                                        </thead>
                                        <tbody class="list" id="table-simple-pagination-body">
                                        <?php
                                            $query=mysqli_query($con,"select * from tbltasks WHERE is_deleted = 0 AND status = 'Completed' AND is_paid = 1 ORDER BY id DESC");
                                            $cnt=1;
                                            while($row=mysqli_fetch_array($query))
                                            {
                                                $totalprice=$row["cpp"]*$row["pages"];
                                                $encodedId = base64_encode($row["id"]); // Encode the id

                                                // Determine badge based on task status
                                                $statusBadge = '';
                                                switch ($row["status"]) {
                                                    case 'In Progress':
                                                        $statusBadge = '<span class="badge badge rounded-pill badge-subtle-warning">In Progress<span class="ms-1 fas fa-stream" data-fa-transform="shrink-2"></span></span>';
                                                        break;
                                                    case 'Cancelled':
                                                        $statusBadge = '<span class="badge badge rounded-pill badge-subtle-danger">Cancelled<span class="ms-1 fas fa-ban" data-fa-transform="shrink-2"></span></span>';
                                                        break;
                                                    case 'Draft':
                                                        $statusBadge = '<span class="badge badge rounded-pill badge-subtle-danger">Draft<span class="ms-1 fas fa-edit" data-fa-transform="shrink-2"></span></span>';
                                                        break;
                                                    case 'Unconfirmed':
                                                        $statusBadge = '<span class="badge badge rounded-pill badge-subtle-primary">Unconfirmed<span class="ms-1 fas fa-question" data-fa-transform="shrink-2"></span></span>';
                                                        break;
                                                    case 'Submitted':
                                                        $statusBadge = '<span class="badge badge rounded-pill badge-subtle-info">Submitted<span class="ms-1 fas fa-file" data-fa-transform="shrink-2"></span></span>';
                                                        break;
                                                    case 'Completed':
                                                        $statusBadge = '<span class="badge badge rounded-pill badge-subtle-success">Completed<span class="ms-1 fas fa-check" data-fa-transform="shrink-2"></span></span>';
                                                        break;
                                                }
                                                // Correctly retrieve is_paid status from the row
                                                $is_paid = $row['is_paid']; // Assuming 'is_paid' is the column name in your database
                                                // Determine badge based on payment status
                                                $statusBadgeClass = ($is_paid == 1) ? 'badge-subtle-success' : 'badge-subtle-warning';
                                                $statusBadgeText = ($is_paid == 1) ? 'Paid' : 'Unpaid';
                                                $statusBadgePay = "<span class='badge badge rounded-pill $statusBadgeClass'>$statusBadgeText</span>";

                                                $is_confirmed = $row['is_confirmed']; // Assuming 'is_paid' is the column name in your database
                                                $confirmationClass = ($is_confirmed == 0) ? 'bg-light' : 'bg-primary';
                                                $confirmationText = ($is_confirmed == 0) ? 'Confirmed' : 'Unconfirmed';
                                                $confirmation = "<span class='badge badge rounded-pill $confirmationClass'>$confirmationText</span>";
                                    ?>
                                        <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                                            <td class="align-middle" style="width: 28px;">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" id="simple-pagination-item-<?php echo $cnt; ?>" data-bulk-select-row="data-bulk-select-row" value="<?php echo $row['id']; ?>" name="taskIds[]"/>
                                                </div>
                                            </td>
                                            <td class="align-middle white-space-nowrap fw-semi-bold text-900"><?php echo $row["id"];?></td>
                                            <td>
                                                <div class="d-flex align-items-center position-relative">
                                                    <div class="flex-1">
                                                        <h6 class="mb-1 fw-semi-bold text-nowrap"><a class="text-900 stretched-link"  href="view-task?task_id=<?php echo $encodedId; ?>"><?php echo $row["topic"];?></a></h6>
                                                        <p class="fw-semi-bold mb-0 text-500"><?php echo $row["pages"];?> Page(s) | CPP: <?php echo $row["cpp"];?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center position-relative">
                                                    <div class="flex-1">
                                                        <h6 class="mb-1 fw-semi-bold text-nowrap"><?php echo $statusBadge;?></h6>
                                                        <p class="fw-semi-bold mb-0 text-500">
                                                            <?php
                                                            // Check if the function is already defined to prevent redeclaration
                                                            if (!function_exists('time_elapsed_string')) {
                                                                function time_elapsed_string($datetime, $full = false) {
                                                                    $now = new DateTime;
                                                                    $ago = new DateTime($datetime);
                                                                    $diff = $now->diff($ago);
                                                                    $weeks = floor($diff->d / 7);
                                                                    $days = $diff->d % 7;  // Remainder days after accounting for weeks
                                                                    $string = [
                                                                        'y' => $diff->y,
                                                                        'm' => $diff->m,
                                                                        'w' => $weeks,
                                                                        'd' => $days,
                                                                        'h' => $diff->h,
                                                                        'i' => $diff->i,
                                                                        's' => $diff->s,
                                                                    ];
                                                                    $time_units = [
                                                                        'y' => 'year',
                                                                        'm' => 'month',
                                                                        'w' => 'week',
                                                                        'd' => 'day',
                                                                        'h' => 'hour',
                                                                        'i' => 'minute',
                                                                        's' => 'second',
                                                                    ];
                                                                    $result = [];
                                                                    foreach ($time_units as $key => $unit) {
                                                                        if ($string[$key]) {
                                                                            $result[] = $string[$key] . ' ' . $unit . ($string[$key] > 1 ? 's' : '');
                                                                        }
                                                                    }
                                                                    if (!$full) {
                                                                        $result = array_slice($result, 0, 1);
                                                                    }
                                                                    return $result ? implode(', ', $result) . ' ago' : 'just now';
                                                                }
                                                            }
                                                            echo time_elapsed_string($row["completed_on"]);
                                                            ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle white-space-nowrap text-900">
                                                <h6 class="mb-1 fw-semi-bold text-nowrap"><?php echo $row["account"];?></h6>
                                                <p class="fw-semi-bold mb-0 text-500"><?php echo $row["writer"];?></p>
                                                </td>
                                            <td class="align-middle white-space-nowrap text-900"><?php echo $row["subject"];?></td>
                                            <td class="align-middle amount">
                                                <h6 class="mb-0"><?php echo number_format($totalprice,2); ?></h6>
                                                <p class="fs-11 mb-0"><?php echo $statusBadgePay;?> |
                                                <?php if ($is_paid == 1):
                                                    $paidOn = $row['paid_on'];
                                                    $paidDate = date("d M Y, g:i A", strtotime($paidOn));
                                                    ?> <span class="text-success ms-2 fs-10"><?php echo $paidDate; ?></span>
                                                <?php endif; ?></p>
                                            </td>
                                            <td class="align-middle white-space-nowrap text-end position-relative">
                                                <div class="hover-actions bg-100">
                                                    <a class="btn bg-primary-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"  href="view-task?task_id=<?php echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="View task" ><span class="far fa-eye"></span></a>
                                                    <a class="btn bg-warning-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"  href="duplicate-task?task_id=<?php echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Duplicate Task"><span class="fas fa-copy"></span></a>
                                                </div>
                                                <div class="dropdown font-sans-serif btn-reveal-trigger">
                                                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="crm-recent-leads-4" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-chevron-left fs-11"></span></button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                                $cnt=$cnt+1;
                                            }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
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