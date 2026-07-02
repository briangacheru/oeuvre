<?php include "head.php";?>
    <title>Tasks in Progress | iTasker</title>
<?php include "navi.php";?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">In Progress <span class="text-info fw-medium"> Tasks</span></h4>
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
                                    </div>
                                </div>
                                <div class="card-body px-0 pt-0">
                                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10"  data-datatables="data-datatables">
                                        <thead class="bg-200">
                                        <tr>
                                            <th class="text-900 no-sort white-space-nowrap d-none">
                                                <div class="form-check mb-0 d-flex align-items-center">
                                                    <input class="form-check-input" id="checkbox-select-all" type="checkbox" onclick="selectAllTasks(this)" data-bulk-select='{"body":"table-simple-pagination-body","actions":"table-simple-pagination-actions","replacedElement":"table-simple-pagination-replace-element"}' />
                                                </div>
                                            </th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap"><span class='ms-3'>Task #</span></th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Topic</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Deadline</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Status</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Amount</th>
                                        </tr>
                                        </thead>
                                        <tbody class="list" id="table-simple-pagination-body">
                                        <?php
                                            $query=mysqli_query($con,"select * from tbltasks WHERE is_deleted = 0 AND status = 'In Progress' AND email='$aid' ORDER BY due_date ASC");
                                            $cnt=1;
                                            while($row=mysqli_fetch_array($query))
                                            {
                                                $totalprice=$row["cpp"]*$row["pages"];
                                                $encodedId = base64_encode($row["id"]); // Encode the id

                                                $due_date = new DateTime($row['due_date']);
                                                $currentDateTime = new DateTime(); // Assuming you've already got this
                                                $interval = $currentDateTime->diff($due_date);
                                                $isLate = ($due_date < $currentDateTime) ? true : false;

                                                // Calculate total hours and minutes
                                                $totalHours = ($interval->days * 24) + $interval->h;
                                                $totalMinutes = $interval->i;

                                                // Format the difference as a string, and choose color based on whether it's late
                                                if ($row['status'] == 'Completed') {
                                                    $timeDiff = "<span class='text-success fw-semi-bold'>Completed</span>";
                                                } elseif ($row['status'] == 'Cancelled') {
                                                    $timeDiff = "<span class='text-danger fw-semi-bold'>Cancelled</span>";
                                                } elseif ($row['status'] == 'Submitted') {
                                                    $timeDiff = "<span class='text-primary fw-semi-bold'>Submitted</span>";
                                                } elseif ($row['is_confirmed'] == 2) {
                                                    $timeDiff = "<span class='text-danger fw-semi-bold'>Declined</span>";
                                                } else {
                                                    if ($isLate) {
                                                        $timeDiff = "<span class='text-danger fw-semi-bold'>$totalHours hrs $totalMinutes min </span>";
                                                    } else {
                                                        $timeDiff = "<span class='text-success fw-semi-bold'>$totalHours hrs $totalMinutes min </span>";
                                                    }
                                                }

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
                                                if ($isLate) {
                                                    $statusBadge .= ' <span class="badge badge rounded-pill badge-subtle-danger">Late<span class="ms-1 fa fa-exclamation-triangle" data-fa-transform="shrink-2"></span></span>';
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
                                        <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-300 <?php echo ($row['acknowledged'] == 0) ? 'table-active' : ''; ?>">
                                            <td class="align-middle d-none" style="width: 28px;">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" id="simple-pagination-item-<?php echo $cnt; ?>" data-bulk-select-row="data-bulk-select-row" value="<?php echo $row['id']; ?>" name="taskIds[]"/>
                                                </div>
                                            </td>
                                            <td class="align-middle white-space-nowrap fw-semi-bold text-900"><span class='ms-3'><?php echo $row['id']; ?></span></td>
                                            <td>
                                                <div class="d-flex align-items-center position-relative">
                                                    <div class="flex-1">
                                                        <h6 class="mb-1 fw-semi-bold text-nowrap">
                                                            <a class="text-900 stretched-link view-task-link"  href="view-task?task_id=<?php echo $encodedId; ?>" data-task-id="<?php echo $row['id']; ?>" data-acknowledged="<?php echo $row['acknowledged']; ?>"><?php echo $row["topic"];?></a>
                                                        </h6>
                                                        <p class="fw-semi-bold mb-0 text-500"><?php echo $row["pages"];?> Page(s) | CPP: <?php echo $row["cpp"];?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle white-space-nowrap email">
                                                <h6 class="mb-1 fw-semi-bold text-nowrap"><?php echo $timeDiff;?></h6>
                                                <p class="fw-semi-bold mb-0 text-500"><?php echo date('M j, g:ia', strtotime($row["due_date"]));?></p>
                                            </td>
                                            <td class="align-middle white-space-nowrap product"><?php echo $statusBadge;?>
                                            <?php if ($is_confirmed == 1): ?>
                                                <?php echo $confirmation;?>
                                            <?php endif; ?>
                                            </td>
                                            <td class="align-middle amount">
                                                <h6 class="mb-0"><?php echo number_format($totalprice,2); ?></h6>
                                                <p class="fs-11 mb-0"><?php echo $statusBadgePay;?></p>
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

    <script>
        // Function to mark task as read
        function markAsRead(taskId, linkElement) {
            // Send AJAX request to update acknowledged
            fetch('update-task-acknowledgement', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'task_id=' + taskId + '&acknowledged=1'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Find the parent row and remove the table-active styling
                        const row = linkElement.closest('tr');
                        if (row) {
                            row.classList.remove('table-active');
                        }
                    }
                })
                .catch(error => {
                    // Silent error handling - you can add user notification here if needed
                });
        }

        // Add event listeners
        document.addEventListener('DOMContentLoaded', function () {
            const viewTaskLinks = document.querySelectorAll('.view-task-link');

            viewTaskLinks.forEach(link => {
                link.addEventListener('click', function (e) {
                    const taskId = this.getAttribute('data-task-id');
                    const acknowledged = this.getAttribute('data-acknowledged');

                    // Only mark as read if it's currently unread (acknowledged = 0)
                    if (acknowledged === '0') {
                        markAsRead(taskId, this);
                    }
                });
            });
        });
    </script>

<?php
include "footer.php";
?>