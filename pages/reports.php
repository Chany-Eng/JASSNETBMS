<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Director', 'Super Admin', 'Accountant']);
ensureInventorySoftDeleteSchema($conn);

$message = '';
$error = '';

function reportsBuildRows(mysqli_result $result): array
{
    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }
    return $rows;
}

function reportsExportExcel(string $filename, array $headers, array $rows): void
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');

    echo '<table border="1"><tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $value) {
            echo '<td>' . htmlspecialchars((string) $value) . '</td>';
        }
        echo '</tr>';
    }

    echo '</table>';
    exit();
}

function reportsExportPdf(string $title, array $headers, array $rows): void
{
    $pdfSafe = static function ($value): string {
        $text = preg_replace('/\s+/', ' ', trim((string) ($value ?? '-')));
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($text === false || $text === '') {
            $text = '-';
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    };

    $drawText = static function ($font, $size, $x, $y, $text) use ($pdfSafe): string {
        return "BT /{$font} {$size} Tf {$x} {$y} Td (" . $pdfSafe($text) . ") Tj ET\n";
    };

    $lines = [];
    $lines[] = $title . ' - ' . date('Y-m-d H:i');
    $lines[] = implode(' | ', $headers);
    $lines[] = str_repeat('-', 110);
    foreach ($rows as $row) {
        $rendered = [];
        foreach ($row as $value) {
            $text = trim((string) $value);
            $rendered[] = strlen($text) > 24 ? substr($text, 0, 21) . '...' : ($text !== '' ? $text : '-');
        }
        $lines[] = implode(' | ', $rendered);
    }

    $stream = '';
    $y = 800;
    foreach ($lines as $line) {
        $stream .= $drawText('F1', 10, 40, $y, $line);
        $y -= 14;
        if ($y < 40) {
            break;
        }
    }

    $objects = [];
    $offsets = [];
    $pdf = "%PDF-1.4\n";

    $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
    $objects[] = "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n";
    $objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n";
    $objects[] = "4 0 obj << /Length " . strlen($stream) . " >> stream\n" . $stream . "endstream\nendobj\n";
    $objects[] = "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf('%010d 00000 n ' . "\n", $offset);
    }
    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]/', '_', strtolower($title)) . '_' . date('Ymd_His') . '.pdf"');
    echo $pdf;
    exit();
}

function reportsGetDataset(mysqli $conn, string $reportType, string $startDate, string $endDate): array
{
    if ($reportType === 'income') {
        $result = $conn->query("SELECT i.date, i.customer_name, i.service_type, FORMAT(i.amount, 2) AS amount, i.payment_method, u.full_name AS recorded_by FROM income i JOIN users u ON i.user_id = u.id WHERE i.date BETWEEN '$startDate' AND '$endDate' ORDER BY i.date DESC");
        return [
            'title' => 'Income Report',
            'headers' => ['Date', 'Customer', 'Service Type', 'Amount', 'Payment Method', 'Recorded By'],
            'rows' => reportsBuildRows($result),
        ];
    }

    if ($reportType === 'expenses') {
        $result = $conn->query("SELECT er.request_date, u.full_name AS requested_by, er.category, FORMAT(er.amount_requested, 2) AS amount_requested, FORMAT(COALESCE(p.total_paid, 0), 2) AS amount_paid, er.status FROM expense_requests er JOIN users u ON er.requested_by = u.id LEFT JOIN (SELECT expense_request_id, SUM(amount_paid) AS total_paid FROM expense_payments GROUP BY expense_request_id) p ON p.expense_request_id = er.id WHERE er.request_date BETWEEN '$startDate' AND '$endDate' ORDER BY er.request_date DESC");
        return [
            'title' => 'Expense Report',
            'headers' => ['Date', 'Requested By', 'Category', 'Amount Requested', 'Amount Paid', 'Status'],
            'rows' => reportsBuildRows($result),
        ];
    }

    $result = $conn->query("SELECT item_name, category, quantity, FORMAT(COALESCE(NULLIF(purchase_price, 0), selling_price, 0), 2) AS unit_value, FORMAT(quantity * COALESCE(NULLIF(purchase_price, 0), selling_price, 0), 2) AS total_value, supplier, status FROM inventory WHERE COALESCE(is_deleted, 0) = 0 ORDER BY category, item_name");
    return [
        'title' => 'Inventory Report',
        'headers' => ['Item Name', 'Category', 'Quantity', 'Unit Value', 'Total Value', 'Supplier', 'Status'],
        'rows' => reportsBuildRows($result),
    ];
}

