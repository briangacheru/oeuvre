<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1); // Log errors to file
ini_set('error_log', __DIR__ . '/php-errors.log');
date_default_timezone_set('Africa/Nairobi');
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include_once 'dbcon.php';
include_once 'currency_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/session-name.php';
    session_start();
}

$db = $dbh;

$method = $_SERVER['REQUEST_METHOD'];
$request = isset($_GET['action']) ? $_GET['action'] : '';

switch($method) {
    case 'GET':
        if ($request == 'summary') {
            getSummary($db);
        } elseif ($request == 'distribution') {
            getDistribution($db);
        } elseif ($request == 'distribution_by_month') {
            getDistributionByMonth($db);
        } elseif ($request == 'search') {
            searchAccounts($db);
        } elseif ($request == 'growth') {
            getGrowthData($db);
        } elseif ($request == 'growth_forecast') {
            getGrowthForecast($db);
        } elseif ($request == 'account_types') {
            getAccountTypes($db);
        } elseif ($request == 'balance_history') {
            getAccountBalanceHistory($db);
        } elseif ($request == 'get_balance_history') {
            getBalanceHistory($db);
        } elseif ($request == 'accounts_for_update') {
            getAccountsForBalanceUpdate($db);
        } elseif ($request == 'balances_by_month') {
            getBalancesByMonth($db);
        } elseif ($request == 'available_months') {
            getAvailableMonths($db);
        } elseif ($request == 'monthly_comparison') {
            getMonthlyComparison($db);
        } elseif ($request == 'latest_month') {
            getLatestMonthData($db);
        } elseif ($request == 'savings_breakdown') {
            getSavingsBreakdown($db);
        } elseif ($request == 'total_balance_breakdown') {
            getTotalBalanceBreakdown($db);
        } elseif ($request == 'latest_month_details') {
            getLatestMonthDetails($db);
        } elseif ($request == 'exchange_rates') {
            getExchangeRatesEndpoint($db);
        } else {
            getAllAccounts($db);
        }
        break;
    case 'POST':
        if ($request == 'update_balance') {
            updateMonthlyBalance($db);
        } elseif ($request == 'manage_type') {
            manageAccountType($db);
        } elseif ($request == 'create') {
            createAccount($db);
        } else {
            createAccount($db); // Default to create if no specific action
        }
        break;
    case 'PUT':
        if ($request == 'update') {
            updateAccount($db);
        } elseif ($request == 'manage_type') {
            manageAccountType($db);
        } else {
            updateAccount($db);
        }
        break;
    case 'DELETE':
        deleteAccount($db);
        break;
}

function getAllAccounts($db)
{
    $query = 'SELECT a.id, a.account_name, a.account_type_id, a.bank_name, a.account_number, 
                     a.currency,
                     a.status, a.interest_rate, a.minimum_balance, a.notes, a.created_at, a.last_updated,
                     at.type_name as account_type, at.color_code, at.icon_class,
                     COALESCE(bh.balance, 0) as balance,
                     bh.month_year as last_balance_update,
                     COALESCE(bh.growth_amount, 0) as growth_amount,
                     COALESCE(bh.growth_percentage, 0) as growth_percentage
              FROM accounts a 
              LEFT JOIN account_types at ON a.account_type_id = at.id 
              LEFT JOIN balance_history bh ON a.id = bh.account_id 
                  AND bh.month_year = (
                      SELECT MAX(month_year) 
                      FROM balance_history bh2 
                      WHERE bh2.account_id = a.id
                  )
              ORDER BY a.account_name';

    $stmt = $db->prepare($query);
    $stmt->execute();

    // Every account keeps its own native currency for display (that's
    // what renders in the table / detail views). We also attach a
    // converted_balance in the resolved target currency purely so the
    // frontend can compute fair "% of total" bars across mixed
    // currencies, without ever overwriting the original figure.
    $targetCurrency = resolveTargetCurrency($db);
    $rates = getExchangeRatesMap($db);

    // Reused for every account's last-3-months lookup below.
    $recentHistoryStmt = $db->prepare(
        "SELECT balance, month_year FROM balance_history 
         WHERE account_id = ? ORDER BY month_year DESC LIMIT 3"
    );

    $accounts = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['currency'] = $row['currency'] ?: 'USD';
        $row['balance'] = floatval($row['balance']);
        $row['growth_amount'] = floatval($row['growth_amount']);
        $row['growth_percentage'] = floatval($row['growth_percentage']);
        $row['converted_balance'] = convertCurrency($row['balance'], $row['currency'], $targetCurrency, $rates);

        $alerts = getAccountAlerts($recentHistoryStmt, $row['id'], $row['balance'], floatval($row['minimum_balance']), $row['growth_amount']);
        $row['alerts'] = $alerts;
        $row['is_dormancy_risk'] = in_array(true, array_column($alerts, 'is_dormancy'), true);
        $row['is_low_balance'] = in_array(true, array_column($alerts, 'is_low_balance'), true);

        $accounts[] = $row;
    }

    echo json_encode($accounts);
}

/**
 * Compute the same kind of alerts shown in the Month Overview's "Needs
 * Attention" list, but live/per-account rather than anchored to one
 * global reporting month — used to badge the main accounts table and to
 * surface context in the account detail modal.
 *
 * $recentHistoryStmt must be a prepared statement of:
 *   SELECT balance, month_year FROM balance_history
 *   WHERE account_id = ? ORDER BY month_year DESC LIMIT 3
 */
function getAccountAlerts($recentHistoryStmt, $accountId, $currentBalance, $minimumBalance, $growthAmount)
{
    $alerts = [];

    if ($minimumBalance > 0 && $currentBalance < $minimumBalance) {
        $alerts[] = [
            'type' => 'low_balance',
            'message' => 'Below minimum balance',
            'is_low_balance' => true,
            'is_dormancy' => false,
        ];
    }

    if ($growthAmount < 0) {
        $alerts[] = [
            'type' => 'negative_growth',
            'message' => 'Negative growth last update',
            'is_low_balance' => false,
            'is_dormancy' => false,
        ];
    }

    $recentHistoryStmt->execute([$accountId]);
    $recent = $recentHistoryStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($recent)) {
        $lastRecordMonth = $recent[0]['month_year'];
        $monthsSince = 0;
        try {
            $d1 = new DateTime($lastRecordMonth);
            $d2 = new DateTime(date('Y-m-01'));
            $diff = $d1->diff($d2);
            $monthsSince = ($diff->y * 12) + $diff->m;
        } catch (Exception $e) {
            $monthsSince = 0;
        }

        if ($monthsSince >= 2) {
            $alerts[] = [
                'type' => 'dormant_stale',
                'message' => 'Not updated in ' . $monthsSince . '+ months (last recorded ' . date('F Y', strtotime($lastRecordMonth)) . ')',
                'is_low_balance' => false,
                'is_dormancy' => true,
            ];
        } elseif (count($recent) >= 3 && $recent[0]['balance'] == $recent[1]['balance'] && $recent[1]['balance'] == $recent[2]['balance']) {
            $alerts[] = [
                'type' => 'dormant_stagnant',
                'message' => 'No balance change across the last 3 recorded months',
                'is_low_balance' => false,
                'is_dormancy' => true,
            ];
        }
    }

    return $alerts;
}

