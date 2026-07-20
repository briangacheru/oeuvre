<?php
require_once __DIR__ . '/../shared-functions.php';
require_once __DIR__ . '/../version-functions.php';
function email_exists($email)
{
    global $con;

    $sql = "SELECT id FROM tbladmin WHERE email = '$email'";

    $result = $con->query($sql);

    if($result->num_rows == 1 ) {
        return true;
    } else {
        return false;
    }
}
function username_exists($username)
{
    global $con;

    $sql = "SELECT id FROM tbladmin WHERE username = '$username'";

    $result = $con->query($sql);

    if($result->num_rows == 1 ) {
        return true;
    } else {
        return false;
    }
}
function get_name($email) {
    global $con;

    $sql = "SELECT username FROM tbladmin WHERE email = '$email'";

    $result = $con->query($sql);

    $row = $result->fetch_assoc();

    return $row["username"];
}
function get_email($email) {
    global $con;

    $sql = "SELECT email FROM tbladmin WHERE email = '$email'";

    $result = $con->query($sql);

    $row = $result->fetch_assoc();

    return $row["email"];
}
function get_picture($email) {
    global $con;

    $sql = "SELECT Photo FROM tbladmin WHERE email = '$email'";

    $result = $con->query($sql);

    $row = $result->fetch_assoc();

    return $row["Photo"];
}













/**
 * Updates the version number whenever code is edited
 * @param string $type Type of update: 'patch', 'minor', or 'major'
 * @param string $description Description of the update
 * @return array The new version data
 */
function updateVersionNumber($type = 'patch', $description = '') {
    $versionFile = __DIR__ . '/version.json';

    // Create version file if it doesn't exist
    if (!file_exists($versionFile)) {
        $versionData = [
            'major' => 3,
            'minor' => 0,
            'patch' => 0,
            'lastUpdated' => date('Y-m-d'),
            'description' => $description ?: 'Initial release'
        ];
    } else {
        // Read current version
        $versionData = json_decode(file_get_contents($versionFile), true);

        // If file is invalid, create a new one
        if (json_last_error() !== JSON_ERROR_NONE || !isset($versionData['major'])) {
            $versionData = [
                'major' => 3,
                'minor' => 0,
                'patch' => 0,
                'lastUpdated' => date('Y-m-d'),
                'description' => $description ?: 'Initial release'
            ];
        }
    }

    // Update version based on type
    switch ($type) {
        case 'major':
            $versionData['major']++;
            $versionData['minor'] = 0;
            $versionData['patch'] = 0;
            break;
        case 'minor':
            $versionData['minor']++;
            $versionData['patch'] = 0;
            break;
        case 'patch':
        default:
            $versionData['patch']++;
            break;
    }

    // Update last updated date and description
    $versionData['lastUpdated'] = date('Y-m-d');
    if (!empty($description)) {
        $versionData['description'] = $description;
    }

    // Save updated version
    file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT));

    return $versionData;
}

/**
 * Gets the current version data
 * @return array The current version data
 */
function getVersionData() {
    $versionFile = __DIR__ . '/version.json';

    // Check if version file exists
    if (!file_exists($versionFile)) {
        // Create default version file
        $versionData = [
            'major' => 3,
            'minor' => 0,
            'patch' => 0,
            'lastUpdated' => date('Y-m-d'),
            'description' => 'Initial release'
        ];
        file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT));
        return $versionData;
    }

    // Read current version
    $versionData = json_decode(file_get_contents($versionFile), true);

    // Check if parsing was successful
    if (json_last_error() !== JSON_ERROR_NONE || !isset($versionData['major'])) {
        // Create default version file
        $versionData = [
            'major' => 3,
            'minor' => 0,
            'patch' => 0,
            'lastUpdated' => date('Y-m-d'),
            'description' => 'Initial release'
        ];
        file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT));
    }

    return $versionData;
}

// If this file is called directly, update the version
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    $type = isset($_GET['type']) ? $_GET['type'] : 'patch';
    $description = isset($_GET['description']) ? $_GET['description'] : '';
    $versionData = updateVersionNumber($type, $description);
    $versionString = "v{$versionData['major']}.{$versionData['minor']}.{$versionData['patch']}";
    echo "Version updated to $versionString";
}

function formatFileSize($bytes) {
    if ($bytes === null || $bytes === '') return 'Unknown size';

    $bytes = (int)$bytes;
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
}

