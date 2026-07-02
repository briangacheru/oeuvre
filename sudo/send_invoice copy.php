<?php
require_once __DIR__ . '/../env.php';
/**
 * send_invoice.php
 * Fetches completed unpaid tasks, unpaid bonuses, and unsettled overdrafts
 * for a writer. Overdrafts are shown and subtracted from the payable total.
 */

ob_start();

include "head.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendJsonResponse($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['writer_name'])) {
    sendJsonResponse(['success' => false, 'message' => 'Invalid request.']);
}

$writerName = mysqli_real_escape_string($con, trim($_POST['writer_name']));

// ── 1. Get writer details ─────────────────────────────────────────────────
$writerQuery = mysqli_query($con,
    "SELECT username, email FROM tblwriters
     WHERE username = '$writerName' AND is_deleted = 0 AND is_verified = 1
     LIMIT 1"
);
$writer = mysqli_fetch_assoc($writerQuery);

if (!$writer) {
    sendJsonResponse(['success' => false, 'message' => 'Writer not found.']);
}

$writerEmail    = $writer['email'];
$writerUsername = $writer['username'];

// ── 2. Fetch completed, unpaid tasks ─────────────────────────────────────
$tasksQuery = mysqli_query($con,
    "SELECT topic, pages, cpp, (pages * cpp) AS total_cost
     FROM tbltasks
     WHERE writer = '$writerName'
       AND LOWER(status) = 'completed'
       AND is_paid = 0
       AND is_deleted = 0
     ORDER BY due_date ASC"
);

$tasks           = [];
$tasksGrandTotal = 0.0;

if ($tasksQuery) {
    while ($row = mysqli_fetch_assoc($tasksQuery)) {
        $tasks[]          = $row;
        $tasksGrandTotal += (float) $row['total_cost'];
    }
}

// ── 3. Fetch unpaid bonuses ───────────────────────────────────────────────
$bonusQuery = mysqli_query($con,
    "SELECT description, amount, od_date
     FROM tbloverdrafts
     WHERE writer = '$writerName'
       AND record_type = 'bonus'
       AND is_settled = 0
       AND is_deleted = 0
     ORDER BY od_date ASC"
);

$bonuses    = [];
$bonusTotal = 0.0;

if ($bonusQuery) {
    while ($row = mysqli_fetch_assoc($bonusQuery)) {
        $bonuses[]   = $row;
        $bonusTotal += (float) $row['amount'];
    }
}

// ── 4. Fetch unsettled overdrafts ─────────────────────────────────────────
$overdraftQuery = mysqli_query($con,
    "SELECT description, amount, od_date, tag
     FROM tbloverdrafts
     WHERE writer = '$writerName'
       AND (record_type = 'overdraft' OR record_type IS NULL)
       AND is_settled = 0
       AND is_deleted = 0
     ORDER BY od_date ASC"
);

$overdrafts    = [];
$overdraftTotal = 0.0;

if ($overdraftQuery) {
    while ($row = mysqli_fetch_assoc($overdraftQuery)) {
        $overdrafts[]    = $row;
        $overdraftTotal += (float) $row['amount'];
    }
}

if (empty($tasks) && empty($bonuses)) {
    sendJsonResponse(['success' => false, 'message' => 'No unpaid completed tasks or bonuses found for this writer.']);
}

// ── 5. Calculate totals ───────────────────────────────────────────────────
$subtotalBeforeDeductions = $tasksGrandTotal + $bonusTotal;
$amountPayable            = $subtotalBeforeDeductions - $overdraftTotal;
$invoiceDate              = date('jS F Y');

// ── 6. Build email HTML ───────────────────────────────────────────────────
$companyLogo = 'https://web.monkbrian.com/assets/img/team/itasker-email-header.png';

