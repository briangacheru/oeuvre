<?php
require_once __DIR__ . '/../env.php';
header('Content-Type: application/json');
// Previously bootstrapped with dbcon.php alone - no session, no login
// check. Any unauthenticated POST could trigger a real bonus-notification
// email to an arbitrary address (writer_email/writer_name come straight
// from the request body), abusing the site's SMTP credentials. Switched
// to check-login.php (includes dbcon.php) + a capability check.
include_once('check-login.php');
requireCapability($currentAdminRole, 'operate_finance', 'json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer autoloader
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../email-template.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input['action'] == 'send_bonus_email') {
        $bonusId = intval($input['bonus_id']);
        $writerEmail = $input['writer_email'];
        $writerName = $input['writer_name'];
        $month = intval($input['month']);
        $year = intval($input['year']);

        // Get bonus details from database with settings
        $bonusQuery = "SELECT mb.*, w.FirstName, w.LastName, w.username,
                              bs1.setting_value as base_percentage, 
                              bs2.setting_value as early_percentage, 
                              bs3.setting_value as perfect_percentage
                       FROM tbl_monthly_bonuses mb
                       LEFT JOIN tblwriters w ON mb.writer_id = w.id
                       LEFT JOIN tbl_bonus_settings bs1 ON bs1.setting_name = 'base_bonus_percentage' AND bs1.is_active = 1
                       LEFT JOIN tbl_bonus_settings bs2 ON bs2.setting_name = 'early_completion_bonus' AND bs2.is_active = 1  
                       LEFT JOIN tbl_bonus_settings bs3 ON bs3.setting_name = 'perfect_month_bonus' AND bs3.is_active = 1
                       WHERE mb.id = ?";
        $stmt = $con->prepare($bonusQuery);
        $stmt->bind_param("i", $bonusId);
        $stmt->execute();
        $bonus = $stmt->get_result()->fetch_assoc();

        if (!$bonus) {
            echo json_encode(['success' => false, 'message' => 'Bonus record not found']);
            exit;
        }

        // Use writer name from database if available, fallback to input
        $writerUsername = $bonus['username'];

        // Send email using PHPMailer
        $mail = new PHPMailer(true);
        $emailSent = false;

        try {
            // Server settings with better connection handling
            $mail->isSMTP();
            $mail->Host       = env('SMTP_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('SMTP_USER');
            $mail->Password   = env('SMTP_PASS');
            $mail->SMTPSecure = 'tls';
            $mail->Port       = (int) env('SMTP_PORT', 587);

            $mail->setFrom(env('MAIL_FROM_ADDRESS'), 'itasker');
            $mail->addReplyTo(env('ADMIN_EMAIL'), 'Bryo Gacheru');
            $mail->addAddress($writerEmail); // Writer's email
            $mail->addAddress(env('ADMIN_EMAIL'), 'itasker Admin');

            // Add important headers to improve deliverability
            $mail->MessageID = '<' . md5(uniqid(time())) . '@monkbrian.com>';
            $mail->addCustomHeader('List-Unsubscribe', '<mailto:support@monkbrian.com?subject=Unsubscribe>');
            $mail->addCustomHeader('X-Mailer', 'PHP/' . phpversion());
            $mail->addCustomHeader('X-Priority', '3'); // Normal priority
            $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
            $mail->addCustomHeader('Importance', 'Normal');

            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            $monthName = date('F', mktime(0, 0, 0, $month, 1));

            $mail->Subject = "Monthly Performance Bonus Report - $monthName $year - iTasker";

            // Format numbers for display
            $totalEarnings = number_format($bonus['total_earnings'], 2);
            $earlyEarnings = number_format($bonus['early_earnings'] ?? 0, 2);
            $onTimeEarnings = number_format($bonus['on_time_earnings'] ?? 0, 2);
            $lateEarnings = number_format($bonus['late_earnings'] ?? 0, 2);
            $baseBonusAmount = number_format($bonus['base_bonus_amount'], 2);
            $earlyBonusAmount = number_format($bonus['early_completion_bonus'], 2);
            $perfectBonusAmount = number_format($bonus['perfect_month_bonus'], 2);
            $totalBonusAmount = number_format($bonus['total_bonus_amount'], 2);

            $tableStyle = "width:100%;border-collapse:collapse;margin:25px 0;background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);";
            $thStyle = "background:#0073e6;color:white;padding:15px;text-align:left;font-weight:bold;";
            $tdStyle = "padding:12px 15px;border-bottom:1px solid #eee;color:#333;";
            $amountStyle = "$tdStyle text-align:right;font-weight:bold;";
            $totalRowStyle = "background:#d4edda;font-weight:bold;color:#155724;";

            $emailBody = "
                    <p style='text-align:center;color:#666;'>$monthName $year Performance Summary</p>
                    <p>Hello <span class='highlight'>$writerUsername</span>,</p>
                    <p>We're excited to share your performance bonus report for <strong>$monthName $year</strong>. Your dedication and quality work continue to impress us!</p>

                    <div style='text-align:center;margin:20px 0;padding:15px;background:#f8f9fa;border-radius:8px;border-left:4px solid #28a745;'>
                        <h3 style='margin: 0; color: #28a745;'>Monthly Achievement</h3>
                        <p style='margin: 5px 0 0 0; font-size: 18px;'><strong>{$bonus['tasks_completed_on_time']}</strong> on-time + <strong>{$bonus['tasks_completed_early']}</strong> early out of <strong>{$bonus['total_tasks_completed']}</strong> total tasks</p>
                    </div>

                    <div style='display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:25px 0;'>
                        <div style='background:#f8f9fa;padding:20px;border-radius:8px;text-align:center;border-left:4px solid #0073e6;'>
                            <h3 style='margin:0 0 10px 0;color:#0073e6;font-size:18px;'>Tasks Completed</h3>
                            <div style='font-size:24px;font-weight:bold;color:#333;margin:5px 0;'>{$bonus['total_tasks_completed']}</div>
                            <div style='font-size:14px;color:#666;'>This Month</div>
                        </div>
                        <div style='background:#f8f9fa;padding:20px;border-radius:8px;text-align:center;border-left:4px solid #0073e6;'>
                            <h3 style='margin:0 0 10px 0;color:#0073e6;font-size:18px;'>Total Earnings</h3>
                            <div style='font-size:24px;font-weight:bold;color:#333;margin:5px 0;'>Ksh. $totalEarnings</div>
                            <div style='font-size:14px;color:#666;'>Before Bonus</div>
                        </div>
                    </div>

                    <h3 style='color: #0073e6; margin-top: 30px;'>Earnings Breakdown</h3>
                    <table style='$tableStyle'>
                        <tr>
                            <th style='$thStyle'>Category</th>
                            <th style='$thStyle'>Tasks</th>
                            <th style='$thStyle'>Earnings</th>
                        </tr>
                        <tr>
                            <td style='$tdStyle'>Early Submissions</td>
                            <td style='$tdStyle'>{$bonus['tasks_completed_early']}</td>
                            <td style='$amountStyle'>Ksh. $earlyEarnings</td>
                        </tr>
                        <tr>
                            <td style='$tdStyle'>On-Time Submissions</td>
                            <td style='$tdStyle'>{$bonus['tasks_completed_on_time']}</td>
                            <td style='$amountStyle'>Ksh. $onTimeEarnings</td>
                        </tr>";

            if ($bonus['tasks_completed_late'] > 0) {
                $emailBody .= "
                        <tr>
                            <td style='$tdStyle'>Late Submissions</td>
                            <td style='$tdStyle'>{$bonus['tasks_completed_late']}</td>
                            <td style='$amountStyle'>Ksh. $lateEarnings</td>
                        </tr>";
            }

            $emailBody .= "
                        <tr style='$totalRowStyle'>
                            <td style='$tdStyle'><strong>Total Earnings</strong></td>
                            <td style='$tdStyle'><strong>{$bonus['total_tasks_completed']}</strong></td>
                            <td style='$amountStyle'><strong>Ksh. $totalEarnings</strong></td>
                        </tr>
                    </table>

                    <h3 style='color: #0073e6; margin-top: 30px;'>Bonus Calculation</h3>
                    <table style='$tableStyle'>
                        <tr>
                            <th style='$thStyle'>Bonus Type</th>
                            <th style='$thStyle'>Rate</th>
                            <th style='$thStyle'>Amount</th>
                        </tr>
                        <tr>
                            <td style='$tdStyle'>Base Performance Bonus</td>
                            <td style='$tdStyle'>{$bonus['base_percentage']}% of total earnings</td>
                            <td style='$amountStyle'>Ksh. $baseBonusAmount</td>
                        </tr>
                        <tr>
                            <td style='$tdStyle'>Early Completion Bonus</td>
                            <td style='$tdStyle'>{$bonus['early_percentage']}% of early submissions</td>
                            <td style='$amountStyle'>Ksh. $earlyBonusAmount</td>
                        </tr>
                        <tr>
                            <td style='$tdStyle'>Perfect Month Bonus</td>
                            <td style='$tdStyle'>" . ($bonus['perfect_month_bonus'] > 0 ? "{$bonus['perfect_percentage']}% (no late tasks)" : "0% (had late tasks)") . "</td>
                            <td style='$amountStyle'>Ksh. $perfectBonusAmount</td>
                        </tr>
                        <tr style='$totalRowStyle'>
                            <td style='$tdStyle'><strong>Total Bonus ({$bonus['bonus_percentage']}%)</strong></td>
                            <td style='$tdStyle'></td>
                            <td style='$amountStyle'><strong>Ksh. $totalBonusAmount</strong></td>
                        </tr>
                    </table>

                    <p style='text-align: center; font-size: 18px; color: #28a745; font-weight: bold; margin-top: 25px;'>
                        Your bonus payment will be processed shortly!
                    </p>

                    <p>Thank you for your excellent work and commitment to quality. Your performance this month demonstrates why you're such a valued member of our team!</p>";

            $mail->Body = render_email_html(
                'Monthly Bonus Report',
                $emailBody,
                'View Your Dashboard',
                'https://web.monkbrian.com/index',
                "Questions about your bonus? Contact us at <a href='mailto:bryo4419@gmail.com'>bryo4419@gmail.com</a>"
            );

            // Alt body for non-HTML email clients
            $mail->AltBody = "Monthly Performance Bonus Report - $monthName $year\n\n
            Hello $writerDisplayName,\n\n
            We're excited to share your performance bonus report for $monthName $year.\n\n
            PERFORMANCE SUMMARY:\n
            - Total Tasks: {$bonus['total_tasks_completed']}\n
            - Early Completions: {$bonus['tasks_completed_early']} (Ksh. $earlyEarnings)\n
            - On-Time Completions: {$bonus['tasks_completed_on_time']} (Ksh. $onTimeEarnings)\n" .
                ($bonus['tasks_completed_late'] > 0 ? "- Late Completions: {$bonus['tasks_completed_late']} (Ksh. $lateEarnings)\n" : "") . "
            - Total Earnings: Ksh. $totalEarnings\n\n
            BONUS BREAKDOWN:\n
            - Base Bonus ({$bonus['base_percentage']}%): Ksh. $baseBonusAmount\n
            - Early Completion Bonus ({$bonus['early_percentage']}%): Ksh. $earlyBonusAmount\n
            - Perfect Month Bonus: Ksh. $perfectBonusAmount\n
            - TOTAL BONUS: Ksh. $totalBonusAmount ({$bonus['bonus_percentage']}%)\n\n
            Thank you for your excellent work!\n\n
            Questions? Contact: bryo4419@gmail.com\n
            © " . date('Y') . " iTasker. All rights reserved.";

            $mail->send();
            $emailSent = true;

        } catch (Exception $e) {
            error_log("Bonus email could not be sent. Mailer Error: {$mail->ErrorInfo}");
            echo json_encode(['success' => false, 'message' => 'Failed to send email: ' . $mail->ErrorInfo]);
            exit;
        }

        if ($emailSent) {
            // Log the email send in the database
            $logQuery = "UPDATE tbl_monthly_bonuses SET 
                        notes = CONCAT(COALESCE(notes, ''), '\nEmail sent on ', NOW(), ' to ', ?),
                        updated_at = NOW()
                        WHERE id = ?";
            $logStmt = $con->prepare($logQuery);
            $logStmt->bind_param("si", $writerEmail, $bonusId);
            $logStmt->execute();
            $logStmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Bonus report emailed successfully to ' . $writerUsername
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send email']);
        }
    }
}
?>