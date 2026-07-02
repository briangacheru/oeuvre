<?php include "head.php";?>
    <title>Budget Tracker</title>
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
                    <h4 class="mb-0 text-primary fw-bold">Finance<span class="text-info fw-medium"> Management</span></h4>
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
    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xxl-3">
            <div class="card overflow-hidden h-md-100 ecommerce-card-min-width">
                <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/authentication-corner.png);">
                </div>
                <div class="card-header pb-0">
                    <?php
                    $currentMonthIncome = 0;
                    $previousMonthIncome = 0;
                    $query = "
                        SELECT SUM(CASE 
                                WHEN category = 'Income' AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') 
                                THEN amount ELSE 0 END) AS currentMonthIncome,
                            SUM(CASE 
                                WHEN category = 'Income' AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m') 
                                THEN amount ELSE 0 END) AS previousMonthIncome
                        FROM tblbudget WHERE is_deleted = 0";
                    $result = mysqli_query($con, $query);
                    if ($result) {
                        $row = mysqli_fetch_assoc($result);
                        $currentMonthIncome = $row['currentMonthIncome'] ?? 0;
                        $previousMonthIncome = $row['previousMonthIncome'] ?? 0;
                    } else {
                        $currentMonthIncome = $previousMonthIncome = "No data"; // Set "No Data" if query fails
                    }

                    // Calculate the percentage change
                    $percentageChange = 0;
                    $badgeClass = "badge-subtle-primary"; // Default class for 0%
                    if ($previousMonthIncome > 0) {
                        $percentageChange = (($currentMonthIncome - $previousMonthIncome) / $previousMonthIncome) * 100;
                        $badgeClass = $percentageChange > 0 ? "badge-subtle-success" : "badge-subtle-danger";
                    }

                    // Round the percentage change to 1 decimal place
                    $percentageChange = round($percentageChange, 2);
                    ?>
                    <h6 class="mb-0 mt-2 d-flex align-items-center"><?php echo date("F"); ?> Income<span class="ms-1 text-400" data-bs-toggle="tooltip" data-bs-placement="top"></span></h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-end">
                    <div class="row justify-content-between">
                        <div class="col-auto align-self-end">
                            <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-info amount-container" id="currentMonthIncome">
                                <span class="blurred-text" id="incomeAmount"><?php echo "Ksh " . number_format($currentMonthIncome); ?></span>
                            </div>
                            <!-- Eye Icon for Toggling -->
                            <button class="btn btn-link btn-sm position-absolute top-0 end-0 text-secondary" onclick="toggleAmountVisibility('incomeAmount', this)">
                                <span class="fas fa-eye"></span>
                            </button>
                            <p class="fs-11 mb-0 text-nowrap" data-bs-toggle="tooltip" data-bs-placement="top" title="Previous month">vs Ksh. <?php echo number_format($previousMonthIncome); ?></p>
                            <span class="badge <?php echo $badgeClass; ?> rounded-pill fs-11" data-bs-toggle="tooltip" data-bs-placement="top" title="Percentage change">
                                <?php
                                if ($percentageChange > 0) {
                                    echo "+" . $percentageChange . "%";
                                } elseif ($percentageChange < 0) {
                                    echo $percentageChange . "%";
                                } else {
                                    echo "0%";
                                }
                                ?>
                            </span>
                        </div>
                        <div class="col-auto text-center ps-2">
                            <div class="fs-9 fw-normal font-sans-serif mb-1 lh-1 badge badge-subtle-info rounded-pill">100%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xxl-3">
            <div class="card h-md-100">
                <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-1.png);">
                </div>
                <div class="card-header pb-0">
                    <?php
                    $currentMonthExpense = 0;
                    $previousMonthExpense = 0;
                    $queryExpense = "SELECT SUM(CASE WHEN category = 'Expense' AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') 
                                        THEN amount + IFNULL(transactionCost, 0) ELSE 0 END) AS currentMonthExpense,
                                    SUM(CASE WHEN category = 'Expense' AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m') 
                                        THEN amount + IFNULL(transactionCost, 0) ELSE 0 END) AS previousMonthExpense
                                FROM (SELECT category, amount, transactionCost, expenseDate 
                                    FROM tblbudget WHERE is_deleted = 0) AS combined";

                    $resultExpense = mysqli_query($con, $queryExpense);
                    if ($resultExpense) {
                        $rowExpense = mysqli_fetch_assoc($resultExpense);
                        $currentMonthExpense = $rowExpense['currentMonthExpense'] ?? 0;
                        $previousMonthExpense = $rowExpense['previousMonthExpense'] ?? 0;
                    } else {
                        $currentMonthExpense = $previousMonthExpense = "No data"; // Set "No Data" if query fails
                    }
                    // Calculate the percentage change between current and previous month
                    $percentageChange1 = 0;
                    $badgeClass = "badge-subtle-primary"; // Default class for 0%
                    if ($previousMonthExpense > 0) {
                        $percentageChange1 = (($currentMonthExpense - $previousMonthExpense) / $previousMonthExpense) * 100;
                        $badgeClass = $percentageChange1 > 0 ? "badge-subtle-success" : "badge-subtle-danger";
                    }
                    $percentageChange1 = round($percentageChange1, 2);
                    // Calculate the percentage of expenses in the current month
                    $monthlyPercentageOfExpenses = 0;
                    if ($currentMonthIncome > 0) {
                        $monthlyPercentageOfExpenses = ($currentMonthExpense / $currentMonthIncome) * 100;
                    }
                    $monthlyPercentageOfExpenses = round($monthlyPercentageOfExpenses, 1);
                    ?>
                    <h6 class="mb-0 mt-2"><?php echo date("F"); ?> Expenses</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-end">
                    <div class="row justify-content-between">
                        <div class="col-auto align-self-end">
                            <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-danger" id="currentMonthExpense">
                                <span class="blurred-text" id="expenseAmount"><?php echo "Ksh " . number_format($currentMonthExpense); ?></span>
                            </div>
                            <!-- Eye Icon for Toggling -->
                            <button class="btn btn-link btn-sm position-absolute top-0 end-0 text-secondary" onclick="toggleAmountVisibility('expenseAmount', this)">
                                <span class="fas fa-eye"></span>
                            </button>
                            <p class="fs-11 mb-0 text-nowrap">vs Ksh <?php echo number_format($previousMonthExpense); ?></p>
                            <span class="badge <?php echo $badgeClass; ?> rounded-pill fs-11">
                                <?php
                                if ($percentageChange1 > 0) {
                                    echo "+" . $percentageChange1 . "%";
                                } elseif ($percentageChange1 < 0) {
                                    echo $percentageChange1 . "%";
                                } else {
                                    echo "0%";
                                }
                                ?>
                            </span>
                        </div>
                        <div class="col-auto text-center ps-2">
                            <div class="fs-9 fw-normal font-sans-serif mb-1 lh-1 badge badge-subtle-danger rounded-pill"><?php echo $monthlyPercentageOfExpenses; ?>%</div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xxl-3">
            <div class="card h-md-100">
                <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-3.png);">
                </div>
                <div class="card-header d-flex flex-between-center pb-0">
                    <?php
                    $currentMonthSavings = 0;
                    $previousMonthSavings = 0;
                    $querySavings = "
                                        SELECT 
                                            SUM(CASE 
                                                WHEN category = 'Savings' AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') 
                                                THEN amount ELSE 0 END) AS currentMonthSavings,
                                            SUM(CASE 
                                                WHEN category = 'Savings' AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m') 
                                                THEN amount ELSE 0 END) AS previousMonthSavings
                                        FROM tblbudget 
                                        WHERE is_deleted = 0
                                    ";
                    $resultSavings = mysqli_query($con, $querySavings);
                    if ($resultSavings) {
                        $rowSavings = mysqli_fetch_assoc($resultSavings);
                        $currentMonthSavings = $rowSavings['currentMonthSavings'] ?? 0;
                        $previousMonthSavings = $rowSavings['previousMonthSavings'] ?? 0;
                    } else {
                        $currentMonthSavings = $previousMonthSavings = "No data"; // Set "No Data" if query fails
                    }

                    // Calculate the percentage change of the previous and current month
                    $percentageChangeSavings = 0;
                    $badgeClassSavings = "badge-subtle-primary"; // Default class for 0%
                    if ($previousMonthSavings > 0) {
                        $percentageChangeSavings = (($currentMonthSavings - $previousMonthSavings) / $previousMonthSavings) * 100;
                        $badgeClassSavings = $percentageChangeSavings > 0 ? "badge-subtle-success" : "badge-subtle-danger";
                    }
                    $percentageChangeSavings = round($percentageChangeSavings, 2);

                    // Calculate the percentage of savings in the current month
                    $monthlyPercentageOfSavings = 0;
                    if ($currentMonthIncome > 0) {
                        $monthlyPercentageOfSavings = ($currentMonthSavings / $currentMonthIncome) * 100;
                    }
                    $monthlyPercentageOfSavings = round($monthlyPercentageOfSavings, 1);
                    ?>
                    <h6 class="mb-0"><?php echo date("F"); ?> Savings</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-end">
                    <div class="row justify-content-between">
                        <div class="col-auto align-self-end">
                            <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-success" id="currentMonthSavings">
                                <span class="blurred-text" id="savingsAmount"><?php echo "Ksh " . number_format($currentMonthSavings); ?></span>
                                <!-- Eye Icon for Toggling -->
                                <button class="btn btn-link btn-sm position-absolute top-0 end-0 text-secondary" onclick="toggleAmountVisibility('savingsAmount', this)">
                                    <span class="fas fa-eye"></span>
                                </button>
                            </div>
                            <p class="fs-11 mb-0 text-nowrap">vs Ksh <?php echo number_format($previousMonthSavings); ?></p>
                            <span class="badge <?php echo $badgeClassSavings; ?> rounded-pill fs-11">
                                        <?php
                                        if ($percentageChangeSavings > 0) {
                                            echo "+" . $percentageChangeSavings . "%";
                                        } elseif ($percentageChangeSavings < 0) {
                                            echo $percentageChangeSavings . "%";
                                        } else {
                                            echo "0%";
                                        }
                                        ?>
                                    </span>
                        </div>
                        <div class="col-auto text-center ps-2">
                            <div class="fs-9 fw-normal font-sans-serif mb-1 lh-1 badge badge-subtle-success rounded-pill"><?php echo $monthlyPercentageOfSavings; ?>%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xxl-3">
            <div class="card h-md-100">
                <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-4.png);">
                </div>
                <div class="card-header d-flex flex-between-center pb-0">
                    <h6 class="mb-0"><?php echo date("Y"); ?> Transaction Summary</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-end">
                    <div class="row justify-content-between">
                        <div class="col align-self-end" id="financialOverviewSection">
                            <div class="display-4 fs-5 mb-2 fw-normal font-sans-serif text-success" id="currentMonthSavings">
                                <button class="btn btn-link btn-sm position-absolute top-0 end-0 text-secondary" onclick="toggleFinancialVisibility()">
                                    <span id="financialToggleIcon" class="fas fa-eye"></span>
                                </button>
                            </div>
                            <div class="d-flex flex-between-center mb-2">
                                <?php
                                $currentYearIncome = 0;
                                // Query to calculate the total income for the current year
                                $query = "
                                        SELECT SUM(amount) AS totalIncome
                                        FROM tblbudget
                                        WHERE category = 'Income' 
                                          AND YEAR(expenseDate) = YEAR(CURDATE()) 
                                          AND is_deleted = 0
                                    ";
                                $result = mysqli_query($con, $query);
                                if ($result) {
                                    $row = mysqli_fetch_assoc($result);
                                    $currentYearIncome = $row['totalIncome'] ?? 0;
                                }
                                ?>
                                <div class="d-flex align-items-center">
                                    <span class="dot bg-primary"></span>
                                    <span class="fw-semi-bold"><?php echo date("Y"); ?> Income</span>
                                </div>
                                <span class="text-900 blur-financial-data" id="financialIncomeAmount">
                                        Ksh: <?php echo number_format($currentYearIncome); ?>
                                    </span>
                                <span class="badge badge-subtle-primary rounded-pill fs-11">
                                        100%
                                    </span>
                            </div>
                            <div class="d-flex flex-between-center mb-2">
                                <?php
                                $currentYearIncome = 0;
                                $currentYearExpenses = 0;
                                // Query to calculate the total income and expenses for the current year
                                $query = "SELECT SUM(CASE WHEN category = 'Income' AND YEAR(expenseDate) = YEAR(CURDATE()) 
                                            THEN amount + IFNULL(transactionCost, 0) ELSE 0 END) AS totalIncome,
                                        SUM(CASE WHEN category = 'Expense' AND YEAR(expenseDate) = YEAR(CURDATE()) 
                                            THEN amount + IFNULL(transactionCost, 0) ELSE 0 END) AS totalExpenses
                                    FROM (SELECT category, amount, transactionCost, expenseDate 
                                        FROM tblbudget WHERE is_deleted = 0) AS combined";

                                $result = mysqli_query($con, $query);
                                if ($result) {
                                    $row = mysqli_fetch_assoc($result);
                                    $currentYearIncome = $row['totalIncome'] ?? 0;
                                    $currentYearExpenses = $row['totalExpenses'] ?? 0;
                                }
                                // Calculate the percentage of expenses relative to income
                                $percentageOfExpenses = 0;
                                if ($currentYearIncome > 0) {
                                    $percentageOfExpenses = ($currentYearExpenses / $currentYearIncome) * 100;
                                }
                                // Round the percentage to 1 decimal place
                                $percentageOfExpenses = round($percentageOfExpenses, 1);
                                ?>
                                <div class="d-flex align-items-center">
                                    <span class="dot bg-danger"></span>
                                    <span class="fw-semi-bold"><?php echo date("Y"); ?> Expenses</span>
                                </div>
                                <span class="text-900 blur-financial-data" id="financialExpensesAmount">
                                    Ksh: <?php echo number_format($currentYearExpenses); ?>
                                </span>
                                <span class="badge badge-subtle-danger rounded-pill fs-11">
                                    <?php echo $percentageOfExpenses; ?>%
                                </span>
                            </div>
                            <div class="d-flex flex-between-center mb-1">
                                <?php
                                $currentYearIncome = 0;
                                $currentYearSavings = 0;
                                // Query to calculate the total income and savings for the current year
                                $query = "
                                    SELECT 
                                        SUM(CASE WHEN category = 'Income' AND YEAR(expenseDate) = YEAR(CURDATE()) THEN amount ELSE 0 END) AS totalIncome,
                                        SUM(CASE WHEN category = 'Savings' AND YEAR(expenseDate) = YEAR(CURDATE()) THEN amount ELSE 0 END) AS totalSavings
                                    FROM tblbudget
                                    WHERE is_deleted = 0
                                ";
                                $result = mysqli_query($con, $query);
                                if ($result) {
                                    $row = mysqli_fetch_assoc($result);
                                    $currentYearIncome = $row['totalIncome'] ?? 0;
                                    $currentYearSavings = $row['totalSavings'] ?? 0;
                                }
                                // Calculate the percentage of savings relative to income
                                $percentageOfSavings = 0;
                                $newcurrentYearSavings = ($currentYearIncome - $currentYearExpenses);
                                $newcurrentYearSavings = round($newcurrentYearSavings, 1);
                                if ($currentYearIncome > 0) {
                                    $percentageOfSavings = ($newcurrentYearSavings / $currentYearIncome) * 100;
                                }
                                // Round the percentage to 1 decimal place
                                $percentageOfSavings = round($percentageOfSavings, 1);
                                ?>
                                <div class="d-flex align-items-center">
                                    <span class="dot bg-success"></span>
                                    <span class="fw-semi-bold"><?php echo date("Y"); ?> Savings</span>
                                </div>
                                <div class="text-900 blur-financial-data" id="financialSavingsAmount">
                                    Ksh: <?php echo number_format($newcurrentYearSavings); ?>
                                </div>
                                <div>
                                    <span class="badge badge-subtle-success rounded-pill fs-11">
                                        <?php echo $percentageOfSavings; ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-0">
        <div class="col-lg-6 pe-lg-2 mb-3">
            <div class="card h-lg-100 overflow-hidden">
                <div class="card-header bg-body-tertiary">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="mb-0">
                                <?php
                                // Query to fetch total transaction costs for the current month, current year, previous month, and previous year
                                $query = "SELECT 
                                -- Current Month Transaction Costs
                                (SELECT SUM(transactionCost) 
                                 FROM tblbudget 
                                 WHERE is_deleted = 0 AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')) AS totalTransactionCostCurrentMonth,
                                
                                -- Current Year Transaction Costs
                                (SELECT SUM(transactionCost) 
                                 FROM tblbudget 
                                 WHERE is_deleted = 0 AND YEAR(expenseDate) = YEAR(CURDATE())) AS totalTransactionCostCurrentYear,
                            
                                -- Previous Month Transaction Costs
                                (SELECT SUM(transactionCost) 
                                 FROM tblbudget 
                                 WHERE is_deleted = 0 AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m')) AS totalTransactionCostPreviousMonth,
                                
                                -- Previous Year Transaction Costs
                                (SELECT SUM(transactionCost) 
                                 FROM tblbudget 
                                 WHERE is_deleted = 0 AND YEAR(expenseDate) = YEAR(CURDATE()) - 1) AS totalTransactionCostPreviousYear";

                                $result = mysqli_query($con, $query);

                                if ($result) {
                                    $row = mysqli_fetch_assoc($result);
                                    $totalTransactionCostCurrentMonth = $row['totalTransactionCostCurrentMonth'] ?? 0;
                                    $totalTransactionCostCurrentYear = $row['totalTransactionCostCurrentYear'] ?? 0;
                                    $totalTransactionCostPreviousMonth = $row['totalTransactionCostPreviousMonth'] ?? 0;
                                    $totalTransactionCostPreviousYear = $row['totalTransactionCostPreviousYear'] ?? 0;
                                } else {
                                    $totalTransactionCostCurrentMonth = 0;
                                    $totalTransactionCostCurrentYear = 0;
                                    $totalTransactionCostPreviousMonth = 0;
                                    $totalTransactionCostPreviousYear = 0;
                                }
                                ?>
                                <span class="fas fa-info-circle me-2"></span>
                                <?php echo date("F"); ?> Transaction cost
                            </h6>
                        </div>
                        <div class="col-auto text-center pe-x1">
                            <span class="badge rounded-pill badge-subtle-danger">Ksh. <?php echo number_format($totalTransactionCostCurrentMonth, 2); ?></span>
                            <span class="fs-11 mb-0 text-nowrap" data-bs-toggle="tooltip" data-bs-placement="top" title="Previous month">vs Ksh. <?php echo number_format($totalTransactionCostPreviousMonth, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 ps-lg-2 mb-3">
            <div class="card h-lg-100">
                <div class="card-header">
                    <div class="row flex-between-center">
                        <div class="col-auto">
                            <h6 class="mb-0">
                                <span class="fas fa-info-circle me-2"></span>
                                <?php echo date("M"); ?> Expenses W/O WP
                            </h6>
                        </div>
                        <?php
                        $currentMonthQuery = "SELECT 0 AS totalAmount";

                        $currentMonthResult = mysqli_query($con, $currentMonthQuery);
                        $currentMonthRow = mysqli_fetch_assoc($currentMonthResult);
                        $currentMonthTotal = isset($currentMonthRow['totalAmount']) ? $currentMonthRow['totalAmount'] : 0;
                        $currentMonthWOWP = ($currentMonthExpense - $currentMonthTotal);

                        $previousMonthQuery = "SELECT 0 AS totalAmount";

                        $previousMonthResult = mysqli_query($con, $previousMonthQuery);
                        $previousMonthRow = mysqli_fetch_assoc($previousMonthResult);
                        $previousMonthTotal = isset($previousMonthRow['totalAmount']) ? $previousMonthRow['totalAmount'] : 0;
                        $previousMonthWOWP = ($previousMonthExpense - $previousMonthTotal);
                        ?>
                        <div class="col-auto text-center pe-x1">
                            <span class="badge rounded-pill badge-subtle-danger">Ksh. <?php echo number_format($currentMonthWOWP, 2); ?></span>
                            <span class="fs-11 mb-0 text-nowrap" data-bs-toggle="tooltip" data-bs-placement="top" title="Previous year">vs Ksh. <?php echo number_format($previousMonthWOWP, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <?php
        // ==========================================
        // 1. CURRENT MONTH (INTERVAL 0 MONTH)
        // ==========================================

        // Query to get expenses breakdown for current month including total expense
        $query = "
WITH total_expense AS (
    SELECT 
        SUM(amount + IFNULL(transactionCost, 0)) AS grand_total
    FROM (
        SELECT amount, transactionCost FROM tblbudget 
        WHERE category = 'Expense' AND is_deleted = 0 
          AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 0 MONTH, '%Y-%m')
    ) AS all_expenses
),
subcategory_expense AS (
    SELECT 
        subcategory,
        MAX(tag) AS tag, 
        SUM(amount + IFNULL(transactionCost, 0)) AS total_amount
    FROM (
        SELECT subcategory, tag, amount, transactionCost FROM tblbudget 
        WHERE category = 'Expense' AND is_deleted = 0 
          AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 0 MONTH, '%Y-%m')
    ) AS combined 
    GROUP BY subcategory
)
SELECT 
    se.subcategory,
    se.tag, 
    se.total_amount,
    ROUND((se.total_amount / te.grand_total) * 100, 2) AS percentage
FROM subcategory_expense se
CROSS JOIN total_expense te
ORDER BY se.total_amount DESC;
";

        $result = $con->query($query);

        $previousMonthZeroExpense = 0;
        $rows = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
                if ($previousMonthZeroExpense == 0) {
                    $previousMonthZeroExpense = $row['total_amount'] / ($row['percentage'] / 100);
                }
            }
        }

        // Query to get detailed transactions for each subcategory
        $detailedTransactionsQuery0Months = "
