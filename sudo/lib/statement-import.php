<?php
// Parsers that turn Equity Bank and Safaricom M-Pesa statement exports
// (CSV or PDF) into rows shaped like tblbudget's CSV-import format
// (Category, Subcategory, Description, Amount, Cost, Tag, Date), plus
// best-effort classification and cross-statement internal-transfer
// detection. Built and verified against real sample statements — see
// memory/oeuvre-statement-import.md for the format notes this was
// derived from.

require_once __DIR__ . '/pdf-rc4.php';

class StatementParseException extends \RuntimeException
{
}

final class StatementImport
{
    /**
     * @return array<int, array<string, mixed>> Row shape: category,
     *   subcategory, description, amount, cost, tag, date (Y-m-d H:i:s),
     *   is_internal_transfer, source, transfer_key (internal use).
     */
    public static function parseEquityCsv(string $csvContent, string $sourceLabel): array
    {
        $csvContent = str_replace("\xEF\xBB\xBF", '', $csvContent);
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (empty($lines)) {
            return [];
        }

        $header = str_getcsv(array_shift($lines), ',', '"', '\\');
        $header = array_map(function ($h) {
            return strtolower(trim($h));
        }, $header);
        $expected = ['transaction details', 'payment reference', 'value date', 'credit (money  in)', 'debit (money out)', 'balance'];
        // Equity's own export sometimes uses a single space, sometimes double, between "Money" and "In)".
        $normalized = array_map(function ($h) {
            return preg_replace('/\s+/', ' ', $h);
        }, $header);
        $expectedNormalized = array_map(function ($h) {
            return preg_replace('/\s+/', ' ', $h);
        }, $expected);
        if ($normalized !== $expectedNormalized) {
            throw new StatementParseException('Unrecognized Equity CSV header. Expected: ' . implode(', ', $expected));
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line, ',', '"', '\\');
            if (count($cols) < 6) {
                continue;
            }
            [$details, $ref, $valueDate, $credit, $debit, $balance] = $cols;
            if (strtolower(trim($details)) === 'total') {
                continue;
            }
            $credit = self::parseAmount($credit);
            $debit = self::parseAmount($debit);
            $date = self::parseEquityDate($valueDate);
            if ($date === null) {
                continue;
            }
            $rows[] = self::buildEquityRow(trim($details), trim($ref), $date, $credit, $debit, $sourceLabel);
        }

