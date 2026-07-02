<?php include "head.php";?>
    <title>Budget Analytics</title>
<?php include "navi.php";
$status = "OK";
$msg = "";
?>
    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);">
        </div>
        <!--/.bg-holder-->
        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">Chart<span class="text-info fw-medium"> Analytics</span></h4>
                </div>
                <div class="col-lg-auto pt-3 pt-lg-0">
                    <form class="row flex-lg-column flex-xxl-row gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
                        <div class="col-auto">
                        </div>
                        <div class="col-md-auto position-relative">
                            <h6 class="mb-1 badge rounded-pill badge-subtle-info"><?php echo date("jS F Y"); ?> | <span id="timeDisplay"></span></h6>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php
if (isset($_SESSION['alert'])) {
    echo $_SESSION['alert'];
    unset($_SESSION['alert']);
    //echo '<meta http-equiv="refresh" content="10;url=' . htmlspecialchars($_SERVER['PHP_SELF']) . '">';
}
?>
    <div class="row g-3 mb-3">
        <div class="col-xxl-12">
            <div class="card rounded-3 overflow-hidden h-100">
                <div class="card-body bg-line-chart-gradient d-flex flex-column justify-content-between">
                    <div class="row align-items-center g-0">
                        <div class="col" data-bs-theme="light">
                            <h4 class="text-white mb-0"></h4>
                        </div>
                        <div class="col-auto d-none d-sm-block">
                            <select class="form-select form-select-sm mb-3" id="timeframeSelector">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div id="FinancialOverview" style="height: 400px;"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-xxl-12">
            <div class="card rounded-3 overflow-hidden h-100">
                <div class="card-body bg-line-chart-gradient d-flex flex-column justify-content-between">
                    <div class="row align-items-center g-0">
                        <div class="col" data-bs-theme="light">
                            <h4 class="text-white mb-0"></h4>
                        </div>
                        <div class="col-auto d-none d-sm-block">
                            <select class="form-select form-select-sm mb-3" id="timePeriodSelector">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div id="transactionCostChart" style="height: 400px;"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        const fetchDataAndRenderChart = (filter) => {
            fetch(`chart-data?filter=${filter}`)
                .then(response => response.json())
                .then(data => {
                    // Custom sort logic based on the filter
                    const sortedData = data.sort((a, b) => {
                        if (filter === 'weekly') {
                            // Sort weekly data (e.g., "2024 W50", "2025 W1")
                            const parseWeek = (period) => {
                                const [year, week] = period.split(' W').map(Number);
                                return year * 100 + week; // Combine year and week into a single number
                            };
                            return parseWeek(a.period) - parseWeek(b.period);
                        } else if (filter === 'monthly') {
                            // Sort monthly data (e.g., "2024-05", "2024-06")
                            const parseMonth = (period) => {
                                const [year, month] = period.split('-').map(Number);
                                return year * 100 + month; // Combine year and month into a single number
                            };
                            return parseMonth(a.period) - parseMonth(b.period);
                        } else {
                            // Default sorting for other filters (e.g., yearly)
                            return a.period.localeCompare(b.period);
                        }
                    });
                    //const reversedData = data.reverse();
                    const categories = sortedData.map(d => d.period);
                    const income = sortedData.map(d => parseFloat(d.income) || 0);
                    const expenses = sortedData.map(d => parseFloat(d.expenses) || 0);
                    const savings = sortedData.map(d => parseFloat(d.savings) || 0);

                    const options = {
                        chart: {
                            type: 'line',
                            dropShadow: {
                                enabled: true,
                                color: '#000',
                                top: 18,
                                left: 7,
                                blur: 10,
                                opacity: 0.2
                            },
                            toolbar: {
                                show: true
                            }
                        },
                        colors: ['#8B8000','#DC143C','#228B22', '#8B4000', '#FFA500', '#284420', '#eaec00'],
                        dataLabels: {
                            enabled: true
                        },
                        series: [
                            { name: 'Income', data: income },
                            { name: 'Expenses', data: expenses },
                            { name: 'Savings', data: savings }
                        ],
                        xaxis: {
                            categories: categories,
                            title: {
                                text: 'Period',
                                style: {
                                    color: '#FFFFFF',
                                    fontSize: '20px'
                                }
                            },
                            labels: {
                                style: {
                                    colors: '#FFFFFF',
                                    fontSize: '12px'
                                }
                            }
                        },
                        yaxis: {
                            title: {
                                text: 'Amount (Ksh)',
                                style: {
                                    color: '#FFFFFF',
                                    fontSize: '20px'
                                }
                            },
                            labels: {
                                style: {
                                    colors: '#FFFFFF',
                                    fontSize: '12px'
                                }
                            }
                        },
                        legend: {
                            fontSize: '14px',
                            labels: {
                                colors: '#FFFFFF'
                            }
                        },
                        stroke: {
                            curve: 'smooth',
                            width: 2,
                            colors: ['#8B8000','#DC143C','#228B22', '#8B4000', '#FFA500', '#284420', '#eaec00']
                        },
                        grid: {
                            borderColor: '#FFFFFF',
                            strokeDashArray: 5,
                            position: 'back'
                        },
                        markers: {
                            size: 1,
                            colors: ['#8B8000','#DC143C','#228B22', '#8B4000', '#FFA500', '#284420', '#eaec00'],
                            strokeColor: '#fff',
                            strokeWidth: 2,
                            hover: {
                                size: 7
                            }
                        },
                        title: {
                            text: 'Budget Tracking Overview',
                            align: 'center',
                            style: {
                                color: '#FFFFFF',
                                fontSize: '20px'
                            }
                        }
                    };

                    const chartDiv = document.querySelector('#FinancialOverview');
                    chartDiv.innerHTML = ''; // Clear previous chart
                    const chart = new ApexCharts(chartDiv, options);
                    chart.render();
                });
        };

        // Handle filter change
        document.querySelector('#timeframeSelector').addEventListener('change', (event) => {
            const filter = event.target.value;
            fetchDataAndRenderChart(filter);
        });

        // Initial load with default filter
        fetchDataAndRenderChart('monthly');
    </script>
    <script>
        const fetchAndRenderChartCost = (filter) => {
            fetch(`transaction-cost-chart?filter=${filter}`)
                .then(response => response.json())
                .then(data => {
                    const categories = data.categories; // Time periods
                    const seriesData = data.seriesData; // Transaction costs

                    const options = {
                        chart: {
                            type: 'area',
                            height: 350,
                            toolbar: {
                                show: true
                            }
                        },
                        series: [{
                            name: 'Transaction Cost',
                            data: seriesData
                        }],
                        xaxis: {
                            categories: categories,
                            title: {
                                text: filter === 'yearly' ? 'Year' : 'Month',
                                style: {
                                    color: '#FFFFFF',
                                    fontSize: '14px'
                                }
                            },
                            labels: {
                                style: {
                                    colors: '#FFFFFF',
                                    fontSize: '12px'
                                }
                            }
                        },
                        yaxis: {
                            title: {
                                text: 'Transaction Cost (Ksh)',
                                style: {
                                    color: '#FFFFFF',
                                    fontSize: '14px'
                                }
                            },
                            labels: {
                                style: {
                                    colors: '#FFFFFF',
                                    fontSize: '12px'
                                }
                            }
                        },
                        title: {
                            text: `Transaction Cost (${filter.charAt(0).toUpperCase() + filter.slice(1)})`,
                            align: 'center',
                            style: {
                                color: '#FFFFFF',
                                fontSize: '16px'
                            }
                        },
                        colors: ['crimson'],
                        dataLabels: {
                            enabled: false
                        },
                        stroke: {
                            curve: 'smooth'
                        }
                    };

                    // Render or update the chart
                    const chartDiv = document.querySelector("#transactionCostChart");
                    chartDiv.innerHTML = ""; // Clear previous chart
                    const chart = new ApexCharts(chartDiv, options);
                    chart.render();
                });
        };

        // Initial chart load (monthly by default)
        fetchAndRenderChartCost('monthly');

        // Event listener for filter change
        document.querySelector('#timePeriodSelector').addEventListener('change', (event) => {
            const filter = event.target.value;
            fetchAndRenderChartCost(filter);
        });
    </script>




<?php
include "footer.php";
?>