SELECT 
    'budget' as source,
    subcategory, tag,
    amount,
    transactionCost,
    description,
    expenseDate as transaction_date
FROM tblbudget 
WHERE category = 'Expense' 
    AND is_deleted = 0 
    AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 0 MONTH, '%Y-%m')
ORDER BY subcategory, transaction_date;
";

        $detailedResult0Months = $con->query($detailedTransactionsQuery0Months);
        $detailedTransactions0Months = [];

        if ($detailedResult0Months && $detailedResult0Months->num_rows > 0) {
            while ($row = $detailedResult0Months->fetch_assoc()) {
                $detailedTransactions0Months[$row['subcategory']][] = $row;
            }
        }
        ?>
        <div class="col-lg-6 pe-lg-2 mb-3">
            <div class="card h-lg-100 overflow-hidden">
                <div class="card-header bg-body-tertiary">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="mb-0">
                                <?php
                                $zeroMonthsAgo = date('F', strtotime('-0 month'));
                                echo $zeroMonthsAgo;
                                ?> Expenses Breakdown
                            </h6>
                        </div>
                        <div class="col-auto text-center pe-x1">
                            <?php echo "Ksh " . number_format($previousMonthZeroExpense ?? 0); ?>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($rows)) : ?>
                        <?php
                        $iconMap = [
                            'PayPal' => 'paypal.png',
                            'Mpesa' => 'mpesa.png',
                            'Card' => 'credit-card.png',
                            'Cash' => 'cash.png',
                            'Airtel Money' => 'airtel.png'
                        ];
                        foreach ($rows as $row) :
                            $subcategory = htmlspecialchars($row['subcategory']);
                            $raw_tag = trim($row['tag'] ?? '');
                            $tag = htmlspecialchars($raw_tag);
                            $iconFile = $iconMap[$raw_tag] ?? 'default.png';
                            $amount = number_format($row['total_amount'], 2);
                            $percentage = round($row['percentage']);
                            $progressBarColor = $percentage >= 2 ? 'bg-danger-subtle' : 'bg-warning-subtle';
                            $badgeColor = $percentage >= 2 ? 'badge-subtle-danger' : 'badge-subtle-warning';
                            $textColor = $percentage >= 2 ? 'text-danger' : 'text-warning';
                            ?>
                            <div class="row g-0 align-items-center py-2 position-relative border-bottom border-200">
                                <div class="col ps-x1 py-1 position-static">
                                    <div class="d-flex align-items-center position-relative">
                                        <img class="rounded-1 border border-200" src="../assets/img/icons/<?= $iconFile ?>" alt="<?= $tag?>">
                                        <div class="flex-1">
                                            <h6 class="mb-0 d-flex align-items-center">
                                                <a href="#" class="text-800 stretched-link" data-bs-toggle="modal"
                                                   data-bs-target="#modal0Month<?= str_replace(' ', '', $subcategory) ?>">
                                                    <?= $subcategory ?>
                                                </a>
                                                <span class="badge rounded-pill <?= $badgeColor ?> ms-2"><?= $percentage ?>%</span>
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col py-1">
                                    <div class="row flex-end-center g-0">
                                        <div class="col-auto pe-2">
                                            <div class="fs-10 fw-semi-bold">Ksh. <?= $amount ?></div>
                                        </div>
                                        <div class="col-5 pe-x1 ps-2">
                                            <div class="progress bg-500 me-2" style="height: 5px;" role="progressbar"
                                                 aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar rounded-pill <?= $progressBarColor ?>"
                                                     style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal for each subcategory -->
                            <div class="modal fade" id="modal0Month<?= str_replace(' ', '', $subcategory) ?>" tabindex="-1"
                                 aria-labelledby="modal0Month<?= str_replace(' ', '', $subcategory) ?>Label" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="modal0Month<?= str_replace(' ', '', $subcategory) ?>Label">
                                                <?= $subcategory ?> - Detailed Transactions (<?= $zeroMonthsAgo ?>)
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Description</th>
                                                        <th>Amount</th>
                                                        <th>Transaction Cost</th>
                                                        <th>Total</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php if (isset($detailedTransactions0Months[$subcategory])) : ?>
                                                        <?php foreach ($detailedTransactions0Months[$subcategory] as $transaction) : ?>
                                                            <tr>
                                                                <td><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                                                                <td><?= htmlspecialchars($transaction['description']) ?></td>
                                                                <td>Ksh <?= number_format($transaction['amount'], 2) ?></td>
                                                                <td>Ksh <?= number_format($transaction['transactionCost'] ?? 0, 2) ?></td>
                                                                <td>Ksh <?= number_format($transaction['amount'] + ($transaction['transactionCost'] ?? 0), 2) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else : ?>
                                                        <tr>
                                                            <td colspan="5" class="text-center">No detailed transactions found</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class='text-center fs-10 mt-3'>No expenses found for <?= $zeroMonthsAgo ?>.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        // ==========================================
        // 2. PREVIOUS MONTH (INTERVAL 1 MONTH)
        // ==========================================

        // Query to get expenses breakdown for one months ago including total expense
        $query = "