// ── Tasks rows ────────────────────────────────────────────────────────────
$tasksRowsHtml = '';
foreach ($tasks as $t) {
    $tasksRowsHtml .= "
        <tr>
            <td style='padding:8px 10px; border-bottom:1px solid #eee; font-size:14px; color:#333;'>" . htmlspecialchars($t['topic']) . "</td>
            <td style='padding:8px 10px; border-bottom:1px solid #eee; font-size:14px; text-align:center; color:#333;'>" . (int)$t['pages'] . "</td>
            <td style='padding:8px 10px; border-bottom:1px solid #eee; font-size:14px; text-align:right; color:#333;'>Ksh " . number_format((float)$t['cpp'], 2) . "</td>
            <td style='padding:8px 10px; border-bottom:1px solid #eee; font-size:14px; text-align:right; font-weight:bold; color:#0073e6;'>Ksh " . number_format((float)$t['total_cost'], 2) . "</td>
        </tr>";
}

$tasksSectionHtml = '';
if (!empty($tasks)) {
    $tasksSectionHtml = "
    <h3 style='color:#0073e6; margin-top:24px; margin-bottom:8px; font-size:16px;'>Completed Tasks</h3>
    <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse; margin-bottom:16px;'>
        <thead>
            <tr style='background:#0073e6;'>
                <th style='padding:9px 10px; color:#fff; font-size:13px; text-align:left;'>Topic</th>
                <th style='padding:9px 10px; color:#fff; font-size:13px; text-align:center;'>Pages</th>
                <th style='padding:9px 10px; color:#fff; font-size:13px; text-align:right;'>CPP</th>
                <th style='padding:9px 10px; color:#fff; font-size:13px; text-align:right;'>Total</th>
            </tr>
        </thead>
        <tbody>
            {$tasksRowsHtml}
            <tr style='background:#eaf4fc;'>
                <td colspan='3' style='padding:9px 10px; font-size:14px; font-weight:bold; color:#333;'>Tasks Sub-total</td>
                <td style='padding:9px 10px; font-size:14px; font-weight:bold; text-align:right; color:#0073e6;'>Ksh " . number_format($tasksGrandTotal, 2) . "</td>
            </tr>
        </tbody>
    </table>";
}

// ── Bonus rows ────────────────────────────────────────────────────────────
$bonusRowsHtml = '';
foreach ($bonuses as $b) {
    $bonusRowsHtml .= "
        <tr>
            <td style='padding:8px 10px; border-bottom:1px solid #eee; font-size:14px; color:#333;'>" . htmlspecialchars($b['description']) . "</td>
            <td style='padding:8px 10px; border-bottom:1px solid #eee; font-size:14px; text-align:center; color:#333;'>" . date('d M Y', strtotime($b['od_date'])) . "</td>
            <td style='padding:8px 10px; border-bottom:1px solid #eee; font-size:14px; text-align:right; font-weight:bold; color:#0073e6;'>Ksh " . number_format((float)$b['amount'], 2) . "</td>
        </tr>";
}

$bonusSectionHtml = '';
if (!empty($bonuses)) {
    $bonusSectionHtml = "
    <h3 style='color:#0073e6; margin-top:24px; margin-bottom:8px; font-size:16px;'>Bonuses</h3>
    <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse; margin-bottom:16px;'>
        <thead>
            <tr style='background:#0073e6;'>
                <th style='padding:9px 10px; color:#fff; font-size:13px; text-align:left;'>Description</th>
                <th style='padding:9px 10px; color:#fff; font-size:13px; text-align:center;'>Date</th>
                <th style='padding:9px 10px; color:#fff; font-size:13px; text-align:right;'>Amount</th>
            </tr>
        </thead>
        <tbody>
            {$bonusRowsHtml}
            <tr style='background:#eaf4fc;'>
                <td colspan='2' style='padding:9px 10px; font-size:14px; font-weight:bold; color:#333;'>Bonuses Sub-total</td>
                <td style='padding:9px 10px; font-size:14px; font-weight:bold; text-align:right; color:#0073e6;'>Ksh " . number_format($bonusTotal, 2) . "</td>
            </tr>
        </tbody>
    </table>";
}

