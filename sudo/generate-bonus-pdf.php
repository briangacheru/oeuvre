<?php
require_once('tcpdf/tcpdf.php'); // You'll need to install TCPDF library
include_once('dbcon.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input['action'] == 'generate_pdf') {
        $bonusId = intval($input['bonus_id']);

        // Get bonus details
        $bonusQuery = "SELECT mb.*, w.FirstName, w.LastName,
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
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Bonus record not found']);
            exit;
        }

        // Create PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('iTasker');
        $pdf->SetAuthor('iTasker Admin');
        $pdf->SetTitle('Monthly Bonus Report');

        // Add a page
        $pdf->AddPage();

        // Generate PDF content
        $html = generateBonusPDFContent($bonus);
        $pdf->writeHTML($html, true, false, true, false, '');

        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="bonus-report-' . $bonus['writer_email'] . '-' . $bonus['month'] . '-' . $bonus['year'] . '.pdf"');

        $pdf->Output('bonus-report.pdf', 'D');
    }
}

function generateBonusPDFContent($bonus) {
    $monthName = date('F', mktime(0, 0, 0, $bonus['month'], 1));
    $writerName = trim(($bonus['FirstName'] ?? '') . ' ' . ($bonus['LastName'] ?? ''));

    return "
    <h1 style='text-align: center; color: #667eea;'>Monthly Bonus Report</h1>
    <h2 style='text-align: center;'>$monthName {$bonus['year']}</h2>
    
    <h3>Writer Information</h3>
    <table border='1' cellpadding='5'>
        <tr><td><strong>Name:</strong></td><td>$writerName</td></tr>
        <tr><td><strong>Email:</strong></td><td>{$bonus['writer_email']}</td></tr>
        <tr><td><strong>Period:</strong></td><td>$monthName {$bonus['year']}</td></tr>
    </table>
    
    <h3>Performance Summary</h3>
    <table border='1' cellpadding='5'>
        <tr><td><strong>Total Tasks:</strong></td><td>{$bonus['total_tasks_completed']}</td></tr>
        <tr><td><strong>Early Completions:</strong></td><td>{$bonus['tasks_completed_early']} (Ksh. " . number_format($bonus['early_earnings'], 2) . ")</td></tr>
        <tr><td><strong>On-Time Completions:</strong></td><td>{$bonus['tasks_completed_on_time']} (Ksh. " . number_format($bonus['on_time_earnings'], 2) . ")</td></tr>
        <tr><td><strong>Total Earnings:</strong></td><td>Ksh. " . number_format($bonus['total_earnings'], 2) . "</td></tr>
    </table>
    
    <h3>Bonus Breakdown</h3>
    <table border='1' cellpadding='5'>
        <tr><td>Base Bonus ({$bonus['base_percentage']}%):</td><td>Ksh. " . number_format($bonus['base_bonus_amount'], 2) . "</td></tr>
        <tr><td>Early Completion Bonus ({$bonus['early_percentage']}%):</td><td>Ksh. " . number_format($bonus['early_completion_bonus'], 2) . "</td></tr>
        <tr><td>Perfect Month Bonus:</td><td>Ksh. " . number_format($bonus['perfect_month_bonus'], 2) . "</td></tr>
        <tr style='background-color: #d4edda;'><td><strong>Total Bonus ({$bonus['bonus_percentage']}%):</strong></td><td><strong>Ksh. " . number_format($bonus['total_bonus_amount'], 2) . "</strong></td></tr>
    </table>
    ";
}
?>