// Function to calculate time ago
function time_ago($timestamp) {
    $currentTime = time();
    $timeDifference = $currentTime - strtotime($timestamp);

    $seconds = $timeDifference;
    $minutes = round($timeDifference / 60);
    $hours = round($timeDifference / 3600);
    $days = round($timeDifference / 86400);
    $weeks = round($timeDifference / 604800);
    $months = round($timeDifference / 2419200);
    $years = round($timeDifference / 29030400);

    if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return $minutes . " minutes ago";
    } else if ($hours <= 24) {
        return $hours . " hours ago";
    } else if ($days <= 7) {
        return $days . " days ago";
    } else if ($weeks <= 4) {
        return $weeks . " weeks ago";
    } else if ($months <= 12) {
        return $months . " months ago";
    } else {
        return $years . " years ago";
    }
}


function timeSubAgo($datetime, $showFullDateAfter = 31) { // Show full date after 7 days
    $time = time() - strtotime($datetime);
    $days = floor($time / 86400);

    // Show full date if older than specified days
    if ($days > $showFullDateAfter) {
        return date('M j, Y g:i A', strtotime($datetime));
    }

    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    return $days . 'd ago';
}

// Helper functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDateTime($date, $time) {
    return date('M j, Y g:i A', strtotime($date . ' ' . $time));
}

function getPriorityBadge($priority) {
    $badges = [
        'low' => 'badge-success',
        'medium' => 'badge-warning',
        'high' => 'badge-danger'
    ];
    return $badges[$priority] ?? 'badge-secondary';
}

// Strips the trailing ref/date/card-suffix noise banks append to card
// purchases and POS/ATM switch transactions, e.g.
// "JAMAA SUPERMARK PURCHASE/602414890494/24-01-2026 1 447815XXXXXX6619"
// -> "JAMAA SUPERMARK PURCHASE". Shared by bucketBudgetSubcategory() (grouping)
// and extractBudgetTransactionName() (per-row display name).
function stripBudgetTransactionNoise(string $subcategory): string
{
    $stripped = trim($subcategory);
    $stripped = preg_replace('/(PURCHASE|KENSWITCH TRANS|CASH ADVANCE FEE|CASH ADVANCE)\/.*/i', '$1', $stripped);
    $stripped = preg_replace('#/PUR/\S*.*$#i', '', $stripped);
    $stripped = preg_replace('#/CASH/\S*.*$#i', '', $stripped);
    $stripped = preg_replace('/\s+4\d{5}X{6}\d{4}\s*$/i', '', $stripped);
    return preg_replace('/\s+/', ' ', trim($stripped));
}

// Recurring merchant names that recur monthly under a VISA- subcategory
// (subscriptions/metered services) - bundled into one "Subscriptions" bucket
// rather than kept as separate per-merchant lines like one-off retail purchases.
const BUDGET_SUBSCRIPTION_MERCHANTS = '/STARLINK|DIGITALOCEAN|CLAUDE\.?AI|NETFLIX|OPENAI|CHATGPT|SPOTIFY|EXPRESSVPN|ABACUS\.?AI|LOVABLE|TRUEHOST|STEALTHWRITER/i';

