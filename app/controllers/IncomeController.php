<?php
/**
 * IncomeController - Handle income/revenue operations
 */

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Income;
use App\Helpers\FileHelper;
use App\Helpers\ValidationHelper;

class IncomeController extends BaseController
{
    private $incomeModel;

    public function __construct()
    {
        parent::__construct();
        $this->incomeModel = new Income();
    }

    /**
     * Display income list
     */
    public function index()
    {
        $page = intval($this->query('page') ?? 1);
        $search = $this->query('search');

        $message = $this->getMessage();

        if ($search) {
            $income = $this->incomeModel->search($search);
        } else {
            $paginated = $this->incomeModel->paginate($page, ITEMS_PER_PAGE, [], 'date DESC');
            $income = $paginated['items'];
        }

        $this->data = [
            'user' => $this->user,
            'income' => $income,
            'message' => $message,
            'search' => $search,
            'page' => $page,
        ];

        $this->render('income/index', $this->data);
    }

    /**
     * Display income detail
     */
    public function show()
    {
        $id = intval($this->query('id'));

        if (!$id) {
            $this->error('Invalid income ID');
            $this->redirect(APP_URL . '/income.php');
        }

        $income = $this->incomeModel->find($id);

        if (!$income) {
            $this->error('Income record not found');
            $this->redirect(APP_URL . '/income.php');
        }

        $this->data = [
            'user' => $this->user,
            'income' => $income,
        ];

        $this->render('income/show', $this->data);
    }

    /**
     * Display add income form
     */
    public function create()
    {
        if (!$this->hasPermission(CAN_ADD_INCOME)) {
            $this->error('You do not have permission to add income');
            $this->redirect(APP_URL . '/income.php');
        }

        $this->data = [
            'user' => $this->user,
            'csrf_token' => $this->getCsrfToken(),
        ];

        $this->render('income/create', $this->data);
    }

