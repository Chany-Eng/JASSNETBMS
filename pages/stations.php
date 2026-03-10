<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

// page accessible to relevant staff; addition restricted below
requirePermission(['Technician', 'Sales', 'Manager', 'Director', 'Super Admin']);

$message = '';
$error = '';

// Check for success message
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_station_request'])) {
        // only technicians (and superadmin via hasPermission) may create requests
        if (!hasPermission(['Technician'])) {
            $error = 'You are not authorized to add station requests';
        } else {
            $station_name = sanitize($_POST['station_name']);
            $location = sanitize($_POST['location']);
            $gps = sanitize($_POST['gps']);
            $description = sanitize($_POST['description']);
            $coverage_area = sanitize($_POST['coverage_area']);
            $installation_type = sanitize($_POST['installation_type']);
            
            // Calculate total estimated cost
            $equipment_cost = floatval($_POST['equipment_cost']);
            $installation_cost = floatval($_POST['installation_cost']);
            $transport_cost = floatval($_POST['transport_cost']);
            $labor_cost = floatval($_POST['labor_cost']);
            $misc_cost = floatval($_POST['misc_cost']);
            $total_estimated_cost = $equipment_cost + $installation_cost + $transport_cost + $labor_cost + $misc_cost;
            
            $stmt = $conn->prepare("INSERT INTO station_requests (requested_by, station_name, location, gps, description, coverage_area, installation_type, total_estimated_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssd", $_SESSION['user_id'], $station_name, $location, $gps, $description, $coverage_area, $installation_type, $total_estimated_cost);
            
            if ($stmt->execute()) {
                $station_id = $conn->insert_id;
                
                // Add equipment requirements
                if (isset($_POST['equipment_name'])) {
                    for ($i = 0; $i < count($_POST['equipment_name']); $i++) {
                        $equipment_name = sanitize($_POST['equipment_name'][$i]);
                        $quantity = intval($_POST['equipment_quantity'][$i]);
                        $source = sanitize($_POST['equipment_source'][$i]);
                        $inventory_id = isset($_POST['inventory_id'][$i]) ? intval($_POST['inventory_id'][$i]) : null;
                        $purchase_cost = floatval($_POST['purchase_cost'][$i]);
                        $supplier = sanitize($_POST['supplier'][$i]);
                        
                        $stmt2 = $conn->prepare("INSERT INTO station_equipment (station_request_id, equipment_name, quantity, source, inventory_id, purchase_cost, supplier) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt2->bind_param("isisdss", $station_id, $equipment_name, $quantity, $source, $inventory_id, $purchase_cost, $supplier);
                        $stmt2->execute();
                    }
                }
                
                $_SESSION['success_message'] = 'Station setup request submitted successfully';
                header("Location: stations.php");
                exit();
            } else {
                $error = 'Error submitting station request';
            }
        }
    } elseif (isset($_POST['approve_manager'])) {
        $request_id = intval($_POST['request_id']);
        $stmt = $conn->prepare("UPDATE station_requests SET status = 'Approved', approved_by = ? WHERE id = ? AND status = 'Pending Approval'");
        $stmt->bind_param("ii", $_SESSION['user_id'], $request_id);
        $stmt->execute();
        $message = 'Station request approved by manager';
    } elseif (isset($_POST['approve_director'])) {
        $request_id = intval($_POST['request_id']);
        $stmt = $conn->prepare("UPDATE station_requests SET status = 'Equipment Issued', approved_by = ? WHERE id = ? AND status = 'Approved'");
        $stmt->bind_param("ii", $_SESSION['user_id'], $request_id);
        $stmt->execute();
        
        // Reserve equipment from inventory
        $equipment_result = $conn->query("SELECT * FROM station_equipment WHERE station_request_id = $request_id AND source = 'Inventory'");
        while ($equipment = $equipment_result->fetch_assoc()) {
            $inventory_id = $equipment['inventory_id'];
            $quantity = $equipment['quantity'];
            $conn->query("UPDATE inventory SET quantity = quantity - $quantity WHERE id = $inventory_id");
        }
        
        $message = 'Station request approved by director and equipment reserved';
    } elseif (isset($_POST['update_progress'])) {
        $request_id = intval($_POST['request_id']);
        $status = sanitize($_POST['progress_status']);
        $notes = sanitize($_POST['progress_notes']);
        
        $stmt = $conn->prepare("INSERT INTO station_progress (station_request_id, status, notes) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $request_id, $status, $notes);
        
        if ($stmt->execute()) {
            // Update station status
            $conn->query("UPDATE station_requests SET status = '$status' WHERE id = $request_id");
            $message = 'Progress updated successfully';
        } else {
            $error = 'Error updating progress';
        }
    }
}

