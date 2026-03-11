<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Sales', 'Technician', 'Manager', 'Director', 'Accountant', 'Super Admin']);

$message = '';
$error = '';

// Check for success message
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve_manager'])) {
        $request_id = intval($_POST['request_id']);
        $stmt = $conn->prepare("UPDATE expense_requests SET status = 'Pending Director Approval', manager_approved = 1 WHERE id = ? AND status = 'Pending Manager Approval'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $message = 'Expense request approved by manager';
    } elseif (isset($_POST['reject_manager'])) {
        $request_id = intval($_POST['request_id']);
        $stmt = $conn->prepare("UPDATE expense_requests SET status = 'Rejected' WHERE id = ? AND status = 'Pending Manager Approval'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $message = 'Expense request rejected by manager';
    } elseif (isset($_POST['approve_director'])) {
        $request_id = intval($_POST['request_id']);
        $stmt = $conn->prepare("UPDATE expense_requests SET status = 'Pending Accountant Processing', director_approved = 1 WHERE id = ? AND status = 'Pending Director Approval'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $message = 'Expense request approved by director';
    } elseif (isset($_POST['reject_director'])) {
        $request_id = intval($_POST['request_id']);
        $stmt = $conn->prepare("UPDATE expense_requests SET status = 'Rejected' WHERE id = ? AND status = 'Pending Director Approval'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $message = 'Expense request rejected by director';
    } elseif (isset($_POST['process_accountant'])) {
        $request_id = intval($_POST['request_id']);
        $amount_paid = floatval($_POST['amount_paid']);
        $payment_method = sanitize($_POST['payment_method']);
        $payment_date = sanitize($_POST['payment_date']);
        
        $stmt = $conn->prepare("INSERT INTO expense_payments (expense_request_id, amount_paid, payment_method, payment_date, accountant_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("idssi", $request_id, $amount_paid, $payment_method, $payment_date, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $stmt = $conn->prepare("UPDATE expense_requests SET status = 'Waiting for Receipt', accountant_processed = 1 WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $message = 'Payment processed by accountant';
        }
    } elseif (isset($_POST['upload_receipt'])) {
        $request_id = intval($_POST['request_id']);
        $vendor_name = sanitize($_POST['vendor_name']);
        $receipt_number = sanitize($_POST['receipt_number']);
        $actual_amount = floatval($_POST['actual_amount']);
        $notes = sanitize($_POST['notes']);
        
        $receipt_file = '';
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] == 0) {
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
                $message = 'Receipt uploaded successfully';
            } else {
                $error = 'Error uploading receipt';
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

$expense_requests = $conn->query("SELECT er.*, u.full_name as requested_by_name FROM expense_requests er JOIN users u ON er.requested_by = u.id $where_clause ORDER BY request_date DESC");

include '../includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0"><i class="fas fa-receipt"></i> Expense Requests</h2>
                    <?php if (hasPermission(['Sales', 'Technician'])): ?>
                    <a href="add_expense_request.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Request
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
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
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Expense Requests List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
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
                                    <?php if ($expense_requests->num_rows > 0): ?>
                                        <?php while ($row = $expense_requests->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['requested_by_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                                            <td>Tshs. <?php echo number_format($row['amount_requested'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $row['status'] == 'Completed' ? 'success' : 
                                                         ($row['status'] == 'Rejected' ? 'danger' : 
                                                         ($row['status'] == 'Pending Manager Approval' ? 'warning' : 'info')); 
                                                ?>"><?php echo $row['status']; ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewRequest(<?php echo $row['id']; ?>)">View</button>
                                                <?php if ($_SESSION['role'] == 'Manager' && $row['status'] == 'Pending Manager Approval'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="approveManager(<?php echo $row['id']; ?>)">Approve</button>
                                                    <button class="btn btn-sm btn-danger" onclick="rejectManager(<?php echo $row['id']; ?>)">Reject</button>
                                                <?php elseif (($_SESSION['role'] == 'Director' || $_SESSION['role'] == 'Super Admin') && $row['status'] == 'Pending Director Approval'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="approveDirector(<?php echo $row['id']; ?>)">Approve</button>
                                                    <button class="btn btn-sm btn-danger" onclick="rejectDirector(<?php echo $row['id']; ?>)">Reject</button>
                                                <?php elseif ($_SESSION['role'] == 'Accountant' && $row['status'] == 'Pending Accountant Processing'): ?>
                                                    <button class="btn btn-sm btn-primary" onclick="processAccountant(<?php echo $row['id']; ?>)">Process</button>
                                                <?php elseif ($row['status'] == 'Waiting for Receipt' && $row['requested_by'] == $_SESSION['user_id']): ?>
                                                    <button class="btn btn-sm btn-info" onclick="uploadReceipt(<?php echo $row['id']; ?>)">Upload Receipt</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-folder-open text-muted" style="font-size: 2rem;"></i>
                                                <p class="text-muted mt-2">No expense requests found</p>
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
                    <div class="mb-3">
                        <label for="amount_paid" class="form-label">Amount Paid (Tshs.)</label>
                        <input type="number" class="form-control" id="amount_paid" name="amount_paid" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
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

<script>
function viewRequest(id) {
    window.location.href = 'expense_detail.php?id=' + id;
}

function approveManager(id) {
    document.getElementById('approveManagerRequestId').value = id;
    new bootstrap.Modal(document.getElementById('approveManagerModal')).show();
}

function rejectManager(id) {
    document.getElementById('rejectManagerRequestId').value = id;
    new bootstrap.Modal(document.getElementById('rejectManagerModal')).show();
}

function approveDirector(id) {
    document.getElementById('approveDirectorRequestId').value = id;
    new bootstrap.Modal(document.getElementById('approveDirectorModal')).show();
}

function rejectDirector(id) {
    document.getElementById('rejectDirectorRequestId').value = id;
    new bootstrap.Modal(document.getElementById('rejectDirectorModal')).show();
}

function processAccountant(id) {
    document.getElementById('processAccountantRequestId').value = id;
    new bootstrap.Modal(document.getElementById('processAccountantModal')).show();
}

function uploadReceipt(id) {
    document.getElementById('uploadReceiptRequestId').value = id;
    new bootstrap.Modal(document.getElementById('uploadReceiptModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