// ── Overdraft rows ────────────────────────────────────────────────────────
$overdraftRowsHtml = '';
foreach ($overdrafts as $o) {
    $overdraftRowsHtml .= "
        <tr>
            <td style='padding:8px 10px; border-bottom:1px solid #fde; font-size:14px; color:#333;'>" . htmlspecialchars($o['description']) . "</td>
            <td style='padding:8px 10px; border-bottom:1px solid #fde; font-size:14px; text-align:center; color:#333;'>" . date('d M Y', strtotime($o['od_date'])) . "</td>
            <td style='padding:8px 10px; border-bottom:1px solid #fde; font-size:14px; text-align:center; color:#333;'>" . htmlspecialchars($o['tag'] ?? '—') . "</td>
            <td style='padding:8px 10px; border-bottom:1px solid #fde; font-size:14px; text-align:right; font-weight:bold; color:#e53535;'>− Ksh " . number_format((float)$o['amount'], 2) . "</td>
        </tr>";
}

$overdraftSectionHtml = '';
if (!empty($overdrafts)) {
    $overdraftSectionHtml = "
    <h3 style='color:#e53535; margin-top:24px; margin-bottom:8px; font-size:16px;'>Overdraft Deductions</h3>
    <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse; margin-bottom:16px;'>
        <thead>
            <tr style='background:#e53535;'>
                <th style='padding:9px 10px; color:#fff; font-size:13px; text-align:left;'>Description</th>
                <th style='padding:9px 10px; color:#fff; font-size:13px; text-align:center;'>Date</th>
                <th style='padding:9px 10px; color:#fff; font-size:13px; text-align:center;'>Method</th>
                <th style='padding:9px 10px; color:#fff; font-size:13px; text-align:right;'>Amount</th>
            </tr>
        </thead>
        <tbody>
            {$overdraftRowsHtml}
            <tr style='background:#fdf0f0;'>
                <td colspan='3' style='padding:9px 10px; font-size:14px; font-weight:bold; color:#333;'>Overdraft Sub-total</td>
                <td style='padding:9px 10px; font-size:14px; font-weight:bold; text-align:right; color:#e53535;'>− Ksh " . number_format($overdraftTotal, 2) . "</td>
            </tr>
        </tbody>
    </table>";
}

// ── Summary breakdown rows ────────────────────────────────────────────────
$summaryRows  = "<tr><td style='padding:7px 10px; font-size:14px; color:#555;'>Tasks Total</td><td style='padding:7px 10px; font-size:14px; text-align:right; color:#555;'>Ksh " . number_format($tasksGrandTotal, 2) . "</td></tr>";
if ($bonusTotal > 0) {
    $summaryRows .= "<tr><td style='padding:7px 10px; font-size:14px; color:#555;'>Bonuses</td><td style='padding:7px 10px; font-size:14px; text-align:right; color:#0073e6;'>+ Ksh " . number_format($bonusTotal, 2) . "</td></tr>";
}
if ($overdraftTotal > 0) {
    $summaryRows .= "<tr><td style='padding:7px 10px; font-size:14px; color:#555; border-bottom:1px solid #eee;'>Overdraft Deductions</td><td style='padding:7px 10px; font-size:14px; text-align:right; color:#e53535; border-bottom:1px solid #eee;'>− Ksh " . number_format($overdraftTotal, 2) . "</td></tr>";
}