function getSummary($db)
{
    try {
        $targetCurrency = resolveTargetCurrency($db);
        $rates = getExchangeRatesMap($db);

        // Basic account counts (currency-independent).
        $countQuery = "SELECT 
            COUNT(*) as total_accounts,
            COUNT(CASE WHEN status = 'Active' THEN 1 END) as active_accounts,
            COUNT(CASE WHEN status != 'Active' THEN 1 END) as inactive_accounts
            FROM accounts";
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute();
        $summary = $countStmt->fetch(PDO::FETCH_ASSOC);

        // Total / average balance — pull each active account's latest
        // balance WITH its native currency, convert row-by-row, then sum.
        // (A plain SQL SUM() would silently add KES + USD + EUR together.)
        $balanceQuery = "SELECT a.currency, COALESCE(bh.balance, 0) as balance
            FROM accounts a
            LEFT JOIN balance_history bh ON a.id = bh.account_id
                AND bh.month_year = (
                    SELECT MAX(month_year) FROM balance_history bh2 WHERE bh2.account_id = a.id
                )
            WHERE a.status = 'Active'";
        $balanceStmt = $db->prepare($balanceQuery);
        $balanceStmt->execute();

        $totalBalance = 0.0;
        $activeCount = 0;
        while ($row = $balanceStmt->fetch(PDO::FETCH_ASSOC)) {
            $currency = $row['currency'] ?: 'USD';
            $totalBalance += convertCurrency($row['balance'], $currency, $targetCurrency, $rates);
            $activeCount++;
        }
        $summary['total_balance'] = $totalBalance;
        $summary['average_balance'] = $activeCount > 0 ? ($totalBalance / $activeCount) : 0;

        // Calculate dynamic monthly growth (current month vs previous month)
        $currentMonth = date('Y-m-01'); // First day of current month
        $previousMonth = date('Y-m-01', strtotime('-1 month')); // First day of previous month

        // Currency-aware month totals (same convert-then-sum approach).
        $monthTotalQuery = "SELECT a.currency, bh.balance
            FROM balance_history bh
            INNER JOIN accounts a ON bh.account_id = a.id
            WHERE bh.month_year = ? AND a.status = 'Active'";

        $currentStmt = $db->prepare($monthTotalQuery);
        $currentStmt->execute([$currentMonth]);
        $currentTotal = 0.0;
        while ($row = $currentStmt->fetch(PDO::FETCH_ASSOC)) {
            $currency = $row['currency'] ?: 'USD';
            $currentTotal += convertCurrency($row['balance'], $currency, $targetCurrency, $rates);
        }

        $previousStmt = $db->prepare($monthTotalQuery);
        $previousStmt->execute([$previousMonth]);
        $previousTotal = 0.0;
        while ($row = $previousStmt->fetch(PDO::FETCH_ASSOC)) {
            $currency = $row['currency'] ?: 'USD';
            $previousTotal += convertCurrency($row['balance'], $currency, $targetCurrency, $rates);
        }

        // Calculate monthly growth percentage
        if ($previousTotal > 0) {
            $monthlyGrowth = (($currentTotal - $previousTotal) / $previousTotal) * 100;
        } else {
            $monthlyGrowth = $currentTotal > 0 ? 100 : 0; // 100% if we have current balance but no previous
        }

        // Calculate monthly savings using latest available data - SIMPLIFIED
        $savingsTypes = ['Savings', 'MMF', 'Sacco'];

        // Get the latest month that has any savings data
        $latestSavingsQuery = "SELECT MAX(bh.month_year) as latest_month
            FROM balance_history bh
            INNER JOIN accounts a ON bh.account_id = a.id
            INNER JOIN account_types at ON a.account_type_id = at.id
            WHERE a.status = 'Active' AND at.type_name IN ('Savings', 'MMF', 'Sacco')";

        $latestSavingsStmt = $db->prepare($latestSavingsQuery);
        $latestSavingsStmt->execute();
        $latestSavingsResult = $latestSavingsStmt->fetch(PDO::FETCH_ASSOC);
        $latestSavingsMonth = $latestSavingsResult['latest_month'];

        if ($latestSavingsMonth) {
            // Get the previous month that has savings data
            $previousSavingsMonthQuery = "SELECT MAX(bh.month_year) as previous_month
                FROM balance_history bh
                INNER JOIN accounts a ON bh.account_id = a.id
                INNER JOIN account_types at ON a.account_type_id = at.id
                WHERE a.status = 'Active' AND at.type_name IN ('Savings', 'MMF', 'Sacco') 
                AND bh.month_year < ?";

            $previousSavingsMonthStmt = $db->prepare($previousSavingsMonthQuery);
            $previousSavingsMonthStmt->execute([$latestSavingsMonth]);
            $previousSavingsMonthResult = $previousSavingsMonthStmt->fetch(PDO::FETCH_ASSOC);
            $previousSavingsMonth = $previousSavingsMonthResult['previous_month'];

            // Currency-aware savings totals for a given month.
            $savingsQuery = "SELECT a.currency, bh.balance
                FROM balance_history bh
                INNER JOIN accounts a ON bh.account_id = a.id
                INNER JOIN account_types at ON a.account_type_id = at.id
                WHERE bh.month_year = ? AND a.status = 'Active' AND at.type_name IN ('Savings', 'MMF', 'Sacco')";

            $currentSavingsStmt = $db->prepare($savingsQuery);
            $currentSavingsStmt->execute([$latestSavingsMonth]);
            $currentSavingsTotal = 0.0;
            while ($row = $currentSavingsStmt->fetch(PDO::FETCH_ASSOC)) {
                $currency = $row['currency'] ?: 'USD';
                $currentSavingsTotal += convertCurrency($row['balance'], $currency, $targetCurrency, $rates);
            }

            $previousSavingsTotal = 0.0;
            if ($previousSavingsMonth) {
                $previousSavingsStmt = $db->prepare($savingsQuery);
                $previousSavingsStmt->execute([$previousSavingsMonth]);
                while ($row = $previousSavingsStmt->fetch(PDO::FETCH_ASSOC)) {
                    $currency = $row['currency'] ?: 'USD';
                    $previousSavingsTotal += convertCurrency($row['balance'], $currency, $targetCurrency, $rates);
                }
            }

            $monthlySavings = $currentSavingsTotal - $previousSavingsTotal;
            $savingsMonthDisplay = date('F Y', strtotime($latestSavingsMonth));

        } else {
            $monthlySavings = 0;
            $savingsMonthDisplay = 'No Data';
            $latestSavingsMonth = null;
        }

        // Add calculated values to summary
        $summary['monthly_growth'] = round($monthlyGrowth, 2);
        $summary['current_month_total'] = $currentTotal;
        $summary['previous_month_total'] = $previousTotal;
        $summary['growth_amount'] = $currentTotal - $previousTotal;
        $summary['monthly_savings'] = $monthlySavings;
        $summary['savings_month_display'] = $savingsMonthDisplay;
        $summary['latest_savings_month'] = $latestSavingsMonth;
        $summary['display_currency'] = $targetCurrency;

        // Ensure all numeric values are properly formatted
        $summary['total_balance'] = floatval($summary['total_balance']);
        $summary['average_balance'] = floatval($summary['average_balance']);
        $summary['active_accounts'] = intval($summary['active_accounts']);
        $summary['inactive_accounts'] = intval($summary['inactive_accounts']);
        $summary['total_accounts'] = intval($summary['total_accounts']);

        echo json_encode($summary);

    } catch (PDOException $e) {
        echo json_encode(array(
            'error' => 'Database error: ' . $e->getMessage(),
            'total_accounts' => 0,
            'active_accounts' => 0,
            'inactive_accounts' => 0,
            'total_balance' => 0,
            'monthly_growth' => 0
        ));
    }
}

function getDistribution($db)
{
    // Group by type AND currency at the SQL level (a plain SUM() across
    // mixed currencies would be meaningless), then convert each
    // currency's subtotal into the target currency and merge by type in PHP.
    $query = "SELECT at.id as type_id, at.type_name as account_type, at.color_code,
                     COALESCE(a.currency, 'USD') as currency,
                     SUM(COALESCE(bh.balance, 0)) as subtotal,
                     COUNT(a.id) as account_count
              FROM accounts a 
              LEFT JOIN account_types at ON a.account_type_id = at.id 
              LEFT JOIN balance_history bh ON a.id = bh.account_id 
                  AND bh.month_year = (
                      SELECT MAX(month_year) 
                      FROM balance_history bh2 
                      WHERE bh2.account_id = a.id
                  )
              GROUP BY at.id, at.type_name, at.color_code, a.currency";

    $stmt = $db->prepare($query);
    $stmt->execute();

    $targetCurrency = resolveTargetCurrency($db);
    $rates = getExchangeRatesMap($db);

    $byType = []; // type_id => ['account_type'=>, 'color_code'=>, 'total_balance'=>, 'account_count'=>]
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $typeId = $row['type_id'] ?? 'uncategorized';
        if (!isset($byType[$typeId])) {
            $byType[$typeId] = [
                'account_type' => $row['account_type'],
                'color_code' => $row['color_code'],
                'total_balance' => 0.0,
                'account_count' => 0,
            ];
        }
        $converted = convertCurrency(floatval($row['subtotal']), $row['currency'], $targetCurrency, $rates);
        $byType[$typeId]['total_balance'] += $converted;
        $byType[$typeId]['account_count'] += intval($row['account_count']);
    }

    $distribution = array_values($byType);
    usort($distribution, function ($a, $b) {
        return $b['total_balance'] <=> $a['total_balance'];
    });

    echo json_encode($distribution);
}

function searchAccounts($db)
{
    $searchTerm = isset($_GET['q']) ? $_GET['q'] : '';

    $query = 'SELECT a.id, a.account_name, a.account_type_id, a.bank_name, a.account_number, 
                     a.currency,
                     a.status, a.interest_rate, a.minimum_balance, a.notes, a.created_at, a.last_updated,
                     at.type_name as account_type, at.color_code, at.icon_class,
                     COALESCE(bh.balance, 0) as balance,
                     bh.month_year as last_balance_update
              FROM accounts a 
              LEFT JOIN account_types at ON a.account_type_id = at.id 
              LEFT JOIN balance_history bh ON a.id = bh.account_id 
                  AND bh.month_year = (
                      SELECT MAX(month_year) 
                      FROM balance_history bh2 
                      WHERE bh2.account_id = a.id
                  )
              WHERE a.account_name LIKE :search 
                 OR at.type_name LIKE :search 
                 OR a.bank_name LIKE :search 
                 OR a.status LIKE :search
              ORDER BY a.account_name';

    $stmt = $db->prepare($query);
    $searchParam = "%{$searchTerm}%";
    $stmt->bindParam(':search', $searchParam);
    $stmt->execute();

    $accounts = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['currency'] = $row['currency'] ?: 'USD';
        $row['balance'] = floatval($row['balance']);
        $accounts[] = $row;
    }

    echo json_encode($accounts);
}