// Get station requests based on user role
$where_clause = '';
if ($_SESSION['role'] == 'Sales' || $_SESSION['role'] == 'Technician') {
    $where_clause = "WHERE sr.requested_by = {$_SESSION['user_id']}";
} elseif ($_SESSION['role'] == 'Manager') {
    $where_clause = "WHERE sr.status = 'Pending Approval'";
} elseif ($_SESSION['role'] == 'Director') {
    $where_clause = "WHERE sr.status IN ('Approved', 'Equipment Issued', 'Installation in Progress', 'Completed')";
}

$station_requests = $conn->query("SELECT sr.*, u.full_name as requested_by_name FROM station_requests sr JOIN users u ON sr.requested_by = u.id $where_clause ORDER BY request_date DESC");

// Get inventory items for equipment selection
$inventory_items = $conn->query("SELECT id, item_name, quantity FROM inventory WHERE quantity > 0 ORDER BY item_name");
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
        <h2><i class="fas fa-broadcast-tower"></i> Station Setup Management</h2>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (hasPermission(['Technician'])): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-plus"></i> Request New Station Setup</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="stationForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="station_name" class="form-label">Station Name *</label>
                                <input type="text" class="form-control" id="station_name" name="station_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>
                            <div class="mb-3">
                                <label for="gps" class="form-label">GPS Coordinates</label>
                                <input type="text" class="form-control" id="gps" name="gps" placeholder="e.g., 40.7128, -74.0060">
                            </div>
                            <div class="mb-3">
                                <label for="installation_type" class="form-label">Installation Type *</label>
                                <select class="form-select" id="installation_type" name="installation_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Hotspot">Hotspot</option>
                                    <option value="Tower">Tower</option>
                                    <option value="Relay">Relay</option>
                                    <option value="Fiber Node">Fiber Node</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="coverage_area" class="form-label">Expected Coverage Area</label>
                                <input type="text" class="form-control" id="coverage_area" name="coverage_area">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Project Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <h5>Cost Estimation</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="equipment_cost" class="form-label">Equipment Cost</label>
                                <input type="number" class="form-control cost-input" id="equipment_cost" name="equipment_cost" step="0.01" min="0" value="0">
                            </div>
                            <div class="mb-3">
                                <label for="installation_cost" class="form-label">Installation Cost</label>
                                <input type="number" class="form-control cost-input" id="installation_cost" name="installation_cost" step="0.01" min="0" value="0">
                            </div>
                            <div class="mb-3">
                                <label for="transport_cost" class="form-label">Transport Cost</label>
                                <input type="number" class="form-control cost-input" id="transport_cost" name="transport_cost" step="0.01" min="0" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="labor_cost" class="form-label">Labor Cost</label>
                                <input type="number" class="form-control cost-input" id="labor_cost" name="labor_cost" step="0.01" min="0" value="0">
                            </div>
                            <div class="mb-3">
                                <label for="misc_cost" class="form-label">Miscellaneous Cost</label>
                                <input type="number" class="form-control cost-input" id="misc_cost" name="misc_cost" step="0.01" min="0" value="0">
                            </div>
                            <div class="mb-3">
                                <label for="total_estimated_cost" class="form-label">Total Estimated Cost</label>
                                <input type="number" class="form-control" id="total_estimated_cost" name="total_estimated_cost" step="0.01" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <h5>Equipment Requirements</h5>
                    <div id="equipmentContainer">
                        <div class="equipment-item border p-3 mb-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Equipment Name</label>
                                        <input type="text" class="form-control" name="equipment_name[]" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Quantity</label>
                                        <input type="number" class="form-control" name="equipment_quantity[]" min="1" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Source</label>
                                        <select class="form-select source-select" name="equipment_source[]" required>
                                            <option value="Inventory">From Inventory</option>
                                            <option value="Purchase">Purchase New</option>
                                        </select>
                                    </div>
                                    <div class="inventory-fields" style="display: none;">
                                        <label class="form-label">Select from Inventory</label>
                                        <select class="form-select" name="inventory_id[]">
                                            <option value="">Select Item</option>
                                            <?php while ($item = $inventory_items->fetch_assoc()): ?>
                                                <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['item_name']); ?> (<?php echo $item['quantity']; ?> available)</option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="purchase-fields">
                                        <div class="mb-3">
                                            <label class="form-label">Estimated Purchase Cost</label>
                                            <input type="number" class="form-control" name="purchase_cost[]" step="0.01" min="0">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Supplier</label>
                                            <input type="text" class="form-control" name="supplier[]">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm remove-equipment">Remove</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary mb-3" id="addEquipment">Add Equipment</button>
                    
                    <button type="submit" name="add_station_request" class="btn btn-primary">Submit Station Request</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Station Setup Requests</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Station Name</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Estimated Cost</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $station_requests->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                <td><?php echo htmlspecialchars($request['station_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['location']); ?></td>
                                <td><?php echo htmlspecialchars($request['installation_type']); ?></td>
                                <td>$<?php echo number_format($request['total_estimated_cost'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $request['status'] == 'Completed' ? 'success' : 
                                             ($request['status'] == 'Rejected' ? 'danger' : 
                                             ($request['status'] == 'Pending Approval' ? 'warning' : 'info')); 
                                    ?>"><?php echo $request['status']; ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewStation(<?php echo $request['id']; ?>)">View</button>
                                    <?php if ($_SESSION['role'] == 'Manager' && $request['status'] == 'Pending Approval'): ?>
                                        <button class="btn btn-sm btn-success" onclick="approveManager(<?php echo $request['id']; ?>)">Approve</button>
                                    <?php elseif (($_SESSION['role'] == 'Director' || $_SESSION['role'] == 'Super Admin') && $request['status'] == 'Approved'): ?>
                                        <button class="btn btn-sm btn-success" onclick="approveDirector(<?php echo $request['id']; ?>)">Issue Equipment</button>
                                    <?php elseif (hasPermission(['Technician', 'Manager', 'Director', 'Super Admin']) && in_array($request['status'], ['Equipment Issued', 'Installation in Progress'])): ?>
                                        <button class="btn btn-sm btn-info" onclick="updateProgress(<?php echo $request['id']; ?>)">Update Progress</button>
                                    <?php endif; ?>
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

<!-- Modals -->
<div class="modal fade" id="approveManagerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Station Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this station setup request?</p>
                <form method="POST" id="approveManagerForm">
                    <input type="hidden" name="request_id" id="approveManagerRequestId">
                    <button type="submit" name="approve_manager" class="btn btn-success">Yes, Approve</button>
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
                <h5 class="modal-title">Issue Equipment & Approve</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to issue equipment and approve this station setup request?</p>
                <form method="POST" id="approveDirectorForm">
                    <input type="hidden" name="request_id" id="approveDirectorRequestId">
                    <button type="submit" name="approve_director" class="btn btn-success">Yes, Issue Equipment</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="updateProgressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Installation Progress</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="updateProgressForm">
                    <input type="hidden" name="request_id" id="updateProgressRequestId">
                    <div class="mb-3">
                        <label for="progress_status" class="form-label">Status</label>
                        <select class="form-select" id="progress_status" name="progress_status" required>
                            <option value="Installation in Progress">Installation in Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="progress_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="progress_notes" name="progress_notes" rows="3"></textarea>
                    </div>
                    <button type="submit" name="update_progress" class="btn btn-primary">Update Progress</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function viewStation(id) {
    window.location.href = 'station_detail.php?id=' + id;
}

function approveManager(id) {
    document.getElementById('approveManagerRequestId').value = id;
    new bootstrap.Modal(document.getElementById('approveManagerModal')).show();
}

function approveDirector(id) {
    document.getElementById('approveDirectorRequestId').value = id;
    new bootstrap.Modal(document.getElementById('approveDirectorModal')).show();
}

function updateProgress(id) {
    document.getElementById('updateProgressRequestId').value = id;
    new bootstrap.Modal(document.getElementById('updateProgressModal')).show();
}

// Calculate total cost
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('cost-input')) {
        calculateTotal();
    }
});

function calculateTotal() {
    const equipment = parseFloat(document.getElementById('equipment_cost').value) || 0;
    const installation = parseFloat(document.getElementById('installation_cost').value) || 0;
    const transport = parseFloat(document.getElementById('transport_cost').value) || 0;
    const labor = parseFloat(document.getElementById('labor_cost').value) || 0;
    const misc = parseFloat(document.getElementById('misc_cost').value) || 0;
    
    const total = equipment + installation + transport + labor + misc;
    document.getElementById('total_estimated_cost').value = total.toFixed(2);
}

// Equipment management
document.getElementById('addEquipment').addEventListener('click', function() {
    const container = document.getElementById('equipmentContainer');
    const newItem = container.querySelector('.equipment-item').cloneNode(true);
    
    // Clear values
    newItem.querySelectorAll('input').forEach(input => input.value = '');
    newItem.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    
    // Re-bind events
    bindEquipmentEvents(newItem);
    
    container.appendChild(newItem);
});

function bindEquipmentEvents(item) {
    const sourceSelect = item.querySelector('.source-select');
    const inventoryFields = item.querySelector('.inventory-fields');
    const purchaseFields = item.querySelector('.purchase-fields');
    const removeBtn = item.querySelector('.remove-equipment');
    
    sourceSelect.addEventListener('change', function() {
        if (this.value === 'Inventory') {
            inventoryFields.style.display = 'block';
            purchaseFields.style.display = 'none';
        } else {
            inventoryFields.style.display = 'none';
            purchaseFields.style.display = 'block';
        }
    });
    
    removeBtn.addEventListener('click', function() {
        if (document.querySelectorAll('.equipment-item').length > 1) {
            item.remove();
        }
    });
}

// Bind events to initial equipment item
document.querySelectorAll('.equipment-item').forEach(bindEquipmentEvents);

// Initialize total calculation
calculateTotal();
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