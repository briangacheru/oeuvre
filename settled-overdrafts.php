<?php include "head.php";?>
    <title>Settled Records | iTasker</title>
<?php include "navi.php";?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->

        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Settled <span class="text-info fw-medium"> Records</span></h4>
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

    <div class="row g-3 mb-3">
    <div class="col">
        <div class="card mb-3">
            <div class="card-body p-0">
                <div class="tab-content">
                    <div class="tab-pane preview-tab-pane active" role="tabpanel">
                        <div class="card shadow-none">
                            <form id="tasksForm" method="post">
                                <div class="card-header">
                                    <div class="row flex-between-center">
                                        <div class="col-6 col-sm-auto d-flex align-items-center pe-0">
                                            <div class="btn-group ms-3" role="group">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="filter-all" onclick="filterRecords('all')">All</button>
                                                <button type="button" class="btn btn-outline-warning btn-sm" id="filter-overdraft" onclick="filterRecords('overdraft')">Overdrafts</button>
                                                <button type="button" class="btn btn-outline-info btn-sm" id="filter-bonus" onclick="filterRecords('bonus')">Bonuses</button>
                                            </div>
                                        </div>
                                        <div class="col-6 col-sm-auto ms-auto text-end ps-0">
                                            <div class="d-flex align-items-center" id="table-simple-pagination-replace-element">
                                                <button class="btn btn-falcon-primary btn-sm" onclick="exportTableToCSVWithConfirmation('settled_records.csv')" data-bs-toggle="tooltip" data-bs-placement="top" title="Export as CSV" type="button"><span class="fas fa-external-link-alt" data-fa-transform="shrink-3 down-2"></span><span class="d-none d-sm-inline-block ms-1">Export as CSV</span></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body px-0 pt-0">
                                    <table class="table table-sm mb-0 overflow-hidden data-table fs-10" data-datatables="data-datatables">
                                        <thead class="bg-200">
                                        <tr>
                                            <th class="text-900 no-sort white-space-nowrap d-none">
                                                <div class="form-check mb-0 d-flex align-items-center">
                                                    <input class="form-check-input" id="checkbox-select-all" type="checkbox" onclick="selectAllTasks(this)" data-bulk-select='{"body":"table-simple-pagination-body","actions":"table-simple-pagination-actions","replacedElement":"table-simple-pagination-replace-element"}' />
                                                </div>
                                            </th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap ps-3">Type</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap"><span class='ms-3'>Amount (Ksh)</span></th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Description</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Transaction Date</th>
                                            <th class="text-900 sort pe-1 align-middle white-space-nowrap">Settled On</th>
                                        </tr>
                                        </thead>
                                        <tbody class="list" id="table-simple-pagination-body">
                                        <?php
                                        // Updated query to use correct session variable and show both overdrafts and bonuses
                                        if (isset($_SESSION['sessionWriter'])) {
                                            $email = $_SESSION['sessionWriter'];
                                            $query = mysqli_query($con, "SELECT * FROM tbloverdrafts WHERE is_settled = 1 AND email = '$email' ORDER BY od_date DESC");
                                        } else {
                                            $query = false; // No session found
                                        }

                                        $cnt = 1;
                                        if ($query && mysqli_num_rows($query) > 0) {
                                            while ($row = mysqli_fetch_array($query)) {
                                                $encodedId = base64_encode($row["id"]);
                                                $recordType = isset($row["record_type"]) && !empty($row["record_type"]) ? $row["record_type"] : 'overdraft';
                                                $badgeClass = ($recordType === 'bonus') ? 'badge-subtle-info' : 'badge-subtle-warning';
                                                $typeDisplay = ($recordType === 'bonus') ? 'Bonus' : 'Overdraft';
                                                $amountClass = ($recordType === 'bonus') ? 'text-info' : 'text-warning';
                                                ?>
                                                <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100 record-row" data-record-type="<?php echo $recordType; ?>">
                                                    <td class="align-middle d-none" style="width: 28px;">
                                                        <div class="form-check mb-0">
                                                            <input class="form-check-input" type="checkbox" id="simple-pagination-item-<?php echo $cnt; ?>" data-bulk-select-row="data-bulk-select-row" value="<?php echo $row['id']; ?>" name="taskIds[]"/>
                                                        </div>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap ps-3">
                                                        <span class="badge <?php echo $badgeClass; ?> rounded-pill"><?php echo $typeDisplay; ?></span>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap fw-semi-bold <?php echo $amountClass; ?>">
                                                        <span class='ms-3'><?php echo number_format($row['amount'], 2); ?></span>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap text-900"><?php echo htmlspecialchars($row['description']); ?></td>
                                                    <td class="align-middle white-space-nowrap text-900"><?php echo date("jS M, Y h:i A", strtotime($row['od_date'])); ?></td>
                                                    <td class="align-middle white-space-nowrap text-900">
                                                        <?php
                                                        if (!empty($row['date_settled'])) {
                                                            echo date("jS M, Y h:i A", strtotime($row['date_settled']));
                                                        } else {
                                                            echo '<span class="text-muted">Not specified</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php
                                                $cnt = $cnt + 1;
                                            }
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
        document.addEventListener('DOMContentLoaded', function() {
            // Set default filter to show all records
            filterRecords('all');
        });

        function exportTableToCSVWithConfirmation(filename) {
            if (confirm("Are you sure you want to export the table as a CSV file?")) {
                exportTableToCSV(filename);
            }
        }

        function exportTableToCSV(filename) {
            var csv = [];
            var rows = document.querySelectorAll("table tr");

            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll("td, th");

                for (var j = 0; j < cols.length; j++) {
                    // Skip hidden columns and clean up the text
                    var cellText = cols[j].innerText.trim();
                    if (cellText !== '') {
                        row.push('"' + cellText.replace(/"/g, '""') + '"');
                    }
                }

                if (row.length > 0) {
                    csv.push(row.join(","));
                }
            }

            // Download CSV
            downloadCSV(csv.join("\n"), filename);
        }

        function downloadCSV(csv, filename) {
            var csvFile;
            var downloadLink;

            csvFile = new Blob([csv], {type: "text/csv"});

            downloadLink = document.createElement("a");

            downloadLink.download = filename;

            downloadLink.href = window.URL.createObjectURL(csvFile);

            downloadLink.style.display = "none";

            document.body.appendChild(downloadLink);

            downloadLink.click();
        }

        function filterRecords(type) {
            const rows = document.querySelectorAll('.record-row');
            const filterButtons = document.querySelectorAll('[id^="filter-"]');

            // Update button states
            filterButtons.forEach(btn => {
                btn.classList.remove('btn-outline-warning', 'btn-outline-info', 'btn-outline-secondary', 'btn-warning', 'btn-info', 'btn-secondary');
                if (btn.id === `filter-${type}`) {
                    if (type === 'all') btn.classList.add('btn-secondary');
                    else if (type === 'overdraft') btn.classList.add('btn-warning');
                    else if (type === 'bonus') btn.classList.add('btn-info');
                } else {
                    btn.classList.add('btn-outline-secondary');
                }
            });

            // Show/hide rows based on filter
            rows.forEach(row => {
                const recordType = row.getAttribute('data-record-type');
                if (type === 'all' || recordType === type) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
<?php
include "footer.php";
?>