function createAccount($db)
{
    try {
        // Get input data
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            echo json_encode(['message' => 'Invalid JSON data', 'success' => false]);
            return;
        }

        // Validate required fields
        if (empty($data['account_name'])) {
            echo json_encode(['message' => 'Account name is required', 'success' => false]);
            return;
        }

        if (empty($data['account_type'])) {
            echo json_encode(['message' => 'Account type is required', 'success' => false]);
            return;
        }

        // Validate status
        $validStatuses = ['Active', 'Inactive', 'Locked', 'Low Balance', 'Debt'];
        $status = isset($data['status']) ? $data['status'] : 'Active';
        if (!in_array($status, $validStatuses)) {
            echo json_encode(array('message' => 'Invalid status. Must be one of: ' . implode(', ', $validStatuses), 'error' => true));
            return;
        }

        // Get account type ID
        $stmt = $db->prepare('SELECT id FROM account_types WHERE type_name = ? AND is_active = 1');
        $stmt->execute([$data['account_type']]);
        $accountType = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$accountType) {
            echo json_encode(['message' => 'Invalid account type', 'success' => false]);
            return;
        }

        // Validate currency — falls back to KES rather than rejecting the
        // request, since older/other clients may not send this field yet.
        $currency = !empty($data['currency']) ? strtoupper(trim($data['currency'])) : 'KES';
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            echo json_encode(['message' => 'Invalid currency code. Use a 3-letter ISO code (e.g. USD, KES, EUR).', 'success' => false]);
            return;
        }

        // Start transaction
        $db->beginTransaction();

        // Insert account
        $stmt = $db->prepare('
            INSERT INTO accounts (account_name, account_type_id, bank_name, account_number, currency, status, interest_rate, minimum_balance, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $data['account_name'],
            $accountType['id'],
            $data['bank_name'] ?? '',
            $data['account_number'] ?? '',
            $currency,
            $data['status'] ?? 'Active',
            $data['interest_rate'] ?? 0,
            $data['minimum_balance'] ?? 0,
            $data['notes'] ?? ''
        ]);

        $accountId = $db->lastInsertId();

        // Insert initial balance (using positional parameters - cleaner!)
        if (isset($data['balance']) && $data['balance'] >= 0) {
            $stmt = $db->prepare('
                INSERT INTO balance_history (account_id, balance, month_year, notes) 
                VALUES (?, ?, ?, ?)
            ');

            $stmt->execute([
                $accountId,
                $data['balance'],
                date('Y-m-01'),
                'Initial balance'
            ]);
        }

        $db->commit();

        echo json_encode([
            'message' => 'Account created successfully',
            'success' => true,
            'account_id' => $accountId
        ]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['message' => 'Error: ' . $e->getMessage(), 'success' => false]);
    }
}


function deleteAccount($db)
{
    $id = isset($_GET['id']) ? $_GET['id'] : '';

    $query = 'DELETE FROM accounts WHERE id = :id';
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        echo json_encode(array('message' => 'Account deleted successfully.'));
    } else {
        echo json_encode(array('message' => 'Unable to delete account.'));
    }
}

// Add after existing functions in accounts_api.php

function getGrowthData($db)
{
    try {
        // Pull raw rows (month, currency, balance, growth_amount) — no
        // SQL-level SUM(), since balances/growth in different currencies
        // can't be added together before conversion. We convert each row
        // into the target currency first, then aggregate by month in PHP.
        $query = "SELECT 
                    bh.month_year AS month,
                    COALESCE(a.currency, 'USD') AS currency,
                    COALESCE(bh.balance, 0) AS balance,
                    COALESCE(bh.growth_amount, 0) AS growth_amount,
                    bh.account_id
                FROM balance_history bh
                INNER JOIN accounts a ON bh.account_id = a.id
                WHERE a.status = 'Active'
                ORDER BY bh.month_year DESC";

        $stmt = $db->prepare($query);
        $stmt->execute();

        $targetCurrency = resolveTargetCurrency($db);
        $rates = getExchangeRatesMap($db);

        $byMonth = []; // month => ['total_balance'=>, 'total_growth_amount'=>, 'accounts'=>Set]
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $month = $row['month'];
            if (!isset($byMonth[$month])) {
                $byMonth[$month] = [
                    'total_balance' => 0.0,
                    'total_growth_amount' => 0.0,
                    'accounts' => [],
                ];
            }
            $currency = $row['currency'] ?: 'USD';
            $byMonth[$month]['total_balance'] += convertCurrency(floatval($row['balance']), $currency, $targetCurrency, $rates);
            $byMonth[$month]['total_growth_amount'] += convertCurrency(floatval($row['growth_amount']), $currency, $targetCurrency, $rates);
            $byMonth[$month]['accounts'][$row['account_id']] = true;
        }

        // Sort months descending and cap to the most recent 12, matching
        // the original endpoint's behaviour.
        krsort($byMonth);
        $byMonth = array_slice($byMonth, 0, 12, true);

        $growth = array();
        foreach ($byMonth as $month => $data) {
            $priorBalance = $data['total_balance'] - $data['total_growth_amount'];
            $growthPercentage = $priorBalance > 0
                ? ($data['total_growth_amount'] / $priorBalance) * 100
                : 0;

            $growth[] = [
                'month' => $month,
                'total_balance' => $data['total_balance'],
                'total_growth_amount' => $data['total_growth_amount'],
                'growth_percentage' => round($growthPercentage, 2),
                'account_count' => count($data['accounts']),
            ];
        }

        echo json_encode($growth);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Forecast the next N months of Total Balance using simple linear
 * regression (least squares) over the available monthly history —
 * same currency-converted monthly totals the Monthly Growth Trend
 * chart already uses, so the forecast lines up with what's on screen.
 *
 * This is a straight-line trend projection, not a seasonal or
 * compounding model — it's meant to show "if the current trend
 * continues" rather than to predict exact future balances.
 */
function getGrowthForecast($db)
{
    try {
        $monthsAhead = isset($_GET['months']) ? max(1, min(12, intval($_GET['months']))) : 5;

        // Same aggregation as getGrowthData: convert every account's
        // balance into the target currency before summing by month.
        $query = "SELECT 
                    bh.month_year AS month,
                    COALESCE(a.currency, 'USD') AS currency,
                    COALESCE(bh.balance, 0) AS balance,
                    COALESCE(bh.growth_amount, 0) AS growth_amount,
                    bh.account_id
                FROM balance_history bh
                INNER JOIN accounts a ON bh.account_id = a.id
                WHERE a.status = 'Active'
                ORDER BY bh.month_year ASC";

        $stmt = $db->prepare($query);
        $stmt->execute();

        $targetCurrency = resolveTargetCurrency($db);
        $rates = getExchangeRatesMap($db);

        $byMonth = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $month = $row['month'];
            if (!isset($byMonth[$month])) {
                $byMonth[$month] = [
                    'total_balance' => 0.0,
                    'total_growth_amount' => 0.0,
                    'accounts' => [],
                ];
            }
            $currency = $row['currency'] ?: 'USD';
            $byMonth[$month]['total_balance'] += convertCurrency(floatval($row['balance']), $currency, $targetCurrency, $rates);
            $byMonth[$month]['total_growth_amount'] += convertCurrency(floatval($row['growth_amount']), $currency, $targetCurrency, $rates);
            $byMonth[$month]['accounts'][$row['account_id']] = true;
        }

        // Keep at most the most recent 12 months, chronological ascending
        // (oldest first) — this is what the regression is fit against.
        ksort($byMonth);
        if (count($byMonth) > 12) {
            $byMonth = array_slice($byMonth, -12, 12, true);
        }

        $historical = [];
        foreach ($byMonth as $month => $data) {
            $priorBalance = $data['total_balance'] - $data['total_growth_amount'];
            $growthPercentage = $priorBalance > 0 ? ($data['total_growth_amount'] / $priorBalance) * 100 : 0;

            $historical[] = [
                'month' => $month,
                'total_balance' => $data['total_balance'],
                'total_growth_amount' => $data['total_growth_amount'],
                'growth_percentage' => round($growthPercentage, 2),
                'account_count' => count($data['accounts']),
            ];
        }

        $n = count($historical);

        if ($n < 2) {
            echo json_encode([
                'success' => false,
                'message' => 'Not enough historical data for a forecast — at least 2 months of balance history are needed.',
                'display_currency' => $targetCurrency,
                'historical' => $historical,
                'forecast' => []
            ]);
            return;
        }

        // Least-squares linear regression over (x=month index, y=total_balance).
        $sumX = 0.0; $sumY = 0.0; $sumXY = 0.0; $sumXX = 0.0;
        foreach ($historical as $i => $point) {
            $x = $i;
            $y = $point['total_balance'];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        $slope = $denominator != 0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denominator : 0;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        // R² (coefficient of determination) — how well the straight line
        // actually fits the historical points, purely to give the user a
        // sense of how much to trust the projection.
        $meanY = $sumY / $n;
        $ssTot = 0.0; $ssRes = 0.0;
        foreach ($historical as $i => $point) {
            $predicted = $intercept + ($slope * $i);
            $ssTot += pow($point['total_balance'] - $meanY, 2);
            $ssRes += pow($point['total_balance'] - $predicted, 2);
        }
        $rSquared = $ssTot > 0 ? max(0, 1 - ($ssRes / $ssTot)) : 1;

        $confidence = 'low';
        if ($n >= 8 && $rSquared >= 0.5) {
            $confidence = 'high';
        } elseif ($n >= 4 && $rSquared >= 0.25) {
            $confidence = 'medium';
        }

        // Project forward. Last historical row's month is the anchor point
        // future months count forward from.
        $lastMonth = $historical[$n - 1]['month'];
        $lastBalance = $historical[$n - 1]['total_balance'];

        $forecast = [];
        $prevBalance = $lastBalance;
        for ($j = 1; $j <= $monthsAhead; $j++) {
            $futureIndex = $n - 1 + $j;
            $projectedBalance = $intercept + ($slope * $futureIndex);
            $futureMonth = date('Y-m-01', strtotime($lastMonth . " +{$j} months"));

            $forecast[] = [
                'month' => $futureMonth,
                'projected_balance' => $projectedBalance,
                'projected_change' => $projectedBalance - $prevBalance,
            ];
            $prevBalance = $projectedBalance;
        }

        echo json_encode([
            'success' => true,
            'display_currency' => $targetCurrency,
            'historical' => $historical, // ascending chronological order
            'forecast' => $forecast,     // ascending, continues right after historical
            'method' => 'linear_regression',
            'r_squared' => round($rSquared, 3),
            'confidence' => $confidence,
            'monthly_trend' => $slope // average projected change per month, in display currency
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getAccountBalanceHistory($db)
{
    $accountId = isset($_GET['account_id']) ? $_GET['account_id'] : '';

    if (!$accountId) {
        echo json_encode(array('error' => 'Account ID required'));
        return;
    }

    $query = 'SELECT bh.*, a.currency FROM balance_history bh
              LEFT JOIN accounts a ON a.id = bh.account_id
              WHERE bh.account_id = :account_id 
              ORDER BY bh.month_year DESC';

    $stmt = $db->prepare($query);
    $stmt->bindParam(':account_id', $accountId);
    $stmt->execute();

    $history = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['currency'] = $row['currency'] ?: 'USD';
        $history[] = $row;
    }

    echo json_encode($history);
}

function getAccountsForBalanceUpdate($db)
{
    $query = 'SELECT 
                acb.id, 
                acb.account_name, 
                acb.bank_name, 
                acb.current_balance, 
                acb.last_balance_update,
                acb.account_type_id,
                a.currency,
                at.type_name as account_type,
                at.color_code,
                at.icon_class
              FROM account_current_balances acb
              LEFT JOIN accounts a ON a.id = acb.id
              LEFT JOIN account_types at ON acb.account_type_id = at.id
              WHERE acb.status = "Active"
              ORDER BY acb.account_name';

    $stmt = $db->prepare($query);
    $stmt->execute();

    $accounts = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['currency'] = $row['currency'] ?: 'USD';
        $accounts[] = $row;
    }

    echo json_encode($accounts);
}

function getAccountTypes($db)
{
    // Get parameter to determine if we want all types or just active ones
    $includeInactive = isset($_GET['include_inactive']) ? $_GET['include_inactive'] : false;

    if ($includeInactive) {
        $query = 'SELECT * FROM account_types ORDER BY is_active DESC, type_name';
    } else {
        $query = 'SELECT * FROM account_types WHERE is_active = 1 ORDER BY type_name';
    }

    $stmt = $db->prepare($query);
    $stmt->execute();

    $types = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $types[] = $row;
    }

    echo json_encode($types);
}

function updateMonthlyBalance($db)
{
    $data = json_decode(file_get_contents('php://input'));

    try {
        // Get previous month's balance for growth calculation
        $prevQuery = 'SELECT balance FROM balance_history 
                      WHERE account_id = ? 
                      AND month_year < ? 
                      ORDER BY month_year DESC 
                      LIMIT 1';

        $prevStmt = $db->prepare($prevQuery);
        $prevStmt->execute([$data->account_id, $data->month_year]);
        $prevResult = $prevStmt->fetch(PDO::FETCH_ASSOC);

        $previousBalance = $prevResult ? floatval($prevResult['balance']) : 0;
        $currentBalance = floatval($data->balance);

        // Calculate growth automatically
        $calculatedGrowthAmount = $currentBalance - $previousBalance;
        $calculatedGrowthPercentage = $previousBalance > 0 ?
            (($calculatedGrowthAmount / $previousBalance) * 100) : 0;

        $query = 'INSERT INTO balance_history (account_id, balance, month_year, growth_amount, growth_percentage, notes)
                  VALUES (?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE 
                      balance = VALUES(balance),
                      growth_amount = VALUES(growth_amount),
                      growth_percentage = VALUES(growth_percentage),
                      notes = VALUES(notes)';

        $stmt = $db->prepare($query);
        $success = $stmt->execute([
            $data->account_id,
            $currentBalance,
            $data->month_year,
            $calculatedGrowthAmount,
            $calculatedGrowthPercentage,
            $data->notes ?? ''
        ]);

        if ($success) {
            echo json_encode(array(
                'message' => 'Balance updated successfully.',
                'success' => true,
                'growth_amount' => $calculatedGrowthAmount,
                'growth_percentage' => round($calculatedGrowthPercentage, 2),
                'previous_balance' => $previousBalance,
                'current_balance' => $currentBalance
            ));
        } else {
            echo json_encode(array('message' => 'Unable to update balance.', 'success' => false));
        }

    } catch (Exception $e) {
        echo json_encode(array('message' => 'Unable to update balance: ' . $e->getMessage(), 'success' => false));
    }
}

function getLatestMonthData($db)
{
    $query = 'SELECT 
                DATE_FORMAT(bh.month_year, "%Y-%m") as month_year,
                DATE_FORMAT(bh.month_year, "%M %Y") as formatted_month,
                COUNT(DISTINCT bh.account_id) as accounts_with_data,
                SUM(bh.balance) as total_balance,
                SUM(bh.growth_amount) as total_growth
              FROM balance_history bh
              WHERE bh.month_year = (
                  SELECT MAX(month_year) 
                  FROM balance_history
              )
              GROUP BY bh.month_year
              ORDER BY bh.month_year DESC
              LIMIT 1';

    $stmt = $db->prepare($query);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        // If no balance history data, return current month
        $result = array(
            'month_year' => date('Y-m'),
            'formatted_month' => date('F Y'),
            'accounts_with_data' => 0,
            'total_balance' => 0,
            'total_growth' => 0
        );
    }

    echo json_encode($result);
}

function manageAccountType($db)
{
    $data = json_decode(file_get_contents('php://input'));
    $action = $data->action;

    try {
        if ($action === 'create') {
            // Check if type already exists (including inactive ones)
            $checkQuery = 'SELECT id, is_active FROM account_types WHERE type_name = :type_name';
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':type_name', $data->type_name);
            $checkStmt->execute();
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($existing['is_active'] == 0) {
                    echo json_encode(array('message' => 'Account type exists but is inactive. Please reactivate it instead.', 'error' => true));
                } else {
                    echo json_encode(array('message' => 'Account type already exists.', 'error' => true));
                }
                return;
            }

            $query = 'INSERT INTO account_types (type_name, color_code, icon_class) 
                      VALUES (:type_name, :color_code, :icon_class)';
            $stmt = $db->prepare($query);
            $stmt->bindParam(':type_name', $data->type_name);
            $stmt->bindParam(':color_code', $data->color_code);
            $stmt->bindParam(':icon_class', $data->icon_class);

        } elseif ($action === 'update') {
            // Check if another type with same name exists (excluding current one)
            $checkQuery = 'SELECT id FROM account_types WHERE type_name = :type_name AND id != :id';
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':type_name', $data->type_name);
            $checkStmt->bindParam(':id', $data->id);
            $checkStmt->execute();

            if ($checkStmt->fetch()) {
                echo json_encode(array('message' => 'Account type name already exists.', 'error' => true));
                return;
            }

            $query = 'UPDATE account_types 
                      SET type_name = :type_name, color_code = :color_code, icon_class = :icon_class, is_active = :is_active
                      WHERE id = :id';
            $stmt = $db->prepare($query);
            $stmt->bindParam(':type_name', $data->type_name);
            $stmt->bindParam(':color_code', $data->color_code);
            $stmt->bindParam(':icon_class', $data->icon_class);
            $stmt->bindParam(':is_active', $data->is_active);
            $stmt->bindParam(':id', $data->id);

        } elseif ($action === 'delete') {
            // Check if any accounts are using this type
            $checkQuery = 'SELECT COUNT(*) as count FROM accounts WHERE account_type_id = :id';
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $data->id);
            $checkStmt->execute();
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                echo json_encode(array('message' => 'Cannot delete account type. It is being used by ' . $result['count'] . ' account(s).', 'error' => true));
                return;
            }

            $query = 'UPDATE account_types SET is_active = 0 WHERE id = :id';
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $data->id);

        } elseif ($action === 'reactivate') {
            // New action to reactivate inactive types
            $query = 'UPDATE account_types SET is_active = 1 WHERE id = :id';
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $data->id);
        }

        if ($stmt->execute()) {
            echo json_encode(array('message' => 'Account type ' . $action . 'd successfully.', 'success' => true));
        } else {
            echo json_encode(array('message' => 'Unable to ' . $action . ' account type.', 'error' => true));
        }

    } catch (PDOException $e) {
        // Handle database errors gracefully
        if ($e->getCode() == 23000) { // Integrity constraint violation
            echo json_encode(array('message' => 'Account type name must be unique.', 'error' => true));
        } else {
            echo json_encode(array('message' => 'Database error: ' . $e->getMessage(), 'error' => true));
        }
    }
}

