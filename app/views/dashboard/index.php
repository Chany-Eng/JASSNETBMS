<?php
// Dashboard view
$pageTitle = 'Dashboard';
?>

<?php ob_start(); ?>
<style>
    .dash-hero {
        border-radius: 14px;
        padding: 1.2rem 1.4rem;
        background: linear-gradient(120deg, #17406d 0%, #2d6a8a 100%);
        color: #fff;
        box-shadow: 0 14px 30px rgba(23, 64, 109, 0.24);
    }

    .kpi-card {
        border-radius: 12px;
        border: 1px solid #dce8f2;
        background: #fff;
        height: 100%;
    }

    .kpi-title {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #5c7288;
        margin-bottom: 0.25rem;
    }

    .kpi-value {
        font-size: 1.6rem;
        font-weight: 700;
        color: #183b56;
        margin: 0;
    }

    .chart-wrap {
        height: 290px;
    }

    .table thead th {
        font-size: 0.8rem;
        letter-spacing: 0.04em;
    }

</style>

<div class="container-fluid">
    <?php
    $permissions = $dashboard_permissions ?? [];
    $canViewAllFinancials = !empty($permissions['can_view_all_financials']);
    $canViewIncomeSummary = !empty($permissions['can_view_income']);
    $canViewOwnIncome = !empty($permissions['can_view_own_income']);
    $canViewExpenseSummary = !empty($permissions['can_view_expense_financials']);
    $canViewExpenseOperations = !empty($permissions['can_view_expense_operations']);
    $canViewInventorySummary = !empty($permissions['can_view_inventory_value']);
    $canViewInventory = !empty($permissions['can_view_inventory']);
    $canViewInventoryCharts = !empty($permissions['can_view_inventory_charts']);
    $canViewStationCharts = !empty($permissions['can_view_station_charts']);
    $canViewStations = !empty($permissions['can_view_stations']);
    ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="dash-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2 class="mb-1 text-white">Operations Dashboard</h2>
                    <div class="small">Snapshot of income, expenses, inventory, and station work.</div>
                </div>
                <div class="text-end small">
                    <div>Today: <?= date('M d, Y') ?></div>
                    <?php if ($canViewAllFinancials): ?>
                    <div>Net Profit: Tshs. <?= number_format($stats['net_profit'] ?? 0, 2) ?></div>
                    <?php else: ?>
                    <div>Role: <?= htmlspecialchars($user['role'] ?? 'User') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-<?= htmlspecialchars($message['type'] === 'error' ? 'danger' : $message['type']) ?> mb-0">
                <?= htmlspecialchars($message['text']) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <div class="row g-3 mb-4">
        <?php if ($canViewAllFinancials): ?>
        <div class="col-md-4 col-xl-2">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="kpi-title">Total Customers</div>
                    <p class="kpi-value"><?= number_format($stats['total_customers'] ?? 0) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="kpi-title">Snippe Hotspot Users</div>
                    <p class="kpi-value"><?= number_format($stats['active_hotspot_users'] ?? 0) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="kpi-title">Subscription Users</div>
                    <p class="kpi-value"><?= number_format($stats['subscription_users'] ?? 0) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canViewIncomeSummary): ?>
        <div class="col-md-4 col-xl-2">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="kpi-title">Monthly Income</div>
                    <p class="kpi-value">Tshs. <?= number_format($stats['income_month'] ?? 0, 2) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canViewOwnIncome): ?>
        <div class="col-md-4 col-xl-2">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="kpi-title">My Role</div>
                    <p class="kpi-value"><?= htmlspecialchars($user['role'] ?? 'Sales') ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canViewExpenseSummary): ?>
        <div class="col-md-4 col-xl-2">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="kpi-title">Total Expenses</div>
                    <p class="kpi-value">Tshs. <?= number_format($stats['approved_expenses'] ?? 0, 2) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canViewInventorySummary): ?>
        <div class="col-md-4 col-xl-2">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="kpi-title">Inventory Value</div>
                    <p class="kpi-value">Tshs. <?= number_format($stats['inventory_value'] ?? 0, 2) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($canViewAllFinancials): ?>
    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">Income vs Expenses (last 6 months)</div>
                <div class="card-body chart-wrap">
                    <canvas id="incomeExpenseChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">Customer Growth (unique per month)</div>
                <div class="card-body chart-wrap">
                    <canvas id="customerGrowthChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canViewInventoryCharts || $canViewStationCharts): ?>
    <div class="row g-4 mb-4">
        <?php if ($canViewInventoryCharts): ?>
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">Inventory Additions</div>
                <div class="card-body chart-wrap">
                    <canvas id="inventoryUsageChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($canViewStationCharts): ?>
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">Station Progress</div>
                <div class="card-body chart-wrap">
                    <canvas id="stationProgressChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <?php if ($canViewIncomeSummary || $canViewOwnIncome): ?>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><?= $canViewAllFinancials ? 'Recent Income' : 'My Recent Income' ?></div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Date</th><th>Customer</th><th>Amount</th></tr></thead>
                        <tbody>
                        <?php if (!empty($recent_income)): ?>
                            <?php foreach ($recent_income as $inc): ?>
                                <tr>
                                    <td><?= htmlspecialchars($inc['date']) ?></td>
                                    <td><?= htmlspecialchars($inc['customer_name']) ?></td>
                                    <td>Tshs. <?= number_format($inc['amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-muted">No recent income records</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($canViewExpenseOperations): ?>
        <div class="col-lg-6">
            <div class="card">
            <div class="card-header"><?= $canViewExpenseSummary ? 'Recent Expense Requests' : 'My Recent Expense Requests' ?></div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (!empty($recent_expenses)): ?>
                            <?php foreach ($recent_expenses as $exp): ?>
                                <tr>
                                    <td><?= htmlspecialchars($exp['request_date']) ?></td>
                                    <td>Tshs. <?= number_format($exp['amount_requested'], 2) ?></td>
                                    <td><?= htmlspecialchars($exp['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-muted">No recent expense requests</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <?php if ($canViewInventory): ?>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">Low Stock Alerts</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Item</th><th>Qty</th></tr></thead>
                        <tbody>
                        <?php if (!empty($low_stock_items)): ?>
                            <?php foreach ($low_stock_items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="text-center text-muted">No low stock alerts</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($canViewStations): ?>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">New Station Requests</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Date</th><th>Name</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (!empty($recent_stations)): ?>
                            <?php foreach ($recent_stations as $st): ?>
                                <tr>
                                    <td><?= htmlspecialchars($st['request_date']) ?></td>
                                    <td><?= htmlspecialchars($st['station_name']) ?></td>
                                    <td><?= htmlspecialchars($st['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-muted">No station requests found</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // prepare chart data from server-side variables
    const chartData = <?= json_encode($chart_data ?? ['months' => [], 'income' => [], 'expenses' => [], 'customerGrowth' => [], 'inventoryUsage' => [], 'stationProgress' => []]); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        const incomeExpenseCanvas = document.getElementById('incomeExpenseChart');
        if (incomeExpenseCanvas) {
        new Chart(incomeExpenseCanvas.getContext('2d'), {
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
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
        }

        const customerGrowthCanvas = document.getElementById('customerGrowthChart');
        if (customerGrowthCanvas) {
        new Chart(customerGrowthCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.months,
                datasets: [{
                    label: 'Customers',
                    data: chartData.customerGrowth,
                    borderColor: '#007bff',
                    backgroundColor: '#007bff44',
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
        }

        const inventoryCanvas = document.getElementById('inventoryUsageChart');
        if (inventoryCanvas) {
        new Chart(inventoryCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.months,
                datasets: [{
                    label: 'New Inventory Items',
                    data: chartData.inventoryUsage,
                    borderColor: '#ffc107',
                    backgroundColor: '#ffc10744',
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
        }

        // station progress as stacked bar
        const stationCanvas = document.getElementById('stationProgressChart');
        if (stationCanvas) {
        const stationCtx = stationCanvas.getContext('2d');
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
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { x: { stacked: true }, y: { stacked: true } }
            }
        });
        }

    });
</script>

<?php $content = ob_get_clean(); ?>
<?php include APP_ROOT . '/app/views/layouts/main.php'; ?>