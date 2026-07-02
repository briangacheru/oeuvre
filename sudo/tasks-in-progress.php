<?php
require_once __DIR__ . '/../env.php'; include "head.php";?>
    <title>iTasker | Tasks in Progress</title>
<?php include "navi.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

$status = "OK";
$msg = "";

if (isset($_GET['del'])) {
    $encodedId = $_GET['del'];
    $cmpid = (int) base64_decode($encodedId);

    // Validate $cmpid to ensure it's numeric and not empty
    if (is_numeric($cmpid) && !empty($cmpid)) {

        // First, retrieve the current status and is_paid value of the task
        $checkQuery = mysqli_query($con, "SELECT status, is_paid FROM tbltasks WHERE id='$cmpid'");
        $rowData = mysqli_fetch_assoc($checkQuery);

        if ($rowData && ($rowData['status'] == 'Completed' || $rowData['status'] == 'Submitted' || $rowData['is_paid'] == 1)) {
            $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                  <i class="bi bi-exclamation-triangle"></i> Task cannot be cancelled as it is already completed, submitted, or paid.
                                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>';
        } else {
            // Perform the delete operation if the task is not completed, submitted, or paid
            $query = mysqli_query($con, "UPDATE tbltasks SET is_deleted = 1 , status = 'Cancelled' WHERE id='$cmpid'");

            if ($query) {

                // Fetch task details and writer's email
                $taskQuery = mysqli_query($con, "SELECT * FROM tbltasks WHERE id='$cmpid'");
                $taskData = mysqli_fetch_assoc($taskQuery);
                $writerEmail = $taskData['email'];
                $taskTopic = $taskData['topic'];
                $taskSubject = $taskData['subject'];
                $taskDueDate = $taskData['due_date'];
                $taskPages = $taskData['pages'];
                $taskDescription = $taskData['description'];
                $taskAccount = $taskData['account'];

                // Initialize PHPMailer
                $mail = new PHPMailer(true);

                try {
                    // Server settings
                    $mail->SMTPDebug = 0;                       // Enable verbose debug output
                    $mail->isSMTP();
                    $mail->Host       = env('SMTP_HOST');
                    $mail->SMTPAuth   = true;
                    $mail->Username   = env('SMTP_USER');
                    $mail->Password   = env('SMTP_PASS');
                    $mail->SMTPSecure = 'tls';
                    $mail->Port       = (int) env('SMTP_PORT', 587);

                    $mail->setFrom(env('MAIL_FROM_ADDRESS'), 'itasker');
                    $mail->addReplyTo(env('ADMIN_EMAIL'), 'Bryo Gacheru');
                    $mail->addAddress($writerEmail);
                    $mail->addAddress(env('ADMIN_EMAIL'), 'iTasker Admin');

                    // Content
                    $mail->isHTML(true);                        // Set email format to HTML
                    $mail->Subject = 'Task ID: ' . $cmpid . ' - ' . $taskTopic . ' - [ ' . $taskAccount. ' ] ';
                    $mail->Body    = "<h1>Task $cmpid has been Cancelled. Do not go ahead with it.</h1>";

                    $mail->send();

                    $_SESSION['alert'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                              <i class="bi bi-check-circle"></i> Task cancelled successfully and email notification sent.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                } catch (Exception $e) {
                    $_SESSION['alert'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                              <i class="bi bi-check-circle"></i> Task cancelled successfully, but email notification could not be sent. Mailer Error: ' . $mail->ErrorInfo . '
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                }
            } else {
                $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                          <i class="bi bi-exclamation-octagon"></i> Error cancelling task record.
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
            }
        }
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                              <i class="bi bi-exclamation-octagon"></i> Invalid or missing ID.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
    }

    header('Location: tasks-in-progress');
    exit;
}

?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);">
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
    <!--<div class="alert alert-success border-0 d-flex align-items-center" role="alert">
        <div class="bg-success me-3 icon-item"><span class="fas fa-check-circle text-white fs-6"></span></div>
        <p class="mb-0 flex-1">A simple success alert—check it out!</p>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>-->
    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

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
                                                    $sql = "SELECT SUM(CPP * pages) AS total FROM tbltasks WHERE is_deleted = 0 AND is_paid = 0 AND status = 'In Progress'";
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
                                            <div class="col-6 col-sm-auto ms-auto text-end ps-0">
                                                <div class="d-none" id="table-simple-pagination-actions">
                                                    <div class="d-flex">
                                                        <button type="button" class="btn btn-falcon-info btn-sm ms-2" onclick="submitForm('mark-inprogress-complete')">Mark as Completed</button>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center" id="table-simple-pagination-replace-element">
                                                    <a class="btn btn-falcon-info btn-sm mx-2" href="create-task" data-bs-toggle="tooltip" data-bs-placement="top" title="Create Task" type="button"><span class="fas fa-plus" data-fa-transform="shrink-3 down-2"></span><span class="d-none d-sm-inline-block ms-1">New Task</span></a>
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
                                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Deadline</th>
                                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Amount</th>
                                                <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                                            </tr>
                                            </thead>
                                            <tbody class="list" id="table-simple-pagination-body">
                                            <?php
                                            $query=mysqli_query($con,"select * from tbltasks WHERE is_deleted = 0 AND status = 'In Progress' ORDER BY due_date ASC");
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
                                                <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100 <?php echo ($row['acknowledged'] == 0) ? 'unviewed-task' : ''; ?>">
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
                                                    <td class='align-middle white-space-nowrap product'>
                                                        <?php echo $statusBadge; ?>
                                                        <?php if ($row['acknowledged'] == 0): ?>
                                                            <span class="badge badge rounded-pill badge-subtle-secondary"><span class='ms-1 fas fa-eye-slash' data-fa-transform='shrink-2'></span></span>
                                                        <?php endif; ?>
                                                        <?php if ($is_confirmed != 0): ?>
                                                            <?php echo $confirmation; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap text-900">
                                                        <h6 class="mb-1 fw-semi-bold text-nowrap"><?php echo $row["account"];?></h6>
                                                        <p class="fw-semi-bold mb-0 text-500"><?php echo $row["writer"];?></p>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap email">
                                                        <h6 class="mb-1 fw-semi-bold text-nowrap"><?php echo $timeDiff;?></h6>
                                                        <p class="fw-semi-bold mb-0 text-500"><?php echo date('M j, g:ia', strtotime($row["due_date"]));?></p>
                                                    </td>
                                                    <td class="align-middle amount">
                                                        <h6 class="mb-0"><?php echo number_format($totalprice,2); ?></h6>
                                                        <p class="fs-11 mb-0"><?php echo $statusBadgePay;?></p>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap text-end position-relative">
                                                        <div class="hover-actions bg-100">
                                                            <a class="btn bg-primary-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"  href="view-task?task_id=<?php echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="View task" ><span class="far fa-eye"></span></a>
                                                            <a class="btn bg-success-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"  href="edit-task?task_id=<?php echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Task"><span class="far fa-edit"></span></a>
                                                            <a class="btn bg-warning-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm duplicate-task-btn"
                                                               href="#"
                                                               data-task-id="<?php echo $row['id']; ?>"
                                                               data-task-encoded-id="<?php echo $encodedId; ?>"
                                                               data-task-topic="<?php echo htmlspecialchars($row['topic'], ENT_QUOTES); ?>"
                                                               data-task-subject="<?php echo htmlspecialchars($row['subject'], ENT_QUOTES); ?>"
                                                               data-task-account="<?php echo htmlspecialchars($row['account'], ENT_QUOTES); ?>"
                                                               data-task-writer="<?php echo htmlspecialchars($row['writer'], ENT_QUOTES); ?>"
                                                               data-task-pages="<?php echo $row['pages']; ?>"
                                                               data-task-cpp="<?php echo $row['cpp']; ?>"
                                                               data-task-price="<?php echo number_format($totalprice,2); ?>"
                                                               data-task-duedate="<?php echo date('M j, Y g:ia', strtotime($row['due_date'])); ?>"
                                                               data-task-status="<?php echo $row['status']; ?>"
                                                               data-bs-toggle="tooltip"
                                                               data-bs-placement="top"
                                                               title="Duplicate Task">
                                                                <span class="fas fa-copy"></span>
                                                            </a>
                                                            <a class="btn bg-danger-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm delete-task-btn"
                                                               href="#"
                                                               data-task-id="<?php echo $row['id']; ?>"
                                                               data-task-encoded-id="<?php echo $encodedId; ?>"
                                                               data-task-topic="<?php echo htmlspecialchars($row['topic'], ENT_QUOTES); ?>"
                                                               data-task-subject="<?php echo htmlspecialchars($row['subject'], ENT_QUOTES); ?>"
                                                               data-task-account="<?php echo htmlspecialchars($row['account'], ENT_QUOTES); ?>"
                                                               data-task-writer="<?php echo htmlspecialchars($row['writer'], ENT_QUOTES); ?>"
                                                               data-task-pages="<?php echo $row['pages']; ?>"
                                                               data-task-cpp="<?php echo $row['cpp']; ?>"
                                                               data-task-price="<?php echo number_format($totalprice,2); ?>"
                                                               data-task-duedate="<?php echo date('M j, Y g:ia', strtotime($row['due_date'])); ?>"
                                                               data-task-status="<?php echo $row['status']; ?>"
                                                               data-bs-toggle="tooltip"
                                                               data-bs-placement="top"
                                                               title="Cancel Task">
                                                                <span class="fas fa-trash"></span>
                                                            </a>
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
        <!-- Duplicate Confirmation Modal -->
        <div class="modal fade" id="duplicateTaskModal" tabindex="-1" aria-labelledby="duplicateTaskModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <!-- Header with gradient background -->
                    <div class="modal-header border-0 position-relative" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); padding: 2rem;">
                        <div class="position-absolute" style="top: 0; left: 0; right: 0; bottom: 0; opacity: 0.1; background-image: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23ffffff" fill-opacity="0.4"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
                    <div class="d-flex align-items-center w-100 position-relative">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                <i class="fas fa-copy text-white" style="font-size: 28px;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h4 class="modal-title text-white fw-bold mb-1" id="duplicateTaskModalLabel">Duplicate Task</h4>
                            <p class="text-white text-opacity-75 mb-0 small">Review task details before duplicating</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white position-absolute" style="top: 1.5rem; right: 1.5rem;" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Body with modern card layout -->
                <div class="modal-body p-4">
                    <!-- Info Alert -->
                    <div class="alert alert-info border-0 shadow-sm mb-4" role="alert" style="border-left: 4px solid #0dcaf0 !important;">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-info" style="font-size: 20px;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="alert-heading fw-bold mb-1">Duplicate Task</h6>
                                <p class="mb-0 small">This will create a copy of the task with all details. You can modify the duplicate after creation.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Task Details Card -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header border-bottom py-3">
                            <h6 class="mb-0 fw-bold text-warning">
                                <i class="fas fa-file-alt me-2"></i>Task Information to Duplicate
                            </h6>
                        </div>
                        <div class="card-body p-4">
                            <!-- Task ID & Status Row -->
                            <div class="row mb-3 pb-3 border-bottom">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                                            <i class="fas fa-hashtag text-primary"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block mb-1">Task ID</small>
                                            <strong class="d-block" id="modalDuplicateTaskId"></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-info bg-opacity-10 rounded p-2 me-3">
                                            <i class="fas fa-flag text-info"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block mb-1">Current Status</small>
                                            <span id="modalDuplicateTaskStatus" class="badge"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Task Title -->
                            <div class="mb-3 pb-3 border-bottom">
                                <div class="d-flex align-items-start">
                                    <div class="bg-success bg-opacity-10 rounded p-2 me-3">
                                        <i class="fas fa-heading text-success"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block mb-1">Task Title</small>
                                        <strong class="d-block text-warning fs-6" id="modalDuplicateTaskTopic"></strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Subject -->
                            <div class="mb-3 pb-3 border-bottom">
                                <div class="d-flex align-items-start">
                                    <div class="bg-warning bg-opacity-10 rounded p-2 me-3">
                                        <i class="fas fa-book text-warning"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block mb-1">Subject</small>
                                        <strong class="d-block" id="modalDuplicateTaskSubject"></strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Account & Writer Row -->
                            <div class="row mb-3 pb-3 border-bottom">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <div class="bg-secondary bg-opacity-10 rounded p-2 me-3">
                                            <i class="fas fa-user-circle text-secondary"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted d-block mb-1">Account</small>
                                            <strong class="d-block" id="modalDuplicateTaskAccount"></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                                            <i class="fas fa-user-edit text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted d-block mb-1">Writer</small>
                                            <strong class="d-block" id="modalDuplicateTaskWriter"></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pages & Pricing Row -->
                            <div class="row mb-3 pb-3 border-bottom">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <div class="bg-info bg-opacity-10 rounded p-2 me-3">
                                            <i class="fas fa-file-invoice text-info"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted d-block mb-1">Pages</small>
                                            <strong class="d-block"><span id="modalDuplicateTaskPages"></span> page(s) @ $<span id="modalDuplicateTaskCpp"></span> per page</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <div class="bg-success bg-opacity-10 rounded p-2 me-3">
                                            <i class="fas fa-dollar-sign text-success"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted d-block mb-1">Total Amount</small>
                                            <span class="badge bg-success fs-6 fw-bold">$<span id="modalDuplicateTaskPrice"></span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Due Date -->
                            <div class="mb-0">
                                <div class="d-flex align-items-start">
                                    <div class="bg-danger bg-opacity-10 rounded p-2 me-3">
                                        <i class="fas fa-clock text-danger"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block mb-1">Original Due Date</small>
                                        <strong class="d-block" id="modalDuplicateTaskDueDate"></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer with modern buttons -->
                <div class="modal-footer border-0 px-4 py-3">
                    <button type="button" class="btn btn-light border px-4 py-2" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-warning px-4 py-2 shadow-sm" id="confirmDuplicateBtn">
                        <i class="fas fa-copy me-2"></i>Yes, Duplicate Task
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteTaskModal" tabindex="-1" aria-labelledby="deleteTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <!-- Header with gradient background -->
                <div class="modal-header border-0 position-relative" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 2rem;">
                    <div class="position-absolute" style="top: 0; left: 0; right: 0; bottom: 0; opacity: 0.1; background-image: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23ffffff" fill-opacity="0.4"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
                <div class="d-flex align-items-center w-100 position-relative">
                    <div class="flex-shrink-0 me-3">
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fas fa-exclamation-triangle text-white" style="font-size: 28px;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="modal-title text-white fw-bold mb-1" id="deleteTaskModalLabel">Confirm Task Cancellation</h4>
                        <p class="text-white text-opacity-75 mb-0 small">Review task details before cancelling</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white position-absolute" style="top: 1.5rem; right: 1.5rem;" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Body with modern card layout -->
            <div class="modal-body p-4">
                <!-- Warning Alert -->
                <div class="alert alert-warning border-0 shadow-sm mb-4" role="alert" style="border-left: 4px solid #ffc107 !important;">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-warning" style="font-size: 20px;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="alert-heading fw-bold mb-1">Important Notice</h6>
                            <p class="mb-0 small">This action will cancel the task and send a notification email to the assigned writer.</p>
                        </div>
                    </div>
                </div>

                <!-- Task Details Card -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-file-alt me-2"></i>Task Information
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <!-- Task ID & Status Row -->
                        <div class="row mb-3 pb-3 border-bottom">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                                        <i class="fas fa-hashtag text-primary"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block mb-1">Task ID</small>
                                        <strong class="d-block" id="modalTaskId"></strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="bg-info bg-opacity-10 rounded p-2 me-3">
                                        <i class="fas fa-flag text-info"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block mb-1">Status</small>
                                        <span id="modalTaskStatus" class="badge"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Task Title -->
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-start">
                                <div class="bg-success bg-opacity-10 rounded p-2 me-3">
                                    <i class="fas fa-heading text-success"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <small class="text-muted d-block mb-1">Task Title</small>
                                    <strong class="d-block text-primary fs-6" id="modalTaskTopic"></strong>
                                </div>
                            </div>
                        </div>

                        <!-- Subject -->
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-start">
                                <div class="bg-warning bg-opacity-10 rounded p-2 me-3">
                                    <i class="fas fa-book text-warning"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <small class="text-muted d-block mb-1">Subject</small>
                                    <strong class="d-block" id="modalTaskSubject"></strong>
                                </div>
                            </div>
                        </div>

                        <!-- Account & Writer Row -->
                        <div class="row mb-3 pb-3 border-bottom">
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="bg-secondary bg-opacity-10 rounded p-2 me-3">
                                        <i class="fas fa-user-circle text-secondary"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block mb-1">Account</small>
                                        <strong class="d-block" id="modalTaskAccount"></strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                                        <i class="fas fa-user-edit text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block mb-1">Writer</small>
                                        <strong class="d-block" id="modalTaskWriter"></strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pages & Pricing Row -->
                        <div class="row mb-3 pb-3 border-bottom">
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="bg-info bg-opacity-10 rounded p-2 me-3">
                                        <i class="fas fa-file-invoice text-info"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block mb-1">Pages</small>
                                        <strong class="d-block"><span id="modalTaskPages"></span> page(s) @ $<span id="modalTaskCpp"></span> per page</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="bg-success bg-opacity-10 rounded p-2 me-3">
                                        <i class="fas fa-dollar-sign text-success"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block mb-1">Total Amount</small>
                                        <span class="badge bg-success fs-6 fw-bold">$<span id="modalTaskPrice"></span></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Due Date -->
                        <div class="mb-0">
                            <div class="d-flex align-items-start">
                                <div class="bg-danger bg-opacity-10 rounded p-2 me-3">
                                    <i class="fas fa-clock text-danger"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <small class="text-muted d-block mb-1">Due Date</small>
                                    <strong class="d-block" id="modalTaskDueDate"></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer with modern buttons -->
            <div class="modal-footer border-0 px-4 py-3">
                <button type="button" class="btn btn-light border px-4 py-2" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>No, Keep Task
                </button>
                <button type="button" class="btn btn-danger px-4 py-2 shadow-sm" id="confirmDeleteBtn">
                    <i class="fas fa-trash me-2"></i>Yes, Cancel Task
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get all delete buttons
            const deleteButtons = document.querySelectorAll('.delete-task-btn');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteTaskModal'));
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            let currentTaskEncodedId = null;

            // Get all duplicate buttons
            const duplicateButtons = document.querySelectorAll('.duplicate-task-btn');
            const duplicateModal = new bootstrap.Modal(document.getElementById('duplicateTaskModal'));
            const confirmDuplicateBtn = document.getElementById('confirmDuplicateBtn');
            let currentDuplicateTaskEncodedId = null;

            // Add click event to each delete button
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Get task data from data attributes
                    const taskId = this.dataset.taskId;
                    const taskEncodedId = this.dataset.taskEncodedId;
                    const taskTopic = this.dataset.taskTopic;
                    const taskSubject = this.dataset.taskSubject;
                    const taskAccount = this.dataset.taskAccount;
                    const taskWriter = this.dataset.taskWriter;
                    const taskPages = this.dataset.taskPages;
                    const taskCpp = this.dataset.taskCpp;
                    const taskPrice = this.dataset.taskPrice;
                    const taskDueDate = this.dataset.taskDuedate;
                    const taskStatus = this.dataset.taskStatus;

                    // Store the encoded ID for confirmation
                    currentTaskEncodedId = taskEncodedId;

                    // Populate modal with task details
                    document.getElementById('modalTaskId').textContent = taskId;
                    document.getElementById('modalTaskTopic').textContent = taskTopic;
                    document.getElementById('modalTaskSubject').textContent = taskSubject;
                    document.getElementById('modalTaskAccount').textContent = taskAccount;
                    document.getElementById('modalTaskWriter').textContent = taskWriter;
                    document.getElementById('modalTaskPages').textContent = taskPages;
                    document.getElementById('modalTaskCpp').textContent = taskCpp;
                    document.getElementById('modalTaskPrice').textContent = taskPrice;
                    document.getElementById('modalTaskDueDate').textContent = taskDueDate;

                    // Set status badge with appropriate color
                    const statusBadge = document.getElementById('modalTaskStatus');
                    statusBadge.textContent = taskStatus;
                    statusBadge.className = 'badge'; // Reset classes

                    switch(taskStatus) {
                        case 'Active':
                            statusBadge.classList.add('bg-primary');
                            break;
                        case 'In Progress':
                            statusBadge.classList.add('bg-warning');
                            break;
                        case 'Revision':
                            statusBadge.classList.add('bg-danger');
                            break;
                        case 'Unconfirmed':
                            statusBadge.classList.add('bg-secondary');
                            break;
                        case 'Submitted':
                            statusBadge.classList.add('bg-info');
                            break;
                        case 'Completed':
                            statusBadge.classList.add('bg-success');
                            break;
                        default:
                            statusBadge.classList.add('bg-secondary');
                    }

                    // Show the modal
                    deleteModal.show();
                });
            });

            // Handle confirm delete button
            confirmDeleteBtn.addEventListener('click', function() {
                if (currentTaskEncodedId) {
                    // Redirect to delete URL
                    window.location.href = 'tasks-in-progress?del=' + currentTaskEncodedId;
                }
            });

            // Add click event to each duplicate button
            duplicateButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Get task data from data attributes
                    const taskId = this.dataset.taskId;
                    const taskEncodedId = this.dataset.taskEncodedId;
                    const taskTopic = this.dataset.taskTopic;
                    const taskSubject = this.dataset.taskSubject;
                    const taskAccount = this.dataset.taskAccount;
                    const taskWriter = this.dataset.taskWriter;
                    const taskPages = this.dataset.taskPages;
                    const taskCpp = this.dataset.taskCpp;
                    const taskPrice = this.dataset.taskPrice;
                    const taskDueDate = this.dataset.taskDuedate;
                    const taskStatus = this.dataset.taskStatus;

                    // Store the encoded ID for confirmation
                    currentDuplicateTaskEncodedId = taskEncodedId;

                    // Populate modal with task details
                    document.getElementById('modalDuplicateTaskId').textContent = taskId;
                    document.getElementById('modalDuplicateTaskTopic').textContent = taskTopic;
                    document.getElementById('modalDuplicateTaskSubject').textContent = taskSubject;
                    document.getElementById('modalDuplicateTaskAccount').textContent = taskAccount;
                    document.getElementById('modalDuplicateTaskWriter').textContent = taskWriter;
                    document.getElementById('modalDuplicateTaskPages').textContent = taskPages;
                    document.getElementById('modalDuplicateTaskCpp').textContent = taskCpp;
                    document.getElementById('modalDuplicateTaskPrice').textContent = taskPrice;
                    document.getElementById('modalDuplicateTaskDueDate').textContent = taskDueDate;

                    // Set status badge with appropriate color
                    const statusBadge = document.getElementById('modalDuplicateTaskStatus');
                    statusBadge.textContent = taskStatus;
                    statusBadge.className = 'badge'; // Reset classes

                    switch(taskStatus) {
                        case 'Active':
                            statusBadge.classList.add('bg-primary');
                            break;
                        case 'In Progress':
                            statusBadge.classList.add('bg-warning');
                            break;
                        case 'Revision':
                            statusBadge.classList.add('bg-danger');
                            break;
                        case 'Unconfirmed':
                            statusBadge.classList.add('bg-secondary');
                            break;
                        case 'Submitted':
                            statusBadge.classList.add('bg-info');
                            break;
                        case 'Completed':
                            statusBadge.classList.add('bg-success');
                            break;
                        default:
                            statusBadge.classList.add('bg-secondary');
                    }

                    // Show the modal
                    duplicateModal.show();
                });
            });

            // Handle confirm duplicate button
            confirmDuplicateBtn.addEventListener('click', function() {
                if (currentDuplicateTaskEncodedId) {
                    // Redirect to duplicate URL
                    window.location.href = 'duplicate-task?task_id=' + currentDuplicateTaskEncodedId;
                }
            });
        });
    </script>

<?php
include "footer.php";
?>