function getBalancesByMonth($db)
{
    $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m-01');
    $accountId = isset($_GET['account_id']) ? $_GET['account_id'] : null;

    if ($accountId) {
        // Get balance for specific account and month
        $query = 'SELECT a.id, a.account_name, a.currency, at.type_name as account_type, at.color_code,
                         bh.balance, 
                         bh.month_year, 
                         bh.growth_amount, 
                         bh.growth_percentage, 
                         COALESCE(bh.notes, "") as notes
                  FROM accounts a
                  LEFT JOIN account_types at ON a.account_type_id = at.id
                  LEFT JOIN balance_history bh ON a.id = bh.account_id AND bh.month_year = :month
                  WHERE a.id = :account_id AND a.status = "Active"';

        $stmt = $db->prepare($query);
        $stmt->bindParam(':month', $month);
        $stmt->bindParam(':account_id', $accountId);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $result['currency'] = $result['currency'] ?: 'USD';
            // Only return actual recorded values, no defaults for missing data
            $result['balance'] = $result['balance'] ? floatval($result['balance']) : null;
            $result['growth_amount'] = $result['growth_amount'] ? floatval($result['growth_amount']) : null;
            $result['growth_percentage'] = $result['growth_percentage'] ? floatval($result['growth_percentage']) : null;
        }

        echo json_encode($result ?: array('error' => 'Account not found or inactive'));

    } else {
        // Get balances for all active accounts for specific month - only actual recorded data
        $query = 'SELECT a.id, a.account_name, a.currency, at.type_name as account_type, at.color_code, at.icon_class,
                         bh.balance, 
                         bh.month_year, 
                         bh.growth_amount, 
                         bh.growth_percentage,
                         COALESCE(bh.notes, "") as notes,
                         CASE WHEN bh.balance IS NOT NULL THEN "Has data" ELSE "No data" END as data_status
                  FROM accounts a
                  LEFT JOIN account_types at ON a.account_type_id = at.id
                  LEFT JOIN balance_history bh ON a.id = bh.account_id AND bh.month_year = :month
                  WHERE a.status = "Active"
                  ORDER BY a.account_name';

        $stmt = $db->prepare($query);
        $stmt->bindParam(':month', $month);
        $stmt->execute();

        $targetCurrency = resolveTargetCurrency($db);
        $rates = getExchangeRatesMap($db);

        $accounts = array();
        $totalBalance = 0.0;
        $totalGrowth = 0.0;
        $accountsWithData = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['currency'] = $row['currency'] ?: 'USD';

            // Only process accounts that have actual data for this month
            if ($row['balance'] !== null) {
                $row['balance'] = floatval($row['balance']);
                $row['growth_amount'] = floatval($row['growth_amount']);
                $row['growth_percentage'] = floatval($row['growth_percentage']);
                // Converted figures for fair aggregation across currencies;
                // native balance/growth stay untouched for display.
                $row['converted_balance'] = convertCurrency($row['balance'], $row['currency'], $targetCurrency, $rates);
                $row['converted_growth_amount'] = convertCurrency($row['growth_amount'], $row['currency'], $targetCurrency, $rates);

                $totalBalance += $row['converted_balance'];
                $totalGrowth += $row['converted_growth_amount'];
                $accountsWithData++;
            } else {
                // Set null values for accounts without data for this month
                $row['balance'] = null;
                $row['growth_amount'] = null;
                $row['growth_percentage'] = null;
                $row['converted_balance'] = null;
                $row['converted_growth_amount'] = null;
            }

            $accounts[] = $row;
        }

        echo json_encode(array(
            'month' => $month,
            'display_currency' => $targetCurrency,
            'accounts' => $accounts,
            'summary' => array(
                'total_balance' => floatval($totalBalance),
                'total_growth' => floatval($totalGrowth),
                'accounts_with_data' => intval($accountsWithData),
                'total_accounts' => count($accounts)
            )
        ));
    }
}

