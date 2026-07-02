<?php include "head.php";?>
    <title>iTasker | Settled Overdrafts</title>
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
                    <h4 class="mb-0 text-primary fw-bold">Settled <span class="text-info fw-medium"> Overdrafts</span></h4>
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
<!--                                                    <button type="button" class="btn btn-falcon-info btn-sm ms-2" onclick="submitForm('mark-tasks-completed.php')">Mark as Completed</button>-->
<!--                                                    <button type="button" class="btn btn-falcon-success btn-sm ms-2" onclick="submitForm('mark-tasks-paid.php')">Mark as Paid</button>-->
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center" id="table-simple-pagination-replace-element">
                                                <a class="btn btn-falcon-info btn-sm mx-2" href="overdraft" data-bs-toggle="tooltip" data-bs-placement="top" title="Create Overdraft" type="button"><span class="fas fa-plus" data-fa-transform="shrink-3 down-2"></span><span class="d-none d-sm-inline-block ms-1">New Overdraft</span></a>
<!--                                                <button class="btn btn-falcon-default btn-sm mx-2" type="button"><span class="fas fa-filter" data-fa-transform="shrink-3 down-2"></span><span class="d-none d-sm-inline-block ms-1">Filter</span></button>-->
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
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">OD Id</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Writer</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Amount</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Date</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Settled On</th>
<!--                                            <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>-->
                                        </tr>
                                        </thead>
                                        <tbody class="list" id="table-simple-pagination-body">
                                        <?php
                                            $query=mysqli_query($con,"select * from tbloverdrafts WHERE is_settled = 1 ORDER BY id DESC");
                                            $cnt=1;
                                            while($row=mysqli_fetch_array($query))
                                            {
                                                $encodedId = base64_encode($row["id"]); // Encode the id
                                    ?>
                                        <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                                            <td class="align-middle" style="width: 28px;">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" id="simple-pagination-item-<?php echo $cnt; ?>" data-bulk-select-row="data-bulk-select-row" value="<?php echo $row['id']; ?>" name="taskIds[]"/>
                                                </div>
                                            </td>
                                            <td class="align-middle white-space-nowrap"><?php echo $row["id"];?></td>
                                            <td class="align-middle white-space-nowrap fw-semi-bold text-900"><?php echo $row["writer"];?></td>
                                            <td class="align-middle white-space-nowrap email text-900"><?php echo $row["amount"];?></td>
                                            <td class="align-middle white-space-nowrap text-900"><?php echo date("jS M, Y h:i A", strtotime($row['od_date'])); ?></td>
                                            <td class="align-middle white-space-nowrap text-900"><?php echo date("jS M, Y", strtotime($row['date_settled'])); ?></td>
<!--                                            <td class="align-middle white-space-nowrap text-end position-relative">-->
<!--                                                <div class="hover-actions bg-100">-->
<!--                                                    <a class="btn bg-primary-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm" href="view-task?task_id=--><?php //echo $encodedId; ?><!--" data-bs-toggle="tooltip" data-bs-placement="top" title="View task" ><span class="far fa-eye"></span></a>-->
<!--                                                </div>-->
<!--                                                <div class="dropdown font-sans-serif btn-reveal-trigger">-->
<!--                                                    <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" id="crm-recent-leads-4" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"><span class="fas fa-chevron-left fs-11"></span></button>-->
<!--                                                </div>-->
<!--                                            </td>-->
                                        </tr>
                                        <!--<tr class="btn-reveal-trigger">
                                            <td class="align-middle" style="width: 28px;">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" id="simple-pagination-item-1" data-bulk-select-row="data-bulk-select-row" />
                                                </div>
                                            </td>
                                            <td class="align-middle white-space-nowrap fw-semi-bold name"><a href="../../app/e-commerce/customer-details.html">Homer</a></td>
                                            <td class="align-middle white-space-nowrap email">sylvia@mail.ru</td>
                                            <td class="align-middle white-space-nowrap product">Bose SoundSport Wireless Headphones</td>
                                            <td class="align-middle text-center fs-9 white-space-nowrap payment"><span class="badge badge rounded-pill badge-subtle-success">Success<span class="ms-1 fas fa-check" data-fa-transform="shrink-2"></span></span>
                                            </td>
                                            <td class="align-middle amount">$634</td>
                                            <td class="align-middle white-space-nowrap text-end">
                                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                                    <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-simple-pagination-table-item-1" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-simple-pagination-table-item-1"><a class="dropdown-item" href="#!">View</a><a class="dropdown-item" href="#!">Edit</a><a class="dropdown-item" href="#!">Refund</a>
                                                        <div class="dropdown-divider"></div><a class="dropdown-item text-warning" href="#!">Archive</a><a class="dropdown-item text-danger" href="#!">Delete</a>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle white-space-nowrap text-end">
                                                <div class="dropstart font-sans-serif position-static d-inline-block">
                                                    <button class="btn btn-link text-600 btn-sm dropdown-toggle btn-reveal float-end" type="button" id="dropdown-simple-pagination-table-item-0" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-10"></span></button>
                                                    <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="dropdown-simple-pagination-table-item-0">
                                                        <a class="dropdown-item text-info"  href="view-task?task_id=<?php echo $encodedId; ?>"><span class="fas fa-eye" data-fa-transform="shrink-2"></span> View</a>
                                                        <a class="dropdown-item text-success"  href="edit-task?task_id=<?php echo $encodedId; ?>"><span class="bi bi-pen" data-fa-transform="shrink-2"></span> Edit</a>
                                                        <a class="dropdown-item text-warning"  href="duplicate-task?task_id=<?php echo $encodedId; ?>" ><span class="fas fa-copy" data-fa-transform="shrink-2"></span> Duplicate</a>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger" href="all-tasks?del=<?php echo $encodedId; ?>" onclick="return confirm('Do you really want to cancel task?');"><span class="fas fa-trash" data-fa-transform="shrink-2"></span> Cancel</>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>-->
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