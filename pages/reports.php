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

function reportsBuildOverviewSummary(mysqli $conn, string $startDate, string $endDate): array
{
    $incomeResult = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM income WHERE date BETWEEN '$startDate' AND '$endDate'");
    $expenseResult = $conn->query("SELECT COALESCE(SUM(p.total_paid), 0) AS total_paid FROM expense_requests er LEFT JOIN (SELECT expense_request_id, SUM(amount_paid) AS total_paid FROM expense_payments GROUP BY expense_request_id) p ON p.expense_request_id = er.id WHERE er.request_date BETWEEN '$startDate' AND '$endDate'");
    $inventoryResult = $conn->query("SELECT COALESCE(SUM(quantity * COALESCE(NULLIF(purchase_price, 0), selling_price, 0)), 0) AS total_value FROM inventory WHERE COALESCE(is_deleted, 0) = 0");

    $incomeTotal = (float) (($incomeResult ? $incomeResult->fetch_assoc()['total'] : 0) ?? 0);
    $expenseTotal = (float) (($expenseResult ? $expenseResult->fetch_assoc()['total_paid'] : 0) ?? 0);
    $inventoryValue = (float) (($inventoryResult ? $inventoryResult->fetch_assoc()['total_value'] : 0) ?? 0);
    $profitLoss = $incomeTotal - $expenseTotal;

    return [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'income' => $incomeTotal,
        'expenses' => $expenseTotal,
        'profit' => $profitLoss > 0 ? $profitLoss : 0.0,
        'loss' => $profitLoss < 0 ? abs($profitLoss) : 0.0,
        'inventory_value' => $inventoryValue,
    ];
}

function reportsExportExcel(string $filename, array $headers, array $rows, array $overview): void
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');

    echo '<table border="1">';
    echo '<tr><th colspan="' . count($headers) . '">Report Overview</th></tr>';
    echo '<tr><td colspan="' . count($headers) . '">Period: ' . htmlspecialchars($overview['start_date']) . ' to ' . htmlspecialchars($overview['end_date']) . '</td></tr>';
    echo '<tr><td colspan="' . count($headers) . '">Total Income: ' . number_format((float) $overview['income'], 2) . ' | Total Expenses: ' . number_format((float) $overview['expenses'], 2) . ' | Profit: ' . number_format((float) $overview['profit'], 2) . ' | Loss: ' . number_format((float) $overview['loss'], 2) . ' | Inventory Value: ' . number_format((float) $overview['inventory_value'], 2) . '</td></tr>';
    echo '<tr><td colspan="' . count($headers) . '"></td></tr>';
    echo '<tr>';
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

