<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Sales', 'Super Admin']);

$error = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_income'])) {
        if (!hasPermission(['Sales'])) {
            $error = 'You are not authorized to add income records';
        } else {
            $date = sanitize($_POST['date']);
            $customer_name = sanitize($_POST['customer_name']);
            $phone = sanitize($_POST['phone']);
            $service_type = sanitize($_POST['service_type']);
            $amount = floatval($_POST['amount']);
            $payment_method = sanitize($_POST['payment_method']);
            $transaction_reference = sanitize($_POST['transaction_reference']);
            $notes = sanitize($_POST['notes']);
            
            $receipt_file = '';
            if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
                $upload_result = uploadFile($_FILES['receipt']);
                if (isset($upload_result['success'])) {
                    $receipt_file = $upload_result['success'];
                } else {
                    $error = $upload_result['error'];
                }
            }
            
            if (!$error) {
                $stmt = $conn->prepare("INSERT INTO income (date, customer_name, phone, service_type, amount, payment_method, transaction_reference, notes, receipt_file, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssdssssi", $date, $customer_name, $phone, $service_type, $amount, $payment_method, $transaction_reference, $notes, $receipt_file, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Income record added successfully';
                    header("Location: view_income.php");
                    exit();
                } else {
                    $error = 'Error adding income record';
                }
            }
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
                    <h2 class="mb-0"><i class="fas fa-plus"></i> Add Income</h2>
                    <a href="view_income.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Income
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Income Record Form</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone">
                                    </div>
                                    <div class="mb-3">
                                        <label for="service_type" class="form-label">Service Type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="service_type" name="service_type" required>
                                            <option value="">Select Service Type</option>
                                            <option value="WiFi Voucher">WiFi Voucher</option>
                                            <option value="Installation">Installation</option>
                                            <option value="Router Sale">Router Sale</option>
                                            <option value="Subscription">Subscription</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="amount" class="form-label">Amount Received (Tshs.) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                        <select class="form-select" id="payment_method" name="payment_method" required>
                                            <option value="">Select Payment Method</option>
                                            <option value="Cash">Cash</option>
                                            <option value="Mobile Money">Mobile Money</option>
                                            <option value="Bank">Bank</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="transaction_reference" class="form-label">Transaction Reference</label>
                                        <input type="text" class="form-control" id="transaction_reference" name="transaction_reference">
                                    </div>
                                    <div class="mb-3">
                                        <label for="receipt" class="form-label">Receipt (Image/PDF)</label>
                                        <input type="file" class="form-control" id="receipt" name="receipt" accept=".jpg,.jpeg,.png,.pdf">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="add_income" class="btn btn-success">
                                    <i class="fas fa-save"></i> Add Income Record
                                </button>
                                <a href="view_income.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
