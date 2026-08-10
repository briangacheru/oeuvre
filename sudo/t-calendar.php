<?php include "head.php";?>
<?php requireCapability($currentAdminRole, 'operate_finance'); ?>
<title>Transactions Calendar</title>
<?php include "navi.php";

?>

<div class="card shadow-none border mb-3">
    <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);"></div>
    <div class="card-header z-1">
        <div class="row flex-between-center gx-0">
            <div class="col-lg-auto d-flex align-items-center">
                <h4 class="mb-0 text-primary fw-bold">Transactions <span class="text-info fw-medium"> Calendar</span></h4>
            </div>
            <div class="col-md-auto p-3">
                    <div class="col-md-auto position-relative">
                        <div class="dropdown font-sans-serif me-md-2">
                            <button class="btn btn-falcon-default text-600 btn-sm dropdown-toggle dropdown-caret-none" type="button" id="view-selector" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span id="current-view">All Transactions</span>
                                <svg class="svg-inline--fa fa-sort fa-w-10 ms-2 fs-10" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="sort" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                                    <path fill="currentColor" d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41zm255-105L177 64c-9.4-9.4-24.6-9.4-33.9 0L24 183c-15.1 15.1-4.4 41 17 41h238c21.4 0 32.1-25.9 17-41z"></path>
                                </svg>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end border py-2" aria-labelledby="view-selector">
                                <button class="btn dropdown-item d-flex justify-content-between filter-btn" data-category="All">All</button>
                                <button class="btn dropdown-item d-flex justify-content-between filter-btn" data-category="Income">Income</button>
                                <button class="btn dropdown-item d-flex justify-content-between filter-btn" data-category="Expense">Expense</button>
                                <button class="btn dropdown-item d-flex justify-content-between filter-btn" data-category="Savings">Savings</button>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>

<!-- Month summary - totals for whichever month the calendar is currently showing,
     recalculated client-side (see updateMonthSummary()) as the admin navigates. -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-primary-subtle h-100">
            <div class="card-body py-3 text-center">
                <div class="d-flex align-items-center justify-content-center mb-1">
                    <i class="fas fa-arrow-down text-primary me-2"></i>
                    <span class="fs-10 text-uppercase fw-semi-bold text-600">Income</span>
                </div>
                <h5 class="mb-0 text-primary">Ksh <span id="monthIncomeTotal">0</span></h5>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-danger-subtle h-100">
            <div class="card-body py-3 text-center">
                <div class="d-flex align-items-center justify-content-center mb-1">
                    <i class="fas fa-arrow-up text-danger me-2"></i>
                    <span class="fs-10 text-uppercase fw-semi-bold text-600">Expense</span>
                </div>
                <h5 class="mb-0 text-danger">Ksh <span id="monthExpenseTotal">0</span></h5>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-success-subtle h-100">
            <div class="card-body py-3 text-center">
                <div class="d-flex align-items-center justify-content-center mb-1">
                    <i class="fas fa-piggy-bank text-success me-2"></i>
                    <span class="fs-10 text-uppercase fw-semi-bold text-600">Savings</span>
                </div>
                <h5 class="mb-0 text-success">Ksh <span id="monthSavingsTotal">0</span></h5>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 bg-body-tertiary h-100">
            <div class="card-body py-3 text-center">
                <div class="d-flex align-items-center justify-content-center mb-1">
                    <i class="fas fa-balance-scale text-700 me-2"></i>
                    <span class="fs-10 text-uppercase fw-semi-bold text-600">Net (Income − Expense)</span>
                </div>
                <h5 class="mb-0" id="monthNetTotal">Ksh 0</h5>
            </div>
        </div>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="card-body p-0 scrollbar m-3">
        <div class="calendar-outline" id="transactionCalendar"></div>
    </div>
</div>