function getAvailableMonths($db)
{
    $query = 'SELECT DISTINCT month_year 
              FROM balance_history 
              ORDER BY month_year DESC';

    $stmt = $db->prepare($query);
    $stmt->execute();

    $months = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $months[] = $row['month_year'];
    }

    echo json_encode($months);
}

function getMonthlyComparison($db)
{
    $startMonth = isset($_GET['start_month']) ? $_GET['start_month'] : '';
    $endMonth = isset($_GET['end_month']) ? $_GET['end_month'] : '';

    if (!$startMonth || !$endMonth) {
        echo json_encode(array('error' => 'Start and end months required'));
        return;
    }

    $query = 'SELECT a.id, a.account_name, a.currency, at.type_name as account_type,
                     COALESCE(bh1.balance, 0) as start_balance, 
                     bh1.month_year as start_month,
                     COALESCE(bh2.balance, 0) as end_balance, 
                     bh2.month_year as end_month,
                     (COALESCE(bh2.balance, 0) - COALESCE(bh1.balance, 0)) as balance_change,
                     CASE 
                         WHEN COALESCE(bh1.balance, 0) > 0 THEN 
                             ((COALESCE(bh2.balance, 0) - COALESCE(bh1.balance, 0)) / bh1.balance * 100)
                         ELSE 0 
                     END as percentage_change
              FROM accounts a
              LEFT JOIN account_types at ON a.account_type_id = at.id
              LEFT JOIN balance_history bh1 ON a.id = bh1.account_id AND bh1.month_year = :start_month
              LEFT JOIN balance_history bh2 ON a.id = bh2.account_id AND bh2.month_year = :end_month
              WHERE a.status = "Active" AND (bh1.balance IS NOT NULL OR bh2.balance IS NOT NULL)
              ORDER BY balance_change DESC';

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_month', $startMonth);
    $stmt->bindParam(':end_month', $endMonth);
    $stmt->execute();

    $targetCurrency = resolveTargetCurrency($db);
    $rates = getExchangeRatesMap($db);

    $comparison = array();
    $totalStartConverted = 0.0;
    $totalEndConverted = 0.0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['currency'] = $row['currency'] ?: 'USD';
        // Ensure all numeric fields are properly typed
        $row['start_balance'] = floatval($row['start_balance']);
        $row['end_balance'] = floatval($row['end_balance']);
        $row['balance_change'] = floatval($row['balance_change']);
        $row['percentage_change'] = floatval($row['percentage_change']);

        // Converted figures — this is what any client-side "total across
        // all accounts" sum should use instead of the native amounts,
        // since accounts can be in different currencies.
        $row['start_balance_converted'] = convertCurrency($row['start_balance'], $row['currency'], $targetCurrency, $rates);
        $row['end_balance_converted'] = convertCurrency($row['end_balance'], $row['currency'], $targetCurrency, $rates);
        $row['balance_change_converted'] = $row['end_balance_converted'] - $row['start_balance_converted'];

        $totalStartConverted += $row['start_balance_converted'];
        $totalEndConverted += $row['end_balance_converted'];

        $comparison[] = $row;
    }

    echo json_encode(array(
        'display_currency' => $targetCurrency,
        'data' => $comparison,
        'totals' => array(
            'start_balance' => $totalStartConverted,
            'end_balance' => $totalEndConverted,
            'change' => $totalEndConverted - $totalStartConverted
        )
    ));
}

function getBalanceHistory($db)
{
    try {
        $accountId = isset($_GET['account_id']) ? $_GET['account_id'] : '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

        if (empty($accountId)) {
            echo json_encode(['message' => 'Account ID is required', 'success' => false]);
            return;
        }

        $query = 'SELECT bh.*, a.currency FROM balance_history bh
                  LEFT JOIN accounts a ON a.id = bh.account_id
                  WHERE bh.account_id = ? ORDER BY bh.month_year DESC';
        if ($limit) {
            $query .= ' LIMIT ' . $limit;
        }

        $stmt = $db->prepare($query);
        $stmt->execute([$accountId]);

        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ensure numeric values
        foreach ($history as &$record) {
            $record['currency'] = $record['currency'] ?: 'USD';
            $record['balance'] = floatval($record['balance']);
            $record['growth_amount'] = floatval($record['growth_amount']);
            $record['growth_percentage'] = floatval($record['growth_percentage']);
        }

        echo json_encode($history);

    } catch (Exception $e) {
        echo json_encode(['message' => 'Error: ' . $e->getMessage(), 'success' => false]);
    }
}

function updateAccount($db)
{
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            echo json_encode(['message' => 'Invalid JSON data', 'success' => false]);
            return;
        }

        // Validate required fields
        if (empty($data['id']) || empty($data['account_name']) || empty($data['account_type'])) {
            echo json_encode(['message' => 'Missing required fields', 'success' => false]);
            return;
        }

        // Get account type ID
        $stmt = $db->prepare('SELECT id FROM account_types WHERE type_name = ? AND is_active = 1');
        $stmt->execute([$data['account_type']]);
        $accountType = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$accountType) {
            echo json_encode(['message' => 'Invalid account type', 'success' => false]);
            return;
        }

        // Currency is editable too (e.g. correcting a typo'd code at
        // creation time). Existing balance_history rows are NOT
        // retroactively converted — they stay recorded in whatever
        // currency was active when each entry was made. If someone
        // changes an account's currency after it has balance history,
        // treat that as "this account now operates in a new currency
        // going forward" rather than a retroactive conversion.
        $currency = !empty($data['currency']) ? strtoupper(trim($data['currency'])) : null;
        if ($currency !== null && !preg_match('/^[A-Z]{3}$/', $currency)) {
            echo json_encode(['message' => 'Invalid currency code. Use a 3-letter ISO code (e.g. USD, KES, EUR).', 'success' => false]);
            return;
        }
        if ($currency === null) {
            // Preserve whatever currency the account already has if the
            // client didn't send one (keeps older callers working).
            $curStmt = $db->prepare('SELECT currency FROM accounts WHERE id = ?');
            $curStmt->execute([$data['id']]);
            $existing = $curStmt->fetch(PDO::FETCH_ASSOC);
            $currency = $existing['currency'] ?? 'KES';
        }

        // Update account
        $stmt = $db->prepare('
            UPDATE accounts 
            SET account_name = ?, account_type_id = ?, bank_name = ?, account_number = ?, 
                currency = ?, status = ?, interest_rate = ?, minimum_balance = ?, notes = ?, 
                last_updated = CURRENT_TIMESTAMP
            WHERE id = ?
        ');

        $success = $stmt->execute([
            $data['account_name'],
            $accountType['id'],
            $data['bank_name'] ?? '',
            $data['account_number'] ?? '',
            $currency,
            $data['status'] ?? 'Active',
            $data['interest_rate'] ?? 0,
            $data['minimum_balance'] ?? 0,
            $data['notes'] ?? '',
            $data['id']
        ]);

        if ($success) {
            echo json_encode([
                'message' => 'Account updated successfully',
                'success' => true
            ]);
        } else {
            echo json_encode(['message' => 'Failed to update account', 'success' => false]);
        }

    } catch (Exception $e) {
        echo json_encode(['message' => 'Error: ' . $e->getMessage(), 'success' => false]);
    }
}

