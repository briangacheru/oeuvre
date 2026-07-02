<?php
require_once __DIR__ . '/../env.php';
header('Content-Type: application/json');
include_once('dbcon.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

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
            // Email Body with same styling as task email
            $companyLogo = 'https://web.monkbrian.com/assets/img/team/itasker-email-header2.png';

            // Format numbers for display
            $totalEarnings = number_format($bonus['total_earnings'], 2);
            $earlyEarnings = number_format($bonus['early_earnings'] ?? 0, 2);
            $onTimeEarnings = number_format($bonus['on_time_earnings'] ?? 0, 2);
            $lateEarnings = number_format($bonus['late_earnings'] ?? 0, 2);
            $baseBonusAmount = number_format($bonus['base_bonus_amount'], 2);
            $earlyBonusAmount = number_format($bonus['early_completion_bonus'], 2);
            $perfectBonusAmount = number_format($bonus['perfect_month_bonus'], 2);
            $totalBonusAmount = number_format($bonus['total_bonus_amount'], 2);

            // Simplified HTML with better text-to-image ratio and no emojis
            $mail->Body = "
<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                padding: 20px;
                margin: 0;
            }
            .email-container {
                max-width: 650px;
                background: #ffffff;
                margin: 0 auto;
                border-radius: 8px;
                box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
            .email-head {
                text-align: center;
                background: white;
            }
            .email-head img {
                max-width: 650px;
                height: auto;
            }
           
            .email-content {
                padding: 30px;
            }
            .email-content h2 {
                color: #0073e6;
                text-align: center;
                margin-bottom: 10px;
                font-size: 22px;
            }
            .email-content p {
                font-size: 16px;
                line-height: 1.6;
                color: #333;
                margin-bottom: 15px;
            }
            .highlight {
                font-weight: bold;
                color: #0073e6;
            }
            .performance-badge {
                text-align: center;
                margin: 20px 0;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #28a745;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin: 25px 0;
            }
            .stat-card {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
                border-left: 4px solid #0073e6;
            }
            .stat-card h3 {
                margin: 0 0 10px 0;
                color: #0073e6;
                font-size: 18px;
            }
            .stat-card .big-number {
                font-size: 24px;
                font-weight: bold;
                color: #333;
                margin: 5px 0;
            }
            .stat-card .small-text {
                font-size: 14px;
                color: #666;
            }
            .bonus-table {
                width: 100%;
                border-collapse: collapse;
                margin: 25px 0;
                background: white;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .bonus-table th {
                background: #0073e6;
                color: white;
                padding: 15px;
                text-align: left;
                font-weight: bold;
            }
            .bonus-table td {
                padding: 12px 15px;
                border-bottom: 1px solid #eee;
                color: #333;
            }
            .bonus-table tr:nth-child(even) {
                background: #f8f9fa;
            }
            .bonus-table .total-row {
                background: #d4edda !important;
                font-weight: bold;
                color: #155724;
            }
            .bonus-table .amount {
                text-align: right;
                font-weight: bold;
            }
            .btn {
                display: block;
                text-align: center;
                background: #0073e6;
                color: #ffffff;
                padding: 15px 25px;
                border-radius: 8px;
                text-decoration: none;
                font-size: 16px;
                font-weight: bold;
                margin: 25px auto;
                max-width: 250px;
                transition: background 0.3s ease;
            }
            .btn:hover {
                background: #5a6fd8;
                color: #ffffff !important;
            }
            .footer {
                text-align: center;
                padding: 25px 20px;
                background: #f8f9fa;
                border-top: 1px solid #eee;
                font-size: 14px;
                color: #666;
            }
            .footer a {
                color: #0073e6;
                text-decoration: none;
            }
            @media (max-width: 600px) {
                .stats-grid {
                    grid-template-columns: 1fr;
                }
                .email-content {
                    padding: 20px;
                }
            }
            </style>
            </head>
            <body>
            <div class='email-container'>
                <div class='email-head'>
                    <img src='{$companyLogo}' alt='iTasker logo'>
                    <h1>Monthly Bonus Report</h1>
                    <p>$monthName $year Performance Summary</p>
                </div>
                
                <div class='email-content'>
                    <p>Hello <span class='highlight'>$writerUsername</span>,</p>
                    <p>We're excited to share your performance bonus report for <strong>$monthName $year</strong>. Your dedication and quality work continue to impress us!</p>
                    
                    <div class='performance-badge'>
                        <h3 style='margin: 0; color: #28a745;'>Monthly Achievement</h3>
                        <p style='margin: 5px 0 0 0; font-size: 18px;'><strong>{$bonus['tasks_completed_on_time']}</strong> on-time + <strong>{$bonus['tasks_completed_early']}</strong> early out of <strong>{$bonus['total_tasks_completed']}</strong> total tasks</p>
                    </div>
                    
                    <div class='stats-grid'>
                        <div class='stat-card'>
                            <h3>Tasks Completed</h3>
                            <div class='big-number'>{$bonus['total_tasks_completed']}</div>
                            <div class='small-text'>This Month</div>
                        </div>
                        <div class='stat-card'>
                            <h3>Total Earnings</h3>
                            <div class='big-number'>Ksh. $totalEarnings</div>
                            <div class='small-text'>Before Bonus</div>
                        </div>
                    </div>
                    
                    <h3 style='color: #667eea; margin-top: 30px;'>Earnings Breakdown</h3>
                    <table class='bonus-table'>
                        <tr>
                            <th>Category</th>
                            <th>Tasks</th>
                            <th>Earnings</th>
                        </tr>
                        <tr>
                            <td>Early Submissions</td>
                            <td>{$bonus['tasks_completed_early']}</td>
                            <td class='amount'>Ksh. $earlyEarnings</td>
                        </tr>
                        <tr>
                            <td>On-Time Submissions</td>
                            <td>{$bonus['tasks_completed_on_time']}</td>
                            <td class='amount'>Ksh. $onTimeEarnings</td>
                        </tr>";

            if ($bonus['tasks_completed_late'] > 0) {
                $mail->Body .= "
                        <tr>
                            <td>Late Submissions</td>
                            <td>{$bonus['tasks_completed_late']}</td>
                            <td class='amount'>Ksh. $lateEarnings</td>
                        </tr>";
            }

            $mail->Body .= "
                        <tr class='total-row'>
                            <td><strong>Total Earnings</strong></td>
                            <td><strong>{$bonus['total_tasks_completed']}</strong></td>
                            <td class='amount'><strong>Ksh. $totalEarnings</strong></td>
                        </tr>
                    </table>
                    
                    <h3 style='color: #667eea; margin-top: 30px;'>Bonus Calculation</h3>
                    <table class='bonus-table'>
                        <tr>
                            <th>Bonus Type</th>
                            <th>Rate</th>
                            <th>Amount</th>
                        </tr>
                        <tr>
                            <td>Base Performance Bonus</td>
                            <td>{$bonus['base_percentage']}% of total earnings</td>
                            <td class='amount'>Ksh. $baseBonusAmount</td>
                        </tr>
                        <tr>
                            <td>Early Completion Bonus</td>
                            <td>{$bonus['early_percentage']}% of early submissions</td>
                            <td class='amount'>Ksh. $earlyBonusAmount</td>
                        </tr>
                        <tr>
                            <td>Perfect Month Bonus</td>
                            <td>" . ($bonus['perfect_month_bonus'] > 0 ? "{$bonus['perfect_percentage']}% (no late tasks)" : "0% (had late tasks)") . "</td>
                            <td class='amount'>Ksh. $perfectBonusAmount</td>
                        </tr>
                        <tr class='total-row'>
                            <td><strong>Total Bonus ({$bonus['bonus_percentage']}%)</strong></td>
                            <td><strong></strong></td>
                            <td class='amount'><strong>Ksh. $totalBonusAmount</strong></td>
                        </tr>
                    </table>
                    
                    <p style='text-align: center; font-size: 18px; color: #28a745; font-weight: bold; margin-top: 25px;'>
                        Your bonus payment will be processed shortly!
                    </p>
                    
                    <p>Thank you for your excellent work and commitment to quality. Your performance this month demonstrates why you're such a valued member of our team!</p>
                    
                    <a class='btn' href='https://web.monkbrian.com/index'>View Your Dashboard</a>
                </div>
                
                <div class='footer'>
                    <p>Questions about your bonus? Contact us at <a href='mailto:bryo4419@gmail.com'>bryo4419@gmail.com</a></p>
                    <p>&copy; " . date('Y') . " iTasker. All rights reserved.</p>
                </div>
            </div>
            </body>
            </html>";

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