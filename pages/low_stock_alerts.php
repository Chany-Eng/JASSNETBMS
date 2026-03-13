<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

requirePermission(['Store Keeper', 'Manager', 'Super Admin']);
ensureInventorySoftDeleteSchema($conn);

$low_stock_items = $conn->query('SELECT * FROM inventory WHERE quantity < 5 AND COALESCE(is_deleted, 0) = 0 ORDER BY quantity ASC, item_name ASC');
?>

<?php include '../includes/header.php'; ?>

<div class="row mb-4" id="low-stock-alerts">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h2>
        <a href="inventory_items.php" class="btn btn-outline-secondary"><i class="fas fa-list"></i> Inventory Items</a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-bell"></i> Items Below Threshold (&lt; 5)</h5>
            </div>
            <div class="card-body">
                <?php if ($low_stock_items && $low_stock_items->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Supplier</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $low_stock_items->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td><span class="text-danger fw-bold"><?php echo (int) $item['quantity']; ?></span></td>
                                    <td><?php echo htmlspecialchars($item['supplier'] ?: 'N/A'); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-success mb-0">All items are sufficiently stocked.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>