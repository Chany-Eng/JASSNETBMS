<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Sales', 'Super Admin']);

$error = '';
$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

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
                    appLogActivity($conn, 'CREATE_INCOME', 'Added income record for ' . $customer_name, 'income', (int) $stmt->insert_id);
                    $_SESSION['success_message'] = 'Income record added successfully';
                    header("Location: add_income.php");
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

<style>
    .income-entry-board {
        background: linear-gradient(120deg, #16324f 0%, #235789 100%);
        color: #fff;
        border-radius: 14px;
        padding: 1.2rem 1.5rem;
        box-shadow: 0 10px 24px rgba(22, 50, 79, 0.22);
    }

    .income-entry-note {
        border-radius: 12px;
        border: 1px solid #d9e4ec;
        background: #fff;
        padding: 1rem 1.1rem;
        height: 100%;
    }

    .section-title {
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #5c6770;
        margin-bottom: 0.85rem;
    }
</style>

<?php if ($success_message): ?>
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="incomeSuccessToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" style="font-size: 1.1rem; padding: 1rem; min-width: 360px;">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="income-entry-board d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="mb-1"><i class="fas fa-plus"></i> Add Income</h2>
                    <div class="small">Record direct customer payments and keep them aligned with your full income list.</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="view_income.php" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back to Income
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Income Record Form</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="section-title">Customer Details</div>
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
                            <div class="col-lg-6">
                                <div class="section-title">Payment Details</div>
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

                        <div class="row g-4 mt-1">
                            <div class="col-lg-8">
                                <div class="mb-0">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="4"></textarea>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="income-entry-note">
                                    <div class="section-title">Quick Note</div>
                                    <p class="mb-2 text-muted">Snippe payments enter automatically on the income list. Use this page for direct/manual income records.</p>
                                    <p class="mb-0 text-muted">If you upload a receipt, it will appear in the income table for quick download.</p>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4 flex-wrap">
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

<?php if ($success_message): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toastElement = document.getElementById('incomeSuccessToast');
    if (toastElement) {
        const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
        toast.show();
    }
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
