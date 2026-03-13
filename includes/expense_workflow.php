<?php

require_once __DIR__ . '/snippe_payouts.php';

function expenseEnsureWorkflowSchema(mysqli $conn): void
{
    snippeEnsurePayoutTables($conn);

    $requestColumns = [
        'manager_comment' => "ALTER TABLE expense_requests ADD COLUMN manager_comment TEXT NULL AFTER manager_approved",
        'director_comment' => "ALTER TABLE expense_requests ADD COLUMN director_comment TEXT NULL AFTER director_approved",
        'accountant_comment' => "ALTER TABLE expense_requests ADD COLUMN accountant_comment TEXT NULL AFTER accountant_processed",
    ];

    foreach ($requestColumns as $column => $sql) {
        $result = $conn->query("SHOW COLUMNS FROM expense_requests LIKE '{$column}'");
        if ($result && $result->num_rows === 0) {
            $conn->query($sql);
        }
    }

    $paymentColumns = [
        'payout_reference' => "ALTER TABLE expense_payments ADD COLUMN payout_reference VARCHAR(120) NULL AFTER accountant_id",
        'payment_notes' => "ALTER TABLE expense_payments ADD COLUMN payment_notes TEXT NULL AFTER payout_reference",
    ];

    foreach ($paymentColumns as $column => $sql) {
        $result = $conn->query("SHOW COLUMNS FROM expense_payments LIKE '{$column}'");
        if ($result && $result->num_rows === 0) {
            $conn->query($sql);
        }
    }
}

function expenseGetPayoutSummary(mysqli $conn, int $requestId): array
{
    expenseEnsureWorkflowSchema($conn);

    $summary = [
        'amount_requested' => 0.0,
        'total_paid' => 0.0,
        'total_in_flight' => 0.0,
        'remaining_balance' => 0.0,
        'has_active_payout' => false,
        'latest_payout' => null,
        'latest_payout_status' => '',
        'latest_failure_reason' => '',
        'can_wait_for_receipt' => false,
        'next_status' => 'Pending Accountant Processing',
    ];

    $requestStmt = $conn->prepare('SELECT amount_requested FROM expense_requests WHERE id = ? LIMIT 1');
    if ($requestStmt) {
        $requestStmt->bind_param('i', $requestId);
        $requestStmt->execute();
        $requestRow = $requestStmt->get_result()->fetch_assoc() ?: [];
        $summary['amount_requested'] = (float) ($requestRow['amount_requested'] ?? 0);
    }

    $paymentStmt = $conn->prepare('SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM expense_payments WHERE expense_request_id = ?');
    if ($paymentStmt) {
        $paymentStmt->bind_param('i', $requestId);
        $paymentStmt->execute();
        $paymentRow = $paymentStmt->get_result()->fetch_assoc() ?: [];
        $summary['total_paid'] = (float) ($paymentRow['total_paid'] ?? 0);
    }

    $activeStmt = $conn->prepare("SELECT COALESCE(SUM(amount_value), 0) AS total_in_flight FROM snippe_payouts WHERE expense_request_id = ? AND status IN ('pending', 'processing', 'queued')");
    if ($activeStmt) {
        $activeStmt->bind_param('i', $requestId);
        $activeStmt->execute();
        $activeRow = $activeStmt->get_result()->fetch_assoc() ?: [];
        $summary['total_in_flight'] = (float) ($activeRow['total_in_flight'] ?? 0);
        $summary['has_active_payout'] = $summary['total_in_flight'] > 0;
    }

    $latestStmt = $conn->prepare('SELECT * FROM snippe_payouts WHERE expense_request_id = ? ORDER BY id DESC LIMIT 1');
    if ($latestStmt) {
        $latestStmt->bind_param('i', $requestId);
        $latestStmt->execute();
        $summary['latest_payout'] = $latestStmt->get_result()->fetch_assoc() ?: null;
    }

    $summary['latest_payout_status'] = strtolower((string) (($summary['latest_payout']['status'] ?? '')));
    $summary['latest_failure_reason'] = trim((string) (($summary['latest_payout']['failure_reason'] ?? '')));
    $summary['remaining_balance'] = max(0, $summary['amount_requested'] - $summary['total_paid']);
    $summary['can_wait_for_receipt'] = $summary['remaining_balance'] <= 0.00001 && !$summary['has_active_payout'];
    $summary['next_status'] = $summary['can_wait_for_receipt'] ? 'Waiting for Receipt' : 'Pending Accountant Processing';

    return $summary;
}

