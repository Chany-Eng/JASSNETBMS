<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Sales', 'Technician', 'Manager', 'Director', 'Accountant', 'Super Admin']);

$error = '';
$expense_request = null;
$expense_payment = null;
$receipt = null;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = 'Invalid expense request ID';
} else {
    $request_id = intval($_GET['id']);
    
    // Fetch expense request details
    $stmt = $conn->prepare("SELECT er.*, u.name as requested_by_name FROM expense_requests er JOIN users u ON er.requested_by = u.id WHERE er.id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $expense_request = $result->fetch_assoc();
    } else {
        $error = 'Expense request not found';
    }
    
    // Fetch related payment if exists
    if ($expense_request) {
        $stmt = $conn->prepare("SELECT ep.*, u.name as accountant_name FROM expense_payments ep LEFT JOIN users u ON ep.accountant_id = u.id WHERE ep.expense_request_id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $expense_payment = $result->fetch_assoc();
        }
        
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

<div class="main-content">
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Expense Request Details</h2>
                    <a href="expenses.php" class="btn btn-secondary">
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
            <!-- Request Information Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
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
                                                 ($expense_request['status'] == 'Rejected') ? 'danger' : 
                                                 'warning'; 
                                        ?>">
                                            <?php echo htmlspecialchars($expense_request['status']); ?>
                                        </span>
                                    </p>
                                    <p><strong>Project Reference:</strong> <?php echo htmlspecialchars($expense_request['project_ref'] ?: 'N/A'); ?></p>
                                    <p><strong>Amount Requested:</strong> 
                                        <span class="text-primary font-weight-bold">
                                            $<?php echo number_format($expense_request['amount_requested'], 2); ?>
                                        </span>
                                    </p>
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
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approval Status Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
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
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="approval-step">
                                        <div class="approval-badge <?php echo $expense_request['accountant_processed'] ? 'approved' : 'pending'; ?>">
                                            <i class="fas fa-calculator"></i>
                                        </div>
                                        <p class="mt-2"><strong>Accountant Processing</strong></p>
                                        <p class="text-muted">
                                            <?php echo $expense_request['accountant_processed'] ? '<span class="text-success">✓ Processed</span>' : '<span class="text-warning">Pending</span>'; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Information Card -->
            <?php if ($expense_payment): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-money-bill"></i> Payment Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Amount Paid:</strong> 
                                            <span class="text-success font-weight-bold">
                                                $<?php echo number_format($expense_payment['amount_paid'], 2); ?>
                                            </span>
                                        </p>
                                        <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($expense_payment['payment_method']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Payment Date:</strong> <?php echo date('M d, Y', strtotime($expense_payment['payment_date'])); ?></p>
                                        <p><strong>Processed By:</strong> <?php echo htmlspecialchars($expense_payment['accountant_name'] ?: 'N/A'); ?></p>
                                    </div>
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
                        <div class="card">
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
                                                $<?php echo number_format($receipt['actual_amount'], 2); ?>
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
</div>

<style>
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
