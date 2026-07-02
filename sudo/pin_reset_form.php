<?php
include 'check-login.php';   // ensures $dbh is available

$token = $_GET['token'] ?? '';
$ref   = $_GET['ref']   ?? '';

$tokenValid   = false;
$expiredError = false;

if ($token && $ref) {
    $adminEmail = base64_decode($ref);
    if ($adminEmail) {
        $stmt = $dbh->prepare(
            "SELECT pin_reset_token, pin_reset_expires FROM tbladmin WHERE email = :email LIMIT 1"
        );
        $stmt->bindParam(':email', $adminEmail);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_OBJ);

        if ($row && !empty($row->pin_reset_token)) {
            if (strtotime($row->pin_reset_expires) >= time()) {
                $tokenValid = true;
            } else {
                $expiredError = true;
            }
        }
    }
}
?>
<?php include "head.php"; ?>
    <title>iTasker | Reset Dashboard PIN</title>
<?php include "navi.php"; ?>

    <div class="container" style="max-width:460px; margin:60px auto;">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white text-center">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Reset Financial Dashboard PIN</h5>
            </div>
            <div class="card-body p-4">

                <?php if ($expiredError): ?>
                    <!-- ── Link expired ── -->
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-clock me-1"></i>
                        This reset link has <strong>expired</strong>.<br>
                        Please go back to Settings and request a new one.
                    </div>
                    <a href="settings" class="btn btn-outline-secondary w-100">← Back to Settings</a>

                <?php elseif (!$tokenValid): ?>
                    <!-- ── Invalid / used link ── -->
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        This link is <strong>invalid</strong> or has already been used.
                    </div>
                    <a href="settings" class="btn btn-outline-secondary w-100">← Back to Settings</a>

                <?php else: ?>
                    <!-- ── Valid token — show form ── -->
                    <p class="text-muted fs-6 text-center mb-4">
                        Enter a new PIN for the Financial Dashboard (4–8 digits).
                    </p>
                    <div id="resetMsg" class="mb-3" style="display:none;"></div>

                    <div class="mb-3">
                        <label class="form-label">New PIN <small class="text-muted">(4–8 digits)</small></label>
                        <input type="password" inputmode="numeric" pattern="[0-9]*"
                               class="form-control" id="newPin" maxlength="8" placeholder="e.g. 4782">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New PIN</label>
                        <input type="password" inputmode="numeric" pattern="[0-9]*"
                               class="form-control" id="confirmPin" maxlength="8" placeholder="Repeat PIN">
                    </div>
                    <button class="btn btn-primary w-100" id="resetBtn" onclick="submitReset()">
                        <i class="fas fa-save me-1"></i> Set New PIN
                    </button>

                    <script>
                        async function submitReset() {
                            const pin     = document.getElementById('newPin').value;
                            const confirm = document.getElementById('confirmPin').value;
                            const msg     = document.getElementById('resetMsg');
                            const btn     = document.getElementById('resetBtn');

                            if (!/^\d{4,8}$/.test(pin)) {
                                showMsg('PIN must be 4–8 numeric digits.', 'danger'); return;
                            }
                            if (pin !== confirm) {
                                showMsg('PINs do not match.', 'danger'); return;
                            }

                            btn.disabled = true;
                            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

                            try {
                                const res  = await fetch('pin_reset', {
                                    method : 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body   : JSON.stringify({
                                        action : 'reset_pin',
                                        token  : '<?php echo htmlspecialchars($token, ENT_QUOTES); ?>',
                                        ref    : '<?php echo htmlspecialchars($ref,   ENT_QUOTES); ?>',
                                        pin,
                                        confirm
                                    })
                                });
                                const data = await res.json();

                                if (data.success) {
                                    showMsg('<i class="fas fa-check-circle me-1"></i>' + data.message, 'success');
                                    btn.style.display = 'none';
                                    // Redirect to settings after 3 s
                                    setTimeout(() => { window.location.href = 'settings'; }, 3000);
                                } else {
                                    showMsg(data.message, 'danger');
                                    btn.disabled = false;
                                    btn.innerHTML = '<i class="fas fa-save me-1"></i> Set New PIN';
                                }
                            } catch(e) {
                                showMsg('Request failed. Please try again.', 'danger');
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-save me-1"></i> Set New PIN';
                            }
                        }

                        function showMsg(html, type) {
                            const el = document.getElementById('resetMsg');
                            el.style.display = 'block';
                            el.innerHTML = `<div class="alert alert-${type} py-2 mb-0">${html}</div>`;
                        }
                    </script>
                <?php endif; ?>

            </div>
        </div>
    </div>

<?php include "footer.php"; ?>