    /**
     * Handle add income form submission
     */
    public function store()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/income/create.php');
        }

        if (!$this->hasPermission(CAN_ADD_INCOME)) {
            $this->error('You do not have permission');
            $this->redirect(APP_URL . '/income.php');
        }

        // Verify CSRF token
        if (!$this->verifyCsrfToken($this->post('csrf_token'))) {
            $this->error('Invalid security token');
            $this->redirect(APP_URL . '/income/create.php');
        }

        // Validate required fields
        $required = ['customer_name', 'service_type', 'amount', 'date', 'payment_method'];
        if (!$this->validateRequired($required, $_POST)) {
            $this->error('All required fields must be filled');
            $this->redirect(APP_URL . '/income/create.php');
        }

        // Validate amount
        $amount = floatval($this->post('amount'));
        if (!ValidationHelper::numeric($amount, 0)) {
            $this->error('Amount must be a positive number');
            $this->redirect(APP_URL . '/income/create.php');
        }

        // Validate date
        if (!ValidationHelper::dateFormat($this->post('date'), DATE_FORMAT)) {
            $this->error('Invalid date format');
            $this->redirect(APP_URL . '/income/create.php');
        }

        // Create income record
        $id = $this->incomeModel->create([
            'customer_name' => $this->sanitize($this->post('customer_name')),
            'service_type' => $this->sanitize($this->post('service_type')),
            'description' => $this->sanitize($this->post('description') ?? ''),
            'amount' => $amount,
            'payment_method' => $this->sanitize($this->post('payment_method')),
            'date' => $this->post('date'),
            'reference_number' => $this->sanitize($this->post('reference_number') ?? ''),
            'recorded_by' => $this->user['id'],
            'notes' => $this->sanitize($this->post('notes') ?? ''),
        ]);

        if ($id) {
            $this->logActivity('CREATE', 'Added income record', 'income', $id);
            $this->success('Income record added successfully');
            $this->redirect(APP_URL . '/income.php?id=' . $id);
        }

        $this->error('Failed to add income record');
        $this->redirect(APP_URL . '/income/create.php');
    }

    /**
     * Export income data
     */
    public function export()
    {
        $format = $this->query('format') ?? 'csv';
        $startDate = $this->query('start_date');
        $endDate = $this->query('end_date');

        $filters = [];
        if ($startDate && $endDate) {
            $filters = ['start_date' => $startDate, 'end_date' => $endDate];
        }

        $income = $this->incomeModel->search('', $filters);

        if ($format === 'csv') {
            $this->exportCsv($income);
        } elseif ($format === 'json') {
            $this->json($income);
        } elseif ($format === 'excel') {
            $this->exportExcel($income);
        } elseif ($format === 'pdf') {
            $this->exportPdf($income);
        }
    }

    /**
     * Export as CSV
     */
    private function exportCsv($data)
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="income_' . date('Y-m-d') . '.csv"');

        $file = fopen('php://output', 'w');

        // Header row
        fputcsv($file, ['Date', 'Customer', 'Service Type', 'Amount', 'Payment Method', 'Reference']);

        // Data rows
        foreach ($data as $row) {
            fputcsv($file, [
                $row['date'],
                $row['customer_name'],
                $row['service_type'],
                $row['amount'],
                $row['payment_method'],
                $row['reference_number'],
            ]);
        }

        fclose($file);
        exit();
    }

    /**
     * Export as Excel (simple HTML table with .xls extension)
     */
    private function exportExcel($data)
    {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="income_' . date('Y-m-d') . '.xls"');
        echo "<table><tr><th>Date</th><th>Customer</th><th>Service Type</th><th>Amount</th><th>Payment Method</th><th>Reference</th></tr>";
        foreach ($data as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['date']) . "</td>";
            echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['service_type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['amount']) . "</td>";
            echo "<td>" . htmlspecialchars($row['payment_method']) . "</td>";
            echo "<td>" . htmlspecialchars($row['reference_number']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        exit();
    }

    /**
     * Export as PDF (basic print-friendly HTML converted by browser)
     */
    private function exportPdf($data)
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

        $stream = "0.11 0.31 0.60 rg 0 770 612 72 re f\n";
        $stream .= "1 1 1 rg\n";
        $stream .= $drawText('F1', 18, 40, 812, 'ERMS Income Report');
        $stream .= $drawText('F1', 10, 40, 794, 'Generated: ' . date('d M Y H:i'));
        $stream .= "0.13 0.18 0.28 rg\n";
        $stream .= $drawText('F1', 9, 40, 742, 'Date | Customer | Service | Amount | Method | Reference');

        $y = 724;
        foreach ($data as $index => $row) {
            $line = implode(' | ', [
                (string) ($row['date'] ?? '-'),
                mb_substr((string) ($row['customer_name'] ?? '-'), 0, 14),
                mb_substr((string) ($row['service_type'] ?? '-'), 0, 12),
                (string) ($row['amount'] ?? '-'),
                mb_substr((string) ($row['payment_method'] ?? '-'), 0, 10),
                mb_substr((string) ($row['reference_number'] ?? '-'), 0, 14),
            ]);
            $stream .= $drawText('F1', 8.5, 40, $y, ($index + 1) . '. ' . $line);
            $y -= 13;
            if ($y < 40) {
                $stream .= $drawText('F1', 8, 40, 24, 'Output truncated to fit one PDF page.');
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
        header('Content-Disposition: attachment; filename="income_' . date('Y-m-d') . '.pdf"');
        echo $pdf;
        exit();
    }

    /**
     * Get income statistics
     */
    public function stats()
    {
        $stats = [
            'total_today' => $this->incomeModel->getTodayTotal(),
            'total_week' => $this->incomeModel->getWeekTotal(),
            'total_month' => $this->incomeModel->getMonthTotal(),
            'by_service' => $this->incomeModel->getByServiceType(),
        ];

        $this->json(['success' => true, 'data' => $stats]);
    }
}
