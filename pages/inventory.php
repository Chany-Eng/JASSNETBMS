<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Store Keeper', 'Manager', 'Super Admin']);

$message = '';
$error = '';

// Check for success message
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_inventory'])) {
        if (!hasPermission(['Store Keeper'])) {
            $error = 'You are not authorized to add inventory items';
        } else {
            $item_name = sanitize($_POST['item_name']);
            $category = sanitize($_POST['category']);
            $quantity = intval($_POST['quantity']);
            $purchase_price = floatval($_POST['purchase_price']);
            $selling_price = floatval($_POST['selling_price']);
            $supplier = sanitize($_POST['supplier']);
            $purchase_date = sanitize($_POST['purchase_date']);
            $notes = sanitize($_POST['notes']);
            
            $stmt = $conn->prepare("INSERT INTO inventory (item_name, category, quantity, purchase_price, selling_price, supplier, purchase_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssidssss", $item_name, $category, $quantity, $purchase_price, $selling_price, $supplier, $purchase_date, $notes);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Inventory item added successfully';
                header("Location: inventory.php");
                exit();
            } else {
                $error = 'Error adding inventory item';
            }
        }
    } elseif (isset($_POST['update_stock'])) {
        $item_id = intval($_POST['item_id']);
        $new_quantity = intval($_POST['new_quantity']);
        $operation = sanitize($_POST['operation']); // 'add' or 'subtract'
        
        // Get current quantity
        $result = $conn->query("SELECT quantity FROM inventory WHERE id = $item_id");
        $current_quantity = $result->fetch_assoc()['quantity'];
        
        if ($operation == 'add') {
            $updated_quantity = $current_quantity + $new_quantity;
        } else {
            $updated_quantity = max(0, $current_quantity - $new_quantity);
        }
        
        $stmt = $conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
        $stmt->bind_param("ii", $updated_quantity, $item_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Stock updated successfully';
            header("Location: inventory.php");
            exit();
        } else {
            $error = 'Error updating stock';
        }
    } elseif (isset($_POST['issue_equipment'])) {
        $item_id = intval($_POST['item_id']);
        $quantity_issued = intval($_POST['quantity_issued']);
        $requested_by = intval($_POST['requested_by']);
        $reason = sanitize($_POST['reason']);
        $project = sanitize($_POST['project']);
        
        // Check if enough stock
        $result = $conn->query("SELECT quantity FROM inventory WHERE id = $item_id");
        $current_quantity = $result->fetch_assoc()['quantity'];
        
        if ($current_quantity >= $quantity_issued) {
            // Create equipment request
            $stmt = $conn->prepare("INSERT INTO equipment_requests (requested_by, item_id, quantity, reason, project, status, approved_by, issued_by) VALUES (?, ?, ?, ?, ?, 'Issued', ?, ?)");
            $stmt->bind_param("iiisssi", $requested_by, $item_id, $quantity_issued, $reason, $project, $_SESSION['user_id'], $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                // Update inventory
                $new_quantity = $current_quantity - $quantity_issued;
                $conn->query("UPDATE inventory SET quantity = $new_quantity WHERE id = $item_id");
                $_SESSION['success_message'] = 'Equipment issued successfully';
                header("Location: inventory.php");
                exit();
            } else {
                $error = 'Error issuing equipment';
            }
        } else {
            $error = 'Insufficient stock';
        }
    }
}

// Get inventory items
$inventory_items = $conn->query("SELECT * FROM inventory ORDER BY item_name");

// Get low stock items
$low_stock_items = $conn->query("SELECT * FROM inventory WHERE quantity < 5 ORDER BY quantity ASC");

// Get equipment requests
$equipment_requests = $conn->query("SELECT er.*, i.item_name, u.full_name as requested_by_name FROM equipment_requests er JOIN inventory i ON er.item_id = i.id JOIN users u ON er.requested_by = u.id ORDER BY request_date DESC LIMIT 10");
?>

<?php include '../includes/header.php'; ?>

