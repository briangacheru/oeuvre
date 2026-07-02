<?php
include "head.php";

$msg = "";
?>
    <title>Cancelled Tasks | iTasker</title>
<?php include "navi.php";?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Cancelled <span class="text-info fw-medium"> Tasks</span></h4>
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
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap"><span class='ms-3'>Task #</span></th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Topic</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Status</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Amount</th>
                                            <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                                        </tr>
                                        </thead>
                                        <tbody class="list" id="table-simple-pagination-body">
                                        <?php
                                        $query=mysqli_query($con,"select * from tbltasks WHERE is_deleted = 1 AND email ='$aid' ORDER BY id DESC");
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
                                                <td class="align-middle white-space-nowrap fw-semi-bold text-900"><span class='ms-3'><?php echo $row['id']; ?></span></td>
                                                <td>
                                                <div class="d-flex align-items-center position-relative">
                                                    <div class="flex-1">
                                                        <h6 class="mb-1 fw-semi-bold text-nowrap"><a class="text-900 stretched-link"  href="view-task?task_id=<?php echo $encodedId; ?>"><?php echo $row["topic"];?></a></h6>
                                                        <p class="fw-semi-bold mb-0 text-500"><?php echo $row["pages"];?> Page(s) | CPP: <?php echo $row["cpp"];?></p>
                                                    </div>
                                                </div>
                                            </td>
                                                <td class="align-middle white-space-nowrap product"><?php echo $statusBadge;?>
                                                    <?php if ($is_confirmed == 1): ?>
                                                        <?php echo $confirmation;?>
                                                    <?php endif; ?>
                                                    | <?php echo $statusBadgePay;?>
                                                </td>
                                                <td class="align-middle text-end amount text-900"><?php echo number_format($totalprice,2); ?></td>
                                                <td class="align-middle white-space-nowrap text-end position-relative">
                                                    <div class="hover-actions bg-100">
                                                        <a class="btn bg-primary-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm" target="_blank" href="view-task?task_id=<?php echo $encodedId; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="View task" ><span class="far fa-eye"></span></a>
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