function getDistributionByMonth($db)
{
    $month = isset($_GET['month']) ? $_GET['month'] : 'latest';

    try {
        // Determine which month to use
        if ($month === 'latest') {
            // Get the latest month with data
            $latestQuery = 'SELECT MAX(month_year) as latest_month FROM balance_history';
            $latestStmt = $db->prepare($latestQuery);
            $latestStmt->execute();
            $latestResult = $latestStmt->fetch(PDO::FETCH_ASSOC);
            $month = $latestResult['latest_month'] ?: date('Y-m-01');
        } elseif ($month === 'current') {
            $month = date('Y-m-01');
        }

        $query = 'SELECT at.type_name as account_type, at.color_code, at.icon_class,
            COALESCE(SUM(bh.balance), 0) as total_balance, 
            COUNT(a.id) as account_count
            FROM accounts a 
            LEFT JOIN account_types at ON a.account_type_id = at.id 
            LEFT JOIN balance_history bh ON a.id = bh.account_id AND bh.month_year = :month
            WHERE a.status = "Active"
            GROUP BY at.id, at.type_name, at.color_code, at.icon_class
            HAVING total_balance > 0
            ORDER BY total_balance DESC';

        $stmt = $db->prepare($query);
        $stmt->bindParam(':month', $month);
        $stmt->execute();

        $distribution = array();
        $totalBalance = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['total_balance'] = floatval($row['total_balance']);
            $totalBalance += $row['total_balance'];
            $distribution[] = $row;
        }

        // Format month for display
        $monthDisplay = date('F Y', strtotime($month));

        echo json_encode(array(
            'distribution' => $distribution,
            'month' => $month,
            'month_display' => $monthDisplay,
            'total_balance' => $totalBalance,
            'account_types_count' => count($distribution)
        ));

    } catch (PDOException $e) {
        echo json_encode(array(
            'error' => 'Database error: ' . $e->getMessage(),
            'distribution' => [],
            'month' => $month,
            'month_display' => 'Error loading data'
        ));
    }
}

function getSavingsBreakdown($db)
{
    try {
        $savingsTypes = ['Savings', 'MMF', 'Sacco'];

        // Get the latest month that has any savings data
        $latestSavingsQuery = "SELECT MAX(bh.month_year) as latest_month
            FROM balance_history bh
            INNER JOIN accounts a ON bh.account_id = a.id
            INNER JOIN account_types at ON a.account_type_id = at.id
            WHERE a.status = 'Active' AND at.type_name IN ('Savings', 'MMF', 'Sacco')";

        $latestSavingsStmt = $db->prepare($latestSavingsQuery);
        $latestSavingsStmt->execute();
        $latestSavingsResult = $latestSavingsStmt->fetch(PDO::FETCH_ASSOC);
        $latestSavingsMonth = $latestSavingsResult['latest_month'];

        if (!$latestSavingsMonth) {
            echo json_encode([
                'success' => false,
                'message' => 'No savings data available',
                'accounts' => [],
                'summary' => []
            ]);
            return;
        }

        // Get the previous month that has savings data
        $previousSavingsMonthQuery = "SELECT MAX(bh.month_year) as previous_month
            FROM balance_history bh
            INNER JOIN accounts a ON bh.account_id = a.id
            INNER JOIN account_types at ON a.account_type_id = at.id
            WHERE a.status = 'Active' AND at.type_name IN ('Savings', 'MMF', 'Sacco') 
            AND bh.month_year < ?";

        $previousSavingsMonthStmt = $db->prepare($previousSavingsMonthQuery);
        $previousSavingsMonthStmt->execute([$latestSavingsMonth]);
        $previousSavingsMonthResult = $previousSavingsMonthStmt->fetch(PDO::FETCH_ASSOC);
        $previousSavingsMonth = $previousSavingsMonthResult['previous_month'];

        // Get detailed breakdown for each savings account
        $breakdownQuery = "SELECT 
                a.id,
                a.account_name,
                at.type_name as account_type,
                at.color_code,
                at.icon_class,
                COALESCE(bh_current.balance, 0) as current_balance,
                COALESCE(bh_previous.balance, 0) as previous_balance,
                (COALESCE(bh_current.balance, 0) - COALESCE(bh_previous.balance, 0)) as growth_amount,
                CASE 
                    WHEN COALESCE(bh_previous.balance, 0) > 0 THEN 
                        ((COALESCE(bh_current.balance, 0) - COALESCE(bh_previous.balance, 0)) / bh_previous.balance * 100)
                    WHEN COALESCE(bh_current.balance, 0) > 0 THEN 100
                    ELSE 0 
                END as growth_percentage
            FROM accounts a
            INNER JOIN account_types at ON a.account_type_id = at.id
            LEFT JOIN balance_history bh_current ON a.id = bh_current.account_id AND bh_current.month_year = :current_month
            LEFT JOIN balance_history bh_previous ON a.id = bh_previous.account_id AND bh_previous.month_year = :previous_month
            WHERE a.status = 'Active' 
            AND at.type_name IN ('Savings', 'MMF', 'Sacco')
            AND (bh_current.balance IS NOT NULL OR bh_previous.balance IS NOT NULL)
            ORDER BY growth_amount DESC";

        $breakdownStmt = $db->prepare($breakdownQuery);
        $breakdownStmt->bindParam(':current_month', $latestSavingsMonth);
        $breakdownStmt->bindParam(':previous_month', $previousSavingsMonth);
        $breakdownStmt->execute();

        $accounts = [];
        $totalCurrentBalance = 0;
        $totalPreviousBalance = 0;
        $totalGrowth = 0;

        while ($row = $breakdownStmt->fetch(PDO::FETCH_ASSOC)) {
            $row['current_balance'] = floatval($row['current_balance']);
            $row['previous_balance'] = floatval($row['previous_balance']);
            $row['growth_amount'] = floatval($row['growth_amount']);
            $row['growth_percentage'] = floatval($row['growth_percentage']);

            $totalCurrentBalance += $row['current_balance'];
            $totalPreviousBalance += $row['previous_balance'];
            $totalGrowth += $row['growth_amount'];

            $accounts[] = $row;
        }

        // Calculate summary by account type
        $typeSummaryQuery = "SELECT 
                at.type_name as account_type,
                at.color_code,
                at.icon_class,
                COALESCE(SUM(bh_current.balance), 0) as total_current,
                COALESCE(SUM(bh_previous.balance), 0) as total_previous,
                (COALESCE(SUM(bh_current.balance), 0) - COALESCE(SUM(bh_previous.balance), 0)) as total_growth,
                COUNT(a.id) as account_count
            FROM accounts a
            INNER JOIN account_types at ON a.account_type_id = at.id
            LEFT JOIN balance_history bh_current ON a.id = bh_current.account_id AND bh_current.month_year = :current_month
            LEFT JOIN balance_history bh_previous ON a.id = bh_previous.account_id AND bh_previous.month_year = :previous_month
            WHERE a.status = 'Active' 
            AND at.type_name IN ('Savings', 'MMF', 'Sacco')
            GROUP BY at.id, at.type_name, at.color_code, at.icon_class
            ORDER BY total_growth DESC";

        $typeSummaryStmt = $db->prepare($typeSummaryQuery);
        $typeSummaryStmt->bindParam(':current_month', $latestSavingsMonth);
        $typeSummaryStmt->bindParam(':previous_month', $previousSavingsMonth);
        $typeSummaryStmt->execute();

        $typeSummary = [];
        while ($row = $typeSummaryStmt->fetch(PDO::FETCH_ASSOC)) {
            $row['total_current'] = floatval($row['total_current']);
            $row['total_previous'] = floatval($row['total_previous']);
            $row['total_growth'] = floatval($row['total_growth']);
            $row['account_count'] = intval($row['account_count']);
            $typeSummary[] = $row;
        }

        echo json_encode([
            'success' => true,
            'current_month' => $latestSavingsMonth,
            'current_month_display' => date('F Y', strtotime($latestSavingsMonth)),
            'previous_month' => $previousSavingsMonth,
            'previous_month_display' => $previousSavingsMonth ? date('F Y', strtotime($previousSavingsMonth)) : 'N/A',
            'accounts' => $accounts,
            'type_summary' => $typeSummary,
            'totals' => [
                'current_balance' => $totalCurrentBalance,
                'previous_balance' => $totalPreviousBalance,
                'total_growth' => $totalGrowth,
                'growth_percentage' => $totalPreviousBalance > 0 ? (($totalGrowth / $totalPreviousBalance) * 100) : ($totalGrowth > 0 ? 100 : 0)
            ]
        ]);

    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'accounts' => [],
            'summary' => []
        ]);
    }
}