if (isset($_GET['export'])) {
    $report_type = $_GET['export'];
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    $format = $_GET['format'] ?? 'csv';
    $dataset = reportsGetDataset($conn, $report_type, $start_date, $end_date);

    if ($format === 'excel') {
        reportsExportExcel($report_type . '_report_' . date('Y-m-d'), $dataset['headers'], $dataset['rows']);
    }

    if ($format === 'pdf') {
        reportsExportPdf($dataset['title'], $dataset['headers'], $dataset['rows']);
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, $dataset['headers']);
    foreach ($dataset['rows'] as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

// Get report data
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Income summary
$income_result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM income WHERE date BETWEEN '$start_date' AND '$end_date'");
$income_summary = $income_result->fetch_assoc();

// Expense summary
$expense_result = $conn->query("SELECT COALESCE(SUM(er.amount_requested), 0) as total_requested, COALESCE(SUM(p.total_paid), 0) as total_paid, COUNT(*) as count FROM expense_requests er LEFT JOIN (SELECT expense_request_id, SUM(amount_paid) AS total_paid FROM expense_payments GROUP BY expense_request_id) p ON p.expense_request_id = er.id WHERE er.request_date BETWEEN '$start_date' AND '$end_date'");
$expense_summary = $expense_result->fetch_assoc();

// Inventory summary
$inventory_result = $conn->query("SELECT COUNT(*) as total_items, SUM(quantity) as total_quantity, SUM(quantity * purchase_price) as total_value FROM inventory WHERE COALESCE(is_deleted, 0) = 0");
$inventory_summary = $inventory_result->fetch_assoc();

// Low stock items
$low_stock_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity < 5 AND COALESCE(is_deleted, 0) = 0");
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
                <h3>Tshs. <?php echo number_format($income_summary['total'] ?? 0, 2); ?></h3>
                <small><?php echo $income_summary['count'] ?? 0; ?> transactions</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-receipt"></i> Total Expenses</h5>
                <h3>Tshs. <?php echo number_format($expense_summary['total_paid'] ?? 0, 2); ?></h3>
                <small><?php echo $expense_summary['count'] ?? 0; ?> requests</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-chart-line"></i> Net Profit</h5>
                <h3>Tshs. <?php echo number_format(($income_summary['total'] ?? 0) - ($expense_summary['total_paid'] ?? 0), 2); ?></h3>
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
                <div class="d-flex gap-2 flex-wrap">
                    <a href="?export=income&format=csv&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-outline-primary">Export CSV</a>
                    <a href="?export=income&format=excel&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-outline-success">Export Excel</a>
                    <a href="?export=income&format=pdf&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-outline-danger">Export PDF</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Amount (Tshs.)</th>
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
                                <td>Tshs. <?php echo number_format($row['amount'], 2); ?></td>
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
                <div class="d-flex gap-2 flex-wrap">
                    <a href="?export=expenses&format=csv&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-outline-primary">Export CSV</a>
                    <a href="?export=expenses&format=excel&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-outline-success">Export Excel</a>
                    <a href="?export=expenses&format=pdf&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-outline-danger">Export PDF</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Requested By</th>
                                <th>Category</th>
                                <th>Amount (Tshs.)</th>
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
                                <td>Tshs. <?php echo number_format($row['amount_requested'], 2); ?></td>
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
                <div class="d-flex gap-2 flex-wrap">
                    <a href="?export=inventory&format=csv" class="btn btn-sm btn-outline-primary">Export CSV</a>
                    <a href="?export=inventory&format=excel" class="btn btn-sm btn-outline-success">Export Excel</a>
                    <a href="?export=inventory&format=pdf" class="btn btn-sm btn-outline-danger">Export PDF</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Value (Tshs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $inventory_records = $conn->query("SELECT item_name, category, quantity, (quantity * purchase_price) as value FROM inventory WHERE COALESCE(is_deleted, 0) = 0 ORDER BY category, item_name LIMIT 10");
                            while ($row = $inventory_records->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo $row['quantity']; ?></td>
                                <td>Tshs. <?php echo number_format($row['value'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <strong>Total Items:</strong> <?php echo $inventory_summary['total_items']; ?><br>
                    <strong>Total Quantity:</strong> <?php echo $inventory_summary['total_quantity']; ?><br>
                    <strong>Total Value:</strong> Tshs. <?php echo number_format($inventory_summary['total_value'], 2); ?>
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
                                <th>Cost (Tshs.)</th>
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
                                <td>Tshs. <?php echo number_format($row['total_estimated_cost'], 2); ?></td>
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