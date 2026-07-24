<?php
// ══════════════════════════════════════════════════════════════════
//  permissions.php
//  Central role/capability helper for the admin (sudo) interface.
//  Replaces the old inline `AdminName == 'Admin'` / `superadmin == 1`
//  checks that were duplicated across ~10 files with no shared helper -
//  see db-migrations/2026_07_24_add_admin_roles.sql for the schema change
//  this builds on.
//
//  Roles are strictly ordered pending < support == finance < admin < superadmin
//  in terms of trust, but capabilities are NOT purely additive between
//  support/finance - each covers a different half of the app (task/writer
//  ops vs financial ops); admin gets both, superadmin gets everything.
// ══════════════════════════════════════════════════════════════════

if (!defined('ADMIN_ROLES')) {
    define('ADMIN_ROLES', ['pending', 'support', 'finance', 'admin', 'superadmin']);
}

if (!defined('ADMIN_CAPABILITIES')) {
    define('ADMIN_CAPABILITIES', [
        // Task/writer operations: create/edit/manage tasks, chat, projects,
        // calendar, todo, writer management (usermanagement.php).
        'operate_tasks'   => ['support', 'admin', 'superadmin'],
        // Financial operations: overdrafts, budget, invoices, saving goals,
        // marking tasks paid/unpaid, bonus settlement.
        'operate_finance' => ['finance', 'admin', 'superadmin'],
        // Site-wide settings: registration open/close toggles, site
        // notification banner (sudo/settings.php).
        'manage_settings' => ['superadmin'],
        // Create/approve admin accounts and assign roles (sudo/manage-admins.php).
        'manage_admins'   => ['superadmin'],
    ]);
}

if (!function_exists('getAdminRole')) {
    /**
     * Looks up the current role for an admin email. Returns 'pending' for
     * an unknown email or a NULL/empty role column, so callers never have
     * to special-case "not found" - a missing account simply has no
     * capabilities, same as a real pending one.
     */
    function getAdminRole($con, $email)
    {
        if (empty($email)) {
            return 'pending';
        }
        $stmt = mysqli_prepare($con, "SELECT role FROM tbladmin WHERE email = ? LIMIT 1");
        if (!$stmt) {
            return 'pending';
        }
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        return (!empty($row['role'])) ? $row['role'] : 'pending';
    }
}

if (!function_exists('isApprovedAdminRole')) {
    // True for any real, approved role - false only for 'pending' (or junk).
    function isApprovedAdminRole($role)
    {
        return in_array($role, ADMIN_ROLES, true) && $role !== 'pending';
    }
}

if (!function_exists('adminCan')) {
    function adminCan($role, $capability)
    {
        return in_array($role, ADMIN_CAPABILITIES[$capability] ?? [], true);
    }
}

if (!function_exists('requireCapability')) {
    /**
     * Page-level guard - call right after the auth include on any page/
     * endpoint that a capability should gate. $mode controls how a denial
     * is communicated:
     *   'redirect' (default) - bounce to the dashboard, for full HTML pages
     *   'json'                - 403 + JSON body, for AJAX endpoints
     *   'text'                - 403 + plain text, for CSV/PDF exports
     */
    function requireCapability($role, $capability, $mode = 'redirect')
    {
        if (adminCan($role, $capability)) {
            return;
        }

        if ($mode === 'json') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Your role does not have permission to access this.']);
            exit();
        }

        if ($mode === 'text') {
            http_response_code(403);
            header('Content-Type: text/plain');
            echo 'Forbidden: your role does not have permission to access this.';
            exit();
        }

        header('Location: index');
        exit();
    }
}
