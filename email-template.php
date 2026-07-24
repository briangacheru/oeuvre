<?php
/**
 * Shared HTML email wrapper used by both interfaces (writer + admin) so
 * every outgoing email shares one consistent look instead of each file
 * hand-rolling its own <html><style> block from scratch (which is what
 * every email in this app did before - see register.php/sudo/register.php's
 * plain-text "Thank you for Signing Up" body, or login-helpers.php's
 * one-off inline-styled OTP email).
 *
 * Include from root files with require_once __DIR__.'/email-template.php',
 * from sudo/ files with require_once __DIR__.'/../email-template.php'.
 */
if (!function_exists('render_email_html')) {
    /**
     * @param string      $title   Shown as the small preheader-style label above the body.
     * @param string      $bodyHtml Raw HTML for the message body - headings/paragraphs/tables.
     * @param string|null $ctaText  Button label, e.g. "Log In". Omit for no button.
     * @param string|null $ctaUrl   Button destination. Required if $ctaText is set.
     */
    function render_email_html($title, $bodyHtml, $ctaText = null, $ctaUrl = null) {
        $logo = 'https://web.monkbrian.com/assets/img/team/itasker-email-header.png';
        $year = date('Y');

        $cta = '';
        if ($ctaText && $ctaUrl) {
            $cta = '<tr><td align="center" style="padding:8px 32px 28px;">
                <a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank"
                   style="background:#18163a;color:#ffffff;text-decoration:none;padding:12px 30px;border-radius:4px;font-weight:600;font-size:14px;display:inline-block;">'
                   . htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8') . '</a>
            </td></tr>';
        }

        return '<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f5f7fa;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fa;padding:32px 0;">
<tr><td align="center">
<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);max-width:480px;">
<tr><td align="center" style="background:#18163a;padding:22px;">
<img src="' . $logo . '" alt="iTasker" style="max-width:150px;height:auto;display:block;">
</td></tr>
<tr><td style="padding:28px 32px 4px;">
<p style="margin:0 0 12px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#2c7be5;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>
<div style="font-size:15px;line-height:1.6;color:#333333;">' . $bodyHtml . '</div>
</td></tr>
' . $cta . '
<tr><td style="padding:20px 32px 28px;color:#9aa0ac;font-size:12px;text-align:center;border-top:1px solid #eef0f3;">
&copy; ' . $year . ' iTasker &bull; This is an automated message, please do not reply.
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
    }
}
