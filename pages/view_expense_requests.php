<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payouts.php';
require_once '../includes/expense_workflow.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Sales', 'Technician', 'Manager', 'Director', 'Accountant', 'Super Admin']);
snippeEnsureUserPayoutFields($conn);
snippeEnsurePayoutTables($conn);
expenseEnsureWorkflowSchema($conn);

$snippeMinimumBankPayoutAmount = snippeGetMinimumPayoutAmount('bank');
$snippeMinimumMobilePayoutAmount = snippeGetMinimumPayoutAmount('mobile');

function payoutPreferenceLabel($channel)
{
    return $channel === 'bank' ? 'Bank Transfer' : 'Mobile Money';
}

function canEditExpenseRequest(array $row): bool
{
    $isOwner = (int) ($row['requested_by'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0);
    $editableStatuses = ['Pending Manager Approval', 'Rejected'];
    return hasPermission(['Super Admin']) || ($isOwner && in_array((string) ($row['status'] ?? ''), $editableStatuses, true));
}

$message = '';
$error = '';

// Check for success message
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && hasPermission(['Accountant', 'Super Admin'])) {
    snippeRefreshPendingPayouts($conn, 5);
    $syncExpenseRequests = $conn->query("SELECT DISTINCT expense_request_id FROM snippe_payouts WHERE expense_request_id IS NOT NULL ORDER BY id DESC LIMIT 10");
    if ($syncExpenseRequests) {
        while ($syncRow = $syncExpenseRequests->fetch_assoc()) {
            expenseSyncPayoutStatus($conn, (int) $syncRow['expense_request_id']);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $normalizeExpenseRequests = $conn->query("SELECT id FROM expense_requests WHERE status NOT IN ('Completed', 'Rejected') ORDER BY id DESC");
    if ($normalizeExpenseRequests) {
        while ($normalizeRow = $normalizeExpenseRequests->fetch_assoc()) {
            expenseSyncPayoutStatus($conn, (int) $normalizeRow['id']);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve_manager'])) {
        $request_id = intval($_POST['request_id']);
        $manager_comment = trim(sanitize($_POST['manager_comment'] ?? ''));
        if ($manager_comment === '') {
            $error = 'Manager comment is required before approval.';
        } else {
            $stmt = $conn->prepare("UPDATE expense_requests SET status = 'Pending Director Approval', manager_approved = 1, manager_comment = ? WHERE id = ? AND status = 'Pending Manager Approval'");
            $stmt->bind_param("si", $manager_comment, $request_id);
            $stmt->execute();
            appLogActivity($conn, 'APPROVE_EXPENSE_MANAGER', 'Manager approved expense request #' . $request_id, 'expense_requests', $request_id);
            $message = 'Expense request approved by manager';
        }
    } elseif (isset($_POST['reject_manager'])) {
        $request_id = intval($_POST['request_id']);
        $stmt = $conn->prepare("UPDATE expense_requests SET status = 'Rejected' WHERE id = ? AND status = 'Pending Manager Approval'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        appLogActivity($conn, 'REJECT_EXPENSE_MANAGER', 'Manager rejected expense request #' . $request_id, 'expense_requests', $request_id);
        $message = 'Expense request rejected by manager';
    } elseif (isset($_POST['approve_director'])) {
        $request_id = intval($_POST['request_id']);
        $director_comment = trim(sanitize($_POST['director_comment'] ?? ''));
        if ($director_comment === '') {
            $error = 'Director comment is required before approval.';
        } else {
            $stmt = $conn->prepare("UPDATE expense_requests SET status = 'Pending Accountant Processing', director_approved = 1, director_comment = ? WHERE id = ? AND status = 'Pending Director Approval'");
            $stmt->bind_param("si", $director_comment, $request_id);
            $stmt->execute();
            appLogActivity($conn, 'APPROVE_EXPENSE_DIRECTOR', 'Director approved expense request #' . $request_id, 'expense_requests', $request_id);
            $message = 'Expense request approved by director';
        }
    } elseif (isset($_POST['reject_director'])) {
        $request_id = intval($_POST['request_id']);
        $stmt = $conn->prepare("UPDATE expense_requests SET status = 'Rejected' WHERE id = ? AND status = 'Pending Director Approval'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        appLogActivity($conn, 'REJECT_EXPENSE_DIRECTOR', 'Director rejected expense request #' . $request_id, 'expense_requests', $request_id);
        $message = 'Expense request rejected by director';
    } elseif (isset($_POST['process_accountant'])) {
        $request_id = intval($_POST['request_id']);
        $amount_paid = floatval($_POST['amount_paid']);
        $payment_method = sanitize($_POST['payment_method']);
        $payment_date = sanitize($_POST['payment_date']);
        $accountant_comment = trim(sanitize($_POST['accountant_comment'] ?? ''));

        $requestStmt = $conn->prepare("SELECT er.*, u.id AS requested_user_id, u.full_name AS requested_by_name, u.phone AS requested_by_phone, u.payout_phone, u.bank_name, u.bank_account_number, u.preferred_payout_channel, u.employee_id FROM expense_requests er JOIN users u ON er.requested_by = u.id WHERE er.id = ? AND er.status IN ('Pending Accountant Processing', 'Waiting for Receipt') LIMIT 1");
        if ($requestStmt) {
            $requestStmt->bind_param("i", $request_id);
            $requestStmt->execute();
            $requestRow = $requestStmt->get_result()->fetch_assoc();

            if (!$requestRow) {
                $error = 'Expense request not found or already processed';
            } elseif ($accountant_comment === '') {
                $error = 'Accountant comment is required before processing payment.';
            } elseif ($amount_paid <= 0) {
                $error = 'Amount paid must be greater than zero';
            } else {
                $summaryBefore = expenseGetPayoutSummary($conn, $request_id);
                $remainingBefore = (float) ($summaryBefore['remaining_balance'] ?? 0);
                $payoutChannel = $payment_method === 'Bank Transfer' ? 'bank' : 'mobile';
                $minimumPayoutAmount = snippeGetMinimumPayoutAmount($payoutChannel);

                if ($amount_paid > ($remainingBefore + 0.00001)) {
                    $error = 'Amount paid cannot be greater than the remaining balance.';
                } elseif (($payment_method === 'Mobile Money' || $payment_method === 'Bank Transfer') && !empty($summaryBefore['has_active_payout'])) {
                    $error = 'This expense already has a payout waiting for Snippe confirmation. Wait for that result before sending another payout.';
                } elseif ($payment_method === 'Bank Transfer' && snippeNormalizeBankCode((string) ($requestRow['bank_name'] ?? '')) === '') {
                    $error = 'Selected user does not have a supported bank saved for Snippe payout';
                } elseif ($payment_method === 'Bank Transfer' && trim((string) ($requestRow['bank_account_number'] ?? '')) === '') {
                    $error = 'Selected user does not have bank account details saved';
                } elseif ($payment_method === 'Mobile Money' && snippeNormalizePhone((string) (($requestRow['payout_phone'] ?? '') !== '' ? $requestRow['payout_phone'] : ($requestRow['requested_by_phone'] ?? ''))) === '') {
                    $error = 'Selected user does not have a valid payout phone number';
                } elseif (($payment_method === 'Mobile Money' || $payment_method === 'Bank Transfer') && $remainingBefore < $minimumPayoutAmount && $amount_paid > 0) {
                    $error = 'The remaining balance is below Snippe minimum payout for ' . ($payoutChannel === 'bank' ? 'bank transfer' : 'mobile money') . ' of Tshs. ' . number_format($minimumPayoutAmount, 2) . '. Please settle this balance manually or combine it before retrying.';
                } elseif (($payment_method === 'Mobile Money' || $payment_method === 'Bank Transfer') && $amount_paid < $minimumPayoutAmount) {
                    $error = 'Snippe minimum payout for ' . ($payoutChannel === 'bank' ? 'bank transfer' : 'mobile money') . ' is Tshs. ' . number_format($minimumPayoutAmount, 2) . '. Enter at least that amount.';
                }
            }

            if (!$error) {
                $conn->begin_transaction();
                try {
                    $commentStmt = $conn->prepare("UPDATE expense_requests SET accountant_comment = ? WHERE id = ?");
                    if ($commentStmt) {
                        $commentStmt->bind_param('si', $accountant_comment, $request_id);
                        $commentStmt->execute();
                    }

                    $successText = 'Payment processed by accountant';
                    $summaryAfter = null;
                    if ($payment_method === 'Mobile Money' || $payment_method === 'Bank Transfer') {
                        $recipient = [
                            'id' => (int) $requestRow['requested_user_id'],
                            'full_name' => $requestRow['requested_by_name'],
                            'phone' => $requestRow['requested_by_phone'],
                            'payout_phone' => $requestRow['payout_phone'],
                            'bank_name' => $requestRow['bank_name'],
                            'bank_account_number' => $requestRow['bank_account_number'],
                            'employee_id' => $requestRow['employee_id'],
                            'preferred_payout_channel' => $requestRow['preferred_payout_channel'],
                        ];
                        $payoutChannel = $payment_method === 'Bank Transfer' ? 'bank' : 'mobile';
                        $payoutResult = snippeCreateExpensePayout($conn, $requestRow, $recipient, $amount_paid, (int) $_SESSION['user_id'], $payoutChannel);
                        $summaryAfter = expenseSyncPayoutStatus($conn, $request_id);
                        $payout = $payoutResult['payout'];
                        $payoutStatus = strtolower((string) ($payout['status'] ?? 'pending'));
                        $failureReason = trim((string) ($payout['failure_reason'] ?? ''));
                        if (in_array($payoutStatus, ['failed', 'cancelled', 'canceled'], true)) {
                            $successText = 'Snippe payout failed. Expense remains with Accountant.' . ($failureReason !== '' ? ' Reason: ' . $failureReason : '');
                        } elseif (!empty($summaryAfter['can_wait_for_receipt'])) {
                            $successText = 'Full payout completed. Expense is now waiting for receipt.';
                        } elseif (!empty($summaryAfter['has_active_payout'])) {
                            $successText = 'Payout submitted to Snippe and is still pending. Expense remains with Accountant until payout completion is confirmed.';
                        } else {
                            $successText = 'Partial payout recorded. Remaining balance: Tshs. ' . number_format((float) ($summaryAfter['remaining_balance'] ?? 0), 2) . '. Expense remains with Accountant.';
                        }
                    } elseif ($payment_method === 'Cash') {
                        $stmt = $conn->prepare("INSERT INTO expense_payments (expense_request_id, amount_paid, payment_method, payment_date, accountant_id, payout_reference, payment_notes) VALUES (?, ?, ?, ?, ?, NULL, ?)");
                        if (!$stmt) {
                            throw new RuntimeException('Could not prepare expense payment query');
                        }
                        $stmt->bind_param("idssis", $request_id, $amount_paid, $payment_method, $payment_date, $_SESSION['user_id'], $accountant_comment);
                        if (!$stmt->execute()) {
                            throw new RuntimeException('Failed to store expense payment');
                        }

                        $summaryAfter = expenseSyncPayoutStatus($conn, $request_id);
                        if (!empty($summaryAfter['can_wait_for_receipt'])) {
                            $successText = 'Cash payment recorded. Expense is now waiting for receipt.';
                        } else {
                            $successText = 'Partial cash payment recorded. Remaining balance: Tshs. ' . number_format((float) ($summaryAfter['remaining_balance'] ?? 0), 2) . '. Expense remains with Accountant.';
                        }
                    }

                    $conn->commit();
                    appLogActivity($conn, 'PROCESS_EXPENSE_PAYMENT', $successText, 'expense_requests', $request_id);
                    $_SESSION['success_message'] = $successText;
                    header('Location: view_expense_requests.php');
                    exit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
        } else {
            $error = 'Could not prepare expense request query';
        }
    } elseif (isset($_POST['upload_receipt'])) {
        $request_id = intval($_POST['request_id']);
        $vendor_name = sanitize($_POST['vendor_name']);
        $receipt_number = sanitize($_POST['receipt_number']);
        $actual_amount = floatval($_POST['actual_amount']);
        $notes = sanitize($_POST['notes']);
        $requestCheckStmt = $conn->prepare("SELECT requested_by, status FROM expense_requests WHERE id = ? LIMIT 1");
        $requestRow = null;
        if ($requestCheckStmt) {
            $requestCheckStmt->bind_param('i', $request_id);
            $requestCheckStmt->execute();
            $requestRow = $requestCheckStmt->get_result()->fetch_assoc() ?: null;
        }

        if (!$requestRow) {
            $error = 'Expense request not found';
        } elseif ((string) ($requestRow['status'] ?? '') !== 'Waiting for Receipt') {
            $error = 'Receipt can only be uploaded when the request is waiting for receipt';
        } elseif ((int) ($requestRow['requested_by'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0) && !hasPermission(['Super Admin'])) {
            $error = 'You are not allowed to upload this receipt';
        }
        
        $receipt_file = '';
        if (!$error && isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] == 0) {
            $upload_result = uploadFile($_FILES['receipt_file']);
            if (isset($upload_result['success'])) {
                $receipt_file = $upload_result['success'];
            } else {
                $error = $upload_result['error'];
            }
        }
        
        if (!$error) {
            $stmt = $conn->prepare("INSERT INTO receipts (expense_request_id, receipt_file, vendor_name, receipt_number, actual_amount, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssds", $request_id, $receipt_file, $vendor_name, $receipt_number, $actual_amount, $notes);
            
            if ($stmt->execute()) {
                $stmt = $conn->prepare("UPDATE expense_requests SET status = 'Completed', receipt_uploaded = 1 WHERE id = ?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                appLogActivity($conn, 'UPLOAD_RECEIPT', 'Uploaded expense receipt for request #' . $request_id, 'receipts', $request_id);
                $message = 'Receipt uploaded successfully';
            } else {
                $error = 'Error uploading receipt';
            }
        }
    } elseif (isset($_POST['update_expense_request'])) {
        $request_id = intval($_POST['request_id'] ?? 0);
        $department = sanitize($_POST['department'] ?? '');
        $category = sanitize($_POST['category'] ?? 'Other');
        $description = sanitize($_POST['description'] ?? '');
        $amount_requested = floatval($_POST['amount_requested'] ?? 0);
        $reason = sanitize($_POST['reason'] ?? '');
        $project_ref = sanitize($_POST['project_ref'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');

        $checkStmt = $conn->prepare('SELECT requested_by, status FROM expense_requests WHERE id = ? LIMIT 1');
        if ($checkStmt) {
            $checkStmt->bind_param('i', $request_id);
            $checkStmt->execute();
            $existingRequest = $checkStmt->get_result()->fetch_assoc();

            if (!$existingRequest) {
                $error = 'Expense request not found';
            } elseif (!canEditExpenseRequest(['requested_by' => $existingRequest['requested_by'], 'status' => $existingRequest['status']])) {
                $error = 'You are not allowed to edit this expense request';
            } elseif ($department === '' || $description === '' || $reason === '' || $amount_requested <= 0) {
                $error = 'Please complete all required expense fields before saving changes';
            } else {
                $stmt = $conn->prepare('UPDATE expense_requests SET department = ?, category = ?, description = ?, amount_requested = ?, reason = ?, project_ref = ?, notes = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('sssdsssi', $department, $category, $description, $amount_requested, $reason, $project_ref, $notes, $request_id);
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = 'Expense request updated successfully';
                        header('Location: view_expense_requests.php');
                        exit();
                    }
                }
                $error = 'Failed to update expense request';
            }
        } else {
            $error = 'Could not verify expense request for editing';
        }
    } elseif (isset($_POST['delete_expense_request'])) {
        if (!hasPermission(['Super Admin'])) {
            $error = 'Only Super Admin can delete expense requests';
        } else {
            $request_id = intval($_POST['request_id'] ?? 0);
            $conn->begin_transaction();
            try {
                $deleteReceipt = $conn->prepare('DELETE FROM receipts WHERE expense_request_id = ?');
                if ($deleteReceipt) {
                    $deleteReceipt->bind_param('i', $request_id);
                    $deleteReceipt->execute();
                }

                $deletePayments = $conn->prepare('DELETE FROM expense_payments WHERE expense_request_id = ?');
                if ($deletePayments) {
                    $deletePayments->bind_param('i', $request_id);
                    $deletePayments->execute();
                }

                $deletePayouts = $conn->prepare('DELETE FROM snippe_payouts WHERE expense_request_id = ?');
                if ($deletePayouts) {
                    $deletePayouts->bind_param('i', $request_id);
                    $deletePayouts->execute();
                }

                $deleteRequest = $conn->prepare('DELETE FROM expense_requests WHERE id = ?');
                if (!$deleteRequest) {
                    throw new RuntimeException('Could not prepare expense delete query');
                }
                $deleteRequest->bind_param('i', $request_id);
                $deleteRequest->execute();

                if ($deleteRequest->affected_rows <= 0) {
                    throw new RuntimeException('Expense request not found or already removed');
                }

                $conn->commit();
                appLogActivity($conn, 'DELETE_EXPENSE', 'Deleted expense request #' . $request_id, 'expense_requests', $request_id);
                $_SESSION['success_message'] = 'Expense request deleted successfully';
                header('Location: view_expense_requests.php');
                exit();
            } catch (Throwable $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Get expense requests based on user role
$where_clause = '';
if ($_SESSION['role'] == 'Sales' || $_SESSION['role'] == 'Technician') {
    $where_clause = "WHERE er.requested_by = {$_SESSION['user_id']}";
} elseif ($_SESSION['role'] == 'Manager') {
    $where_clause = "WHERE er.status = 'Pending Manager Approval' OR er.status IN ('Pending Director Approval', 'Pending Accountant Processing', 'Waiting for Receipt', 'Completed', 'Rejected')";
} elseif ($_SESSION['role'] == 'Director') {
    $where_clause = "WHERE er.status IN ('Pending Director Approval', 'Pending Accountant Processing', 'Waiting for Receipt', 'Completed', 'Rejected')";
} elseif ($_SESSION['role'] == 'Accountant') {
    $where_clause = "WHERE er.status IN ('Pending Accountant Processing', 'Waiting for Receipt', 'Completed', 'Rejected')";
}

$expense_requests = $conn->query("SELECT er.*, u.full_name as requested_by_name, u.phone as requested_by_phone, u.payout_phone, u.bank_name, u.bank_account_number, u.preferred_payout_channel, (SELECT COALESCE(SUM(ep.amount_paid), 0) FROM expense_payments ep WHERE ep.expense_request_id = er.id) AS total_paid, (er.amount_requested - (SELECT COALESCE(SUM(ep.amount_paid), 0) FROM expense_payments ep WHERE ep.expense_request_id = er.id)) AS remaining_balance, (SELECT COUNT(*) FROM snippe_payouts sp WHERE sp.expense_request_id = er.id AND sp.status IN ('pending', 'processing', 'queued')) AS active_payouts, (SELECT sp.status FROM snippe_payouts sp WHERE sp.expense_request_id = er.id ORDER BY sp.id DESC LIMIT 1) AS latest_payout_status, (SELECT sp.failure_reason FROM snippe_payouts sp WHERE sp.expense_request_id = er.id ORDER BY sp.id DESC LIMIT 1) AS latest_payout_failure_reason FROM expense_requests er JOIN users u ON er.requested_by = u.id $where_clause ORDER BY request_date DESC");

$rows = [];
$summary = [
    'total' => 0,
    'pending' => 0,
    'completed' => 0,
    'rejected' => 0,
];

if ($expense_requests) {
    while ($r = $expense_requests->fetch_assoc()) {
        $rows[] = $r;
        $summary['total']++;

        if ($r['status'] === 'Completed') {
            $summary['completed']++;
        } elseif ($r['status'] === 'Rejected') {
            $summary['rejected']++;
        } else {
            $summary['pending']++;
        }
    }
}

function statusBadgeClass($status)
{
    if ($status === 'Completed') {
        return 'success';
    }
    if ($status === 'Rejected') {
        return 'danger';
    }
    if ($status === 'Pending Manager Approval' || $status === 'Pending Director Approval') {
        return 'warning';
    }
    if ($status === 'Pending Accountant Processing') {
        return 'primary';
    }
    if ($status === 'Waiting for Receipt') {
        return 'secondary';
    }
    return 'info';
}

include '../includes/header.php';
?>

<style>
    .expense-board {
        background: linear-gradient(120deg, #114b5f 0%, #1a759f 100%);
        color: #fff;
        border-radius: 14px;
        padding: 1.2rem 1.5rem;
        box-shadow: 0 10px 24px rgba(17, 75, 95, 0.25);
    }

    .mini-stat {
        border-radius: 12px;
        border: 1px solid #d9e4ec;
        background: #fff;
        padding: 0.95rem 1rem;
        height: 100%;
    }

    .mini-stat .label {
        font-size: 0.82rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .mini-stat .value {
        font-size: 1.45rem;
        font-weight: 700;
        color: #16324f;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="expense-board d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2 class="mb-1"><i class="fas fa-receipt"></i> Expense Requests</h2>
                    <div class="small">Track approvals, payments, and receipts in one place.</div>
                </div>
                <?php if (hasPermission(['Sales', 'Technician'])): ?>
                <a href="add_expense_request.php" class="btn btn-light">
                    <i class="fas fa-plus"></i> New Request
                </a>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="mini-stat">
                <div class="label">Total Requests</div>
                <div class="value"><?php echo $summary['total']; ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="mini-stat">
                <div class="label">Pending</div>
                <div class="value text-warning"><?php echo $summary['pending']; ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="mini-stat">
                <div class="label">Completed</div>
                <div class="value text-success"><?php echo $summary['completed']; ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="mini-stat">
                <div class="label">Rejected</div>
                <div class="value text-danger"><?php echo $summary['rejected']; ?></div>
            </div>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Expense Requests List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Requested By</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($rows) > 0): ?>
                                    <?php foreach ($rows as $row): ?>
                                    <tr id="expenseRow_<?php echo (int) $row['id']; ?>"
                                        data-department="<?php echo htmlspecialchars((string) ($row['department'] ?? ''), ENT_QUOTES); ?>"
                                        data-category="<?php echo htmlspecialchars((string) ($row['category'] ?? 'Other'), ENT_QUOTES); ?>"
                                        data-description="<?php echo htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES); ?>"
                                        data-reason="<?php echo htmlspecialchars((string) ($row['reason'] ?? ''), ENT_QUOTES); ?>"
                                        data-project-ref="<?php echo htmlspecialchars((string) ($row['project_ref'] ?? ''), ENT_QUOTES); ?>"
                                        data-notes="<?php echo htmlspecialchars((string) ($row['notes'] ?? ''), ENT_QUOTES); ?>"
                                        data-amount-requested="<?php echo htmlspecialchars((string) ((float) ($row['amount_requested'] ?? 0)), ENT_QUOTES); ?>"
                                        data-total-paid="<?php echo htmlspecialchars((string) ((float) ($row['total_paid'] ?? 0)), ENT_QUOTES); ?>"
                                        data-remaining-balance="<?php echo htmlspecialchars((string) max(0, (float) ($row['remaining_balance'] ?? 0)), ENT_QUOTES); ?>"
                                        data-preferred-channel="<?php echo htmlspecialchars((string) ($row['preferred_payout_channel'] ?? 'mobile'), ENT_QUOTES); ?>"
                                        data-latest-payout-status="<?php echo htmlspecialchars((string) ($row['latest_payout_status'] ?? ''), ENT_QUOTES); ?>"
                                        data-latest-payout-failure="<?php echo htmlspecialchars((string) ($row['latest_payout_failure_reason'] ?? ''), ENT_QUOTES); ?>">
                                        <td><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['requested_by_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                                        <td>Tshs. <?php echo number_format($row['amount_requested'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo statusBadgeClass($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                            <div class="small text-muted mt-1">Paid: Tshs. <?php echo number_format((float) ($row['total_paid'] ?? 0), 2); ?></div>
                                            <div class="small <?php echo ((float) ($row['remaining_balance'] ?? 0)) > 0 ? 'text-warning' : 'text-success'; ?>">Remaining: Tshs. <?php echo number_format(max(0, (float) ($row['remaining_balance'] ?? 0)), 2); ?></div>
                                            <?php if (!empty($row['latest_payout_status']) && in_array(strtolower((string) $row['latest_payout_status']), ['failed', 'cancelled', 'canceled'], true)): ?>
                                                <div class="small text-danger">Payout failed<?php echo !empty($row['latest_payout_failure_reason']) ? ': ' . htmlspecialchars($row['latest_payout_failure_reason']) : ''; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewRequest(<?php echo $row['id']; ?>)">View</button>
                                            <?php if (canEditExpenseRequest($row)): ?>
                                                <button class="btn btn-sm btn-outline-secondary" onclick="editExpenseRequest(<?php echo $row['id']; ?>)">Edit</button>
                                            <?php endif; ?>
                                            <?php if (hasPermission(['Super Admin'])): ?>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteExpenseRequest(<?php echo $row['id']; ?>)">Delete</button>
                                            <?php endif; ?>
                                            <?php if (hasPermission(['Manager']) && $row['status'] == 'Pending Manager Approval'): ?>
                                                <button class="btn btn-sm btn-success" onclick="approveManager(<?php echo $row['id']; ?>)">Approve</button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectManager(<?php echo $row['id']; ?>)">Reject</button>
                                            <?php elseif (hasPermission(['Director']) && $row['status'] == 'Pending Director Approval'): ?>
                                                <button class="btn btn-sm btn-success" onclick="approveDirector(<?php echo $row['id']; ?>)">Approve</button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectDirector(<?php echo $row['id']; ?>)">Reject</button>
                                            <?php elseif (hasPermission(['Accountant']) && $row['status'] == 'Pending Accountant Processing'): ?>
                                                <button
                                                    class="btn btn-sm btn-primary"
                                                    data-request-id="<?php echo (int) $row['id']; ?>"
                                                    data-requested-by="<?php echo htmlspecialchars($row['requested_by_name'], ENT_QUOTES); ?>"
                                                    data-payout-phone="<?php echo htmlspecialchars(($row['payout_phone'] ?: ($row['requested_by_phone'] ?: 'N/A')), ENT_QUOTES); ?>"
                                                    data-bank-name="<?php echo htmlspecialchars(snippeGetBankDisplayName((string) ($row['bank_name'] ?? '')) ?: 'N/A', ENT_QUOTES); ?>"
                                                    data-bank-account="<?php echo htmlspecialchars($row['bank_account_number'] ?: 'N/A', ENT_QUOTES); ?>"
                                                    data-preferred-channel="<?php echo htmlspecialchars($row['preferred_payout_channel'] ?: 'mobile', ENT_QUOTES); ?>"
                                                    onclick="processAccountant(this)"
                                                >Process</button>
                                            <?php elseif ($row['status'] == 'Waiting for Receipt' && ($row['requested_by'] == $_SESSION['user_id'] || hasPermission(['Super Admin']))): ?>
                                                <button class="btn btn-sm btn-info" onclick="uploadReceipt(<?php echo $row['id']; ?>)">Upload Receipt</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="fas fa-folder-open text-muted" style="font-size: 2rem;"></i>
                                            <p class="text-muted mt-2 mb-0">No expense requests found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Modals for actions -->
<div class="modal fade" id="approveManagerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Expense Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this expense request?</p>
                <form method="POST" id="approveManagerForm">
                    <input type="hidden" name="request_id" id="approveManagerRequestId">
                    <div class="mb-3">
                        <label for="manager_comment" class="form-label">Manager Comment *</label>
                        <textarea class="form-control" id="manager_comment" name="manager_comment" rows="3" placeholder="Add your approval comment..." required></textarea>
                    </div>
                    <button type="submit" name="approve_manager" class="btn btn-success">Yes, Approve</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectManagerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Expense Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reject this expense request?</p>
                <form method="POST" id="rejectManagerForm">
                    <input type="hidden" name="request_id" id="rejectManagerRequestId">
                    <button type="submit" name="reject_manager" class="btn btn-danger">Yes, Reject</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="approveDirectorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Expense Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this expense request?</p>
                <form method="POST" id="approveDirectorForm">
                    <input type="hidden" name="request_id" id="approveDirectorRequestId">
                    <div class="mb-3">
                        <label for="director_comment" class="form-label">Director Comment *</label>
                        <textarea class="form-control" id="director_comment" name="director_comment" rows="3" placeholder="Add your approval comment..." required></textarea>
                    </div>
                    <button type="submit" name="approve_director" class="btn btn-success">Yes, Approve</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectDirectorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Expense Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reject this expense request?</p>
                <form method="POST" id="rejectDirectorForm">
                    <input type="hidden" name="request_id" id="rejectDirectorRequestId">
                    <button type="submit" name="reject_director" class="btn btn-danger">Yes, Reject</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editExpenseRequestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Expense Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editExpenseRequestForm">
                    <input type="hidden" name="request_id" id="editExpenseRequestId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_expense_department" class="form-label">Department *</label>
                            <input type="text" class="form-control" id="edit_expense_department" name="department" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_expense_category" class="form-label">Category *</label>
                            <select class="form-select" id="edit_expense_category" name="category" required>
                                <option value="Fuel">Fuel</option>
                                <option value="Equipment">Equipment</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Transport">Transport</option>
                                <option value="Salary">Salary</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_expense_amount_requested" class="form-label">Amount Requested *</label>
                            <input type="number" class="form-control" id="edit_expense_amount_requested" name="amount_requested" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_expense_project_ref" class="form-label">Project Reference</label>
                            <input type="text" class="form-control" id="edit_expense_project_ref" name="project_ref">
                        </div>
                        <div class="col-12">
                            <label for="edit_expense_description" class="form-label">Description *</label>
                            <textarea class="form-control" id="edit_expense_description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <label for="edit_expense_reason" class="form-label">Reason *</label>
                            <textarea class="form-control" id="edit_expense_reason" name="reason" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <label for="edit_expense_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="edit_expense_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" name="update_expense_request" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteExpenseRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Expense Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">This will permanently remove the expense request and related payout, payment, and receipt records.</p>
                <form method="POST" id="deleteExpenseRequestForm">
                    <input type="hidden" name="request_id" id="deleteExpenseRequestId">
                    <button type="submit" name="delete_expense_request" class="btn btn-danger">Delete Request</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="processAccountantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Process Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="processAccountantForm">
                    <input type="hidden" name="request_id" id="processAccountantRequestId">
                    <div class="alert alert-light border">
                        <div><strong>Recipient:</strong> <span id="payoutRecipientName">-</span></div>
                        <div><strong>Mobile:</strong> <span id="payoutRecipientPhone">-</span></div>
                        <div><strong>Bank:</strong> <span id="payoutRecipientBank">-</span></div>
                        <div><strong>Preferred Payout:</strong> <span id="payoutRecipientPreference">-</span></div>
                        <div id="expensePayoutSummary" class="small text-muted mt-2">Snippe automation is active for both Mobile Money and Bank Transfer, using the saved recipient details.</div>
                    </div>
                    <div class="mb-3">
                        <label for="amount_paid" class="form-label">Amount Paid (Tshs.)</label>
                        <input type="number" class="form-control" id="amount_paid" name="amount_paid" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Mobile Money">Mobile Money</option>
                        </select>
                        <div class="form-text">Minimum payout is Tshs. <?php echo number_format((float) $snippeMinimumMobilePayoutAmount, 2); ?> for Mobile Money and Tshs. <?php echo number_format((float) $snippeMinimumBankPayoutAmount, 2); ?> for Bank Transfer.</div>
                    </div>
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="accountant_comment" class="form-label">Accountant Comment *</label>
                        <textarea class="form-control" id="accountant_comment" name="accountant_comment" rows="3" placeholder="Add accountant processing comment..." required></textarea>
                    </div>
                    <button type="submit" name="process_accountant" class="btn btn-primary">Process Payment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="uploadReceiptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="uploadReceiptForm">
                    <input type="hidden" name="request_id" id="uploadReceiptRequestId">
                    <div class="mb-3">
                        <label for="vendor_name" class="form-label">Vendor Name</label>
                        <input type="text" class="form-control" id="vendor_name" name="vendor_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="receipt_number" class="form-label">Receipt Number</label>
                        <input type="text" class="form-control" id="receipt_number" name="receipt_number">
                    </div>
                    <div class="mb-3">
                        <label for="actual_amount" class="form-label">Actual Amount Paid (Tshs.)</label>
                        <input type="number" class="form-control" id="actual_amount" name="actual_amount" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="receipt_file" class="form-label">Receipt File (Image/PDF)</label>
                        <input type="file" class="form-control" id="receipt_file" name="receipt_file" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                    <div class="mb-3">
                        <label for="receipt_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="receipt_notes" name="notes" rows="2"></textarea>
                    </div>
                    <button type="submit" name="upload_receipt" class="btn btn-primary">Upload Receipt</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($success_message): ?>
<div class="modal fade" id="expenseSubmitSuccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Expense Request Submitted</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const expenseMinimumPayouts = {
    'Mobile Money': <?php echo json_encode((float) $snippeMinimumMobilePayoutAmount); ?>,
    'Bank Transfer': <?php echo json_encode((float) $snippeMinimumBankPayoutAmount); ?>
};

function formatExpenseCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Number(amount || 0));
}

function updateExpensePayoutSummary() {
    const requestId = document.getElementById('processAccountantRequestId').value;
    const row = requestId ? document.getElementById('expenseRow_' + requestId) : null;
    const method = document.getElementById('payment_method').value;
    const amountInput = document.getElementById('amount_paid');
    const summaryEl = document.getElementById('expensePayoutSummary');

    if (!row || !summaryEl) {
        return;
    }

    const requested = Number(row.dataset.amountRequested || 0);
    const paid = Number(row.dataset.totalPaid || 0);
    const remaining = Number(row.dataset.remainingBalance || 0);
    const latestStatus = row.dataset.latestPayoutStatus || '';
    const failureReason = row.dataset.latestPayoutFailure || '';
    const minimum = expenseMinimumPayouts[method] || 0;

    amountInput.max = remaining > 0 ? remaining : requested;
    if (!amountInput.value || Number(amountInput.value) > remaining) {
        amountInput.value = remaining > 0 ? remaining : requested;
    }

    let summary = 'Requested: Tshs. ' + formatExpenseCurrency(requested)
        + ' | Paid: Tshs. ' + formatExpenseCurrency(paid)
        + ' | Remaining: Tshs. ' + formatExpenseCurrency(remaining) + '.';

    if (method === 'Cash') {
        summary += ' Cash payments can be recorded for any remaining amount.';
    } else {
        summary += ' Minimum ' + method + ' payout is Tshs. ' + formatExpenseCurrency(minimum) + '.';
    }

    if (latestStatus) {
        summary += ' Latest payout status: ' + latestStatus.toUpperCase() + '.';
    }

    if (failureReason) {
        summary += ' Failure reason: ' + failureReason + '.';
    }

    summaryEl.textContent = summary;
}

function viewRequest(id) {
    window.location.href = 'expense_detail.php?id=' + id;
}

function editExpenseRequest(id) {
    const row = document.getElementById('expenseRow_' + id);
    if (!row) {
        return;
    }

    document.getElementById('editExpenseRequestId').value = id;
    document.getElementById('edit_expense_department').value = row.dataset.department || '';
    document.getElementById('edit_expense_category').value = row.dataset.category || 'Other';
    document.getElementById('edit_expense_amount_requested').value = row.dataset.amountRequested || '';
    document.getElementById('edit_expense_project_ref').value = row.dataset.projectRef || '';
    document.getElementById('edit_expense_description').value = row.dataset.description || '';
    document.getElementById('edit_expense_reason').value = row.dataset.reason || '';
    document.getElementById('edit_expense_notes').value = row.dataset.notes || '';

    new bootstrap.Modal(document.getElementById('editExpenseRequestModal')).show();
}

function deleteExpenseRequest(id) {
    document.getElementById('deleteExpenseRequestId').value = id;
    new bootstrap.Modal(document.getElementById('deleteExpenseRequestModal')).show();
}

function approveManager(id) {
    document.getElementById('approveManagerRequestId').value = id;
    document.getElementById('manager_comment').value = '';
    new bootstrap.Modal(document.getElementById('approveManagerModal')).show();
}

function rejectManager(id) {
    document.getElementById('rejectManagerRequestId').value = id;
    new bootstrap.Modal(document.getElementById('rejectManagerModal')).show();
}

function approveDirector(id) {
    document.getElementById('approveDirectorRequestId').value = id;
    document.getElementById('director_comment').value = '';
    new bootstrap.Modal(document.getElementById('approveDirectorModal')).show();
}

function rejectDirector(id) {
    document.getElementById('rejectDirectorRequestId').value = id;
    new bootstrap.Modal(document.getElementById('rejectDirectorModal')).show();
}

function processAccountant(button) {
    const requestId = button.dataset.requestId;
    const row = document.getElementById('expenseRow_' + requestId);
    document.getElementById('processAccountantRequestId').value = button.dataset.requestId;
    document.getElementById('payoutRecipientName').textContent = button.dataset.requestedBy || '-';
    document.getElementById('payoutRecipientPhone').textContent = button.dataset.payoutPhone || 'N/A';
    document.getElementById('payoutRecipientBank').textContent = ((button.dataset.bankName || 'N/A') + ' / ' + (button.dataset.bankAccount || 'N/A'));
    document.getElementById('payoutRecipientPreference').textContent = button.dataset.preferredChannel === 'bank' ? 'Bank Transfer' : 'Mobile Money';
    document.getElementById('payment_method').value = (button.dataset.preferredChannel === 'bank') ? 'Bank Transfer' : ((button.dataset.payoutPhone && button.dataset.payoutPhone !== 'N/A') ? 'Mobile Money' : 'Cash');
    document.getElementById('accountant_comment').value = '';
    if (row) {
        const remaining = Number(row.dataset.remainingBalance || row.dataset.amountRequested || 0);
        document.getElementById('amount_paid').value = remaining > 0 ? remaining : Number(row.dataset.amountRequested || 0);
    }
    updateExpensePayoutSummary();
    new bootstrap.Modal(document.getElementById('processAccountantModal')).show();
}

function uploadReceipt(id) {
    document.getElementById('uploadReceiptRequestId').value = id;
    new bootstrap.Modal(document.getElementById('uploadReceiptModal')).show();
}

<?php if ($success_message): ?>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethod = document.getElementById('payment_method');
    if (paymentMethod) {
        paymentMethod.addEventListener('change', updateExpensePayoutSummary);
    }

    const amountInput = document.getElementById('amount_paid');
    if (amountInput) {
        amountInput.addEventListener('input', updateExpensePayoutSummary);
    }

    var modalEl = document.getElementById('expenseSubmitSuccessModal');
    if (modalEl) {
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
});
<?php else: ?>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethod = document.getElementById('payment_method');
    if (paymentMethod) {
        paymentMethod.addEventListener('change', updateExpensePayoutSummary);
    }

    const amountInput = document.getElementById('amount_paid');
    if (amountInput) {
        amountInput.addEventListener('input', updateExpensePayoutSummary);
    }
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