function reportsExportPdf(string $title, array $headers, array $rows, array $overview): void
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

    $drawFilledRect = static function ($x, $y, $w, $h, $r, $g, $b): string {
        return sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $r, $g, $b, $x, $y, $w, $h);
    };

    $drawLine = static function ($x1, $y1, $x2, $y2): string {
        return sprintf("0.85 0.89 0.93 RG %.2F %.2F m %.2F %.2F l S\n", $x1, $y1, $x2, $y2);
    };

    $lines = [];
    foreach ($rows as $row) {
        $rendered = [];
        foreach ($row as $value) {
            $text = trim((string) $value);
            $rendered[] = strlen($text) > 18 ? substr($text, 0, 15) . '...' : ($text !== '' ? $text : '-');
        }
        $lines[] = implode(' | ', $rendered);
    }

    $stream = '';
    $stream .= $drawFilledRect(0, 770, 612, 72, 0.11, 0.31, 0.60);
    $stream .= "1 1 1 rg\n";
    $stream .= $drawText('F1', 18, 40, 812, 'ERMS ' . $title);
    $stream .= $drawText('F1', 10, 40, 794, 'Generated: ' . date('d M Y H:i'));
    $stream .= $drawText('F1', 10, 40, 780, 'Period: ' . $overview['start_date'] . ' to ' . $overview['end_date']);
    $stream .= "0.13 0.18 0.28 rg\n";
    $stream .= $drawText('F1', 10, 40, 748, 'Income: Tshs. ' . number_format((float) $overview['income'], 2));
    $stream .= $drawText('F1', 10, 230, 748, 'Expenses: Tshs. ' . number_format((float) $overview['expenses'], 2));
    $stream .= $drawText('F1', 10, 420, 748, 'Inventory: Tshs. ' . number_format((float) $overview['inventory_value'], 2));
    $stream .= $drawText('F1', 10, 40, 732, 'Profit: Tshs. ' . number_format((float) $overview['profit'], 2));
    $stream .= $drawText('F1', 10, 230, 732, 'Loss: Tshs. ' . number_format((float) $overview['loss'], 2));
    $stream .= $drawLine(36, 720, 576, 720);
    $stream .= $drawText('F1', 9, 40, 706, 'Columns: ' . implode(' | ', $headers));

    $y = 688;
    foreach ($lines as $index => $line) {
        $stream .= $drawText('F1', 8.5, 40, $y, ($index + 1) . '. ' . $line);
        $y -= 13;
        if ($y < 40) {
            $stream .= $drawText('F1', 8, 40, 24, 'Output truncated to fit one PDF page. Use Excel export for full detail.');
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
        $result = $conn->query("SELECT i.date, i.customer_name, i.service_type, FORMAT(i.amount, 2) AS amount, i.payment_method, COALESCE(NULLIF(i.transaction_reference, ''), '-') AS reference_number, u.full_name AS recorded_by FROM income i JOIN users u ON i.user_id = u.id WHERE i.date BETWEEN '$startDate' AND '$endDate' ORDER BY i.date DESC");
        return [
            'title' => 'Income Report',
            'headers' => ['Date', 'Customer', 'Service Type', 'Amount', 'Payment Method', 'Reference', 'Recorded By'],
            'rows' => reportsBuildRows($result),
        ];
    }

    if ($reportType === 'expenses') {
        $result = $conn->query("SELECT er.request_date, u.full_name AS requested_by, er.category, FORMAT(er.amount_requested, 2) AS amount_requested, FORMAT(COALESCE(p.total_paid, 0), 2) AS amount_paid, COALESCE(NULLIF(r.receipt_number, ''), '-') AS receipt_number, COALESCE(NULLIF(r.vendor_name, ''), '-') AS vendor_name, er.status FROM expense_requests er JOIN users u ON er.requested_by = u.id LEFT JOIN (SELECT expense_request_id, SUM(amount_paid) AS total_paid FROM expense_payments GROUP BY expense_request_id) p ON p.expense_request_id = er.id LEFT JOIN (SELECT expense_request_id, MAX(receipt_number) AS receipt_number, MAX(vendor_name) AS vendor_name FROM receipts GROUP BY expense_request_id) r ON r.expense_request_id = er.id WHERE er.request_date BETWEEN '$startDate' AND '$endDate' ORDER BY er.request_date DESC");
        return [
            'title' => 'Expense Report',
            'headers' => ['Date', 'Requested By', 'Category', 'Amount Requested', 'Amount Paid', 'Receipt No', 'Vendor', 'Status'],
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
    $overview = reportsBuildOverviewSummary($conn, $start_date, $end_date);

    if ($format === 'excel') {
        reportsExportExcel($report_type . '_report_' . date('Y-m-d'), $dataset['headers'], $dataset['rows'], $overview);
    }

    if ($format === 'pdf') {
        reportsExportPdf($dataset['title'], $dataset['headers'], $dataset['rows'], $overview);
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [$dataset['title']]);
    fputcsv($output, ['Period', $overview['start_date'] . ' to ' . $overview['end_date']]);
    fputcsv($output, ['Total Income', number_format((float) $overview['income'], 2), 'Total Expenses', number_format((float) $overview['expenses'], 2)]);
    fputcsv($output, ['Profit', number_format((float) $overview['profit'], 2), 'Loss', number_format((float) $overview['loss'], 2)]);
    fputcsv($output, ['Inventory Value', number_format((float) $overview['inventory_value'], 2)]);
    fputcsv($output, []);
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

$profit_loss = (float) ($income_summary['total'] ?? 0) - (float) ($expense_summary['total_paid'] ?? 0);
$income_count = (int) ($income_summary['count'] ?? 0);
$expense_count = (int) ($expense_summary['count'] ?? 0);
$inventory_total_items = (int) ($inventory_summary['total_items'] ?? 0);
$inventory_total_quantity = (int) ($inventory_summary['total_quantity'] ?? 0);
$inventory_total_value = (float) ($inventory_summary['total_value'] ?? 0);
$profit_amount = $profit_loss > 0 ? $profit_loss : 0.0;
$loss_amount = $profit_loss < 0 ? abs($profit_loss) : 0.0;

$report_chart = [
    'labels' => ['Income', 'Expenses', 'Profit', 'Loss', 'Inventory Value'],
    'values' => [
        (float) ($income_summary['total'] ?? 0),
        (float) ($expense_summary['total_paid'] ?? 0),
        $profit_amount,
        $loss_amount,
        $inventory_total_value,
    ],
    'counts' => [$income_count, $expense_count, (int) $profit_amount, (int) $loss_amount, $inventory_total_items],
];
?>

<?php include '../includes/header.php'; ?>

<div class="row g-4 mb-4 align-items-stretch">
    <div class="col-xl-8">
        <div class="report-shell p-4 h-100">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <div class="text-uppercase fw-semibold small mb-2" style="letter-spacing: 0.12em; color: #1d4f98;">Clean Modern Reports</div>
                    <h2 class="mb-2 border-0 shadow-none bg-transparent p-0"><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>
                    <p class="section-caption mb-0">Professional reporting for finance, expenses, inventory, and station operations with export-ready tables and quick decision metrics.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="app-status-chip"><i class="fas fa-calendar-range"></i> <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></span>
                    <span class="app-status-chip"><i class="fas fa-file-export"></i> CSV, Excel, PDF</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="report-shell p-4 h-100">
            <div class="small text-uppercase fw-semibold mb-2" style="letter-spacing: 0.1em; color: #1d4f98;">Report Window</div>
            <div class="fw-bold mb-1" style="color: #223047; font-size: 1.1rem;">Filtered analytics period</div>
            <div class="section-caption mb-3">Use the filter card below to regenerate report values, exports, and tables for the selected date range.</div>
            <div class="d-grid gap-2">
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span class="section-caption">Start date</span><strong><?php echo date('d M Y', strtotime($start_date)); ?></strong></div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span class="section-caption">End date</span><strong><?php echo date('d M Y', strtotime($end_date)); ?></strong></div>
                <div class="d-flex justify-content-between align-items-center pt-2"><span class="section-caption">Low stock alerts</span><strong><?php echo number_format((int) $low_stock_count); ?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card page-shell-card">
            <div class="card-header">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Report Filters</h5>
                    <div class="d-flex gap-2 flex-wrap report-print-toolbar">
                        <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print()"><i class="fas fa-print me-2"></i>Print Report</button>
                        <a href="?export=income&format=pdf&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-outline-danger btn-sm"><i class="fas fa-file-pdf me-2"></i>Quick PDF</a>
                        <a href="?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-primary btn-sm">Today</a>
                        <a href="?start_date=<?php echo date('Y-m-d', strtotime('monday this week')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-primary btn-sm">This Week</a>
                        <a href="?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-primary btn-sm">This Month</a>
                    </div>
                </div>
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

<div class="report-print-summary d-none">
    <div class="report-print-summary-title">ERMS Reports Overview</div>
    <div class="report-print-summary-meta">Period: <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?></div>
    <div class="report-print-summary-grid">
        <div><strong>Total Income:</strong> Tshs. <?php echo number_format((float) ($income_summary['total'] ?? 0), 2); ?></div>
        <div><strong>Total Expenses:</strong> Tshs. <?php echo number_format((float) ($expense_summary['total_paid'] ?? 0), 2); ?></div>
        <div><strong>Profit:</strong> Tshs. <?php echo number_format($profit_amount, 2); ?></div>
        <div><strong>Loss:</strong> Tshs. <?php echo number_format($loss_amount, 2); ?></div>
        <div><strong>Inventory Value:</strong> Tshs. <?php echo number_format($inventory_total_value, 2); ?></div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card text-white report-metric-card report-gradient-income">
            <div class="card-body">
                <div class="metric-label"><i class="fas fa-sack-dollar me-2"></i>Total Income</div>
                <div class="metric-value">Tshs. <?php echo number_format($income_summary['total'] ?? 0, 2); ?></div>
                <div class="metric-meta"><?php echo number_format($income_count); ?> transactions recorded</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card text-white report-metric-card report-gradient-expense">
            <div class="card-body">
                <div class="metric-label"><i class="fas fa-file-invoice-dollar me-2"></i>Total Expenses</div>
                <div class="metric-value">Tshs. <?php echo number_format($expense_summary['total_paid'] ?? 0, 2); ?></div>
                <div class="metric-meta"><?php echo number_format($expense_count); ?> requests processed</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card text-white report-metric-card report-gradient-profit">
            <div class="card-body">
                <div class="metric-label"><i class="fas fa-arrow-trend-up me-2"></i>Profit</div>
                <div class="metric-value">Tshs. <?php echo number_format($profit_amount, 2); ?></div>
                <div class="metric-meta"><?php echo $profit_amount > 0 ? 'Profit realized in selected period' : 'No realized profit in selected period'; ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card text-white report-metric-card report-gradient-loss">
            <div class="card-body">
                <div class="metric-label"><i class="fas fa-arrow-trend-down me-2"></i>Loss</div>
                <div class="metric-value">Tshs. <?php echo number_format($loss_amount, 2); ?></div>
                <div class="metric-meta"><?php echo $loss_amount > 0 ? 'Loss recorded in selected period' : 'No recorded loss in selected period'; ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card text-white report-metric-card report-gradient-stock">
            <div class="card-body">
                <div class="metric-label"><i class="fas fa-boxes-stacked me-2"></i>Inventory Snapshot</div>
                <div class="metric-value"><?php echo number_format($inventory_total_items); ?></div>
                <div class="metric-meta"><?php echo number_format((int) $low_stock_count); ?> low stock items</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card page-shell-card h-100">
            <div class="card-header">
                <h5><i class="fas fa-chart-column"></i> Financial Overview Chart</h5>
            </div>
            <div class="card-body chart-panel chart-panel-compact">
                <canvas id="reportsOverviewChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card page-shell-card h-100">
            <div class="card-header">
                <h5><i class="fas fa-circle-info"></i> Quick Report Totals</h5>
            </div>
            <div class="card-body">
                <div class="report-quick-grid">
                <div class="report-quick-item d-flex justify-content-between align-items-center border rounded-4 p-3 bg-light-subtle">
                    <div>
                        <div class="small text-uppercase fw-semibold text-muted">Inventory Value</div>
                        <div class="fw-bold">Tshs. <?php echo number_format($inventory_total_value, 2); ?></div>
                    </div>
                    <i class="fas fa-warehouse text-primary fs-4"></i>
                </div>
                <div class="report-quick-item d-flex justify-content-between align-items-center border rounded-4 p-3 bg-light-subtle">
                    <div>
                        <div class="small text-uppercase fw-semibold text-muted">Total Quantity</div>
                        <div class="fw-bold"><?php echo number_format($inventory_total_quantity); ?></div>
                    </div>
                    <i class="fas fa-layer-group text-warning fs-4"></i>
                </div>
                <div class="report-quick-item d-flex justify-content-between align-items-center border rounded-4 p-3 bg-light-subtle">
                    <div>
                        <div class="small text-uppercase fw-semibold text-muted">Transactions</div>
                        <div class="fw-bold"><?php echo number_format($income_count + $expense_count); ?></div>
                    </div>
                    <i class="fas fa-arrows-rotate text-success fs-4"></i>
                </div>
                <div class="report-quick-item d-flex justify-content-between align-items-center border rounded-4 p-3 bg-light-subtle">
                    <div>
                        <div class="small text-uppercase fw-semibold text-muted">Net Position</div>
                        <div class="fw-bold <?php echo $profit_loss >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $profit_loss >= 0 ? 'Profit' : 'Loss'; ?>
                        </div>
                    </div>
                    <i class="fas <?php echo $profit_loss >= 0 ? 'fa-circle-arrow-up text-success' : 'fa-circle-arrow-down text-danger'; ?> fs-4"></i>
                </div>
                <div class="report-quick-item d-flex justify-content-between align-items-center border rounded-4 p-3 bg-light-subtle">
                    <div>
                        <div class="small text-uppercase fw-semibold text-muted">Report Formats</div>
                        <div class="fw-bold">CSV, Excel, PDF</div>
                    </div>
                    <i class="fas fa-file-export text-danger fs-4"></i>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card page-shell-card">
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
                    <table class="table table-striped table-modern">
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
        <div class="card page-shell-card">
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
                    <table class="table table-striped table-modern">
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
        <div class="card page-shell-card">
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
                    <table class="table table-striped table-modern">
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
                <div class="mt-3 d-grid gap-2">
                    <div><strong>Total Items:</strong> <?php echo $inventory_summary['total_items']; ?></div>
                    <div><strong>Total Quantity:</strong> <?php echo $inventory_summary['total_quantity']; ?></div>
                    <div><strong>Total Value:</strong> Tshs. <?php echo number_format($inventory_summary['total_value'], 2); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card page-shell-card">
            <div class="card-header">
                <h5><i class="fas fa-broadcast-tower"></i> Station Projects</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-modern">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reportsOverviewChart = document.getElementById('reportsOverviewChart');
    if (!reportsOverviewChart) {
        return;
    }

    const reportChart = <?= json_encode($report_chart); ?>;
    new Chart(reportsOverviewChart.getContext('2d'), {
        type: 'bar',
        data: {
            labels: reportChart.labels,
            datasets: [{
                label: 'Amount / Value',
                data: reportChart.values,
                backgroundColor: ['#2d9d78', '#d45a6b', '#2969c7', '#d97706', '#d48b18'],
                borderRadius: 12,
                maxBarThickness: 34
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Tshs. ' + Number(context.parsed.y).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#60758d', maxRotation: 0, minRotation: 0, font: { size: 11 } },
                    border: { display: false }
                },
                y: {
                    ticks: {
                        color: '#60758d',
                        font: { size: 11 },
                        callback: function(value) {
                            return 'Tshs. ' + Number(value).toLocaleString();
                        }
                    },
                    grid: { color: 'rgba(96, 117, 141, 0.15)' },
                    border: { display: false }
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>