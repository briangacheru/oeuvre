<?php include "head.php";?>
    <title>Savings Goals</title>
<?php include "navi.php";
$status = "OK";
$msg = "";
?>
    <div class="card shadow-none border mb-3">
        <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
            <h3 class="mb-0 text-success bg">Active Saving Goals</h3>
            <div>
                <button class="btn btn-outline-success btn-sm me-2" type="button" data-bs-toggle="modal" data-bs-target="#addGoalModal">
                    <span class="fas fa-plus fs-11"></span>
                    <span class="d-none d-sm-inline-block ms-1 align-middle" data-bs-toggle="tooltip" data-bs-placement="top" title="Add a new savings goal">Add Goal</span>
                </button>
            </div>
        </div>
    </div>

<?php
if (isset($_SESSION['alert'])) {
    echo $_SESSION['alert'];
    unset($_SESSION['alert']);
    //echo '<meta http-equiv="refresh" content="10;url=' . htmlspecialchars($_SERVER['PHP_SELF']) . '">';
}
?>

    <div class="row g-3 mb-3">
        <div class="col-xxl-12">
            <div class="row g-3 h-100">
                <?php
                // Fetch savings goals and their current savings
                $sql = "
                    SELECT g.goalID, g.goalName, g.goalAmount, g.goalStatus, g.goalDescription, g.is_achieved, g.goalPeriod, g.achieved_on, COALESCE(SUM(b.amount), 0) AS currentSavings
                    FROM tblsavingsgoals g LEFT JOIN tblbudget b  ON g.goalName = b.description WHERE g.is_achieved = 0 AND g.is_deleted = 0
                    GROUP BY g.goalID, g.goalName, g.goalAmount, g.goalStatus, g.goalDescription, g.is_achieved, g.goalPeriod, g.achieved_on";
                $result = $con->query($sql);

                // Check if data exists
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        // Calculate progress (assuming you have a field for current savings)
                        $goalID = $row['goalID'];
                        $goalName = $row['goalName'];
                        $goalAmount = $row['goalAmount'];
                        $goalStatus = $row['goalStatus'];
                        $goalDate = $row['goalPeriod'];
                        $achieved_on = $row['achieved_on'];
                        $goalDescription = $row['goalDescription'];
                        $currentSavings = $row['currentSavings'];
                        $progress = ($currentSavings / $goalAmount) * 100;
                        $goalBalance = ($goalAmount - $currentSavings);

                        $currentDate = new DateTime();
                        $goalDateTime = new DateTime($goalDate);
                        $interval = $currentDate->diff($goalDateTime);

                        $isPast = $goalDateTime < $currentDate;

                        $goalPeriodParts = [];
                        if ($interval->m > 0) {
                            $goalPeriodParts[] = ($isPast ? '-' : '') . $interval->m . " month" . ($interval->m > 1 ? "s" : "");
                        }
                        if ($interval->d >= 7) {
                            $weeks = intdiv($interval->d, 7);
                            $goalPeriodParts[] = ($isPast ? '-' : '') . $weeks . " week" . ($weeks > 1 ? "s" : "");
                            $interval->d %= 7;
                        }
                        if ($interval->d > 0) {
                            $goalPeriodParts[] = ($isPast ? '-' : '') . $interval->d . " day" . ($interval->d > 1 ? "s" : "");
                        }

                        if (empty($goalPeriodParts)) {
                            $goalPeriod = "0 days";
                        } else {
                            $goalPeriod = implode(" ", $goalPeriodParts);
                        }
                        $style = $isPast ? 'badge rounded-pill badge-subtle-danger' : 'badge rounded-pill badge-subtle-success';

                        echo '<div class="col-12">';
                        echo '<div class="card font-sans-serif h-100">';
                        echo '<div class="card-header pb-0">';
                        echo "<h5 class='mb-2'>$goalName</h5>";
                        echo '</div>';
                        echo '<div class="card-body pt-0">';
                        echo '<div class="row align-items-end h-100 mb-n1">';
                        echo '<div class="col-6 col-sm-5 pe-md-0 pe-lg-3">';
                        echo '<div class="row g-0">';
                        echo '<div class="col-7"><h6 class="text-600">Target:</h6></div>';
                        echo "<div class='col-5'><h6 class='text-800'>Ksh " . number_format($goalAmount, 0) . "</h6></div>";
                        echo '</div>';
                        echo '<div class="row g-0 mb-2">';
                        echo '<div class="col-7"><h6 class="mb-0 text-600">Reached:</h6></div>';
                        echo "<div class='col-5'><h6 class='mb-0 text-800'>Ksh " . number_format($currentSavings, 0) . "</h6></div>";
                        echo '</div>';
                        echo '<div class="row g-0 mb-2">';
                        echo '<div class="col-7"><h6 class="mb-0 text-600">Balance:</h6></div>';
                        echo "<div class='col-5'><h6 class='mb-0 text-800'>Ksh " . number_format($goalBalance, 0) . "</h6></div>";
                        echo '</div>';
                        echo '<div class="row g-0">';
                        echo '<div class="col-7"><h6 class="mb-0 text-600">T-minus:</h6></div>';
                        echo "<div class='col-5'><h6 class='mb-0 $style'>$goalPeriod</h6></div>";
                        echo '</div>';
                        echo '</div>';
                        echo '<div class="col-6 col-sm-7">';
                        echo '<div class="row g-0 mb-2">';
                        echo '<div class="col-5"><h6 class="mb-0 text-600">Description:</h6></div>';
                        echo "<div class='col-7'><h6 class='mb-0 text-800'>$goalDescription</h6></div>";
                        echo '</div>';
                        echo '<div class="row g-0 mb-2">';
                        echo '<div class="col-5"><h6 class="mb-0 text-600">Progress:</h6></div>';
                        echo "<div class='col-7'><h6 class='mb-0 text-800'>" . round($progress, 2) . "%</h6></div>";
                        echo '</div>';
                        echo '<div class="progress">';
                        echo '<div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: ' . round($progress, 2) . '%;" aria-valuenow="' . round($progress, 2) . '" aria-valuemin="0" aria-valuemax="100">' . round($progress, 2) . '%</div>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<h6 class="text-center mb-0 text-600">No active savings goals yet.</h6>';
                }
                ?>

            </div>
        </div>

    </div>
    <div class="row g-3 mb-3">
        <div class="col-xxl-12">
            <div class="card overflow-hidden">
                <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                    <h5 class="mb-0 text-success bg">All Saving Goals List</h5>
                </div>
                <div class="card-body px-0 pt-0" id="transaction-table">
                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10"  data-datatables="data-datatables">
                        <thead class="bg-200">
                        <tr>
                            <th class="text-900 no-sort white-space-nowrap">
                                <div class="form-check mb-0 d-flex align-items-center">
                                    <input class="form-check-input" id="checkbox-select-all" type="checkbox" onclick="selectAllTasks(this)" data-bulk-select='{"body":"table-simple-pagination-body","actions":"table-simple-pagination-actions","replacedElement":"table-simple-pagination-replace-element"}' />
                                </div>
                            </th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Goal</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Amount (Ksh)</th>
                            <th class="text-900 sort pe-1 text-center white-space-nowrap">Status</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Progress</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Due date</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Achieved on</th>
                            <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                        </tr>
                        </thead>
                        <tbody class="list" id="table-simple-pagination-body">
                        <?php
                        $query = mysqli_query($con,"SELECT g.goalID, g.goalName, g.goalDescription, g.goalStatus, g.goalPeriod, g.created_at, g.goalAmount, g.is_deleted, g.achieved_on, g.is_achieved, 
                        COALESCE(SUM(b.amount), 0) AS currentSavings FROM tblsavingsgoals g LEFT JOIN tblbudget b ON g.goalName = b.description 
                        WHERE g.is_deleted = 0 GROUP BY g.goalID ORDER BY g.goalID DESC");

                        $cnt=1;
                        while($row=mysqli_fetch_array($query)){
                            $goalID = $row['goalID'];
                            $goalName = $row['goalName'];
                            $goalDescription = $row['goalDescription'];
                            $goalAmount = $row['goalAmount'];
                            $goalPeriod = $row['goalPeriod'];
                            $achieved_on = $row['achieved_on'];
                            $currentSavings = $row['currentSavings'];
                            $progress = $goalAmount > 0 ? ($currentSavings / $goalAmount) * 100 : 0;
                            ?>
                            <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                                <td class="align-middle" style="width: 28px;">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" id="simple-pagination-item-<?php echo $cnt; ?>" data-bulk-select-row="data-bulk-select-row" value="<?php echo $row['goalID']; ?>" name="goalIds[]"/>
                                    </div>
                                </td>
                                <td class="align-middle text-start product">
                                    <div class="d-flex align-items-center position-relative">
                                        <div class="flex-1">
                                            <h6 class="mb-0 fw-semi-bold text-nowrap"><?php echo $row["goalName"];?></h6>
                                            <p class="fw-semi-bold mb-0 text-500">
                                                <?php echo $row["goalDescription"];?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle text-start amount">
                                    <div class="flex-1">
                                        <h6 class="mb-0 fw-semi-bold text-nowrap"> <?php echo $row["goalAmount"]; ?></h6>
                                    </div>
                                </td>
                                <td class="align-middle text-start">
                                    <div class="d-flex align-items-center position-relative">
                                        <div class="flex-1">
                                            <h6 class="mb-0 fw-semi-bold text-nowrap">
                                                <span class="w-100 fs-10 badge <?php echo $row["goalStatus"] == 0 ? 'badge-subtle-warning' : 'badge-subtle-success'; ?>">
                                                    <?php echo $row["goalStatus"] == 0 ? "Active" : "Inactive"; ?>
                                                </span>
                                            </h6>
                                            <p class="fw-semi-bold mb-0 text-500">
                                                <span class="w-100 badge <?php echo $row["is_achieved"] == 0 ? 'badge-subtle-secondary' : 'badge-subtle-success'; ?>"><?php echo $row["is_achieved"] == 0 ? "Not Achieved Yet" : "Achieved & Completed"; ?></span>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle text-start amount">
                                    <div class="flex-1">
                                        <h6 class="mb-0 fw-semi-bold text-nowrap"> <?php echo round($progress, 2); ?>%</h6>
                                    </div>
                                </td>
                                <td class="align-middle text-start amount">
                                    <h6 class="mb-0 fw-semi-bold mb-0 text-500"><?php
                                        $originalDate = $row["goalPeriod"];
                                        $formattedDate = date("M j, Y", strtotime($originalDate));
                                        echo $formattedDate;?>
                                    </h6>
                                </td>
                                <td class="align-middle text-start amount">
                                    <h6 class="mb-0 fw-semi-bold mb-0 text-500">
                                        <?php
                                        if (is_null($row["achieved_on"]) || empty($row["achieved_on"]) || $row["achieved_on"] == "0000-00-00") {
                                            echo "Not Achieved";
                                        } else {
                                            $originalDate = $row["achieved_on"];
                                            $formattedDate = date("M j, Y", strtotime($originalDate));
                                            echo $formattedDate;
                                        }?>
                                    </h6>
                                </td>
                                <td class="align-middle white-space-nowrap text-end position-relative">
                                    <div class="hover-actions bg-100">
                                        <button class="btn btn-outline-danger icon-item rounded-3 me-2 fs-11 icon-item-sm" data-bs-toggle="modal" data-bs-target="#editGoalModal" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit this goal"
                                                onclick="populateEditModal(<?php echo $row['goalID']; ?>, '<?php echo $row['goalName']; ?>', '<?php echo $row['goalDescription']; ?>', <?php echo $row['goalAmount']; ?>, <?php echo $row['goalStatus']; ?>, '<?php echo $row['goalPeriod']; ?>', '<?php echo $row['achieved_on']; ?>')">
                                            <span class="fas fa-edit"></span>
                                        </button>
                                        <button class="btn icon-item rounded-3 me-2 fs-11 icon-item-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteGoalModal" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete this goal"
                                                onclick="populateDeleteModal(<?php echo $row['goalID']; ?>)">
                                            <span class="fas fa-trash"></span>
                                        </button>
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
            </div>
        </div>
    </div>

    <!-- Add Savings Goal Modal -->
    <div class="modal fade" id="addGoalModal" tabindex="-1" aria-labelledby="addGoalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <form id="addGoalForm" method="POST" action="add_goal">
                    <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                        <div class="position-relative z-1">
                            <h4 class="mb-0 text-white" id="addGoalModalLabel">Add New Savings Goal</h4>
                        </div>
                        <button type="button" class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="addGoalName" class="form-label">Goal Name</label>
                            <input type="text" class="form-control" id="addGoalName" name="goalName" required>
                        </div>
                        <div class="mb-3">
                            <label for="addGoalDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="addGoalDescription" name="goalDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="addGoalAmount" class="form-label">Amount (Ksh)</label>
                            <input type="number" class="form-control" id="addGoalAmount" name="goalAmount" required>
                        </div>
                        <div class="mb-3">
                            <label for="addGoalPeriod" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="addGoalPeriod" name="goalPeriod" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><span class="fas fa-save"></span> Add Goal</button>
                    </div>
            </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editGoalModal" tabindex="-1" aria-labelledby="editGoalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editGoalForm" method="POST" action="edit_goal">
                    <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                        <div class="position-relative z-1">
                            <h4 class="mb-0 text-white" id="editGoalModalLabel">Edit Savings Goal</h4>
                        </div>
                        <button type="button" class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editGoalID" name="goalID">
                        <div class="mb-3">
                            <label for="editGoalName" class="form-label">Goal Name</label>
                            <input type="text" class="form-control" id="editGoalName" name="goalName" required>
                        </div>
                        <div class="mb-3">
                            <label for="editGoalDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editGoalDescription" name="goalDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editGoalAmount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="editGoalAmount" name="goalAmount" required>
                        </div>
                        <div class="mb-3">
                            <label for="editGoalStatus" class="form-label">Status</label>
                            <select class="form-control" id="editGoalStatus" name="goalStatus">
                                <option value="0">Active</option>
                                <option value="1">Completed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editGoalPeriod" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="editGoalPeriod" name="goalPeriod" required>
                        </div>
                        <div class="mb-3">
                            <label for="editAchievedOn" class="form-label">Achieved Date</label>
                            <input type="date" class="form-control" id="editAchievedOn" name="achieved_on">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><span class="fas fa-edit"></span> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteGoalModal" tabindex="-1" aria-labelledby="deleteGoalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="deleteGoalForm" method="POST" action="delete_goal">
                <div class="modal-content">
                    <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                        <div class="position-relative z-1">
                            <h4 class="mb-0 text-white" id="deleteGoalModalLabel">Delete Savings Goal</h4>
                        </div>
                        <button type="button" class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="deleteGoalID" name="goalID">
                        <p>Are you sure you want to delete this goal?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><span class="fas fa-trash"></span> Delete Goal</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script type="text/javascript">
        function populateEditModal(goalID, goalName, goalDescription, goalAmount, goalStatus, goalPeriod, achieved_on) {
            document.getElementById('editGoalID').value = goalID;
            document.getElementById('editGoalName').value = goalName;
            document.getElementById('editGoalDescription').value = goalDescription;
            document.getElementById('editGoalAmount').value = goalAmount;
            document.getElementById('editGoalStatus').value = goalStatus;
            document.getElementById('editGoalPeriod').value = goalPeriod;
            document.getElementById('editAchievedOn').value = achieved_on;
        }

        function populateDeleteModal(goalID) {
            document.getElementById('deleteGoalID').value = goalID;
        }

    </script>

<?php
include "footer.php";
?>