WITH total_expense AS (
    SELECT 
        SUM(amount + IFNULL(transactionCost, 0)) AS grand_total
    FROM (
        SELECT amount, transactionCost FROM tblbudget 
        WHERE category = 'Expense' AND is_deleted = 0 
          AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m')
    ) AS all_expenses
),
subcategory_expense AS (
    SELECT 
        subcategory,
        MAX(tag) AS tag, 
        SUM(amount + IFNULL(transactionCost, 0)) AS total_amount
    FROM (
        SELECT subcategory, tag, amount, transactionCost FROM tblbudget 
        WHERE category = 'Expense' AND is_deleted = 0 
          AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m')
    ) AS combined 
    GROUP BY subcategory
)
SELECT 
    se.subcategory, 
    se.tag,
    se.total_amount,
    ROUND((se.total_amount / te.grand_total) * 100, 2) AS percentage
FROM subcategory_expense se
CROSS JOIN total_expense te
ORDER BY se.total_amount DESC;
";

        $result = $con->query($query);

        $previousMonthOneExpense = 0;
        $rows = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
                if ($previousMonthOneExpense == 0) {
                    $previousMonthOneExpense = $row['total_amount'] / ($row['percentage'] / 100);
                }
            }
        }

        // Query to get detailed transactions for each subcategory
        $detailedTransactionsQuery1Months = "
