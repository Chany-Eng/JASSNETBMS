<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$message = '';
$error = '';

if (isset($_GET['export'])) {
    $report_type = $_GET['export'];
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($report_type == 'income') {
        fputcsv($output, ['Date', 'Customer', 'Service Type', 'Amount', 'Payment Method', 'Recorded By']);
        
        $result = $conn->query("SELECT i.date, i.customer_name, i.service_type, i.amount, i.payment_method, u.full_name FROM income i JOIN users u ON i.user_id = u.id WHERE i.date BETWEEN '$start_date' AND '$end_date' ORDER BY i.date");
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
    } elseif ($report_type == 'expenses') {
        fputcsv($output, ['Date', 'Requested By', 'Category', 'Amount Requested', 'Amount Paid', 'Status']);
        
        $result = $conn->query("SELECT er.request_date, u.full_name, er.category, er.amount_requested, COALESCE(ep.amount_paid, 0) as amount_paid, er.status FROM expense_requests er JOIN users u ON er.requested_by = u.id LEFT JOIN expense_payments ep ON er.id = ep.expense_request_id WHERE er.request_date BETWEEN '$start_date' AND '$end_date' ORDER BY er.request_date");
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
    } elseif ($report_type == 'inventory') {
        fputcsv($output, ['Item Name', 'Category', 'Quantity', 'Purchase Price', 'Selling Price', 'Supplier', 'Status']);
        
        $result = $conn->query("SELECT item_name, category, quantity, purchase_price, selling_price, supplier, status FROM inventory ORDER BY category, item_name");
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit();
}

// Get report data
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Income summary
$income_result = $conn->query("SELECT SUM(amount) as total, COUNT(*) as count FROM income WHERE date BETWEEN '$start_date' AND '$end_date'");
$income_summary = $income_result->fetch_assoc();

// Expense summary
$expense_result = $conn->query("SELECT SUM(amount_requested) as total_requested, SUM(COALESCE(ep.amount_paid, 0)) as total_paid, COUNT(*) as count FROM expense_requests er LEFT JOIN expense_payments ep ON er.id = ep.expense_request_id WHERE er.request_date BETWEEN '$start_date' AND '$end_date'");
$expense_summary = $expense_result->fetch_assoc();

// Inventory summary
$inventory_result = $conn->query("SELECT COUNT(*) as total_items, SUM(quantity) as total_quantity, SUM(quantity * purchase_price) as total_value FROM inventory");
$inventory_summary = $inventory_result->fetch_assoc();

// Low stock items
$low_stock_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity < 5");
$low_stock_count = $low_stock_result->fetch_assoc()['count'];
?>

<?php include '../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> Report Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-dollar-sign"></i> Total Income</h5>
                <h3>$<?php echo number_format($income_summary['total'] ?? 0, 2); ?></h3>
                <small><?php echo $income_summary['count'] ?? 0; ?> transactions</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-receipt"></i> Total Expenses</h5>
                <h3>$<?php echo number_format($expense_summary['total_paid'] ?? 0, 2); ?></h3>
                <small><?php echo $expense_summary['count'] ?? 0; ?> requests</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-chart-line"></i> Net Profit</h5>
                <h3>$<?php echo number_format(($income_summary['total'] ?? 0) - ($expense_summary['total_paid'] ?? 0), 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-exclamation-triangle"></i> Low Stock Items</h5>
                <h3><?php echo $low_stock_count; ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-dollar-sign"></i> Income Report</h5>
                <a href="?export=income&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-outline-primary">Export CSV</a>
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
                            <?php
                            $income_records = $conn->query("SELECT i.date, i.customer_name, i.service_type, i.amount FROM income i WHERE i.date BETWEEN '$start_date' AND '$end_date' ORDER BY i.date DESC LIMIT 10");
                            while ($row = $income_records->fetch_assoc()):
                            ?>
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
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-receipt"></i> Expense Report</h5>
                <a href="?export=expenses&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-outline-primary">Export CSV</a>
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
                            <?php
                            $expense_records = $conn->query("SELECT er.request_date, u.full_name, er.category, er.amount_requested, er.status FROM expense_requests er JOIN users u ON er.requested_by = u.id WHERE er.request_date BETWEEN '$start_date' AND '$end_date' ORDER BY er.request_date DESC LIMIT 10");
                            while ($row = $expense_records->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td>$<?php echo number_format($row['amount_requested'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $row['status'] == 'Completed' ? 'success' : ($row['status'] == 'Rejected' ? 'danger' : 'warning'); ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
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

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-boxes"></i> Inventory Report</h5>
                <a href="?export=inventory" class="btn btn-sm btn-outline-primary">Export CSV</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $inventory_records = $conn->query("SELECT item_name, category, quantity, (quantity * purchase_price) as value FROM inventory ORDER BY category, item_name LIMIT 10");
                            while ($row = $inventory_records->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo $row['quantity']; ?></td>
                                <td>$<?php echo number_format($row['value'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <strong>Total Items:</strong> <?php echo $inventory_summary['total_items']; ?><br>
                    <strong>Total Quantity:</strong> <?php echo $inventory_summary['total_quantity']; ?><br>
                    <strong>Total Value:</strong> $<?php echo number_format($inventory_summary['total_value'], 2); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-broadcast-tower"></i> Station Projects</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Station</th>
                                <th>Status</th>
                                <th>Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $station_records = $conn->query("SELECT station_name, status, total_estimated_cost FROM station_requests ORDER BY request_date DESC LIMIT 10");
                            while ($row = $station_records->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['station_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $row['status'] == 'Completed' ? 'success' : ($row['status'] == 'Rejected' ? 'danger' : 'info'); ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td>$<?php echo number_format($row['total_estimated_cost'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>