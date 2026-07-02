<?php
// ══════════════════════════════════════════════════════════════════
//  currency_helper.php
//  Shared currency conversion utilities for accounts_api.php
//
//  Model: every rate in tbl_exchange_rates is stored as
//         "1 USD = rate_to_usd units of currency_code"
//  Converting X -> Y always pivots through USD:
//         usd   = amount / rate_to_usd[X]
//         value = usd * rate_to_usd[Y]
// ══════════════════════════════════════════════════════════════════

/**
 * Load all exchange rates as [code => rate_to_usd], cached per-request.
 */
function getExchangeRatesMap($db)
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    try {
        $stmt = $db->query("SELECT currency_code, rate_to_usd FROM tbl_exchange_rates");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cache[$row['currency_code']] = floatval($row['rate_to_usd']);
        }
    } catch (Exception $e) {
        // Table missing or DB error — fall back to USD-only so the
        // dashboard degrades gracefully instead of hard-failing.
    }

    if (!isset($cache['USD'])) {
        $cache['USD'] = 1.0;
    }

    return $cache;
}

/**
 * Convert a single amount from one currency to another.
 * Unknown currencies fall back to a 1:1 rate rather than throwing,
 * so a typo'd/unsupported code never breaks the dashboard — it just
 * won't be converted accurately until its rate is added.
 */
function convertCurrency($amount, $fromCurrency, $toCurrency, array $ratesMap)
{
    $amount = floatval($amount);
    if ($amount == 0 || $fromCurrency === $toCurrency) {
        return $amount;
    }

    $fromRate = isset($ratesMap[$fromCurrency]) && $ratesMap[$fromCurrency] > 0
        ? $ratesMap[$fromCurrency] : 1.0;
    $toRate = isset($ratesMap[$toCurrency]) && $ratesMap[$toCurrency] > 0
        ? $ratesMap[$toCurrency] : 1.0;

    $usd = $amount / $fromRate;
    return $usd * $toRate;
}

/**
 * Work out which currency an endpoint should convert TO:
 *   1. explicit ?to=EUR query param (lets the frontend override per-request)
 *   2. the signed-in admin's saved preferred_currency
 *   3. USD as a last-resort default
 */
function resolveTargetCurrency($db)
{
    if (!empty($_GET['to'])) {
        $code = strtoupper(trim($_GET['to']));
        if (preg_match('/^[A-Z]{3}$/', $code)) {
            return $code;
        }
    }

    $adminId = $_SESSION['odmsaid'] ?? null;
    if ($adminId) {
        try {
            $stmt = $db->prepare(
                "SELECT preferred_currency FROM tbl_user_settings WHERE admin_id = ? LIMIT 1"
            );
            $stmt->execute([$adminId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['preferred_currency'])) {
                return $row['preferred_currency'];
            }
        } catch (Exception $e) {
            // fall through to default
        }
    }

    return 'USD';
}

/**
 * Curated list for <select> dropdowns. tbl_exchange_rates isn't limited
 * to these — add a row to the table and it becomes usable everywhere;
 * this list just drives the UI's picker.
 */
function supportedCurrencies()
{
    return [
        'USD' => ['name' => 'US Dollar',            'symbol' => '$'],
        'EUR' => ['name' => 'Euro',                  'symbol' => '€'],
        'GBP' => ['name' => 'British Pound',         'symbol' => '£'],
        'KES' => ['name' => 'Kenyan Shilling',       'symbol' => 'KSh'],
        'AED' => ['name' => 'UAE Dirham',            'symbol' => 'د.إ'],
        'NGN' => ['name' => 'Nigerian Naira',        'symbol' => '₦'],
        'ZAR' => ['name' => 'South African Rand',    'symbol' => 'R'],
        'INR' => ['name' => 'Indian Rupee',          'symbol' => '₹'],
        'CAD' => ['name' => 'Canadian Dollar',       'symbol' => 'CA$'],
        'AUD' => ['name' => 'Australian Dollar',     'symbol' => 'AU$'],
        'JPY' => ['name' => 'Japanese Yen',          'symbol' => '¥'],
        'CHF' => ['name' => 'Swiss Franc',           'symbol' => 'CHF'],
        'CNY' => ['name' => 'Chinese Yuan',          'symbol' => '¥'],
    ];
}