SELECT 
    'budget' as source,
    subcategory, tag,
    amount,
    transactionCost,
    description,
    expenseDate as transaction_date
FROM tblbudget 
WHERE category = 'Expense' 
    AND is_deleted = 0 
    AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m')
ORDER BY subcategory, transaction_date;
";

        $detailedResult1Months = $con->query($detailedTransactionsQuery1Months);
        $detailedTransactions1Months = [];

        if ($detailedResult1Months && $detailedResult1Months->num_rows > 0) {
            while ($row = $detailedResult1Months->fetch_assoc()) {
                $detailedTransactions1Months[$row['subcategory']][] = $row;
            }
        }
        ?>
        <div class="col-lg-6 pe-lg-2 mb-3">
            <div class="card h-lg-100 overflow-hidden">
                <div class="card-header bg-body-tertiary">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="mb-0">
                                <?php
                                $oneMonthsAgo = date('F', strtotime('-1 month'));
                                echo $oneMonthsAgo;
                                ?> Expenses Breakdown
                            </h6>
                        </div>
                        <div class="col-auto text-center pe-x1">
                            <?php echo "Ksh " . number_format($previousMonthOneExpense ?? 0); ?>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($rows)) : ?>
                        <?php
                        $iconMap = [
                            'PayPal' => 'paypal.png',
                            'Mpesa' => 'mpesa.png',
                            'Card' => 'credit-card.png',
                            'Cash' => 'cash.png',
                            'Airtel Money' => 'airtel.png'
                        ];
                        foreach ($rows as $row) :
                            $subcategory = htmlspecialchars($row['subcategory']);
                            $raw_tag = trim($row['tag'] ?? '');
                            $tag = htmlspecialchars($raw_tag);
                            $iconFile = $iconMap[$raw_tag] ?? 'default.png';
                            $amount = number_format($row['total_amount'], 2);
                            $percentage = round($row['percentage']);
                            $progressBarColor = $percentage >= 2 ? 'bg-danger-subtle' : 'bg-warning-subtle';
                            $badgeColor = $percentage >= 2 ? 'badge-subtle-danger' : 'badge-subtle-warning';
                            $textColor = $percentage >= 2 ? 'text-danger' : 'text-warning';
                            ?>
                            <div class="row g-0 align-items-center py-2 position-relative border-bottom border-200">
                                <div class="col ps-x1 py-1 position-static">
                                    <div class="d-flex align-items-center position-relative">
                                        <img class="rounded-1 border border-200" src="../assets/img/icons/<?= $iconFile ?>" alt="<?= $tag?>" width="40">
                                        <div class="flex-1">
                                            <h6 class="mb-0 d-flex align-items-center">
                                                <a href="#" class="text-800 stretched-link" data-bs-toggle="modal"
                                                   data-bs-target="#modal1Month<?= str_replace(' ', '', $subcategory) ?>">
                                                    <?= $subcategory ?>
                                                </a>
                                                <span class="badge rounded-pill <?= $badgeColor ?> ms-2"><?= $percentage ?>%</span>
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col py-1">
                                    <div class="row flex-end-center g-0">
                                        <div class="col-auto pe-2">
                                            <div class="fs-10 fw-semi-bold">Ksh. <?= $amount ?></div>
                                        </div>
                                        <div class="col-5 pe-x1 ps-2">
                                            <div class="progress bg-500 me-2" style="height: 5px;" role="progressbar"
                                                 aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar rounded-pill <?= $progressBarColor ?>"
                                                     style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal for each subcategory -->
                            <div class="modal fade" id="modal1Month<?= str_replace(' ', '', $subcategory) ?>" tabindex="-1"
                                 aria-labelledby="modal1Month<?= str_replace(' ', '', $subcategory) ?>Label" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="modal1Month<?= str_replace(' ', '', $subcategory) ?>Label">
                                                <?= $subcategory ?> - Detailed Transactions (<?= $oneMonthsAgo ?>)
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Description</th>
                                                        <th>Amount</th>
                                                        <th>Transaction Cost</th>
                                                        <th>Total</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php if (isset($detailedTransactions1Months[$subcategory])) : ?>
                                                        <?php foreach ($detailedTransactions1Months[$subcategory] as $transaction) : ?>
                                                            <tr>
                                                                <td><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                                                                <td><?= htmlspecialchars($transaction['description']) ?></td>
                                                                <td>Ksh <?= number_format($transaction['amount'], 2) ?></td>
                                                                <td>Ksh <?= number_format($transaction['transactionCost'] ?? 0, 2) ?></td>
                                                                <td>Ksh <?= number_format($transaction['amount'] + ($transaction['transactionCost'] ?? 0), 2) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else : ?>
                                                        <tr>
                                                            <td colspan="5" class="text-center">No detailed transactions found</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class='text-center fs-10 mt-3'>No expenses found for <?= $oneMonthsAgo ?>.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <?php
        // ==========================================
        // 1. TWO MONTHS AGO (INTERVAL 2 MONTH)
        // ==========================================

        // Query to get expenses breakdown for two months ago including total expense
        $query = "