$htmlBody = "
<!DOCTYPE html>
<html>
<head>
<style>
body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
.email-container { max-width: 620px; background: #ffffff; margin: 0 auto; padding: 20px; border-radius: 8px; box-shadow: 0px 2px 10px rgba(0,0,0,0.1); }
.email-header { text-align: center; border-bottom: 2px solid #0073e6; padding-bottom: 15px; }
.email-header img { max-width: 100%; height: auto; max-height: 100px; }
.email-content { padding: 20px; }
.email-content h2 { color: #0073e6; text-align: center; }
.email-content p { font-size: 16px; line-height: 1.5; color: #333; }
.highlight { font-weight: bold; color: #0073e6; }
.footer { text-align: center; padding-top: 15px; font-size: 12px; color: #777; border-top: 1px solid #eee; margin-top: 20px; }
</style>
</head>
<body>
<div class='email-container'>
    <div class='email-header'>
        <img src='{$companyLogo}' alt='iTasker'>
    </div>
    <div class='email-content'>
        <h2>Payment Invoice</h2>
        <p>Hello <span class='highlight'>{$writerUsername}</span>,</p>
        <p>Payment invoice as of <span class='highlight'>{$invoiceDate}</span>.</p>

        {$tasksSectionHtml}
        {$bonusSectionHtml}
        {$overdraftSectionHtml}

        <!-- Summary breakdown -->
        <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse; margin-top:16px; border:1px solid #eee; border-radius:6px;'>
            <tbody>
                {$summaryRows}
                <tr style='background:#0073e6;'>
                    <td style='padding:12px 10px; font-size:16px; font-weight:bold; color:#fff;'>Amount Payable to You</td>
                    <td style='padding:12px 10px; font-size:16px; font-weight:bold; color:#fff; text-align:right;'>Ksh " . number_format($amountPayable, 2) . "</td>
                </tr>
            </tbody>
        </table>

        <p style='margin-top:20px;'>Thank you! The amount payable will be sent to your mobile banking account shortly and the above tasks will be marked as paid on the website.</p>
    </div>
    <div class='footer'>
        <p>For any questions, contact <a href='mailto:bryo4419@gmail.com'>bryo4419@gmail.com</a></p>
        <p>&copy; " . date('Y') . " iTasker. All rights reserved.</p>
        <p style='font-size:11px; color:#aaa;'>This is an automated message. Please do not reply directly to this email.</p>
    </div>
</div>
</body>
</html>";

// ── Plain-text fallback ───────────────────────────────────────────────────
$altBody  = "Hello {$writerUsername},\n\n";
$altBody .= "Payment invoice as of {$invoiceDate}.\n\n";

if (!empty($tasks)) {
    $altBody .= "COMPLETED TASKS\n" . str_repeat('-', 62) . "\n";
    $altBody .= sprintf("%-38s %5s %9s %10s\n", 'Topic', 'Pages', 'CPP', 'Total');
    $altBody .= str_repeat('-', 62) . "\n";
    foreach ($tasks as $t) {
        $altBody .= sprintf("%-38s %5s %9s %10s\n",
            mb_strimwidth($t['topic'], 0, 37, '…'),
            $t['pages'],
            'Ksh ' . number_format((float)$t['cpp'], 2),
            'Ksh ' . number_format((float)$t['total_cost'], 2)
        );
    }
    $altBody .= sprintf("%-50s %10s\n\n", 'Tasks Sub-total', 'Ksh ' . number_format($tasksGrandTotal, 2));
}

if (!empty($bonuses)) {
    $altBody .= "BONUSES\n" . str_repeat('-', 62) . "\n";
    $altBody .= sprintf("%-35s %-14s %12s\n", 'Description', 'Date', 'Amount');
    $altBody .= str_repeat('-', 62) . "\n";
    foreach ($bonuses as $b) {
        $altBody .= sprintf("%-35s %-14s %12s\n",
            mb_strimwidth($b['description'], 0, 34, '…'),
            date('d M Y', strtotime($b['od_date'])),
            'Ksh ' . number_format((float)$b['amount'], 2)
        );
    }
    $altBody .= sprintf("%-48s %12s\n\n", 'Bonuses Sub-total', 'Ksh ' . number_format($bonusTotal, 2));
}

if (!empty($overdrafts)) {
    $altBody .= "OVERDRAFT DEDUCTIONS\n" . str_repeat('-', 62) . "\n";
    $altBody .= sprintf("%-35s %-14s %12s\n", 'Description', 'Date', 'Amount');
    $altBody .= str_repeat('-', 62) . "\n";
    foreach ($overdrafts as $o) {
        $altBody .= sprintf("%-35s %-14s %12s\n",
            mb_strimwidth($o['description'], 0, 34, '…'),
            date('d M Y', strtotime($o['od_date'])),
            '- Ksh ' . number_format((float)$o['amount'], 2)
        );
    }
    $altBody .= sprintf("%-48s %12s\n\n", 'Overdraft Sub-total', '- Ksh ' . number_format($overdraftTotal, 2));
}

$altBody .= str_repeat('=', 62) . "\n";
$altBody .= sprintf("%-48s %12s\n", 'AMOUNT PAYABLE TO YOU', 'Ksh ' . number_format($amountPayable, 2));
$altBody .= str_repeat('=', 62) . "\n\n";
$altBody .= "Thank you! The amount payable will be sent to your mobile banking account shortly and the above tasks will be marked as paid on the website.\n";
$altBody .= "For questions contact: bryo4419@gmail.com\niTasker";

// ── Preview mode — return raw HTML, no email sent ─────────────────────────
if (!empty($_POST['preview_only'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: text/html; charset=UTF-8');
    echo $htmlBody;
    exit;
}

// ── Send via PHPMailer ────────────────────────────────────────────────────
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = env('SMTP_HOST');
    $mail->SMTPAuth   = true;
    $mail->Username   = env('SMTP_USER');
    $mail->Password   = env('SMTP_PASS');
    $mail->SMTPSecure = 'tls';
    $mail->Port       = (int) env('SMTP_PORT', 587);

    $mail->setFrom(env('MAIL_FROM_ADDRESS'), 'iTasker');
    $mail->addAddress($writerEmail, $writerUsername);
    $mail->addBCC(env('ADMIN_EMAIL'), 'iTasker Admin');

    $mail->addCustomHeader('X-Priority', '3');
    $mail->addCustomHeader('X-Mailer', 'iTasker v1.0');
    $mail->addCustomHeader('List-Unsubscribe', '<mailto:support@monkbrian.com>');

    $mail->isHTML(true);
    $mail->Subject = "iTasker Invoice – Payment Summary ({$invoiceDate})";
    $mail->Body    = $htmlBody;
    $mail->AltBody = $altBody;

    $mail->send();

    $taskCount      = count($tasks);
    $bonusCount     = count($bonuses);
    $overdraftCount = count($overdrafts);

    // ── Log the sent invoice to tbl_invoice_logs ─────────────────────────
    $logStmt = mysqli_prepare($con,
        "INSERT INTO tbl_invoice_logs
            (writer_name, writer_email, tasks_total, bonus_total, overdraft_total,
             amount_payable, task_count, bonus_count, overdraft_count, notes, sent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if ($logStmt) {
        $logNotes = '';
        mysqli_stmt_bind_param($logStmt, 'ssddddiiis',
            $writerUsername,
            $writerEmail,
            $tasksGrandTotal,
            $bonusTotal,
            $overdraftTotal,
            $amountPayable,
            $taskCount,
            $bonusCount,
            $overdraftCount,
            $logNotes
        );
        mysqli_stmt_execute($logStmt);
        mysqli_stmt_close($logStmt);
    } else {
        error_log("Invoice log insert failed: " . mysqli_error($con));
    }

    $parts = [];
    if ($taskCount)      $parts[] = "{$taskCount} task" . ($taskCount > 1 ? 's' : '');
    if ($bonusCount)     $parts[] = "{$bonusCount} bonus" . ($bonusCount > 1 ? 'es' : '');
    if ($overdraftCount) $parts[] = "{$overdraftCount} overdraft deduction" . ($overdraftCount > 1 ? 's' : '');
    $summary = implode(', ', $parts);

    sendJsonResponse([
        'success' => true,
        'message' => "Invoice sent to {$writerEmail} ({$summary}). Amount payable: Ksh " . number_format($amountPayable, 2) . "."
    ]);

} catch (Exception $e) {
    error_log("Invoice email failed for {$writerEmail}: {$mail->ErrorInfo}");
    sendJsonResponse([
        'success' => false,
        'message' => "Failed to send invoice email. Mailer error: {$mail->ErrorInfo}"
    ]);
}