<?php include "head.php";?>
    <title>Projects</title>
<?php include "navi.php";
$status = "OK";
$msg = "";
?>

<?php
if (isset($_SESSION['alert'])) {
    echo $_SESSION['alert'];
    unset($_SESSION['alert']);
}
?>

    <!-- Page Header -->
    <div class="card shadow-none border mb-3">
        <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
            <h5 class="mb-0 text-primary">Active Projects</h5>
            <a href="projects_archive" class="btn btn-outline-success btn-sm me-2"><span class="fas fa-trophy fs-11 me-1"></span>Archive</a>
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                <span class="fas fa-plus fs-11 me-1"></span>New Project
            </button>
        </div>
    </div>

    <!-- Active Project Cards -->
    <div class="row g-3 mb-3">
        <?php
        $sql = "
            SELECT
                p.projectID, p.projectName, p.projectDescription,
                p.projectAmount, p.projectStatus, p.is_achieved, p.projectPeriod,
                COALESCE(SUM(CASE WHEN t.type = 'Expense' THEN t.amount ELSE 0 END), 0) AS totalExpenses,
                COALESCE(SUM(CASE WHEN t.type = 'Income'  THEN t.amount ELSE 0 END), 0) AS totalIncome
            FROM tbl_projects p
            LEFT JOIN tbl_project_transactions t ON t.projectID = p.projectID
            WHERE p.is_achieved = 0 AND p.is_deleted = 0 AND p.projectStatus = 0
            GROUP BY p.projectID, p.projectName, p.projectDescription, p.projectAmount, p.projectStatus, p.is_achieved, p.projectPeriod
            ORDER BY p.projectPeriod ASC";
        $result = $con->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $pID        = $row['projectID'];
                $pName      = htmlspecialchars($row['projectName']);
                $pDesc      = htmlspecialchars($row['projectDescription']);
                $pBudget    = $row['projectAmount'];
                $pDue       = $row['projectPeriod'];
                $totalExp   = $row['totalExpenses'];
                $totalInc   = $row['totalIncome'];
                $netBalance = $totalInc - $totalExp;
                $spentPct   = $pBudget > 0 ? min(($totalExp / $pBudget) * 100, 100) : 0;
                $isOver     = $totalExp > $pBudget;
                $encodedID  = base64_encode($pID);

                $now    = new DateTime();
                $due    = new DateTime($pDue);
                $isPast = $now > $due;
                $diff   = $now->diff($due);
                $parts  = [];
                if ($diff->m) $parts[] = $diff->m . "mo";
                $remDays = $diff->d;
                if ($remDays >= 7) { $w = intdiv($remDays, 7); $parts[] = $w . "w"; $remDays %= 7; }
                if ($remDays) $parts[] = $remDays . "d";
                $tminus = $parts ? implode(" ", $parts) : "Today";

                $progressClass = $isOver ? 'bg-danger' : ($spentPct >= 80 ? 'bg-warning' : 'bg-primary');
                $netClass      = $netBalance >= 0 ? 'text-success' : 'text-danger';
                $tminusBadge   = $isPast ? 'badge-subtle-danger' : 'badge-subtle-primary';

                echo '
                <div class="col-md-6 col-xxl-4">
                    <div class="card h-100 font-sans-serif">
                        <div class="card-header pb-0 d-flex flex-between-center bg-body-tertiary">
                            <h6 class="mb-0 fw-bold">' . $pName . '</h6>
                            <span class="badge ' . $tminusBadge . ' fs-11">
                                <span class="fas fa-clock me-1"></span>' . ($isPast ? 'Overdue' : $tminus) . '
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="text-600 fs-10 mb-3">' . ($pDesc ?: '<em>No description</em>') . '</p>
                            <div class="row g-2 mb-3">
                                <div class="col-4 text-center">
                                    <p class="mb-0 fs-11 text-600">Budget</p>
                                    <h6 class="mb-0 fw-bold">Ksh ' . number_format($pBudget, 0) . '</h6>
                                </div>
                                <div class="col-4 text-center border-start border-end">
                                    <p class="mb-0 fs-11 text-600">Expenses</p>
                                    <h6 class="mb-0 fw-bold text-danger">Ksh ' . number_format($totalExp, 0) . '</h6>
                                </div>
                                <div class="col-4 text-center">
                                    <p class="mb-0 fs-11 text-600">Income</p>
                                    <h6 class="mb-0 fw-bold text-success">Ksh ' . number_format($totalInc, 0) . '</h6>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between fs-10 mb-1">
                                    <span class="text-600">' . ($isOver ? 'Over budget' : round($spentPct, 1) . '% spent') . '</span>
                                    <span class="' . $netClass . ' fw-semi-bold">Net ' . ($netBalance >= 0 ? '+' : '') . 'Ksh ' . number_format(abs($netBalance), 0) . '</span>
                                </div>
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar ' . $progressClass . '" role="progressbar" style="width:' . round($spentPct, 1) . '%" aria-valuenow="' . round($spentPct, 1) . '" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-body-tertiary py-2">
                            <a class="btn btn-outline-primary btn-sm w-100" href="project-details?projectID=' . $encodedID . '">
                                View Details <span class="fas fa-arrow-right ms-1 fs-11"></span>
                            </a>
                        </div>
                    </div>
                </div>';
            }
        } else {
            echo '
            <div class="col-12">
                <div class="card">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center py-5">
                        <span class="fas fa-folder-open fs-2 text-300 mb-3 d-block"></span>
                        <h6 class="text-600">No active projects yet</h6>
                        <p class="fs-10 text-500 mb-3">Create your first project to start tracking expenses and income.</p>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                            <span class="fas fa-plus me-1"></span>New Project
                        </button>
                    </div>
                </div>
            </div>';
        }
        ?>
    </div>

    <!-- All Projects Table -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card overflow-hidden">
                <div class="card-header d-flex flex-between-center bg-body-tertiary py-2">
                    <h5 class="mb-0 text-primary">All Projects</h5>
                </div>
                <div class="card-body px-0 pt-0">
                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables="data-datatables">
                        <thead class="bg-200">
                        <tr>
                            <th class="text-900 no-sort white-space-nowrap">
                                <div class="form-check mb-0 d-flex align-items-center">
                                    <input class="form-check-input" id="checkbox-select-all" type="checkbox" onclick="selectAllTasks(this)"
                                           data-bulk-select='{"body":"table-simple-pagination-body","actions":"table-simple-pagination-actions","replacedElement":"table-simple-pagination-replace-element"}' />
                                </div>
                            </th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Project</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Budget (Ksh)</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Expenses (Ksh)</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Income (Ksh)</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Status</th>
                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Due Date</th>
                            <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                        </tr>
                        </thead>
                        <tbody class="list" id="table-simple-pagination-body">
                        <?php
                        $sqlAll = "
                            SELECT
                                p.projectID, p.projectName, p.projectDescription,
                                p.projectAmount, p.projectStatus, p.is_achieved, p.projectPeriod,
                                COALESCE(SUM(CASE WHEN t.type = 'Expense' THEN t.amount ELSE 0 END), 0) AS totalExpenses,
                                COALESCE(SUM(CASE WHEN t.type = 'Income'  THEN t.amount ELSE 0 END), 0) AS totalIncome
                            FROM tbl_projects p
                            LEFT JOIN tbl_project_transactions t ON t.projectID = p.projectID
                            WHERE p.is_deleted = 0
                            GROUP BY p.projectID, p.projectName, p.projectDescription, p.projectAmount, p.projectStatus, p.is_achieved, p.projectPeriod
                            ORDER BY p.projectID DESC";
                        $resAll = $con->query($sqlAll);
                        $cnt = 1;
                        if ($resAll) {
                            while ($r = $resAll->fetch_assoc()) {
                                $rID      = $r['projectID'];
                                $rName    = htmlspecialchars($r['projectName']);
                                $rDesc    = htmlspecialchars($r['projectDescription']);
                                $rBudget  = number_format($r['projectAmount'], 2);
                                $rExp     = number_format($r['totalExpenses'], 2);
                                $rInc     = number_format($r['totalIncome'], 2);
                                $rDue     = date("M j, Y", strtotime($r['projectPeriod']));
                                $rDueFmt  = $r['projectPeriod'];
                                $rStat    = $r['projectStatus'];
                                $rAch     = $r['is_achieved'];
                                $encID    = base64_encode($rID);

                                if ($rAch == 1)
                                    $statusBadge = '<span class="badge badge-subtle-success fs-11">Achieved</span>';
                                elseif ($rStat == 0)
                                    $statusBadge = '<span class="badge badge-subtle-warning fs-11">Active</span>';
                                else
                                    $statusBadge = '<span class="badge badge-subtle-secondary fs-11">Inactive</span>';
                                ?>
                                <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                                    <td class="align-middle" style="width:28px;">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox"
                                                   id="simple-pagination-item-<?php echo $cnt; ?>"
                                                   data-bulk-select-row="data-bulk-select-row"
                                                   value="<?php echo $rID; ?>" name="projectIds[]"/>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center position-relative">
                                            <div class="flex-1">
                                                <h6 class="mb-0 fw-semi-bold text-nowrap">
                                                    <a class="text-900 stretched-link" href="project-details?projectID=<?php echo $encID; ?>">
                                                        <?php echo $rName; ?>
                                                    </a>
                                                </h6>
                                                <p class="mb-0 text-500 fs-11"><?php echo $rDesc; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="align-middle fw-semi-bold"><?php echo $rBudget; ?></td>
                                    <td class="align-middle text-danger fw-semi-bold"><?php echo $rExp; ?></td>
                                    <td class="align-middle text-success fw-semi-bold"><?php echo $rInc; ?></td>
                                    <td class="align-middle"><?php echo $statusBadge; ?></td>
                                    <td class="align-middle text-500"><?php echo $rDue; ?></td>
                                    <td class="align-middle white-space-nowrap text-end position-relative">
                                        <div class="hover-actions bg-100">
                                            <button class="btn btn-outline-primary icon-item rounded-3 me-1 fs-11 icon-item-sm"
                                                    data-bs-toggle="modal" data-bs-target="#editProjectModal"
                                                    onclick="populateEditModal('<?php echo $rID; ?>','<?php echo addslashes($rName); ?>','<?php echo addslashes($rDesc); ?>','<?php echo $r['projectAmount']; ?>','<?php echo $rStat; ?>','<?php echo $rAch; ?>','<?php echo $rDueFmt; ?>')"
                                                    title="Edit">
                                                <span class="fas fa-edit"></span>
                                            </button>
                                            <button class="btn btn-outline-danger icon-item rounded-3 me-1 fs-11 icon-item-sm"
                                                    data-bs-toggle="modal" data-bs-target="#deleteProjectModal"
                                                    onclick="populateDeleteModal('<?php echo $rID; ?>')"
                                                    title="Delete">
                                                <span class="fas fa-trash"></span>
                                            </button>
                                        </div>
                                        <div class="dropdown font-sans-serif btn-reveal-trigger">
                                            <button class="btn btn-link text-600 btn-sm dropdown-toggle dropdown-caret-none btn-reveal-sm transition-none" type="button" data-bs-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false">
                                                <span class="fas fa-chevron-left fs-11"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                                $cnt++;
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Project Modal -->
    <div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="add_project">
                    <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                        <div class="position-relative z-1">
                            <h4 class="mb-0 text-white" id="addProjectModalLabel">New Project</h4>
                        </div>
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="addProjectName" class="form-label">Project Name</label>
                            <input type="text" class="form-control" id="addProjectName" name="projectName" required placeholder="e.g. Office Renovation">
                        </div>
                        <div class="mb-3">
                            <label for="addProjectDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="addProjectDescription" name="projectDescription" rows="3" placeholder="What is this project about?"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="addProjectAmount" class="form-label">Budget (Ksh)</label>
                            <input type="number" class="form-control" id="addProjectAmount" name="projectAmount" required min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label for="addProjectPeriod" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="addProjectPeriod" name="projectPeriod" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><span class="fas fa-save me-1"></span>Create Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div class="modal fade" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editProjectForm" method="POST" action="edit_project">
                    <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                        <div class="position-relative z-1">
                            <h4 class="mb-0 text-white" id="editProjectModalLabel">Edit Project</h4>
                        </div>
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editProjectID" name="projectID">
                        <div class="mb-3">
                            <label for="editProjectName" class="form-label">Project Name</label>
                            <input type="text" class="form-control" id="editProjectName" name="projectName" required>
                        </div>
                        <div class="mb-3">
                            <label for="editProjectDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editProjectDescription" name="projectDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editProjectAmount" class="form-label">Budget (Ksh)</label>
                            <input type="number" class="form-control" id="editProjectAmount" name="projectAmount" required min="0" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label for="editProjectStatus" class="form-label">Status</label>
                            <select class="form-select" id="editProjectStatus" name="projectStatus">
                                <option value="0">Active</option>
                                <option value="1">Inactive</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editProjectPeriod" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="editProjectPeriod" name="projectPeriod" required>
                        </div>
                        <div class="mb-3">
                            <label for="editProjectAchievement" class="form-label">Mark as Achieved?</label>
                            <select class="form-select" id="editProjectAchievement" name="is_achieved">
                                <option value="0">Not yet</option>
                                <option value="1">Yes — Achieved</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><span class="fas fa-save me-1"></span>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Project Modal -->
    <div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-labelledby="deleteProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="deleteProjectForm" method="POST" action="delete_project">
                    <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                        <div class="position-relative z-1">
                            <h4 class="mb-0 text-white" id="deleteProjectModalLabel">Delete Project</h4>
                        </div>
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="deleteProjectID" name="projectID">
                        <p class="mb-0">Are you sure you want to delete this project? All associated transactions will also be removed. This cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><span class="fas fa-trash me-1"></span>Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function populateEditModal(id, name, desc, amount, status, achieved, period) {
            document.getElementById('editProjectID').value          = id;
            document.getElementById('editProjectName').value        = name;
            document.getElementById('editProjectDescription').value = desc;
            document.getElementById('editProjectAmount').value      = amount;
            document.getElementById('editProjectStatus').value      = status;
            document.getElementById('editProjectAchievement').value = achieved;
            document.getElementById('editProjectPeriod').value      = period;
        }
        function populateDeleteModal(id) {
            document.getElementById('deleteProjectID').value = id;
        }
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
        });
    </script>

<?php include "footer.php"; ?>