WITH total_expense AS (
    SELECT 
        SUM(amount + IFNULL(transactionCost, 0)) AS grand_total
    FROM (
        SELECT amount, transactionCost FROM tblbudget 
        WHERE category = 'Expense' AND is_deleted = 0 
          AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m')
    ) AS all_expenses
),
subcategory_expense AS (
    SELECT 
        subcategory,
        tag, 
        SUM(amount + IFNULL(transactionCost, 0)) AS total_amount
    FROM (
        SELECT subcategory, tag, amount, transactionCost FROM tblbudget 
        WHERE category = 'Expense' AND is_deleted = 0 
          AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m')
    ) AS combined 
    GROUP BY subcategory
)
SELECT 
    se.subcategory, 
    se.tag,
    se.total_amount,
    ROUND((se.total_amount / te.grand_total) * 100, 2) AS percentage
FROM subcategory_expense se
CROSS JOIN total_expense te
ORDER BY se.total_amount DESC;
";

        $result = $con->query($query);

        $previousMonthTwoExpense = 0;
        $rows = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
                if ($previousMonthTwoExpense == 0) {
                    $previousMonthTwoExpense = $row['total_amount'] / ($row['percentage'] / 100);
                }
            }
        }

        // Query to get detailed transactions for each subcategory
        $detailedTransactionsQuery2Months = "
SELECT 
    'budget' as source,
    subcategory, tag,
    amount,
    transactionCost,
    description,
    expenseDate as transaction_date
FROM tblbudget 
WHERE category = 'Expense' 
    AND is_deleted = 0 
    AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m')
ORDER BY subcategory, transaction_date;
";

        $detailedResult2Months = $con->query($detailedTransactionsQuery2Months);
        $detailedTransactions2Months = [];

        if ($detailedResult2Months && $detailedResult2Months->num_rows > 0) {
            while ($row = $detailedResult2Months->fetch_assoc()) {
                $detailedTransactions2Months[$row['subcategory']][] = $row;
            }
        }
        ?>
        <div class="col-lg-6 pe-lg-2 mb-3">
            <div class="card h-lg-100 overflow-hidden">
                <div class="card-header bg-body-tertiary">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="mb-0">
                                <?php
                                $twoMonthsAgo = date('F', strtotime('-2 month'));
                                echo $twoMonthsAgo;
                                ?> Expenses Breakdown
                            </h6>
                        </div>
                        <div class="col-auto text-center pe-x1">
                            <?php echo "Ksh " . number_format($previousMonthTwoExpense ?? 0); ?>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($rows)) : ?>
                        <?php
                        $iconMap = [
                            'PayPal' => 'paypal.png',
                            'Mpesa' => 'mpesa.png',
                            'Card' => 'credit-card.png',
                            'Cash' => 'cash.png',
                            'Airtel Money' => 'airtel.png'
                        ];
                        foreach ($rows as $row) :
                            $subcategory = htmlspecialchars($row['subcategory']);
                            $raw_tag = trim($row['tag'] ?? '');
                            $tag = htmlspecialchars($raw_tag);
                            $iconFile = $iconMap[$raw_tag] ?? 'default.png';
                            $amount = number_format($row['total_amount'], 2);
                            $percentage = round($row['percentage']);
                            $progressBarColor = $percentage >= 2 ? 'bg-danger-subtle' : 'bg-warning-subtle';
                            $badgeColor = $percentage >= 2 ? 'badge-subtle-danger' : 'badge-subtle-warning';
                            $textColor = $percentage >= 2 ? 'text-danger' : 'text-warning';
                            ?>
                            <div class="row g-0 align-items-center py-2 position-relative border-bottom border-200">
                                <div class="col ps-x1 py-1 position-static">
                                    <div class="d-flex align-items-center position-relative">
                                        <img class="rounded-1 border border-200" src="../assets/img/icons/<?= $iconFile ?>" alt="<?= $tag?>" width="45">
                                        <div class="flex-1">
                                            <h6 class="mb-0 d-flex align-items-center">
                                                <a href="#" class="text-800 stretched-link" data-bs-toggle="modal"
                                                   data-bs-target="#modal2Month<?= str_replace(' ', '', $subcategory) ?>">
                                                    <?= $subcategory ?>
                                                </a>
                                                <span class="badge rounded-pill <?= $badgeColor ?> ms-2"><?= $percentage ?>%</span>
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col py-1">
                                    <div class="row flex-end-center g-0">
                                        <div class="col-auto pe-2">
                                            <div class="fs-10 fw-semi-bold">Ksh. <?= $amount ?></div>
                                        </div>
                                        <div class="col-5 pe-x1 ps-2">
                                            <div class="progress bg-500 me-2" style="height: 5px;" role="progressbar"
                                                 aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar rounded-pill <?= $progressBarColor ?>"
                                                     style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal for each subcategory -->
                            <div class="modal fade" id="modal2Month<?= str_replace(' ', '', $subcategory) ?>" tabindex="-1"
                                 aria-labelledby="modal2Month<?= str_replace(' ', '', $subcategory) ?>Label" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="modal2Month<?= str_replace(' ', '', $subcategory) ?>Label">
                                                <?= $subcategory ?> - Detailed Transactions (<?= $twoMonthsAgo ?>)
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Description</th>
                                                        <th>Amount</th>
                                                        <th>Transaction Cost</th>
                                                        <th>Total</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php if (isset($detailedTransactions2Months[$subcategory])) : ?>
                                                        <?php foreach ($detailedTransactions2Months[$subcategory] as $transaction) : ?>
                                                            <tr>
                                                                <td><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                                                                <td><?= htmlspecialchars($transaction['description']) ?></td>
                                                                <td>Ksh <?= number_format($transaction['amount'], 2) ?></td>
                                                                <td>Ksh <?= number_format($transaction['transactionCost'] ?? 0, 2) ?></td>
                                                                <td>Ksh <?= number_format($transaction['amount'] + ($transaction['transactionCost'] ?? 0), 2) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else : ?>
                                                        <tr>
                                                            <td colspan="5" class="text-center">No detailed transactions found</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class='text-center fs-10 mt-3'>No expenses found for <?= $twoMonthsAgo ?>.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        // ==========================================
        // 2. THREE MONTHS AGO (INTERVAL 3 MONTH)
        // ==========================================

        // Query to get expenses breakdown for three months ago including total expense
        $query = "
WITH total_expense AS (
    SELECT 
        SUM(amount + IFNULL(transactionCost, 0)) AS grand_total
    FROM (
        SELECT amount, transactionCost FROM tblbudget 
        WHERE category = 'Expense' AND is_deleted = 0 
          AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 3 MONTH, '%Y-%m')
    ) AS all_expenses
),
subcategory_expense AS (
    SELECT 
        subcategory,
        MAX(tag) AS tag, 
        SUM(amount + IFNULL(transactionCost, 0)) AS total_amount
    FROM (
        SELECT subcategory, tag, amount, transactionCost FROM tblbudget 
        WHERE category = 'Expense' AND is_deleted = 0 
          AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 3 MONTH, '%Y-%m')
    ) AS combined 
    GROUP BY subcategory
)
SELECT 
    se.subcategory, 
    se.tag,
    se.total_amount,
    ROUND((se.total_amount / te.grand_total) * 100, 2) AS percentage
FROM subcategory_expense se
CROSS JOIN total_expense te
ORDER BY se.total_amount DESC;
";

        $result = $con->query($query);

        $previousMonthButTwoExpense = 0;
        $rows = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
                if ($previousMonthButTwoExpense == 0) {
                    $previousMonthButTwoExpense = $row['total_amount'] / ($row['percentage'] / 100);
                }
            }
        }

        // Query to get detailed transactions for each subcategory
        // FIXED: Added missing comma after 'tag'
        $detailedTransactionsQuery3Months = "
SELECT 
    'budget' as source,
    subcategory, tag,
    amount,
    transactionCost,
    description,
    expenseDate as transaction_date
FROM tblbudget 
WHERE category = 'Expense' 
    AND is_deleted = 0 
    AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 3 MONTH, '%Y-%m')
