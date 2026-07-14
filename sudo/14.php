<?php
// ═══════════════════════════════════════════════════════════════════
//  PIN GATE — session + DB check before any body HTML is output.
//  head.php handles DB/session bootstrap (no double-include).
// ═══════════════════════════════════════════════════════════════════
// Load DB without double-include (head.php also includes dbcon.php)
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/dbcon.php';
}

ob_start(); // buffer head.php output — only emit if not locked
include 'head.php';
$_headHtml = ob_get_clean();

$adminId = $_SESSION['odmsaid'] ?? null;

// ── Check DB for a PIN belonging to this admin ──────────────────────
$_pinLocked = false;
if ($adminId && isset($dbh) && $dbh instanceof PDO) {
    try {
        $dbh->exec("CREATE TABLE IF NOT EXISTS tbl_dashboard_pin (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            admin_id   VARCHAR(255) NOT NULL UNIQUE,
            pin_hash   VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $s = $dbh->prepare("SELECT pin_hash FROM tbl_dashboard_pin WHERE admin_id = :aid LIMIT 1");
        $s->execute([':aid' => $adminId]);
        $pinRow = $s->fetch(PDO::FETCH_ASSOC);

        if ($pinRow) {
            $sKey = 'dashboard_unlocked_' . md5($adminId);
            $tKey = 'dashboard_unlock_time_' . md5($adminId);
            $unlocked = !empty($_SESSION[$sKey]);
            if ($unlocked && (time() - ($_SESSION[$tKey] ?? 0)) > 1800) {
                unset($_SESSION[$sKey], $_SESSION[$tKey]);
                $unlocked = false;
            }
            $_pinLocked = !$unlocked;
        }
    } catch (Exception $e) {
        $_pinLocked = false;
    }
}

// ── If locked: render standalone PIN screen and stop ────────────────
if ($_pinLocked):
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Financial Dashboard — PIN Required</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%; width: 100%;
            background: radial-gradient(ellipse at 60% 30%, #1e1b4b 0%, #0f172a 60%, #020617 100%);
            display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            overflow: hidden;
        }
        /* subtle animated background orbs */
        body::before, body::after {
            content: ''; position: fixed; border-radius: 50%;
            filter: blur(80px); opacity: .25; pointer-events: none;
        }
        body::before { width: 480px; height: 480px; background: #6366f1; top: -120px; right: -80px; animation: orb 8s ease-in-out infinite alternate; }
        body::after  { width: 380px; height: 380px; background: #8b5cf6; bottom: -100px; left: -60px; animation: orb 10s ease-in-out infinite alternate-reverse; }
        @keyframes orb { from { transform: translate(0,0) scale(1); } to { transform: translate(30px,20px) scale(1.1); } }

        #pinCard {
            background: rgba(15, 18, 35, 0.85);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 1.75rem;
            box-shadow: 0 40px 100px rgba(0,0,0,.7), 0 0 0 1px rgba(99,102,241,.2);
            padding: 2.75rem 2.25rem 2.25rem;
            width: 100%; max-width: 360px;
            text-align: center;
            animation: slideUp .45s cubic-bezier(.22,1,.36,1) forwards;
            position: relative; z-index: 1;
        }
        @keyframes slideUp {
            from { opacity:0; transform: translateY(32px) scale(.95); }
            to   { opacity:1; transform: translateY(0) scale(1); }
        }
        .lock-icon {
            width: 72px; height: 72px; border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.9rem; color: #fff; margin-bottom: 1.3rem;
            box-shadow: 0 10px 30px rgba(99,102,241,.5);
            transition: transform .3s;
        }
        h4 { color: #fff; font-size: 1.2rem; font-weight: 700; margin-bottom: .3rem; }
        .sub { color: rgba(255,255,255,.45); font-size: .82rem; margin-bottom: 1.6rem; }

        /* dots */
        #pinDots { display:flex; justify-content:center; gap:.65rem; margin-bottom:1.5rem; }
        .dot {
            width:13px; height:13px; border-radius:50%;
            border: 2px solid rgba(255,255,255,.25);
            background: transparent;
            transition: background .18s, border-color .18s, transform .15s;
        }
        .dot.on  { background:#6366f1; border-color:#6366f1; transform:scale(1.25); }
        .dot.err { background:#ef4444; border-color:#ef4444; }
        .dot.ok  { background:#22c55e; border-color:#22c55e; }

        /* keypad */
        #pad { display:grid; grid-template-columns:repeat(3,1fr); gap:.55rem; margin-bottom:.9rem; }
        .k {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.10);
            border-radius: .75rem; color:#fff;
            font-size:1.3rem; font-weight:600;
            padding: .9rem .5rem; cursor:pointer;
            transition: background .15s, transform .1s;
            user-select:none; -webkit-tap-highlight-color:transparent;
        }
        .k:hover  { background:rgba(99,102,241,.28); border-color:rgba(99,102,241,.45); }
        .k:active { background:rgba(99,102,241,.45); transform:scale(.92); }
        .k.del    { background:rgba(239,68,68,.10); border-color:rgba(239,68,68,.2); color:#f87171; }
        .k.del:hover { background:rgba(239,68,68,.22); }
        .k:disabled { opacity:.35; cursor:not-allowed; }

        #msg { min-height:1.4em; font-size:.82rem; font-weight:500; }
        #msg.err { color:#f87171; } #msg.ok { color:#4ade80; }
        #tries { font-size:.73rem; color:rgba(255,255,255,.3); margin-top:.5rem; }

        @keyframes shake {
            0%,100%{transform:translateX(0)}
            20%{transform:translateX(-9px)} 40%{transform:translateX(9px)}
            60%{transform:translateX(-6px)} 80%{transform:translateX(6px)}
        }
        .shake { animation: shake .38s ease; }

        /* Accounts Details table: whole row is clickable to view details. */
        #accountsTableBody tr.account-row:hover {
            background-color: rgba(99, 102, 241, 0.06);
        }
    </style>
</head>
<body>
<div id="pinCard">
    <div class="lock-icon" id="lockIcon"><i class="fas fa-lock"></i></div>
    <h4>Financial Dashboard</h4>
    <p class="sub">Enter your PIN to continue</p>

    <div id="pinDots">
        <div class="dot"></div><div class="dot"></div>
        <div class="dot"></div><div class="dot"></div>
    </div>

    <div id="pad">
        <button class="k" onclick="pk('1')">1</button>
        <button class="k" onclick="pk('2')">2</button>
        <button class="k" onclick="pk('3')">3</button>
        <button class="k" onclick="pk('4')">4</button>
        <button class="k" onclick="pk('5')">5</button>
        <button class="k" onclick="pk('6')">6</button>
        <button class="k" onclick="pk('7')">7</button>
        <button class="k" onclick="pk('8')">8</button>
        <button class="k" onclick="pk('9')">9</button>
        <button class="k del" onclick="clr()" title="Clear"><i class="fas fa-times-circle"></i></button>
        <button class="k" onclick="pk('0')">0</button>
        <button class="k" onclick="bsp()" title="Backspace"><i class="fas fa-backspace"></i></button>
    </div>

    <div id="msg"></div>
    <div id="tries"></div>
</div>

<script>
    var buf = '', attempts = 0, MAX = 5, submitting = false;

    function dots() {
        var n = Math.max(4, buf.length), c = document.getElementById('pinDots');
        c.innerHTML = '';
        for (var i = 0; i < n; i++) {
            var d = document.createElement('div');
            d.className = 'dot' + (i < buf.length ? ' on' : '');
            c.appendChild(d);
        }
    }
    function pk(d) {
        if (buf.length >= 8 || submitting) return;
        buf += d; dots();
        if (buf.length === 4) setTimeout(submit, 130);
    }
    function bsp() { buf = buf.slice(0,-1); dots(); setMsg('',''); }
    function clr()  { buf = ''; dots(); setMsg('',''); }

    function setMsg(t, cls) {
        var el = document.getElementById('msg');
        el.textContent = t; el.className = cls;
    }
    function markDots(cls) {
        document.querySelectorAll('.dot').forEach(function(d){
            d.classList.remove('on','err','ok'); d.classList.add(cls);
        });
        setTimeout(function(){ buf=''; dots(); }, 650);
    }
    function shake() {
        var c = document.getElementById('pinCard');
        c.classList.remove('shake'); void c.offsetWidth; c.classList.add('shake');
    }
    function disablePad() {
        document.querySelectorAll('.k').forEach(function(b){ b.disabled=true; });
    }

    async function submit() {
        if (!buf || submitting) return;
        submitting = true;
        var entered = buf; buf = ''; dots();
        try {
            var r = await fetch('pin_api', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({action:'verify_pin', pin: entered})
            });
            var data = await r.json();
            if (data.success) {
                setMsg('✓ Access granted', 'ok');
                markDots('ok');
                document.getElementById('lockIcon').innerHTML = '<i class="fas fa-unlock"></i>';
                document.getElementById('lockIcon').style.background = 'linear-gradient(135deg,#22c55e,#16a34a)';
                setTimeout(function(){ window.location.reload(); }, 600);
            } else {
                attempts++;
                var left = MAX - attempts;
                // Show the actual server message (catches auth errors, DB errors etc.)
                var errMsg = (data.message && data.message !== 'Incorrect PIN. Please try again.')
                    ? data.message
                    : '✗ Incorrect PIN';
                setMsg(errMsg, 'err');
                markDots('err'); shake();
                if (left > 0) {
                    document.getElementById('tries').textContent = left + ' attempt' + (left!==1?'s':'') + ' remaining';
                } else {
                    setMsg('Too many attempts. Reload to try again.', 'err');
                    document.getElementById('tries').textContent = '';
                    disablePad();
                }
                submitting = false;
            }
        } catch(e) {
            setMsg('Connection error. Try again.', 'err');
            submitting = false;
        }
    }

    document.addEventListener('keydown', function(e){
        if (e.key >= '0' && e.key <= '9') pk(e.key);
        else if (e.key === 'Backspace') bsp();
        else if (e.key === 'Escape') clr();
        else if (e.key === 'Enter') submit();
    });
</script>
</body>
</html>
<?php
exit; // ← Dashboard content NEVER renders when locked
endif;
// ═══════════════════════════════════════════════════════════════════
//  PIN GATE END — authenticated; emit the buffered head.php output
// ═══════════════════════════════════════════════════════════════════
echo $_headHtml;
?>
<title>Financial Dashboard</title>
<?php include 'navi.php'; ?>


<div class='container-fluid'>
    <!-- Header -->
    <div class='card shadow-none border mb-3'>
        <div class='bg-holder bg-card d-none d-md-block'
             style='background-image:url(../assets/img/illustrations/corner-6.png);'>
        </div>
        <!--/.bg-holder-->

        <div class='card-header z-1'>
            <div class='row flex-between-center gx-0'>
                <div class='col-lg-auto d-flex align-items-center'>
                    <h4 class='mb-0 text-primary fw-bold'>Account Distribution <span class='text-info fw-medium'>Dashboard</span>
                    </h4>
                </div>
                <div class='col-lg-auto pt-3 pt-lg-0'>
                    <form class="$rowTask flex-lg-column flex-xxl-$rowTask gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
                        <div class='col-auto'>
                        </div>
                        <div class='col-md-auto position-relative'>
                            <h6 class='mb-1 badge rounded-pill badge-subtle-info'><?php echo date('jS F Y'); ?> |
                                <span id="timeDisplay"></span></h6>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-none border mb-3">
        <div class='col-md-auto mt-3 mb-3 text-end'>
            <span class="d-inline-flex align-items-center me-2" data-bs-toggle='tooltip' data-bs-placement='top' title='Converts chart & total figures. Individual accounts always show their own currency.'>
                <label for='displayCurrencySelect' class='fs-11 text-muted me-1 mb-0'><i class='fas fa-coins'></i></label>
                <select id='displayCurrencySelect' class='form-select form-select-sm d-inline-block' style='width:auto;' onchange='changeDisplayCurrency(this.value)'>
                </select>
            </span>
            <button type='button' onclick='refreshData()' class='btn btn-sm btn-outline-success me-2' data-bs-toggle='tooltip' data-bs-placement='top' title='Refresh'>
                <i class='fas fa-sync-alt' aria-hidden='true'></i>
                <span class='d-none d-sm-inline-block d-xl-none d-xxl-inline-block ms-1'>Refresh</span> <span class='spinner-border spinner-border-sm loading' role='status'></span>
            </button>
            <button type='button' onclick='showExchangeRatesModal()' class='btn btn-sm btn-outline-warning me-2' data-bs-toggle='tooltip' data-bs-placement='top' title='Exchange Rates'>
                <i class='fas fa-coins' aria-hidden='true'></i>
                <span class='d-none d-sm-inline-block d-xl-none d-xxl-inline-block ms-1'>Exchange Rates</span>
            </button>
            <button type='button' onclick='showAddAccountModal()' class='btn btn-sm btn-outline-info me-2' data-bs-toggle='tooltip' data-bs-placement='top' title='Add Account'>
                <i class='fas fa-plus-circle' aria-hidden='true'></i>
                <span class='d-none d-sm-inline-block d-xl-none d-xxl-inline-block ms-1'>Add Account</span>
            </button>
            <button type='button' onclick='showUpdateBalanceModal()' class='btn btn-sm btn-outline-facebook me-2' data-bs-toggle='tooltip' data-bs-placement='top' title='Update Balances'>
                <i class='fas fa-hand-holding-usd' aria-hidden='true'></i>
                <span class='d-none d-sm-inline-block d-xl-none d-xxl-inline-block ms-1'>Update Balances</span>
            </button>
            <button type='button' onclick='showManageTypesModal()' class='btn btn-sm btn-outline-primary me-2' data-bs-toggle='tooltip' data-bs-placement='top' title='Manage Account Types'>
                <i class='fas fa-cogs' aria-hidden='true'></i>
                <span class='d-none d-sm-inline-block d-xl-none d-xxl-inline-block ms-1'>Manage Types</span>
            </button>
            <button type='button' onclick='showMonthViewer()' class='btn btn-sm btn-outline-google-plus me-2' data-bs-toggle='tooltip' data-bs-placement='top' title='View by Month'>
                <i class='far fa-calendar-alt' aria-hidden='true'></i>
                <span class='d-none d-sm-inline-block d-xl-none d-xxl-inline-block ms-1'>View by Month</span>
            </button>
            <button type='button' onclick='exportData()' class='btn btn-sm btn-outline-secondary me-2' data-bs-toggle='tooltip' data-bs-placement='top' title='Export'>
                <i class='fas fa-download' aria-hidden='true'></i>
                <span class='d-none d-sm-inline-block d-xl-none d-xxl-inline-block ms-1'>Export</span>
            </button>

        </div>
    </div>

    <!-- Summary Cards -->
    <div class='row g-3 mb-3'>
        <div class='col-md-6 col-xxl-3'>
            <div class='card account-card h-md-100' onclick='showTotalBalanceBreakdown()' style='cursor: pointer;' data-bs-toggle='tooltip' data-bs-placement='top' title='Click to view balance breakdown'>
                <div class='card-header d-flex flex-between-center pb-0'>
                    <h6 class='mb-0'>Total Balance</h6>
                    <i class='fas fa-wallet fa-2x opacity-75'></i>
                </div>
                <div class='card-body pt-2'>
                    <div class='row g-0 h-100 align-items-center'>
                        <div class='col'>
                            <div class='d-flex align-items-center'>
                                <div>
                                    <h5 class="mb-2" id='totalBalance'>Loading...</h5>
                                    <span class='badge rounded-pill fs-11' id='monthlyGrowth'>Loading...</span>
                                    <small class='d-block mt-1 opacity-75'><i class='fas fa-external-link-alt fa-xs'></i> Click for details</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class='col-md-6 col-xxl-3'>
            <div class='card account-card h-md-100'>
                <div class='card-header d-flex flex-between-center pb-0'>
                    <h6 class='mb-0'>Accounts</h6>
                    <i class='fas fa-university fa-2x opacity-75'></i>
                </div>
                <div class='card-body pt-2'>
                    <div class='row g-0 h-100 align-items-center'>
                        <div class='col'>
                            <div class='d-flex align-items-center'>
                                <div>
                                    <h5 class='mb-2' id='activeAccounts'>Loading...</h5>
                                    <small class='opacity-75' id='inactiveAccounts'></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class='col-md-6 col-xxl-3'>
            <div class='card account-card h-md-100' onclick='showSavingsBreakdown()' style='cursor: pointer;' data-bs-toggle='tooltip' data-bs-placement='top' title='Click to view savings breakdown'>
                <div class='card-header d-flex flex-between-center pb-0'>
                    <h6 class='mb-0' id='savingsTitle'>Monthly Savings</h6>
                    <i class='fas fa-piggy-bank fa-2x opacity-75'></i>
                </div>
                <div class='card-body pt-2'>
                    <div class='row g-0 h-100 align-items-center'>
                        <div class='col'>
                            <div class='d-flex align-items-center'>
                                <div>
                                    <h5 class="mb-2" id='monthlySavings'>Loading...</h5>
                                    <small class='opacity-75' id='savingsDetails'>Savings, MMF & SACCO <i class='fas fa-external-link-alt ms-1 fa-xs'></i></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class='col-md-6 col-xxl-3'>
            <div class='card account-card h-md-100' onclick='showLatestMonthDetails()' style='cursor: pointer;' data-bs-toggle='tooltip' data-bs-placement='top' title='Click for detailed month overview'>
                <div class='card-header d-flex flex-between-center pb-0'>
                    <h6 class='mb-0'>Latest Data Month</h6>
                    <i class='fas fa-calendar-alt fa-2x opacity-75'></i>
                </div>
                <div class='card-body pt-2'>
                    <div class='row g-0 h-100 align-items-center'>
                        <div class='col'>
                            <div class='d-flex align-items-center'>
                                <div class='w-100'>
                                    <h5 class='mb-1' id='latestDataMonth'>Loading...</h5>
                                    <div id='latestDataDetails' class='small'>-</div>
                                    <div class='mt-2' id='latestDataProgress' style='display: none;'>
                                        <div class='progress' style='height: 6px;'>
                                            <div class='progress-bar bg-success' id='dataCompletionBar' role='progressbar' style='width: 0%'></div>
                                        </div>
                                        <small class='text-muted' id='dataCompletionText'>0% complete</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class='row mb-4'>
        <div class='col-lg-6'>
            <div class='card bg-line-chart-gradient'>
                <div class='card-header bg-body-tertiary'>
                    <h5 class='mb-0'><i class='fas fa-chart-pie me-2'></i>Distribution by Account Type</h5>
                </div>
                <div class='card-body'>
                    <div class='chart-container'>
                        <div id='pieChart'></div>
                    </div>
                </div>
            </div>
        </div>
        <div class='col-lg-6'>
            <div class='card bg-line-chart-gradient'>
                <div class='card-header bg-body-tertiary'>
                    <h5 class='mb-0'><i class='fas fa-chart-bar me-2'></i>Accounts Balance Comparison</h5>
                </div>
                <div class='card-body'>
                    <div class='chart-container'>
                        <div id='barChart'></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class='row mb-4'>
        <div class='col-12'>
            <div class='card bg-line-chart-gradient'>
                <div class='card-header bg-body-tertiary d-flex justify-content-between align-items-center flex-wrap'>
                    <h5 class='mb-0'><i class='fas fa-chart-line me-2'></i>Monthly Growth Trend</h5>
                    <span id='growthForecastNote' class='text-muted fs-11'></span>
                </div>
                <div class='card-body'>
                    <div class='chart-container'>
                        <div id='growthChart'></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Table -->
    <div class='row'>
        <div class='col-12'>
            <div class='card'>
                <div class='card-header bg-body-tertiary d-flex justify-content-between align-items-center'>
                    <h5 class='mb-0'><i class='fas fa-table me-2'></i>Accounts Details</h5>
                    <div class='input-group' style='width: 300px;'>
                        <input type='text' class='form-control' placeholder='Search accounts...' id='searchInput'>
                        <button class='btn btn-outline-light' type='button' onclick='searchAccounts()'>
                            <i class='fas fa-search'></i>
                        </button>
                    </div>
                </div>
                <div class='card-body p-0'>
                    <div class='table-responsive'>
                        <table class='table table-hover mb-0 table-border fs-10 border-200 overflow-hidden rounded-3' id='accountsTable'>
                            <thead class='bg-body-tertiary'>
                            <tr>
                                <th scope='col' onclick='sortTable(0)' style='cursor: pointer;'>
                                    Account Name <i class='fas fa-sort'></i>
                                </th>
                                <th scope='col' onclick='sortTable(2)' style='cursor: pointer;'>
                                    Balance <i class='fas fa-sort'></i>
                                </th>
                                <th scope='col' onclick='sortTable(4)' style='cursor: pointer;'>
                                    As at <i class='fas fa-sort'></i>
                                </th>
                                <th scope='col'>Status</th>
                            </tr>
                            </thead>
                            <tbody id='accountsTableBody'>
                            <tr>
                                <td colspan='4' class='text-center py-4'>
                                    <div class='spinner-border text-primary' role='status'>
                                        <span class='visually-hidden'>Loading...</span>
                                    </div>
                                    <p class='mt-2'>Loading accounts...</p>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class='card-footer bg-body-tertiary'>
                    <div class='d-flex justify-content-between align-items-center'>
                        <small class='text-muted' id='accountCount'>Loading accounts...</small>
                        <nav>
                            <ul class='pagination pagination-sm mb-0'>
                                <li class='page-item disabled'><a class='page-link' href='#'>Previous</a></li>
                                <li class='page-item active'><a class='page-link' href='#'>1</a></li>
                                <li class='page-item disabled'><a class='page-link' href='#'>Next</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add New Account Modal -->
<div class='modal fade' id='addAccountModal' tabindex='-1'>
    <div class='modal-dialog modal-lg'>
        <div class='modal-content'>
            <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                <div class='position-relative z-1'>
                    <h4 class='mb-0 text-white'>Add New Account</h4>
                </div>
                <div data-bs-theme='dark'>
                    <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
            </div>
            <div class='modal-body'>
                <form id='addAccountForm'>
                    <div class='row'>
                        <!-- Account Name -->
                        <div class='col-md-6 mb-3'>
                            <label for='accountName' class='form-label'>
                                <i class='fas fa-tag text-primary'></i> Account Name *
                            </label>
                            <input type='text' class='form-control' id='accountName'
                                   placeholder='e.g., Main Checking Account' required maxlength='100'>
                            <div class='form-text'>Choose a descriptive name for your account</div>
                        </div>

                        <!-- Account Type -->
                        <div class='col-md-6 mb-3'>
                            <label for='accountType' class='form-label'>
                                <i class='fas fa-list text-primary'></i> Account Type *
                            </label>
                            <select class='form-select' id='accountType' required>
                                <option value=''>Select Account Type</option>
                            </select>
                            <div class='form-text'>Select the type of account</div>
                        </div>

                        <!-- Currency -->
                        <div class='col-md-6 mb-3'>
                            <label for='accountCurrency' class='form-label'>
                                <i class='fas fa-coins text-primary'></i> Currency *
                            </label>
                            <select class='form-select' id='accountCurrency' required>
                                <option value=''>Select Currency</option>
                            </select>
                            <div class='form-text'>The currency this account's balance is held in. Can't be changed automatically later — edit the account if it was set wrong.</div>
                        </div>

                        <!-- Initial Balance -->
                        <div class='col-md-6 mb-3'>
                            <label for='initialBalance' class='form-label'>
                                <i class='fas fa-dollar-sign text-success'></i> Initial Balance *
                            </label>
                            <div class='input-group'>
                                <span class='input-group-text' id='initialBalanceCurrencyPrefix'>$</span>
                                <input type='number' step='0.01' class='form-control' id='initialBalance'
                                       placeholder='0.00' required min='0'>
                            </div>
                            <div class='form-text'>Enter the current balance for this account, in the currency selected above</div>
                        </div>

                        <!-- Bank Name -->
                        <div class='col-md-6 mb-3'>
                            <label for='bankName' class='form-label'>
                                <i class='fas fa-university text-primary'></i> Bank/Institution
                            </label>
                            <input type='text' class='form-control' id='bankName'
                                   placeholder='e.g., Chase Bank' maxlength='100'>
                            <div class='form-text'>Optional: Name of the bank or financial institution</div>
                        </div>

                        <!-- Account Number -->
                        <div class='col-md-6 mb-3'>
                            <label for='accountNumber' class='form-label'>
                                <i class='fas fa-hashtag text-primary'></i> Account Number
                            </label>
                            <input type='text' class='form-control' id='accountNumber'
                                   placeholder='****1234' maxlength='50'>
                            <div class='form-text'>Optional: Last 4 digits or masked account number</div>
                        </div>

                        <!-- Interest Rate -->
                        <div class='col-md-6 mb-3'>
                            <label for='interestRate' class='form-label'>
                                <i class='fas fa-percentage text-success'></i> Interest Rate (%)
                            </label>
                            <div class='input-group'>
                                <input type='number' step='0.01' class='form-control' id='interestRate'
                                       placeholder='0.00' min='0' max='100'>
                                <span class='input-group-text'>%</span>
                            </div>
                            <div class='form-text'>Optional: Annual interest rate</div>
                        </div>

                        <!-- Minimum Balance -->
                        <div class='col-md-6 mb-3'>
                            <label for='minimumBalance' class='form-label'>
                                <i class='fas fa-exclamation-triangle text-warning'></i> Minimum Balance
                            </label>
                            <div class='input-group'>
                                <span class='input-group-text' id='minimumBalanceCurrencyPrefix'>$</span>
                                <input type='number' step='0.01' class='form-control' id='minimumBalance'
                                       placeholder='0.00' min='0'>
                            </div>
                            <div class='form-text'>Optional: Required minimum balance</div>
                        </div>

                        <!-- Status -->
                        <div class='col-md-6 mb-3'>
                            <label for='accountStatus' class='form-label'>
                                <i class='fas fa-toggle-on text-success'></i> Status
                            </label>
                            <select class='form-select' id='accountStatus' required>
                                <option value='Active' selected>Active</option>
                                <option value='Inactive'>Inactive</option>
                                <option value='Locked'>Locked</option>
                                <option value='Low Balance'>Low Balance</option>
                                <option value='Debt'>Debt</option>
                            </select>
                            <div class='form-text'>Current status of the account</div>
                        </div>

                        <!-- Notes -->
                        <div class='col-12 mb-3'>
                            <label for='accountNotes' class='form-label'>
                                <i class='fas fa-sticky-note text-info'></i> Notes
                            </label>
                            <textarea class='form-control' id='accountNotes' rows='3'
                                      placeholder='Optional notes about this account...' maxlength='500'></textarea>
                            <div class='form-text'>Optional: Additional information about this account</div>
                        </div>
                    </div>

                    <!-- Summary Card -->
                    <div class='card border-0 mt-3' id='accountSummaryCard' style='display: none;'>
                        <div class='card-body py-2'>
                            <h6 class='card-title mb-2'>
                                <i class='fas fa-eye text-primary'></i> Account Preview
                            </h6>
                            <div class='row'>
                                <div class='col-md-6'>
                                    <small class='text-muted'>Account Name:</small>
                                    <div class='fw-bold' id='previewAccountName'>-</div>
                                </div>
                                <div class='col-md-6'>
                                    <small class='text-muted'>Initial Balance:</small>
                                    <div class='fw-bold text-success' id='previewBalance'>$0.00</div>
                                </div>
                                <div class='col-md-6 mt-2'>
                                    <small class='text-muted'>Account Type:</small>
                                    <div class='fw-bold' id='previewAccountType'>-</div>
                                </div>
                                <div class='col-md-6 mt-2'>
                                    <small class='text-muted'>Bank:</small>
                                    <div class='fw-bold' id='previewBank'>-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                    <i class='fas fa-times'></i> Cancel
                </button>
                <button type='button' class='btn btn-primary' onclick='addAccount()'>
                    <i class='fas fa-save'></i> Create Account
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Exchange Rates Modal -->
<div class='modal fade' id='exchangeRatesModal' tabindex='-1'>
    <div class='modal-dialog modal-lg'>
        <div class='modal-content'>
            <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                <div class='position-relative z-1'>
                    <h4 class='mb-0 text-white'><i class='fas fa-coins me-2'></i>Exchange Rates</h4>
                </div>
                <div data-bs-theme='dark'>
                    <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
            </div>
            <div class='modal-body'>
                <p class='text-muted fs-10 mb-3'>
                    How every supported currency compares to your preferred display currency,
                    <strong id='exRatesPreferredLabel'>USD</strong>. These rates are what the dashboard's charts and
                    totals use to convert mixed-currency accounts into one number.
                </p>
                <div class='table-responsive'>
                    <table class='table table-sm table-hover align-middle'>
                        <thead>
                        <tr>
                            <th>Currency</th>
                            <th class='text-end'>1 unit = <span id='exRatesPreferredLabel2'>USD</span></th>
                            <th class='text-end'>1 <span id='exRatesPreferredLabel3'>USD</span> =</th>
                            <th class='text-end'>Rate to USD</th>
                        </tr>
                        </thead>
                        <tbody id='exchangeRatesTableBody'>
                        <tr><td colspan='4' class='text-center text-muted py-3'>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class='text-muted fs-11 mt-2' id='exRatesLastUpdated'></div>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                    <i class='fas fa-times'></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Update Balance Modal -->
<div class='modal fade' id='updateBalanceModal' tabindex='-1'>
    <div class='modal-dialog modal-xl'>
        <div class='modal-content'>
            <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                <div class='position-relative z-1'>
                    <h4 class='mb-0 text-white'>Update Monthly Balances</h4>
                </div>
                <div data-bs-theme='dark'>
                    <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
            </div>
            <div class='modal-body'>
                <div class='mb-3'>
                    <label for='updateMonth' class='form-label'>Month/Year</label>
                    <input type='month' class='form-control' id='updateMonth' required>
                </div>
                <div id='balanceUpdateList'></div>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                <button type='button' class='btn btn-primary' onclick='saveBalanceUpdates()'>Save Updates</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Account Types Modal -->
<div class='modal fade' id='manageTypesModal' tabindex='-1'>
    <div class='modal-dialog modal-lg'>
        <div class='modal-content'>
            <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                <div class='position-relative z-1'>
                    <h4 class='mb-0 text-white'>Manage Account Types</h4>
                </div>
                <div data-bs-theme='dark'>
                    <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
            </div>
            <div class='modal-body'>
                <div class='row mb-3'>
                    <div class='col-md-6'>
                        <h6>Add New Type</h6>
                        <form id='addTypeForm'>
                            <div class='mb-2'>
                                <input type='text' class='form-control' id='newTypeName' placeholder='Type Name' required>
                            </div>
                            <div class='mb-2'>
                                <input type='color' class='form-control' id='newTypeColor' value='#007bff' required>
                            </div>
                            <div class='mb-2'>
                                <input type='text' class='form-control' id='newTypeIcon' placeholder='Icon Class (e.g., fas fa-wallet)' value='fas fa-wallet'>
                            </div>
                            <button type='button' class='btn btn-success btn-sm' onclick='addAccountType()'>Add Type</button>
                        </form>
                    </div>
                    <div class='col-md-6'>
                        <h6>Existing Types</h6>
                        <div id='existingTypesList'></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Month Balance Viewer Modal -->
<div class='modal fade' id='monthViewerModal' tabindex='-1'>
    <div class='modal-dialog modal-xl'>
        <div class='modal-content'>
            <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                <div class='position-relative z-1'>
                    <h4 class='mb-0 text-white'>View Balances By Month</h4>
                </div>
                <div data-bs-theme='dark'>
                    <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
            </div>
            <div class='modal-body'>
                <!-- Month Selection -->
                <div class='row mb-4'>
                    <div class='col-md-4'>
                        <label for='viewMonth' class='form-label'>Select Month</label>
                        <select class='form-select' id='viewMonth' onchange='loadBalancesByMonth()'>
                            <option value=''>Select Month</option>
                        </select>
                    </div>
                    <div class='col-md-4'>
                        <label class='form-label'>Quick Actions</label>
                        <div class='btn-group d-block'>
                            <button class='btn btn-outline-primary btn-sm' onclick='loadCurrentMonth()'>Current Month</button>
                            <button class='btn btn-outline-secondary btn-sm' onclick='loadPreviousMonth()'>Previous Month</button>
                        </div>
                    </div>
                    <div class='col-md-4'>
                        <label class='form-label'>Compare Months</label>
                        <button class='btn btn-outline-info btn-sm d-block' onclick='showMonthComparison()'>
                            <i class='fas fa-chart-line'></i> Compare Months
                        </button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class='row mb-4 summary-cards-row' id='monthSummaryCards' style='display: none;'>
                    <div class='col-xl-3 col-lg-3 col-md-6 col-sm-6 col-12 mb-3'>
                        <div class='card bg-body-secondary summary-card'>
                            <div class='card-body text-center d-flex flex-column justify-content-center'>
                                <h6 class='card-title mb-2'>Total Balance</h6>
                                <h5 class='mb-0' id='monthTotalBalance'>$0</h5>
                            </div>
                        </div>
                    </div>
                    <div class='col-xl-3 col-lg-3 col-md-6 col-sm-6 col-12 mb-3'>
                        <div class='card bg-body-secondary summary-card'>
                            <div class='card-body text-center d-flex flex-column justify-content-center'>
                                <h6 class='card-title mb-2'>Total Growth</h6>
                                <h5 class='mb-0' id='monthTotalGrowth'>$0</h5>
                            </div>
                        </div>
                    </div>
                    <div class='col-xl-3 col-lg-3 col-md-6 col-sm-6 col-12 mb-3'>
                        <div class='card bg-body-secondary summary-card'>
                            <div class='card-body text-center d-flex flex-column justify-content-center'>
                                <h6 class='card-title mb-2'>Accounts with Data</h6>
                                <h5 class='mb-0' id='monthAccountsCount'>0</h5>
                            </div>
                        </div>
                    </div>
                    <div class='col-xl-3 col-lg-3 col-md-6 col-sm-6 col-12 mb-3'>
                        <div class='card bg-body-secondary summary-card'>
                            <div class='card-body text-center d-flex flex-column justify-content-center'>
                                <h6 class='card-title mb-2'>Selected Month</h6>
                                <h5 class='mb-0' id='selectedMonthDisplay'>-</h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accounts Table -->
                <div class='table-responsive'>
                    <table class='table table-hover' id='monthBalancesTable' style='display: none;'>
                        <thead class='table-primary'>
                        <tr>
                            <th>Account Name</th>
                            <th>Type</th>
                            <th>Balance</th>
                            <th>Growth Amount</th>
                            <th>Growth %</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody id='monthBalancesTableBody'>
                        </tbody>
                    </table>
                </div>

                <!-- No Data Message -->
                <div id='noMonthDataMessage' class='text-center py-4' style='display: none;'>
                    <i class='fas fa-calendar-times fa-3x text-muted mb-3'></i>
                    <h5>No data available</h5>
                    <p class='text-muted'>Select a month to view balance data</p>
                </div>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                <button type='button' class='btn btn-primary' onclick='exportMonthData()'>
                    <i class='fas fa-download'></i> Export Month Data
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Month Comparison Modal -->
<div class='modal fade' id='monthComparisonModal' tabindex='-1'>
    <div class='modal-dialog modal-lg'>
        <div class='modal-content'>
            <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                <div class='position-relative z-1'>
                    <h4 class='mb-0 text-white'>Compare Monthly Balances</h4>
                </div>
                <div data-bs-theme='dark'>
                    <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
            </div>
            <div class='modal-body'>
                <div class='row mb-3'>
                    <div class='col-md-6'>
                        <label for='startMonth' class='form-label'>Start Month</label>
                        <select class='form-select' id='startMonth'>
                            <option value=''>Select Start Month</option>
                        </select>
                    </div>
                    <div class='col-md-6'>
                        <label for='endMonth' class='form-label'>End Month</label>
                        <select class='form-select' id='endMonth'>
                            <option value=''>Select End Month</option>
                        </select>
                    </div>
                </div>
                <div class='text-center mb-3'>
                    <button class='btn btn-primary' onclick='loadMonthComparison()'>
                        <i class='fas fa-chart-line'></i> Compare
                    </button>
                </div>
                <div id='comparisonResults'></div>
            </div>
        </div>
    </div>
</div>

<!-- View Account Details Modal -->
<div class='modal fade' id='viewAccountModal' tabindex='-1'>
    <div class='modal-dialog modal-lg'>
        <div class='modal-content'>
            <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                <div class='position-relative z-1'>
                    <h4 class='mb-0 text-white' id='viewAccountModalTitle'>Account Details</h4>
                </div>
                <div data-bs-theme='dark'>
                    <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
            </div>
            <div class='modal-body'>
                <div id='viewAccountAlerts' class='mb-3' style='display:none;'></div>
                <div class='row'>
                    <!-- Account Information Card -->
                    <div class='col-md-6 mb-3'>
                        <div class='card h-100'>
                            <div class='card-header bg-body-tertiary'>
                                <h6 class='mb-0'><i class='fas fa-info-circle me-2'></i>Account Information</h6>
                            </div>
                            <div class='card-body'>
                                <div class='row mb-2'>
                                    <div class='col-5'><strong>Name:</strong></div>
                                    <div class='col-7' id='viewAccountName'>-</div>
                                </div>
                                <div class='row mb-2'>
                                    <div class='col-5'><strong>Type:</strong></div>
                                    <div class='col-7' id='viewAccountType'>-</div>
                                </div>
                                <div class='row mb-2'>
                                    <div class='col-5'><strong>Status:</strong></div>
                                    <div class='col-7' id='viewAccountStatus'>-</div>
                                </div>
                                <div class='row mb-2'>
                                    <div class='col-5'><strong>Created:</strong></div>
                                    <div class='col-7' id='viewAccountCreated'>-</div>
                                </div>
                                <div class='row mb-2'>
                                    <div class='col-5'><strong>Last Updated:</strong></div>
                                    <div class='col-7' id='viewAccountUpdated'>-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Information Card -->
                    <div class='col-md-6 mb-3'>
                        <div class='card h-100'>
                            <div class='card-header bg-body-tertiary'>
                                <h6 class='mb-0'><i class='fas fa-dollar-sign me-2'></i>Financial Information</h6>
                            </div>
                            <div class='card-body'>
                                <div class='row mb-2'>
                                    <div class='col-6'><strong>Current Balance:</strong></div>
                                    <div class='col-6'>
                                        <span class='fw-bold text-success' id='viewCurrentBalance'>$0.00</span>
                                    </div>
                                </div>
                                <div class='row mb-2'>
                                    <div class='col-6'><strong>Interest Rate:</strong></div>
                                    <div class='col-6' id='viewInterestRate'>0%</div>
                                </div>
                                <div class='row mb-2'>
                                    <div class='col-6'><strong>Minimum Balance:</strong></div>
                                    <div class='col-6' id='viewMinimumBalance'>$0.00</div>
                                </div>
                                <div class='row mb-2'>
                                    <div class='col-6'><strong>Growth This Month:</strong></div>
                                    <div class='col-6'>
                                        <span id='viewGrowthAmount' class='fw-bold'>$0.00</span>
                                        <small class='text-muted'>(<span id='viewGrowthPercent'>0%</span>)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Information Card -->
                    <div class='col-md-6 mb-3'>
                        <div class='card h-100'>
                            <div class='card-header bg-body-tertiary'>
                                <h6 class='mb-0'><i class='fas fa-university me-2'></i>Bank Information</h6>
                            </div>
                            <div class='card-body'>
                                <div class='row mb-2'>
                                    <div class='col-5'><strong>Bank Name:</strong></div>
                                    <div class='col-7' id='viewBankName'>-</div>
                                </div>
                                <div class='row mb-2'>
                                    <div class='col-5'><strong>Account Number:</strong></div>
                                    <div class='col-7' id='viewAccountNumber'>-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes Card -->
                    <div class='col-md-6 mb-3'>
                        <div class='card h-100'>
                            <div class='card-header bg-body-tertiary'>
                                <h6 class='mb-0'><i class='fas fa-sticky-note me-2'></i>Notes</h6>
                            </div>
                            <div class='card-body'>
                                <div id='viewAccountNotes' class='text-muted'>No notes available</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Balance History Section -->
                <div class='card mt-3'>
                    <div class='card-header bg-body-tertiary'>
                        <h6 class='mb-0'><i class='fas fa-history me-2'></i>Recent Balance History</h6>
                    </div>
                    <div class='card-body p-0'>
                        <div class='table-responsive scrollbar'>
                            <table class='table table-sm mb-0 border-200 table-border'>
                                <thead class='bg-200'>
                                <tr>
                                    <th>Month</th>
                                    <th>Balance</th>
                                    <th>Growth</th>
                                    <th>Growth %</th>
                                    <th>Notes</th>
                                </tr>
                                </thead>
                                <tbody id='viewBalanceHistory'>
                                <tr>
                                    <td colspan='5' class='text-center text-muted'>Loading balance history...</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                    <i class='fas fa-times'></i> Close
                </button>
                <button type='button' class='btn btn-primary' onclick='editAccountFromView()'>
                    <i class='fas fa-edit'></i> Edit Account
                </button>
                <button type='button' class='btn btn-success' onclick='viewBalanceHistoryModal()'>
                    <i class='fas fa-chart-line'></i> View Full History
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Account Modal -->
<div class='modal fade' id='editAccountModal' tabindex='-1'>
    <div class='modal-dialog modal-lg'>
        <div class='modal-content'>
            <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                <div class='position-relative z-1'>
                    <h4 class='mb-0 text-white'>Edit Account Details</h4>
                </div>
                <div data-bs-theme='dark'>
                    <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
            </div>
            <div class='modal-body'>
                <form id='editAccountForm'>
                    <input type='hidden' id='editAccountId'>

                    <div class='row'>
                        <!-- Basic Information -->
                        <div class='col-md-6 mb-3'>
                            <label for='editAccountName' class='form-label'>
                                <i class='fas fa-tag text-primary'></i> Account Name *
                            </label>
                            <input type='text' class='form-control' id='editAccountName' required maxlength='100'>
                        </div>

                        <div class='col-md-6 mb-3'>
                            <label for='editAccountType' class='form-label'>
                                <i class='fas fa-list text-primary'></i> Account Type *
                            </label>
                            <select class='form-select' id='editAccountType' required>
                                <option value=''>Select Account Type</option>
                            </select>
                        </div>

                        <div class='col-md-6 mb-3'>
                            <label for='editAccountCurrency' class='form-label'>
                                <i class='fas fa-coins text-primary'></i> Currency *
                            </label>
                            <select class='form-select' id='editAccountCurrency' required>
                                <option value=''>Select Currency</option>
                            </select>
                            <div class='form-text'>Changing this does not convert past balance history — it only changes how new entries are recorded.</div>
                        </div>

                        <!-- Bank Information -->
                        <div class='col-md-6 mb-3'>
                            <label for='editBankName' class='form-label'>
                                <i class='fas fa-university text-primary'></i> Bank Name
                            </label>
                            <input type='text' class='form-control' id='editBankName' maxlength='100'>
                        </div>

                        <div class='col-md-6 mb-3'>
                            <label for='editAccountNumber' class='form-label'>
                                <i class='fas fa-hashtag text-primary'></i> Account Number
                            </label>
                            <input type='text' class='form-control' id='editAccountNumber' maxlength='50'>
                        </div>

                        <!-- Financial Information -->
                        <div class='col-md-6 mb-3'>
                            <label for='editInterestRate' class='form-label'>
                                <i class='fas fa-percentage text-success'></i> Interest Rate (%)
                            </label>
                            <div class='input-group'>
                                <input type='number' step='0.01' class='form-control' id='editInterestRate' min='0'
                                       max='100'>
                                <span class='input-group-text'>%</span>
                            </div>
                        </div>

                        <div class='col-md-6 mb-3'>
                            <label for='editMinimumBalance' class='form-label'>
                                <i class='fas fa-exclamation-triangle text-warning'></i> Minimum Balance
                            </label>
                            <div class='input-group'>
                                <span class='input-group-text' id='editMinimumBalanceCurrencyPrefix'>$</span>
                                <input type='number' step='0.01' class='form-control' id='editMinimumBalance'
                                       min='0'>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class='col-md-6 mb-3'>
                            <label for='editAccountStatus' class='form-label'>
                                <i class='fas fa-toggle-on text-success'></i> Status
                            </label>
                            <select class='form-select' id='editAccountStatus'>
                                <option value='Active'>Active</option>
                                <option value='Inactive'>Inactive</option>
                                <option value='Locked'>Locked</option>
                                <option value='Low Balance'>Low Balance</option>
                                <option value='Debt'>Debt</option>
                            </select>
                        </div>

                        <!-- Notes -->
                        <div class='col-12 mb-3'>
                            <label for='editAccountNotes' class='form-label'>
                                <i class='fas fa-sticky-note text-info'></i> Notes
                            </label>
                            <textarea class='form-control' id='editAccountNotes' rows='3'
                                      maxlength='500'></textarea>
                        </div>
                    </div>

                    <!-- Current Balance Info (Read-only) -->
                    <div class='card border-0 mt-3'>
                        <div class='card-body py-2'>
                            <h6 class='card-title mb-2'>
                                <i class='fas fa-info-circle text-info'></i> Current Balance Information
                            </h6>
                            <div class='row'>
                                <div class='col-md-6'>
                                    <small class='text-muted'>Current Balance:</small>
                                    <div class='fw-bold text-success' id='editCurrentBalanceDisplay'>$0.00</div>
                                </div>
                                <div class='col-md-6'>
                                    <small class='text-muted'>Last Balance Update:</small>
                                    <div class='fw-bold' id='editLastBalanceUpdate'>-</div>
                                </div>
                            </div>
                            <div class='alert alert-info mt-2 mb-0'>
                                <small><i class='fas fa-info-circle'></i> To update the balance, use the 'Update
                                    Balances' feature from the main dashboard.</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                    <i class='fas fa-times'></i> Cancel
                </button>
                <button type='button' class='btn btn-danger me-auto' onclick='deleteAccountFromEdit()'>
                    <i class='fas fa-trash'></i> Delete Account
                </button>
                <button type='button' class='btn btn-primary' onclick='saveAccountChanges()'>
                    <i class='fas fa-save'></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Confirmation Modal -->
<div class='modal fade' id='deleteAccountModal' tabindex='-1' data-bs-backdrop='static' data-bs-keyboard='false'>
    <div class='modal-dialog modal-dialog-centered'>
        <div class='modal-content'>
            <div class='modal-header bg-danger text-white'>
                <div class='d-flex align-items-center'>
                    <i class='fas fa-exclamation-triangle fa-2x me-3'></i>
                    <div>
                        <h5 class='mb-0'>Delete Account</h5>
                        <small class='opacity-75'>This action is permanent</small>
                    </div>
                </div>
                <button type='button' class='btn-close btn-close-white' data-bs-dismiss='modal' aria-label='Close'></button>
            </div>
            <div class='modal-body'>
                <!-- Warning Alert -->
                <div class='alert alert-danger d-flex align-items-start mb-4'>
                    <i class='fas fa-radiation-alt fa-lg me-3 mt-1'></i>
                    <div>
                        <strong>Warning:</strong> You are about to permanently delete a financial account. This action cannot be undone.
                    </div>
                </div>

                <!-- Account Summary -->
                <div class='card border-danger mb-4'>
                    <div class='card-header bg-danger bg-opacity-10'>
                        <h6 class='mb-0 text-danger'><i class='fas fa-university me-2'></i>Account to be Deleted</h6>
                    </div>
                    <div class='card-body'>
                        <div class='row'>
                            <div class='col-6 mb-2'>
                                <small class='text-muted d-block'>Account Name</small>
                                <strong id='deleteAccountName' class='text-danger'>-</strong>
                            </div>
                            <div class='col-6 mb-2'>
                                <small class='text-muted d-block'>Account Type</small>
                                <span id='deleteAccountType'>-</span>
                            </div>
                            <div class='col-6 mb-2'>
                                <small class='text-muted d-block'>Current Balance</small>
                                <strong id='deleteAccountBalance' class='text-success'>$0.00</strong>
                            </div>
                            <div class='col-6 mb-2'>
                                <small class='text-muted d-block'>Bank/Institution</small>
                                <span id='deleteAccountBank'>-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- What Will Be Deleted -->
                <div class='mb-4'>
                    <h6 class='text-danger mb-2'><i class='fas fa-trash-alt me-2'></i>What will be permanently deleted:</h6>
                    <ul class='list-unstyled ms-4'>
                        <li class='mb-1'><i class='fas fa-times text-danger me-2'></i>All account information</li>
                        <li class='mb-1'><i class='fas fa-times text-danger me-2'></i>Complete balance history (<span id='deleteHistoryCount'>0</span> records)</li>
                        <li class='mb-1'><i class='fas fa-times text-danger me-2'></i>All associated notes and metadata</li>
                        <li class='mb-1'><i class='fas fa-times text-danger me-2'></i>Growth tracking data</li>
                    </ul>
                </div>

                <!-- Confirmation Input -->
                <div class='mb-3'>
                    <label for='deleteConfirmInput' class='form-label'>
                        <i class='fas fa-keyboard me-2'></i>To confirm deletion, type the account name exactly:
                    </label>
                    <div class='input-group'>
                            <span class='input-group-text bg-danger text-white'>
                                <i class='fas fa-shield-alt'></i>
                            </span>
                        <input type='text' class='form-control' id='deleteConfirmInput'
                               placeholder='Type account name here...' autocomplete='off' spellcheck='false'>
                    </div>
                    <div class='form-text'>
                        <span class='text-muted'>Account name to type: </span>
                        <code id='deleteAccountNameHint' class='text-danger user-select-all'>-</code>
                        <button type='button' class='btn btn-link btn-sm p-0 ms-2' onclick='copyAccountNameToClipboard()' title='Copy to clipboard'>
                            <i class='fas fa-copy'></i>
                        </button>
                    </div>
                    <div id='deleteConfirmFeedback' class='invalid-feedback'>
                        Account name does not match. Please type it exactly as shown.
                    </div>
                </div>

                <!-- Additional Confirmation Checkbox -->
                <div class='form-check mb-3'>
                    <input class='form-check-input' type='checkbox' id='deleteUnderstandCheckbox'>
                    <label class='form-check-label' for='deleteUnderstandCheckbox'>
                        I understand that this action is <strong>irreversible</strong> and all data will be permanently lost.
                    </label>
                </div>

                <!-- Optional: Reason for Deletion -->
                <div class='mb-3'>
                    <label for='deleteReason' class='form-label'>
                        <i class='fas fa-comment-alt me-2'></i>Reason for deletion (optional)
                    </label>
                    <select class='form-select' id='deleteReason'>
                        <option value=''>Select a reason...</option>
                        <option value='Account closed'>Account closed at institution</option>
                        <option value='Duplicate entry'>Duplicate entry</option>
                        <option value='No longer needed'>No longer need to track</option>
                        <option value='Transferred elsewhere'>Funds transferred elsewhere</option>
                        <option value='Data cleanup'>General data cleanup</option>
                        <option value='Other'>Other</option>
                    </select>
                </div>

                <!-- Download History Before Delete Option -->
                <div class='alert alert-info d-flex align-items-center mb-0'>
                    <i class='fas fa-download fa-lg me-3'></i>
                    <div class='flex-grow-1'>
                        <strong>Recommended:</strong> Download your balance history before deleting.
                    </div>
                    <button type='button' class='btn btn-outline-info btn-sm ms-3' onclick='downloadBeforeDelete()'>
                        <i class='fas fa-file-csv me-1'></i>Export
                    </button>
                </div>
            </div>
            <div class='modal-footer bg-light'>
                <div class='d-flex w-100 justify-content-between align-items-center'>
                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal' onclick='cancelDeleteAccount()'>
                        <i class='fas fa-arrow-left me-1'></i> Cancel
                    </button>
                    <div class='text-end'>
                        <small class='text-muted d-block mb-1' id='deleteCountdown'></small>
                        <button type='button' class='btn btn-danger' id='confirmDeleteBtn' onclick='confirmDeleteAccount()' disabled>
                            <i class='fas fa-trash-alt me-1'></i> Permanently Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Balance History Modal -->
<div class='modal fade' id='balanceHistoryModal' tabindex='-1'>
    <div class='modal-dialog modal-xl'>
        <div class='modal-content'>
            <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                <div class='position-relative z-1'>
                    <h4 class='mb-0 text-white'>Balance History - <span id='historyAccountName'></span></h4>
                </div>
                <div data-bs-theme='dark'>
                    <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
            </div>
            <div class='modal-body'>
                <!-- Balance Chart -->
                <div class='card bg-line-chart-gradient mb-3'>
                    <div class='card-header'>
                        <h6 class='mb-0'><i class='fas fa-chart-area me-2'></i>Balance Trend</h6>
                    </div>
                    <div class='card-body'>
                        <div class='chart-container'>
                            <div id='accountBalanceChart'></div>
                        </div>
                    </div>
                </div>

                <!-- Balance History Table -->
                <div class='card'>
                    <div class='card-header d-flex justify-content-between align-items-center'>
                        <h6 class='mb-0'><i class='fas fa-table me-2'></i>Detailed History</h6>
                        <button class='btn btn-sm btn-outline-primary' onclick='exportBalanceHistory()'>
                            <i class='fas fa-download'></i> Export
                        </button>
                    </div>
                    <div class='card-body p-0'>
                        <div class='table-responsive'>
                            <table class='table table-hover mb-0'>
                                <thead class='table-dark'>
                                <tr>
                                    <th>Month/Year</th>
                                    <th>Balance</th>
                                    <th>Growth Amount</th>
                                    <th>Growth %</th>
                                    <th>Notes</th>
                                    <th>Date Added</th>
                                </tr>
                                </thead>
                                <tbody id='fullBalanceHistory'>
                                <tr>
                                    <td colspan='6' class='text-center'>Loading balance history...</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                    <i class='fas fa-times'></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Savings Breakdown Modal -->
<div class='modal fade' id='savingsBreakdownModal' tabindex='-1'>
    <div class='modal-dialog modal-xl'>
        <div class='modal-content'>
            <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                <div class='position-relative z-1'>
                    <h4 class='mb-0 text-white'><i class='fas fa-piggy-bank me-2'></i>Savings Breakdown - <span id='savingsBreakdownMonth'></span></h4>
                </div>
                <div data-bs-theme='dark'>
                    <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
            </div>
            <div class='modal-body'>
                <!-- Loading State -->
                <div id='savingsBreakdownLoading' class='text-center py-5'>
                    <div class='spinner-border text-primary' role='status'>
                        <span class='visually-hidden'>Loading...</span>
                    </div>
                    <p class='mt-3 text-muted'>Loading savings breakdown...</p>
                </div>

                <!-- Content Container -->
                <div id='savingsBreakdownContent' style='display: none;'>
                    <!-- Summary Cards -->
                    <div class='row mb-4'>
                        <div class='col-md-3'>
                            <div class='card bg-success bg-opacity-10 border-success'>
                                <div class='card-body text-center py-3'>
                                    <h6 class='text-success mb-1'>Total Savings Growth</h6>
                                    <h4 class='mb-0' id='totalSavingsGrowth'>$0</h4>
                                </div>
                            </div>
                        </div>
                        <div class='col-md-3'>
                            <div class='card bg-primary bg-opacity-10 border-primary'>
                                <div class='card-body text-center py-3'>
                                    <h6 class='text-primary mb-1'>Current Total Balance</h6>
                                    <h4 class='mb-0' id='totalCurrentSavings'>$0</h4>
                                </div>
                            </div>
                        </div>
                        <div class='col-md-3'>
                            <div class='card bg-secondary bg-opacity-10 border-secondary'>
                                <div class='card-body text-center py-3'>
                                    <h6 class='text-secondary mb-1'>Previous Total Balance</h6>
                                    <h4 class='mb-0' id='totalPreviousSavings'>$0</h4>
                                </div>
                            </div>
                        </div>
                        <div class='col-md-3'>
                            <div class='card bg-info bg-opacity-10 border-info'>
                                <div class='card-body text-center py-3'>
                                    <h6 class='text-info mb-1'>Growth Rate</h6>
                                    <h4 class='mb-0' id='savingsGrowthRate'>0%</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Type Summary -->
                    <div class='card mb-4'>
                        <div class='card-header bg-body-tertiary'>
                            <h6 class='mb-0'><i class='fas fa-layer-group me-2'></i>Growth by Account Type</h6>
                        </div>
                        <div class='card-body p-0'>
                            <div class='table-responsive'>
                                <table class='table table-hover mb-0'>
                                    <thead class='table-light'>
                                    <tr>
                                        <th>Account Type</th>
                                        <th class='text-end'>Accounts</th>
                                        <th class='text-end'>Previous Balance</th>
                                        <th class='text-end'>Current Balance</th>
                                        <th class='text-end'>Growth Amount</th>
                                    </tr>
                                    </thead>
                                    <tbody id='savingsTypeSummaryBody'>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Individual Accounts -->
                    <div class='card'>
                        <div class='card-header bg-body-tertiary d-flex justify-content-between align-items-center'>
                            <h6 class='mb-0'><i class='fas fa-list-ul me-2'></i>Individual Account Breakdown</h6>
                            <span class='badge bg-primary' id='savingsAccountCount'>0 accounts</span>
                        </div>
                        <div class='card-body p-0'>
                            <div class='table-responsive'>
                                <table class='table table-hover mb-0'>
                                    <thead class='table-light'>
                                    <tr>
                                        <th>Account Name</th>
                                        <th>Type</th>
                                        <th class='text-end'>Previous Balance</th>
                                        <th class='text-end'>Current Balance</th>
                                        <th class='text-end'>Growth Amount</th>
                                        <th class='text-end'>Growth %</th>
                                    </tr>
                                    </thead>
                                    <tbody id='savingsBreakdownBody'>
                                    </tbody>
                                    <tfoot class='table-secondary fw-bold' id='savingsBreakdownFooter'>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Comparison Period Note -->
                    <div class='alert alert-info mt-3 mb-0'>
                        <i class='fas fa-info-circle me-2'></i>
                        <span id='comparisonPeriodNote'>Comparing current month to previous month</span>
                    </div>
                </div>

                <!-- No Data State -->
                <div id='savingsBreakdownNoData' class='text-center py-5' style='display: none;'>
                    <i class='fas fa-piggy-bank fa-4x text-muted mb-3'></i>
                    <h5>No Savings Data Available</h5>
                    <p class='text-muted'>There is no savings data recorded yet. Add balance records for Savings, MMF, or Sacco accounts to see the breakdown.</p>
                </div>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-outline-primary' onclick='exportSavingsBreakdown()'>
                    <i class='fas fa-download me-1'></i> Export
                </button>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                    <i class='fas fa-times'></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Total Balance Breakdown Modal -->
<div class='modal fade' id='totalBalanceModal' tabindex='-1'>
    <div class='modal-dialog modal-xl'>
        <div class='modal-content'>
            <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                <div class='position-relative z-1'>
                    <h4 class='mb-0 text-white'><i class='fas fa-wallet me-2'></i>Total Balance Breakdown</h4>
                </div>
                <div data-bs-theme='dark'>
                    <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
            </div>
            <div class='modal-body'>
                <!-- Loading State -->
                <div id='totalBalanceLoading' class='text-center py-5'>
                    <div class='spinner-border text-primary' role='status'>
                        <span class='visually-hidden'>Loading...</span>
                    </div>
                    <p class='mt-3 text-muted'>Loading balance breakdown...</p>
                </div>

                <!-- Content Container -->
                <div id='totalBalanceContent' style='display: none;'>
                    <!-- Month Selection Tabs -->
                    <ul class='nav nav-tabs mb-4' id='balanceMonthTabs' role='tablist'>
                        <li class='nav-item' role='presentation'>
                            <button class='nav-link active' id='currentMonth-tab' data-bs-toggle='tab' data-bs-target='#currentMonthPane' type='button' role='tab'>
                                <i class='fas fa-calendar-day me-1'></i> <span id='currentMonthTabLabel'>Current Month</span>
                            </button>
                        </li>
                        <li class='nav-item' role='presentation'>
                            <button class='nav-link' id='previousMonth-tab' data-bs-toggle='tab' data-bs-target='#previousMonthPane' type='button' role='tab'>
                                <i class='fas fa-calendar-minus me-1'></i> <span id='previousMonthTabLabel'>Previous Month</span>
                            </button>
                        </li>
                        <li class='nav-item' role='presentation'>
                            <button class='nav-link' id='comparison-tab' data-bs-toggle='tab' data-bs-target='#comparisonPane' type='button' role='tab'>
                                <i class='fas fa-exchange-alt me-1'></i> Comparison
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class='tab-content' id='balanceMonthTabsContent'>
                        <!-- Current Month Tab -->
                        <div class='tab-pane fade show active' id='currentMonthPane' role='tabpanel'>
                            <!-- Summary Cards -->
                            <div class='row mb-4'>
                                <div class='col-md-4'>
                                    <div class='card bg-primary bg-opacity-10 border-primary'>
                                        <div class='card-body text-center py-3'>
                                            <h6 class='text-primary mb-1'>Total Balance</h6>
                                            <h4 class='mb-0' id='currentMonthTotal'>$0</h4>
                                        </div>
                                    </div>
                                </div>
                                <div class='col-md-4'>
                                    <div class='card bg-success bg-opacity-10 border-success'>
                                        <div class='card-body text-center py-3'>
                                            <h6 class='text-success mb-1'>Active Accounts Total</h6>
                                            <h4 class='mb-0' id='currentMonthActiveTotal'>$0</h4>
                                        </div>
                                    </div>
                                </div>
                                <div class='col-md-4'>
                                    <div class='card bg-secondary bg-opacity-10 border-secondary'>
                                        <div class='card-body text-center py-3'>
                                            <h6 class='text-secondary mb-1'>Inactive/Other Accounts</h6>
                                            <h4 class='mb-0' id='currentMonthInactiveTotal'>$0</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Current Month Table -->
                            <div class='card'>
                                <div class='card-header bg-body-tertiary'>
                                    <h6 class='mb-0'><i class='fas fa-list me-2'></i>All Accounts - <span id='currentMonthTableTitle'>Current Month</span></h6>
                                </div>
                                <div class='card-body p-0'>
                                    <div class='table-responsive'>
                                        <table class='table table-hover mb-0'>
                                            <thead class='table-light'>
                                            <tr>
                                                <th>Account Name</th>
                                                <th>Type</th>
                                                <th>Bank</th>
                                                <th>Status</th>
                                                <th class='text-end'>Balance</th>
                                                <th class='text-end'>% of Total</th>
                                            </tr>
                                            </thead>
                                            <tbody id='currentMonthTableBody'>
                                            </tbody>
                                            <tfoot class='table-secondary fw-bold' id='currentMonthTableFooter'>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Previous Month Tab -->
                        <div class='tab-pane fade' id='previousMonthPane' role='tabpanel'>
                            <!-- Summary Cards -->
                            <div class='row mb-4'>
                                <div class='col-md-4'>
                                    <div class='card bg-primary bg-opacity-10 border-primary'>
                                        <div class='card-body text-center py-3'>
                                            <h6 class='text-primary mb-1'>Total Balance</h6>
                                            <h4 class='mb-0' id='previousMonthTotal'>$0</h4>
                                        </div>
                                    </div>
                                </div>
                                <div class='col-md-4'>
                                    <div class='card bg-success bg-opacity-10 border-success'>
                                        <div class='card-body text-center py-3'>
                                            <h6 class='text-success mb-1'>Active Accounts Total</h6>
                                            <h4 class='mb-0' id='previousMonthActiveTotal'>$0</h4>
                                        </div>
                                    </div>
                                </div>
                                <div class='col-md-4'>
                                    <div class='card bg-secondary bg-opacity-10 border-secondary'>
                                        <div class='card-body text-center py-3'>
                                            <h6 class='text-secondary mb-1'>Inactive/Other Accounts</h6>
                                            <h4 class='mb-0' id='previousMonthInactiveTotal'>$0</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Previous Month Table -->
                            <div class='card'>
                                <div class='card-header bg-body-tertiary'>
                                    <h6 class='mb-0'><i class='fas fa-list me-2'></i>All Accounts - <span id='previousMonthTableTitle'>Previous Month</span></h6>
                                </div>
                                <div class='card-body p-0'>
                                    <div class='table-responsive'>
                                        <table class='table table-hover mb-0'>
                                            <thead class='table-light'>
                                            <tr>
                                                <th>Account Name</th>
                                                <th>Type</th>
                                                <th>Bank</th>
                                                <th>Status</th>
                                                <th class='text-end'>Balance</th>
                                                <th class='text-end'>% of Total</th>
                                            </tr>
                                            </thead>
                                            <tbody id='previousMonthTableBody'>
                                            </tbody>
                                            <tfoot class='table-secondary fw-bold' id='previousMonthTableFooter'>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Comparison Tab -->
                        <div class='tab-pane fade' id='comparisonPane' role='tabpanel'>
                            <!-- Comparison Summary Cards -->
                            <div class='row mb-4'>
                                <div class='col-md-3'>
                                    <div class='card bg-info bg-opacity-10 border-info'>
                                        <div class='card-body text-center py-3'>
                                            <h6 class='text-info mb-1'>Previous Total</h6>
                                            <h4 class='mb-0' id='compPreviousTotal'>$0</h4>
                                        </div>
                                    </div>
                                </div>
                                <div class='col-md-3'>
                                    <div class='card bg-primary bg-opacity-10 border-primary'>
                                        <div class='card-body text-center py-3'>
                                            <h6 class='text-primary mb-1'>Current Total</h6>
                                            <h4 class='mb-0' id='compCurrentTotal'>$0</h4>
                                        </div>
                                    </div>
                                </div>
                                <div class='col-md-3'>
                                    <div class='card bg-success bg-opacity-10 border-success'>
                                        <div class='card-body text-center py-3'>
                                            <h6 class='text-success mb-1'>Net Change</h6>
                                            <h4 class='mb-0' id='compNetChange'>$0</h4>
                                        </div>
                                    </div>
                                </div>
                                <div class='col-md-3'>
                                    <div class='card bg-warning bg-opacity-10 border-warning'>
                                        <div class='card-body text-center py-3'>
                                            <h6 class='text-warning mb-1'>Growth Rate</h6>
                                            <h4 class='mb-0' id='compGrowthRate'>0%</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Comparison Table -->
                            <div class='card'>
                                <div class='card-header bg-body-tertiary'>
                                    <h6 class='mb-0'><i class='fas fa-balance-scale me-2'></i>Month-over-Month Comparison (All Accounts)</h6>
                                </div>
                                <div class='card-body p-0'>
                                    <div class='table-responsive'>
                                        <table class='table table-hover mb-0'>
                                            <thead class='table-light'>
                                            <tr>
                                                <th>Account Name</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th class='text-end'>Previous</th>
                                                <th class='text-end'>Current</th>
                                                <th class='text-end'>Change</th>
                                                <th class='text-end'>Change %</th>
                                            </tr>
                                            </thead>
                                            <tbody id='comparisonTableBody'>
                                            </tbody>
                                            <tfoot class='table-secondary fw-bold' id='comparisonTableFooter'>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Data State -->
                <div id='totalBalanceNoData' class='text-center py-5' style='display: none;'>
                    <i class='fas fa-wallet fa-4x text-muted mb-3'></i>
                    <h5>No Balance Data Available</h5>
                    <p class='text-muted'>There is no balance data recorded yet. Add balance records to see the breakdown.</p>
                </div>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-outline-primary' onclick='exportTotalBalanceBreakdown()'>
                    <i class='fas fa-download me-1'></i> Export
                </button>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                    <i class='fas fa-times'></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Latest Month Details Modal -->
<div class='modal fade' id='latestMonthModal' tabindex='-1'>
    <div class='modal-dialog modal-xl'>
        <div class='modal-content'>
            <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                <div class='position-relative z-1'>
                    <h4 class='mb-0 text-white'><i class='fas fa-calendar-alt me-2'></i>Month Overview - <span id='latestMonthModalTitle'>Loading...</span></h4>
                </div>
                <div data-bs-theme='dark'>
                    <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
            </div>
            <div class='modal-body'>
                <!-- Loading State -->
                <div id='latestMonthLoading' class='text-center py-5'>
                    <div class='spinner-border text-primary' role='status'>
                        <span class='visually-hidden'>Loading...</span>
                    </div>
                    <p class='mt-3 text-muted'>Loading month details...</p>
                </div>

                <!-- Content Container -->
                <div id='latestMonthContent' style='display: none;'>
                    <!-- Key Metrics Row -->
                    <div class='row mb-4'>
                        <div class='col-md-3'>
                            <div class='card bg-primary bg-opacity-10 border-primary h-100'>
                                <div class='card-body text-center py-3'>
                                    <i class='fas fa-wallet fa-2x text-primary mb-2'></i>
                                    <h6 class='text-primary mb-1'>Total Balance</h6>
                                    <h4 class='mb-0' id='lmTotalBalance'>$0</h4>
                                </div>
                            </div>
                        </div>
                        <div class='col-md-3'>
                            <div class='card bg-success bg-opacity-10 border-success h-100'>
                                <div class='card-body text-center py-3'>
                                    <i class='fas fa-chart-line fa-2x text-success mb-2'></i>
                                    <h6 class='text-success mb-1'>Total Growth</h6>
                                    <h4 class='mb-0' id='lmTotalGrowth'>$0</h4>
                                </div>
                            </div>
                        </div>
                        <div class='col-md-3'>
                            <div class='card bg-info bg-opacity-10 border-info h-100'>
                                <div class='card-body text-center py-3'>
                                    <i class='fas fa-check-circle fa-2x text-info mb-2'></i>
                                    <h6 class='text-info mb-1'>Accounts Updated</h6>
                                    <h4 class='mb-0' id='lmAccountsUpdated'>0</h4>
                                </div>
                            </div>
                        </div>
                        <div class='col-md-3'>
                            <div class='card bg-warning bg-opacity-10 border-warning h-100'>
                                <div class='card-body text-center py-3'>
                                    <i class='fas fa-exclamation-circle fa-2x text-warning mb-2'></i>
                                    <h6 class='text-warning mb-1'>Pending Updates</h6>
                                    <h4 class='mb-0' id='lmPendingUpdates'>0</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Completion Progress -->
                    <div class='card mb-4'>
                        <div class='card-header bg-body-tertiary'>
                            <h6 class='mb-0'><i class='fas fa-tasks me-2'></i>Data Completion Status</h6>
                        </div>
                        <div class='card-body'>
                            <div class='row align-items-center'>
                                <div class='col-md-8'>
                                    <div class='progress' style='height: 25px;'>
                                        <div class='progress-bar bg-success' id='lmProgressBar' role='progressbar' style='width: 0%'>
                                            <span id='lmProgressText'>0%</span>
                                        </div>
                                    </div>
                                </div>
                                <div class='col-md-4 text-end'>
                                    <span id='lmProgressDetails' class='text-muted'>0 of 0 accounts</span>
                                </div>
                            </div>
                            <div class='mt-3' id='lmDataFreshness'>
                                <span class='badge bg-success'><i class='fas fa-clock me-1'></i>Data is current</span>
                            </div>
                        </div>
                    </div>

                    <!-- Distribution by Type -->
                    <div class='row mb-4'>
                        <div class='col-md-6'>
                            <div class='card h-100'>
                                <div class='card-header bg-body-tertiary'>
                                    <h6 class='mb-0'><i class='fas fa-chart-pie me-2'></i>Balance by Account Type</h6>
                                </div>
                                <div class='card-body p-0'>
                                    <div class='table-responsive'>
                                        <table class='table table-hover mb-0'>
                                            <thead class='table-light'>
                                            <tr>
                                                <th>Type</th>
                                                <th class='text-end'>Balance</th>
                                                <th class='text-end'>Growth</th>
                                                <th class='text-end'>%</th>
                                            </tr>
                                            </thead>
                                            <tbody id='lmTypeBreakdownBody'>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class='col-md-6'>
                            <div class='card h-100'>
                                <div class='card-header bg-body-tertiary'>
                                    <h6 class='mb-0'><i class='fas fa-trophy me-2'></i>Top Performers</h6>
                                </div>
                                <div class='card-body p-0'>
                                    <div class='table-responsive'>
                                        <table class='table table-hover mb-0'>
                                            <thead class='table-light'>
                                            <tr>
                                                <th>Account</th>
                                                <th class='text-end'>Growth</th>
                                                <th class='text-end'>Growth %</th>
                                            </tr>
                                            </thead>
                                            <tbody id='lmTopPerformersBody'>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Accounts Needing Attention -->
                    <div class='card mb-4' id='lmNeedsAttentionCard' style='display: none;'>
                        <div class='card-header bg-warning bg-opacity-25'>
                            <h6 class='mb-0 text-warning'><i class='fas fa-exclamation-triangle me-2'></i>Accounts Needing Attention</h6>
                        </div>
                        <div class='card-body p-0'>
                            <div class='table-responsive'>
                                <table class='table table-hover mb-0'>
                                    <thead class='table-light'>
                                    <tr>
                                        <th>Account Name</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Issue</th>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody id='lmNeedsAttentionBody'>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class='card'>
                        <div class='card-header bg-body-tertiary'>
                            <h6 class='mb-0'><i class='fas fa-bolt me-2'></i>Quick Actions</h6>
                        </div>
                        <div class='card-body'>
                            <div class='d-flex flex-wrap gap-2'>
                                <button class='btn btn-outline-primary' onclick='showUpdateBalanceModal(); bootstrap.Modal.getInstance(document.getElementById("latestMonthModal")).hide();'>
                                    <i class='fas fa-edit me-1'></i> Update Balances
                                </button>
                                <button class='btn btn-outline-success' onclick='showAddAccountModal(); bootstrap.Modal.getInstance(document.getElementById("latestMonthModal")).hide();'>
                                    <i class='fas fa-plus me-1'></i> Add New Account
                                </button>
                                <button class='btn btn-outline-info' onclick='showMonthViewer(); bootstrap.Modal.getInstance(document.getElementById("latestMonthModal")).hide();'>
                                    <i class='fas fa-calendar me-1'></i> View by Month
                                </button>
                                <button class='btn btn-outline-secondary' onclick='exportLatestMonthData()'>
                                    <i class='fas fa-download me-1'></i> Export Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Data State -->
                <div id='latestMonthNoData' class='text-center py-5' style='display: none;'>
                    <i class='fas fa-calendar-times fa-4x text-muted mb-3'></i>
                    <h5>No Data for This Month</h5>
                    <p class='text-muted'>Start by adding balance records for your accounts.</p>
                    <button class='btn btn-primary' onclick='showUpdateBalanceModal(); bootstrap.Modal.getInstance(document.getElementById("latestMonthModal")).hide();'>
                        <i class='fas fa-plus me-1'></i> Add Balance Records
                    </button>
                </div>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>
                    <i class='fas fa-times'></i> Close
                </button>
            </div>
        </div>
    </div>
</div>


<script>
    // Configuration - Update these URLs to match your API endpoints
    const API_BASE_URL = 'accounts_api';

    // Global variables
    let accountsData = [];
    let pieChartInstance, barChartInstance;
    let totalBalance = 0;
    let growthChart;
    let accountTypes = [];
    let currentAccount = null;
    let accountBalanceChart = null;
    let accountBalanceChartInstance;

    // ── Multi-currency state ─────────────────────────────────────────
    // `preferredCurrency` is what charts/totals convert INTO. It's seeded
    // from the admin's saved setting (tbl_user_settings via ?action=exchange_rates)
    // and can be overridden per-browser via the header dropdown (localStorage),
    // without touching the saved server-side preference.
    let preferredCurrency = 'USD';
    let exchangeRates = { USD: 1 };
    let currencyList = {}; // code => {name, symbol}
    let exchangeRatesLastUpdated = null;
    let exchangeRatesLastUpdatedDisplay = null;

    // Load currency config once, then populate every currency <select> on the page.
    async function loadCurrencyConfig() {
        try {
            const response = await fetch(`${API_BASE_URL}?action=exchange_rates`);
            const data = await response.json();

            if (data.success) {
                exchangeRates = data.rates || { USD: 1 };
                currencyList = data.currencies || {};
                exchangeRatesLastUpdated = data.last_updated || null;
                exchangeRatesLastUpdatedDisplay = data.last_updated_display || null;

                const saved = localStorage.getItem('itasker_display_currency');
                preferredCurrency = saved || data.default_currency || 'USD';
            }
        } catch (error) {
            console.error('Error loading currency config:', error);
        } finally {
            populateCurrencyDropdown('displayCurrencySelect', preferredCurrency);
            populateCurrencyDropdown('accountCurrency');
            populateCurrencyDropdown('editAccountCurrency');
        }
    }

    // Fill a <select> with the supported currency list.
    function populateCurrencyDropdown(selectId, selectedValue) {
        const select = document.getElementById(selectId);
        if (!select) return;

        const codes = Object.keys(currencyList).length ? currencyList : {
            USD: { name: 'US Dollar', symbol: '$' },
            KES: { name: 'Kenyan Shilling', symbol: 'KSh' },
            EUR: { name: 'Euro', symbol: '€' }
        };

        const placeholder = select.id === 'displayCurrencySelect' ? '' : '<option value="">Select Currency</option>';
        select.innerHTML = placeholder + Object.entries(codes).map(([code, info]) =>
            `<option value="${code}">${code} — ${info.name}</option>`
        ).join('');

        if (selectedValue) {
            select.value = selectedValue;
        }
    }

    function currencySymbol(code) {
        return (currencyList[code] && currencyList[code].symbol) || code || '$';
    }

    // Client-side mirror of the PHP convertCurrency() helper — converts an
    // amount from one currency to another by pivoting through USD, using
    // the same exchangeRates map (code => rate_to_usd) loaded at startup.
    // Used anywhere we sum figures across accounts that may be in
    // different currencies (e.g. bulk balance-update totals).
    function convertAmountClient(amount, fromCurrency, toCurrency) {
        const amt = parseFloat(amount) || 0;
        if (amt === 0 || fromCurrency === toCurrency) {
            return amt;
        }
        const fromRate = (exchangeRates[fromCurrency] && exchangeRates[fromCurrency] > 0) ? exchangeRates[fromCurrency] : 1;
        const toRate = (exchangeRates[toCurrency] && exchangeRates[toCurrency] > 0) ? exchangeRates[toCurrency] : 1;
        return (amt / fromRate) * toRate;
    }

    // Called when the header "display currency" dropdown changes.
    function changeDisplayCurrency(code) {
        preferredCurrency = code;
        localStorage.setItem('itasker_display_currency', code);
        // Only the aggregate views need to be reloaded — account-level
        // tables/details stay in each account's own native currency.
        loadSummaryData();
        loadDistributionData();
        loadGrowthData();
    }

    // Show the exchange rates breakdown modal: every supported currency
    // compared against the user's currently selected preferred currency.
    async function showExchangeRatesModal() {
        // Make sure we have the freshest rates before showing the table.
        await loadCurrencyConfig();
        // loadCurrencyConfig repopulates the account currency <select>s,
        // which is fine, but it also resets displayCurrencySelect's
        // options — make sure the current preference stays selected.
        populateCurrencyDropdown('displayCurrencySelect', preferredCurrency);

        const tbody = document.getElementById('exchangeRatesTableBody');
        const labelEls = ['exRatesPreferredLabel', 'exRatesPreferredLabel2', 'exRatesPreferredLabel3'];
        labelEls.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = preferredCurrency;
        });

        const preferredRateToUsd = exchangeRates[preferredCurrency] || 1;
        const codes = Object.keys(currencyList).length ? Object.keys(currencyList) : Object.keys(exchangeRates);

        if (!codes.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No exchange rate data available yet. Run the exchange rate cron job first.</td></tr>';
        } else {
            tbody.innerHTML = codes.map(code => {
                const rateToUsd = exchangeRates[code] || 1;
                const info = currencyList[code] || { name: code, symbol: code };

                // 1 unit of `code` expressed in the preferred currency:
                // convert code -> USD -> preferred
                const oneUnitInPreferred = (1 / rateToUsd) * preferredRateToUsd;
                // 1 unit of preferred expressed in `code`
                const oneUnitOfPreferredInCode = code === preferredCurrency ? 1 : (1 / preferredRateToUsd) * rateToUsd;

                const isPreferred = code === preferredCurrency;

                return `
                    <tr class="${isPreferred ? 'table-primary' : ''}">
                        <td>
                            <strong>${code}</strong> — ${info.name}
                            ${isPreferred ? '<span class="badge bg-primary ms-1">Preferred</span>' : ''}
                        </td>
                        <td class="text-end">${formatCurrency(oneUnitInPreferred, preferredCurrency)}</td>
                        <td class="text-end">${oneUnitOfPreferredInCode.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 4})} ${code}</td>
                        <td class="text-end text-muted">${rateToUsd.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 4})}</td>
                    </tr>
                `;
            }).join('');
        }

        const lastUpdatedEl = document.getElementById('exRatesLastUpdated');
        lastUpdatedEl.textContent = exchangeRatesLastUpdatedDisplay
            ? `Rates last refreshed: ${exchangeRatesLastUpdatedDisplay}`
            : 'Rates have not been refreshed yet — set up the daily cron job (cron/update_exchange_rates.php).';

        new bootstrap.Modal(document.getElementById('exchangeRatesModal')).show();
    }

    // Initialize dashboard on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadDashboardData();
    });

    // Load all dashboard data
    async function loadDashboardData() {
        try {
            await loadCurrencyConfig();
            await Promise.all([
                loadSummaryData(),
                loadAccountsData(),
                loadDistributionData(),
                loadGrowthData(),
                loadLatestMonthData()
            ]);
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            showToast('Failed to load dashboard data. Please check your API connection.', 'danger');
        }
    }

    function updateMonthlyGrowthDisplay(data) {
        const growthValue = parseFloat(data.monthly_growth) || 0;
        const growthAmount = parseFloat(data.growth_amount) || 0;
        const growthElement = document.getElementById('monthlyGrowth');

        // Format the display
        const growthSign = growthValue >= 0 ? '+' : '';
        const amountSign = growthAmount >= 0 ? '+' : '';

        // Update content
        growthElement.textContent = `${growthSign}${growthValue.toFixed(1)}% | ${amountSign}${formatCurrency(growthAmount, preferredCurrency)}`;

        // Reset classes and add base classes
        growthElement.className = 'badge rounded-pill fs-11';

        // Add conditional styling classes
        if (growthValue > 0) {
            growthElement.classList.add('badge-subtle-success');
        } else if (growthValue < 0) {
            growthElement.classList.add('badge-subtle-danger');
        } else {
            growthElement.classList.add('badge-subtle-secondary');
        }

        // Update tooltip
        growthElement.title = `Growth from previous month: ${amountSign}${formatCurrency(growthAmount, preferredCurrency)} (${growthSign}${growthValue.toFixed(2)}%)`;
    }

    // Load summary data for cards
    async function loadSummaryData() {
        try {
            const response = await fetch(`${API_BASE_URL}?action=summary&to=${preferredCurrency}`);
            const data = await response.json();

            document.getElementById('totalBalance').textContent = formatCurrency(data.total_balance, preferredCurrency);
            document.getElementById('activeAccounts').textContent = data.active_accounts + ' Active';
            document.getElementById('inactiveAccounts').textContent = data.inactive_accounts + ' Inactive';
            document.getElementById('monthlySavings').textContent = formatCurrency(data.monthly_savings || 0, preferredCurrency);
            document.getElementById('monthlySavings').className = (data.monthly_savings || 0) >= 0 ? 'mb-2 text-success' : 'mb-2 text-danger';

            // Update the title to show the specific month
            document.getElementById('savingsTitle').textContent = data.savings_month_display === 'No Data' ?
                'Monthly Savings' :
                data.savings_month_display + ' Savings';

            // Update the details to show account types
            document.getElementById('savingsDetails').textContent = 'Savings, MMF & Sacco';

            // Use the enhanced monthly growth display
            updateMonthlyGrowthDisplay(data);

            totalBalance = data.total_balance;
        } catch (error) {
            console.error('Error loading summary:', error);
            // Set fallback values on error
            document.getElementById('savingsTitle').textContent = 'Monthly Savings';
            document.getElementById('activeAccounts').textContent = 'Error';
            document.getElementById('inactiveAccounts').textContent = 'Error';
        }
    }

    // Load accounts data for table
    async function loadAccountsData() {
        try {
            const response = await fetch(`${API_BASE_URL}?to=${preferredCurrency}`);
            accountsData = await response.json();

            renderAccountsTable(accountsData);
            updateAccountCount(accountsData.length);
        } catch (error) {
            console.error('Error loading accounts:', error);
            document.getElementById('accountsTableBody').innerHTML =
                '<tr><td colspan="4" class="text-center text-danger">Error loading accounts</td></tr>';
        }
    }

    // Load distribution data for charts
    async function loadDistributionData() {
        try {
            const response = await fetch(`${API_BASE_URL}?action=distribution&to=${preferredCurrency}`);
            const data = await response.json();

            renderCharts(data);
        } catch (error) {
            console.error('Error loading distribution:', error);
        }
    }

    // Add this function to populate account types dropdown
    async function populateAccountTypesDropdown() {
        try {
            const response = await fetch(`${API_BASE_URL}?action=account_types`);
            const types = await response.json();

            const select = document.getElementById('accountType');
            select.innerHTML = '<option value="">Select Type</option>';

            types.forEach(type => {
                const option = document.createElement('option');
                option.value = type.type_name;
                option.textContent = type.type_name;
                option.style.color = type.color_code;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading account types:', error);
        }
    }

    // Update the showAddAccountModal function
    async function showAddAccountModal() {
        // Clear form
        document.getElementById('addAccountForm').reset();
        document.getElementById('accountSummaryCard').style.display = 'none';
        document.getElementById('accountStatus').value = 'Active';

        // form.reset() only clears inputs/selects — it doesn't touch these
        // <span> prefixes, so without this they'd keep showing whatever
        // currency symbol was left over from the last time this modal was
        // used, even though no currency is selected yet this time.
        document.getElementById('initialBalanceCurrencyPrefix').textContent = '$';
        document.getElementById('minimumBalanceCurrencyPrefix').textContent = '$';

        // Load account types
        await loadAccountTypesForModal();
        populateCurrencyDropdown('accountCurrency');

        // Add real-time preview functionality
        setupAccountPreview();

        // Show modal
        new bootstrap.Modal(document.getElementById('addAccountModal')).show();
    }
    // Load account types for the modal
    async function loadAccountTypesForModal() {
        try {
            const response = await fetch(`${API_BASE_URL}?action=account_types`);
            const accountTypes = await response.json();

            const select = document.getElementById('accountType');
            select.innerHTML = '<option value="">Select Account Type</option>';

            accountTypes.forEach(type => {
                const option = document.createElement('option');
                option.value = type.type_name;
                option.textContent = type.type_name;
                option.setAttribute('data-color', type.color_code);
                option.setAttribute('data-icon', type.icon_class);
                select.appendChild(option);
            });

        } catch (error) {
            console.error('Error loading account types:', error);
            showToast('Failed to load account types', 'danger');
        }
    }
    // Setup real-time account preview
    function setupAccountPreview() {
        const inputs = ['accountName', 'initialBalance', 'accountType', 'bankName', 'accountCurrency'];

        inputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            input.addEventListener('input', updateAccountPreview);
            input.addEventListener('change', updateAccountPreview);
        });

        document.getElementById('accountCurrency').onchange = function() {
            const symbol = currencySymbol(this.value) || '$';
            document.getElementById('initialBalanceCurrencyPrefix').textContent = symbol;
            document.getElementById('minimumBalanceCurrencyPrefix').textContent = symbol;
        };
    }
    // Update account preview in real-time
    function updateAccountPreview() {
        const accountName = document.getElementById('accountName').value;
        const initialBalance = parseFloat(document.getElementById('initialBalance').value) || 0;
        const accountType = document.getElementById('accountType').value;
        const bankName = document.getElementById('bankName').value;
        const currency = document.getElementById('accountCurrency').value || 'USD';

        const summaryCard = document.getElementById('accountSummaryCard');

        // Show preview if at least name and balance are provided
        if (accountName || initialBalance > 0) {
            summaryCard.style.display = 'block';

            document.getElementById('previewAccountName').textContent = accountName || 'Unnamed Account';
            document.getElementById('previewBalance').textContent = formatCurrency(initialBalance, currency);
            document.getElementById('previewAccountType').textContent = accountType || 'Not selected';
            document.getElementById('previewBank').textContent = bankName || 'Not specified';
        } else {
            summaryCard.style.display = 'none';
        }
    }

    // Render accounts table
    // Row click on the accounts table opens the account detail modal.
    // Buttons inside the row call event.stopPropagation() so this only
    // fires for clicks on the row itself (or non-interactive cells).
    function handleAccountRowClick(event, accountId) {
        if (event.target.closest('button, a, input, select, .dropdown-menu')) {
            return;
        }
        viewDetails(accountId);
    }

    function renderAccountsTable(accounts) {
        const tbody = document.getElementById('accountsTableBody');

        if (accounts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2"></i><div>No accounts found</div></td></tr>';
            return;
        }

        tbody.innerHTML = accounts.map(account => {
            const balanceClass = account.balance >= 0 ? 'balance-positive' : 'balance-negative';
            const statusBadge = getStatusBadge(account.status);
            const typeBadge = getTypeBadge(account.account_type, account.color_code, account.icon_class);
            const currencyBadge = account.currency
                ? `<span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" style="font-size:.65rem;">${account.currency}</span>`
                : '';

            // Alerts computed server-side (see getAccountAlerts in accounts_api.php)
            const alerts = account.alerts || [];
            const dormancyAlert = alerts.find(a => a.is_dormancy);
            const dormancyBadge = account.is_dormancy_risk
                ? `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" data-bs-toggle="tooltip" title="${(dormancyAlert ? dormancyAlert.message : '').replace(/"/g, '&quot;')}"><i class="fas fa-moon me-1"></i>Dormancy risk</span>`
                : '';
            const lowBalanceBadge = account.is_low_balance
                ? `<span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle" data-bs-toggle="tooltip" title="Below minimum balance"><i class="fas fa-battery-quarter me-1"></i>Low balance</span>`
                : '';

            return `
                    <tr class="account-row" style="cursor:pointer;" onclick="handleAccountRowClick(event, ${account.id})">
                        <td>
                            <strong>${account.account_name}</strong>
                            <div class="mt-1">${typeBadge}</div>
                        </td>
                        <td class="${balanceClass}"><strong>${formatCurrency(account.balance, account.currency)}</strong>${currencyBadge}</td>
                        <td>${account.last_balance_update ? formatBalanceDate(account.last_balance_update) : 'No balance data'}</td>
                        <td>
                            <div class="d-flex flex-column align-items-start gap-1">
                                ${statusBadge}
                                ${dormancyBadge}
                                ${lowBalanceBadge}
                            </div>
                        </td>
                    </tr>
                `;
        }).join('');
    }

    function formatBalanceDate(dateString) {
        if (!dateString) return 'No balance data';

        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            year: 'numeric'
        });
    }

    // Render charts
    function renderCharts(distributionData) {
        const labels = distributionData.map(item => item.account_type);
        const data = distributionData.map(item => parseFloat(item.total_balance));
        const colors = distributionData.map(item => item.color_code || '#007bff');
        const total = data.reduce((sum, val) => sum + val, 0);

        // Destroy existing ApexCharts instances
        if (pieChartInstance) pieChartInstance.destroy();
        if (barChartInstance) barChartInstance.destroy();

        // ==== PIE CHART: Account Type Distribution ====
        const pieOptions = {
            chart: {
                type: 'donut',
                height: 350,
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800,
                    animateGradually: {
                        enabled: true,
                        delay: 150
                    },
                    dynamicAnimation: {
                        enabled: true,
                        speed: 350
                    }
                },
                toolbar: {
                    show: false // we’re using custom buttons
                }
            },
            series: data,
            labels: labels,
            colors: colors,
            dataLabels: {
                enabled: true,
                formatter: (val) => val.toFixed(1) + '%',
                style: {colors: ['#fff']}
            },
            tooltip: {
                y: {
                    formatter: (val) => {
                        const percent = (val / total) * 100;
                        return `${formatCurrency(val, preferredCurrency)} (${percent.toFixed(1)}%)`;
                    }
                }
            },
            legend: {
                position: 'bottom',
                labels: {
                    colors: '#ffffff'
                }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                formatter: () => formatCurrency(total, preferredCurrency),
                                color: '#ffffff'
                            }
                        }
                    }
                }
            }
        };

        pieChartInstance = new ApexCharts(document.querySelector('#pieChart'), pieOptions);
        pieChartInstance.render();

        // === BAR CHART: Individual Active Accounts ===
        // Uses converted_balance (already in the display currency, computed
        // server-side) rather than the raw native `balance`, since accounts
        // can be in different currencies and a bar chart can't mix them.
        fetch(`accounts_api?to=${preferredCurrency}`)
            .then(response => response.json())
            .then(accounts => {
                const activeAccounts = accounts
                    .filter(account => account.status !== 'Inactive')
                    .sort((a, b) => (b.converted_balance ?? b.balance) - (a.converted_balance ?? a.balance));

                // Map account type to color
                const colorMap = {};
                distributionData.forEach(item => {
                    colorMap[item.account_type] = item.color_code || '#007bff';
                });

                const barData = activeAccounts.map(acc => ({
                    x: acc.account_name,
                    y: parseFloat(acc.converted_balance ?? acc.balance),
                    fillColor: colorMap[acc.account_type] || '#007bff'
                }));

                const totalBar = barData.reduce((sum, bar) => sum + bar.y, 0);

                const barOptions = {
                    chart: {
                        type: 'bar',
                        height: 400,
                        toolbar: { show: false },
                        animations: {
                            enabled: true,
                            easing: 'easeinout',
                            speed: 800,
                            animateGradually: {
                                enabled: true,
                                delay: 150
                            },
                            dynamicAnimation: {
                                enabled: true,
                                speed: 350
                            }
                        }
                    },
                    series: [{
                        name: `Balance (${preferredCurrency})`,
                        data: activeAccounts.map(acc => ({
                            x: acc.account_name,
                            y: parseFloat(acc.converted_balance ?? acc.balance),
                            fillColor: colorMap[acc.account_type] || '#007bff'
                        }))
                    }],
                    xaxis: {
                        labels: {
                            rotate: -45,
                            style: {colors: '#ffffff'}
                        }
                    },
                    yaxis: {
                        labels: {
                            formatter: val => formatCurrency(val, preferredCurrency),
                            style: {colors: '#ffffff'}
                        },
                        title: {
                            text: `Balance (${preferredCurrency})`,
                            style: {color: '#ffffff'}
                        }
                    },
                    plotOptions: {
                        bar: {
                            distributed: true,
                            borderRadius: 4,
                            columnWidth: '60%'
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    tooltip: {
                        y: {
                            formatter: (val, opts) => {
                                const percent = (val / totalBar) * 100;
                                const acc = activeAccounts[opts?.dataPointIndex];
                                const nativeNote = acc && acc.currency !== preferredCurrency
                                    ? ` [native: ${formatCurrency(acc.balance, acc.currency)}]`
                                    : '';
                                return `${formatCurrency(val, preferredCurrency)} (${percent.toFixed(1)}%)${nativeNote}`;
                            }
                        }
                    },
                    grid: {
                        borderColor: 'rgba(255,255,255,0.05)',
                        strokeDashArray: 4,
                        yaxis: {lines: {show: true}},
                        xaxis: {lines: {show: false}}
                    },
                    legend: {show: false}
                };

                barChartInstance = new ApexCharts(document.querySelector('#barChart'), barOptions);
                barChartInstance.render();
            })
            .catch(error => {
                console.error('Error loading accounts for bar chart:', error);
            });
    }

    // Search accounts
    async function searchAccounts() {
        const searchTerm = document.getElementById('searchInput').value;

        if (searchTerm.trim() === '') {
            renderAccountsTable(accountsData);
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}?action=search&q=${encodeURIComponent(searchTerm)}`);
            const filteredAccounts = await response.json();
            renderAccountsTable(filteredAccounts);
            updateAccountCount(filteredAccounts.length, accountsData.length);
        } catch (error) {
            console.error('Error searching accounts:', error);
        }
    }

    // Add new account with initial balance (with debugging)
    async function addAccount() {
        // Get form values
        const accountName = document.getElementById('accountName').value.trim();
        const accountType = document.getElementById('accountType').value;
        const accountCurrency = document.getElementById('accountCurrency').value;
        const initialBalance = parseFloat(document.getElementById('initialBalance').value) || 0;
        const bankName = document.getElementById('bankName').value.trim();
        const accountNumber = document.getElementById('accountNumber').value.trim();
        const interestRate = parseFloat(document.getElementById('interestRate').value) || 0;
        const minimumBalance = parseFloat(document.getElementById('minimumBalance').value) || 0;
        const status = document.getElementById('accountStatus').value;
        const notes = document.getElementById('accountNotes').value.trim();
        const validStatuses = ['Active', 'Inactive', 'Locked', 'Low Balance', 'Debt'];

        // Validate and default status if invalid or empty
        if (!status || !validStatuses.includes(status)) {
            console.warn('Invalid or empty status detected, defaulting to Active. Original value:', status);
            status = 'Active';
            document.getElementById('accountStatus').value = 'Active';
        }

        // Validation
        if (!accountName) {
            showToast('Account name is required', 'danger');
            document.getElementById('accountName').focus();
            return;
        }

        if (!accountType) {
            showToast('Please select an account type', 'danger');
            document.getElementById('accountType').focus();
            return;
        }

        if (!accountCurrency) {
            showToast('Please select a currency', 'danger');
            document.getElementById('accountCurrency').focus();
            return;
        }

        if (initialBalance < 0) {
            showToast('Initial balance cannot be negative', 'danger');
            document.getElementById('initialBalance').focus();
            return;
        }

        // Show loading state
        const submitButton = document.querySelector('[onclick="addAccount()"]');
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
        submitButton.disabled = true;

        try {
            // Prepare account data
            const accountData = {
                account_name: accountName,
                account_type: accountType,
                currency: accountCurrency,
                balance: initialBalance, // This will be used for initial balance_history entry
                bank_name: bankName,
                account_number: accountNumber,
                status: status,
                interest_rate: interestRate,
                minimum_balance: minimumBalance,
                notes: notes
            };

            const response = await fetch(`${API_BASE_URL}?action=create`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(accountData)
            });

            // Get response text first to see what we're actually receiving
            const responseText = await response.text();

            // Try to parse as JSON
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (jsonError) {
                showToast('Server returned invalid response. Check console for details.', 'danger');
                return;
            }

            if (response.ok && result.success) {
                // Success
                bootstrap.Modal.getInstance(document.getElementById('addAccountModal')).hide();
                await loadDashboardData();
                showToast(`Account "${accountName}" created successfully with initial balance of ${formatCurrency(initialBalance, accountCurrency)}!`, 'success');

                // Clear form
                document.getElementById('addAccountForm').reset();
                document.getElementById('accountSummaryCard').style.display = 'none';
            } else {
                showToast(result.message || 'Failed to create account', 'danger');
            }

        } catch (error) {
            showToast('Failed to create account. Please try again.', 'danger');
        } finally {
            // Restore button
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    }

    // Enhanced success notification with account details
    function showAccountCreatedSuccess(accountName, initialBalance) {
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle me-2"></i>
                    <div>
                        <strong>Account Created Successfully!</strong>
                        <br><small>${accountName} • Initial Balance: ${formatCurrency(initialBalance)}</small>
                    </div>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

        // Add to the shared toast container (assets/js/toast.js)
        getToastContainer().appendChild(toast);

        // Show toast
        const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
        bsToast.show();

        // Remove from DOM after hiding
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    // Delete account
    async function deleteAccount(accountId) {
        // Find the account data
        const account = accountsData.find(a => a.id == accountId);
        if (!account) {
            showToast('Account not found', 'danger');
            return;
        }

        // Store account data for deletion
        deleteAccountData = {
            id: account.id,
            name: account.account_name,
            type: account.type_name || '-',
            bank: account.bank_name || '-',
            balance: formatCurrency(account.current_balance || 0)
        };

        // Fetch balance history count
        let historyCount = 0;
        try {
            const response = await fetch(`${API_BASE_URL}?action=get_balance_history&account_id=${accountId}`);
            const history = await response.json();
            historyCount = history.length || 0;
        } catch (error) {
            console.error('Error fetching history count:', error);
        }

        // Populate modal with account details
        document.getElementById('deleteAccountName').textContent = deleteAccountData.name;
        document.getElementById('deleteAccountType').textContent = deleteAccountData.type;
        document.getElementById('deleteAccountBalance').textContent = deleteAccountData.balance;
        document.getElementById('deleteAccountBank').textContent = deleteAccountData.bank;
        document.getElementById('deleteAccountNameHint').textContent = deleteAccountData.name;
        document.getElementById('deleteHistoryCount').textContent = historyCount;

        // Reset form state
        document.getElementById('deleteConfirmInput').value = '';
        document.getElementById('deleteConfirmInput').classList.remove('is-valid', 'is-invalid');
        document.getElementById('deleteUnderstandCheckbox').checked = false;
        document.getElementById('deleteReason').value = '';
        document.getElementById('confirmDeleteBtn').disabled = true;
        document.getElementById('deleteCountdown').textContent = '';

        // Clear any existing countdown
        if (deleteCountdownTimer) {
            clearInterval(deleteCountdownTimer);
            deleteCountdownTimer = null;
        }

        // Show delete confirmation modal
        new bootstrap.Modal(document.getElementById('deleteAccountModal')).show();
    }

    // Refresh all data
    async function refreshData() {
        const refreshBtn = document.querySelector('[onclick="refreshData()"]');
        const spinner = refreshBtn.querySelector('.loading');

        spinner.classList.add('show');
        refreshBtn.disabled = true;

        try {
            await loadDashboardData();
            showToast('Data refreshed successfully!', 'success');
        } catch (error) {
            showToast('Failed to refresh data.', 'danger');
        } finally {
            spinner.classList.remove('show');
            refreshBtn.disabled = false;
        }
    }

    // Utility functions
    function formatCurrency(amount, currencyCode) {
        const num = parseFloat(amount);
        const code = (currencyCode || preferredCurrency || 'USD').toUpperCase();
        if (isNaN(num)) {
            try {
                return new Intl.NumberFormat('en-US', { style: 'currency', currency: code }).format(0);
            } catch (e) {
                return `${currencySymbol(code)}0.00`;
            }
        }

        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: code
            }).format(num);
        } catch (e) {
            // Intl.NumberFormat throws on codes it doesn't recognize as a
            // valid ISO 4217 currency (rare, but some regional codes trip
            // it up) — fall back to a manual symbol + number instead of
            // crashing the render.
            return `${currencySymbol(code)}${num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }
    }

    function formatCurrencyValue(amount) {
        const num = parseFloat(amount);
        if (isNaN(num)) return '0.00';

        const sign = num >= 0 ? '+' : '';
        return sign + num.toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    function formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    }

    function getStatusBadge(status) {
        const badges = {
            'Active': 'bg-success',
            'Inactive': 'bg-secondary',
            'Locked': 'bg-warning text-dark',
            'Low Balance': 'bg-danger',
            'Debt': 'bg-dark'
        };

        const icons = {
            'Active': 'fas fa-check-circle',
            'Inactive': 'fas fa-pause-circle',
            'Locked': 'fas fa-lock',
            'Low Balance': 'fas fa-exclamation-triangle',
            'Debt': 'fas fa-minus-circle'
        };

        const badgeClass = badges[status] || 'bg-secondary';
        const iconClass = icons[status] || 'fas fa-question-circle';

        return `<span class="badge ${badgeClass}"><i class="${iconClass} me-1"></i>${status}</span>`;
    }

    // Replace the getTypeBadge function with this dynamic version:
    function getTypeBadge(type, colorCode = null, iconClass = null) {
        // If colorCode is provided, use it; otherwise fall back to defaults
        const defaultColors = {
            'Checking': '#007bff',
            'Savings': '#28a745',
            'Investment': '#ffc107',
            'CD': '#17a2b8',
            'Digital': '#6c757d',
            'Crypto': '#343a40',
            'Credit': '#dc3545'
        };

        const defaultIcons = {
            'Checking': 'fas fa-university',
            'Savings': 'fas fa-piggy-bank',
            'Investment': 'fas fa-chart-line',
            'CD': 'fas fa-certificate',
            'Digital': 'fas fa-mobile-alt',
            'Crypto': 'fab fa-bitcoin',
            'Credit': 'fas fa-credit-card'
        };

        const color = colorCode || defaultColors[type] || '#6c757d';
        const icon = iconClass || defaultIcons[type] || 'fas fa-wallet';

        return `<span class="badge" style="background-color: ${color}"><i class="${icon} me-1"></i>${type}</span>`;
    }

    function getProgressBarClass(type) {
        const classes = {
            'Checking': 'progress-bar-custom',
            'Savings': 'bg-success',
            'Investment': 'bg-warning',
            'CD': 'bg-info',
            'Digital': 'bg-secondary',
            'Crypto': 'bg-dark',
            'Credit': 'bg-danger'
        };
        return classes[type] || 'bg-primary';
    }

    function updateAccountCount(showing, total = null) {
        const countText = total ? `Showing ${showing} of ${total} accounts` : `Showing ${showing} accounts`;
        document.getElementById('accountCount').textContent = countText;
    }

    // showToast() is now defined in the shared assets/js/toast.js (loaded via sudo/footer.php)

    function exportData() {
        // Convert accounts data to CSV
        const csv = convertToCSV(accountsData);
        downloadCSV(csv, 'accounts_export.csv');
    }

    function convertToCSV(data) {
        const headers = ['Account Name', 'Type', 'Balance', 'Bank', 'Status', 'Last Updated'];
        const rows = data.map(account => [
            account.account_name,
            account.account_type,
            account.balance,
            account.bank_name || '',
            account.status,
            account.last_updated
        ]);

        return [headers, ...rows].map(row => row.join(',')).join('\n');
    }

    function downloadCSV(csv, filename) {
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        window.URL.revokeObjectURL(url);
    }

    // Table sorting (client-side for now)
    let sortDirection = {};
    function sortTable(columnIndex) {
        const direction = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
        sortDirection[columnIndex] = direction;

        const sortedData = [...accountsData].sort((a, b) => {
            let aVal, bVal;

            switch(columnIndex) {
                case 0: aVal = a.account_name; bVal = b.account_name; break;
                case 1: aVal = a.account_type; bVal = b.account_type; break;
                case 2: aVal = parseFloat(a.balance); bVal = parseFloat(b.balance); break;
                case 4: aVal = new Date(a.last_balance_update); bVal = new Date(b.last_balance_update); break;
                default: return 0;
            }

            if (direction === 'asc') {
                return aVal > bVal ? 1 : -1;
            } else {
                return aVal < bVal ? 1 : -1;
            }
        });

        renderAccountsTable(sortedData);
    }

    // Search on Enter key
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchAccounts();
        }
    });

    // Load growth data (+ 5-month forecast) and render growth chart
    async function loadGrowthData() {
        try {
            const response = await fetch(`${API_BASE_URL}?action=growth_forecast&to=${preferredCurrency}&months=5`);
            const data = await response.json();
            renderGrowthChart(data);
        } catch (error) {
            console.error('Error loading growth data:', error);
        }
    }

    // Render growth chart with historical actuals + forward forecast
    function renderGrowthChart(data) {
        const MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const monthLabel = (monthStr) => {
            const parts = (monthStr || '').split('-');
            const mon = MONTH_NAMES[parseInt(parts[1], 10) - 1] || '';
            return mon + '-' + (parts[0] || '');
        };

        // Backend already returns ascending chronological order (oldest
        // first) for both arrays, so the forecast continues naturally
        // right after the last historical point — no reversing needed.
        const historical = data.historical || [];
        const forecast = data.forecast || [];

        const historicalLabels = historical.map(item => monthLabel(item.month));
        const forecastLabels = forecast.map(item => monthLabel(item.month));
        const labels = historicalLabels.concat(forecastLabels);

        const historicalBalances = historical.map(item => parseFloat(item.total_balance));
        const historicalGrowth = historical.map(item => parseFloat(item.total_growth_amount || 0));
        const forecastBalances = forecast.map(item => parseFloat(item.projected_balance));
        const forecastChange = forecast.map(item => parseFloat(item.projected_change || 0));

        const n = historicalBalances.length;

        // "Actual Balance" series: real values, nulls where the forecast lives.
        const actualSeriesData = historicalBalances.concat(new Array(forecastBalances.length).fill(null));

        // "Forecast" series: nulls through history, EXCEPT the very last
        // historical point is repeated here too so the dashed line
        // visually connects to the solid line with no gap, then the
        // projected values continue from there.
        const forecastSeriesData = forecastBalances.length > 0
            ? new Array(Math.max(n - 1, 0)).fill(null).concat([historicalBalances[n - 1]]).concat(forecastBalances)
            : [];

        // Update the confidence/trend note in the card header.
        const noteEl = document.getElementById('growthForecastNote');
        if (noteEl) {
            if (!data.success || forecast.length === 0) {
                noteEl.textContent = data.message || 'Forecast unavailable — need at least 2 months of balance history.';
            } else {
                const trendDir = data.monthly_trend >= 0 ? 'up' : 'down';
                const trendIcon = data.monthly_trend >= 0 ? '▲' : '▼';
                const confidenceLabel = { high: 'High', medium: 'Medium', low: 'Low' }[data.confidence] || 'Low';
                noteEl.innerHTML = `Forecast: ${trendIcon} ${formatCurrency(Math.abs(data.monthly_trend), data.display_currency)}/mo trend &nbsp;•&nbsp; Confidence: ${confidenceLabel} (R&sup2;=${data.r_squared})`;
            }
        }

        const series = [
            { name: 'Actual Balance', data: actualSeriesData }
        ];
        if (forecastSeriesData.length > 0) {
            series.push({ name: 'Forecast (next ' + forecast.length + ' mo)', data: forecastSeriesData });
        }

        const options = {
            chart: {
                type: 'line',
                height: 400,
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: true,
                        zoom: true,
                        zoomin: true,
                        zoomout: true,
                        reset: true
                    }
                },
                zoom: {
                    enabled: true
                }
            },
            series: series,
            xaxis: {
                categories: labels,
                title: {
                    text: 'Month-Year',
                    style: { color: '#ffffff' }
                },
                labels: {
                    style: {
                        colors: '#ffffff'
                    }
                }
            },
            yaxis: [
                {
                    title: {
                        text: 'Balance',
                        style: { color: '#ffffff' }
                    },
                    labels: {
                        style: {
                            colors: '#ffffff'
                        },
                        formatter: function (val) {
                            return formatCurrency(val, preferredCurrency);
                        }
                    }
                }
            ],
            markers: {
                size: 4,
                colors: ['#0ecd41', '#f59e0b'],
                strokeColors: '#fff',
                strokeWidth: 2,
                hover: {
                    size: 6
                }
            },
            colors: ['#0ecd41', '#f59e0b'],
            stroke: {
                curve: 'smooth',
                width: [5, 3],
                dashArray: [0, 6]
            },
            grid: {
                show: true,
                borderColor: 'rgba(255, 255, 255, 0.05)', // almost invisible
                strokeDashArray: 4
            },
            tooltip: {
                shared: true,
                intersect: false,
                custom: function({ dataPointIndex, w }) {
                    const label = labels[dataPointIndex];
                    const isForecast = dataPointIndex >= n;
                    const balance = isForecast ? forecastBalances[dataPointIndex - n] : historicalBalances[dataPointIndex];
                    const change = isForecast ? forecastChange[dataPointIndex - n] : historicalGrowth[dataPointIndex];
                    const sign = change >= 0 ? '+' : '';
                    const color = change >= 0 ? '#22c55e' : '#ef4444';
                    const arrow = change >= 0 ? '&#9650;' : '&#9660;';
                    const dotColor = isForecast ? '#f59e0b' : '#0ecd41';
                    const seriesLabel = isForecast ? 'Forecast' : 'Total Balance';
                    return '<div style="padding:10px 14px;background:#0c1936;border:1px solid rgba(255,255,255,.12);border-radius:8px;min-width:190px;">' +
                        '<div style="color:#94a3b8;font-size:11px;margin-bottom:6px;">' + label + (isForecast ? ' (projected)' : '') + '</div>' +
                        '<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">' +
                        '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' + dotColor + ';flex-shrink:0;"></span>' +
                        '<span style="color:#fff;font-size:13px;font-weight:600;">' + seriesLabel + '</span>' +
                        '</div>' +
                        '<div style="color:#fff;font-size:15px;font-weight:700;margin-bottom:6px;padding-left:16px;">' + formatCurrency(balance, preferredCurrency) + '</div>' +
                        '<div style="border-top:1px solid rgba(255,255,255,.08);padding-top:6px;">' +
                        '<span style="color:#94a3b8;font-size:11px;">vs prev month&nbsp;&nbsp;</span>' +
                        '<span style="color:' + color + ';font-size:13px;font-weight:600;">' + arrow + ' ' + sign + formatCurrency(Math.abs(change), preferredCurrency) + '</span>' +
                        '</div>' +
                        '</div>';
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'center',
                offsetY: -10
            },
            annotations: (n > 0 && forecastSeriesData.length > 0) ? {
                xaxis: [{
                    x: labels[n - 1],
                    borderColor: 'rgba(255,255,255,.25)',
                    strokeDashArray: 3,
                    label: {
                        text: 'Today',
                        orientation: 'horizontal',
                        style: { color: '#0f172a', background: 'rgba(255,255,255,.75)', fontSize: '10px' }
                    }
                }]
            } : {}
        };

        if (growthChart) {
            growthChart.updateOptions(options, true, true);
        } else {
            growthChart = new ApexCharts(document.querySelector('#growthChart'), options);
            growthChart.render();
        }
    }


    // Show update balance modal
    async function showUpdateBalanceModal() {
        const modal = new bootstrap.Modal(document.getElementById('updateBalanceModal'));

        // Set current month
        const now = new Date();
        const currentMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
        document.getElementById('updateMonth').value = currentMonth;

        // Load accounts for balance update
        await loadAccountsForBalanceUpdate();
        modal.show();
    }

    // Load accounts for balance update (enhanced with real-time updates)
    async function loadAccountsForBalanceUpdate() {
        try {
            const response = await fetch(`${API_BASE_URL}?action=accounts_for_update`);
            const accounts = await response.json();
            const listContainer = document.getElementById('balanceUpdateList');

            if (accounts.length === 0) {
                listContainer.innerHTML = '<div class="alert alert-info">No accounts found</div>';
                return;
            }

            listContainer.innerHTML = accounts.map(account => {
                // Get type badge with icon
                const typeBadge = getTypeBadge(account.account_type, account.color_code, account.icon_class);
                const currency = account.currency || 'USD';

                return `
            <div class='card mb-3 border-0 shadow-sm'>
                <div class='card-body py-3'>
                    <div class='row align-items-center'>
                        <div class='col-md-3'>
                            <div class='d-flex align-items-center'>
                                <div>
                                    <strong class='d-block'>${account.account_name}</strong>
                                    <div class='text-muted small mb-1'>
                                        Bank: ${account.bank_name || 'No Bank'}
                                    </div>
                                    <div class='mb-1'>
                                        ${typeBadge}
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" style="font-size:.65rem;">${currency}</span>
                                    </div>
                                    <small class='text-muted'>Balance: ${formatCurrency(account.current_balance, currency)}</small>
                                    <br><small class='text-muted'>As at: ${account.last_balance_update || 'Never'}</small>
                                </div>
                            </div>
                        </div>
                        <div class='col-md-2'>
                            <label class='form-label text-muted small mb-1'>New Balance</label>
                            <div class='input-group input-group-sm'>
                                <span class='input-group-text' id='balance_currency_${account.id}'>${currencySymbol(currency)}</span>
                                <input type='number'
                                       step='1'
                                       class='form-control form-control-sm'
                                       id='balance_${account.id}'
                                       value='${account.current_balance}'
                                       placeholder='Enter new balance'
                                       oninput='calculateGrowthPreview(${account.id}, ${account.current_balance})'
                                       onchange='calculateGrowthPreview(${account.id}, ${account.current_balance})'
                                       onkeyup='calculateGrowthPreview(${account.id}, ${account.current_balance})'>
                            </div>
                        </div>
                        <div class='col-md-2'>
                            <label class='form-label text-muted small mb-1'>
                                Growth Amount
                                <i class='fas fa-calculator text-primary' title='Auto-calculated'></i>
                            </label>
                            <input type='text'
                                   class='form-control form-control-sm text-center fw-bold'
                                   id='growth_${account.id}'
                                   value='+0.00'
                                   readonly
                                   title='Automatically calculated based on balance change'>
                        </div>
                        <div class='col-md-2'>
                            <label class='form-label text-muted small mb-1'>
                                Growth %
                                <i class='fas fa-percentage text-success' title='Auto-calculated'></i>
                            </label>
                            <input type='text'
                                   class='form-control form-control-sm text-center fw-bold'
                                   id='growth_percent_${account.id}'
                                   value='0.00%'
                                   readonly
                                   title='Percentage change from previous balance'>
                        </div>
                        <div class='col-md-2'>
                            <label class='form-label text-muted small mb-1'>Notes</label>
                            <input type='text'
                                   class='form-control form-control-sm'
                                   id='notes_${account.id}'
                                   placeholder='Optional notes'
                                   maxlength='100'>
                        </div>
                        <div class='col-md-1'>
                            <div class='text-center'>
                                <button class='btn btn-outline-primary btn-sm'
                                        onclick='resetBalance(${account.id}, ${account.current_balance})'
                                        title='Reset to current balance'>
                                    <i class='fas fa-undo'></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Growth Summary Row -->
                    <div class='row mt-2'>
                        <div class='col-12'>
                            <div class='alert alert-light py-1 mb-0' id='summary_${account.id}' style='display: none;'>
                                <small class='text-muted'>
                                    <i class='fas fa-info-circle'></i>
                                    <span id='summary_text_${account.id}'></span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `;
            }).join('');

            // Store accounts data for calculations
            window.currentAccountsForUpdate = accounts;

            // Initialize all growth calculations
            accounts.forEach(account => {
                calculateGrowthPreview(account.id, account.current_balance);
            });

        } catch (error) {
            console.error('Error loading accounts for update:', error);
            showToast('Failed to load accounts for balance update', 'danger');
        }
    }

    // Show bulk update summary
    function showBulkUpdateSummary() {
        if (!window.currentAccountsForUpdate) return;

        let totalGrowth = 0; // in preferredCurrency, converted per-account
        let changedAccounts = 0;

        window.currentAccountsForUpdate.forEach(account => {
            const newBalanceInput = document.getElementById(`balance_${account.id}`);
            const newBalance = parseFloat(newBalanceInput.value) || 0;
            const growth = newBalance - account.current_balance;

            if (growth !== 0) {
                // Accounts can be in different currencies — convert each
                // one's growth into the display currency before adding it
                // to the running total, rather than summing raw mixed values.
                totalGrowth += convertAmountClient(growth, account.currency || 'USD', preferredCurrency);
                changedAccounts++;
            }
        });

        // Update modal footer with summary
        const modalFooter = document.querySelector('#updateBalanceModal .modal-footer');
        const existingSummary = modalFooter.querySelector('.bulk-summary');

        if (existingSummary) {
            existingSummary.remove();
        }

        if (changedAccounts > 0) {
            const summaryDiv = document.createElement('div');
            summaryDiv.className = 'bulk-summary me-auto';
            summaryDiv.innerHTML = `
            <small class="text-muted">
                <i class="fas fa-calculator"></i>
                <strong>${changedAccounts}</strong> accounts changed |
                Total growth (${preferredCurrency}): <span class="${totalGrowth >= 0 ? 'text-success' : 'text-danger'}">
                    ${formatCurrency(totalGrowth, preferredCurrency)}
                </span>
            </small>
        `;

            modalFooter.insertBefore(summaryDiv, modalFooter.firstChild);
        }
    }

    // Calculate growth preview when balance changes
    function calculateGrowthPreview(accountId, currentBalance) {
        const newBalanceInput = document.getElementById(`balance_${accountId}`);
        const growthAmountDisplay = document.getElementById(`growth_${accountId}`);
        const growthPercentDisplay = document.getElementById(`growth_percent_${accountId}`);
        const summaryDiv = document.getElementById(`summary_${accountId}`);
        const summaryText = document.getElementById(`summary_text_${accountId}`);

        // Each account keeps its own native currency here — this modal
        // records a raw balance in whatever currency the account is in,
        // not the dashboard's display/preferred currency.
        const account = (window.currentAccountsForUpdate || []).find(a => a.id == accountId);
        const currency = account ? (account.currency || 'USD') : preferredCurrency;

        const newBalance = parseFloat(newBalanceInput.value) || 0;
        const growthAmount = newBalance - currentBalance;
        const growthPercent = currentBalance > 0 ? ((growthAmount / currentBalance) * 100) : 0;

        // Update the display fields with formatted values
        growthAmountDisplay.value = formatCurrencyValue(growthAmount);
        growthPercentDisplay.value = growthPercent.toFixed(2) + '%';

        // Determine colors and styling
        let colorClass, bgClass, summaryClass, icon, description;

        if (growthAmount > 0) {
            colorClass = 'text-success';
            bgClass = 'bg-success-subtle border-success';
            summaryClass = 'alert-success';
            icon = '📈';
            description = 'Increase';
        } else if (growthAmount < 0) {
            colorClass = 'text-danger';
            bgClass = 'bg-danger-subtle border-danger';
            summaryClass = 'alert-danger';
            icon = '📉';
            description = 'Decrease';
        } else {
            colorClass = 'text-muted';
            bgClass = 'bg-light border-secondary';
            summaryClass = 'alert-light';
            icon = '➖';
            description = 'No change';
        }

        // Apply styling
        growthAmountDisplay.className = `form-control form-control-sm text-center fw-bold ${colorClass} ${bgClass}`;
        growthPercentDisplay.className = `form-control form-control-sm text-center fw-bold ${colorClass} ${bgClass}`;

        // Update summary
        if (growthAmount !== 0) {
            summaryDiv.style.display = 'block';
            summaryDiv.className = `alert ${summaryClass} py-1 mb-0`;
            summaryText.innerHTML = `
            ${icon} <strong>${description}</strong> of ${formatCurrency(Math.abs(growthAmount), currency)}
            (${Math.abs(growthPercent).toFixed(2)}%) from ${formatCurrency(currentBalance, currency)} to ${formatCurrency(newBalance, currency)}
        `;
        } else {
            summaryDiv.style.display = 'none';
        }

        // Update tooltips
        growthAmountDisplay.title = `${description}: ${formatCurrency(Math.abs(growthAmount), currency)}`;
        growthPercentDisplay.title = `${description}: ${Math.abs(growthPercent).toFixed(2)}%`;

        // Update the balance input styling
        if (newBalance !== currentBalance) {
            newBalanceInput.className = `form-control form-control-sm ${colorClass} border-2`;
        } else {
            newBalanceInput.className = 'form-control form-control-sm';
        }
        animateGrowthUpdate(accountId);
        showBulkUpdateSummary();
    }

    // Add animation when growth updates
    function animateGrowthUpdate(accountId) {
        const growthAmount = document.getElementById(`growth_${accountId}`);
        const growthPercent = document.getElementById(`growth_percent_${accountId}`);

        [growthAmount, growthPercent].forEach(element => {
            element.classList.add('growth-updated');
            setTimeout(() => {
                element.classList.remove('growth-updated');
            }, 300);
        });
    }

    function resetBalance(accountId, originalBalance) {
        const balanceInput = document.getElementById(`balance_${accountId}`);
        const notesInput = document.getElementById(`notes_${accountId}`);

        balanceInput.value = originalBalance;
        notesInput.value = '';

        // Trigger recalculation
        calculateGrowthPreview(accountId, originalBalance);

        // Visual feedback
        balanceInput.focus();
        balanceInput.select();
    }

    // Load latest month data
    async function loadLatestMonthData() {
        try {
            const response = await fetch(`${API_BASE_URL}?action=latest_month`);
            const data = await response.json();

            // Update the latest data month card
            document.getElementById('latestDataMonth').textContent = data.formatted_month;

            // Update details with actionable insights
            const detailsElement = document.getElementById('latestDataDetails');
            if (data.accounts_with_data > 0) {
                const needsUpdate = data.total_accounts - data.accounts_with_data;
                const isStale = isDataStale(data.month_year);

                if (needsUpdate > 0) {
                    detailsElement.innerHTML = `
                    <i class='fas fa-tasks text-warning me-1'></i>${needsUpdate} need updates<br>
                    <button class='btn btn-sm btn-outline-primary mt-1' onclick='showUpdateModal()'>
                        <i class='fas fa-plus me-1'></i>Update Now
                    </button>
                `;
                } else if (isStale) {
                    detailsElement.innerHTML = `
                    <i class='fas fa-calendar-plus text-info me-1'></i>Ready for new month<br>
                    <button class='btn btn-sm btn-outline-success mt-1' onclick='startNewMonth()'>
                        <i class='fas fa-forward me-1'></i>Start ${getNextMonth()}
                    </button>
                `;
                } else {
                    detailsElement.innerHTML = `
                    <i class='fas fa-check-circle text-success me-1'></i>Data complete<br>
                    <i class='fas fa-chart-line me-1'></i>Ready for analysis
                `;
                }
            } else {
                detailsElement.innerHTML = `
                <i class='fas fa-plus-circle text-primary me-1'></i>No data yet<br>
                <button class='btn btn-sm btn-outline-primary mt-1' onclick='showUpdateModal()'>
                    <i class='fas fa-plus me-1'></i>Add First Record
                </button>
            `;
            }

            window.latestMonthData = data;

        } catch (error) {
            console.error('Error loading latest month data:', error);
            document.getElementById('latestDataMonth').textContent = 'Error';
            document.getElementById('latestDataDetails').textContent = 'Failed to load';
        }
    }

    function calculateDaysAgo(monthYear) {
        const targetDate = new Date(monthYear + '-01');
        const today = new Date();
        const diffTime = Math.abs(today - targetDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays;
    }

    function isCurrentMonthData(monthYear) {
        const currentMonth = new Date().toISOString().slice(0, 7);
        return monthYear === currentMonth;
    }

    function isDataStale(monthYear) {
        const currentMonth = new Date().toISOString().slice(0, 7);
        return monthYear < currentMonth;
    }

    function getNextMonth() {
        const next = new Date();
        next.setMonth(next.getMonth() + 1);
        return next.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
    }

    // Save balance updates
    async function saveBalanceUpdates() {
        const monthYear = document.getElementById('updateMonth').value + '-01';
        const updates = [];

        for (const account of window.currentAccountsForUpdate || []) {
            const newBalanceInput = document.getElementById(`balance_${account.id}`);
            const notesInput = document.getElementById(`notes_${account.id}`);

            const newBalance = parseFloat(newBalanceInput.value) || 0;
            const notes = notesInput.value || '';

            // Only update if balance has changed
            if (newBalance !== account.current_balance) {
                updates.push({
                    account_id: account.id,
                    balance: newBalance,
                    month_year: monthYear,
                    notes: notes
                });
            }
        }

        if (updates.length === 0) {
            showToast('No balance changes detected', 'danger');
            return;
        }

        // Show progress
        const saveButton = document.querySelector('[onclick="saveBalanceUpdates()"]');
        const originalText = saveButton.innerHTML;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        saveButton.disabled = true;

        let successCount = 0;
        let errorCount = 0;

        // Save each update
        for (const update of updates) {
            try {
                const response = await fetch(`${API_BASE_URL}?action=update_balance`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(update)
                });

                const result = await response.json();

                if (response.ok && !result.message.includes('Unable')) {
                    successCount++;
                    console.log(`Updated account ${update.account_id}:`, result);
                } else {
                    errorCount++;
                    console.error(`Failed to update account ${update.account_id}:`, result.message);
                }
            } catch (error) {
                errorCount++;
                console.error('Error updating balance:', error);
            }
        }

        // Restore button
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;

        // Show results
        if (successCount > 0) {
            bootstrap.Modal.getInstance(document.getElementById('updateBalanceModal')).hide();
            await loadDashboardData();
            showToast(`Successfully updated ${successCount} account balances!`, 'success');
        }

        if (errorCount > 0) {
            showToast(`Failed to update ${errorCount} accounts. Check console for details.`, 'danger');
        }
    }

    // Show manage types modal
    async function showManageTypesModal() {
        await loadAccountTypes();
        const modal = new bootstrap.Modal(document.getElementById('manageTypesModal'));
        modal.show();
    }

    // Load account types
    async function loadAccountTypes() {
        try {
            const response = await fetch(`${API_BASE_URL}?action=account_types&include_inactive=true`);
            accountTypes = await response.json();
            renderAccountTypesList();
        } catch (error) {
            console.error('Error loading account types:', error);
        }
    }

    // Render account types list
    function renderAccountTypesList() {
        const container = document.getElementById('existingTypesList');

        container.innerHTML = accountTypes.map(type => {
            const isActive = type.is_active == 1;
            const statusClass = isActive ? '' : 'opacity-50';
            const statusBadge = isActive ?
                '' :
                '<span class="badge bg-secondary ms-2">Inactive</span>';

            return `
        <div class='d-flex justify-content-between align-items-center mb-2 p-2 border rounded ${statusClass}'>
            <div class="d-flex align-items-center">
                <span class='badge me-2' style='background-color: ${type.color_code}'>
                    <i class="${type.icon_class} me-1"></i>${type.type_name}
                </span>
                ${statusBadge}
            </div>
            <div>
                ${isActive ? `
                    <button class='btn btn-sm btn-outline-primary me-1' onclick='editAccountType(${type.id})' title='Edit'>
                        <i class='fas fa-edit'></i>
                    </button>
                    <button class='btn btn-sm btn-outline-danger' onclick='deleteAccountType(${type.id})' title='Deactivate'>
                        <i class='fas fa-toggle-off'></i>
                    </button>
                ` : `
                    <button class='btn btn-sm btn-outline-success me-1' onclick='reactivateAccountType(${type.id})' title='Reactivate'>
                        <i class='fas fa-toggle-on'></i>
                    </button>
                    <button class='btn btn-sm btn-outline-primary me-1' onclick='editAccountType(${type.id})' title='Edit'>
                        <i class='fas fa-edit'></i>
                    </button>
                `}
            </div>
        </div>
        `;
        }).join('');
    }

    // Add new function to reactivate account types
    async function reactivateAccountType(typeId) {
        if (!confirm('Are you sure you want to reactivate this account type?')) return;

        try {
            const response = await fetch(`${API_BASE_URL}?action=manage_type`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reactivate', id: typeId })
            });

            const result = await response.json();

            if (result.success) {
                await loadAccountTypes();
                await populateAccountTypesDropdown(); // Refresh dropdown
                showToast(result.message, 'success');
            } else {
                showToast(result.message, 'danger');
            }
        } catch (error) {
            console.error('Error reactivating account type:', error);
            showToast('Failed to reactivate account type', 'danger');
        }
    }

    // Add account type
    async function addAccountType() {
        const typeName = document.getElementById('newTypeName').value.trim();
        const colorCode = document.getElementById('newTypeColor').value;
        const iconClass = document.getElementById('newTypeIcon').value.trim();

        if (!typeName) {
            showToast('Please enter a type name', 'danger');
            return;
        }

        const formData = {
            action: 'create',
            type_name: typeName,
            color_code: colorCode,
            icon_class: iconClass || 'fas fa-wallet'
        };

        try {
            const response = await fetch(`${API_BASE_URL}?action=manage_type`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                document.getElementById('addTypeForm').reset();
                document.getElementById('newTypeColor').value = '#007bff'; // Reset color
                await loadAccountTypes();
                showToast(result.message, 'success');
            } else {
                showToast(result.message, 'danger');
            }
        } catch (error) {
            console.error('Error adding account type:', error);
            showToast('Failed to add account type', 'danger');
        }
    }

    // Delete account type
    async function deleteAccountType(typeId) {
        if (!confirm('Are you sure you want to deactivate this account type? It will be hidden from new accounts but existing accounts will keep this type.')) return;

        try {
            const response = await fetch(`${API_BASE_URL}?action=manage_type`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: typeId })
            });

            const result = await response.json();

            if (result.success) {
                await loadAccountTypes();
                await populateAccountTypesDropdown(); // Refresh dropdown
                showToast('Account type deactivated successfully', 'success');
            } else {
                showToast(result.message, 'danger');
            }
        } catch (error) {
            console.error('Error deactivating account type:', error);
            showToast('Failed to deactivate account type', 'danger');
        }
    }

    function calculateGrowthPercentage(accountId, currentBalance) {
        const newBalanceInput = document.getElementById(`balance_${accountId}`);
        const growthAmountInput = document.getElementById(`growth_${accountId}`);

        const newBalance = parseFloat(newBalanceInput.value) || 0;
        const growthAmount = parseFloat(growthAmountInput.value) || 0;

        // Auto-calculate growth amount if balance changed
        if (newBalance !== currentBalance && growthAmount === 0) {
            const calculatedGrowth = newBalance - currentBalance;
            growthAmountInput.value = calculatedGrowth.toFixed(2);
        }
    }

    async function editAccountType(typeId) {
        try {
            // Find the account type data
            const accountType = accountTypes.find(type => type.id == typeId);
            if (!accountType) {
                showToast('Account type not found', 'danger');
                return;
            }

            // Show edit modal
            showEditAccountTypeModal(accountType);
        } catch (error) {
            console.error('Error editing account type:', error);
            showToast('Failed to open edit form', 'danger');
        }
    }

    function showEditAccountTypeModal(accountType) {
        const modalHtml = `
    <div class='modal fade' id='editAccountTypeModal' tabindex='-1'>
        <div class='modal-dialog'>
            <div class='modal-content'>
                <div class='modal-header bg-primary text-white'>
                    <h5 class='modal-title'>
                        <i class='fas fa-edit me-2'></i>
                        Edit Account Type
                    </h5>
                    <button type='button' class='btn-close btn-close-white' data-bs-dismiss='modal'></button>
                </div>
                <div class='modal-body'>
                    <form id='editAccountTypeForm'>
                        <input type='hidden' id='editTypeId' value='${accountType.id}'>

                        <!-- Type Name -->
                        <div class='mb-3'>
                            <label for='editTypeName' class='form-label'>Type Name</label>
                            <input type='text' class='form-control' id='editTypeName'
                                   value='${accountType.type_name}' required>
                        </div>

                        <!-- Color Code -->
                        <div class='mb-3'>
                            <label for='editColorCode' class='form-label'>Color</label>
                            <div class='row'>
                                <div class='col-8'>
                                    <input type='color' class='form-control form-control-color'
                                           id='editColorCode' value='${accountType.color_code}' required>
                                </div>
                                <div class='col-4'>
                                    <input type='text' class='form-control' id='editColorCodeText'
                                           value='${accountType.color_code}' placeholder='#000000'>
                                </div>
                            </div>
                        </div>

                        <!-- Icon Class -->
                        <div class='mb-3'>
                            <label for='editIconClass' class='form-label'>Icon Class</label>
                            <div class='input-group'>
                                <input type='text' class='form-control' id='editIconClass'
                                       value='${accountType.icon_class}' placeholder='fas fa-wallet' required>
                                <button type='button' class='btn btn-outline-secondary' onclick='showIconPicker("edit")'>
                                    <i class='fas fa-icons'></i>
                                </button>
                            </div>
                            <small class='text-muted'>Use Font Awesome classes (e.g., fas fa-wallet)</small>
                        </div>

                        <!-- Preview -->
                        <div class='mb-3'>
                            <label class='form-label'>Preview</label>
                            <div class='p-3 border rounded'>
                                <span id='editTypePreview' class='badge' style='background-color: ${accountType.color_code}'>
                                    <i class='${accountType.icon_class} me-1'></i>${accountType.type_name}
                                </span>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class='mb-3'>
                            <div class='form-check'>
                                <input class='form-check-input' type='checkbox' id='editIsActive'
                                       ${accountType.is_active == 1 ? 'checked' : ''}>
                                <label class='form-check-label' for='editIsActive'>
                                    Active (visible in dropdowns)
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class='modal-footer'>
                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                    <button type='button' class='btn btn-primary' onclick='saveEditedAccountType()'>
                        <i class='fas fa-save me-1'></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>`;

        // Remove existing modal if any
        const existingModal = document.getElementById('editAccountTypeModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('editAccountTypeModal'));
        modal.show();

        // Setup event listeners for real-time preview
        setupEditTypePreview();
    }

    function setupEditTypePreview() {
        const nameInput = document.getElementById('editTypeName');
        const colorInput = document.getElementById('editColorCode');
        const colorTextInput = document.getElementById('editColorCodeText');
        const iconInput = document.getElementById('editIconClass');
        const preview = document.getElementById('editTypePreview');

        function updatePreview() {
            const name = nameInput.value || 'Type Name';
            const color = colorInput.value;
            const icon = iconInput.value || 'fas fa-wallet';

            preview.style.backgroundColor = color;
            preview.innerHTML = `<i class='${icon} me-1'></i>${name}`;

            // Update text input with color picker value
            colorTextInput.value = color;
        }

        // Sync color picker with text input
        colorInput.addEventListener('input', updatePreview);
        colorTextInput.addEventListener('input', function() {
            if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                colorInput.value = this.value;
                updatePreview();
            }
        });

        nameInput.addEventListener('input', updatePreview);
        iconInput.addEventListener('input', updatePreview);
    }

    async function saveEditedAccountType() {
        const formData = {
            action: 'update',
            id: parseInt(document.getElementById('editTypeId').value),
            type_name: document.getElementById('editTypeName').value.trim(),
            color_code: document.getElementById('editColorCode').value,
            icon_class: document.getElementById('editIconClass').value.trim(),
            is_active: document.getElementById('editIsActive').checked ? 1 : 0
        };

        // Validation
        if (!formData.type_name) {
            showToast('Please enter a type name', 'danger');
            return;
        }

        if (!formData.color_code) {
            showToast('Please select a color', 'danger');
            return;
        }

        if (!formData.icon_class) {
            showToast('Please enter an icon class', 'danger');
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}?action=manage_type`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('editAccountTypeModal')).hide();

                // Refresh data
                await loadAccountTypes();
                await populateAccountTypesDropdown();

                showToast('Account type updated successfully!', 'success');
            } else {
                showToast('Failed to update account type: ' + result.message, 'danger');
            }
        } catch (error) {
            console.error('Error updating account type:', error);
            showToast('Failed to update account type. Please try again.', 'danger');
        }
    }

    // Enhanced icon picker function
    function showIconPicker(mode = 'add') {
        const iconInput = mode === 'edit' ?
            document.getElementById('editIconClass') :
            document.getElementById('iconClass');

        const commonIcons = [
            'fas fa-wallet', 'fas fa-university', 'fas fa-piggy-bank', 'fas fa-chart-line',
            'fas fa-certificate', 'fas fa-mobile-alt', 'fab fa-bitcoin', 'fas fa-credit-card',
            'fas fa-coins', 'fas fa-dollar-sign', 'fas fa-euro-sign', 'fas fa-pound-sign',
            'fas fa-yen-sign', 'fas fa-money-bill', 'fas fa-money-check', 'fas fa-receipt',
            'fas fa-cash-register', 'fas fa-hand-holding-usd', 'fas fa-donate', 'fas fa-gift',
            'fas fa-shopping-cart', 'fas fa-store', 'fas fa-building', 'fas fa-home',
            'fas fa-car', 'fas fa-plane', 'fas fa-ship', 'fas fa-train'
        ];

        const pickerHtml = `
    <div class='modal fade' id='iconPickerModal' tabindex='-1'>
        <div class='modal-dialog modal-lg'>
            <div class='modal-content'>
                <div class='modal-header'>
                    <h5 class='modal-title'>Choose an Icon</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                </div>
                <div class='modal-body'>
                    <div class='row g-2'>
                        ${commonIcons.map(icon => `
                            <div class='col-2'>
                                <button type='button' class='btn btn-outline-secondary w-100 icon-option'
                                        data-icon='${icon}' title='${icon}'>
                                    <i class='${icon}'></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                    <hr>
                    <div class='mb-3'>
                        <label class='form-label'>Or enter custom icon class:</label>
                        <input type='text' class='form-control' id='customIconInput'
                               placeholder='fas fa-custom-icon'>
                    </div>
                </div>
                <div class='modal-footer'>
                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                    <button type='button' class='btn btn-primary' onclick='selectCustomIcon("${mode}")'>
                        Use Custom Icon
                    </button>
                </div>
            </div>
        </div>
    </div>`;

        // Remove existing picker if any
        const existingPicker = document.getElementById('iconPickerModal');
        if (existingPicker) {
            existingPicker.remove();
        }

        // Add picker to body
        document.body.insertAdjacentHTML('beforeend', pickerHtml);

        // Show picker
        const pickerModal = new bootstrap.Modal(document.getElementById('iconPickerModal'));
        pickerModal.show();

        // Add click handlers for icon options
        document.querySelectorAll('.icon-option').forEach(button => {
            button.addEventListener('click', function() {
                const selectedIcon = this.dataset.icon;
                iconInput.value = selectedIcon;

                // Update preview if in edit mode
                if (mode === 'edit') {
                    setupEditTypePreview();
                    document.getElementById('editIconClass').dispatchEvent(new Event('input'));
                } else {
                    document.getElementById('iconClass').dispatchEvent(new Event('input'));
                }

                pickerModal.hide();
            });
        });
    }

    function selectCustomIcon(mode) {
        const customIcon = document.getElementById('customIconInput').value.trim();
        if (customIcon) {
            const iconInput = mode === 'edit' ?
                document.getElementById('editIconClass') :
                document.getElementById('iconClass');

            iconInput.value = customIcon;

            // Update preview
            if (mode === 'edit') {
                document.getElementById('editIconClass').dispatchEvent(new Event('input'));
            } else {
                document.getElementById('iconClass').dispatchEvent(new Event('input'));
            }

            bootstrap.Modal.getInstance(document.getElementById('iconPickerModal')).hide();
        }
    }


    // Show month viewer modal
    async function showMonthViewer() {
        await loadAvailableMonths();
        new bootstrap.Modal(document.getElementById('monthViewerModal')).show();
    }

    // Load available months
    async function loadAvailableMonths() {
        try {
            const response = await fetch(`${API_BASE_URL}?action=available_months`);
            const months = await response.json();

            const select = document.getElementById('viewMonth');
            const startSelect = document.getElementById('startMonth');
            const endSelect = document.getElementById('endMonth');

            // Clear existing options
            [select, startSelect, endSelect].forEach(sel => {
                sel.innerHTML = '<option value="">Select Month</option>';
            });

            months.forEach(month => {
                const option = document.createElement('option');
                option.value = month;
                option.textContent = formatMonthDisplay(month);

                select.appendChild(option.cloneNode(true));
                startSelect.appendChild(option.cloneNode(true));
                endSelect.appendChild(option.cloneNode(true));
            });

        } catch (error) {
            console.error('Error loading available months:', error);
        }
    }

    // Load balances by month
    async function loadBalancesByMonth() {
        const selectedMonth = document.getElementById('viewMonth').value;

        if (!selectedMonth) {
            hideMonthData();
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}?action=balances_by_month&month=${selectedMonth}&to=${preferredCurrency}`);
            const data = await response.json();

            if (data.error) {
                showToast(data.error, 'danger');
                return;
            }

            displayMonthData(data);

        } catch (error) {
            console.error('Error loading month balances:', error);
            showToast('Failed to load month data', 'danger');
        }
    }

    // Display month data
    function displayMonthData(data) {
        const displayCur = data.display_currency || preferredCurrency;

        // Show summary cards — these are aggregates, so always in the display currency
        document.getElementById('monthSummaryCards').style.display = 'block';
        document.getElementById('monthTotalBalance').textContent = formatCurrency(data.summary.total_balance || 0, displayCur);
        document.getElementById('monthTotalGrowth').textContent = formatCurrency(data.summary.total_growth || 0, displayCur);
        document.getElementById('monthAccountsCount').textContent = `${data.summary.accounts_with_data || 0}/${data.summary.total_accounts || 0}`;
        document.getElementById('selectedMonthDisplay').textContent = formatMonthDisplay(data.month);

        // Show table
        document.getElementById('monthBalancesTable').style.display = 'table';
        document.getElementById('noMonthDataMessage').style.display = 'none';

        // Populate table
        const tbody = document.getElementById('monthBalancesTableBody');
        tbody.innerHTML = data.accounts.map(account => {
            const hasData = account.data_status === 'Has data';
            const rowClass = hasData ? '' : 'table-secondary';
            const currency = account.currency || 'USD';

            // Safely handle numeric values with fallbacks
            const balance = parseFloat(account.balance) || 0;
            const growthAmount = parseFloat(account.growth_amount) || 0;
            const growthPercentage = parseFloat(account.growth_percentage) || 0;

            return `
            <tr class="${rowClass}">
                <td><strong>${account.account_name || 'Unknown'}</strong></td>
                <td>${getTypeBadge(account.account_type || 'Unknown', account.color_code)}</td>
                <td class="${balance >= 0 ? 'text-success' : 'text-danger'}">
                    <strong>${formatCurrency(balance, currency)}</strong>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" style="font-size:.65rem;">${currency}</span>
                </td>
                <td class="${growthAmount >= 0 ? 'text-success' : 'text-danger'}">
                    ${formatCurrency(growthAmount, currency)}
                </td>
                <td class="${growthPercentage >= 0 ? 'text-success' : 'text-danger'}">
                    ${growthPercentage.toFixed(2)}%
                </td>
                <td>
                    <span class="badge ${hasData ? 'bg-success' : 'bg-secondary'}">
                        ${hasData ? 'Has Data' : 'No Data'}
                    </span>
                </td>
                <td>${account.notes || '-'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary"  onclick="viewAccountHistory(${account.id})" title="View History">
                        <i class="fas fa-history"></i>
                    </button>
                </td>
            </tr>
        `;
        }).join('');
    }

    // Hide month data
    function hideMonthData() {
        document.getElementById('monthSummaryCards').style.display = 'none';
        document.getElementById('monthBalancesTable').style.display = 'none';
        document.getElementById('noMonthDataMessage').style.display = 'block';
    }

    // Load current month
    function loadCurrentMonth() {
        const currentMonth = new Date().toISOString().slice(0, 7) + '-01';
        document.getElementById('viewMonth').value = currentMonth;
        loadBalancesByMonth();
    }

    // Load previous month
    function loadPreviousMonth() {
        const now = new Date();
        now.setMonth(now.getMonth() - 1);
        const previousMonth = now.toISOString().slice(0, 7) + '-01';
        document.getElementById('viewMonth').value = previousMonth;
        loadBalancesByMonth();
    }

    // Show month comparison
    async function showMonthComparison() {
        await loadAvailableMonths();
        new bootstrap.Modal(document.getElementById('monthComparisonModal')).show();
    }

    // Load month comparison
    async function loadMonthComparison() {
        const startMonth = document.getElementById('startMonth').value;
        const endMonth = document.getElementById('endMonth').value;

        if (!startMonth || !endMonth) {
            showToast('Please select both start and end months', 'danger');
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}?action=monthly_comparison&start_month=${startMonth}&end_month=${endMonth}&to=${preferredCurrency}`);
            const data = await response.json();

            if (data.error) {
                showToast(data.error, 'danger');
                return;
            }

            displayComparisonResults(data, startMonth, endMonth);

        } catch (error) {
            console.error('Error loading comparison:', error);
            showToast('Failed to load comparison data', 'danger');
        }
    }

    // Display comparison results
    function displayComparisonResults(data, startMonth, endMonth) {
        const container = document.getElementById('comparisonResults');
        const rows = data.data || [];
        const displayCur = data.display_currency || preferredCurrency;

        if (rows.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No comparison data available for selected months.</div>';
            return;
        }

        // Totals are computed server-side from converted (display-currency)
        // figures, so mixed-currency accounts add up correctly.
        const totalStartBalance = data.totals ? data.totals.start_balance : rows.reduce((sum, acc) => sum + (parseFloat(acc.start_balance_converted ?? acc.start_balance) || 0), 0);
        const totalEndBalance = data.totals ? data.totals.end_balance : rows.reduce((sum, acc) => sum + (parseFloat(acc.end_balance_converted ?? acc.end_balance) || 0), 0);
        const totalChange = totalEndBalance - totalStartBalance;
        const totalChangePercent = totalStartBalance > 0 ? (totalChange / totalStartBalance * 100) : 0;

        container.innerHTML = `
        <div class="alert alert-primary">
            <h6>Overall Summary <span class="badge bg-primary-subtle text-primary-emphasis">${displayCur}</span></h6>
            <div class="row">
                <div class="col-md-3">
                    <strong>Start (${formatMonthDisplay(startMonth)}):</strong><br>
                    ${formatCurrency(totalStartBalance, displayCur)}
                </div>
                <div class="col-md-3">
                    <strong>End (${formatMonthDisplay(endMonth)}):</strong><br>
                    ${formatCurrency(totalEndBalance, displayCur)}
                </div>
                <div class="col-md-3">
                    <strong>Change:</strong><br>
                    <span class="${totalChange >= 0 ? 'text-success' : 'text-danger'}">
                        ${formatCurrency(totalChange, displayCur)}
                    </span>
                </div>
                <div class="col-md-3">
                    <strong>Change %:</strong><br>
                    <span class="${totalChangePercent >= 0 ? 'text-success' : 'text-danger'}">
                        ${totalChangePercent.toFixed(2)}%
                    </span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Start Balance</th>
                        <th>End Balance</th>
                        <th>Change</th>
                        <th>Change %</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(account => {
            const currency = account.currency || 'USD';
            const startBalance = parseFloat(account.start_balance) || 0;
            const endBalance = parseFloat(account.end_balance) || 0;
            const balanceChange = parseFloat(account.balance_change) || 0;
            const percentageChange = parseFloat(account.percentage_change) || 0;

            return `
                            <tr>
                                <td><strong>${account.account_name || 'Unknown'}</strong>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" style="font-size:.65rem;">${currency}</span>
                                </td>
                                <td>${formatCurrency(startBalance, currency)}</td>
                                <td>${formatCurrency(endBalance, currency)}</td>
                                <td class="${balanceChange >= 0 ? 'text-success' : 'text-danger'}">
                                    ${formatCurrency(balanceChange, currency)}
                                </td>
                                <td class="${percentageChange >= 0 ? 'text-success' : 'text-danger'}">
                                    ${percentageChange.toFixed(2)}%
                                </td>
                            </tr>
                        `;
        }).join('')}
                </tbody>
            </table>
        </div>
    `;
    }

    // Helper functions
    function formatMonthDisplay(monthString) {
        const date = new Date(monthString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
    }

    async function viewAccountHistory(accountId) {
        try {
            // Get account details
            const account = accountsData.find(acc => acc.id == accountId);
            if (!account) {
                showToast('Account not found', 'danger');
                return;
            }

            // Fetch balance history for this account
            const response = await fetch(`${API_BASE_URL}?action=balance_history&account_id=${accountId}`);
            const historyData = await response.json();

            // Create and show modal
            showAccountHistoryModal(account, historyData);
        } catch (error) {
            console.error('Error loading account history:', error);
            showToast('Failed to load account history', 'danger');
        }
    }

    function showAccountHistoryModal(account, historyData) {
        // Create modal HTML
        const modalHtml = `
    <div class='modal fade' id='accountHistoryModal' tabindex='-1'>
        <div class='modal-dialog modal-xl'>
            <div class='modal-content'>
                <div class='modal-header px-5 position-relative modal-shape-header bg-shape'>
                    <div class='position-relative z-1'>
                      <h4 class='mb-0 text-white' id='authentication-modal-label'>Balance History - ${account.account_name}</h4>
                    </div>
                    <div data-bs-theme='dark'>
                      <button class='btn-close position-absolute top-0 end-0 mt-2 me-2' data-bs-dismiss='modal' aria-label='Close'></button>
                    </div>
                </div>
                <div class='modal-body'>
                    <!-- Account Summary -->
                    <div class='row mb-4'>
                        <div class='col-md-3'>
                            <div class='card bg-body-secondary'>
                                <div class='card-body text-center'>
                                    <h6>Current Balance</h6>
                                    <h4>${formatCurrency(account.balance, account.currency)}</h4>
                                </div>
                            </div>
                        </div>
                        <div class='col-md-3'>
                            <div class='card bg-body-secondary'>
                                <div class='card-body text-center'>
                                    <h6>Account Type</h6>
                                    <h5>${account.account_type}</h5>
                                </div>
                            </div>
                        </div>
                        <div class='col-md-3'>
                            <div class='card bg-body-secondary'>
                                <div class='card-body text-center'>
                                    <h6>Bank</h6>
                                    <h5>${account.bank_name || 'N/A'}</h5>
                                </div>
                            </div>
                        </div>
                        <div class='col-md-3'>
                            <div class='card bg-body-secondary'>
                                <div class='card-body text-center'>
                                    <h6>Status</h6>
                                    <h5>${account.status}</h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- History Chart -->
                    <div class='card bg-line-chart-gradient mb-4'>
                        <div class='card-header'>
                            <h6 class='mb-0'><i class='fas fa-chart-line me-2'></i>Balance Trend</h6>
                        </div>
                        <div class='card-body'>
                            <div id='accountHistoryChart' ></div>
                        </div>
                    </div>

                    <!-- History Table -->
                    <div class='card'>
                        <div class='card-header d-flex justify-content-between align-items-center'>
                            <h6 class='mb-0'><i class='fas fa-table me-2'></i>Monthly Balance Records</h6>
                        </div>
                        <div class='card-body p-0'>
                            <div class='table-responsive'>
                                <table class='table table-hover mb-0'>
                                    <thead class='table-light'>
                                        <tr>
                                            <th>Month/Year</th>
                                            <th>Balance</th>
                                            <th>Growth Amount</th>
                                            <th>Growth %</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody id='historyTableBody'>
                                        ${renderHistoryTableRows(historyData, account.currency)}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class='modal-footer'>
                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                    <button type='button' class='btn btn-primary' onclick='exportAccountHistory(${account.id})'>
                        <i class='fas fa-download me-1'></i>Export History
                    </button>
                </div>
            </div>
        </div>
    </div>`;

        // Remove existing modal if any
        const existingModal = document.getElementById('accountHistoryModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('accountHistoryModal'));
        modal.show();

        // Render chart after modal is shown
        setTimeout(() => {
            renderAccountHistoryChart(historyData, account.currency);
        }, 300);
    }

    function renderHistoryTableRows(historyData, currency) {
        if (!historyData || historyData.length === 0) {
            return '<tr><td colspan="6" class="text-center text-muted py-4">No balance history found</td></tr>';
        }

        return historyData.map(record => {
            const growthClass = record.growth_amount >= 0 ? 'text-success' : 'text-danger';
            const growthIcon = record.growth_amount >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down';
            const rowCurrency = record.currency || currency || 'USD';

            return `
        <tr>
            <td><strong>${formatMonthYear(record.month_year)}</strong></td>
            <td><strong>${formatCurrency(record.balance, rowCurrency)}</strong></td>
            <td class="${growthClass}">
                <i class="${growthIcon} me-1"></i>
                ${formatCurrency(Math.abs(record.growth_amount), rowCurrency)}
            </td>
            <td class="${growthClass}">
                <strong>${record.growth_percentage > 0 ? '+' : ''}${record.growth_percentage}%</strong>
            </td>
            <td>${record.notes || '-'}</td>
        </tr>`;
        }).join('');
    }

    function renderAccountHistoryChart(historyData, currency) {
        if (!historyData || historyData.length === 0) return;
        currency = currency || (historyData[0] && historyData[0].currency) || 'USD';

        // Sort data by month_year
        const sortedData = historyData.sort((a, b) => new Date(a.month_year) - new Date(b.month_year));

        const labels = sortedData.map(record => formatMonthYear(record.month_year));
        const balanceData = sortedData.map(record => parseFloat(record.balance));
        const growthData = sortedData.map(record => parseFloat(record.growth_amount || 0));

        const options = {
            chart: {
                type: 'line',
                height: 350,
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: true,
                        zoom: true,
                        zoomin: true,
                        zoomout: true,
                        reset: true
                    }
                },
                zoom: {
                    enabled: true
                }
            },
            series: [
                {
                    name: 'Balance',
                    data: balanceData
                }
            ],
            xaxis: {
                categories: labels,
                title: {
                    text: 'Month-Year',
                    style: { color: '#ffffff' }
                },
                labels: {
                    style: {
                        colors: '#ffffff'
                    }
                }
            },
            yaxis: [
                {
                    title: {
                        text: 'Balance',
                        style: { color: '#ffffff' }
                    },
                    labels: {
                        style: {
                            colors: '#ffffff'
                        },
                        formatter: function (val) {
                            return formatCurrency(val, currency);
                        }
                    }
                }
            ],
            markers: {
                size: 4,
                colors: ['#06771e', '#28a745'],
                strokeWidth: 2,
                hover: {
                    size: 6
                }
            },
            colors: ['#0ecd41', '#690917'],
            stroke: {
                width: [5, 4],  // Increase line thickness for both series
                curve: 'smooth'
            },
            grid: {
                borderColor: 'rgba(255, 255, 255, 0.05)', // almost invisible white
                strokeDashArray: 4, // dashed style
            },
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: function (val) {
                        return formatCurrency(val, currency);
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'center',
                offsetY: -10,
                labels: {
                    colors: '#ffffff'
                }
            }
        };

        const accountHistoryChart = new ApexCharts(document.querySelector('#accountHistoryChart'), options);
        accountHistoryChart.render();
    }

    function exportMonthData() {
        const selectedMonth = document.getElementById('viewMonth').value;
        if (!selectedMonth) {
            showToast('Please select a month first', 'danger');
            return;
        }

        // Export functionality
        alert('Export functionality for month: ' + formatMonthDisplay(selectedMonth));
    }

    // Safe number formatting helper
    function safeToFixed(value, decimals = 2) {
        const num = parseFloat(value);
        return isNaN(num) ? '0.00' : num.toFixed(decimals);
    }

    // View account details
    async function viewDetails(accountId) {
        try {
            // Find account in current data
            currentAccount = accountsData.find(acc => acc.id == accountId);
            if (!currentAccount) {
                showToast('Account not found', 'danger');
                return;
            }

            // Populate account information
            document.getElementById('viewAccountModalTitle').textContent = `${currentAccount.account_name} Account Details`;
            document.getElementById('viewAccountName').textContent = currentAccount.account_name;
            document.getElementById('viewAccountType').innerHTML = getTypeBadge(currentAccount.account_type, currentAccount.color_code);
            document.getElementById('viewAccountStatus').innerHTML = getStatusBadge(currentAccount.status);
            document.getElementById('viewAccountCreated').textContent = formatDate(currentAccount.created_at);
            document.getElementById('viewAccountUpdated').textContent = formatDate(currentAccount.last_updated);

            // Financial information — always the account's own native currency
            document.getElementById('viewCurrentBalance').textContent = formatCurrency(currentAccount.balance, currentAccount.currency);
            document.getElementById('viewCurrentBalance').className = currentAccount.balance >= 0 ? 'fw-bold text-success' : 'fw-bold text-danger';
            document.getElementById('viewInterestRate').textContent = (currentAccount.interest_rate || 0) + '%';
            document.getElementById('viewMinimumBalance').textContent = formatCurrency(currentAccount.minimum_balance || 0, currentAccount.currency);

            // Growth information (if available)
            const growthAmount = currentAccount.growth_amount || 0;
            const growthPercent = currentAccount.growth_percentage || 0;
            document.getElementById('viewGrowthAmount').textContent = formatCurrency(growthAmount, currentAccount.currency);
            document.getElementById('viewGrowthAmount').className = growthAmount >= 0 ? 'fw-bold text-success' : 'fw-bold text-danger';
            document.getElementById('viewGrowthPercent').textContent = growthPercent.toFixed(2) + '%';

            // Bank information
            document.getElementById('viewBankName').textContent = currentAccount.bank_name || 'Not specified';
            document.getElementById('viewAccountNumber').textContent = currentAccount.account_number || 'Not specified';

            // Notes
            document.getElementById('viewAccountNotes').textContent = currentAccount.notes || 'No notes available';
            document.getElementById('viewAccountNotes').className = currentAccount.notes ? '' : 'text-muted';

            // Alerts — same signals surfaced in the Month Overview's
            // "Accounts Needing Attention" list (dormancy risk, low
            // balance, negative growth), computed server-side per account.
            renderViewAccountAlerts(currentAccount.alerts || []);

            // Load balance history
            await loadAccountBalanceHistory(accountId);

            // Show modal
            new bootstrap.Modal(document.getElementById('viewAccountModal')).show();

        } catch (error) {
            console.error('Error viewing account details:', error);
            showToast('Failed to load account details', 'danger');
        }
    }

    // Show any active alerts (dormancy risk, low balance, negative growth)
    // for the account currently open in the detail modal.
    function renderViewAccountAlerts(alerts) {
        const container = document.getElementById('viewAccountAlerts');

        if (!alerts || alerts.length === 0) {
            container.style.display = 'none';
            container.innerHTML = '';
            return;
        }

        const iconFor = (type) => {
            if (type === 'dormant_stale' || type === 'dormant_stagnant') return '<i class="fas fa-moon me-2"></i>';
            if (type === 'low_balance') return '<i class="fas fa-battery-quarter me-2"></i>';
            if (type === 'negative_growth') return '<i class="fas fa-arrow-down me-2"></i>';
            return '<i class="fas fa-info-circle me-2"></i>';
        };
        const alertClassFor = (type) => {
            if (type === 'dormant_stale' || type === 'dormant_stagnant') return 'alert-warning';
            if (type === 'low_balance') return 'alert-danger';
            if (type === 'negative_growth') return 'alert-danger';
            return 'alert-info';
        };

        container.style.display = 'block';
        container.innerHTML = `
            <div class="mb-2"><strong><i class="fas fa-exclamation-triangle me-2"></i>This account needs attention:</strong></div>
            ${alerts.map(a => `
                <div class="alert ${alertClassFor(a.type)} py-2 px-3 mb-2 d-flex align-items-center" role="alert">
                    ${iconFor(a.type)}<span>${a.message}</span>
                </div>
            `).join('')}
        `;
    }

    // Load account balance history
    async function loadAccountBalanceHistory(accountId, limit = 5) {
        try {
            const response = await fetch(`${API_BASE_URL}?action=get_balance_history&account_id=${accountId}&limit=${limit}`);
            const history = await response.json();

            const tbody = document.getElementById('viewBalanceHistory');

            if (history.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No balance history available</td></tr>';
                return;
            }

            tbody.innerHTML = history.map(record => {
                const growthClass = record.growth_amount >= 0 ? 'text-success' : 'text-danger';
                const growthIcon = record.growth_amount >= 0 ? '↗️' : '↘️';
                const currency = record.currency || (currentAccount ? currentAccount.currency : null) || 'USD';

                return `
                <tr>
                    <td>${formatMonthYear(record.month_year)}</td>
                    <td class="fw-bold">${formatCurrency(record.balance, currency)}</td>
                    <td class="${growthClass}">
                        ${growthIcon} ${formatCurrency(Math.abs(record.growth_amount), currency)}
                    </td>
                    <td class="${growthClass}">${record.growth_percentage.toFixed(2)}%</td>
                    <td><small>${record.notes || '-'}</small></td>
                </tr>
            `;
            }).join('');

        } catch (error) {
            console.error('Error loading balance history:', error);
            document.getElementById('viewBalanceHistory').innerHTML =
                '<tr><td colspan="5" class="text-center text-danger">Error loading balance history</td></tr>';
        }
    }

    // Edit account from view modal
    function editAccountFromView() {
        bootstrap.Modal.getInstance(document.getElementById('viewAccountModal')).hide();
        setTimeout(() => editAccount(currentAccount.id), 300);
    }

    // Edit account details
    async function editAccount(accountId) {
        try {
            // Find account in current data
            currentAccount = accountsData.find(acc => acc.id == accountId);
            if (!currentAccount) {
                showToast('Account not found', 'danger');
                return;
            }

            // Load account types for dropdown
            await loadAccountTypesForEdit();
            populateCurrencyDropdown('editAccountCurrency');

            const editCurrencySelect = document.getElementById('editAccountCurrency');
            editCurrencySelect.onchange = function() {
                document.getElementById('editMinimumBalanceCurrencyPrefix').textContent = currencySymbol(this.value) || '$';
            };

            // Populate form fields
            document.getElementById('editAccountId').value = currentAccount.id;
            document.getElementById('editAccountName').value = currentAccount.account_name;
            document.getElementById('editAccountType').value = currentAccount.account_type;
            document.getElementById('editAccountCurrency').value = currentAccount.currency || 'KES';
            document.getElementById('editMinimumBalanceCurrencyPrefix').textContent = currencySymbol(currentAccount.currency) || '$';
            document.getElementById('editBankName').value = currentAccount.bank_name || '';
            document.getElementById('editAccountNumber').value = currentAccount.account_number || '';
            document.getElementById('editInterestRate').value = currentAccount.interest_rate || 0;
            document.getElementById('editMinimumBalance').value = currentAccount.minimum_balance || 0;
            document.getElementById('editAccountStatus').value = currentAccount.status;
            document.getElementById('editAccountNotes').value = currentAccount.notes || '';

            // Display current balance info (read-only) — always shown in
            // the account's own currency, never the converted/preferred one.
            document.getElementById('editCurrentBalanceDisplay').textContent = formatCurrency(currentAccount.balance, currentAccount.currency);
            document.getElementById('editCurrentBalanceDisplay').className = currentAccount.balance >= 0 ? 'fw-bold text-success' : 'fw-bold text-danger';
            document.getElementById('editLastBalanceUpdate').textContent = currentAccount.last_balance_update ?
                formatDate(currentAccount.last_balance_update) : 'Never updated';

            // Show modal
            new bootstrap.Modal(document.getElementById('editAccountModal')).show();

        } catch (error) {
            console.error('Error loading account for editing:', error);
            showToast('Failed to load account for editing', 'danger');
        }
    }

    // Load account types for edit modal
    async function loadAccountTypesForEdit() {
        try {
            const response = await fetch(`${API_BASE_URL}?action=account_types`);
            const types = await response.json();

            const select = document.getElementById('editAccountType');
            select.innerHTML = '<option value="">Select Account Type</option>';

            types.forEach(type => {
                const option = document.createElement('option');
                option.value = type.type_name;
                option.textContent = type.type_name;
                option.setAttribute('data-color', type.color_code);
                option.setAttribute('data-icon', type.icon_class);
                select.appendChild(option);
            });

        } catch (error) {
            console.error('Error loading account types:', error);
            showToast('Failed to load account types', 'danger');
        }
    }

    // Save account changes
    async function saveAccountChanges() {
        try {
            // Get form data
            const formData = {
                id: document.getElementById('editAccountId').value,
                account_name: document.getElementById('editAccountName').value.trim(),
                account_type: document.getElementById('editAccountType').value,
                currency: document.getElementById('editAccountCurrency').value,
                bank_name: document.getElementById('editBankName').value.trim(),
                account_number: document.getElementById('editAccountNumber').value.trim(),
                interest_rate: parseFloat(document.getElementById('editInterestRate').value) || 0,
                minimum_balance: parseFloat(document.getElementById('editMinimumBalance').value) || 0,
                status: document.getElementById('editAccountStatus').value,
                notes: document.getElementById('editAccountNotes').value.trim()
            };

            // Validation
            if (!formData.account_name) {
                showToast('Account name is required', 'danger');
                document.getElementById('editAccountName').focus();
                return;
            }

            if (!formData.account_type) {
                showToast('Account type is required', 'danger');
                document.getElementById('editAccountType').focus();
                return;
            }

            // Show loading state
            const saveButton = document.querySelector('[onclick="saveAccountChanges()"]');
            const originalText = saveButton.innerHTML;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            saveButton.disabled = true;

            // Send update request
            const response = await fetch(`${API_BASE_URL}?action=update`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (response.ok && result.success) {
                // Success
                bootstrap.Modal.getInstance(document.getElementById('editAccountModal')).hide();
                await loadDashboardData(); // Refresh all data
                showToast('Account updated successfully!', 'success');
            } else {
                showToast(result.message || 'Failed to update account', 'danger');
            }

        } catch (error) {
            console.error('Error updating account:', error);
            showToast('Failed to update account. Please try again.', 'danger');
        } finally {
            // Restore button
            const saveButton = document.querySelector('[onclick="saveAccountChanges()"]');
            if (saveButton) {
                saveButton.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                saveButton.disabled = false;
            }
        }
    }

    // Delete account from edit modal
    // Delete Account Variables
    let deleteAccountData = null;
    let deleteCountdownTimer = null;
    let deleteCountdownSeconds = 0;

    // Open delete confirmation modal from edit modal
    async function deleteAccountFromEdit() {
        const accountId = document.getElementById('editAccountId').value;
        const accountName = document.getElementById('editAccountName').value;
        const accountType = document.getElementById('editAccountType').options[document.getElementById('editAccountType').selectedIndex]?.text || '-';
        const bankName = document.getElementById('editBankName').value || '-';
        const currentBalance = document.getElementById('editCurrentBalanceDisplay').textContent;

        // Store account data for deletion
        deleteAccountData = {
            id: accountId,
            name: accountName,
            type: accountType,
            bank: bankName,
            balance: currentBalance
        };

        // Fetch balance history count
        let historyCount = 0;
        try {
            const response = await fetch(`${API_BASE_URL}?action=get_balance_history&account_id=${accountId}`);
            const history = await response.json();
            historyCount = history.length || 0;
        } catch (error) {
            console.error('Error fetching history count:', error);
        }

        // Populate modal with account details
        document.getElementById('deleteAccountName').textContent = accountName;
        document.getElementById('deleteAccountType').textContent = accountType;
        document.getElementById('deleteAccountBalance').textContent = currentBalance;
        document.getElementById('deleteAccountBank').textContent = bankName;
        document.getElementById('deleteAccountNameHint').textContent = accountName;
        document.getElementById('deleteHistoryCount').textContent = historyCount;

        // Reset form state
        document.getElementById('deleteConfirmInput').value = '';
        document.getElementById('deleteConfirmInput').classList.remove('is-valid', 'is-invalid');
        document.getElementById('deleteUnderstandCheckbox').checked = false;
        document.getElementById('deleteReason').value = '';
        document.getElementById('confirmDeleteBtn').disabled = true;
        document.getElementById('deleteCountdown').textContent = '';

        // Clear any existing countdown
        if (deleteCountdownTimer) {
            clearInterval(deleteCountdownTimer);
            deleteCountdownTimer = null;
        }

        // Hide edit modal and show delete confirmation modal
        const editModal = bootstrap.Modal.getInstance(document.getElementById('editAccountModal'));
        if (editModal) {
            editModal.hide();
        }

        // Small delay to allow edit modal to close
        setTimeout(() => {
            new bootstrap.Modal(document.getElementById('deleteAccountModal')).show();
        }, 300);
    }

    // Copy account name to clipboard
    function copyAccountNameToClipboard() {
        if (deleteAccountData && deleteAccountData.name) {
            navigator.clipboard.writeText(deleteAccountData.name).then(() => {
                showToast('Account name copied to clipboard!', 'success');
            }).catch(err => {
                console.error('Failed to copy:', err);
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = deleteAccountData.name;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showToast('Account name copied to clipboard!', 'success');
            });
        }
    }

    // Download balance history before deletion
    async function downloadBeforeDelete() {
        if (!deleteAccountData) return;

        try {
            const response = await fetch(`${API_BASE_URL}?action=get_balance_history&account_id=${deleteAccountData.id}`);
            const history = await response.json();

            if (history.length === 0) {
                showToast('No balance history to export', 'danger');
                return;
            }

            // Create CSV content
            const headers = ['Month/Year', 'Balance', 'Growth Amount', 'Growth %', 'Notes', 'Date Added'];
            const csvContent = [
                headers.join(','),
                ...history.map(record => {
                    return [
                        `"${formatMonthYear(record.month_year)}"`,
                        `"${record.balance}"`,
                        `"${record.growth_amount}"`,
                        `"${record.growth_percentage}%"`,
                        `"${record.notes || ''}"`,
                        `"${formatDate(record.created_at)}"`
                    ].join(',');
                })
            ].join('\n');

            // Download CSV
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${deleteAccountData.name.replace(/[^a-z0-9]/gi, '_')}_balance_history_backup.csv`;
            a.click();
            window.URL.revokeObjectURL(url);

            showToast('Balance history exported successfully!', 'success');
        } catch (error) {
            console.error('Error exporting history:', error);
            showToast('Failed to export balance history', 'danger');
        }
    }

    // Validate delete confirmation inputs
    function validateDeleteConfirmation() {
        const confirmInput = document.getElementById('deleteConfirmInput');
        const checkbox = document.getElementById('deleteUnderstandCheckbox');
        const deleteBtn = document.getElementById('confirmDeleteBtn');
        const countdownEl = document.getElementById('deleteCountdown');

        const nameMatches = deleteAccountData &&
            confirmInput.value.trim() === deleteAccountData.name.trim();
        const checkboxChecked = checkbox.checked;

        // Update input validation state
        if (confirmInput.value.length > 0) {
            if (nameMatches) {
                confirmInput.classList.remove('is-invalid');
                confirmInput.classList.add('is-valid');
            } else {
                confirmInput.classList.remove('is-valid');
                confirmInput.classList.add('is-invalid');
            }
        } else {
            confirmInput.classList.remove('is-valid', 'is-invalid');
        }

        // Enable/disable delete button
        if (nameMatches && checkboxChecked) {
            // Start countdown if not already started
            if (!deleteCountdownTimer) {
                deleteCountdownSeconds = 5;
                deleteBtn.disabled = true;
                countdownEl.textContent = `Button will be enabled in ${deleteCountdownSeconds}s...`;

                deleteCountdownTimer = setInterval(() => {
                    deleteCountdownSeconds--;
                    if (deleteCountdownSeconds > 0) {
                        countdownEl.textContent = `Button will be enabled in ${deleteCountdownSeconds}s...`;
                    } else {
                        clearInterval(deleteCountdownTimer);
                        deleteCountdownTimer = null;
                        countdownEl.textContent = '';
                        deleteBtn.disabled = false;
                    }
                }, 1000);
            }
        } else {
            // Reset countdown
            if (deleteCountdownTimer) {
                clearInterval(deleteCountdownTimer);
                deleteCountdownTimer = null;
            }
            deleteBtn.disabled = true;
            countdownEl.textContent = '';
        }
    }

    // Add event listeners for delete confirmation validation
    document.addEventListener('DOMContentLoaded', function() {
        const confirmInput = document.getElementById('deleteConfirmInput');
        const checkbox = document.getElementById('deleteUnderstandCheckbox');

        if (confirmInput) {
            confirmInput.addEventListener('input', validateDeleteConfirmation);
            confirmInput.addEventListener('paste', function(e) {
                // Allow paste and validate after a small delay
                setTimeout(validateDeleteConfirmation, 10);
            });
        }

        if (checkbox) {
            checkbox.addEventListener('change', validateDeleteConfirmation);
        }

        // Reset state when modal is closed
        const deleteModal = document.getElementById('deleteAccountModal');
        if (deleteModal) {
            deleteModal.addEventListener('hidden.bs.modal', function() {
                if (deleteCountdownTimer) {
                    clearInterval(deleteCountdownTimer);
                    deleteCountdownTimer = null;
                }
                deleteAccountData = null;
            });
        }
    });

    // Cancel delete and return to edit modal
    function cancelDeleteAccount() {
        if (deleteCountdownTimer) {
            clearInterval(deleteCountdownTimer);
            deleteCountdownTimer = null;
        }

        const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteAccountModal'));
        if (deleteModal) {
            deleteModal.hide();
        }

        // Optionally re-open edit modal
        if (deleteAccountData && deleteAccountData.id) {
            setTimeout(() => {
                // Find and edit the account again
                const account = accountsData.find(a => a.id == deleteAccountData.id);
                if (account) {
                    editAccount(account);
                }
            }, 300);
        }
    }

    // Confirm and execute delete
    async function confirmDeleteAccount() {
        if (!deleteAccountData) {
            showToast('No account selected for deletion', 'danger');
            return;
        }

        const confirmInput = document.getElementById('deleteConfirmInput');
        const checkbox = document.getElementById('deleteUnderstandCheckbox');

        // Final validation
        if (confirmInput.value.trim() !== deleteAccountData.name.trim()) {
            showToast('Account name does not match. Please type it exactly.', 'danger');
            return;
        }

        if (!checkbox.checked) {
            showToast('Please confirm that you understand this action is irreversible.', 'danger');
            return;
        }

        // Get deletion reason
        const reason = document.getElementById('deleteReason').value;

        // Disable button to prevent double-click
        const deleteBtn = document.getElementById('confirmDeleteBtn');
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';

        try {
            const response = await fetch(`${API_BASE_URL}?id=${deleteAccountData.id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    reason: reason,
                    confirmed: true
                })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                // Close modal
                const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteAccountModal'));
                if (deleteModal) {
                    deleteModal.hide();
                }

                // Clear data
                deleteAccountData = null;

                // Refresh dashboard
                await loadDashboardData();

                // Show success message
                showToast('Account has been permanently deleted.', 'success');

            } else {
                showToast(result.message || 'Failed to delete account', 'danger');
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i> Permanently Delete Account';
            }

        } catch (error) {
            console.error('Error deleting account:', error);
            showToast('Failed to delete account. Please try again.', 'danger');
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i> Permanently Delete Account';
        }
    }

    // View full balance history modal
    async function viewBalanceHistoryModal() {
        if (!currentAccount) return;

        try {
            // Set account name in modal title
            document.getElementById('historyAccountName').textContent = currentAccount.account_name;

            // Load full balance history
            const response = await fetch(`${API_BASE_URL}?action=get_balance_history&account_id=${currentAccount.id}`);
            const history = await response.json();

            // Populate table
            const tbody = document.getElementById('fullBalanceHistory');
            if (history.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No balance history available</td></tr>';
            } else {
                tbody.innerHTML = history.map(record => {
                    const growthClass = record.growth_amount >= 0 ? 'text-success' : 'text-danger';
                    const growthIcon = record.growth_amount >= 0 ? '↗️' : '↘️';
                    const currency = record.currency || currentAccount.currency || 'USD';

                    return `
                    <tr>
                        <td class="fw-bold">${formatMonthYear(record.month_year)}</td>
                        <td class="fw-bold">${formatCurrency(record.balance, currency)}</td>
                        <td class="${growthClass}">
                            ${growthIcon} ${formatCurrency(Math.abs(record.growth_amount), currency)}
                        </td>
                        <td class="${growthClass}">${record.growth_percentage.toFixed(2)}%</td>
                        <td><small>${record.notes || '-'}</small></td>
                        <td><small>${formatDate(record.created_at)}</small></td>
                    </tr>
                `;
                }).join('');
            }

            // Render balance chart
            renderAccountBalanceChart(history, currentAccount.currency);

            // Show modal
            new bootstrap.Modal(document.getElementById('balanceHistoryModal')).show();

        } catch (error) {
            console.error('Error loading balance history:', error);
            showToast('Failed to load balance history', 'danger');
        }
    }

    // Render account balance chart
    function renderAccountBalanceChart(history, currency) {
        if (!history || history.length === 0) return;
        currency = currency || (currentAccount && currentAccount.currency) || (history[0] && history[0].currency) || 'USD';

        // Destroy existing chart if needed
        if (accountBalanceChartInstance) {
            accountBalanceChartInstance.destroy();
        }

        // Prepare data
        const labels = history.map(record => formatMonthYear(record.month_year)).reverse();
        const balanceData = history.map(record => parseFloat(record.balance)).reverse();

        const options = {
            chart: {
                type: 'line',
                height: 350,
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: true,
                        zoom: true,
                        zoomin: true,
                        zoomout: true,
                        reset: true
                    }
                },
                zoom: {
                    enabled: true
                }
            },
            series: [
                {
                    name: 'Balance',
                    data: balanceData
                }
            ],
            xaxis: {
                categories: labels,
                title: {
                    text: 'Month-Year',
                    style: { color: '#ffffff' }
                },
                labels: {
                    style: {
                        colors: '#ffffff'
                    }
                }
            },
            yaxis: [
                {
                    title: {
                        text: 'Balance',
                        style: { color: '#ffffff' }
                    },
                    labels: {
                        style: {
                            colors: '#ffffff'
                        },
                        formatter: function (val) {
                            return formatCurrency(val, currency);
                        }
                    }
                }
            ],
            markers: {
                size: 4,
                colors: ['#06771e', '#28a745'],
                strokeWidth: 2,
                hover: {
                    size: 6
                }
            },
            colors: ['#0ecd41', '#690917'],
            stroke: {
                width: [5, 4],  // Increase line thickness for both series
                curve: 'smooth'
            },
            grid: {
                borderColor: 'rgba(255, 255, 255, 0.05)', // almost invisible white
                strokeDashArray: 4, // dashed style
            },
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: function (val) {
                        return formatCurrency(val, currency);
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'center',
                offsetY: -10,
                labels: {
                    colors: '#ffffff'
                }
            }
        };

        // Render new chart
        accountBalanceChartInstance = new ApexCharts(document.querySelector('#accountBalanceChart'), options);
        accountBalanceChartInstance.render();
    }

    // Export balance history
    function exportBalanceHistory() {
        if (!currentAccount) return;

        // Get balance history data from table
        const table = document.getElementById('fullBalanceHistory');
        const rows = Array.from(table.querySelectorAll('tr'));

        if (rows.length === 0 || rows[0].cells.length === 1) {
            showToast('No data to export', 'danger');
            return;
        }

        // Create CSV content
        const headers = ['Month/Year', 'Balance', 'Growth Amount', 'Growth %', 'Notes', 'Date Added'];
        const csvContent = [
            headers.join(','),
            ...rows.map(row => {
                const cells = Array.from(row.cells);
                return cells.map(cell => `"${cell.textContent.trim()}"`).join(',');
            })
        ].join('\n');

        // Download CSV
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${currentAccount.account_name}_balance_history.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
    }

    // Utility function to format month/year
    function formatMonthYear(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
    }

    // =====================================================
    // SAVINGS BREAKDOWN FUNCTIONALITY
    // =====================================================

    // Store savings breakdown data globally for export
    let savingsBreakdownData = null;

    // Show Savings Breakdown Modal
    async function showSavingsBreakdown() {
        // Show modal first with loading state
        const modal = new bootstrap.Modal(document.getElementById('savingsBreakdownModal'));
        modal.show();

        // Reset states
        document.getElementById('savingsBreakdownLoading').style.display = 'block';
        document.getElementById('savingsBreakdownContent').style.display = 'none';
        document.getElementById('savingsBreakdownNoData').style.display = 'none';

        try {
            const response = await fetch(`${API_BASE_URL}?action=savings_breakdown`);
            const data = await response.json();

            savingsBreakdownData = data; // Store for export

            if (!data.success || data.accounts.length === 0) {
                document.getElementById('savingsBreakdownLoading').style.display = 'none';
                document.getElementById('savingsBreakdownNoData').style.display = 'block';
                return;
            }

            // Update header with month
            document.getElementById('savingsBreakdownMonth').textContent = data.current_month_display;

            // Update summary cards
            const totalGrowth = data.totals.total_growth;
            const growthClass = totalGrowth >= 0 ? 'text-success' : 'text-danger';
            const growthSign = totalGrowth >= 0 ? '+' : '';

            document.getElementById('totalSavingsGrowth').innerHTML =
                `<span class="${growthClass}">${growthSign}${formatCurrency(totalGrowth)}</span>`;
            document.getElementById('totalCurrentSavings').textContent = formatCurrency(data.totals.current_balance);
            document.getElementById('totalPreviousSavings').textContent = formatCurrency(data.totals.previous_balance);

            const growthPct = data.totals.growth_percentage;
            const pctClass = growthPct >= 0 ? 'text-success' : 'text-danger';
            const pctSign = growthPct >= 0 ? '+' : '';
            document.getElementById('savingsGrowthRate').innerHTML =
                `<span class="${pctClass}">${pctSign}${growthPct.toFixed(2)}%</span>`;

            // Render type summary table
            renderSavingsTypeSummary(data.type_summary);

            // Render individual accounts table
            renderSavingsBreakdownTable(data.accounts, data.totals);

            // Update account count badge
            document.getElementById('savingsAccountCount').textContent = `${data.accounts.length} account${data.accounts.length !== 1 ? 's' : ''}`;

            // Update comparison period note
            document.getElementById('comparisonPeriodNote').textContent =
                `Comparing ${data.current_month_display} to ${data.previous_month_display}`;

            // Show content
            document.getElementById('savingsBreakdownLoading').style.display = 'none';
            document.getElementById('savingsBreakdownContent').style.display = 'block';

        } catch (error) {
            console.error('Error loading savings breakdown:', error);
            document.getElementById('savingsBreakdownLoading').style.display = 'none';
            document.getElementById('savingsBreakdownNoData').style.display = 'block';
            showToast('Failed to load savings breakdown', 'danger');
        }
    }

    // Render savings type summary table
    function renderSavingsTypeSummary(typeSummary) {
        const tbody = document.getElementById('savingsTypeSummaryBody');

        if (!typeSummary || typeSummary.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No data available</td></tr>';
            return;
        }

        tbody.innerHTML = typeSummary.map(type => {
            const growthClass = type.total_growth >= 0 ? 'text-success' : 'text-danger';
            const growthIcon = type.total_growth >= 0 ?
                '<i class="fas fa-arrow-up me-1"></i>' :
                '<i class="fas fa-arrow-down me-1"></i>';

            return `
                    <tr>
                        <td>
                            <span class="badge me-2" style="background-color: ${type.color_code || '#6c757d'}">
                                <i class="${type.icon_class || 'fas fa-wallet'}"></i>
                            </span>
                            ${type.account_type}
                        </td>
                        <td class="text-end">${type.account_count}</td>
                        <td class="text-end">${formatCurrency(type.total_previous)}</td>
                        <td class="text-end fw-bold">${formatCurrency(type.total_current)}</td>
                        <td class="text-end ${growthClass} fw-bold">
                            ${growthIcon}${formatCurrency(Math.abs(type.total_growth))}
                        </td>
                    </tr>
                `;
        }).join('');
    }

    // Render individual accounts breakdown table
    function renderSavingsBreakdownTable(accounts, totals) {
        const tbody = document.getElementById('savingsBreakdownBody');
        const tfoot = document.getElementById('savingsBreakdownFooter');

        if (!accounts || accounts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No accounts found</td></tr>';
            tfoot.innerHTML = '';
            return;
        }

        tbody.innerHTML = accounts.map(account => {
            const growthClass = account.growth_amount >= 0 ? 'text-success' : 'text-danger';
            const growthIcon = account.growth_amount >= 0 ?
                '<i class="fas fa-arrow-up me-1"></i>' :
                '<i class="fas fa-arrow-down me-1"></i>';
            const pctIcon = account.growth_percentage >= 0 ?
                '<i class="fas fa-caret-up me-1"></i>' :
                '<i class="fas fa-caret-down me-1"></i>';

            return `
                    <tr>
                        <td>
                            <strong>${account.account_name}</strong>
                        </td>
                        <td>
                            <span class="badge" style="background-color: ${account.color_code || '#6c757d'}">
                                <i class="${account.icon_class || 'fas fa-wallet'} me-1"></i>
                                ${account.account_type}
                            </span>
                        </td>
                        <td class="text-end text-muted">${formatCurrency(account.previous_balance)}</td>
                        <td class="text-end fw-bold">${formatCurrency(account.current_balance)}</td>
                        <td class="text-end ${growthClass} fw-bold">
                            ${growthIcon}${formatCurrency(Math.abs(account.growth_amount))}
                        </td>
                        <td class="text-end ${growthClass}">
                            ${pctIcon}${Math.abs(account.growth_percentage).toFixed(2)}%
                        </td>
                    </tr>
                `;
        }).join('');

        // Render footer with totals
        const totalGrowthClass = totals.total_growth >= 0 ? 'text-success' : 'text-danger';
        const totalGrowthIcon = totals.total_growth >= 0 ?
            '<i class="fas fa-arrow-up me-1"></i>' :
            '<i class="fas fa-arrow-down me-1"></i>';
        const totalPctIcon = totals.growth_percentage >= 0 ?
            '<i class="fas fa-caret-up me-1"></i>' :
            '<i class="fas fa-caret-down me-1"></i>';

        tfoot.innerHTML = `
                <tr>
                    <td colspan="2"><strong>TOTAL</strong></td>
                    <td class="text-end">${formatCurrency(totals.previous_balance)}</td>
                    <td class="text-end">${formatCurrency(totals.current_balance)}</td>
                    <td class="text-end ${totalGrowthClass}">
                        ${totalGrowthIcon}${formatCurrency(Math.abs(totals.total_growth))}
                    </td>
                    <td class="text-end ${totalGrowthClass}">
                        ${totalPctIcon}${Math.abs(totals.growth_percentage).toFixed(2)}%
                    </td>
                </tr>
            `;
    }

    // Export savings breakdown to CSV
    function exportSavingsBreakdown() {
        if (!savingsBreakdownData || !savingsBreakdownData.accounts || savingsBreakdownData.accounts.length === 0) {
            showToast('No data to export', 'danger');
            return;
        }

        const data = savingsBreakdownData;

        // Create CSV content
        const headers = ['Account Name', 'Type', 'Previous Balance', 'Current Balance', 'Growth Amount', 'Growth %'];
        const rows = data.accounts.map(account => [
            `"${account.account_name}"`,
            `"${account.account_type}"`,
            account.previous_balance.toFixed(2),
            account.current_balance.toFixed(2),
            account.growth_amount.toFixed(2),
            account.growth_percentage.toFixed(2) + '%'
        ]);

        // Add totals row
        rows.push([
            '"TOTAL"',
            '""',
            data.totals.previous_balance.toFixed(2),
            data.totals.current_balance.toFixed(2),
            data.totals.total_growth.toFixed(2),
            data.totals.growth_percentage.toFixed(2) + '%'
        ]);

        const csvContent = [
            `"Savings Breakdown - ${data.current_month_display}"`,
            `"Compared to: ${data.previous_month_display}"`,
            '',
            headers.join(','),
            ...rows.map(row => row.join(','))
        ].join('\n');

        // Download CSV
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `savings_breakdown_${data.current_month_display.replace(' ', '_')}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);

        showToast('Savings breakdown exported successfully', 'success');
    }

    // =====================================================
    // TOTAL BALANCE BREAKDOWN FUNCTIONALITY
    // =====================================================

    let totalBalanceData = null;

    // Show Total Balance Breakdown Modal
    async function showTotalBalanceBreakdown() {
        const modal = new bootstrap.Modal(document.getElementById('totalBalanceModal'));
        modal.show();

        // Reset states
        document.getElementById('totalBalanceLoading').style.display = 'block';
        document.getElementById('totalBalanceContent').style.display = 'none';
        document.getElementById('totalBalanceNoData').style.display = 'none';

        try {
            const response = await fetch(`${API_BASE_URL}?action=total_balance_breakdown&to=${preferredCurrency}`);
            const data = await response.json();

            totalBalanceData = data;

            if (!data.success) {
                document.getElementById('totalBalanceLoading').style.display = 'none';
                document.getElementById('totalBalanceNoData').style.display = 'block';
                return;
            }

            const displayCur = data.display_currency || preferredCurrency;

            // Update tab labels
            document.getElementById('currentMonthTabLabel').textContent = data.current_month.month_display;
            document.getElementById('previousMonthTabLabel').textContent = data.previous_month ? data.previous_month.month_display : 'Previous Month';

            // Render Current Month
            renderMonthBalanceTable(
                data.current_month,
                'currentMonthTableBody',
                'currentMonthTableFooter',
                'currentMonthTotal',
                'currentMonthActiveTotal',
                'currentMonthInactiveTotal',
                'currentMonthTableTitle',
                displayCur
            );

            // Render Previous Month
            if (data.previous_month) {
                renderMonthBalanceTable(
                    data.previous_month,
                    'previousMonthTableBody',
                    'previousMonthTableFooter',
                    'previousMonthTotal',
                    'previousMonthActiveTotal',
                    'previousMonthInactiveTotal',
                    'previousMonthTableTitle',
                    displayCur
                );
            } else {
                document.getElementById('previousMonthTableBody').innerHTML =
                    '<tr><td colspan="6" class="text-center text-muted py-4">No previous month data available</td></tr>';
                document.getElementById('previousMonthTableFooter').innerHTML = '';
            }

            // Render Comparison
            renderComparisonTable(data.comparison, displayCur);

            // Show content
            document.getElementById('totalBalanceLoading').style.display = 'none';
            document.getElementById('totalBalanceContent').style.display = 'block';

        } catch (error) {
            console.error('Error loading total balance breakdown:', error);
            document.getElementById('totalBalanceLoading').style.display = 'none';
            document.getElementById('totalBalanceNoData').style.display = 'block';
            showToast('Failed to load balance breakdown', 'danger');
        }
    }

    // Render month balance table
    function renderMonthBalanceTable(monthData, tbodyId, tfootId, totalId, activeTotalId, inactiveTotalId, titleId, displayCur) {
        document.getElementById(totalId).textContent = formatCurrency(monthData.total, displayCur);
        document.getElementById(activeTotalId).textContent = formatCurrency(monthData.active_total, displayCur);
        document.getElementById(inactiveTotalId).textContent = formatCurrency(monthData.inactive_total, displayCur);
        document.getElementById(titleId).textContent = monthData.month_display;

        const tbody = document.getElementById(tbodyId);
        const tfoot = document.getElementById(tfootId);

        if (!monthData.accounts || monthData.accounts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No accounts found</td></tr>';
            tfoot.innerHTML = '';
            return;
        }

        tbody.innerHTML = monthData.accounts.map(account => {
            const statusClass = account.status === 'Active' ? 'bg-success' :
                account.status === 'Inactive' ? 'bg-secondary' :
                    account.status === 'Locked' ? 'bg-danger' : 'bg-warning';
            const currency = account.currency || 'USD';

            return `
                    <tr class="${account.status !== 'Active' ? 'table-secondary' : ''}">
                        <td>
                            <strong>${account.account_name}</strong>
                        </td>
                        <td>
                            <span class="badge" style="background-color: ${account.color_code || '#6c757d'}">
                                ${account.account_type || 'N/A'}
                            </span>
                        </td>
                        <td>${account.bank_name || '-'}</td>
                        <td><span class="badge ${statusClass}">${account.status}</span></td>
                        <td class="text-end fw-bold">${formatCurrency(account.balance, currency)}
                            <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" style="font-size:.65rem;">${currency}</span>
                        </td>
                        <td class="text-end">${account.percentage.toFixed(1)}%</td>
                    </tr>
                `;
        }).join('');

        tfoot.innerHTML = `
                <tr>
                    <td colspan="4"><strong>TOTAL (${monthData.account_count} accounts)</strong></td>
                    <td class="text-end">${formatCurrency(monthData.total, displayCur)}</td>
                    <td class="text-end">100%</td>
                </tr>
            `;
    }

    // Render comparison table
    function renderComparisonTable(comparison, displayCur) {
        // Update summary cards — server-converted totals
        document.getElementById('compPreviousTotal').textContent = formatCurrency(comparison.previous_total, displayCur);
        document.getElementById('compCurrentTotal').textContent = formatCurrency(comparison.current_total, displayCur);

        const netChangeClass = comparison.net_change >= 0 ? 'text-success' : 'text-danger';
        const netChangeSign = comparison.net_change >= 0 ? '+' : '';
        document.getElementById('compNetChange').innerHTML =
            `<span class="${netChangeClass}">${netChangeSign}${formatCurrency(comparison.net_change, displayCur)}</span>`;

        const growthClass = comparison.growth_rate >= 0 ? 'text-success' : 'text-danger';
        const growthSign = comparison.growth_rate >= 0 ? '+' : '';
        document.getElementById('compGrowthRate').innerHTML =
            `<span class="${growthClass}">${growthSign}${comparison.growth_rate.toFixed(2)}%</span>`;

        const tbody = document.getElementById('comparisonTableBody');
        const tfoot = document.getElementById('comparisonTableFooter');

        if (!comparison.data || comparison.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No comparison data available</td></tr>';
            tfoot.innerHTML = '';
            return;
        }

        tbody.innerHTML = comparison.data.map(account => {
            // Native balances/change stay in the account's own currency
            // (accurate per-account); the row-level change here never mixes
            // currencies since it's always one account vs. itself.
            const currency = account.currency || 'USD';
            const changeClass = account.change >= 0 ? 'text-success' : 'text-danger';
            const changeIcon = account.change >= 0 ?
                '<i class="fas fa-arrow-up me-1"></i>' :
                '<i class="fas fa-arrow-down me-1"></i>';
            const statusClass = account.status === 'Active' ? 'bg-success' :
                account.status === 'Inactive' ? 'bg-secondary' : 'bg-warning';

            return `
                    <tr class="${account.status !== 'Active' ? 'table-secondary' : ''}">
                        <td><strong>${account.account_name}</strong>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" style="font-size:.65rem;">${currency}</span>
                        </td>
                        <td>
                            <span class="badge" style="background-color: ${account.color_code || '#6c757d'}">
                                ${account.account_type || 'N/A'}
                            </span>
                        </td>
                        <td><span class="badge ${statusClass}">${account.status}</span></td>
                        <td class="text-end">${formatCurrency(account.previous_balance, currency)}</td>
                        <td class="text-end fw-bold">${formatCurrency(account.current_balance, currency)}</td>
                        <td class="text-end ${changeClass} fw-bold">
                            ${changeIcon}${formatCurrency(Math.abs(account.change), currency)}
                        </td>
                        <td class="text-end ${changeClass}">
                            ${account.change >= 0 ? '+' : ''}${account.change_percentage.toFixed(2)}%
                        </td>
                    </tr>
                `;
        }).join('');

        const totalChangeClass = comparison.net_change >= 0 ? 'text-success' : 'text-danger';
        const totalChangeIcon = comparison.net_change >= 0 ?
            '<i class="fas fa-arrow-up me-1"></i>' :
            '<i class="fas fa-arrow-down me-1"></i>';

        tfoot.innerHTML = `
                <tr>
                    <td colspan="3"><strong>TOTAL (${displayCur})</strong></td>
                    <td class="text-end">${formatCurrency(comparison.previous_total, displayCur)}</td>
                    <td class="text-end">${formatCurrency(comparison.current_total, displayCur)}</td>
                    <td class="text-end ${totalChangeClass}">
                        ${totalChangeIcon}${formatCurrency(Math.abs(comparison.net_change), displayCur)}
                    </td>
                    <td class="text-end ${totalChangeClass}">
                        ${comparison.growth_rate >= 0 ? '+' : ''}${comparison.growth_rate.toFixed(2)}%
                    </td>
                </tr>
            `;
    }

    // Export total balance breakdown
    function exportTotalBalanceBreakdown() {
        if (!totalBalanceData || !totalBalanceData.success) {
            showToast('No data to export', 'danger');
            return;
        }

        const data = totalBalanceData;
        const displayCur = data.display_currency || preferredCurrency;
        const headers = ['Account Name', 'Type', 'Bank', 'Status', 'Currency', 'Previous Balance', 'Current Balance', 'Change', 'Change %'];

        const rows = data.comparison.data.map(acc => [
            `"${acc.account_name}"`,
            `"${acc.account_type || ''}"`,
            `"${acc.status}"`,
            `"${acc.currency || 'USD'}"`,
            acc.previous_balance.toFixed(2),
            acc.current_balance.toFixed(2),
            acc.change.toFixed(2),
            acc.change_percentage.toFixed(2) + '%'
        ]);

        rows.push([
            `"TOTAL (converted to ${displayCur})"`, '""', '""', `"${displayCur}"`,
            data.comparison.previous_total.toFixed(2),
            data.comparison.current_total.toFixed(2),
            data.comparison.net_change.toFixed(2),
            data.comparison.growth_rate.toFixed(2) + '%'
        ]);

        const csvContent = [
            `"Total Balance Breakdown"`,
            `"Current Month: ${data.current_month.month_display}"`,
            `"Previous Month: ${data.previous_month ? data.previous_month.month_display : 'N/A'}"`,
            `"Totals converted to: ${displayCur}. Per-account rows are in each account's own currency (see Currency column)."`,
            '',
            headers.join(','),
            ...rows.map(row => row.join(','))
        ].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `balance_breakdown_${data.current_month.month_display.replace(' ', '_')}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);

        showToast('Balance breakdown exported successfully', 'success');
    }

    // =====================================================
    // LATEST MONTH DETAILS FUNCTIONALITY
    // =====================================================

    let latestMonthDetailsData = null;

    // Show Latest Month Details Modal
    async function showLatestMonthDetails() {
        const modal = new bootstrap.Modal(document.getElementById('latestMonthModal'));
        modal.show();

        // Reset states
        document.getElementById('latestMonthLoading').style.display = 'block';
        document.getElementById('latestMonthContent').style.display = 'none';
        document.getElementById('latestMonthNoData').style.display = 'none';

        try {
            const response = await fetch(`${API_BASE_URL}?action=latest_month_details&to=${preferredCurrency}`);
            const data = await response.json();

            latestMonthDetailsData = data;

            if (!data.success) {
                document.getElementById('latestMonthLoading').style.display = 'none';
                document.getElementById('latestMonthNoData').style.display = 'block';
                return;
            }

            const displayCur = data.display_currency || preferredCurrency;

            // Update modal title
            document.getElementById('latestMonthModalTitle').textContent = data.month_display;

            // Update key metrics
            document.getElementById('lmTotalBalance').textContent = formatCurrency(data.totals.total_balance, displayCur);

            const growthClass = data.totals.total_growth >= 0 ? 'text-success' : 'text-danger';
            const growthSign = data.totals.total_growth >= 0 ? '+' : '';
            document.getElementById('lmTotalGrowth').innerHTML =
                `<span class="${growthClass}">${growthSign}${formatCurrency(data.totals.total_growth, displayCur)}</span>`;

            document.getElementById('lmAccountsUpdated').textContent = data.accounts.with_data;
            document.getElementById('lmPendingUpdates').textContent = data.accounts.pending;

            // Update progress bar
            const progressBar = document.getElementById('lmProgressBar');
            const progressText = document.getElementById('lmProgressText');
            const progressDetails = document.getElementById('lmProgressDetails');

            progressBar.style.width = `${data.accounts.completion_percentage}%`;
            progressText.textContent = `${data.accounts.completion_percentage}%`;
            progressDetails.textContent = `${data.accounts.with_data} of ${data.accounts.total_active} accounts`;

            // Update progress bar color based on completion
            progressBar.className = 'progress-bar';
            if (data.accounts.completion_percentage >= 100) {
                progressBar.classList.add('bg-success');
            } else if (data.accounts.completion_percentage >= 75) {
                progressBar.classList.add('bg-info');
            } else if (data.accounts.completion_percentage >= 50) {
                progressBar.classList.add('bg-warning');
            } else {
                progressBar.classList.add('bg-danger');
            }

            // Update data freshness
            const freshnessDiv = document.getElementById('lmDataFreshness');
            const freshnessClass = data.freshness.status === 'current' ? 'bg-success' :
                data.freshness.status === 'recent' ? 'bg-info' : 'bg-warning';
            const freshnessIcon = data.freshness.status === 'current' ? 'fa-check-circle' :
                data.freshness.status === 'recent' ? 'fa-clock' : 'fa-exclamation-triangle';
            freshnessDiv.innerHTML = `<span class="badge ${freshnessClass}"><i class="fas ${freshnessIcon} me-1"></i>${data.freshness.message}</span>`;

            // Update card in main dashboard
            const progressDiv = document.getElementById('latestDataProgress');
            const completionBar = document.getElementById('dataCompletionBar');
            const completionText = document.getElementById('dataCompletionText');

            if (progressDiv) {
                progressDiv.style.display = 'block';
                completionBar.style.width = `${data.accounts.completion_percentage}%`;
                completionText.textContent = `${data.accounts.completion_percentage}% complete`;
            }

            // Render type breakdown
            renderTypeBreakdown(data.type_breakdown, data.totals.total_balance, displayCur);

            // Render top performers
            renderTopPerformers(data.top_performers);

            // Render accounts needing attention
            renderNeedsAttention(data.needs_attention);

            // Show content
            document.getElementById('latestMonthLoading').style.display = 'none';
            document.getElementById('latestMonthContent').style.display = 'block';

        } catch (error) {
            console.error('Error loading latest month details:', error);
            document.getElementById('latestMonthLoading').style.display = 'none';
            document.getElementById('latestMonthNoData').style.display = 'block';
            showToast('Failed to load month details', 'danger');
        }
    }

    // Render type breakdown table
    function renderTypeBreakdown(typeBreakdown, totalBalance, displayCur) {
        const tbody = document.getElementById('lmTypeBreakdownBody');

        if (!typeBreakdown || typeBreakdown.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No data available</td></tr>';
            return;
        }

        tbody.innerHTML = typeBreakdown.map(type => {
            const growthClass = type.total_growth >= 0 ? 'text-success' : 'text-danger';
            const growthIcon = type.total_growth >= 0 ?
                '<i class="fas fa-arrow-up me-1"></i>' :
                '<i class="fas fa-arrow-down me-1"></i>';

            return `
                    <tr>
                        <td>
                            <span class="badge me-2" style="background-color: ${type.color_code || '#6c757d'}">
                                <i class="${type.icon_class || 'fas fa-wallet'}"></i>
                            </span>
                            ${type.account_type}
                        </td>
                        <td class="text-end fw-bold">${formatCurrency(type.total_balance, displayCur)}</td>
                        <td class="text-end ${growthClass}">
                            ${growthIcon}${formatCurrency(Math.abs(type.total_growth), displayCur)}
                        </td>
                        <td class="text-end">${type.percentage.toFixed(1)}%</td>
                    </tr>
                `;
        }).join('');
    }

    // Render top performers table
    function renderTopPerformers(topPerformers) {
        const tbody = document.getElementById('lmTopPerformersBody');

        if (!topPerformers || topPerformers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No growth data available</td></tr>';
            return;
        }

        tbody.innerHTML = topPerformers.map((performer, index) => {
            const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '';
            const currency = performer.currency || 'USD';

            return `
                    <tr>
                        <td>
                            ${medal} <strong>${performer.account_name}</strong>
                            <br><small class="text-muted">${performer.account_type}
                                <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" style="font-size:.6rem;">${currency}</span>
                            </small>
                        </td>
                        <td class="text-end text-success fw-bold">
                            <i class="fas fa-arrow-up me-1"></i>${formatCurrency(performer.growth_amount, currency)}
                        </td>
                        <td class="text-end text-success">
                            +${performer.growth_percentage.toFixed(2)}%
                        </td>
                    </tr>
                `;
        }).join('');
    }

    // Render accounts needing attention
    function renderNeedsAttention(needsAttention) {
        const card = document.getElementById('lmNeedsAttentionCard');
        const tbody = document.getElementById('lmNeedsAttentionBody');

        if (!needsAttention || needsAttention.length === 0) {
            card.style.display = 'none';
            return;
        }

        card.style.display = 'block';

        tbody.innerHTML = needsAttention.map(account => {
            let issueIcon, issueClass, actionButton;

            switch (account.issue) {
                case 'No data for this month':
                    issueIcon = '<i class="fas fa-calendar-times text-warning"></i>';
                    issueClass = 'text-warning';
                    actionButton = `<button class="btn btn-sm btn-outline-primary" onclick="showUpdateBalanceModal(); bootstrap.Modal.getInstance(document.getElementById('latestMonthModal')).hide();">Update</button>`;
                    break;
                case 'Negative growth':
                    issueIcon = '<i class="fas fa-arrow-down text-danger"></i>';
                    issueClass = 'text-danger';
                    actionButton = `<button class="btn btn-sm btn-outline-info" onclick="bootstrap.Modal.getInstance(document.getElementById('latestMonthModal')).hide(); viewDetails(${account.id});">View</button>`;
                    break;
                case 'Below minimum balance':
                    issueIcon = '<i class="fas fa-exclamation-triangle text-warning"></i>';
                    issueClass = 'text-warning';
                    actionButton = `<button class="btn btn-sm btn-outline-info" onclick="bootstrap.Modal.getInstance(document.getElementById('latestMonthModal')).hide(); viewDetails(${account.id});">View</button>`;
                    break;
                case 'No balance change in 2+ months':
                    issueIcon = '<i class="fas fa-moon text-warning"></i>';
                    issueClass = 'text-warning';
                    actionButton = `<button class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Record a transaction to keep this account active" onclick="showUpdateBalanceModal(); bootstrap.Modal.getInstance(document.getElementById('latestMonthModal')).hide();">Update</button>`;
                    break;
                case 'No update in 2+ months':
                    issueIcon = '<i class="fas fa-moon text-warning"></i>';
                    issueClass = 'text-warning';
                    actionButton = `<button class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="No balance recorded recently — update it to keep this account active" onclick="showUpdateBalanceModal(); bootstrap.Modal.getInstance(document.getElementById('latestMonthModal')).hide();">Update</button>`;
                    break;
                default:
                    issueIcon = '<i class="fas fa-info-circle text-info"></i>';
                    issueClass = 'text-info';
                    actionButton = '';
            }

            let issueText = account.issue;
            if (account.issue === 'No balance change in 2+ months') {
                issueText = 'No activity in 2+ months — transact soon to avoid dormancy lock';
            } else if (account.issue === 'No update in 2+ months') {
                const since = account.last_updated_month ? ` (last recorded ${formatMonthDisplay(account.last_updated_month)})` : '';
                issueText = `Not updated in 2+ months${since} — record a balance soon to avoid dormancy lock`;
            }

            return `
                    <tr>
                        <td><strong>${account.account_name}</strong></td>
                        <td>
                            <span class="badge" style="background-color: ${account.color_code || '#6c757d'}">
                                ${account.account_type}
                            </span>
                        </td>
                        <td><span class="badge bg-${account.status === 'Active' ? 'success' : 'secondary'}">${account.status}</span></td>
                        <td class="${issueClass}">${issueIcon} ${issueText}</td>
                        <td>${actionButton}</td>
                    </tr>
                `;
        }).join('');
    }

    // Export latest month data
    function exportLatestMonthData() {
        if (!latestMonthDetailsData || !latestMonthDetailsData.success) {
            showToast('No data to export', 'danger');
            return;
        }

        const data = latestMonthDetailsData;
        const displayCur = data.display_currency || preferredCurrency;

        // Create comprehensive CSV
        let csvContent = [
            `"Month Overview - ${data.month_display}"`,
            `"Summary & breakdown totals converted to ${displayCur}. Top performers listed in each account's own currency."`,
            '',
            '"SUMMARY"',
            `"Total Balance (${displayCur})","${data.totals.total_balance.toFixed(2)}"`,
            `"Total Growth (${displayCur})","${data.totals.total_growth.toFixed(2)}"`,
            `"Accounts Updated","${data.accounts.with_data}"`,
            `"Pending Updates","${data.accounts.pending}"`,
            `"Completion","${data.accounts.completion_percentage}%"`,
            '',
            '"BREAKDOWN BY TYPE"',
            `"Type","Balance (${displayCur})","Growth (${displayCur})","Percentage"`
        ];

        data.type_breakdown.forEach(type => {
            csvContent.push(`"${type.account_type}","${type.total_balance.toFixed(2)}","${type.total_growth.toFixed(2)}","${type.percentage.toFixed(1)}%"`);
        });

        csvContent.push('');
        csvContent.push('"TOP PERFORMERS"');
        csvContent.push('"Account","Currency","Growth Amount","Growth %"');

        data.top_performers.forEach(performer => {
            csvContent.push(`"${performer.account_name}","${performer.currency || 'USD'}","${performer.growth_amount.toFixed(2)}","${performer.growth_percentage.toFixed(2)}%"`);
        });

        if (data.needs_attention.length > 0) {
            csvContent.push('');
            csvContent.push('"NEEDS ATTENTION"');
            csvContent.push('"Account","Type","Status","Issue"');

            data.needs_attention.forEach(account => {
                csvContent.push(`"${account.account_name}","${account.account_type}","${account.status}","${account.issue}"`);
            });
        }

        const blob = new Blob([csvContent.join('\n')], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `month_overview_${data.month_display.replace(' ', '_')}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);

        showToast('Month overview exported successfully', 'success');
    }

</script>

<script src='https://cdn.jsdelivr.net/npm/apexcharts'></script>
<?php include 'footer.php'; ?>