<?php include "head.php";?>
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

<div class="card overflow-hidden">
    <div class="card-body p-0 scrollbar m-3">
        <div class="calendar-outline" id="transactionCalendar"></div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content position-relative border-0">
            <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                <div class="position-relative z-1">
                    <h4 class="mb-0 text-white" id="taskModalLabel">Transaction Details</h4>
                </div>
                <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4 px-5" id="transactionDetails"></div>
        </div>
    </div>
</div>

<?php
include "footer.php";
?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('transactionCalendar');

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: [
                <?php
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

                while ($row = mysqli_fetch_array($query)) {
                    $badgeClass = 'badge-subtle-primary'; // Default for Income
                    if ($row['category'] === 'Expense') {
                        $badgeClass = 'badge-subtle-danger';
                    } elseif ($row['category'] === 'Savings') {
                        $badgeClass = 'badge-subtle-success';
                    }

                    $endDate = date('Y-m-d', strtotime($row['date'])) . ' 23:59:59';
                    echo "{
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
                    transactionCost: '" . $row['transactionCost'] . "'
                },
                badgeClass: '$badgeClass'
            },";
                }
                ?>
            ],
            eventContent: function (arg) {
                // Generate custom HTML content for each event
                let categoryBadge = `<span class="badge rounded-pill ${arg.event.extendedProps.badgeClass}">Ksh ${arg.event.extendedProps.amount}</span>`;
                let dotIndicator = `<span class="event-dot ${arg.event.extendedProps.badgeClass}"></span>`;

                return {
                    html: `${dotIndicator} ${categoryBadge}`
                };
            },
            eventClick: function (info) {
                var details = `
            <div class="d-flex flex-between-center mb-1">
                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Category</span></div>
                <div><span class="text-warning">${info.event.extendedProps.category}</span></div>
            </div>
            <div class="d-flex flex-between-center mb-1">
                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Subcategory</span></div>
                <div><span class="text-warning">${info.event.extendedProps.subcategory}</span></div>
            </div>
            <div class="d-flex flex-between-center mb-1">
                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Description</span></div>
                <div><span class="text-warning">${info.event.extendedProps.description}</span></div>
            </div>
            <div class="d-flex flex-between-center mb-1">
                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Amount</span></div>
                <div><span class="text-warning"> Ksh ${new Intl.NumberFormat('en-US').format(info.event.extendedProps.amount)}</span></div>
            </div>
            <div class="d-flex flex-between-center mb-1">
                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Date</span></div>
                <div><span class="text-warning">${new Date(info.event.start).toLocaleDateString('en-US', {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                })}</span>
                </div>
            </div>
            <div class="d-flex flex-between-center mb-1">
                <div class="d-flex align-items-center"><span class="dot bg-primary"></span><span class="fw-semi-bold">Transaction Cost</span></div>
                <div><span class="text-warning">Ksh ${new Intl.NumberFormat('en-US').format(info.event.extendedProps.transactionCost)}</span></div>
            </div>
        `;
                document.getElementById('transactionDetails').innerHTML = details;
                var taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
                taskModal.show();
            }
        });

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