ORDER BY subcategory, transaction_date;
";

        $detailedResult3Months = $con->query($detailedTransactionsQuery3Months);
        $detailedTransactions3Months = [];

        if ($detailedResult3Months && $detailedResult3Months->num_rows > 0) {
            while ($row = $detailedResult3Months->fetch_assoc()) {
                $detailedTransactions3Months[$row['subcategory']][] = $row;
            }
        }
        ?>
        <div class="col-lg-6 pe-lg-2 mb-3">
            <div class="card h-lg-100 overflow-hidden">
                <div class="card-header bg-body-tertiary">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="mb-0">
                                <?php
                                $threeMonthsAgo = date('F', strtotime('-3 month'));
                                echo $threeMonthsAgo;
                                ?> Expenses Breakdown
                            </h6>
                        </div>
                        <div class="col-auto text-center pe-x1">
                            <?php echo "Ksh " . number_format($previousMonthButTwoExpense ?? 0); ?>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($rows)) : ?>
                        <?php
                        $iconMap = [
                            'PayPal' => 'paypal.png',
                            'Mpesa' => 'mpesa.png',
                            'Card' => 'credit-card.png',
                            'Cash' => 'cash.png',
                            'Airtel Money' => 'airtel.png'
                        ];
                        foreach ($rows as $row) :
                            $subcategory = htmlspecialchars($row['subcategory']);
                            $raw_tag = trim($row['tag'] ?? '');
                            $tag = htmlspecialchars($raw_tag);
                            $iconFile = $iconMap[$raw_tag] ?? 'default.png';
                            // Removed duplicate $subcategory assignment
                            $amount = number_format($row['total_amount'], 2);
                            $percentage = round($row['percentage']);
                            $progressBarColor = $percentage >= 2 ? 'bg-danger-subtle' : 'bg-warning-subtle';
                            $badgeColor = $percentage >= 2 ? 'badge-subtle-danger' : 'badge-subtle-warning';
                            $textColor = $percentage >= 2 ? 'text-danger' : 'text-warning';
                            ?>
                            <div class="row g-0 align-items-center py-2 position-relative border-bottom border-200">
                                <div class="col ps-x1 py-1 position-static">
                                    <div class="d-flex align-items-center position-relative">
                                        <img class="rounded-1 border border-200" src="../assets/img/icons/<?= $iconFile ?>" alt="<?= $tag?>" width="45">
                                        <div class="flex-1">
                                            <h6 class="mb-0 d-flex align-items-center">
                                                <a href="#" class="text-800 stretched-link" data-bs-toggle="modal"
                                                   data-bs-target="#modal3Month<?= str_replace(' ', '', $subcategory) ?>">
                                                    <?= $subcategory ?>
                                                </a>
                                                <span class="badge rounded-pill <?= $badgeColor ?> ms-2"><?= $percentage ?>%</span>
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col py-1">
                                    <div class="row flex-end-center g-0">
                                        <div class="col-auto pe-2">
                                            <div class="fs-10 fw-semi-bold">Ksh. <?= $amount ?></div>
                                        </div>
                                        <div class="col-5 pe-x1 ps-2">
                                            <div class="progress bg-500 me-2" style="height: 5px;" role="progressbar"
                                                 aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar rounded-pill <?= $progressBarColor ?>"
                                                     style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal for each subcategory -->
                            <div class="modal fade" id="modal3Month<?= str_replace(' ', '', $subcategory) ?>" tabindex="-1"
                                 aria-labelledby="modal3Month<?= str_replace(' ', '', $subcategory) ?>Label" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="modal3Month<?= str_replace(' ', '', $subcategory) ?>Label">
                                                <?= $subcategory ?> - Detailed Transactions (<?= $threeMonthsAgo ?>)
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Description</th>
                                                        <th>Amount</th>
                                                        <th>Transaction Cost</th>
                                                        <th>Total</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php if (isset($detailedTransactions3Months[$subcategory])) : ?>
                                                        <?php foreach ($detailedTransactions3Months[$subcategory] as $transaction) : ?>
                                                            <tr>
                                                                <td><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                                                                <td><?= htmlspecialchars($transaction['description']) ?></td>
                                                                <td>Ksh <?= number_format($transaction['amount'], 2) ?></td>
                                                                <td>Ksh <?= number_format($transaction['transactionCost'] ?? 0, 2) ?></td>
                                                                <td>Ksh <?= number_format($transaction['amount'] + ($transaction['transactionCost'] ?? 0), 2) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else : ?>
                                                        <tr>
                                                            <td colspan="5" class="text-center">No detailed transactions found</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class='text-center fs-10 mt-3'>No expenses found for <?= $threeMonthsAgo ?>.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <div class="row g-3">
        <?php
        // ==========================================
        // 1. FOUR MONTHS AGO (INTERVAL 4 MONTH)
        // ==========================================

        // Query to get expenses breakdown for four months ago including total expense
        $query = "
WITH total_expense AS (
    SELECT 
        SUM(amount + IFNULL(transactionCost, 0)) AS grand_total
    FROM (
        SELECT amount, transactionCost FROM tblbudget 
        WHERE category = 'Expense' AND is_deleted = 0 
          AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 4 MONTH, '%Y-%m')
    ) AS all_expenses
),
subcategory_expense AS (
    SELECT 
        subcategory,
        MAX(tag) AS tag, 
        SUM(amount + IFNULL(transactionCost, 0)) AS total_amount
    FROM (
        SELECT subcategory, tag, amount, transactionCost FROM tblbudget 
        WHERE category = 'Expense' AND is_deleted = 0 
          AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 4 MONTH, '%Y-%m')
    ) AS combined 
    GROUP BY subcategory
)
SELECT 
    se.subcategory,
    se.tag,
    se.total_amount,
    ROUND((se.total_amount / te.grand_total) * 100, 2) AS percentage
FROM subcategory_expense se
CROSS JOIN total_expense te
ORDER BY se.total_amount DESC;
";

        $result = $con->query($query);

        $previousMonthFourExpense = 0;
        $rows = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
                if ($previousMonthFourExpense == 0) {
                    $previousMonthFourExpense = $row['total_amount'] / ($row['percentage'] / 100);
                }
            }
        }

        // Query to get detailed transactions for each subcategory
        $detailedTransactionsQuery4Months = "
SELECT 
    'budget' as source,
    subcategory, tag,
    amount,
    transactionCost,
    description,
    expenseDate as transaction_date
FROM tblbudget 
WHERE category = 'Expense' 
    AND is_deleted = 0 
    AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 4 MONTH, '%Y-%m')
ORDER BY subcategory, transaction_date;
";

        $detailedResult4Months = $con->query($detailedTransactionsQuery4Months);
        $detailedTransactions4Months = [];

        if ($detailedResult4Months && $detailedResult4Months->num_rows > 0) {
            while ($row = $detailedResult4Months->fetch_assoc()) {
                $detailedTransactions4Months[$row['subcategory']][] = $row;
            }
        }
        ?>
        <div class="col-lg-6 pe-lg-2 mb-3">
            <div class="card h-lg-100 overflow-hidden">
                <div class="card-header bg-body-tertiary">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="mb-0">
                                <?php
                                $fourMonthsAgo = date('F', strtotime('-4 month'));
                                echo $fourMonthsAgo;
                                ?> Expenses Breakdown
                            </h6>
                        </div>
                        <div class="col-auto text-center pe-x1">
                            <?php echo "Ksh " . number_format($previousMonthFourExpense ?? 0); ?>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($rows)) : ?>
                        <?php
                        $iconMap = [
                            'PayPal' => 'paypal.png',
                            'Mpesa' => 'mpesa.png',
                            'Card' => 'credit-card.png',
                            'Cash' => 'cash.png',
                            'Airtel Money' => 'airtel.png'
                        ];
                        foreach ($rows as $row) :
                            $subcategory = htmlspecialchars($row['subcategory']);
                            $raw_tag = trim($row['tag'] ?? '');
                            $tag = htmlspecialchars($raw_tag);
                            $iconFile = $iconMap[$raw_tag] ?? 'default.png';
                            $amount = number_format($row['total_amount'], 2);
                            $percentage = round($row['percentage']);
                            $progressBarColor = $percentage >= 2 ? 'bg-danger-subtle' : 'bg-warning-subtle';
                            $badgeColor = $percentage >= 2 ? 'badge-subtle-danger' : 'badge-subtle-warning';
                            $textColor = $percentage >= 2 ? 'text-danger' : 'text-warning';
                            ?>
                            <div class="row g-0 align-items-center py-2 position-relative border-bottom border-200">
                                <div class="col ps-x1 py-1 position-static">
                                    <div class="d-flex align-items-center position-relative">
                                        <img class="rounded-1 border border-200" src="../assets/img/icons/<?= $iconFile ?>" alt="<?= $tag ?>" width="40">
                                        <div class="flex-1">
                                            <h6 class="mb-0 d-flex align-items-center">
                                                <a href="#" class="text-800 stretched-link" data-bs-toggle="modal"
                                                   data-bs-target="#modal4Month<?= str_replace(' ', '', $subcategory) ?>">
                                                    <?= $subcategory ?>
                                                </a>
                                                <span class="badge rounded-pill <?= $badgeColor ?> ms-2"><?= $percentage ?>%</span>
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col py-1">
                                    <div class="row flex-end-center g-0">
                                        <div class="col-auto pe-2">
                                            <div class="fs-10 fw-semi-bold">Ksh. <?= $amount ?></div>
                                        </div>
                                        <div class="col-5 pe-x1 ps-2">
                                            <div class="progress bg-500 me-2" style="height: 5px;" role="progressbar"
                                                 aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar rounded-pill <?= $progressBarColor ?>"
                                                     style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal for each subcategory -->
                            <div class="modal fade" id="modal4Month<?= str_replace(' ', '', $subcategory) ?>" tabindex="-1"
                                 aria-labelledby="modal4Month<?= str_replace(' ', '', $subcategory) ?>Label" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="modal4Month<?= str_replace(' ', '', $subcategory) ?>Label">
                                                <?= $subcategory ?> - Detailed Transactions (<?= $fourMonthsAgo ?>)
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Description</th>
                                                        <th>Amount</th>
                                                        <th>Transaction Cost</th>
                                                        <th>Total</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php if (isset($detailedTransactions4Months[$subcategory])) : ?>
                                                        <?php foreach ($detailedTransactions4Months[$subcategory] as $transaction) : ?>
                                                            <tr>
                                                                <td><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                                                                <td><?= htmlspecialchars($transaction['description']) ?></td>
                                                                <td>Ksh <?= number_format($transaction['amount'], 2) ?></td>
                                                                <td>Ksh <?= number_format($transaction['transactionCost'] ?? 0, 2) ?></td>
                                                                <td>Ksh <?= number_format($transaction['amount'] + ($transaction['transactionCost'] ?? 0), 2) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else : ?>
                                                        <tr>
                                                            <td colspan="5" class="text-center">No detailed transactions found</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class='text-center fs-10 mt-3'>No expenses found for <?= $fourMonthsAgo ?>.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        // ==========================================
        // 2. FIVE MONTHS AGO (INTERVAL 5 MONTH)
        // ==========================================

        // Query to get expenses breakdown for five months ago including total expense
        $query = "
