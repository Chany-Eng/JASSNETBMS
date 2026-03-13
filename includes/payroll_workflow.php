<?php

function payrollEnsureSchema(mysqli $conn): void
{
    snippeEnsureUserPayoutFields($conn);
    ensureActivityLogSchema($conn);

    $conn->query(
        "CREATE TABLE IF NOT EXISTS salary_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            salary_month DATE NOT NULL,
            basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
            bonus_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            deduction_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
            payout_channel VARCHAR(20) NOT NULL DEFAULT 'mobile',
            payout_destination VARCHAR(120) NULL,
            request_comment TEXT NULL,
            manager_comment TEXT NULL,
            director_comment TEXT NULL,
            accountant_final_comment TEXT NULL,
            payment_reference VARCHAR(120) NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Pending Manager Approval',
            manager_approved_by INT NULL,
            manager_approved_at DATETIME NULL,
            director_approved_by INT NULL,
            director_approved_at DATETIME NULL,
            finalized_by INT NULL,
            finalized_at DATETIME NULL,
            rejected_by INT NULL,
            rejected_at DATETIME NULL,
            rejection_comment TEXT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_salary_requests_user (user_id),
            KEY idx_salary_requests_status (status),
            KEY idx_salary_requests_month (salary_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function payrollPayoutLabel(string $channel): string
{
    return strtolower(trim($channel)) === 'bank' ? 'Bank Transfer' : 'Mobile Money';
}

function payrollResolvePayoutDestination(array $user): string
{
    $channel = strtolower(trim((string) ($user['preferred_payout_channel'] ?? 'mobile')));
    if ($channel === 'bank') {
        $bankLabel = trim((string) ($user['bank_name'] ?? ''));
        $bankAccount = trim((string) ($user['bank_account_number'] ?? ''));
        if ($bankLabel !== '' || $bankAccount !== '') {
            return trim($bankLabel . ' ' . $bankAccount);
        }
    }

    $mobile = trim((string) (($user['payout_phone'] ?? '') !== '' ? $user['payout_phone'] : ($user['phone'] ?? '')));
    if ($mobile !== '') {
        return $mobile;
    }

    return '';
}

function payrollStatusBadgeClass(string $status): string
{
    if ($status === 'Paid') {
        return 'success';
    }
    if ($status === 'Rejected') {
        return 'danger';
    }
    if ($status === 'Pending Director Approval') {
        return 'info';
    }
    if ($status === 'Pending Accountant Final Approval') {
        return 'primary';
    }
    return 'warning';
}

function payrollExportExcel(string $filename, array $rows): void
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');

    echo '<table border="1"><tr>';
    echo '<th>Month</th><th>Employee</th><th>Employee ID</th><th>ID Number</th><th>Net Salary</th><th>Payout Method</th><th>Payout Destination</th><th>Status</th><th>Paid Date</th><th>Accountant Signature</th>';
    echo '</tr>';

    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string) ($row['salary_month_label'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['employee_name'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['employee_id'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['id_number'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars(number_format((float) ($row['net_salary'] ?? 0), 2)) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['payout_method_label'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['payout_destination'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['status'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['paid_date_label'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['accountant_signature'] ?? '')) . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    exit();
}

function payrollRenderPayslipPdf(array $row): void
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

    $lines = [
        'JASSNET Salary Payslip',
        'Employee: ' . ($row['employee_name'] ?? '-'),
        'Month: ' . ($row['salary_month_label'] ?? '-'),
        'Payment Date: ' . ($row['paid_date_label'] ?? '-'),
        'Day: ' . ($row['paid_day_label'] ?? '-'),
        'Basic Salary: Tshs. ' . number_format((float) ($row['basic_salary'] ?? 0), 2),
        'Bonus: Tshs. ' . number_format((float) ($row['bonus_amount'] ?? 0), 2),
        'Deductions: Tshs. ' . number_format((float) ($row['deduction_amount'] ?? 0), 2),
        'Net Salary Paid: Tshs. ' . number_format((float) ($row['net_salary'] ?? 0), 2),
        'Payment Method: ' . ($row['payout_method_label'] ?? '-'),
        'Destination: ' . ($row['payout_destination'] ?? '-'),
        'Reference: ' . (($row['payment_reference'] ?? '') !== '' ? $row['payment_reference'] : '-'),
        'Accountant Comment: ' . (($row['accountant_final_comment'] ?? '') !== '' ? $row['accountant_final_comment'] : '-'),
        'Accountant Signature: ' . (($row['accountant_signature'] ?? '') !== '' ? $row['accountant_signature'] : '________________'),
    ];

    $stream = '';
    $y = 800;
    foreach ($lines as $index => $line) {
        $fontSize = $index === 0 ? 16 : 11;
        $stream .= $drawText('F1', $fontSize, 40, $y, $line);
        $y -= $index === 0 ? 28 : 18;
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
    header('Content-Disposition: attachment; filename="payslip_' . preg_replace('/[^A-Za-z0-9_-]/', '_', strtolower((string) ($row['employee_name'] ?? 'employee'))) . '_' . date('Ym', strtotime((string) ($row['salary_month'] ?? date('Y-m-01')))) . '.pdf"');
    echo $pdf;
    exit();
}

function payrollRenderBatchPdf(array $rows): void
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

    $lines = [
        'JASSNET Salary Payment Report',
        'Generated: ' . date('d M Y H:i'),
        'Employee | Month | Net Salary | Method | Destination | Paid Date',
        str_repeat('-', 110),
    ];

    foreach ($rows as $row) {
        $lines[] = implode(' | ', [
            mb_substr((string) ($row['employee_name'] ?? '-'), 0, 18),
            mb_substr((string) ($row['salary_month_label'] ?? '-'), 0, 12),
            number_format((float) ($row['net_salary'] ?? 0), 2),
            mb_substr((string) ($row['payout_method_label'] ?? '-'), 0, 12),
            mb_substr((string) ($row['payout_destination'] ?? '-'), 0, 18),
            mb_substr((string) ($row['paid_date_label'] ?? '-'), 0, 12),
        ]);
    }

    $stream = '';
    $y = 806;
    foreach ($lines as $index => $line) {
        $fontSize = $index === 0 ? 14 : 9;
        $stream .= $drawText('F1', $fontSize, 36, $y, $line);
        $y -= $index < 2 ? 18 : 13;
        if ($y < 48) {
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
    header('Content-Disposition: attachment; filename="salary_batch_' . date('Ymd_His') . '.pdf"');
    echo $pdf;
    exit();
}

function payrollSendSalaryPayout(array $salaryRow, array $recipient): array
{
    $payoutChannel = snippeNormalizePayoutChannel((string) ($salaryRow['payout_channel'] ?? ($recipient['preferred_payout_channel'] ?? 'mobile')));
    $recipientName = trim((string) ($recipient['full_name'] ?? 'Employee'));
    $recipientPhone = snippeNormalizePhone((string) (($recipient['payout_phone'] ?? '') !== '' ? $recipient['payout_phone'] : ($recipient['phone'] ?? '')));
    $bankCode = snippeNormalizeBankCode((string) ($recipient['bank_name'] ?? ''));
    $bankAccountNumber = trim((string) ($recipient['bank_account_number'] ?? ''));
    $amountValue = max(0, (int) round((float) ($salaryRow['net_salary'] ?? 0)));

    if ($amountValue <= 0) {
        throw new RuntimeException('Salary payout amount must be greater than zero.');
    }

    $minimumPayoutAmount = snippeGetMinimumPayoutAmount($payoutChannel);
    if ($amountValue < $minimumPayoutAmount) {
        throw new RuntimeException('Snippe payout amount for ' . ($payoutChannel === 'bank' ? 'bank transfer' : 'mobile money') . ' must be at least ' . $minimumPayoutAmount . '.');
    }

    $payload = [
        'amount' => $amountValue,
        'recipient_name' => $recipientName,
        'narration' => 'Salary payout for request #' . (int) ($salaryRow['id'] ?? 0),
        'metadata' => [
            'salary_request_id' => (string) ((int) ($salaryRow['id'] ?? 0)),
            'employee_id' => (string) ($recipient['employee_id'] ?? ''),
            'salary_month' => (string) ($salaryRow['salary_month'] ?? ''),
        ],
    ];

    if ($payoutChannel === 'bank') {
        if ($bankCode === '' || $bankAccountNumber === '') {
            throw new RuntimeException('User bank details are incomplete for salary payout.');
        }
        $payload['channel'] = 'bank';
        $payload['recipient_bank'] = $bankCode;
        $payload['recipient_account'] = $bankAccountNumber;
    } else {
        if ($recipientPhone === '') {
            throw new RuntimeException('User mobile payout phone is invalid for salary payout.');
        }
        $payload['channel'] = 'mobile';
        $payload['recipient_phone'] = $recipientPhone;
    }

    $webhookUrl = snippeGetWebhookUrl();
    if ($webhookUrl !== '') {
        $payload['webhook_url'] = $webhookUrl;
    }

    $idempotencyKey = hash('sha256', 'salary-payout-' . ((int) ($salaryRow['id'] ?? 0)) . '-' . $amountValue . '-' . $payoutChannel . '-' . ($payoutChannel === 'bank' ? $bankCode . '-' . $bankAccountNumber : $recipientPhone));
    $response = snippePayoutRequest('POST', '/v1/payouts/send', $payload, $idempotencyKey);
    $data = $response['data'] ?? [];
    $reference = (string) ($data['reference'] ?? '');

    if ($reference === '') {
        throw new RuntimeException('Snippe salary payout response did not include a reference.');
    }

    return [
        'reference' => $reference,
        'status' => strtolower((string) ($data['status'] ?? 'pending')),
        'raw' => $response,
    ];
}