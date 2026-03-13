<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Sales', 'Super Admin']);

$message = '';
$error = '';

// Check for success message
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
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
                $stmt->bind_param("ssssdsssss", $date, $customer_name, $phone, $service_type, $amount, $payment_method, $transaction_reference, $notes, $receipt_file, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Income record added successfully';
                    header("Location: income.php");
                    exit();
                } else {
                    $error = 'Error adding income record';
                }
            }
        }
    }
}

// Get income records with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$allowedPerPage = [10, 25, 50, 100, 200];
$limit = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
if (!in_array($limit, $allowedPerPage, true)) {
    $limit = 10;
}
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$where_clause = '';
if ($search) {
    $where_clause = " AND (customer_name LIKE '%$search%' OR service_type LIKE '%$search%' OR transaction_reference LIKE '%$search%')";
}

$total_result = $conn->query("SELECT COUNT(*) as count FROM income WHERE 1 $where_clause");
$total_records = $total_result->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

$income_records = $conn->query("SELECT i.*, u.full_name FROM income i JOIN users u ON i.user_id = u.id WHERE 1 $where_clause ORDER BY date DESC LIMIT $limit OFFSET $offset");
?>

<?php include '../includes/header.php'; ?>

<?php if ($success_message): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" style="font-size: 1.2rem; padding: 1rem; min-width: 400px;">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2" style="font-size: 1.5rem;"></i> <?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-dollar-sign"></i> Income Management</h2>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars(formatSuccessMessage($message)); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (hasPermission(['Sales'])): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-plus"></i> Add Income Record</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="customer_name" class="form-label">Customer Name *</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="mb-3">
                                <label for="service_type" class="form-label">Service Type *</label>
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
                                <label for="amount" class="form-label">Amount Received (Tshs.) *</label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Payment Method *</label>
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
                    <button type="submit" name="add_income" class="btn btn-primary">Add Income Record</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-list"></i> Income Records</h5>
                <form method="GET" class="row g-2 align-items-center w-100 justify-content-end" style="max-width: 520px;">
                    <div class="col-sm-6">
                        <input type="text" class="form-control" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-sm-3">
                        <select class="form-select" name="per_page">
                            <?php foreach ($allowedPerPage as $perPageOption): ?>
                                <option value="<?php echo $perPageOption; ?>" <?php echo $limit === $perPageOption ? 'selected' : ''; ?>><?php echo $perPageOption; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <button type="submit" class="btn btn-outline-primary w-100">Search</button>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" data-table-pagination="server" data-row-number-start="<?php echo $offset + 1; ?>">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Amount (Tshs.)</th>
                                <th>Payment</th>
                                <th>Reference</th>
                                <th>Recorded By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $income_records->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['service_type']); ?></td>
                                <td>Tshs. <?php echo number_format($row['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($row['transaction_reference']); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td>
                                    <?php if ($row['receipt_file']): ?>
                                        <a href="../uploads/<?php echo $row['receipt_file']; ?>" target="_blank" class="btn btn-sm btn-outline-info">View Receipt</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $limit; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($success_message): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toast = new bootstrap.Toast(document.getElementById('successToast'), { delay: 5000 });
    toast.show();
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>