<!-- View Transaction Details Modal - matches sudo/transactions.php's #viewTransactionModal -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                <div class="position-relative z-1">
                    <h4 class="mb-0 text-white" id="taskModalLabel">Transaction Details</h4>
                    <p class="fs-10 text-white mb-0 opacity-75">Reference: <span id="calTransactionRef">#</span></p>
                </div>
                <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Amount highlight card -->
                <div class="text-center mb-4 p-3 rounded-3 bg-light border">
                    <p class="fs-10 text-600 mb-1 fw-semi-bold text-uppercase">Amount</p>
                    <h2 class="fw-bold mb-1 text-primary">Ksh <span id="calTransactionAmount">0</span></h2>
                    <p class="fs-10 text-500 mb-0">Transaction cost: <span class="badge badge-subtle-secondary" id="calTransactionCost">0</span></p>
                </div>

                <!-- Detail grid -->
                <div class="row g-2">
                    <div class="col-12">
                        <div class="p-2 border rounded-3 h-100">
                            <p class="fs-11 text-600 mb-1 fw-semi-bold text-uppercase">
                                <i class="fas fa-tag me-1 text-info"></i>Sub Category
                            </p>
                            <h6 class="mb-0 fw-bold" id="calTransactionSubcategory">-</h6>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded-3 h-100">
                            <p class="fs-11 text-600 mb-1 fw-semi-bold text-uppercase">
                                <i class="fas fa-folder me-1 text-primary"></i>Category
                            </p>
                            <h6 class="mb-0 fw-bold" id="calTransactionCategory">-</h6>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded-3">
                            <p class="fs-11 text-600 mb-1 fw-semi-bold text-uppercase">
                                <i class="fas fa-align-left me-1 text-success"></i>Description
                            </p>
                            <h6 class="mb-0 fw-semi-bold" id="calTransactionDescription" style="word-break: break-word; white-space: normal;">-</h6>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded-3 h-100">
                            <p class="fs-11 text-600 mb-1 fw-semi-bold text-uppercase">
                                <i class="fas fa-wallet me-1 text-warning"></i>Platform
                            </p>
                            <h6 class="mb-0 fw-bold" id="calTransactionTag">-</h6>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded-3 h-100">
                            <p class="fs-11 text-600 mb-1 fw-semi-bold text-uppercase">
                                <i class="fas fa-calendar-alt me-1 text-danger"></i>Date
                            </p>
                            <h6 class="mb-0 fw-bold" id="calTransactionDate">-</h6>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="transactions" class="btn btn-falcon-default btn-sm">
                    <i class="fas fa-external-link-alt me-1"></i>Open in Transactions
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Built once, up front, so both the JS `events:` array and the per-day
// expense-total lookup below can be simple echoes of already-computed PHP
// values - $dailyExpenseTotals used to only exist *inside* the events-loop
// further down, so the JS `dailyExpenseTotals` declaration near the top of
// the script block below (needed there so dayCellContent can close over it)
// was reading it before it had ever been assigned (PHP runs top-to-bottom).
$query = mysqli_query($con, "
    SELECT
        budgetID AS id, category, subcategory, description, tag, amount, transactionCost, expenseDate AS date, 'tblbudget' AS table_source
    FROM tblbudget WHERE is_deleted = 0
    UNION ALL
    SELECT
        id, category, subcategory, description, tag, amount, transactionCost, od_date AS date, 'tbloverdrafts' AS table_source
    FROM tbloverdrafts WHERE is_deleted = 0
    ORDER BY date DESC
");

$dailyExpenseTotals = [];
$calendarEventsJs = '';

while ($row = mysqli_fetch_array($query)) {
    $badgeClass = 'badge-subtle-primary'; // Default for Income
    if ($row['category'] === 'Expense') {
        $badgeClass = 'badge-subtle-danger';
    } elseif ($row['category'] === 'Savings') {
        $badgeClass = 'badge-subtle-success';
    }

    if ($row['category'] === 'Expense') {
        $dayKey = date('Y-m-d', strtotime($row['date']));
        $dailyExpenseTotals[$dayKey] = ($dailyExpenseTotals[$dayKey] ?? 0) + (float) $row['amount'];
    }

    $sourcePrefix = ($row['table_source'] === 'tblbudget') ? 'TNSN' : 'OVDT';
    $endDate = date('Y-m-d', strtotime($row['date'])) . ' 23:59:59';
    $calendarEventsJs .= "{
                title: '" . addslashes($row['category']) . "',
                start: '" . $row['date'] . "',
                end: '" . $endDate . "',
                extendedProps: {
                    id: '" . $row['id'] . "',
                    category: '" . addslashes($row['category']) . "',
                    subcategory: '" . addslashes($row['subcategory']) . "',
                    tag: '" . addslashes($row['tag']) . "',
                    description: '" . addslashes($row['description']) . "',
                    amount: '" . $row['amount'] . "',
                    tableSource: '" . $row['table_source'] . "',
                    sourcePrefix: '" . $sourcePrefix . "',
                    transactionCost: '" . $row['transactionCost'] . "'
                },
                badgeClass: '$badgeClass'
            },";
}