WITH total_expense AS (
    SELECT 
        SUM(amount + IFNULL(transactionCost, 0)) AS grand_total
    FROM (
        SELECT amount, transactionCost FROM tblbudget 
        WHERE category = 'Expense' AND is_deleted = 0 
          AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m')
    ) AS all_expenses
),
subcategory_expense AS (
    SELECT 
        subcategory,
        MAX(tag) AS tag, 
        SUM(amount + IFNULL(transactionCost, 0)) AS total_amount
    FROM (
        SELECT subcategory, tag, amount, transactionCost FROM tblbudget 
        WHERE category = 'Expense' AND is_deleted = 0 
          AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m')
    ) AS combined 
    GROUP BY subcategory
)
SELECT 
    se.subcategory,
    se.tag,
    se.total_amount,
    ROUND((se.total_amount / te.grand_total) * 100, 2) AS percentage
FROM subcategory_expense se
CROSS JOIN total_expense te
ORDER BY se.total_amount DESC;
";

        $result = $con->query($query);

        $previousMonthFiveExpense = 0;
        $rows = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
                if ($previousMonthFiveExpense == 0) {
                    $previousMonthFiveExpense = $row['total_amount'] / ($row['percentage'] / 100);
                }
            }
        }

        // Query to get detailed transactions for each subcategory
        $detailedTransactionsQuery5Months = "
SELECT 
    'budget' as source,
    subcategory, tag,
    amount,
    transactionCost,
    description,
    expenseDate as transaction_date
FROM tblbudget 
WHERE category = 'Expense' 
    AND is_deleted = 0 
    AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m')
