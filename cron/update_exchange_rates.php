<?php
// ══════════════════════════════════════════════════════════════════
//  update_exchange_rates.php
//  Run this on a daily cron (rates only change once a day anyway).
//  Pulls live rates from Frankfurter (api.frankfurter.dev) — free,
//  no API key, ~200 currencies including KES/NGN/AED/etc — and
//  upserts them into tbl_exchange_rates as "1 USD = X <currency>".
//
//  Crontab example (once a day at 05:10 Nairobi time), logging to a
//  FILE instead of /dev/null so failures are actually visible:
//    10 5 * * * /usr/local/bin/ea-php82 /home/monkbria/web.monkbrian.com/cron/update_exchange_rates.php >> /home/monkbria/web.monkbrian.com/cron/exchange_rates.log 2>&1
// ══════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../sudo/dbcon.php';
require_once __DIR__ . '/../sudo/currency_helper.php';

date_default_timezone_set('Africa/Nairobi');

function logLine($msg)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

/**
 * Fetch a single rate, trying cURL first (works on far more hosts than
 * file_get_contents — shared/cPanel hosting frequently has
 * allow_url_fopen disabled or blocks stream wrappers while still
 * allowing cURL) and falling back to file_get_contents if cURL isn't
 * available. Returns [rate|null, errorMessage|null].
 */
function fetchRate($base, $quote)
{
    $url = "https://api.frankfurter.dev/v2/rate/{$base}/{$quote}";

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'itasker-exchange-rate-cron/1.0',
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return [null, "curl error: {$curlErr}"];
        }
        if ($httpCode !== 200) {
            return [null, "HTTP {$httpCode}: " . substr($raw, 0, 200)];
        }

        $data = json_decode($raw, true);
        if (!isset($data['rate']) || !is_numeric($data['rate'])) {
            return [null, 'unexpected response: ' . substr($raw, 0, 200)];
        }
        return [floatval($data['rate']), null];
    }

    // Fallback: file_get_contents with an explicit stream context.
    if (!ini_get('allow_url_fopen')) {
        return [null, 'curl unavailable AND allow_url_fopen is disabled — no way to reach the internet from PHP CLI'];
    }

    $ctx = stream_context_create([
        'http' => ['timeout' => 10, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $raw = @file_get_contents($url, false, $ctx);

    if ($raw === false) {
        $err = error_get_last();
        return [null, 'file_get_contents failed: ' . ($err['message'] ?? 'unknown error')];
    }

    $data = json_decode($raw, true);
    if (!isset($data['rate']) || !is_numeric($data['rate'])) {
        return [null, 'unexpected response: ' . substr($raw, 0, 200)];
    }

    return [floatval($data['rate']), null];
}

logLine('Exchange rate update starting.');
logLine('PHP version: ' . PHP_VERSION . ' | curl: ' . (function_exists('curl_init') ? 'yes' : 'no') . ' | allow_url_fopen: ' . (ini_get('allow_url_fopen') ? 'yes' : 'no'));

// Sanity check: does the table even exist? If the migration SQL was
// never run, every upsert below will fail — surface that clearly
// instead of quietly logging N failures with no context.
try {
    $dbh->query("SELECT 1 FROM tbl_exchange_rates LIMIT 1");
} catch (Exception $e) {
    logLine('FATAL: tbl_exchange_rates does not exist or is not reachable (' . $e->getMessage() . '). Run migration_currency.sql first.');
    exit(1);
}

// Currencies we keep fresh. Extend this list any time — every code
// here becomes selectable in the account/settings currency dropdowns.
$currencies = array_keys(supportedCurrencies());

$updated = [];
$failed  = [];

// Computed once, from PHP's own clock (already forced to Africa/Nairobi
// above via date_default_timezone_set). We write this explicitly rather
// than trusting MySQL's CURRENT_TIMESTAMP, because CURRENT_TIMESTAMP is
// evaluated on the DATABASE server's clock — which is very often UTC
// even when the app server/PHP is set to Nairobi, producing a
// stored value that's off by whatever the UTC offset is (e.g. 3 hours).
$nowNairobi = date('Y-m-d H:i:s');

foreach ($currencies as $code) {
    if ($code === 'USD') {
        continue; // identity rate, always 1.0
    }

    list($rate, $error) = fetchRate('USD', $code);

    if ($rate === null) {
        $failed[] = "{$code}: {$error}";
        continue;
    }

    try {
        $stmt = $dbh->prepare(
            "INSERT INTO tbl_exchange_rates (currency_code, rate_to_usd, updated_at)
             VALUES (:code, :rate, :updated_at)
             ON DUPLICATE KEY UPDATE rate_to_usd = :rate2, updated_at = :updated_at2"
        );
        $stmt->execute([
            ':code' => $code,
            ':rate' => $rate,
            ':updated_at' => $nowNairobi,
            ':rate2' => $rate,
            ':updated_at2' => $nowNairobi,
        ]);
        $updated[] = "{$code}={$rate}";
    } catch (Exception $e) {
        $failed[] = $code . ' (db: ' . $e->getMessage() . ')';
    }

    // Frankfurter has no hard rate limit, but a tiny pause is polite.
    usleep(150000); // 150ms
}

// Make sure USD's identity row always exists.
try {
    $stmt = $dbh->prepare(
        "INSERT INTO tbl_exchange_rates (currency_code, rate_to_usd, updated_at)
         VALUES ('USD', 1.0, :updated_at)
         ON DUPLICATE KEY UPDATE rate_to_usd = 1.0, updated_at = :updated_at2"
    );
    $stmt->execute([':updated_at' => $nowNairobi, ':updated_at2' => $nowNairobi]);
} catch (Exception $e) {
    logLine('Could not upsert USD identity row: ' . $e->getMessage());
}

logLine('Exchange rate update complete.');
logLine('Updated (' . count($updated) . '): ' . (count($updated) ? implode(', ', $updated) : 'none'));
if ($failed) {
    logLine('Failed (' . count($failed) . '):');
    foreach ($failed as $f) {
        logLine('  - ' . $f);
    }
}