<?php
session_start();
include_once 'db.php';

error_reporting(E_ALL);
ini_set('log_errors', 1);

if (isset($_POST['export_csv'])) {
    // Define the CSV file headers
    $filename = "transactions_" . date("Ymd_His") . ".csv";
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $output = fopen("php://output", "w");
    fputcsv($output, ['Category', 'Subcategory', 'Description', 'Amount (Ksh)', 'Cost (Ksh)', 'Tag', 'Date']);
    // Query the database for transaction data
    $query = mysqli_query($con, "
    SELECT category, subcategory, description, amount, transactionCost, tag, expenseDate AS date 
    FROM tblbudget 
    WHERE is_deleted = 0 
    ORDER BY date DESC");
    // Loop through each row and write it to the CSV file
    while ($row = mysqli_fetch_assoc($query)) {
        // Format the date
        $row['date'] = date("M j, Y H:i", strtotime($row['date']));
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Handle CSV Import
if (isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        error_log("CSV Import started: " . $_FILES['csv_file']['name']);

        // Read file content and remove BOM
        $fileContent = file_get_contents($_FILES['csv_file']['tmp_name']);
        $fileContent = str_replace("\xEF\xBB\xBF", '', $fileContent); // Remove UTF-8 BOM

        // Create temporary file without BOM
        $tempFile = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tempFile, $fileContent);

        $csvFile = fopen($tempFile, 'r');

        // Read and validate header row
        $header = fgetcsv($csvFile);

        if (!$header) {
            $_SESSION['alert'] = '
            <div class="alert alert-danger border-0 d-flex align-items-center">
                <p class="mb-0 flex-1"><i class="fas fa-times-circle me-2"></i>Unable to read CSV file. Please check the file format.</p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
            fclose($csvFile);
            unlink($tempFile);
            header("Location: transactions");
            exit;
        }

        // Normalize headers by trimming whitespace and converting to lowercase
        $normalizedHeader = array_map('trim', $header);
        $normalizedHeader = array_map('strtolower', $normalizedHeader);

        // Define expected headers (also normalized)
        $expectedHeaders = [
            'category',
            'subcategory',
            'description',
            'amount (ksh)',
            'cost (ksh)',
            'tag',
            'date'
        ];

        // Compare normalized headers with expected headers
        if ($normalizedHeader !== $expectedHeaders) {
            error_log("Invalid headers found: " . implode(', ', $normalizedHeader));
            $_SESSION['alert'] = '
            <div class="alert alert-danger border-0 d-flex align-items-center">
                <p class="mb-0 flex-1"><i class="fas fa-times-circle me-2"></i><strong>Invalid CSV headers.</strong><br>
                <strong>Expected:</strong> ' . implode(', ', $expectedHeaders) . '<br>
                <strong>Found:</strong> ' . implode(', ', $normalizedHeader) . '</p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
            fclose($csvFile);
            unlink($tempFile);
            header("Location: transactions");
            exit;
        }

        $successCount = 0;
        $duplicateCount = 0;
        $errorCount = 0;
        $rowNumber = 1; // Track row number for debugging
        $errorDetails = []; // Store first 5 errors to show user

        while (($row = fgetcsv($csvFile)) !== FALSE) {
            $rowNumber++;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Ensure we have exactly 7 columns
            if (count($row) !== 7) {
                $errorCount++;
                if (count($errorDetails) < 5) {
                    $errorDetails[] = "Row $rowNumber: Expected 7 columns, found " . count($row);
                }
                error_log("Row $rowNumber: Invalid column count - " . count($row));
                continue;
            }

            list($category, $subcategory, $description, $amount, $transactionCost, $tag, $date) = $row;

            // ⚠️ BUG FIX: Correct date parsing for MM/DD/YY format
            $dateFormatted = false;

            // Try multiple date formats
            // Format 1: MM/DD/YY HH:MM (e.g., "10/10/25 15:49")
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})\s+(\d{1,2}):(\d{2})$/', trim($date), $matches)) {
                $month = $matches[1];
                $day = $matches[2];
                $year = '20' . $matches[3]; // ⚠️ FIX: Use $matches[3] (the YY part), not $matches[2]
                $hour = $matches[4];
                $minute = $matches[5];

                // Validate date components
                if (checkdate($month, $day, $year)) {
                    $dateFormatted = sprintf("%04d-%02d-%02d %02d:%02d:00", $year, $month, $day, $hour, $minute);
                } else {
                    $errorCount++;
                    if (count($errorDetails) < 5) {
                        $errorDetails[] = "Row $rowNumber: Invalid date '$date' (month=$month, day=$day, year=$year)";
                    }
                    error_log("Row $rowNumber: Invalid date components - $date");
                    continue;
                }
            }
            // Format 2: M/D/YY H:MM or similar variations
            else {
                // Fallback to strtotime
                $timestamp = strtotime($date);
                if ($timestamp !== false && $timestamp > 0) {
                    $dateFormatted = date("Y-m-d H:i:s", $timestamp);
                } else {
                    $errorCount++;
                    if (count($errorDetails) < 5) {
                        $errorDetails[] = "Row $rowNumber: Cannot parse date '$date'";
                    }
                    error_log("Row $rowNumber: Invalid date format - $date");
                    continue;
                }
            }

            // Double-check date is valid
            if (!$dateFormatted || $dateFormatted == '1970-01-01 00:00:00') {
                $errorCount++;
                if (count($errorDetails) < 5) {
                    $errorDetails[] = "Row $rowNumber: Date resulted in invalid format '$date'";
                }
                error_log("Row $rowNumber: Date parsing resulted in invalid date - $date");
                continue;
            }

            // Sanitize input
            $category = mysqli_real_escape_string($con, htmlspecialchars(trim($category), ENT_QUOTES, 'UTF-8'));
            $subcategory = mysqli_real_escape_string($con, htmlspecialchars(trim($subcategory), ENT_QUOTES, 'UTF-8'));
            $description = mysqli_real_escape_string($con, htmlspecialchars(trim($description), ENT_QUOTES, 'UTF-8'));
            $amount = floatval($amount);
            $transactionCost = floatval($transactionCost);
            $tag = mysqli_real_escape_string($con, htmlspecialchars(trim($tag), ENT_QUOTES, 'UTF-8'));

            // Check for duplicate based on date, description, and amount
            $checkSql = "
                SELECT * FROM tblbudget 
                WHERE expenseDate = '$dateFormatted'
                  AND description = '$description'
                  AND amount = '$amount'
                  AND is_deleted = 0
            ";
            $result = mysqli_query($con, $checkSql);

            if (mysqli_num_rows($result) > 0) {
                $duplicateCount++;
                continue;
            }

            // Insert into tblbudget
            $insertSql = "
                INSERT INTO tblbudget (
                    category, subcategory, description, amount, transactionCost, tag, expenseDate
                ) VALUES (
                    '$category', '$subcategory', '$description', '$amount', '$transactionCost', '$tag', '$dateFormatted'
                )
            ";

            if (mysqli_query($con, $insertSql)) {
                $successCount++;
            } else {
                $errorCount++;
                if (count($errorDetails) < 5) {
                    $errorDetails[] = "Row $rowNumber: Database error - " . mysqli_error($con);
                }
                error_log("Row $rowNumber DB Error: " . mysqli_error($con));
            }
        }

        fclose($csvFile);
        unlink($tempFile); // Clean up temp file

        // Set session alerts with detailed feedback
        $alerts = [];

        if ($successCount > 0) {
            $alerts[] = '
            <div class="alert alert-success border-0 d-flex align-items-center alert-dismissible fade show">
                <i class="fas fa-check-circle me-2 fs-4"></i>
                <div class="flex-1">
                    <strong>Success!</strong> Imported ' . $successCount . ' transaction(s) successfully.
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }

        if ($duplicateCount > 0) {
            $alerts[] = '
            <div class="alert alert-warning border-0 d-flex align-items-center alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2 fs-4"></i>
                <div class="flex-1">
                    <strong>Skipped Duplicates:</strong> ' . $duplicateCount . ' transaction(s) already exist in the database (same date, description, and amount).
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }

        if ($errorCount > 0) {
            $errorDetailHtml = '';
            if (!empty($errorDetails)) {
                $errorDetailHtml = '<ul class="mb-0 mt-2 small">';
                foreach ($errorDetails as $detail) {
                    $errorDetailHtml .= '<li>' . htmlspecialchars($detail) . '</li>';
                }
                if ($errorCount > count($errorDetails)) {
                    $errorDetailHtml .= '<li><em>... and ' . ($errorCount - count($errorDetails)) . ' more errors (check server logs)</em></li>';
                }
                $errorDetailHtml .= '</ul>';
            }

            $alerts[] = '
            <div class="alert alert-danger border-0 d-flex align-items-start alert-dismissible fade show">
                <i class="fas fa-times-circle me-2 fs-4 mt-1"></i>
                <div class="flex-1">
                    <strong>Import Errors:</strong> Failed to import ' . $errorCount . ' transaction(s).
                    ' . $errorDetailHtml . '
                    <p class="mb-0 mt-2 small"><strong>Common issues:</strong> Invalid date format, missing columns, or database constraints.</p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }

        if (empty($alerts)) {
            $alerts[] = '
            <div class="alert alert-info border-0 d-flex align-items-center alert-dismissible fade show">
                <i class="fas fa-info-circle me-2 fs-4"></i>
                <div class="flex-1">
                    <strong>No Changes:</strong> No new transactions imported. All records were either duplicates or invalid.
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
        }

        // Summary alert
        $totalProcessed = $successCount + $duplicateCount + $errorCount;
        $summaryAlert = '
        <div class="alert alert-primary border-0 d-flex align-items-center alert-dismissible fade show">
            <i class="fas fa-info-circle me-2 fs-4"></i>
            <div class="flex-1">
                <strong>Import Summary:</strong> Processed ' . $totalProcessed . ' rows total. 
                <span class="badge bg-success">' . $successCount . ' Success</span> 
                <span class="badge bg-warning text-dark">' . $duplicateCount . ' Duplicates</span> 
                <span class="badge bg-danger">' . $errorCount . ' Errors</span>
            </div>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';

        $_SESSION['alert'] = $summaryAlert . implode('', $alerts);
        header("Location: transactions");
        exit;

    } else {
        // Better error handling for file upload
        $errorMsg = 'Error uploading file.';
        if (isset($_FILES['csv_file'])) {
            $errorCode = $_FILES['csv_file']['error'];
            switch ($errorCode) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMsg = 'File is too large. Maximum size: ' . ini_get('upload_max_filesize');
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMsg = 'File was only partially uploaded. Please try again.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMsg = 'No file was uploaded. Please select a CSV file.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMsg = 'Server error: Missing temporary folder.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMsg = 'Server error: Failed to write file to disk.';
                    break;
                default:
                    $errorMsg = 'Unknown upload error (Code: ' . $errorCode . ')';
            }
        }

        error_log("CSV Upload Error: " . $errorMsg);

        $_SESSION['alert'] = '
        <div class="alert alert-danger border-0 d-flex align-items-center alert-dismissible fade show">
            <i class="fas fa-times-circle me-2 fs-4"></i>
            <div class="flex-1">
                <strong>Upload Failed:</strong> ' . $errorMsg . '
            </div>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
        header("Location: transactions");
        exit;
    }
}
?>
<?php
// Handle Bulk Delete
if (isset($_POST['bulk_delete'])) {
    if (!isset($_POST['taskIds']) || empty($_POST['taskIds'])) {
        $_SESSION['alert'] = '
        <div class="alert alert-warning border-0 d-flex align-items-center alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2 fs-4"></i>
            <div class="flex-1">
                <strong>No Selection:</strong> Please select at least one transaction to delete.
            </div>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
        header("Location: transactions");
        exit;
    }

    $selectedIds = $_POST['taskIds'];
    $successCount = 0;
    $errorCount = 0;
    $errorDetails = [];

    foreach ($selectedIds as $id) {
        $id = intval($id); // Sanitize input

        // Soft delete - set is_deleted = 1 instead of actually deleting
        $deleteSql = "UPDATE tblbudget SET is_deleted = 1 WHERE budgetID = $id AND is_deleted = 0";

        if (mysqli_query($con, $deleteSql)) {
            if (mysqli_affected_rows($con) > 0) {
                $successCount++;
            } else {
                // Transaction not found or already deleted
                $errorCount++;
                if (count($errorDetails) < 3) {
                    $errorDetails[] = "Transaction #$id: Not found or already deleted";
                }
            }
        } else {
            $errorCount++;
            if (count($errorDetails) < 3) {
                $errorDetails[] = "Transaction #$id: " . mysqli_error($con);
            }
            error_log("Bulk Delete Error - ID $id: " . mysqli_error($con));
        }
    }

    // Build alert messages
    $alerts = [];

    if ($successCount > 0) {
        $alerts[] = '
        <div class="alert alert-success border-0 d-flex align-items-center alert-dismissible fade show">
            <i class="fas fa-check-circle me-2 fs-4"></i>
            <div class="flex-1">
                <strong>Deleted Successfully:</strong> ' . $successCount . ' transaction(s) have been deleted.
            </div>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }

    if ($errorCount > 0) {
        $errorDetailHtml = '';
        if (!empty($errorDetails)) {
            $errorDetailHtml = '<ul class="mb-0 mt-2 small">';
            foreach ($errorDetails as $detail) {
                $errorDetailHtml .= '<li>' . htmlspecialchars($detail) . '</li>';
            }
            if ($errorCount > count($errorDetails)) {
                $errorDetailHtml .= '<li><em>... and ' . ($errorCount - count($errorDetails)) . ' more errors</em></li>';
            }
            $errorDetailHtml .= '</ul>';
        }

        $alerts[] = '
        <div class="alert alert-danger border-0 d-flex align-items-start alert-dismissible fade show">
            <i class="fas fa-times-circle me-2 fs-4 mt-1"></i>
            <div class="flex-1">
                <strong>Delete Errors:</strong> Failed to delete ' . $errorCount . ' transaction(s).
                ' . $errorDetailHtml . '
            </div>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }

    // Summary
    $totalSelected = count($selectedIds);
    $summaryAlert = '
    <div class="alert alert-primary border-0 d-flex align-items-center alert-dismissible fade show">
        <i class="fas fa-info-circle me-2 fs-4"></i>
        <div class="flex-1">
            <strong>Bulk Delete Summary:</strong> Processed ' . $totalSelected . ' transaction(s). 
            <span class="badge bg-success">' . $successCount . ' Deleted</span> 
            <span class="badge bg-danger">' . $errorCount . ' Failed</span>
        </div>
        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';

    $_SESSION['alert'] = $summaryAlert . implode('', $alerts);
    header("Location: transactions");
    exit;
}
?>
<?php include "head.php";?>
    <title>Transactions Management</title>
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
                    <h4 class="mb-0 text-primary fw-bold">Transactions<span class="text-info fw-medium"> Management</span></h4>
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
    unset($_SESSION['alert']);
    //echo '<meta http-equiv="refresh" content="10;url=' . htmlspecialchars($_SERVER['PHP_SELF']) . '">';
}
?>
    <div class="row mb-3">
        <div class="col">
            <div class="card shadow-none border ps-3">
                <div class="row gx-0 flex-between-center">
                    <div class="col-sm-auto d-flex align-items-center"><img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                        <div>
                            <h4 class="text-primary fw-bold mb-0">Import <span class="text-info fw-medium">Transactions</span></h4>
                        </div><img class="ms-n4 d-md-none d-lg-block" src="../assets/img/illustrations/crm-line-chart.png" alt="" width="150" />
                    </div>
                    <div class="col-sm-auto pt-lg-0">
                        <form method="post" enctype="multipart/form-data" class="row flex-lg-column flex-xxl-row align-items-center align-items-lg-start align-items-xxl-center">
                            <div class="card-body">
                                <div class="mb-1">
                                    <label for="transaction_file" class="form-label">Select CSV File</label>
                                    <input type="file" class="form-control" id="transaction_file" name="csv_file" accept=".csv" required>
                                    <div class="form-text">File must match the export format with columns: Category, Subcategory, Description, Amount, Cost, Tag, Date</div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" name="import_csv" class="btn btn-primary">Import CSV</button>
                                <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importHelpModal">
                                    <i class="fas fa-question-circle me-1"></i> Import Help
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                                                    <span class="text-primary">Transaction History</span>
                                                    <span class="text-warning">
                                                </span>
                                                </h4>
                                            </div>
                                            <div class="col-6 col-sm-auto ms-auto text-end ps-0">
                                                <div class="d-none" id="table-simple-pagination-actions">
                                                    <div class="d-flex">
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center" id="table-simple-pagination-replace-element">
                                                    <form method="post" action="">
                                                        <button type="submit" name="export_csv" data-bs-toggle="tooltip" data-bs-placement="top" title="Export all transactions as CSV" class="btn btn-falcon-default btn-sm"><span class="fas fa-external-link-alt" data-fa-transform="shrink-3 down-2"></span><span class="d-none d-sm-inline-block ms-1">Export</span></button>
                                                        <a class="btn btn-falcon-info btn-sm mx-2" data-bs-toggle="modal" data-bs-target="#addTransactionModal" data-bs-toggle="tooltip" data-bs-placement="top" title="Add a new transaction" type="button"><span class="fas fa-plus" data-fa-transform="shrink-3 down-2"></span><span class="d-none d-sm-inline-block ms-1">Add Transaction</span></a>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Loader -->
                                    <div id="loading-spinner" class="text-center py-5">
                                        <div class="spinner-grow text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
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
                                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Category</th>
                                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Description</th>
                                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Amount (Ksh)</th>
                                                <th class="text-900 sort pe-1 text-center white-space-nowrap">Platform</th>
                                                <th class="text-900 sort pe-1 align-middle white-space-nowrap">Date</th>
                                                <th class="text-900 no-sort pe-1 align-middle data-table-row-action"></th>
                                            </tr>
                                            </thead>
                                            <tbody class="list" id="table-simple-pagination-body">
                                            <?php
                                            $query=mysqli_query($con,"SELECT 
                                                budgetID AS id, category, subcategory, description, tag, amount, transactionCost, expenseDate AS date, 'tblbudget' AS table_source 
                                                FROM tblbudget WHERE is_deleted = 0 AND expenseDate >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                                                ORDER BY date DESC");
                                            $cnt=1;
                                            while($row=mysqli_fetch_array($query))
                                            {

                                                ?>
                                                <tr class="hover-actions-trigger btn-reveal-trigger hover-bg-100">
                                                    <td class="align-middle" style="width: 28px;">
                                                        <div class="form-check mb-0">
                                                            <input class="form-check-input" type="checkbox" id="simple-pagination-item-<?php echo $cnt; ?>" data-bulk-select-row="data-bulk-select-row" value="<?php echo $row['id']; ?>" name="taskIds[]"/>
                                                        </div>
                                                    </td>
                                                    <td class="align-middle text-start product">
                                                        <div class="d-flex align-items-center position-relative">
                                                            <div class="flex-1">
                                                                <h6 class="mb-0 fw-semi-bold text-nowrap">
                                                                    <?php
                                                                    if ($row["category"] === "Expense") {
                                                                        echo '<span class="badge fs-10 rounded-pill badge-subtle-danger">Expense</span>';
                                                                    } elseif ($row["category"] === "Savings") {
                                                                        echo '<span class="badge fs-10 rounded-pill badge-subtle-success">Savings</span>';
                                                                    } elseif ($row["category"] === "Income") {
                                                                        echo '<span class="badge fs-10 rounded-pill badge-subtle-primary">Income</span>';
                                                                    } else {
                                                                        echo htmlspecialchars($row["category"]);
                                                                    }
                                                                    ?>
                                                                </h6>
                                                                <p class="fw-semi-bold mb-0 text-300">
                                                                    <?php
                                                                    if (isset($row['table_source']) && $row['table_source'] === 'tblbudget') {
                                                                        echo 'TNSN';
                                                                    } elseif (isset($row['table_source']) && $row['table_source'] === 'tbloverdrafts') {
                                                                        echo 'OVDT';}
                                                                    ?> | #<?php echo $row["id"]; ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="align-middle text-start product description-cell" style="cursor: pointer;"
                                                        data-bs-toggle="modal" data-bs-target="#viewTransactionModal"
                                                        onclick="loadViewModal('<?php echo $row['id']; ?>', '<?php echo addslashes($row['category']); ?>', '<?php echo addslashes($row['subcategory']); ?>', '<?php echo addslashes($row['tag']); ?>', '<?php echo addslashes($row['description']); ?>', '<?php echo $row['amount']; ?>', '<?php echo $row['transactionCost']; ?>', '<?php echo date('M j, Y \a\\t H:i', strtotime($row['date'])); ?>', '<?php echo (isset($row['table_source']) && $row['table_source'] === 'tblbudget') ? 'TNSN' : 'OVDT'; ?>')">
                                                        <div class="d-flex align-items-center position-relative">
                                                            <div class="flex-1">
                                                                <h6 class="mb-0 fw-semi-bold text-nowrap" title="<?php echo htmlspecialchars($row["subcategory"], ENT_QUOTES); ?>">
                                                                    <?php
                                                                    $descText = $row["subcategory"];
                                                                    if (mb_strlen($descText) > 50) {
                                                                        echo htmlspecialchars(mb_substr($descText, 0, 50)) . '...';
                                                                    } else {
                                                                        echo htmlspecialchars($descText);
                                                                    }
                                                                    ?>
                                                                </h6>
                                                                <p class="fw-semi-bold mb-0 text-500" ><?php echo $row["description"]; ?> </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="align-middle text-start amount">
                                                        <div class="flex-1">
                                                            <h6 class="mb-0 fw-semi-bold text-nowrap"> <?php echo $row["amount"]; ?></h6>
                                                            <p class="fw-semi-bold mb-0 text-500">
                                                                <span class="badge  badge-subtle-secondary"><?php echo $row["transactionCost"];?></span>
                                                            </p>
                                                        </div>
                                                    </td>
                                                    <td class="align-middle text-start product">
                                                        <div class="d-flex align-items-center position-relative">
                                                            <div class="flex-1">
                                                                <h6 class="mb-1 fw-semi-bold text-nowrap">
                                                                    <?php
                                                                    if ($row["tag"] === "Mpesa") {
                                                                        echo '<span class="badge fs-10 w-100 rounded-pill badge-subtle-success">Mpesa</span>';
                                                                    } elseif ($row["tag"] === "Cash") {
                                                                        echo '<span class="badge fs-10 w-100 rounded-pill badge-subtle-warning">Cash</span>';
                                                                    } elseif (strtolower($row["tag"]) === "paypal") {
                                                                        echo '<span class="badge fs-10 w-100 rounded-pill badge-subtle-primary">PayPal</span>';
                                                                    } elseif ($row["tag"] === "Card") {
                                                                        echo '<span class="badge fs-10 w-100 rounded-pill badge-subtle-info">Card</span>';
                                                                    } elseif ($row["tag"] === "Airtel Money") {
                                                                        echo '<span class="badge fs-10 w-100 rounded-pill badge-subtle-danger">Airtel Money</span>';
                                                                    }
                                                                    else {
                                                                        echo htmlspecialchars($row["tag"]);
                                                                    }
                                                                    ?>
                                                                </h6>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="align-middle text-start amount">
                                                        <h6 class="mb-0 fw-semi-bold mb-0 text-500"><?php $originalDate = $row["date"];
                                                            $formattedDate = date("M j, Y", strtotime($originalDate));
                                                            echo $formattedDate;?>
                                                        </h6>
                                                    </td>
                                                    <td class="align-middle white-space-nowrap text-end position-relative">
                                                        <div class="hover-actions bg-100">
                                                            <a class="btn bg-primary-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm" data-bs-toggle="modal"  data-bs-target="#viewTransactionModal" data-bs-toggle="tooltip" data-bs-placement="top" title="View Transaction"
                                                               onclick="loadViewModal('<?php echo $row['id']; ?>', '<?php echo addslashes($row['category']); ?>', '<?php echo addslashes($row['subcategory']); ?>', '<?php echo addslashes($row['tag']); ?>', '<?php echo addslashes($row['description']); ?>', '<?php echo $row['amount']; ?>', '<?php echo $row['transactionCost']; ?>', '<?php echo date('M j, Y \a\\t H:i', strtotime($row['date'])); ?>', '<?php echo (isset($row['table_source']) && $row['table_source'] === 'tblbudget') ? 'TNSN' : 'OVDT'; ?>')"><span class="far fa-eye"></span></a>
                                                            <a class="btn bg-success-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm" data-bs-toggle="modal"  data-bs-target="#editTransactionModal" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Transaction"
                                                               onclick="loadEditModal('<?php echo $row['id']; ?>', '<?php echo addslashes($row['category']); ?>', '<?php echo addslashes($row['subcategory']); ?>', '<?php echo addslashes($row['tag']); ?>', '<?php echo addslashes($row['description']); ?>', '<?php echo $row['amount']; ?>', '<?php echo date('Y-m-d\TH:i', strtotime($row['date'])); ?>')"><span class="far fa-edit"></span></a>
                                                            <a class="btn bg-danger-subtle icon-item rounded-3 me-2 fs-11 icon-item-sm" data-bs-toggle="modal"   data-bs-target="#deleteTransactionModal" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete Transaction"
                                                               onclick="loadDeleteModal('<?php echo $row['id']; ?>')"><span class="fas fa-trash"></span></a>
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
    </div>

    <!-- Bulk Delete Button (shows when items are selected) -->
    <div class="card shadow-none border mb-3 d-none" id="bulk-actions-bar">
        <div class="card-body py-2">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle text-primary me-2 fs-4"></i>
                    <span id="selected-count" class="fw-bold">0</span>
                    <span class="ms-1">transaction(s) selected</span>
                </div>
                <div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmBulkDelete()">
                        <i class="fas fa-trash me-1"></i>
                        Delete Selected
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="clearSelection()">
                        <i class="fas fa-times me-1"></i>
                        Clear Selection
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Confirmation Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2 fs-4"></i>
                        <h5 class="modal-title mb-0" id="bulkDeleteModalLabel">Confirm Bulk Delete</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning border-0 d-flex align-items-start mb-3">
                        <div>
                            <strong>Warning:</strong> This action cannot be undone!
                        </div>
                    </div>

                    <p class="mb-3">
                        You are about to delete <strong id="delete-count" class="text-danger">0</strong> transaction(s).
                    </p>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmDeleteCheckbox">
                        <label class="form-check-label" for="confirmDeleteCheckbox">
                            I understand that this will permanently delete the selected transactions
                        </label>
                    </div>

                    <div id="transactions-to-delete" class="border rounded p-3 bg-secondary-subtle" style="max-height: 200px; overflow-y: auto;">
                        <!-- Transaction list will be populated here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn" onclick="executeBulkDelete()" disabled>
                        <i class="fas fa-trash me-1"></i>
                        Delete <span id="delete-count-btn">0</span> Transaction(s)
                    </button>
                </div>
            </div>
        </div>
    </div>


    <!-- Add Transaction Modal -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="add-transaction" method="post">
                    <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                        <div class="position-relative z-1">
                            <h4 class="mb-0 text-white" id="addTransactionModalLabel">Add Transaction</h4>
                        </div>
                        <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" name="category" id="category" aria-label="Default select example" required>
                                <option selected=""></option>
                                <option value="Expense">Expense</option>
                                <option value="Income">Income</option>
                                <option value="Savings">Savings</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="subcategory" class="form-label">Sub Category</label>
                            <input type="text" name="subcategory" id="subcategory" class="form-control" placeholder="Enter or select subcategory" list="subcategoryList" required>
                            <datalist id="subcategoryList">
                                <!-- Options dynamically populated -->
                            </datalist>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" name="description" id="description" class="form-control" placeholder="Enter or select description" list="descriptionList" required>
                            <datalist id="descriptionList">
                                <!-- Options dynamically populated -->
                            </datalist>
                        </div>
                        <div class="mb-3">
                            <label for="tag" class="form-label">Tag</label>
                            <select class="form-select" name="tag" id="tag" aria-label="Default select example" required>
                                <option selected=""></option>
                                <option value="Card">Card</option>
                                <option value="Cash">Cash</option>
                                <option value="Mpesa">MPesa</option>
                                <option value="PayPal">PayPal</option>
                                <option value="Airtel Money">Airtel Money</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" name="amount" id="amount" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="expenseDate" class="form-label">Transaction Date</label>
                            <input type="datetime-local" name="expenseDate" id="expenseDate" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary"><span class="fas fa-plus"></span> Add Transaction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- View Transaction Modal -->
    <div class="modal fade" id="viewTransactionModal" tabindex="-1" aria-labelledby="viewTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1">
                        <h4 class="mb-0 text-white" id="viewTransactionModalLabel">Transaction Details</h4>
                        <p class="fs-10 text-white mb-0 opacity-75">Reference: <span id="viewTransactionRef">#</span></p>
                    </div>
                    <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Amount highlight card -->
                    <div class="text-center mb-4 p-3 rounded-3 bg-light border">
                        <p class="fs-10 text-600 mb-1 fw-semi-bold text-uppercase">Amount</p>
                        <h2 class="fw-bold mb-1 text-primary">Ksh <span id="viewAmount">0</span></h2>
                        <p class="fs-10 text-500 mb-0">Transaction cost: <span class="badge badge-subtle-secondary" id="viewTransactionCost">0</span></p>
                    </div>

                    <!-- Detail grid -->
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="p-2 border rounded-3 h-100">
                                <p class="fs-11 text-600 mb-1 fw-semi-bold text-uppercase">
                                    <i class="fas fa-tag me-1 text-info"></i>Sub Category
                                </p>
                                <h6 class="mb-0 fw-bold" id="viewSubcategory">-</h6>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded-3 h-100">
                                <p class="fs-11 text-600 mb-1 fw-semi-bold text-uppercase">
                                    <i class="fas fa-folder me-1 text-primary"></i>Category
                                </p>
                                <h6 class="mb-0 fw-bold" id="viewCategory">-</h6>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded-3">
                                <p class="fs-11 text-600 mb-1 fw-semi-bold text-uppercase">
                                    <i class="fas fa-align-left me-1 text-success"></i>Description
                                </p>
                                <h6 class="mb-0 fw-semi-bold" id="viewDescription" style="word-break: break-word; white-space: normal;">-</h6>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded-3 h-100">
                                <p class="fs-11 text-600 mb-1 fw-semi-bold text-uppercase">
                                    <i class="fas fa-wallet me-1 text-warning"></i>Platform
                                </p>
                                <h6 class="mb-0 fw-bold" id="viewTag">-</h6>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded-3 h-100">
                                <p class="fs-11 text-600 mb-1 fw-semi-bold text-uppercase">
                                    <i class="fas fa-calendar-alt me-1 text-danger"></i>Date
                                </p>
                                <h6 class="mb-0 fw-bold" id="viewDate">-</h6>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Transaction Modal -->
    <div class="modal fade" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editTransactionForm" method="POST" action="edit-transaction">
                    <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                        <div class="position-relative z-1">
                            <h4 class="mb-0 text-white" id="editTransactionModalLabel">Edit Transaction</h4>
                        </div>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="budgetID" id="editBudgetID">
                        <div class="mb-3">
                            <label for="editCategory" class="form-label">Category</label>
                            <select class="form-select" id="editCategory" name="category" required>
                                <option value="Expense">Expense</option>
                                <option value="Income">Income</option>
                                <option value="Savings">Savings</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editSubCategory" class="form-label">Sub Category</label>
                            <input type="text" class="form-control" id="editSubcategory" name="subcategory" required>
                        </div>
                        <div class="mb-3">
                            <label for="editDescription" class="form-label">Description</label>
                            <input type="text" class="form-control" id="editDescription" name="description" rows="3" required>
                        </div>
                        <div class="mb-3">
                            <label for="editAmount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="editAmount" name="amount" step="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="editTag" class="form-label">Tag</label>
                            <select class="form-select" id="editTag" name="tag" required>
                                <option value="Card">Card</option>
                                <option value="Cash">Cash</option>
                                <option value="Mpesa">Mpesa</option>
                                <option value="PayPal">PayPal</option>
                                <option value="Airtel Money">Airtel Money</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editDate" class="form-label">Date</label>
                            <input type="datetime-local" class="form-control" id="editDate" name="date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary"><span class="fas fa-save"></span> Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Transaction Modal -->
    <div class="modal fade" id="deleteTransactionModal" tabindex="-1" aria-labelledby="deleteTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="deleteTransactionForm" method="POST" action="delete-transaction">
                <div class="modal-content">
                    <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                        <div class="position-relative z-1">
                            <h4 class="mb-0 text-white" id="deleteTransactionModalLabel">Delete Transaction</h4>
                        </div>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="budgetID" id="deleteBudgetID">
                        <p>Are you sure you want to delete this transaction?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger"><span class="fas fa-trash"></span> Delete</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal fade" id="importHelpModal" tabindex="-1" aria-labelledby="importHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importHelpModalLabel">Import File Format</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>The import file should be in CSV format with the following columns:</p>
                    <ol>
                        <li><strong>Category</strong> - Transaction category (Expense, Income, Savings)</li>
                        <li><strong>Subcategory</strong> - Transaction subcategory</li>
                        <li><strong>Description</strong> - Transaction description</li>
                        <li><strong>Amount (Ksh)</strong> - Transaction amount</li>
                        <li><strong>Cost (Ksh)</strong> - Transaction cost</li>
                        <li><strong>Tag</strong> - Payment method (Mpesa, Cash, PayPal, Card, Airtel Money)</li>
                        <li><strong>Date</strong> - Transaction date in format "MMM D, YYYY H:MM" (e.g., "May 10, 2025 15:30")</li>
                    </ol>
                    <p>You can download a template by exporting your current transactions.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Populate the View Transaction modal from data passed via onclick.
        // This is called from both the eye button and the description cell.
        function loadViewModal(id, category, subcategory, tag, description, amount, transactionCost, date, sourcePrefix) {
            document.getElementById('viewTransactionRef').innerText = (sourcePrefix || '') + ' #' + id;
            document.getElementById('viewCategory').innerText = category || '-';
            document.getElementById('viewSubcategory').innerText = subcategory || '-';
            document.getElementById('viewDescription').innerText = description || '-';
            document.getElementById('viewAmount').innerText = amount || '0';
            document.getElementById('viewTransactionCost').innerText = transactionCost || '0';
            document.getElementById('viewTag').innerText = tag || '-';
            document.getElementById('viewDate').innerText = date || '-';
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Wire up Edit and Delete modals using row data (View modal is handled by loadViewModal)
            const tableRows = document.querySelectorAll('.hover-actions-trigger');
            tableRows.forEach(row => {
                const editBtn = row.querySelector('[data-bs-target="#editTransactionModal"]');
                const deleteBtn = row.querySelector('[data-bs-target="#deleteTransactionModal"]');

                // For edit modal we still read from the DOM, but use the description cell's title attribute
                // (which contains the full untruncated description) rather than the visible truncated text.
                const descCell = row.querySelector('td.description-cell p');
                const fullDescription = descCell ? (descCell.getAttribute('title') || descCell.innerText) : '';

                const rowData = {
                    id: row.querySelector('input[type="checkbox"]').value,
                    category: row.querySelector('td:nth-child(2) h6').innerText,
                    subcategory: row.querySelector('td:nth-child(3) h6').innerText,
                    description: fullDescription,
                    amount: row.querySelector('td:nth-child(4) h6').innerText,
                    transactionCost: row.querySelector('td:nth-child(4) p').innerText,
                    tag: row.querySelector('td:nth-child(5) h6').innerText,
                    date: row.querySelector('td:nth-child(6) h6').innerText,
                };

                // Populate Edit Modal
                if (editBtn) {
                    editBtn.addEventListener('click', () => {
                        document.getElementById('editBudgetID').value = rowData.id;
                        document.getElementById('editCategory').value = rowData.category;
                        document.getElementById('editSubcategory').value = rowData.subcategory;
                        document.getElementById('editDescription').value = rowData.description;
                        document.getElementById('editAmount').value = rowData.amount;
                        document.getElementById('editTag').value = rowData.tag;
                        // Format date for datetime-local input with GMT+3 adjustment
                        const date = new Date(rowData.date);
                        date.setHours(date.getHours() + 3);
                        const formattedDate = date.toISOString().slice(0, 16);
                        document.getElementById('editDate').value = formattedDate;
                    });
                }
            });
        });
    </script>

    <script>
        // ORIGINAL DOM-BASED LISTENER (kept disabled — replaced by loadViewModal above)
        document.addEventListener('DOMContentLoaded', () => {
            // Load data into modals dynamically
            const tableRows = document.querySelectorAll('.hover-actions-trigger');
            tableRows.forEach(row => {
                const viewBtn = row.querySelector('[data-bs-target="#viewTransactionModal"]');
                const editBtn = row.querySelector('[data-bs-target="#editTransactionModal"]');
                const deleteBtn = row.querySelector('[data-bs-target="#deleteTransactionModal"]');
                const rowData = {
                    id: row.querySelector('input[type="checkbox"]').value, // Use 'id' consistently
                    category: row.querySelector('td:nth-child(2) h6').innerText,
                    subcategory: row.querySelector('td:nth-child(3) h6').innerText,
                    description: row.querySelector('td:nth-child(3) p').innerText,
                    amount: row.querySelector('td:nth-child(4) h6').innerText,
                    transactionCost: row.querySelector('td:nth-child(4) p').innerText,
                    tag: row.querySelector('td:nth-child(5) h6').innerText,
                    date: row.querySelector('td:nth-child(6) h6').innerText,
                };

                // Populate View Modal -- DISABLED: now handled by loadViewModal()
                // viewBtn.addEventListener('click', () => { ... });

                // Populate Delete Modal
                deleteBtn.addEventListener('click', () => {
                    document.getElementById('deleteBudgetID').value = rowData.id; // Ensure 'id' is passed here
                });
            });
        });


        function toggleAmountVisibility(amountId, btn) {
            const amountElement = document.getElementById(amountId); // Target the amount element
            const icon = btn.querySelector('.fas'); // Target the icon inside the button

            if (amountElement.classList.contains('blurred-text')) {
                // Show the amount
                amountElement.classList.remove('blurred-text');
                amountElement.classList.add('visible-text');
                icon.classList.remove('fa-eye'); // Change to slashed-eye icon
                icon.classList.add('fa-eye-slash');
            } else {
                // Hide the amount
                amountElement.classList.remove('visible-text');
                amountElement.classList.add('blurred-text');
                icon.classList.remove('fa-eye-slash'); // Change back to regular eye icon
                icon.classList.add('fa-eye');
            }
        }

        function toggleFinancialVisibility() {
            const financialAmounts = document.querySelectorAll('#financialOverviewSection .blur-financial-data, #financialOverviewSection .visible-financial-data');
            const financialIcon = document.getElementById('financialToggleIcon');

            financialAmounts.forEach(amount => {
                if (amount.classList.contains('blur-financial-data')) {
                    // If blurred, make visible
                    amount.classList.remove('blur-financial-data');
                    amount.classList.add('visible-financial-data');
                } else if (amount.classList.contains('visible-financial-data')) {
                    // If visible, blur it again
                    amount.classList.remove('visible-financial-data');
                    amount.classList.add('blur-financial-data');
                }
            });

            // Toggle the eye icon
            if (financialIcon.classList.contains('fa-eye')) {
                financialIcon.classList.remove('fa-eye');
                financialIcon.classList.add('fa-eye-slash');
            } else {
                financialIcon.classList.remove('fa-eye-slash');
                financialIcon.classList.add('fa-eye');
            }
        }

    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Show the loader initially
            const spinner = document.getElementById('loading-spinner');
            const table = document.getElementById('transaction-table');

            // Simulate a delay to mimic data fetching
            setTimeout(() => {
                // Hide the spinner and show the table
                spinner.classList.add('d-none');
                table.classList.remove('d-none');
            }, 1000); // Adjust the timeout as per actual data fetching duration
        });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const categoryField = document.getElementById("category");
            const subCategoryField = document.getElementById("subcategory");
            const subCategoryDatalist = document.getElementById("subcategoryList");
            const descriptionField = document.getElementById("description");
            const descriptionDatalist = document.getElementById("descriptionList");

            categoryField.addEventListener("change", async function () {
                const category = categoryField.value;

                // Reset subcategory and description datalists
                subCategoryDatalist.innerHTML = '';
                descriptionDatalist.innerHTML = '';
                subCategoryField.value = '';
                descriptionField.value = '';

                if (category === "Savings") {
                    // Populate subcategory datalist for Savings
                    const savingsOptions = ["MMF", "Sacco"];
                    savingsOptions.forEach(option => {
                        const opt = document.createElement("option");
                        opt.value = option;
                        subCategoryDatalist.appendChild(opt);
                    });

                    // Fetch goal names for the Description field
                    try {
                        const response = await fetch("fetch-savings-goals");
                        const goals = await response.json(); // Assuming JSON response from the server

                        goals.forEach(goal => {
                            const opt = document.createElement("option");
                            opt.value = goal.goalName;
                            descriptionDatalist.appendChild(opt);
                        });
                    } catch (error) {
                        console.error("Error fetching savings goals:", error);
                    }
                } else if (category === "Income") {
                    // Populate subcategory datalist for Income
                    const incomeOptions = ["Writing"];
                    incomeOptions.forEach(option => {
                        const opt = document.createElement("option");
                        opt.value = option;
                        subCategoryDatalist.appendChild(opt);
                    });

                    // Populate description datalist for Income
                    const incomeDescriptions = ["Freelance"];
                    incomeDescriptions.forEach(option => {
                        const opt = document.createElement("option");
                        opt.value = option;
                        descriptionDatalist.appendChild(opt);
                    });
                }
            });

            // Enable manual updates (allow users to type custom values)
            subCategoryField.addEventListener("input", function () {
                subCategoryField.setAttribute("value", subCategoryField.value);
            });

            descriptionField.addEventListener("input", function () {
                descriptionField.setAttribute("value", descriptionField.value);
            });
        });
    </script>
    <script>
        // Track selected checkboxes
        let selectedTransactions = new Set();

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add change listener to all transaction checkboxes
            document.querySelectorAll('input[name="taskIds[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectedTransactions();
                });
            });

            // Add listener to "Select All" checkbox
            const selectAllCheckbox = document.getElementById('checkbox-select-all');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('input[name="taskIds[]"]');
                    checkboxes.forEach(cb => {
                        cb.checked = this.checked;
                    });
                    updateSelectedTransactions();
                });
            }

            // Enable/disable delete button based on checkbox
            document.getElementById('confirmDeleteCheckbox').addEventListener('change', function() {
                document.getElementById('confirmDeleteBtn').disabled = !this.checked;
            });
        });

        // Update selected transactions count and show/hide bulk actions bar
        function updateSelectedTransactions() {
            selectedTransactions.clear();

            document.querySelectorAll('input[name="taskIds[]"]:checked').forEach(checkbox => {
                selectedTransactions.add({
                    id: checkbox.value,
                    row: checkbox.closest('tr')
                });
            });

            const count = selectedTransactions.size;
            document.getElementById('selected-count').textContent = count;

            // Show/hide bulk actions bar
            const bulkActionsBar = document.getElementById('bulk-actions-bar');
            if (count > 0) {
                bulkActionsBar.classList.remove('d-none');
            } else {
                bulkActionsBar.classList.add('d-none');
            }

            // Update "Select All" checkbox state
            const selectAllCheckbox = document.getElementById('checkbox-select-all');
            const allCheckboxes = document.querySelectorAll('input[name="taskIds[]"]');
            if (selectAllCheckbox && allCheckboxes.length > 0) {
                selectAllCheckbox.checked = count === allCheckboxes.length;
                selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
            }
        }

        // Show confirmation modal
        function confirmBulkDelete() {
            if (selectedTransactions.size === 0) {
                alert('Please select at least one transaction to delete.');
                return;
            }

            // Update counts
            document.getElementById('delete-count').textContent = selectedTransactions.size;
            document.getElementById('delete-count-btn').textContent = selectedTransactions.size;

            // Build list of transactions to delete
            let transactionsList = '<div class="small">';
            let count = 0;
            selectedTransactions.forEach(transaction => {
                if (count < 10) { // Show first 10
                    const row = transaction.row;
                    const category = row.querySelector('.product h6').textContent.trim();
                    const description = row.querySelector('.product p').textContent.trim();
                    const amount = row.querySelector('.amount h6').textContent.trim();

                    transactionsList += `
                <div class="mb-2 pb-2 border-bottom">
                    <div class="d-flex justify-content-between">
                        <span>${category}</span>
                        <strong>KSh ${amount}</strong>
                    </div>
                    <div class="text-muted">${description}</div>
                </div>
            `;
                }
                count++;
            });

            if (selectedTransactions.size > 10) {
                transactionsList += `<div class="text-center text-muted mt-2">... and ${selectedTransactions.size - 10} more</div>`;
            }

            transactionsList += '</div>';

            document.getElementById('transactions-to-delete').innerHTML = transactionsList;

            // Reset confirmation checkbox
            document.getElementById('confirmDeleteCheckbox').checked = false;
            document.getElementById('confirmDeleteBtn').disabled = true;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
            modal.show();
        }

        // Execute bulk delete
        function executeBulkDelete() {
            if (!document.getElementById('confirmDeleteCheckbox').checked) {
                alert('Please confirm that you want to delete these transactions.');
                return;
            }

            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            // Add hidden input for bulk_delete action
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'bulk_delete';
            actionInput.value = '1';
            form.appendChild(actionInput);

            // Add all selected transaction IDs
            selectedTransactions.forEach(transaction => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'taskIds[]';
                input.value = transaction.id;
                form.appendChild(input);
            });

            // Submit form
            document.body.appendChild(form);
            form.submit();
        }

        // Clear all selections
        function clearSelection() {
            document.querySelectorAll('input[name="taskIds[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('checkbox-select-all').checked = false;
            updateSelectedTransactions();
        }

        // Legacy function for single delete (keep existing functionality)
        function loadDeleteModal(id) {
            document.getElementById('deleteBudgetID').value = id;
        }
    </script>


<?php
include "footer.php";
?>