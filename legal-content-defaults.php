<?php
/**
 * Built-in fallback body content for terms.php / privacy.php, used when
 * tbl_legal_pages has no admin-saved override yet (row missing, migration
 * not run, or content column still NULL). Also used by
 * sudo/legal-pages.php to seed the editor the first time an admin opens it,
 * so they're editing real content instead of a blank box.
 *
 * Include with require_once __DIR__.'/legal-content-defaults.php' from root
 * files, require_once __DIR__.'/../legal-content-defaults.php' from sudo/.
 */
if (!function_exists('default_terms_content')) {
    function default_terms_content() {
        return <<<'HTML'
<p>These Terms of Service ("Terms") govern your access to and use of itasker (the "Platform"), operated by Monk Freelancing. By registering for an account or otherwise using the Platform, you agree to be bound by these Terms. If you do not agree, please do not use the Platform.</p>

<h5 class="mt-4">1. Who Can Use itasker</h5>
<p>You must be at least 18 years old and able to form a binding contract to register as a writer on itasker. Registration may be limited to invited or approved applicants at our discretion, and we may open or close registration at any time.</p>

<h5 class="mt-4">2. Your Account</h5>
<p>You're responsible for maintaining the confidentiality of your username and password, and for all activity that happens under your account. Notify us immediately at <a href="mailto:support@monkbrian.com">support@monkbrian.com</a> if you suspect unauthorized access. We may use email- or device-based verification (such as a one-time code) to confirm sign-ins from new devices.</p>

<h5 class="mt-4">3. Tasks and Submissions</h5>
<p>Tasks assigned to you through the Platform come with a topic, subject, due date, page count, and per-page rate. By accepting a task, you agree to complete and submit original work by the stated due date. Work you submit must be your own, must not infringe the rights of any third party, and must not be resold, reused, or resubmitted elsewhere once assigned to you through itasker.</p>

<h5 class="mt-4">4. Payments</h5>
<p>Compensation for completed and confirmed tasks is calculated from the page count and per-page rate shown on the task, less any applicable deductions (such as outstanding overdrafts). Payments are disbursed to the mobile banking or payment details you provide. You're responsible for making sure those details are accurate and up to date - itasker isn't responsible for payments sent to incorrect details you supplied.</p>

<h5 class="mt-4">5. Acceptable Use</h5>
<p>You agree not to: misrepresent your identity or qualifications; submit plagiarized, AI-generated work where prohibited by task instructions, or otherwise dishonest work; attempt to access another user's account or data; interfere with or disrupt the Platform's normal operation; or use the Platform for any unlawful purpose.</p>

<h5 class="mt-4">6. Suspension and Termination</h5>
<p>We may suspend, deactivate, or terminate your account if you violate these Terms, if your account is inactive for an extended period, or at our reasonable discretion. You may also request that your account be closed at any time by contacting us. Sections of these Terms that by their nature should survive termination (such as payment obligations for already-completed work) will continue to apply.</p>

<h5 class="mt-4">7. Intellectual Property</h5>
<p>The itasker name, branding, and underlying software belong to Monk Freelancing. Work product you submit for a task becomes the property of the client/account the task was created for, subject to your having been compensated for it under these Terms.</p>

<h5 class="mt-4">8. Disclaimers and Limitation of Liability</h5>
<p>The Platform is provided "as is" without warranties of any kind. To the fullest extent permitted by law, Monk Freelancing is not liable for indirect, incidental, or consequential damages arising from your use of the Platform, and our total liability for any claim will not exceed the amount paid to you through the Platform in the three months preceding the claim.</p>

<h5 class="mt-4">9. Changes to These Terms</h5>
<p>We may update these Terms from time to time. Material changes will be reflected by updating the "Last updated" date above. Continuing to use the Platform after changes take effect means you accept the revised Terms.</p>

<h5 class="mt-4">10. Contact</h5>
<p>Questions about these Terms can be sent to <a href="mailto:support@monkbrian.com">support@monkbrian.com</a> or +254 710 301 320.</p>
HTML;
    }
}