<?php if ($success_message): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" style="font-size: 1.2rem; padding: 1rem; min-width: 400px;">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2" style="font-size: 1.5rem;"></i> <?php echo $success_message; ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-boxes"></i> Inventory Management</h2>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (hasPermission(['Store Keeper'])): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-plus"></i> Add Inventory Item</h5>
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
                                <label for="purchase_price" class="form-label">Purchase Price</label>
                                <input type="number" class="form-control" id="purchase_price" name="purchase_price" step="0.01" min="0">
                            </div>
                            <div class="mb-3">
                                <label for="selling_price" class="form-label">Selling Price</label>
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
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h5>
            </div>
            <div class="card-body">
                <?php if ($low_stock_items->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($item = $low_stock_items->fetch_assoc()): ?>
                            <div class="list-group-item list-group-item-warning">
                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong> - Only <?php echo $item['quantity']; ?> left
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-success">All items are sufficiently stocked.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-tools"></i> Issue Equipment</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="issue_item_id" class="form-label">Select Item</label>
                        <select class="form-select" id="issue_item_id" name="item_id" required>
                            <option value="">Select Item</option>
                            <?php 
                            $items_result = $conn->query("SELECT * FROM inventory WHERE quantity > 0 ORDER BY item_name");
                            while ($item = $items_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['item_name']); ?> (<?php echo $item['quantity']; ?> available)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="requested_by" class="form-label">Issue To</label>
                        <select class="form-select" id="requested_by" name="requested_by" required>
                            <option value="">Select User</option>
                            <?php 
                            $users_result = $conn->query("SELECT id, full_name FROM users WHERE role IN ('Sales', 'Technician') AND is_active = 1 ORDER BY full_name");
                            while ($user = $users_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="quantity_issued" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity_issued" name="quantity_issued" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="issue_reason" class="form-label">Reason</label>
                        <input type="text" class="form-control" id="issue_reason" name="reason" required>
                    </div>
                    <div class="mb-3">
                        <label for="issue_project" class="form-label">Project</label>
                        <input type="text" class="form-control" id="issue_project" name="project">
                    </div>
                    <button type="submit" name="issue_equipment" class="btn btn-success">Issue Equipment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Inventory Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Purchase Price</th>
                                <th>Selling Price</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $inventory_items->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                <td>
                                    <span class="<?php echo $item['quantity'] < 5 ? 'text-danger fw-bold' : ''; ?>">
                                        <?php echo $item['quantity']; ?>
                                    </span>
                                </td>
                                <td>$<?php echo number_format($item['purchase_price'], 2); ?></td>
                                <td>$<?php echo number_format($item['selling_price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($item['supplier']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $item['status'] == 'Available' ? 'success' : ($item['status'] == 'Damaged' ? 'danger' : 'warning'); ?>">
                                        <?php echo $item['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="updateStock(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>', <?php echo $item['quantity']; ?>)">Update Stock</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> Recent Equipment Issues</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Issued To</th>
                                <th>Quantity</th>
                                <th>Reason</th>
                                <th>Project</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $equipment_requests->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                <td><?php echo $request['quantity']; ?></td>
                                <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                <td><?php echo htmlspecialchars($request['project']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="updateStockForm">
                    <input type="hidden" name="item_id" id="updateStockItemId">
                    <div class="mb-3">
                        <label>Item: <span id="updateStockItemName"></span></label>
                        <p>Current Quantity: <span id="updateStockCurrentQty"></span></p>
                    </div>
                    <div class="mb-3">
                        <label for="operation" class="form-label">Operation</label>
                        <select class="form-select" id="operation" name="operation" required>
                            <option value="add">Add Stock</option>
                            <option value="subtract">Remove Stock</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new_quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="new_quantity" name="new_quantity" min="1" required>
                    </div>
                    <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function updateStock(id, name, currentQty) {
    document.getElementById('updateStockItemId').value = id;
    document.getElementById('updateStockItemName').textContent = name;
    document.getElementById('updateStockCurrentQty').textContent = currentQty;
    new bootstrap.Modal(document.getElementById('updateStockModal')).show();
}
</script>

<?php if ($success_message): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toast = new bootstrap.Toast(document.getElementById('successToast'), { delay: 5000 });
    toast.show();
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