function getTotalBalanceBreakdown($db)
{
    try {
        $targetCurrency = resolveTargetCurrency($db);
        $rates = getExchangeRatesMap($db);

        // Get the two most recent months with data
        $monthsQuery = "SELECT DISTINCT month_year FROM balance_history ORDER BY month_year DESC LIMIT 2";
        $monthsStmt = $db->prepare($monthsQuery);
        $monthsStmt->execute();
        $months = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($months)) {
            echo json_encode([
                'success' => false,
                'message' => 'No balance data available',
                'current_month' => null,
                'previous_month' => null
            ]);
            return;
        }

        $currentMonth = $months[0];
        $previousMonth = isset($months[1]) ? $months[1] : null;

        // Get ALL accounts (including inactive) with their balances for current month
        $currentMonthQuery = "SELECT 
                a.id,
                a.account_name,
                a.bank_name,
                a.status,
                a.currency,
                at.type_name as account_type,
                at.color_code,
                at.icon_class,
                COALESCE(bh.balance, 0) as balance,
                COALESCE(bh.growth_amount, 0) as growth_amount,
                COALESCE(bh.growth_percentage, 0) as growth_percentage
            FROM accounts a
            LEFT JOIN account_types at ON a.account_type_id = at.id
            LEFT JOIN balance_history bh ON a.id = bh.account_id AND bh.month_year = :month
            ORDER BY bh.balance DESC, a.account_name ASC";

        $currentStmt = $db->prepare($currentMonthQuery);
        $currentStmt->bindParam(':month', $currentMonth);
        $currentStmt->execute();

        $currentMonthAccounts = [];
        $currentTotal = 0;
        $currentActiveTotal = 0;
        $currentInactiveTotal = 0;

        while ($row = $currentStmt->fetch(PDO::FETCH_ASSOC)) {
            $row['currency'] = $row['currency'] ?: 'USD';
            $row['balance'] = floatval($row['balance']);
            $row['growth_amount'] = floatval($row['growth_amount']);
            $row['growth_percentage'] = floatval($row['growth_percentage']);
            // Native balance stays untouched for display; converted_balance
            // is what totals/percentages are computed from so mixed
            // currencies don't get added together directly.
            $row['converted_balance'] = convertCurrency($row['balance'], $row['currency'], $targetCurrency, $rates);

            $currentTotal += $row['converted_balance'];
            if ($row['status'] === 'Active') {
                $currentActiveTotal += $row['converted_balance'];
            } else {
                $currentInactiveTotal += $row['converted_balance'];
            }

            $currentMonthAccounts[] = $row;
        }

        // Calculate percentage of total for each account (using converted amounts)
        foreach ($currentMonthAccounts as &$account) {
            $account['percentage'] = $currentTotal > 0 ? ($account['converted_balance'] / $currentTotal) * 100 : 0;
        }
        unset($account);

        // Get ALL accounts with their balances for previous month
        $previousMonthAccounts = [];
        $previousTotal = 0;
        $previousActiveTotal = 0;
        $previousInactiveTotal = 0;

        if ($previousMonth) {
            $previousStmt = $db->prepare($currentMonthQuery);
            $previousStmt->bindParam(':month', $previousMonth);
            $previousStmt->execute();

            while ($row = $previousStmt->fetch(PDO::FETCH_ASSOC)) {
                $row['currency'] = $row['currency'] ?: 'USD';
                $row['balance'] = floatval($row['balance']);
                $row['growth_amount'] = floatval($row['growth_amount']);
                $row['growth_percentage'] = floatval($row['growth_percentage']);
                $row['converted_balance'] = convertCurrency($row['balance'], $row['currency'], $targetCurrency, $rates);

                $previousTotal += $row['converted_balance'];
                if ($row['status'] === 'Active') {
                    $previousActiveTotal += $row['converted_balance'];
                } else {
                    $previousInactiveTotal += $row['converted_balance'];
                }

                $previousMonthAccounts[] = $row;
            }

            // Calculate percentage of total for each account (using converted amounts)
            foreach ($previousMonthAccounts as &$account) {
                $account['percentage'] = $previousTotal > 0 ? ($account['converted_balance'] / $previousTotal) * 100 : 0;
            }
            unset($account);
        }

        // Build comparison data
        $comparisonData = [];
        $accountMap = [];

        // Index previous month accounts by ID
        foreach ($previousMonthAccounts as $acc) {
            $accountMap[$acc['id']] = $acc;
        }

        // Build comparison. Native balance/change stay in the account's own
        // currency (an account never changes currency between two months in
        // practice, so this is exact); converted_* fields are what the
        // modal's totals/aggregates should sum across accounts.
        foreach ($currentMonthAccounts as $currentAcc) {
            $prevBalance = isset($accountMap[$currentAcc['id']]) ? $accountMap[$currentAcc['id']]['balance'] : 0;
            $prevBalanceConverted = isset($accountMap[$currentAcc['id']]) ? $accountMap[$currentAcc['id']]['converted_balance'] : 0;
            $change = $currentAcc['balance'] - $prevBalance;
            $changeConverted = $currentAcc['converted_balance'] - $prevBalanceConverted;
            $changePercent = $prevBalance > 0 ? (($change / $prevBalance) * 100) : ($currentAcc['balance'] > 0 ? 100 : 0);

            $comparisonData[] = [
                'id' => $currentAcc['id'],
                'account_name' => $currentAcc['account_name'],
                'account_type' => $currentAcc['account_type'],
                'color_code' => $currentAcc['color_code'],
                'status' => $currentAcc['status'],
                'currency' => $currentAcc['currency'],
                'previous_balance' => $prevBalance,
                'current_balance' => $currentAcc['balance'],
                'change' => $change,
                'change_percentage' => $changePercent,
                'previous_balance_converted' => $prevBalanceConverted,
                'current_balance_converted' => $currentAcc['converted_balance'],
                'change_converted' => $changeConverted
            ];
        }

        // Sort comparison by converted change amount descending (so mixed
        // currencies rank fairly against each other)
        usort($comparisonData, function($a, $b) {
            return $b['change_converted'] <=> $a['change_converted'];
        });

        // Calculate totals for comparison (already in the target currency)
        $netChange = $currentTotal - $previousTotal;
        $growthRate = $previousTotal > 0 ? (($netChange / $previousTotal) * 100) : ($currentTotal > 0 ? 100 : 0);

        echo json_encode([
            'success' => true,
            'display_currency' => $targetCurrency,
            'current_month' => [
                'month' => $currentMonth,
                'month_display' => date('F Y', strtotime($currentMonth)),
                'accounts' => $currentMonthAccounts,
                'total' => $currentTotal,
                'active_total' => $currentActiveTotal,
                'inactive_total' => $currentInactiveTotal,
                'account_count' => count($currentMonthAccounts)
            ],
            'previous_month' => $previousMonth ? [
                'month' => $previousMonth,
                'month_display' => date('F Y', strtotime($previousMonth)),
                'accounts' => $previousMonthAccounts,
                'total' => $previousTotal,
                'active_total' => $previousActiveTotal,
                'inactive_total' => $previousInactiveTotal,
                'account_count' => count($previousMonthAccounts)
            ] : null,
            'comparison' => [
                'data' => $comparisonData,
                'previous_total' => $previousTotal,
                'current_total' => $currentTotal,
                'net_change' => $netChange,
                'growth_rate' => $growthRate
            ]
        ]);

    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function getLatestMonthDetails($db)
{
    try {
        // Get the latest month with data
        $latestMonthQuery = "SELECT MAX(month_year) as latest_month FROM balance_history";
        $latestStmt = $db->prepare($latestMonthQuery);
        $latestStmt->execute();
        $latestResult = $latestStmt->fetch(PDO::FETCH_ASSOC);
        $latestMonth = $latestResult['latest_month'];

        if (!$latestMonth) {
            echo json_encode([
                'success' => false,
                'message' => 'No balance data available'
            ]);
            return;
        }

        $targetCurrency = resolveTargetCurrency($db);
        $rates = getExchangeRatesMap($db);

        // Get total active accounts count
        $totalAccountsQuery = "SELECT COUNT(*) as total FROM accounts WHERE status = 'Active'";
        $totalAccountsStmt = $db->prepare($totalAccountsQuery);
        $totalAccountsStmt->execute();
        $totalAccountsResult = $totalAccountsStmt->fetch(PDO::FETCH_ASSOC);
        $totalActiveAccounts = intval($totalAccountsResult['total']);

        // Get accounts with data for this month
        $accountsWithDataQuery = "SELECT COUNT(DISTINCT account_id) as count FROM balance_history WHERE month_year = ?";
        $accountsWithDataStmt = $db->prepare($accountsWithDataQuery);
        $accountsWithDataStmt->execute([$latestMonth]);
        $accountsWithDataResult = $accountsWithDataStmt->fetch(PDO::FETCH_ASSOC);
        $accountsWithData = intval($accountsWithDataResult['count']);

        // Get total balance and growth for the month — pull native
        // per-account rows (with currency) and convert-then-sum in PHP,
        // rather than SUM()-ing mixed currencies directly in SQL.
        $totalsRowsQuery = "SELECT a.currency, bh.balance, bh.growth_amount
            FROM balance_history bh
            INNER JOIN accounts a ON bh.account_id = a.id
            WHERE bh.month_year = ? AND a.status = 'Active'";
        $totalsStmt = $db->prepare($totalsRowsQuery);
        $totalsStmt->execute([$latestMonth]);

        $totalBalance = 0.0;
        $totalGrowth = 0.0;
        while ($row = $totalsStmt->fetch(PDO::FETCH_ASSOC)) {
            $currency = $row['currency'] ?: 'USD';
            $totalBalance += convertCurrency($row['balance'], $currency, $targetCurrency, $rates);
            $totalGrowth += convertCurrency($row['growth_amount'], $currency, $targetCurrency, $rates);
        }

        // Get breakdown by account type — group by type AND currency in
        // SQL (can't SUM across currencies), then convert + merge by type.
        $typeBreakdownQuery = "SELECT 
                at.id as type_id,
                at.type_name as account_type,
                at.color_code,
                at.icon_class,
                COALESCE(a.currency, 'USD') as currency,
                COALESCE(SUM(bh.balance), 0) as subtotal_balance,
                COALESCE(SUM(bh.growth_amount), 0) as subtotal_growth,
                COUNT(DISTINCT a.id) as account_count
            FROM accounts a
            INNER JOIN account_types at ON a.account_type_id = at.id
            LEFT JOIN balance_history bh ON a.id = bh.account_id AND bh.month_year = :month
            WHERE a.status = 'Active' AND bh.balance IS NOT NULL
            GROUP BY at.id, at.type_name, at.color_code, at.icon_class, a.currency";
        $typeStmt = $db->prepare($typeBreakdownQuery);
        $typeStmt->bindParam(':month', $latestMonth);
        $typeStmt->execute();

        $typeByTypeId = [];
        while ($row = $typeStmt->fetch(PDO::FETCH_ASSOC)) {
            $typeId = $row['type_id'];
            if (!isset($typeByTypeId[$typeId])) {
                $typeByTypeId[$typeId] = [
                    'account_type' => $row['account_type'],
                    'color_code' => $row['color_code'],
                    'icon_class' => $row['icon_class'],
                    'total_balance' => 0.0,
                    'total_growth' => 0.0,
                    'account_count' => 0,
                ];
            }
            $currency = $row['currency'];
            $typeByTypeId[$typeId]['total_balance'] += convertCurrency($row['subtotal_balance'], $currency, $targetCurrency, $rates);
            $typeByTypeId[$typeId]['total_growth'] += convertCurrency($row['subtotal_growth'], $currency, $targetCurrency, $rates);
            $typeByTypeId[$typeId]['account_count'] += intval($row['account_count']);
        }

        $typeBreakdown = [];
        foreach ($typeByTypeId as $type) {
            $type['percentage'] = $totalBalance > 0 ? ($type['total_balance'] / $totalBalance) * 100 : 0;
            $typeBreakdown[] = $type;
        }
        usort($typeBreakdown, function ($a, $b) {
            return $b['total_balance'] <=> $a['total_balance'];
        });

        // Get top performers (highest growth) — kept in native currency
        // (growth % is currency-agnostic per account), currency exposed
        // so the frontend can label each figure correctly.
        $topPerformersQuery = "SELECT 
                a.account_name,
                a.currency,
                at.type_name as account_type,
                at.color_code,
                bh.balance,
                bh.growth_amount,
                bh.growth_percentage
            FROM balance_history bh
            INNER JOIN accounts a ON bh.account_id = a.id
            INNER JOIN account_types at ON a.account_type_id = at.id
            WHERE bh.month_year = ? AND a.status = 'Active' AND bh.growth_amount > 0
            ORDER BY bh.growth_amount DESC
            LIMIT 5";
        $topStmt = $db->prepare($topPerformersQuery);
        $topStmt->execute([$latestMonth]);

        $topPerformers = [];
        while ($row = $topStmt->fetch(PDO::FETCH_ASSOC)) {
            $row['currency'] = $row['currency'] ?: 'USD';
            $row['balance'] = floatval($row['balance']);
            $row['growth_amount'] = floatval($row['growth_amount']);
            $row['growth_percentage'] = floatval($row['growth_percentage']);
            $topPerformers[] = $row;
        }

        // Get accounts needing attention (no data for current month, negative
        // growth, below minimum, or DORMANT). Dormancy covers two distinct
        // patterns:
        //   1) STALE — the account's most recent balance_history row is 2+
        //      months old (nobody's recorded anything for it at all).
        //   2) STAGNANT — it's still being recorded monthly, but the value
        //      hasn't moved across 3 consecutive readings (this month, last
        //      month, and the month before), meaning no real transactions.
        // Both risk the account being auto-locked, so both get flagged.
        $prevMonth = date('Y-m-01', strtotime($latestMonth . ' -1 month'));
        $prevMonth2 = date('Y-m-01', strtotime($latestMonth . ' -2 months'));

        $needsAttentionQuery = "SELECT 
                a.id,
                a.account_name,
                a.currency,
                at.type_name as account_type,
                at.color_code,
                a.status,
                a.minimum_balance,
                bh.balance,
                bh.growth_amount,
                bh_prev.balance as prev_balance,
                bh_prev2.balance as prev_balance2,
                latest.last_updated_month,
                CASE 
                    WHEN latest.last_updated_month IS NULL THEN 'No data for this month'
                    WHEN TIMESTAMPDIFF(MONTH, latest.last_updated_month, :month1) >= 2 THEN 'No update in 2+ months'
                    WHEN bh.balance IS NULL THEN 'No data for this month'
                    WHEN bh.growth_amount < 0 THEN 'Negative growth'
                    WHEN a.minimum_balance > 0 AND bh.balance < a.minimum_balance THEN 'Below minimum balance'
                    WHEN bh_prev.balance IS NOT NULL AND bh_prev2.balance IS NOT NULL 
                         AND bh.balance = bh_prev.balance AND bh_prev.balance = bh_prev2.balance 
                         THEN 'No balance change in 2+ months'
                    ELSE 'OK'
                END as issue
            FROM accounts a
            INNER JOIN account_types at ON a.account_type_id = at.id
            LEFT JOIN balance_history bh ON a.id = bh.account_id AND bh.month_year = :month2
            LEFT JOIN balance_history bh_prev ON a.id = bh_prev.account_id AND bh_prev.month_year = :prev_month
            LEFT JOIN balance_history bh_prev2 ON a.id = bh_prev2.account_id AND bh_prev2.month_year = :prev_month2
            LEFT JOIN (
                SELECT account_id, MAX(month_year) as last_updated_month
                FROM balance_history
                GROUP BY account_id
            ) latest ON latest.account_id = a.id
            WHERE a.status = 'Active' 
            AND (
                bh.balance IS NULL 
                OR bh.growth_amount < 0 
                OR (a.minimum_balance > 0 AND bh.balance < a.minimum_balance)
                OR (
                    bh_prev.balance IS NOT NULL AND bh_prev2.balance IS NOT NULL 
                    AND bh.balance = bh_prev.balance AND bh_prev.balance = bh_prev2.balance
                )
                OR (
                    latest.last_updated_month IS NOT NULL 
                    AND TIMESTAMPDIFF(MONTH, latest.last_updated_month, :month3) >= 2
                )
            )
            ORDER BY 
                CASE WHEN bh.balance IS NULL THEN 0 ELSE 1 END,
                bh.growth_amount ASC";
        $attentionStmt = $db->prepare($needsAttentionQuery);
        $attentionStmt->bindParam(':month1', $latestMonth);
        $attentionStmt->bindParam(':month2', $latestMonth);
        $attentionStmt->bindParam(':month3', $latestMonth);
        $attentionStmt->bindParam(':prev_month', $prevMonth);
        $attentionStmt->bindParam(':prev_month2', $prevMonth2);
        $attentionStmt->execute();

        $needsAttention = [];
        while ($row = $attentionStmt->fetch(PDO::FETCH_ASSOC)) {
            $row['currency'] = $row['currency'] ?: 'USD';
            $row['balance'] = $row['balance'] !== null ? floatval($row['balance']) : null;
            $row['growth_amount'] = $row['growth_amount'] !== null ? floatval($row['growth_amount']) : null;
            $row['prev_balance'] = $row['prev_balance'] !== null ? floatval($row['prev_balance']) : null;
            $row['prev_balance2'] = $row['prev_balance2'] !== null ? floatval($row['prev_balance2']) : null;
            $row['minimum_balance'] = floatval($row['minimum_balance']);
            $needsAttention[] = $row;
        }

        // Calculate data freshness
        $currentMonth = date('Y-m-01');
        $dataMonth = date('Y-m-01', strtotime($latestMonth));
        $isCurrentMonth = ($currentMonth === $dataMonth);
        $monthsDiff = (strtotime($currentMonth) - strtotime($dataMonth)) / (30 * 24 * 60 * 60);

        $freshness = 'current';
        $freshnessMessage = 'Data is current';
        if (!$isCurrentMonth) {
            if ($monthsDiff <= 1) {
                $freshness = 'recent';
                $freshnessMessage = 'Data is from last month';
            } else {
                $freshness = 'stale';
                $freshnessMessage = 'Data is ' . floor($monthsDiff) . ' months old';
            }
        }

        // Calculate completion percentage
        $completionPercentage = $totalActiveAccounts > 0 ? ($accountsWithData / $totalActiveAccounts) * 100 : 0;

        echo json_encode([
            'success' => true,
            'month' => $latestMonth,
            'month_display' => date('F Y', strtotime($latestMonth)),
            'display_currency' => $targetCurrency,
            'totals' => [
                'total_balance' => $totalBalance,
                'total_growth' => $totalGrowth
            ],
            'accounts' => [
                'total_active' => $totalActiveAccounts,
                'with_data' => $accountsWithData,
                'pending' => $totalActiveAccounts - $accountsWithData,
                'completion_percentage' => round($completionPercentage, 1)
            ],
            'freshness' => [
                'status' => $freshness,
                'message' => $freshnessMessage,
                'is_current_month' => $isCurrentMonth
            ],
            'type_breakdown' => $typeBreakdown,
            'top_performers' => $topPerformers,
            'needs_attention' => $needsAttention
        ]);

    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

// ══════════════════════════════════════════════════════════════════
//  Lightweight currency endpoint
//  GET accounts_api?action=exchange_rates
//
//  Returns everything the frontend needs to build the currency
//  selectors and do its own client-side conversions if it ever needs
//  to: the full rate table, when it was last refreshed by the cron
//  job, the curated currency list for dropdowns, and which currency
//  this admin has saved as their default.
// ══════════════════════════════════════════════════════════════════
function getExchangeRatesEndpoint($db)
{
    try {
        $rates = getExchangeRatesMap($db);

        $lastUpdated = null;
        $lastUpdatedDisplay = null;
        try {
            $stmt = $db->query("SELECT MAX(updated_at) as last_updated FROM tbl_exchange_rates");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $lastUpdated = $row['last_updated'] ?? null;

            if ($lastUpdated) {
                // Format here, server-side, using the app's already-established
                // Africa/Nairobi default timezone (see date_default_timezone_set
                // at the top of this file) — this avoids the frontend having to
                // guess/re-convert timezones from a bare "Y-m-d H:i:s" string,
                // which browsers often misinterpret as their own local time.
                $lastUpdatedDisplay = date('M j, Y g:i A', strtotime($lastUpdated)) . ' (Nairobi time)';
            }
        } catch (Exception $e) {
            // table might not exist yet — non-fatal
        }

        echo json_encode([
            'success' => true,
            'base' => 'USD',
            'rates' => $rates,               // code => rate_to_usd
            'currencies' => supportedCurrencies(), // code => {name, symbol} for dropdowns
            'default_currency' => resolveTargetCurrency($db),
            'last_updated' => $lastUpdated,             // raw DB value, if ever needed
            'last_updated_display' => $lastUpdatedDisplay // ready-to-show string
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

?>