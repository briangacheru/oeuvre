<?php
require_once __DIR__ . '/../env.php'; include "head.php";?>
    <title>iTasker | Tasks in Revision</title>
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
                    <h4 class="mb-0 text-primary fw-bold">In Revision <span class="text-info fw-medium"> Tasks</span></h4>
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
                                            $query=mysqli_query($con,"select * from tbltasks WHERE is_deleted = 0 AND status = 'In Revision' ORDER BY id DESC");
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
                                                    case 'In Revision':
                                                        $statusBadge = '<span class="badge badge rounded-pill badge-subtle-warning">In Revision<span class="ms-1 fas fa-flag" data-fa-transform="shrink-2"></span></span>';
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
                                                        <h6 class="mb-1 fw-semi-bold text-nowrap"><a class="text-900 stretched-link"  href="view-task?task_id=<?php echo $encodedId; ?>"><?php echo htmlspecialchars($row["topic"], ENT_QUOTES, 'UTF-8'); ?></a></h6>
                                                        <p class="fw-semi-bold mb-0 text-500"><?php echo $row["pages"];?> Page(s) | CPP: <?php echo $row["cpp"];?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle white-space-nowrap product">
                                                <?php echo $statusBadge; ?>
                                                <?php if ($row['acknowledged'] == 0): ?>
                                                    <span class="badge badge rounded-pill badge-subtle-secondary"><span class='ms-1 fas fa-eye-slash' data-fa-transform='shrink-2'></span></span>
                                                <?php endif; ?>
                                                <?php if ($is_confirmed != 0): ?>
                                                    <?php echo $confirmation; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle white-space-nowrap email">
                                                <h6 class="mb-1 fw-semi-bold text-nowrap"><?php echo htmlspecialchars($row["account"], ENT_QUOTES, 'UTF-8'); ?></h6>
                                                <p class="fw-semi-bold mb-0 text-500"><?php echo $row["writer"];?></p>
                                                </td>
                                            <td class="align-middle white-space-nowrap email"><?php echo $timeDiff;?></td>
                                            <td class="align-middle amount">
                                                <h6 class="mb-0"><?php echo number_format($totalprice,2); ?></h6>
                                                <p class="fs-11 mb-0"><?php echo $statusBadgePay;?></p>
                                            </td>
                                            <td class="align-middle white-space-nowrap text-end position-relative">
                                                <div class="hover-actions bg-100">
                                                    <a class="btn bg-primary-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"  href="view-task?task_id=<?php echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="View task" ><span class="far fa-eye"></span></a>
                                                    <a class="btn bg-success-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"  href="edit-task?task_id=<?php echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Task"><span class="far fa-edit"></span></a>
                                                    <a class="btn bg-warning-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"  href="duplicate-task?task_id=<?php echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Duplicate Task"><span class="fas fa-copy"></span></a>
                                                    <a class="btn bg-danger-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm"  href="tasks-in-progress?del=<?php echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Cancel Task" onclick="return confirm('Do you really want to cancel task?');"><span class="fas fa-trash"></span></a>
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