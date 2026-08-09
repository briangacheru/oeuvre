<?php
/**
 * Shared HTML email wrapper used by every outgoing email (both interfaces)
 * so nothing hand-rolls its own <html><style> block. Visual design matches
 * the task-assignment email from sudo/submit-task.php, which is the
 * reference style for all iTasker emails.
 *
 * Include from root files with require_once __DIR__.'/email-template.php',
 * from sudo/ files with require_once __DIR__.'/../email-template.php'.
 */
if (!function_exists('render_email_html')) {
    /**
     * @param string      $title      H2 heading shown at the top of the card.
     * @param string      $bodyHtml   Raw HTML for the message body - paragraphs, <span class="highlight">, tables, etc.
     * @param string|null $ctaText    Button label, e.g. "View Task Details". Omit for no button.
     * @param string|null $ctaUrl     Button destination. Required if $ctaText is set.
     * @param string|null $footerNote Extra line shown above the copyright, e.g. "For any questions, contact <a href='mailto:...'>...</a>".
     */
    function render_email_html($title, $bodyHtml, $ctaText = null, $ctaUrl = null, $footerNote = null) {
        $logo = 'https://web.monkbrian.com/assets/img/team/itasker-email-header.png';
        $year = date('Y');

        $cta = '';
        if ($ctaText && $ctaUrl) {
            $cta = '<a class="btn" href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8') . '</a>';
        }

        $footer = $footerNote ? '<p>' . $footerNote . '</p>' : '';

        return "
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset='utf-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .email-container {
            max-width: 600px;
            background: #ffffff;
            margin: 0 auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            text-align: center;
            border-bottom: 2px solid #0073e6;
            padding-bottom: 15px;
        }
        .email-header img {
            max-width: 100%;
            height: auto;
            max-height: 100px;
        }
        .email-content {
            padding: 20px;
        }
        .email-content h2 {
            color: #0073e6;
            text-align: center;
        }
        .email-content p {
            font-size: 16px;
            line-height: 1.5;
            color: #333;
        }
        .highlight {
            font-weight: bold;
            color: #0073e6;
        }
        .btn {
            display: block;
            text-align: center;
            background: #0073e6;
            color: #ffff;
            padding: 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            margin-top: 20px;
            transition: background 0.3s ease-in-out, color 0.3s ease-in-out;
        }
        .btn:hover {
            background: #005bb5;
            color: #ffff !important;
        }
        .footer {
            text-align: center;
            padding-top: 15px;
            font-size: 12px;
            color: #777;
        }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; text-align: center; }
        .stat-number { font-size: 24px; font-weight: 700; color: #0073e6; }
        .stat-label { font-size: 12px; color: #6c757d; text-transform: uppercase; }
        .reminder-card { background: #f8f9fa; border-left: 4px solid #0073e6; padding: 20px; margin: 15px 0; border-radius: 8px; text-align: left; }
        .reminder-card.high { border-left-color: #dc3545; }
        .reminder-card.medium { border-left-color: #ffc107; }
        .reminder-card.low { border-left-color: #28a745; }
        .reminder-card.overdue { border-left-color: #dc3545; background: #fff5f5; }
        .reminder-title { font-size: 18px; font-weight: 600; margin-bottom: 8px; color: #2c3e50; }
        .reminder-meta { font-size: 14px; color: #6c757d; margin-bottom: 10px; }
        .reminder-description { font-size: 15px; color: #495057; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-overdue { background: #fee; color: #dc3545; }
        .status-due-today { background: #fff3cd; color: #856404; }
        .status-upcoming { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        </style>
        </head>
        <body>
        <div class='email-container'>
            <div class='email-header'>
                <img src='{$logo}' alt='itasker logo'>
            </div>
            <div class='email-content'>
                <h2>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h2>
                {$bodyHtml}
                {$cta}
            </div>
            <div class='footer'>
                {$footer}
                <p>&copy; {$year} iTasker. All rights reserved.</p>
            </div>
        </div>
        </body>
        </html>";
    }
}
