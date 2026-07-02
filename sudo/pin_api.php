<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

ob_start();
include_once __DIR__ . '/head.php';
ob_end_clean();

$adminId = $_SESSION['odmsaid'] ?? null;
if (!$adminId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated. Please log in.']);
    exit;
}

// ── Request parsing ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($input['action'] ?? '');

// ── Ensure PIN table exists ────────────────────────────────────────────────
try {
    $dbh->exec("
        CREATE TABLE IF NOT EXISTS tbl_dashboard_pin (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            admin_id   VARCHAR(255) NOT NULL UNIQUE,
            pin_hash   VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB init error: ' . $e->getMessage()]);
    exit;
}

// ── Helpers ────────────────────────────────────────────────────────────────
function getStoredHash(PDO $dbh, string $adminId): ?string {
    $stmt = $dbh->prepare("SELECT pin_hash FROM tbl_dashboard_pin WHERE admin_id = :aid LIMIT 1");
    $stmt->execute([':aid' => $adminId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['pin_hash'] : null;
}

function upsertPin(PDO $dbh, string $adminId, string $hash): void {
    $stmt = $dbh->prepare("
        INSERT INTO tbl_dashboard_pin (admin_id, pin_hash)
        VALUES (:aid, :hash)
        ON DUPLICATE KEY UPDATE pin_hash = :hash2, updated_at = NOW()
    ");
    $stmt->execute([':aid' => $adminId, ':hash' => $hash, ':hash2' => $hash]);
}

function deletePin(PDO $dbh, string $adminId): void {
    $stmt = $dbh->prepare("DELETE FROM tbl_dashboard_pin WHERE admin_id = :aid");
    $stmt->execute([':aid' => $adminId]);
}

function sessionIsUnlocked(string $adminId): bool {
    $key = 'dashboard_unlocked_' . md5($adminId);
    $tKey = 'dashboard_unlock_time_' . md5($adminId);
    if (empty($_SESSION[$key])) return false;
    if ((time() - ($_SESSION[$tKey] ?? 0)) > 1800) { // 30 min
        unset($_SESSION[$key], $_SESSION[$tKey]);
        return false;
    }
    return true;
}

function grantSession(string $adminId): void {
    $key = 'dashboard_unlocked_' . md5($adminId);
    $tKey = 'dashboard_unlock_time_' . md5($adminId);
    $_SESSION[$key]  = true;
    $_SESSION[$tKey] = time();
}

function revokeSession(string $adminId): void {
    $key = 'dashboard_unlocked_' . md5($adminId);
    $tKey = 'dashboard_unlock_time_' . md5($adminId);
    unset($_SESSION[$key], $_SESSION[$tKey]);
}

// ── Route ──────────────────────────────────────────────────────────────────
try {
    switch ($action) {

        // ── Set / Change PIN ───────────────────────────────────────────────
        case 'set_pin':
            $currentPin = $input['current_pin'] ?? '';
            $pin        = $input['pin']         ?? '';
            $confirm    = $input['confirm']     ?? '';

            // If a PIN already exists, require the current PIN before allowing a change
            $existingHash = getStoredHash($dbh, $adminId);
            if ($existingHash !== null) {
                if (!$currentPin) {
                    echo json_encode(['success' => false, 'message' => 'Please enter your current PIN to make changes.']);
                    exit;
                }
                if (!password_verify($currentPin, $existingHash)) {
                    echo json_encode(['success' => false, 'message' => 'Current PIN is incorrect.']);
                    exit;
                }
            }

            if (!preg_match('/^\d{4,8}$/', $pin)) {
                echo json_encode(['success' => false, 'message' => 'New PIN must be 4–8 digits.']);
                exit;
            }
            if ($pin !== $confirm) {
                echo json_encode(['success' => false, 'message' => 'New PINs do not match.']);
                exit;
            }

            upsertPin($dbh, $adminId, password_hash($pin, PASSWORD_DEFAULT));
            $msg = $existingHash !== null ? 'PIN changed successfully.' : 'PIN set successfully.';
            echo json_encode(['success' => true, 'message' => $msg]);
            break;

        // ── Verify PIN (called by lock overlay on 14.php) ─────────────────
        case 'verify_pin':
            $pin  = trim($input['pin'] ?? '');
            $hash = getStoredHash($dbh, $adminId);

            if ($hash === null) {
                grantSession($adminId);
                echo json_encode(['success' => true, 'message' => 'No PIN set.']);
                exit;
            }

            if (password_verify($pin, $hash)) {
                grantSession($adminId);
                echo json_encode(['success' => true, 'message' => 'Access granted.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Incorrect PIN. Please try again.']);
            }
            break;

        // ── Check status (called on 14.php page load) ─────────────────────
        case 'check_pin_status':
            $hash = getStoredHash($dbh, $adminId);
            echo json_encode([
                'success'     => true,
                'pin_set'     => ($hash !== null),
                'is_unlocked' => sessionIsUnlocked($adminId),
            ]);
            break;

        // ── Remove PIN ────────────────────────────────────────────────────
        case 'remove_pin':
            $pin  = $input['pin'] ?? '';
            $hash = getStoredHash($dbh, $adminId);

            if ($hash === null) {
                echo json_encode(['success' => false, 'message' => 'No PIN is currently set.']);
                exit;
            }
            if (!password_verify($pin, $hash)) {
                echo json_encode(['success' => false, 'message' => 'Incorrect current PIN.']);
                exit;
            }

            deletePin($dbh, $adminId);
            revokeSession($adminId);
            echo json_encode(['success' => true, 'message' => 'PIN removed. Dashboard is now unlocked.']);
            break;

        // ── Lock (manual lock button) ─────────────────────────────────────
        case 'lock':
            revokeSession($adminId);
            echo json_encode(['success' => true, 'message' => 'Dashboard locked.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>