include "footer.php";
?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('transactionCalendar');

        // Per-day expense totals (Expense category only), keyed by Y-m-d - built
        // server-side from the same rows the events below came from.
        var dailyExpenseTotals = <?php echo json_encode($dailyExpenseTotals); ?>;

        // Builds the same Y-m-d key the PHP side used, in the browser's local
        // timezone - toISOString() would shift the date near midnight for
        // anyone not on UTC, silently attaching totals to the wrong day.
        function formatDateKey(date) {
            var y = date.getFullYear();
            var m = String(date.getMonth() + 1).padStart(2, '0');
            var d = String(date.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        }

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: [
                <?php echo $calendarEventsJs; ?>
            ],
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            dayCellContent: function (arg) {
                var key = formatDateKey(arg.date);
                var total = dailyExpenseTotals[key];
                var dayNumber = document.createElement('span');
                dayNumber.textContent = arg.dayNumberText;

                if (!total) {
                    return { domNodes: [dayNumber] };
                }

                var wrap = document.createElement('div');
                wrap.className = 'd-flex justify-content-between align-items-center w-100';
                wrap.appendChild(dayNumber);

                var badge = document.createElement('span');
                badge.className = 'badge badge-subtle-danger fs-11';
                badge.title = 'Total expenses this day';
                badge.textContent = 'Ksh ' + new Intl.NumberFormat('en-US').format(Math.round(total));
                wrap.appendChild(badge);
                // theme.js's tooltipInit() only scans the DOM once on page load,
                // so it never sees these FullCalendar-rendered cells - init here
                // instead, or the [data-bs-toggle] attribute would just be inert.
                new bootstrap.Tooltip(badge, { trigger: 'hover' });

                return { domNodes: [wrap] };
            },
            eventContent: function (arg) {
                // Generate custom HTML content for each event
                let categoryBadge = `<span class="badge rounded-pill ${arg.event.extendedProps.badgeClass}">Ksh ${arg.event.extendedProps.amount}</span>`;
                let dotIndicator = `<span class="event-dot ${arg.event.extendedProps.badgeClass}"></span>`;

                return {
                    html: `${dotIndicator} ${categoryBadge}`
                };
            },
            eventClick: function (info) {
                var props = info.event.extendedProps;
                document.getElementById('calTransactionRef').innerText = (props.sourcePrefix || '') + ' #' + props.id;
                document.getElementById('calTransactionCategory').innerText = props.category || '-';
                document.getElementById('calTransactionSubcategory').innerText = props.subcategory || '-';
                document.getElementById('calTransactionDescription').innerText = props.description || '-';
                document.getElementById('calTransactionAmount').innerText = new Intl.NumberFormat('en-US').format(props.amount || 0);
                document.getElementById('calTransactionCost').innerText = new Intl.NumberFormat('en-US').format(props.transactionCost || 0);
                document.getElementById('calTransactionTag').innerText = props.tag || '-';
                document.getElementById('calTransactionDate').innerText = new Date(info.event.start).toLocaleDateString('en-US', {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });

                var taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
                taskModal.show();
            },
            datesSet: function (info) {
                updateMonthSummary(info.view.currentStart, info.view.currentEnd);
            }
        });

        // Month summary cards - totals for whichever month is currently visible,
        // recomputed from the events FullCalendar already has in memory (no
        // extra request) every time the admin navigates.
        function updateMonthSummary(rangeStart, rangeEnd) {
            var totals = { Income: 0, Expense: 0, Savings: 0 };
            calendar.getEvents().forEach(function (event) {
                if (event.start >= rangeStart && event.start < rangeEnd) {
                    var category = event.extendedProps.category;
                    if (totals.hasOwnProperty(category)) {
                        totals[category] += parseFloat(event.extendedProps.amount) || 0;
                    }
                }
            });

            var fmt = new Intl.NumberFormat('en-US');
            document.getElementById('monthIncomeTotal').textContent = fmt.format(totals.Income);
            document.getElementById('monthExpenseTotal').textContent = fmt.format(totals.Expense);
            document.getElementById('monthSavingsTotal').textContent = fmt.format(totals.Savings);
            document.getElementById('monthNetTotal').textContent = 'Ksh ' + fmt.format(totals.Income - totals.Expense);
        }

        calendar.render();

        // Event filtering by category
        document.querySelectorAll('.filter-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                var category = this.getAttribute('data-category');
                document.getElementById('current-view').textContent = category + " Transactions";
                calendar.getEvents().forEach(function (event) {
                    if (category === 'All' || event.extendedProps.category === category) {
                        event.setProp('display', 'auto');
                    } else {
                        event.setProp('display', 'none');
                    }
                });
            });
        });
    });
</script>





