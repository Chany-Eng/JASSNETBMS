<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payouts.php';
require_once '../includes/expense_workflow.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Sales', 'Technician', 'Manager', 'Director', 'Accountant', 'Super Admin']);

$error = '';
$expense_request = null;
$expense_payments = [];
$receipt = null;
$payouts = [];
$latestPayout = null;
$payoutSummary = null;

snippeEnsurePayoutTables($conn);
expenseEnsureWorkflowSchema($conn);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = 'Invalid expense request ID';
} else {
    $request_id = intval($_GET['id']);
    
    // Fetch expense request details
    $stmt = $conn->prepare("SELECT er.*, u.full_name as requested_by_name FROM expense_requests er JOIN users u ON er.requested_by = u.id WHERE er.id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $expense_request = $result->fetch_assoc();
        $payoutSummary = expenseSyncPayoutStatus($conn, $request_id);
    } else {
        $error = 'Expense request not found';
    }
    
    // Fetch related payment if exists
    if ($expense_request) {
        $stmt = $conn->prepare("SELECT ep.*, u.full_name as accountant_name FROM expense_payments ep LEFT JOIN users u ON ep.accountant_id = u.id WHERE ep.expense_request_id = ? ORDER BY ep.payment_date DESC, ep.id DESC");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($paymentRow = $result->fetch_assoc())) {
            $expense_payments[] = $paymentRow;
        }

        $stmt = $conn->prepare("SELECT * FROM snippe_payouts WHERE expense_request_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($payoutRow = $result->fetch_assoc())) {
            $payouts[] = $payoutRow;
        }
        $latestPayout = $payouts[0] ?? null;
        
        // Fetch related receipt if exists
        $stmt = $conn->prepare("SELECT * FROM receipts WHERE expense_request_id = ? LIMIT 1");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $receipt = $result->fetch_assoc();
        }
    }
}

include '../includes/header.php';
?>

