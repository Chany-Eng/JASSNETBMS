<?php
// Dashboard view
$pageTitle = 'Dashboard';
?>

<?php ob_start(); ?>
<div class="container-fluid">
    <!-- statistics cards -->
    <div class="row g-3 mb-4">
        <?php if ($controller->hasPermission(['Sales', 'Super Admin'])): ?>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6>Total Customers</h6>
                    <h3><?= number_format($stats['total_customers'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6>Active PPPoE Users</h6>
                    <h3><?= number_format($stats['active_pppoe_users'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6>Active Hotspot Users</h6>
                    <h3><?= number_format($stats['active_hotspot_users'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($controller->hasPermission(['Sales', 'Accountant', 'Director', 'Super Admin'])): ?>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6>Monthly Income</h6>
                    <h3><?= number_format($stats['income_month'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($controller->hasPermission(['Accountant', 'Director', 'Super Admin'])): ?>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6>Total Expenses</h6>
                    <h3><?= number_format($stats['approved_expenses'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($controller->hasPermission(['Manager', 'Super Admin'])): ?>
        <div class="col-md-2">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6>Inventory Value</h6>
                    <h3><?= number_format($stats['inventory_value'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- charts section -->
    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">Income vs Expenses (last 6 months)</div>
                <div class="card-body">
                    <canvas id="incomeExpenseChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">Customer Growth (unique per month)</div>
                <div class="card-body">
                    <canvas id="customerGrowthChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">Inventory Additions</div>
                <div class="card-body">
                    <canvas id="inventoryUsageChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">Station Progress</div>
                <div class="card-body">
                    <canvas id="stationProgressChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- activity panels -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">Recent Income</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Date</th><th>Customer</th><th>Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent_income as $inc): ?>
                            <tr>
                                <td><?= htmlspecialchars($inc['date']) ?></td>
                                <td><?= htmlspecialchars($inc['customer_name']) ?></td>
                                <td><?= number_format($inc['amount'],2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">Recent Expense Requests</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent_expenses as $exp): ?>
                            <tr>
                                <td><?= htmlspecialchars($exp['request_date']) ?></td>
                                <td><?= number_format($exp['amount_requested'],2) ?></td>
                                <td><?= htmlspecialchars($exp['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">Low Stock Alerts</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Item</th><th>Qty</th></tr></thead>
                        <tbody>
                        <?php foreach ($low_stock_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                <td><?= htmlspecialchars($item['quantity']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">New Station Requests</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Date</th><th>Name</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent_stations as $st): ?>
                            <tr>
                                <td><?= htmlspecialchars($st['request_date']) ?></td>
                                <td><?= htmlspecialchars($st['station_name']) ?></td>
                                <td><?= htmlspecialchars($st['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // prepare chart data from server-side variables
    const chartData = <?= json_encode($controller->getChartData()); ?>;

    function genLineChart(ctxId, label, data1, data2, color1, color2) {
        const ctx = document.getElementById(ctxId).getContext('2d');
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.months,
                datasets: [
                    {
                        label: label + ' Income',
                        data: data1,
                        borderColor: color1,
                        backgroundColor: color1 + '44',
                        fill: true
                    },
                    {
                        label: label + ' Expenses',
                        data: data2,
                        borderColor: color2,
                        backgroundColor: color2 + '44',
                        fill: true
                    }
                ]
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        new Chart(document.getElementById('incomeExpenseChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartData.months,
                datasets: [
                    {
                        label: 'Income',
                        data: chartData.income,
                        backgroundColor: '#28a745'
                    },
                    {
                        label: 'Expenses',
                        data: chartData.expenses,
                        backgroundColor: '#dc3545'
                    }
                ]
            }
        });

        new Chart(document.getElementById('customerGrowthChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.months,
                datasets: [{
                    label: 'Customers',
                    data: chartData.customerGrowth,
                    borderColor: '#007bff',
                    backgroundColor: '#007bff44',
                    fill: true
                }]
            }
        });

        new Chart(document.getElementById('inventoryUsageChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.months,
                datasets: [{
                    label: 'New Inventory Items',
                    data: chartData.inventoryUsage,
                    borderColor: '#ffc107',
                    backgroundColor: '#ffc10744',
                    fill: true
                }]
            }
        });

        // station progress as stacked bar
        const stationCtx = document.getElementById('stationProgressChart').getContext('2d');
        const statuses = {};
        chartData.stationProgress.forEach((row, index) => {
            Object.keys(row).forEach(status => {
                statuses[status] = statuses[status] || [];
                statuses[status][index] = row[status];
            });
        });
        const datasets = Object.keys(statuses).map((status, idx) => ({
            label: status,
            data: statuses[status],
            backgroundColor: `hsl(${(idx*60)%360}, 70%, 50%)`
        }));
        new Chart(stationCtx, {
            type: 'bar',
            data: {
                labels: chartData.months,
                datasets: datasets
            },
            options: { scales: { x: { stacked: true }, y: { stacked: true } } }
        });
    });
</script>

<?php $content = ob_get_clean(); ?>
<?php include APP_ROOT . '/app/views/layouts/main.php'; ?>