        return self::foldEquityFees($rows);
    }

    /**
     * Fallback path for when only a PDF is available. Equity's PDF text
     * loses which column (Credit/Debit) an amount belongs to, so
     * direction is inferred from the running balance instead — reliable
     * for every row except the very first one in the statement, which
     * has no prior balance to compare against and is left as Expense
     * with a warning for the admin to check in the preview step.
     */
    public static function parseEquityPdf(string $text, string $sourceLabel): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $rows = [];
        $pendingDetails = [];
        $prevBalance = null;
        // Repeats on every page (running table header) or is only the
        // account-holder letterhead before the table starts — skipped so
        // it doesn't pollute a real row's Details text.
        $boilerplate = [
            'transactions', 'transaction details', 'payment reference', 'value date',
            'credit (money', 'in)', 'debit (money out)', 'balance', 'account',
            'statement',
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === '<>') {
                continue;
            }
            if ($line === 'Transactions') {
                // Table is about to start — drop any letterhead collected so far.
                $pendingDetails = [];
                continue;
            }
            if (in_array(strtolower($line), $boilerplate, true)
                || preg_match('/^Disclaimer:/i', $line)
                || preg_match('/computer generated statement/i', $line)
                || preg_match('/^\d{2}\/\d{2}\/\d{4}\s+Page\s+\d+\/\d+$/i', $line)) {
                continue;
            }
            // The ref/date/amount usually starts its own line (details
            // wrap across preceding lines), but some rows put a short
            // description ahead of the ref on the SAME line — matched
            // here via a non-greedy prefix rather than requiring the ref
            // at line-start, so those rows aren't silently dropped
            // (dropping one also corrupts the next row's balance-delta
            // direction check — confirmed against a real sample file).
            if (preg_match('/^(.*?)([A-Z]?\d{4,12})\t(\d{2}\/\d{2}\/\d{4})\t(.+)$/', $line, $m)
                || preg_match('/^(.*?)([A-Z]?\d{4,12})\s+(\d{2}\/\d{2}\/\d{4})\s+([\d,]+\.\d{2}.*)$/', $line, $m)) {
                $inlineDetails = trim($m[1]);
                $ref = $m[2];
                $date = self::parseEquityDate($m[3]);
                preg_match_all('/-?[\d,]+\.\d{2}/', $m[4], $numMatches);
                $nums = $numMatches[0];
                if ($date === null || count($nums) < 1) {
                    $pendingDetails = [];
                    continue;
                }
                $balance = self::parseAmount(end($nums));
                $amount = count($nums) >= 2 ? self::parseAmount($nums[0]) : $balance;

                if ($inlineDetails !== '') {
                    $pendingDetails[] = $inlineDetails;
                }
                $details = trim(preg_replace('/\s+/', ' ', implode(' ', $pendingDetails)));
                $pendingDetails = [];

                if (strtolower($details) === 'total' || $details === '') {
                    $prevBalance = $balance;
                    continue;
                }

                $credit = 0.0;
                $debit = 0.0;
                $warning = null;
                if ($prevBalance === null) {
                    // No prior balance to diff against — best guess only.
                    $debit = $amount;
                    $warning = 'Direction (income/expense) could not be confirmed for the first row of this PDF — please check it.';
                } elseif ($balance > $prevBalance) {
                    $credit = $amount;
                } else {
                    $debit = $amount;
                }
                $prevBalance = $balance;

                $row = self::buildEquityRow($details, $ref, $date, $credit, $debit, $sourceLabel);
                if ($warning !== null) {
                    $row['warning'] = $warning;
                }
                $rows[] = $row;
            } else {
                $pendingDetails[] = $line;
            }
        }

        return self::foldEquityFees($rows);
    }

    private static function buildEquityRow(string $details, string $ref, string $date, float $credit, float $debit, string $sourceLabel): array
    {
        [$friendlyLabel, $tag, $categoryOverride] = self::classifyEquityDescription($details);
        if ($categoryOverride !== null) {
            $category = $categoryOverride;
            $amount = $debit > 0 ? $debit : $credit;
        } elseif ($credit > 0) {
            $category = 'Income';
            $amount = $credit;
        } else {
            $category = 'Expense';
            $amount = $debit;
        }

        return [
            'category' => $category,
            // subcategory holds the RAW statement text (not the friendly
            // label) — sudo/functions.php's bucketBudgetSubcategory() /
            // extractBudgetTransactionName() already parse this exact raw
            // format to group and label rows in the budget.php "Expenses
            // Breakdown" modal; storing a cleaned label here instead would
            // make imported rows fall through those regexes unrecognized
            // and land in their own fragmented one-off buckets, disconnected
            // from the equivalent legacy-imported rows.
            'subcategory' => $details,
            'description' => $friendlyLabel,
            'amount' => round($amount, 2),
            'cost' => 0.0,
            'tag' => $tag,
            'date' => $date,
            'is_internal_transfer' => false,
            'source' => $sourceLabel,
            'transfer_key' => $ref,
            'transfer_amount' => $credit > 0 ? $credit : -$debit,
        ];
    }

    /**
     * "TRANSACTION + SMS CHARGE" rows share the exact same Payment
     * reference as the transfer they belong to, so grouping by reference
     * and folding the charge row's amount into Cost is safe here. PayPal
     * commission / TPG transfer-fee rows use a DIFFERENT reference from
     * their parent row (confirmed against real statements), so they're
     * deliberately left as their own standalone Expense rows rather than
     * guessed at.
     */
    private static function foldEquityFees(array $rows): array
    {
        $byRef = [];
        foreach ($rows as $i => $row) {
            $byRef[$row['transfer_key']][] = $i;
        }

        $result = [];
        $consumed = [];
        foreach ($byRef as $indices) {
            if (count($indices) !== 2) {
                continue;
            }
            [$a, $b] = $indices;
            // 'subcategory' holds the raw statement text at this point (see buildEquityRow()).
            $aIsFee = preg_match('/charge|commission/i', $rows[$a]['subcategory']) === 1;
            $bIsFee = preg_match('/charge|commission/i', $rows[$b]['subcategory']) === 1;
            if ($aIsFee === $bIsFee) {
                continue; // both or neither look like a fee — leave both standalone.
            }
            [$mainIdx, $feeIdx] = $aIsFee ? [$b, $a] : [$a, $b];
            $main = $rows[$mainIdx];
            $main['cost'] = round($main['cost'] + $rows[$feeIdx]['amount'], 2);
            $result[] = $main;
            $consumed[$mainIdx] = true;
            $consumed[$feeIdx] = true;
        }
        foreach ($rows as $i => $row) {
            if (!isset($consumed[$i])) {
                $result[] = $row;
            }
        }
        return $result;
    }

    private static function classifyEquityDescription(string $details): array
    {
        // [subcategory, tag, categoryOverride]
        $rules = [
            ['/^TRANSACTION \+ SMS CHARGE/i', ['M-Pesa Transfer Charge', 'Mpesa', null]],
            ['/^APP\/MPESA\//i', ['M-Pesa Transfer', 'Mpesa', null]],
            ['/^PAYPAL WD|^PAYPAL COM\d/i', ['PayPal Withdrawal', 'PayPal', null]],
            ['/^COMMISSION|PAYPAL WITHDRAWAL CHARGES/i', ['PayPal Commission', 'PayPal', null]],
            ['/^TPG COMM/i', ['Transfer Fee', 'Card', null]],
            ['/^TPG /i', ['MMF / Sacco Contribution', 'Card', 'Savings']],
            ['/^COMMISSION ON INWARD SWIFT/i', ['SWIFT Commission', 'Card', null]],
            ['/^SWIFT /i', ['Inward SWIFT Transfer', 'Card', null]],
            ['/^VISA-|PURCHASE/i', ['Card Purchase', 'Card', null]],
            ['/^APP\/(?!MPESA)/i', ['Account Transfer', 'Card', null]],
        ];
        foreach ($rules as [$pattern, $result]) {
            if (preg_match($pattern, $details)) {
                return $result;
            }
        }
        return ['Other', 'Card', null];
    }

    /**
     * @param string $text  Decrypted PDF text (via smalot/pdfparser).
     */
    public static function parseMpesaPdf(string $text, string $sourceLabel): array
    {
        if (!preg_match_all(
            '/([A-Z][A-Z0-9]{7,11})\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+(.*?)(?=(?:[A-Z][A-Z0-9]{7,11}\s+\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})|\z)/s',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        $byReceipt = [];
        foreach ($matches as $m) {
            [, $receipt, $datetime, $rest] = $m;
            if (!preg_match('/^(.*?)\b(Completed|Failed|Pending)\b(.*)$/s', $rest, $rm)) {
                continue;
            }
            [, $detailsRaw, $status, $numsPart] = $rm;
            if ($status !== 'Completed') {
                continue;
            }
            $details = trim(preg_replace('/\s+/', ' ', $detailsRaw));
            preg_match_all('/-?[\d,]+\.\d{2}/', $numsPart, $numMatches);
            $nums = $numMatches[0];
            if (count($nums) < 2) {
                continue;
            }
            $amount = self::parseAmount($nums[0]);
            $byReceipt[$receipt][] = [
                'details' => $details,
                'amount' => $amount,
                'datetime' => $datetime,
            ];
        }

        $rows = [];
        foreach ($byReceipt as $receipt => $entries) {
            $feeEntries = [];
            $mainEntries = [];
            foreach ($entries as $e) {
                if (preg_match('/\bCharge$/i', $e['details'])) {
                    $feeEntries[] = $e;
                } else {
                    $mainEntries[] = $e;
                }
            }

            if (count($mainEntries) === 1) {
                $main = $mainEntries[0];
                $cost = 0.0;
                foreach ($feeEntries as $fee) {
                    $cost += abs($fee['amount']);
                }
                $rows[] = self::buildMpesaRow($main['details'], $main['amount'], $cost, $main['datetime'], $sourceLabel, $receipt);
            } else {
                // No single clear "main" leg (0 or 2+) — import every leg
                // standalone rather than guessing which one to fold into.
                foreach ($entries as $e) {
                    $rows[] = self::buildMpesaRow($e['details'], $e['amount'], 0.0, $e['datetime'], $sourceLabel, $receipt);
                }
            }
        }

        return $rows;
    }

    private static function buildMpesaRow(string $details, float $signedAmount, float $cost, string $datetime, string $sourceLabel, string $receipt): array
    {
        $category = $signedAmount >= 0 ? 'Income' : 'Expense';
        $amount = abs($signedAmount);

        return [
            'category' => $category,
            // subcategory holds the RAW M-Pesa Details text — see the
            // comment on buildEquityRow() for why (bucketBudgetSubcategory()
            // / extractBudgetTransactionName() in sudo/functions.php parse
            // this exact raw format for the "Expenses Breakdown" modal).
            'subcategory' => $details,
            'description' => self::classifyMpesaDetails($details),
            'amount' => round($amount, 2),
            'cost' => round($cost, 2),
            'tag' => 'Mpesa',
            'date' => $datetime,
            'is_internal_transfer' => false,
            'source' => $sourceLabel,
            'transfer_key' => $receipt,
            'transfer_amount' => $signedAmount,
        ];
    }

    private static function classifyMpesaDetails(string $details): string
    {
        $rules = [
            ['/^Business Payment from/i', 'Received from Business/Bank'],
            ['/^Funds received from/i', 'Received Money'],
            ['/^Customer Transfer/i', 'Send Money'],
            ['/^Customer Payment to Small Business/i', 'Send Money'],
            ['/^Pay ?Bill/i', 'Paybill'],
            ['/^Merchant Payment/i', 'Buy Goods'],
            ['/^Customer Withdrawal At Agent/i', 'Agent Withdrawal'],
            ['/^Customer Bundle Purchase/i', 'Bundles & Data'],
            ['/^Airtime Purchase/i', 'Airtime'],
            ['/^Unit Trust/i', 'Money Market Fund'],
        ];
        foreach ($rules as [$pattern, $subcategory]) {
            if (preg_match($pattern, $details)) {
                return $subcategory;
            }
        }
        return 'Other';
    }

    /**
     * Marks rows as internal transfers when a same-day, same-amount,
     * opposite-direction pair shows up across two DIFFERENT sources in
     * this batch (e.g. an Equity debit funding an M-Pesa top-up, or a
     * transfer between your own two Equity accounts). Deliberately a
     * simple amount+date+cross-source heuristic rather than parsing the
     * embedded transaction codes/phone numbers each source uses
     * differently — it generalizes across all source-pair combinations,
     * and any mismatch is still editable by the admin in the preview
     * step before import.
     */
    public static function detectInternalTransfers(array &$rows): void
    {
        $groups = [];
        foreach ($rows as $i => $row) {
            if ($row['category'] === 'Savings') {
                continue;
            }
            $day = substr($row['date'], 0, 10);
            $amountKey = number_format(abs($row['transfer_amount']), 2, '.', '');
            $groups[$day . '|' . $amountKey][] = $i;
        }

        foreach ($groups as $indices) {
            if (count($indices) < 2) {
                continue;
            }
            $positives = array_filter($indices, function ($i) use ($rows) {
                return $rows[$i]['transfer_amount'] > 0;
            });
            $negatives = array_filter($indices, function ($i) use ($rows) {
                return $rows[$i]['transfer_amount'] < 0;
            });
            if (empty($positives) || empty($negatives)) {
                continue;
            }
            foreach ($indices as $i) {
                foreach ($indices as $j) {
                    if ($i !== $j && $rows[$i]['source'] !== $rows[$j]['source']) {
                        $rows[$i]['is_internal_transfer'] = true;
                        break;
                    }
                }
            }
        }
    }

    private static function parseAmount($value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }
        return (float) str_replace(',', '', $value);
    }

    private static function parseEquityDate(string $ddmmyyyy): ?string
    {
        if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', trim($ddmmyyyy), $m)) {
            return null;
        }
        [, $d, $mo, $y] = $m;
        if (!checkdate((int) $mo, (int) $d, (int) $y)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d 00:00:00', $y, $mo, $d);
    }

    /**
     * Decrypts (if needed) and extracts text from a PDF file using
     * PdfRc4Decryptor + smalot/pdfparser.
     */
    public static function extractPdfText(string $filePath, string $password = ''): string
    {
        $bytes = file_get_contents($filePath);
        if ($bytes === false) {
            throw new StatementParseException('Could not read the uploaded file.');
        }
        $decrypted = PdfRc4Decryptor::decrypt($bytes, $password);

        $tmpFile = tempnam(sys_get_temp_dir(), 'stmt') . '.pdf';
        file_put_contents($tmpFile, $decrypted);
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($tmpFile);
            return $pdf->getText();
        } catch (\Throwable $e) {
            throw new StatementParseException('Could not read this PDF: ' . $e->getMessage());
        } finally {
            @unlink($tmpFile);
        }
    }
}
