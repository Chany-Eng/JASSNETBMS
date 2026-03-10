<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$user = getCurrentUser();
$stats = getDashboardStats();

// Check for login success message
$login_success = isset($_SESSION['login_success']);
if ($login_success) {
    unset($_SESSION['login_success']);
}

// Check password expiration
$password_status = checkPasswordExpiration($user);

// Get recent transactions
$recent_income = $conn->query("SELECT * FROM income ORDER BY date DESC LIMIT 5");
$recent_expenses = $conn->query("SELECT er.*, u.full_name FROM expense_requests er JOIN users u ON er.requested_by = u.id ORDER BY request_date DESC LIMIT 5");

// Get chart data
$income_chart_data = [];
$expense_chart_data = [];
$months = [];

for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime($month));
    
    $result = $conn->query("SELECT SUM(amount) as total FROM income WHERE DATE_FORMAT(date, '%Y-%m') = '$month'");
    $income_chart_data[] = $result->fetch_assoc()['total'] ?? 0;
    
    $result = $conn->query("SELECT SUM(amount_requested) as total FROM expense_requests WHERE status = 'Completed' AND DATE_FORMAT(request_date, '%Y-%m') = '$month'");
    $expense_chart_data[] = $result->fetch_assoc()['total'] ?? 0;
}
?>

<?php include 'includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
        <?php if ($password_status == 'warning'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Your password will expire in <?php echo 28 - (new DateTime())->diff(new DateTime($user['password_last_changed']))->days; ?> days. Please change it soon.
            </div>
        <?php endif; ?>
        
        <?php if ($login_success): ?>
            <div class="toast-container position-fixed top-0 end-0 p-3">
                <div id="loginSuccessToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" style="font-size: 1.2rem; padding: 1rem; min-width: 400px;">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-check-circle me-2" style="font-size: 1.5rem;"></i> Login successful! Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>.
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!hasPermission(['Technician']) && !hasPermission(['Store Keeper'])): ?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-success animate-stagger">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-dollar-sign"></i> Income Today</h5>
                <h3>$<?php echo number_format($stats['income_today'], 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info animate-stagger">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-calendar-week"></i> Income This Week</h5>
                <h3>$<?php echo number_format($stats['income_week'], 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-primary animate-stagger">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-calendar-alt"></i> Income This Month</h5>
                <h3>$<?php echo number_format($stats['income_month'], 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning animate-stagger">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-chart-line"></i> Net Profit</h5>
                <h3>$<?php echo number_format($stats['net_profit'], 2); ?></h3>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card animate-stagger">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar"></i> Income vs Expenses</h5>
            </div>
            <div class="card-body">
                <canvas id="incomeExpenseChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card animate-stagger">
            <div class="card-header">
                <h5><i class="fas fa-chart-line"></i> Monthly Income</h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyIncomeChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card animate-stagger">
            <div class="card-header">
                <h5><i class="fas fa-receipt"></i> Approved Expenses</h5>
            </div>
            <div class="card-body">
                <h3 class="text-success">$<?php echo number_format($stats['approved_expenses'], 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card animate-stagger">
            <div class="card-header">
                <h5><i class="fas fa-clock"></i> Pending Requests</h5>
            </div>
            <div class="card-body">
                <h3 class="text-warning"><?php echo $stats['pending_requests']; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card animate-stagger">
            <div class="card-header">
                <h5><i class="fas fa-exclamation-triangle"></i> Low Stock Items</h5>
            </div>
            <div class="card-body">
                <h3 class="text-danger"><?php echo $stats['low_stock']; ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card animate-stagger">
            <div class="card-header">
                <h5><i class="fas fa-dollar-sign"></i> Recent Income</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recent_income->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['service_type']); ?></td>
                                <td>$<?php echo number_format($row['amount'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card animate-stagger">
            <div class="card-header">
                <h5><i class="fas fa-receipt"></i> Recent Expense Requests</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Requested By</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recent_expenses->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td>$<?php echo number_format($row['amount_requested'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $row['status'] == 'Completed' ? 'success' : 
                                             ($row['status'] == 'Rejected' ? 'danger' : 'warning'); 
                                    ?>"><?php echo $row['status']; ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
const incomeExpenseChart = new Chart(incomeExpenseCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            label: 'Income',
            data: <?php echo json_encode($income_chart_data); ?>,
            backgroundColor: 'rgba(40, 167, 69, 0.5)',
            borderColor: 'rgba(40, 167, 69, 1)',
            borderWidth: 1
        }, {
            label: 'Expenses',
            data: <?php echo json_encode($expense_chart_data); ?>,
            backgroundColor: 'rgba(220, 53, 69, 0.5)',
            borderColor: 'rgba(220, 53, 69, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

const monthlyIncomeCtx = document.getElementById('monthlyIncomeChart').getContext('2d');
const monthlyIncomeChart = new Chart(monthlyIncomeCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            label: 'Monthly Income',
            data: <?php echo json_encode($income_chart_data); ?>,
            backgroundColor: 'rgba(0, 123, 255, 0.2)',
            borderColor: 'rgba(0, 123, 255, 1)',
            borderWidth: 2,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php if ($login_success): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toast = new bootstrap.Toast(document.getElementById('loginSuccessToast'), { delay: 10000 });
    toast.show();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>