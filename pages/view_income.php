<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Sales', 'Technician', 'Accountant', 'Super Admin']);

$message = '';
$error = '';

// Check for success message
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

// Get income records with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$where_clause = '';
if ($search) {
    $where_clause = " WHERE (customer_name LIKE '%$search%' OR service_type LIKE '%$search%' OR transaction_reference LIKE '%$search%')";
}

$total_result = $conn->query("SELECT COUNT(*) as count FROM income $where_clause");
$total_records = $total_result->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

$income_records = $conn->query("SELECT i.*, u.full_name FROM income i JOIN users u ON i.user_id = u.id $where_clause ORDER BY date DESC LIMIT $limit OFFSET $offset");

include '../includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0"><i class="fas fa-dollar-sign"></i> Income Records</h2>
                    <?php if (hasPermission(['Sales'])): ?>
                    <a href="add_income.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Income
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

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Search Income Records</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-9">
                                <input type="text" class="form-control" name="search" placeholder="Search by customer name, service type, or reference..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Income List (Total: <?php echo $total_records; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Service Type</th>
                                        <th>Amount (Tshs.)</th>
                                        <th>Payment Method</th>
                                        <th>Reference</th>
                                        <th>Recorded By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($income_records->num_rows > 0): ?>
                                        <?php while ($row = $income_records->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($row['service_type']); ?></span>
                                            </td>
                                            <td><strong>Tshs. <?php echo number_format($row['amount'], 2); ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                            <td><?php echo htmlspecialchars($row['transaction_reference'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                            <td>
                                                <?php if ($row['receipt_file']): ?>
                                                    <a href="../uploads/<?php echo htmlspecialchars($row['receipt_file']); ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-download"></i> Receipt
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">No receipt</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <i class="fas fa-folder-open text-muted" style="font-size: 2rem;"></i>
                                                <p class="text-muted mt-2">No income records found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>">First</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>">Last</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
