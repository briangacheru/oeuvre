<?php
require_once __DIR__ . '/../env.php';
include "head.php";?>
    <title>iTasker | Draft Tasks</title>
<?php include "navi.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../email-template.php';

$status = "OK";
$msg = "";

if (isset($_GET['del'])) {
    $encodedId = $_GET['del'];
    $cmpid = decode_task_id($encodedId);
    $cancellationReason = trim($_GET['reason'] ?? '') ?: 'No reason provided';

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
            $escapedReason = mysqli_real_escape_string($con, $cancellationReason);
            $query = mysqli_query($con, "UPDATE tbltasks SET is_deleted = 1, status = 'Cancelled', cancellation_reason = '$escapedReason', cancelled_at = NOW() WHERE id='$cmpid'");

            if ($query) {

                // Fetch task details and writer's email
                $taskQuery = mysqli_query($con, "SELECT * FROM tbltasks WHERE id='$cmpid'");
                $taskData = mysqli_fetch_assoc($taskQuery);
                $writerEmail = $taskData['email'];
                $writerName = $taskData['writer'];
                $taskTopic = $taskData['topic'];
                $taskSubject = $taskData['subject'];
                $taskDueDate = $taskData['due_date'];
                $taskPages = $taskData['pages'];
                $taskCpp = $taskData['cpp'];
                $taskDescription = $taskData['description'];
                $taskAccount = $taskData['account'];

                // Initialize PHPMailer
                $mail = new PHPMailer(true);

                try {
                    // Server settings
                    $mail->SMTPDebug = 0;
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
                    $mail->addBCC(env('ADMIN_EMAIL'), 'iTasker Admin');

                    // Content - styled the same as the task-assignment ("acknowledgement")
                    // email, but with red accents to signal a cancellation instead of a new task.
                    $mail->isHTML(true);
                    $mail->Subject = 'Task ID: ' . $cmpid . ' - ' . $taskTopic . ' - [ ' . $taskAccount . ' ] ';

                    $taskDetailsUrl = 'https://web.monkbrian.com/view-task?task_id=' . $encodedId;

                    $emailBody = "
                                <p>Hello <span style='font-weight:bold;color:#dc3545;'>$writerName</span>,</p>
                                <p>The following task has been cancelled. Please do not go ahead with it:</p>
                                <p><strong>Topic:</strong> <span style='font-weight:bold;color:#dc3545;'>$taskTopic</span></p>
                                <p><strong>Subject:</strong> $taskSubject</p>
                                <p><strong>Due Date:</strong> <span style='font-weight:bold;color:#dc3545;'>$taskDueDate</span></p>
                                <p><strong>Pages:</strong> $taskPages</p>
                                <p><strong>Price per Page:</strong> Ksh $taskCpp</p>
                                <p><strong>Reason for Cancellation:</strong> <span style='font-weight:bold;color:#dc3545;'>$cancellationReason</span></p>";

                    $mail->Body = render_email_html(
                        "Task ID $cmpid has been CANCELLED",
                        $emailBody,
                        'View Task Details',
                        $taskDetailsUrl,
                        "For any questions, contact <a href='mailto:bryo4419@gmail.com'>bryo4419@gmail.com</a>"
                    );

                    $mail->AltBody = "Task Cancelled\n\n
                        Hello $writerName,\n
                        The following task has been cancelled. Please do not go ahead with it:\n
                        Topic: $taskTopic\n
                        Subject: $taskSubject\n
                        Due Date: $taskDueDate\n
                        Pages: $taskPages\n
                        Price per Page: Ksh $taskCpp\n
                        Reason for Cancellation: $cancellationReason\n
                        View Task Details: $taskDetailsUrl\n\n
                        For any questions, contact bryo4419@gmail.com";

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
                $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-octagon"></i> Error cancelling task record.
                                     <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
            }
        }
    } else {
        $_SESSION['alert'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-octagon"></i> Invalid or missing ID.
                             <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
    }

    header('Location: draft-tasks');
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
                    <h4 class="mb-0 text-primary fw-bold">Draft <span class="text-info fw-medium"> Tasks</span></h4>
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
<?= csrf_field() ?>
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
                                            <th class="text-900 no-sort white-space-nowrap">
                                                <div class="form-check mb-0 d-flex align-items-center">
                                                    <input class="form-check-input" id="checkbox-select-all" type="checkbox" onclick="selectAllTasks(this)" data-bulk-select='{"body":"table-simple-pagination-body","actions":"table-simple-pagination-actions","replacedElement":"table-simple-pagination-replace-element"}' />
                                                </div>
                                            </th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Task ID</th>
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
                                            $taskCommentCounts = [];
                                            $commentCountsResult = mysqli_query($con, "SELECT task_id, COUNT(*) AS cnt FROM tbl_task_comments GROUP BY task_id");
                                            if ($commentCountsResult) {
                                                while ($ccRow = mysqli_fetch_assoc($commentCountsResult)) {
                                                    $taskCommentCounts[$ccRow['task_id']] = (int) $ccRow['cnt'];
                                                }
                                            }
                                            $query=mysqli_query($con,"select * from tbltasks WHERE is_deleted = 0 AND (writer = 'Draft' OR status = 'Draft') ORDER BY id DESC");
                                            $cnt=1;
                                            while($row=mysqli_fetch_array($query))
                                            {
                                                $totalprice=$row["cpp"]*$row["pages"];
                                                $encodedId = encode_task_id($row["id"]); // Encode the id

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

                                                $is_confirmed = $row['is_confirmed']; // Assuming 'is_confirmed' is the column name in your database

                                                if ($is_confirmed == 0) {
                                                    $confirmationClass = 'badge-subtle-light';
                                                    $confirmationText = 'Confirmed';
                                                } elseif ($is_confirmed == 1) {
                                                    $confirmationClass = 'badge-subtle-primary';
                                                    $confirmationText = 'Unconfirmed';
                                                } elseif ($is_confirmed == 2) {
                                                    $confirmationClass = 'badge-subtle-danger';
                                                    $confirmationText = 'Declined';
                                                }
                                                $confirmation = "<span class='badge rounded-pill $confirmationClass'>$confirmationText</span>";

                                                $publish = $row['publish'];
                                                $publishClass = 'badge-subtle-success';
                                                $publishText = 'Published';

                                                if ($publish == 0 || $publish == false || empty($publish)) {
                                                    $publishClass = 'badge-subtle-secondary';
                                                    $publishText = 'Unpublished';
                                                }
                                                $published = "<span class='badge rounded-pill $publishClass'>$publishText</span>";
                                                ?>
                                        <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                                            <td class="align-middle" style="width: 28px;">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" id="simple-pagination-item-<?php echo $cnt; ?>" data-bulk-select-row="data-bulk-select-row" value="<?php echo $row['id']; ?>" name="taskIds[]"/>
                                                </div>
                                            </td>
                                            <td class="align-middle white-space-nowrap fw-semi-bold text-900">
                                                <?php echo $row["id"];?>
                                                <?php if (!empty($taskCommentCounts[$row['id']])): ?>
                                                    <a href="view-task?task_id=<?php echo $encodedId; ?>#discussionBody" class="ms-1 text-info" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo $taskCommentCounts[$row['id']]; ?> message<?php echo $taskCommentCounts[$row['id']] > 1 ? 's' : ''; ?> in discussion"><i class="fas fa-comment-dots"></i></a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center position-relative">
                                                    <div class="flex-1">
                                                        <h6 class="mb-1 fw-semi-bold text-nowrap"><a class="text-900 stretched-link"  href="view-task?task_id=<?php echo $encodedId; ?>"><?php echo htmlspecialchars($row["topic"], ENT_QUOTES, 'UTF-8'); ?></a></h6>
                                                        <p class="fw-semi-bold mb-0 text-500"><?php echo $row["pages"];?> Page(s) | CPP: <?php echo $row["cpp"];?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle white-space-nowrap product"><?php echo $statusBadge;?>
                                            <?php if ($is_confirmed != 0): ?>
                                                <?php echo $confirmation;?>
                                            <?php endif; ?>
                                                <?php if ($publish != 1): ?>
                                                    <?php echo $published;?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle white-space-nowrap text-900">
                                                <h6 class="mb-1 fw-semi-bold text-nowrap"><?php echo htmlspecialchars($row["account"], ENT_QUOTES, 'UTF-8'); ?></h6>
                                                <p class="fw-semi-bold mb-0 text-500"><?php echo $row["writer"];?></p>
                                                </td>
                                            <td class="align-middle white-space-nowrap text-900"><?php echo htmlspecialchars($row["subject"], ENT_QUOTES, 'UTF-8'); ?></td>
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
                                            <strong class="d-block"><span id="modalDuplicateTaskPages"></span> page(s) @ Ksh <span id="modalDuplicateTaskCpp"></span> per page</strong>
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
                                            <span class="badge bg-success fw-bold">Ksh <span id="modalDuplicateTaskPrice"></span></span>
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

    <!-- Cancel Confirmation Modal -->
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
                                        <strong class="d-block"><span id="modalTaskPages"></span> page(s) @ Ksh <span id="modalTaskCpp"></span> per page</strong>
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
                                        <span class="badge bg-success fs-6 fw-bold">Ksh <span id="modalTaskPrice"></span></span>
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

                <!-- Cancellation Reason -->
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-header border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-danger">
                            <i class="fas fa-comment-dots me-2"></i>Reason for Cancellation
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <select class="form-select mb-3" id="cancelReasonSelect">
                            <option value="" selected disabled>Select a reason...</option>
                            <option value="Client asked the task to be cancelled">Client asked the task to be cancelled</option>
                            <option value="The task timeline has depleted">The task timeline has depleted</option>
                            <option value="other">Other (please specify)</option>
                        </select>
                        <textarea class="form-control d-none" id="cancelReasonCustom" rows="3" placeholder="Please specify the reason for cancelling this task..."></textarea>
                        <div class="invalid-feedback d-block d-none" id="cancelReasonError">Please provide a reason for cancelling this task.</div>
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
            const duplicateButtons = document.querySelectorAll('.duplicate-task-btn');
            const duplicateModal = new bootstrap.Modal(document.getElementById('duplicateTaskModal'));
            const confirmDuplicateBtn = document.getElementById('confirmDuplicateBtn');
            let currentDuplicateTaskEncodedId = null;

            duplicateButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

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

                    currentDuplicateTaskEncodedId = taskEncodedId;

                    document.getElementById('modalDuplicateTaskId').textContent = taskId;
                    document.getElementById('modalDuplicateTaskTopic').textContent = taskTopic;
                    document.getElementById('modalDuplicateTaskSubject').textContent = taskSubject;
                    document.getElementById('modalDuplicateTaskAccount').textContent = taskAccount;
                    document.getElementById('modalDuplicateTaskWriter').textContent = taskWriter;
                    document.getElementById('modalDuplicateTaskPages').textContent = taskPages;
                    document.getElementById('modalDuplicateTaskCpp').textContent = taskCpp;
                    document.getElementById('modalDuplicateTaskPrice').textContent = taskPrice;
                    document.getElementById('modalDuplicateTaskDueDate').textContent = taskDueDate;

                    const statusBadge = document.getElementById('modalDuplicateTaskStatus');
                    statusBadge.textContent = taskStatus;
                    statusBadge.className = 'badge';

                    switch (taskStatus) {
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

                    duplicateModal.show();
                });
            });

            confirmDuplicateBtn.addEventListener('click', function() {
                if (currentDuplicateTaskEncodedId) {
                    window.location.href = 'duplicate-task?task_id=' + currentDuplicateTaskEncodedId;
                }
            });

            // Cancel Task modal
            const deleteButtons = document.querySelectorAll('.delete-task-btn');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteTaskModal'));
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            const cancelReasonSelect = document.getElementById('cancelReasonSelect');
            const cancelReasonCustom = document.getElementById('cancelReasonCustom');
            const cancelReasonError = document.getElementById('cancelReasonError');
            let currentTaskEncodedId = null;

            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

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

                    currentTaskEncodedId = taskEncodedId;

                    document.getElementById('modalTaskId').textContent = taskId;
                    document.getElementById('modalTaskTopic').textContent = taskTopic;
                    document.getElementById('modalTaskSubject').textContent = taskSubject;
                    document.getElementById('modalTaskAccount').textContent = taskAccount;
                    document.getElementById('modalTaskWriter').textContent = taskWriter;
                    document.getElementById('modalTaskPages').textContent = taskPages;
                    document.getElementById('modalTaskCpp').textContent = taskCpp;
                    document.getElementById('modalTaskPrice').textContent = taskPrice;
                    document.getElementById('modalTaskDueDate').textContent = taskDueDate;

                    const deleteStatusBadge = document.getElementById('modalTaskStatus');
                    deleteStatusBadge.textContent = taskStatus;
                    deleteStatusBadge.className = 'badge';

                    switch (taskStatus) {
                        case 'Active':
                            deleteStatusBadge.classList.add('bg-primary');
                            break;
                        case 'In Progress':
                            deleteStatusBadge.classList.add('bg-warning');
                            break;
                        case 'Revision':
                            deleteStatusBadge.classList.add('bg-danger');
                            break;
                        case 'Unconfirmed':
                            deleteStatusBadge.classList.add('bg-secondary');
                            break;
                        case 'Submitted':
                            deleteStatusBadge.classList.add('bg-info');
                            break;
                        case 'Completed':
                            deleteStatusBadge.classList.add('bg-success');
                            break;
                        default:
                            deleteStatusBadge.classList.add('bg-secondary');
                    }

                    // Reset the cancellation reason fields for this fresh confirmation
                    cancelReasonSelect.value = '';
                    cancelReasonCustom.value = '';
                    cancelReasonCustom.classList.add('d-none');
                    cancelReasonError.classList.add('d-none');

                    deleteModal.show();
                });
            });

            cancelReasonSelect.addEventListener('change', function() {
                cancelReasonCustom.classList.toggle('d-none', this.value !== 'other');
                cancelReasonError.classList.add('d-none');
            });

            confirmDeleteBtn.addEventListener('click', function() {
                if (!currentTaskEncodedId) return;

                const selectedReason = cancelReasonSelect.value;
                const reason = selectedReason === 'other' ? cancelReasonCustom.value.trim() : selectedReason;

                if (!reason) {
                    cancelReasonError.classList.remove('d-none');
                    return;
                }

                window.location.href = 'draft-tasks?del=' + currentTaskEncodedId + '&reason=' + encodeURIComponent(reason);
            });
        });
    </script>

<?php
include "footer.php";
?>