function expenseRecordCompletedPayoutPayments(mysqli $conn, int $requestId): void
{
    expenseEnsureWorkflowSchema($conn);

    $payoutStmt = $conn->prepare("SELECT * FROM snippe_payouts WHERE expense_request_id = ? AND status IN ('completed', 'success') ORDER BY id ASC");
    if (!$payoutStmt) {
        return;
    }

    $payoutStmt->bind_param('i', $requestId);
    $payoutStmt->execute();
    $payoutResult = $payoutStmt->get_result();

    while ($payout = $payoutResult->fetch_assoc()) {
        $reference = trim((string) ($payout['reference'] ?? ''));
        if ($reference === '') {
            continue;
        }

        $existingStmt = $conn->prepare('SELECT id FROM expense_payments WHERE payout_reference = ? LIMIT 1');
        if ($existingStmt) {
            $existingStmt->bind_param('s', $reference);
            $existingStmt->execute();
            $existingRow = $existingStmt->get_result()->fetch_assoc();
            if ($existingRow) {
                continue;
            }
        }

        $paymentMethod = stripos((string) ($payout['payout_channel'] ?? ''), 'bank') !== false ? 'Bank Transfer' : 'Mobile Money';
        $paymentDateSource = (string) ($payout['completed_at'] ?? $payout['updated_at'] ?? $payout['created_at'] ?? date('Y-m-d H:i:s'));
        $paymentDate = date('Y-m-d', strtotime($paymentDateSource));
        $amountPaid = (float) ($payout['amount_value'] ?? 0);
        $accountantId = (int) ($payout['created_by'] ?? 0);
        $paymentNotes = trim((string) ($payout['narration'] ?? ''));

        $insertStmt = $conn->prepare('INSERT INTO expense_payments (expense_request_id, amount_paid, payment_method, payment_date, accountant_id, payout_reference, payment_notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if ($insertStmt) {
            $insertStmt->bind_param('idssiss', $requestId, $amountPaid, $paymentMethod, $paymentDate, $accountantId, $reference, $paymentNotes);
            $insertStmt->execute();
        }
    }
}

function expenseSyncPayoutStatus(mysqli $conn, int $requestId): array
{
    expenseEnsureWorkflowSchema($conn);
    expenseRecordCompletedPayoutPayments($conn, $requestId);

    $summary = expenseGetPayoutSummary($conn, $requestId);

    $statusStmt = $conn->prepare('SELECT status FROM expense_requests WHERE id = ? LIMIT 1');
    if (!$statusStmt) {
        return $summary;
    }

    $statusStmt->bind_param('i', $requestId);
    $statusStmt->execute();
    $statusRow = $statusStmt->get_result()->fetch_assoc() ?: null;
    $currentStatus = (string) ($statusRow['status'] ?? '');
    if (in_array($currentStatus, ['Completed', 'Rejected'], true)) {
        return $summary;
    }

    $nextStatus = (string) $summary['next_status'];
    $accountantProcessed = $summary['can_wait_for_receipt'] ? 1 : 0;
    $stmt = $conn->prepare('UPDATE expense_requests SET status = ?, accountant_processed = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('sii', $nextStatus, $accountantProcessed, $requestId);
        $stmt->execute();
    }

    return expenseGetPayoutSummary($conn, $requestId);
}