ORDER BY subcategory, transaction_date;
";

        $detailedResult5Months = $con->query($detailedTransactionsQuery5Months);
        $detailedTransactions5Months = [];

        if ($detailedResult5Months && $detailedResult5Months->num_rows > 0) {
            while ($row = $detailedResult5Months->fetch_assoc()) {
                $detailedTransactions5Months[$row['subcategory']][] = $row;
            }
        }
        ?>
        <div class="col-lg-6 pe-lg-2 mb-3">
            <div class="card h-lg-100 overflow-hidden">
                <div class="card-header bg-body-tertiary">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="mb-0">
                                <?php
                                $fiveMonthsAgo = date('F', strtotime('-5 month'));
                                echo $fiveMonthsAgo;
                                ?> Expenses Breakdown
                            </h6>
                        </div>
                        <div class="col-auto text-center pe-x1">
                            <?php echo "Ksh " . number_format($previousMonthFiveExpense ?? 0); ?>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($rows)) : ?>
                        <?php
                        $iconMap = [
                            'PayPal' => 'paypal.png',
                            'Mpesa' => 'mpesa.png',
                            'Card' => 'credit-card.png',
                            'Cash' => 'cash.png',
                            'Airtel Money' => 'airtel.png'
                        ];
                        foreach ($rows as $row) :
                            $subcategory = htmlspecialchars($row['subcategory']);
                            $raw_tag = trim($row['tag'] ?? '');
                            $tag = htmlspecialchars($raw_tag);
                            $iconFile = $iconMap[$raw_tag] ?? 'default.png';
                            $amount = number_format($row['total_amount'], 2);
                            $percentage = round($row['percentage']);
                            $progressBarColor = $percentage >= 2 ? 'bg-danger-subtle' : 'bg-warning-subtle';
                            $badgeColor = $percentage >= 2 ? 'badge-subtle-danger' : 'badge-subtle-warning';
                            $textColor = $percentage >= 2 ? 'text-danger' : 'text-warning';
                            ?>
                            <div class="row g-0 align-items-center py-2 position-relative border-bottom border-200">
                                <div class="col ps-x1 py-1 position-static">
                                    <!-- UPDATED HTML: Replaced letter avatar with payment icon image -->
                                    <div class="d-flex align-items-center position-relative">
                                        <img class="rounded-1 border border-200" src="../assets/img/icons/<?= $iconFile ?>" alt="<?= $tag ?>" width="40">
                                        <div class="flex-1">
                                            <h6 class="mb-0 d-flex align-items-center">
                                                <a href="#" class="text-800 stretched-link" data-bs-toggle="modal"
                                                   data-bs-target="#modal5Month<?= str_replace(' ', '', $subcategory) ?>">
                                                    <?= $subcategory ?>
                                                </a>
                                                <span class="badge rounded-pill <?= $badgeColor ?> ms-2"><?= $percentage ?>%</span>
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col py-1">
                                    <div class="row flex-end-center g-0">
                                        <div class="col-auto pe-2">
                                            <div class="fs-10 fw-semi-bold">Ksh. <?= $amount ?></div>
                                        </div>
                                        <div class="col-5 pe-x1 ps-2">
                                            <div class="progress bg-500 me-2" style="height: 5px;" role="progressbar"
                                                 aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar rounded-pill <?= $progressBarColor ?>"
                                                     style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal for each subcategory -->
                            <div class="modal fade" id="modal5Month<?= str_replace(' ', '', $subcategory) ?>" tabindex="-1"
                                 aria-labelledby="modal5Month<?= str_replace(' ', '', $subcategory) ?>Label" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="modal5Month<?= str_replace(' ', '', $subcategory) ?>Label">
                                                <?= $subcategory ?> - Detailed Transactions (<?= $fiveMonthsAgo ?>)
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Description</th>
                                                        <th>Amount</th>
                                                        <th>Transaction Cost</th>
                                                        <th>Total</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php if (isset($detailedTransactions5Months[$subcategory])) : ?>
                                                        <?php foreach ($detailedTransactions5Months[$subcategory] as $transaction) : ?>
                                                            <tr>
                                                                <td><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></td>
                                                                <td><?= htmlspecialchars($transaction['description']) ?></td>
                                                                <td>Ksh <?= number_format($transaction['amount'], 2) ?></td>
                                                                <td>Ksh <?= number_format($transaction['transactionCost'] ?? 0, 2) ?></td>
                                                                <td>Ksh <?= number_format($transaction['amount'] + ($transaction['transactionCost'] ?? 0), 2) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else : ?>
                                                        <tr>
                                                            <td colspan="5" class="text-center">No detailed transactions found</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class='text-center fs-10 mt-3'>No expenses found for <?= $fiveMonthsAgo ?>.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6 pe-lg-2 mb-3">
            <div class="card h-lg-100 overflow-hidden">
                <div class="card-header bg-body-tertiary">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="mb-0"><?php $currentMonth = date('F', strtotime('-0 month')); echo $currentMonth; ?> Savings Breakdown</h6>
                        </div>
                        <div class="col-auto text-center pe-x1"><?php echo "Ksh " . number_format($currentMonthSavings); ?>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php
                    $query = "SELECT subcategory, SUM(amount) AS total_amount, ROUND((SUM(amount) / (SELECT SUM(amount) FROM tblbudget 
                              WHERE category = 'Savings' AND is_deleted = 0 
                              AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')) * 100), 2) AS percentage
                                FROM tblbudget WHERE category = 'Savings' AND is_deleted = 0 AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
                                GROUP BY subcategory ORDER BY total_amount DESC";
                    $result = $con->query($query);
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $subcategory = htmlspecialchars($row['subcategory']);
                            $amount = number_format($row['total_amount'], 2);
                            $percentage = round($row['percentage']);
                            $progressBarColor = $percentage >= 5 ? 'bg-success-subtle' : 'bg-info-subtle'; // Adjust color based on percentage
                            $badgeColor = $percentage >= 5 ? 'badge-subtle-success' : 'badge-subtle-info';
                            $textColor = $percentage >= 5 ? 'text-success' : 'text-info';

                            echo "
                            <div class=\"row g-0 align-items-center py-2 position-relative border-bottom border-200\">
                                <div class=\"col ps-x1 py-1 position-static\">
                                    <div class=\"d-flex align-items-center\">
                                        <div class=\"avatar avatar-xl me-3\">
                                            <div class=\"avatar-name rounded-circle {$progressBarColor} text-dark\">
                                                <span class=\"fs-9 {$textColor}\">{$subcategory[0]}</span>
                                            </div>
                                        </div>
                                        <div class=\"flex-1\">
                                            <h6 class=\"mb-0 d-flex align-items-center\">
                                                <a class=\"text-800 stretched-link\" href=\"#!\">{$subcategory}</a>
                                                <span class=\"badge rounded-pill {$badgeColor} ms-2 \">{$percentage}%</span>
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <div class=\"col py-1\">
                                    <div class=\"row flex-end-center g-0\">
                                        <div class=\"col-auto pe-2\">
                                            <div class=\"fs-10 fw-semi-bold\">Ksh. {$amount}</div>
                                        </div>
                                        <div class=\"col-5 pe-x1 ps-2\">
                                            <div class=\"progress bg-500 me-2\" style=\"height: 5px;\" role=\"progressbar\" aria-valuenow=\"{$percentage}\" aria-valuemin=\"0\" aria-valuemax=\"100\">
                                                <div class=\"progress-bar rounded-pill {$progressBarColor}\" style=\"width: {$percentage}%\"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            ";
                        }
                    } else {
                        echo "<div class='text-center fs-10 m-3'>No savings found for {$currentMonth}.</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6 pe-lg-2 mb-3">
            <div class="card h-lg-100 overflow-hidden">
                <div class="card-header bg-body-tertiary">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="mb-0"><?php $previousMonth = date('F', strtotime('-1 month')); echo $previousMonth; ?> Savings Breakdown</h6>
                        </div>
                        <div class="col-auto text-center pe-x1"><?php echo "Ksh " . number_format($previousMonthSavings); ?>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php
                    $query = "SELECT subcategory, SUM(amount) AS total_amount, ROUND((SUM(amount) / 
                        (SELECT SUM(amount) FROM tblbudget WHERE category = 'Savings' AND is_deleted = 0 
                           AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m'))
                        ) * 100, 2) AS percentage
                FROM tblbudget WHERE category = 'Savings' AND is_deleted = 0 AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m')
                GROUP BY subcategory ORDER BY total_amount DESC";

                    $result = $con->query($query);
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $subcategory = htmlspecialchars($row['subcategory']);
                            $amount = number_format($row['total_amount'], 2);
                            $percentage = round($row['percentage']);
                            $progressBarColor = $percentage >= 5 ? 'bg-success-subtle' : 'bg-info-subtle';
                            $badgeColor = $percentage >= 5 ? 'badge-subtle-success' : 'badge-subtle-info';
                            $textColor = $percentage >= 5 ? 'text-success' : 'text-info';

                            echo "
            <div class=\"row g-0 align-items-center py-2 position-relative border-bottom border-200\">
                <div class=\"col ps-x1 py-1 position-static\">
                    <div class=\"d-flex align-items-center\">
                        <div class=\"avatar avatar-xl me-3\">
                            <div class=\"avatar-name rounded-circle {$progressBarColor} text-dark\">
                                <span class=\"fs-9 {$textColor}\">{$subcategory[0]}</span>
                            </div>
                        </div>
                        <div class=\"flex-1\">
                            <h6 class=\"mb-0 d-flex align-items-center\">
                                <a class=\"text-800 stretched-link\" href=\"#!\">{$subcategory}</a>
                                <span class=\"badge rounded-pill {$badgeColor} ms-2 \">{$percentage}%</span>
                            </h6>
                        </div>
                    </div>
                </div>
                <div class=\"col py-1\">
                    <div class=\"row flex-end-center g-0\">
                        <div class=\"col-auto pe-2\">
                            <div class=\"fs-10 fw-semi-bold\">Ksh. {$amount}</div>
                        </div>
                        <div class=\"col-5 pe-x1 ps-2\">
                            <div class=\"progress bg-500 me-2\" style=\"height: 5px;\" role=\"progressbar\" aria-valuenow=\"{$percentage}\" aria-valuemin=\"0\" aria-valuemax=\"100\">
                                <div class=\"progress-bar rounded-pill {$progressBarColor}\" style=\"width: {$percentage}%\"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            ";
                        }
                    } else {
                        echo "<div class='text-center fs-10 m-3'>No savings found for {$previousMonth}.</div>";
                    }
                    ?>
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
                <div class="modal-header position-relative modal-shape-header bg-shape">
                    <div class="position-relative z-1">
                        <h4 class="mb-0 text-white text-center" id="viewTransactionModalLabel">Transaction Details</h4>
                    </div>
                    <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="col pe-2">
                        <div class="fs-8 mt-1">
                            <div class="d-flex flex-between-center mb-1">
                                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Category</span></div>
                                <div class="d-xxl-none"><span class="text-warning" id="viewCategory"></span></div>
                            </div>
                            <div class="d-flex flex-between-center mb-1">
                                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Sub Category</span></div>
                                <div class="d-xxl-none"><span class="text-warning" id="viewSubcategory"></span></div>
                            </div>
                            <div class="d-flex flex-between-center mb-1">
                                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Description</span></div>
                                <div class="d-xxl-none"><span class="text-warning" id="viewDescription"></span></div>
                            </div>
                            <div class="d-flex flex-between-center mb-1">
                                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Amount</span></div>
                                <div class="d-xxl-none"><span class="text-warning" id="viewAmount"></span></div>
                            </div>
                            <div class="d-flex flex-between-center mb-1">
                                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Transaction cost</span></div>
                                <div class="d-xxl-none"><span class="text-warning" id="viewTransactionCost"></span></div>
                            </div>
                            <div class="d-flex flex-between-center mb-1">
                                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Tag</span></div>
                                <div class="d-xxl-none"><span class="text-warning" id="viewTag"></span></div>
                            </div>
                            <div class="d-flex flex-between-center mb-1">
                                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Date</span></div>
                                <div class="d-xxl-none"><span class="text-warning" id="viewDate"></span></div>
                            </div>
                        </div>
                    </div>
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

    <script>
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

                // Populate View Modal
                viewBtn.addEventListener('click', () => {
                    document.getElementById('viewCategory').innerText = rowData.category;
                    document.getElementById('viewSubcategory').innerText = rowData.subcategory;
                    document.getElementById('viewDescription').innerText = rowData.description;
                    document.getElementById('viewAmount').innerText = rowData.amount;
                    document.getElementById('viewTransactionCost').innerText = rowData.transactionCost;
                    document.getElementById('viewTag').innerText = rowData.tag;
                    document.getElementById('viewDate').innerText = rowData.date;
                });

                // Populate Edit Modal
                editBtn.addEventListener('click', () => {
                    document.getElementById('editBudgetID').value = rowData.id; // Ensure 'id' is passed here
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
                        const response = await fetch("fetch-savings-goals.php");
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

<?php
include "footer.php";
?>