// Mpesa/bank statements append a per-transaction reference (recipient, phone
// number, till, or card ref/date/suffix) to otherwise-identical subcategory
// strings, so a raw GROUP BY subcategory can produce 50+ rows a month for what
// is really a handful of recurring transaction types. This collapses those
// into stable buckets while leaving genuinely distinct expenses (specific
// merchants, one-off card purchases) as their own line.
function bucketBudgetSubcategory(string $subcategory): string
{
    $stripped = stripBudgetTransactionNoise($subcategory);

    $rules = [
        '/^Customer Transfer to /i' => 'Customer Transfers',
        '/^Customer Transfer \d/i' => 'Customer Transfers',
        '/^Customer Transfer of Funds Charge$/i' => 'Customer Transfer of Funds Charge',
        '/^Merchant Payment( Online)? to /i' => 'Merchant Payments',
        '/^Customer Payment to /i' => 'Merchant Payments',
        '/^Digital Score Payment to /i' => 'Merchant Payments',
        '/^Pay Bill( Online)? to /i' => 'Bill Payments',
        '/^Customer Withdrawal At Agent Till /i' => 'Agent Withdrawals',
        '/^EAZZY-AGENT WITHDRAWAL/i' => 'Agent Withdrawals',
        '/^(Customer )?Bundle Purchase to /i' => 'Data Bundles & Airtime',
        '/^Recharge for Customer to /i' => 'Data Bundles & Airtime',
        '/^Airtime Purchase$/i' => 'Data Bundles & Airtime',
        '/^Offnet C2B Transfer to .*AIRTEL/i' => 'Airtel Money Transfers',
        '/^Standing Order/i' => 'Standing Orders',
        '/^NAKURU BRANCH TW/i' => 'Bank ATM & Branch Withdrawals',
        '/\bATM\b/i' => 'Bank ATM & Branch Withdrawals',
        '/^APP\//i' => 'Bank App Transfers',
        '/^TPG /i' => 'Bank App Transfers',
        '/^TRANSACTION \+ SMS CHARGE/i' => 'Transaction & SMS Charges',
        '/^COMMISSION .*PAYPAL WITHDRAWAL CHARGES/i' => 'PayPal Withdrawal Charges',
        '/^COMMISSION ON INWARD SWIFT/i' => 'Inward SWIFT Commission',
        '/^Unit Trust Invest To /i' => 'Investments',
        '/CASH ADVANCE/i' => 'Cash Advances',
        '/^Inter Sol Cash Wdrawal charge/i' => 'Withdrawal Charge',
    ];

    foreach ($rules as $pattern => $bucket) {
        if (preg_match($pattern, $stripped)) {
            return $bucket;
        }
    }

    // "<location> KENSWITCH TRANS" (POS/ATM switch transactions) - bundle by location.
    if (preg_match('/KENSWITCH TRANS\s*$/i', $stripped)) {
        $location = trim(preg_replace('/\s*KENSWITCH TRANS\s*$/i', '', $stripped));
        return strtoupper($location) . ' - Card Transactions';
    }

    // VISA card charges: known subscriptions/metered services bundle into one
    // "Subscriptions" line, everything else (one-off card purchases at
    // supermarkets, fuel stations, pharmacies, etc., whether the raw text is
    // "VISA-<merchant>" or "<merchant> PURCHASE") bundles into "Card
    // Purchases" - the modal groups by merchant name (see
    // extractBudgetTransactionName()) so each merchant is still visible there.
    if (preg_match('/^VISA-(.+)$/i', $stripped, $m)) {
        if (preg_match(BUDGET_SUBSCRIPTION_MERCHANTS, $m[1])) {
            return 'Subscriptions';
        }
        return 'Card Purchases';
    }

    if (preg_match('/PURCHASE$/i', $stripped)) {
        return 'Card Purchases';
    }

    return $stripped;
}

// Derives a human-readable "who/what" name for a single transaction's raw
// subcategory text, for display in the breakdown modal's detail table (which
// otherwise only has "Money out" in its description column). Falls back to
// the de-refd subcategory itself when no pattern matches.
function extractBudgetTransactionName(string $subcategory): string
{
    $stripped = stripBudgetTransactionNoise($subcategory);

    $patterns = [
        // "Customer Transfer [to] [-] <phone> [-] <NAME>" (recipient formatting
        // is inconsistent across statement exports, so this covers all variants)
        '/^Customer Transfer\s+(?:to\s+)?-?\s*[\d*]+\s*-?\s*(.+)$/i',
        '/^Customer Payment to (?:Small Business to\s+)?[\w*]+\s*-\s*(.+)$/i',
        '/^(?:Merchant Payment(?: Online)?|Pay Bill(?: Online)?|Customer Withdrawal At Agent Till|Standing Order Pay Bill|Digital Score Payment)(?: to)?\s+[\w.*]+\s*-\s*(.+)$/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $stripped, $m)) {
            $name = preg_replace('/\s+Acc\.?\s*.*$/i', '', trim($m[1]));
            return html_entity_decode($name, ENT_QUOTES);
        }
    }

    // "... by <phone> - <NAME>" (data bundle/recharge recipient)
    if (preg_match('/\bby\s*-?\s*[\w*]+\s*-?\s*(.+)$/i', $stripped, $m)) {
        return html_entity_decode(trim($m[1]), ENT_QUOTES);
    }

    if (preg_match('/^EAZZY-AGENT WITHDRAWAL-([^\/]+)/i', $stripped, $m)) {
        return trim($m[1]);
    }

    // "APP/MPESA/<phone>/<ref>/ <NAME> <ref> ..." - the ref repeats after the
    // name, so anchor on it to isolate just the name.
    if (preg_match('#^APP/MPESA/\d+/(\S+)/\s*(.+?)\s+\1\b#i', $stripped, $m)) {
        return trim($m[2]);
    }

    // "TPG <ref>/COOP/<NAME>/..." bank transfer format.
    if (preg_match('#/COOP/([^/]+)/#i', $stripped, $m)) {
        return trim($m[1]);
    }

    if (preg_match('/for Mobile No\.?\s*([\d*]+)/i', $stripped, $m)) {
        return $m[1];
    }

    if (preg_match('/^VISA-(.+)$/i', $stripped, $m)) {
        return trim($m[1]);
    }

    if (preg_match('/^(.+?)\s+PURCHASE$/i', $stripped, $m)) {
        return trim($m[1]);
    }

    if (preg_match('/^(.+?)\s+KENSWITCH TRANS$/i', $stripped, $m)) {
        return trim($m[1]);
    }

    return $stripped;
}