if (!function_exists('get_legal_page')) {
    /**
     * Fetches an admin-saved override for 'terms' or 'privacy' from
     * tbl_legal_pages, falling back to the built-in default content above
     * when there's no override yet (empty content) or the table/column
     * doesn't exist yet (migration not run - mysqli_query then returns
     * false, handled the same as "no override").
     *
     * @return array{content: string, updated_at: ?string, is_custom: bool}
     */
    function get_legal_page($con, $pageKey) {
        $defaults = ['terms' => 'default_terms_content', 'privacy' => 'default_privacy_content'];
        $defaultFn = $defaults[$pageKey] ?? null;

        $stmt = mysqli_prepare($con, "SELECT content, updated_at FROM tbl_legal_pages WHERE page_key = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $pageKey);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if ($row && trim((string) $row['content']) !== '') {
                return ['content' => $row['content'], 'updated_at' => $row['updated_at'], 'is_custom' => true];
            }
        }

        return ['content' => $defaultFn ? $defaultFn() : '', 'updated_at' => null, 'is_custom' => false];
    }
}

if (!function_exists('default_privacy_content')) {
    function default_privacy_content() {
        return <<<'HTML'
<p>This Privacy Policy explains what information itasker (the "Platform"), operated by Monk Freelancing, collects, how we use it, and the choices you have. By using the Platform you agree to the collection and use of information as described here.</p>

<h5 class="mt-4">1. Information We Collect</h5>
<p><strong>Account information:</strong> username, email address, and a securely hashed password (we never store your password in plain text). If you sign in with Google, we receive your Google account's name and email address.</p>
<p><strong>Task and work information:</strong> the tasks assigned to you and details you submit against them, including any files you upload and comments you leave.</p>
<p><strong>Payment information:</strong> the mobile banking or payment details you provide so we can pay you for completed work.</p>
<p><strong>Device and usage information:</strong> IP address, browser/device details, and session activity - used for security purposes such as detecting sign-ins from a new device and rate-limiting abusive requests.</p>
<p><strong>Communications:</strong> emails we send you (task notifications, security codes, password resets) and any messages you send us.</p>

<h5 class="mt-4">2. How We Use Your Information</h5>
<p>We use the information above to: create and maintain your account; assign, track, and pay you for tasks; send task-related and account-related notifications; verify your identity and protect your account (including one-time codes for new-device sign-ins); respond to support requests; and maintain the security and reliability of the Platform.</p>

<h5 class="mt-4">3. Cookies and Sessions</h5>
<p>We use session cookies to keep you signed in and to remember devices you've previously used to sign in. We don't use these for advertising or cross-site tracking.</p>

<h5 class="mt-4">4. Email Communications</h5>
<p>We send transactional emails related to your account and tasks - for example, task assignments and updates, sign-in security codes, and password reset links. These are necessary for the Platform to function and can't be opted out of while your account is active. Every automated email includes a way to contact us if you believe you're receiving it in error.</p>

<h5 class="mt-4">5. How We Share Your Information</h5>
<p>We do not sell your personal information. We share it only: with our email delivery provider, solely to send you the communications described above; with payment channels, solely to disburse payments you're owed; if Google Sign-In is enabled and you choose to use it, with Google, as part of that authentication flow; or where required by law.</p>

<h5 class="mt-4">6. Data Security</h5>
<p>Passwords are hashed, not stored in plain text. Password reset and sign-in verification links/codes are single-use and time-limited. We restrict access to your data to what's needed to operate the Platform. No system is perfectly secure, but we take reasonable steps to protect your information from unauthorized access.</p>

<h5 class="mt-4">7. Data Retention</h5>
<p>We retain your account and task information for as long as your account is active, and for a reasonable period afterward for record-keeping, dispute resolution, and legal compliance (for example, payment records). You can request deletion of your account by contacting us - see Section 9.</p>

<h5 class="mt-4">8. Your Rights and Choices</h5>
<p>You can review and update your account details from within the Platform. You may request a copy of the personal information we hold about you, ask us to correct inaccurate information, or ask us to delete your account and associated data (subject to the retention needs described above) by emailing us.</p>

<h5 class="mt-4">9. Contact</h5>
<p>Questions about this Privacy Policy, or requests regarding your personal information, can be sent to <a href="mailto:support@monkbrian.com">support@monkbrian.com</a> or +254 710 301 320.</p>

<h5 class="mt-4">10. Changes to This Policy</h5>
<p>We may update this Privacy Policy from time to time. Material changes will be reflected by updating the "Last updated" date above. Continuing to use the Platform after changes take effect means you accept the revised policy.</p>
HTML;
    }
}
