<?php include "head.php";?>
    <title>iTasker | Submitted Tasks</title>
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
                    <h4 class="mb-0 text-primary fw-bold">Submitted <span class="text-info fw-medium"> Tasks</span></h4>
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
    <!--<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
        <div class="bg-success me-3 icon-item"><span class="fas fa-check-circle text-white fs-6"></span></div>
        <p class="mb-0 flex-1">A simple success alert—check it out!</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>-->

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
                                            <h4 class="mb-0">
                                                <span class="text-primary">Total:</span>
                                                <span class="text-warning">
                                                    <?php
                                                    $sql = "SELECT SUM(CPP * pages) AS total FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND status = 'Submitted'";
                                                    // Execute the query
                                                    $result = mysqli_query($con, $sql);
                                                    if ($result) {
                                                        // Fetch the result into an associative array
                                                        $row = mysqli_fetch_assoc($result);
                                                        echo ($row['total'] !== null ? $row['total'] : 0);
                                                    } else {
                                                        echo "Error: " . mysqli_error($con);
                                                    }
                                                    ?>
                                                </span>
                                            </h4>
                                        </div>

                                        <div class='col-md-auto mt-4 mt-md-0'>
                                            <button id='completeTasksBtn' class='btn btn-sm btn-falcon-default me-2'
                                                    type='button' onclick="submitForm('mark-tasks-completed')"
                                                    style='display: none;'>
                                                <span class='fas fa-check-double'
                                                      data-fa-transform='shrink-3 down-2'></span>
                                                <span class='d-none d-sm-inline-block ms-1'>Mark as Completed</span>
                                            </button>
                                            <button id='markUnreadBtn' class='btn btn-sm btn-falcon-warning me-2'
                                                    type='button' onclick="submitForm('mark-tasks-unread')"
                                                    style='display: none;'>
                                                <span class='fas fa-envelope'
                                                      data-fa-transform='shrink-3 down-2'></span>
                                                <span class='d-none d-sm-inline-block ms-1'>Mark as Unread</span>
                                            </button>
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
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Amount</th>
                                            <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                                        </tr>
                                        </thead>
                                        <tbody class="list" id="table-simple-pagination-body">
                                        <?php
                                            $query=mysqli_query($con,"select * from tbltasks WHERE is_deleted = 0 AND status = 'Submitted' ORDER BY submitted_on DESC");
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
                                         <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100 <?php echo ($row['admin_acknowledged'] == 0) ? 'table-active' : ''; ?>">
                                            <td class="align-middle" style="width: 28px;">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input bulk-select-checkbox" type="checkbox" id="simple-pagination-item-<?php echo $cnt; ?>" data-bulk-select-row="data-bulk-select-row" value="<?php echo $row['id']; ?>" name="taskIds[]"/>
                                                </div>
                                            </td>
                                            <td class="align-middle white-space-nowrap fw-semi-bold text-900"><?php echo $row["id"];?></td>
                                            <td>
                                                <div class="d-flex align-items-center position-relative">
                                                    <div class="flex-1">
                                                        <h6 class="mb-1 fw-semi-bold text-nowrap"><a
                                                                    class="text-900 stretched-link view-task-link"
                                                                    href="view-task?task_id=<?php echo $encodedId; ?>"
                                                                    data-task-id="<?php echo $row['id']; ?>"
                                                                    data-acknowledged="<?php echo $row['admin_acknowledged']; ?>"><?php echo $row['topic']; ?></a>
                                                        </h6>
                                                        <p class="fw-semi-bold mb-0 text-500"><?php echo $row["pages"];?> Page(s) | CPP: <?php echo $row["cpp"];?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle white-space-nowrap product">
                                                <div class="d-flex align-items-center position-relative">
                                                    <div class="flex-1">
                                                        <h6 class="mb-1 fw-semi-bold text-nowrap">
                                                            <?php echo $statusBadge;?>
                                                            <?php if ($is_confirmed == 1): ?><?php echo $confirmation;?><?php endif; ?>
                                                        </h6>
                                                        <p class="fw-semi-bold mb-0 text-500">
                                                            <?php
                                                            // Check if the function is already defined to prevent redeclaration
                                                            if (!function_exists('time_elapsed_string')) {
                                                                function time_elapsed_string($datetime, $full = false) {
                                                                    $now = new DateTime;
                                                                    $ago = new DateTime($datetime);
                                                                    $diff = $now->diff($ago);

                                                                    // Manually calculate weeks
                                                                    $weeks = floor($diff->d / 7);
                                                                    $days = $diff->d % 7;  // Remainder days after accounting for weeks

                                                                    // Create array with time components
                                                                    $string = [
                                                                        'y' => $diff->y,
                                                                        'm' => $diff->m,
                                                                        'w' => $weeks,
                                                                        'd' => $days,
                                                                        'h' => $diff->h,
                                                                        'i' => $diff->i,
                                                                        's' => $diff->s,
                                                                    ];

                                                                    // Define time units to display
                                                                    $time_units = [
                                                                        'y' => 'year',
                                                                        'm' => 'month',
                                                                        'w' => 'week',
                                                                        'd' => 'day',
                                                                        'h' => 'hour',
                                                                        'i' => 'minute',
                                                                        's' => 'second',
                                                                    ];

                                                                    // Create a human-readable string
                                                                    $result = [];
                                                                    foreach ($time_units as $key => $unit) {
                                                                        if ($string[$key]) {
                                                                            $result[] = $string[$key] . ' ' . $unit . ($string[$key] > 1 ? 's' : '');
                                                                        }
                                                                    }

                                                                    // Return the first component (e.g., "2 days ago") unless full is requested
                                                                    if (!$full) {
                                                                        $result = array_slice($result, 0, 1);
                                                                    }

                                                                    // Return human-readable time difference or "just now" if no time has passed
                                                                    return $result ? implode(', ', $result) . ' ago' : 'just now';
                                                                }
                                                            }

                                                            // Output the time elapsed since submission
                                                            echo time_elapsed_string($row["submitted_on"]);
                                                            ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="align-middle white-space-nowrap text-900">
                                                <h6 class="mb-1 fw-semi-bold text-nowrap"><?php echo $row["account"];?></h6>
                                                <p class="fw-semi-bold mb-0 text-500"><?php echo $row["writer"];?></p>
                                                </td>
                                            <td class="align-middle amount">
                                                <h6 class="mb-0"><?php echo number_format($totalprice,2); ?></h6>
                                                <p class="fs-11 mb-0"><?php echo $statusBadgePay;?></p>
                                            </td>
                                            <td class="align-middle white-space-nowrap text-end position-relative">
                                                <div class="hover-actions">
                                                    <a class="btn bg-primary-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm view-task-link" href="view-task?task_id=<?php echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="View task" data-task-id="<?php echo $row['id']; ?>" data-acknowledged="<?php echo $row['admin_acknowledged']; ?>"><span class="far fa-eye"></span></a>
                                                    <a class="btn bg-success-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"  href="edit-task?task_id=<?php echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Task"><span class="far fa-edit"></span></a>
                                                    <a class="btn bg-warning-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"  href="duplicate-task?task_id=<?php echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Duplicate Task"><span class="fas fa-copy"></span></a>

                                                    <?php if ($row['admin_acknowledged'] == 1): ?>
                                                        <button class="btn bg-info-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm mark-unread-btn" data-bs-toggle="tooltip" data-bs-placement="top" title="Mark as Unread" data-task-id="<?php echo $row['id']; ?>" onclick="markSingleTaskAsUnread(<?php echo $row['id']; ?>, this)">
                                                            <span class="fas fa-envelope"></span>
                                                        </button>
                                                    <?php endif; ?>
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


    <script>
        function submitForm(action) {
            document.getElementById('tasksForm').action = action + '.php';
            document.getElementById('tasksForm').submit();
        }

        function selectAllTasks(source) {
            const checkboxes = document.querySelectorAll('.bulk-select-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = source.checked);
            updateArchiveButtonVisibility();
        }

        function updateButtonVisibility() {
            const checkboxes = document.querySelectorAll('.bulk-select-checkbox:checked');
            const completeButton = document.getElementById('completeTasksBtn');
            const unreadButton = document.getElementById('markUnreadBtn');

            if (checkboxes.length > 0) {
                completeButton.style.display = 'inline-block';
                unreadButton.style.display = 'inline-block';
            } else {
                completeButton.style.display = 'none';
                unreadButton.style.display = 'none';
            }
        }

        // Function to mark task as read
        function markTaskAsRead(taskId, linkElement) {
            const params = new URLSearchParams();
            params.append('task_id', taskId);
            params.append('acknowledged', '1');

            fetch('update-task-acknowledgment', {
                method: 'POST',
                body: params,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-cache'
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                        // Remove the table-active styling from the row
                        const row = linkElement.closest('tr');
                        if (row) {
                            row.classList.remove('table-active');
                        }

                        // Update the data attribute to prevent future AJAX calls
                        linkElement.setAttribute('data-acknowledged', '1');
                    } else {
                        alert('Failed to mark task as read: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error marking task as read: ' + error.message);
                });
        }

        // Function to mark single task as unread
        function markSingleTaskAsUnread(taskId, buttonElement) {
            // Prevent multiple rapid clicks
            if (buttonElement.dataset.processing === 'true') {
                return;
            }

            buttonElement.dataset.processing = 'true';
            buttonElement.disabled = true;

            const params = new URLSearchParams();
            params.append('task_id', taskId);
            params.append('acknowledged', '0');

            fetch('update-task-acknowledgment', {
                method: 'POST',
                body: params,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-cache'
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                        // Add the table-active styling to the row (mark as unread)
                        const row = buttonElement.closest('tr');
                        if (row) {
                            row.classList.add('table-active');
                        }

                        // Hide the unread button since task is now unread
                        buttonElement.style.display = 'none';

                        // Update the view task link's data attribute
                        const viewTaskLink = row.querySelector('.view-task-link');
                        if (viewTaskLink) {
                            viewTaskLink.setAttribute('data-acknowledged', '0');
                        }

                        // Show success message (optional)
                        showTaskToast('Task marked as unread!', 'success');
                    } else {
                        alert('Failed to mark task as unread: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error marking task as unread: ' + error.message);
                })
                .finally(() => {
                    buttonElement.dataset.processing = 'false';
                    buttonElement.disabled = false;
                });
        }

        // Optional: Simple toast notification function
        function showTaskToast(message, type = 'info') {
            // Create a simple toast notification
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
            toast.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="bg-${type} me-3 icon-item">
                <span class="fas fa-${type === 'success' ? 'check' : 'info'}-circle text-white fs-5"></span>
            </div>
            <p class="mb-0 flex-1">${message} </p>
            <button class="btn-close" type="button" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;

            document.body.appendChild(toast);

            // Auto remove after 3 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 6000);
        }

        // Event listener setup
        document.addEventListener('DOMContentLoaded', function () {
            const checkboxes = document.querySelectorAll('.bulk-select-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateButtonVisibility);
            });

            updateButtonVisibility();

            const viewTaskLinks = document.querySelectorAll('.view-task-link');

            viewTaskLinks.forEach(link => {
                link.addEventListener('click', function (e) {
                    const taskId = this.getAttribute('data-task-id');
                    const acknowledged = this.getAttribute('data-acknowledged');

                    if (!taskId || taskId === 'null' || taskId === 'undefined') {
                        return;
                    }

                    // Only mark as read if it's currently unread (admin_acknowledged = 0)
                    if (acknowledged === '0') {
                        // Prevent multiple rapid clicks
                        if (this.dataset.processing === 'true') {
                            return;
                        }

                        this.dataset.processing = 'true';
                        markTaskAsRead(taskId, this);

                        // Reset processing flag after a delay
                        setTimeout(() => {
                            this.dataset.processing = 'false';
                        }, 2000);
                    }
                });
            });
        });
    </script>

<?php
include "footer.php";
?>