<div class="container-fluid py-4 detail-page detail-page-stack">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Expense Request Details</h2>
                    <a href="view_expense_requests.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Expenses
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($expense_request): ?>
            <?php if ($expense_request['status'] === 'Rejected'): ?>
                <div class="alert alert-danger border-0 shadow-sm">
                    <strong>Expense request rejected.</strong> This request will remain stopped until a new valid request is submitted or the rejected request is edited and resubmitted where allowed.
                </div>
            <?php endif; ?>

            <!-- Request Information Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card page-shell-card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-receipt"></i> Request Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Request ID:</strong> #<?php echo htmlspecialchars($expense_request['id']); ?></p>
                                    <p><strong>Request Date:</strong> <?php echo date('M d, Y', strtotime($expense_request['request_date'])); ?></p>
                                    <p><strong>Requested By:</strong> <?php echo htmlspecialchars($expense_request['requested_by_name']); ?></p>
                                    <p><strong>Department:</strong> <?php echo htmlspecialchars($expense_request['department']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Category:</strong> <?php echo htmlspecialchars($expense_request['category']); ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-<?php 
                                            echo ($expense_request['status'] == 'Completed') ? 'success' : 
                                                 (($expense_request['status'] == 'Rejected') ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo htmlspecialchars($expense_request['status']); ?>
                                        </span>
                                    </p>
                                    <p><strong>Project Reference:</strong> <?php echo htmlspecialchars($expense_request['project_ref'] ?: 'N/A'); ?></p>
                                    <p><strong>Amount Requested:</strong> 
                                        <span class="text-primary font-weight-bold">
                                            Tshs. <?php echo number_format($expense_request['amount_requested'], 2); ?>
                                        </span>
                                    </p>
                                    <p><strong>Total Paid:</strong> <span class="text-success">Tshs. <?php echo number_format((float) ($payoutSummary['total_paid'] ?? 0), 2); ?></span></p>
                                    <p><strong>Remaining Balance:</strong> <span class="<?php echo ((float) ($payoutSummary['remaining_balance'] ?? 0)) > 0 ? 'text-warning' : 'text-success'; ?>">Tshs. <?php echo number_format((float) ($payoutSummary['remaining_balance'] ?? 0), 2); ?></span></p>
                                </div>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-12">
                                    <p><strong>Description:</strong></p>
                                    <p><?php echo htmlspecialchars($expense_request['description']); ?></p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <p><strong>Reason:</strong></p>
                                    <p><?php echo htmlspecialchars($expense_request['reason']); ?></p>
                                </div>
                            </div>

                            <?php if ($expense_request['notes']): ?>
                                <div class="row">
                                    <div class="col-12">
                                        <p><strong>Notes:</strong></p>
                                        <p><?php echo htmlspecialchars($expense_request['notes']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($latestPayout && !empty($payoutSummary['latest_payout_status']) && in_array($payoutSummary['latest_payout_status'], ['failed', 'cancelled', 'canceled'], true)): ?>
                                <div class="alert alert-danger mb-0">
                                    <strong>Latest payout failed.</strong>
                                    <?php echo htmlspecialchars($payoutSummary['latest_failure_reason'] ?: 'No failure reason returned by Snippe.'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approval Status Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card page-shell-card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-check-circle"></i> Approval Status
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="approval-step">
                                        <div class="approval-badge <?php echo $expense_request['manager_approved'] ? 'approved' : 'pending'; ?>">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <p class="mt-2"><strong>Manager Approval</strong></p>
                                        <p class="text-muted">
                                            <?php echo $expense_request['manager_approved'] ? '<span class="text-success">✓ Approved</span>' : '<span class="text-warning">Pending</span>'; ?>
                                        </p>
                                        <div class="small text-muted"><?php echo !empty($expense_request['manager_comment']) ? nl2br(htmlspecialchars($expense_request['manager_comment'])) : 'No manager comment yet.'; ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="approval-step">
                                        <div class="approval-badge <?php echo $expense_request['director_approved'] ? 'approved' : 'pending'; ?>">
                                            <i class="fas fa-crown"></i>
                                        </div>
                                        <p class="mt-2"><strong>Director Approval</strong></p>
                                        <p class="text-muted">
                                            <?php echo $expense_request['director_approved'] ? '<span class="text-success">✓ Approved</span>' : '<span class="text-warning">Pending</span>'; ?>
                                        </p>
                                        <div class="small text-muted"><?php echo !empty($expense_request['director_comment']) ? nl2br(htmlspecialchars($expense_request['director_comment'])) : 'No director comment yet.'; ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="approval-step">
                                        <div class="approval-badge <?php echo $expense_request['accountant_processed'] ? 'approved' : 'pending'; ?>">
                                            <i class="fas fa-calculator"></i>
                                        </div>
                                        <p class="mt-2"><strong>Accountant Processing</strong></p>
                                        <p class="text-muted">
                                            <?php echo $expense_request['accountant_processed'] ? '<span class="text-success">✓ Fully Paid</span>' : '<span class="text-warning">Awaiting Balance Clearance</span>'; ?>
                                        </p>
                                        <div class="small text-muted"><?php echo !empty($expense_request['accountant_comment']) ? nl2br(htmlspecialchars($expense_request['accountant_comment'])) : 'No accountant comment yet.'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Summary Card -->
            <?php if ($payoutSummary): ?>
                <div class="row mb-4">
                    <div class="col-12 mb-4">
                        <div class="card page-shell-card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-money-bill"></i> Payment Summary
                                </h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Requested:</strong> Tshs. <?php echo number_format((float) ($payoutSummary['amount_requested'] ?? 0), 2); ?></p>
                                <p><strong>Paid:</strong> <span class="text-success">Tshs. <?php echo number_format((float) ($payoutSummary['total_paid'] ?? 0), 2); ?></span></p>
                                <p><strong>In Flight:</strong> Tshs. <?php echo number_format((float) ($payoutSummary['total_in_flight'] ?? 0), 2); ?></p>
                                <p><strong>Remaining:</strong> <span class="<?php echo ((float) ($payoutSummary['remaining_balance'] ?? 0)) > 0 ? 'text-warning' : 'text-success'; ?>">Tshs. <?php echo number_format((float) ($payoutSummary['remaining_balance'] ?? 0), 2); ?></span></p>
                                <p><strong>Next Workflow Status:</strong> <?php echo htmlspecialchars((string) ($payoutSummary['next_status'] ?? 'Pending Accountant Processing')); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card page-shell-card h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-history"></i> Payment History</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($expense_payments): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Amount</th>
                                                    <th>Method</th>
                                                    <th>Processed By</th>
                                                    <th>Reference</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($expense_payments as $expense_payment): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($expense_payment['payment_date']))); ?></td>
                                                        <td>Tshs. <?php echo number_format((float) $expense_payment['amount_paid'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($expense_payment['payment_method']); ?></td>
                                                        <td><?php echo htmlspecialchars($expense_payment['accountant_name'] ?: 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($expense_payment['payout_reference'] ?: 'Manual'); ?></td>
                                                    </tr>
                                                    <?php if (!empty($expense_payment['payment_notes'])): ?>
                                                        <tr>
                                                            <td colspan="5" class="small text-muted">Notes: <?php echo nl2br(htmlspecialchars($expense_payment['payment_notes'])); ?></td>
                                                        </tr>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 text-muted">No payment has been recorded yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($payouts): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card page-shell-card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-paper-plane"></i> Snippe Payout Attempts
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Created</th>
                                                <th>Channel</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Reference</th>
                                                <th>Failure Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payouts as $payout): ?>
                                                <?php $payoutStatus = strtolower((string) ($payout['status'] ?? 'pending')); ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime((string) ($payout['created_at'] ?? 'now')))); ?></td>
                                                    <td><?php echo htmlspecialchars(stripos((string) $payout['payout_channel'], 'bank') !== false ? 'Bank Transfer' : 'Mobile Money'); ?></td>
                                                    <td>Tshs. <?php echo number_format((float) ($payout['amount_value'] ?? 0), 2); ?></td>
                                                    <td><span class="badge bg-<?php echo in_array($payoutStatus, ['completed', 'success'], true) ? 'success' : (in_array($payoutStatus, ['failed', 'cancelled', 'canceled'], true) ? 'danger' : 'warning'); ?>"><?php echo htmlspecialchars(strtoupper($payoutStatus)); ?></span></td>
                                                    <td><?php echo htmlspecialchars($payout['reference'] ?: 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($payout['failure_reason'] ?: 'N/A'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Receipt Information Card -->
            <?php if ($receipt): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card page-shell-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-alt"></i> Receipt Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Vendor Name:</strong> <?php echo htmlspecialchars($receipt['vendor_name']); ?></p>
                                        <p><strong>Receipt Number:</strong> <?php echo htmlspecialchars($receipt['receipt_number']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Actual Amount:</strong> 
                                            <span class="text-primary font-weight-bold">
                                                Tshs. <?php echo number_format($receipt['actual_amount'], 2); ?>
                                            </span>
                                        </p>
                                        <?php if ($receipt['notes']): ?>
                                            <p><strong>Notes:</strong> <?php echo htmlspecialchars($receipt['notes']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($receipt['receipt_file']): ?>
                                    <hr>
                                    <div class="row">
                                        <div class="col-12">
                                            <p><strong>Receipt File:</strong></p>
                                            <a href="../uploads/<?php echo htmlspecialchars($receipt['receipt_file']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download"></i> Download Receipt
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
</div>

<style>
.detail-page-stack {
    max-width: 1180px;
}

.detail-page .card {
    border: 1px solid #dbe4f0;
    border-radius: 18px;
    overflow: hidden;
}

.detail-page .card-header {
    padding: 1rem 1.25rem;
}

.approval-badge {
    width: 80px;
    height: 80px;
    margin: 0 auto;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: white;
}

.approval-badge.approved {
    background: linear-gradient(135deg, #388e3c 0%, #2e7d32 100%);
    box-shadow: 0 4px 12px rgba(56, 142, 60, 0.3);
}

.approval-badge.pending {
    background: linear-gradient(135deg, #ffa500 0%, #ff9800 100%);
    box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
}

.approval-step {
    padding: 20px;
}
</style>

<?php include '../includes/footer.php'; ?>