// Bootstrap text-color class for a transaction's payment tag, used to give
// the breakdown modal's per-name rows a quick visual read of which payment
// channel they went through.
function budgetTagColorClass(?string $tag): string
{
    switch (trim((string)$tag)) {
        case 'Mpesa':
            return 'text-success';
        case 'Card':
            return 'text-warning';
        case 'Airtel Money':
            return 'text-danger';
        default:
            return 'text-primary';
    }
}

// Collapses a bucket's raw per-transaction rows (as produced by
// getExpenseBreakdownByMonth()'s detailedTransactions map) into one row per
// distinct extractBudgetTransactionName(), summing amount + transactionCost
// for each name and sorted by total descending.
function groupBudgetTransactionsByName(array $transactions): array
{
    $groups = [];
    foreach ($transactions as $transaction) {
        $name = extractBudgetTransactionName($transaction['subcategory']);
        $lineTotal = (float)$transaction['amount'] + (float)($transaction['transactionCost'] ?? 0);

        if (!isset($groups[$name])) {
            $groups[$name] = ['name' => $name, 'tag' => $transaction['tag'], 'total' => 0.0];
        }
        $groups[$name]['total'] += $lineTotal;
    }

    $groups = array_values($groups);
    usort($groups, fn($a, $b) => $b['total'] <=> $a['total']);

    return $groups;
}

// Fetches Expense rows for a given month (0 = current month, 1 = last month, ...)
// and groups them by bucketBudgetSubcategory() instead of raw subcategory, so the
// breakdown card shows a handful of recurring buckets rather than one row per
// transaction recipient. Returns [grandTotal, breakdownRows, detailedTransactionsByBucket].
function getExpenseBreakdownByMonth(mysqli $con, int $monthsAgo): array
{
    $stmt = $con->prepare(
        "SELECT subcategory, tag, amount, transactionCost, description, expenseDate
         FROM tblbudget
         WHERE category = 'Expense' AND is_deleted = 0
           AND DATE_FORMAT(expenseDate, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL ? MONTH, '%Y-%m')
         ORDER BY expenseDate"
    );
    $stmt->bind_param('i', $monthsAgo);
    $stmt->execute();
    $result = $stmt->get_result();

    $bucketTotals = [];
    $bucketTags = [];
    $detailedTransactions = [];
    $grandTotal = 0.0;

    while ($row = $result->fetch_assoc()) {
        $lineTotal = (float)$row['amount'] + (float)($row['transactionCost'] ?? 0);
        $bucket = bucketBudgetSubcategory($row['subcategory']);

        $bucketTotals[$bucket] = ($bucketTotals[$bucket] ?? 0) + $lineTotal;
        $bucketTags[$bucket] ??= $row['tag'];
        $grandTotal += $lineTotal;

        $detailedTransactions[$bucket][] = [
            'source' => 'budget',
            'subcategory' => $row['subcategory'],
            'tag' => $row['tag'],
            'amount' => $row['amount'],
            'transactionCost' => $row['transactionCost'],
            'description' => $row['description'],
            'transaction_date' => $row['expenseDate'],
        ];
    }
    $stmt->close();

    arsort($bucketTotals);

    $rows = [];
    foreach ($bucketTotals as $bucket => $total) {
        $rows[] = [
            'subcategory' => $bucket,
            'tag' => $bucketTags[$bucket],
            'total_amount' => $total,
            'percentage' => $grandTotal > 0 ? round(($total / $grandTotal) * 100, 2) : 0,
        ];
    }

    return [$grandTotal, $rows, $detailedTransactions];
}



?>