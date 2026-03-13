<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

requirePermission(['Store Keeper', 'Manager', 'Super Admin']);

$error = '';
$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inventory'])) {
    if (!hasPermission(['Store Keeper'])) {
        $error = 'You are not authorized to add inventory items';
    } else {
        $item_name = sanitize($_POST['item_name'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 0);
        $purchase_price = floatval($_POST['purchase_price'] ?? 0);
        $selling_price = floatval($_POST['selling_price'] ?? 0);
        $supplier = sanitize($_POST['supplier'] ?? '');
        $purchase_date = sanitize($_POST['purchase_date'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');

        $stmt = $conn->prepare('INSERT INTO inventory (item_name, category, quantity, purchase_price, selling_price, supplier, purchase_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('ssidssss', $item_name, $category, $quantity, $purchase_price, $selling_price, $supplier, $purchase_date, $notes);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Inventory item added successfully';
                header('Location: add_inventory.php');
                exit();
            }
            $error = 'Error adding inventory item';
        } else {
            $error = 'Could not prepare inventory insert query';
        }
    }
}
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

<div class="row mb-4" id="add-inventory-item">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-plus"></i> Add Inventory Item</h2>
        <a href="inventory_items.php" class="btn btn-outline-secondary"><i class="fas fa-list"></i> Inventory Items</a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-boxes"></i> New Item Details</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="item_name" class="form-label">Item Name *</label>
                                <input type="text" class="form-control" id="item_name" name="item_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="category" class="form-label">Category *</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Router">Router</option>
                                    <option value="Cable">Cable</option>
                                    <option value="Antenna">Antenna</option>
                                    <option value="Tools">Tools</option>
                                    <option value="Accessories">Accessories</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Quantity *</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="purchase_price" class="form-label">Purchase Price (Tshs.)</label>
                                <input type="number" class="form-control" id="purchase_price" name="purchase_price" step="0.01" min="0">
                            </div>
                            <div class="mb-3">
                                <label for="selling_price" class="form-label">Selling Price (Tshs.)</label>
                                <input type="number" class="form-control" id="selling_price" name="selling_price" step="0.01" min="0">
                            </div>
                            <div class="mb-3">
                                <label for="supplier" class="form-label">Supplier</label>
                                <input type="text" class="form-control" id="supplier" name="supplier">
                            </div>
                            <div class="mb-3">
                                <label for="purchase_date" class="form-label">Purchase Date</label>
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                    <button type="submit" name="add_inventory" class="btn btn-primary">Add Item</button>
                </form>
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