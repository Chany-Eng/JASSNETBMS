<?php
require_once '../includes/functions.php';
require_once '../includes/station_workflow.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

requirePermission(['Technician']);
stationEnsureWorkflowSchema($conn);
ensureInventorySoftDeleteSchema($conn);

$message = '';
$error = '';
$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_station_request'])) {
    $station_name = sanitize($_POST['station_name'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $gps = sanitize($_POST['gps'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $coverage_area = sanitize($_POST['coverage_area'] ?? '');
    $installation_type = sanitize($_POST['installation_type'] ?? '');

    $equipment_cost = floatval($_POST['equipment_cost'] ?? 0);
    $installation_cost = floatval($_POST['installation_cost'] ?? 0);
    $transport_cost = floatval($_POST['transport_cost'] ?? 0);
    $labor_cost = floatval($_POST['labor_cost'] ?? 0);
    $misc_cost = floatval($_POST['misc_cost'] ?? 0);
    $total_estimated_cost = $equipment_cost + $installation_cost + $transport_cost + $labor_cost + $misc_cost;

    $status = 'Pending Manager Approval';
    $stmt = $conn->prepare('INSERT INTO station_requests (requested_by, station_name, location, gps, description, coverage_area, installation_type, total_estimated_cost, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('issssssds', $_SESSION['user_id'], $station_name, $location, $gps, $description, $coverage_area, $installation_type, $total_estimated_cost, $status);

        if ($stmt->execute()) {
            $station_id = $conn->insert_id;
            appLogActivity($conn, 'CREATE_STATION_REQUEST', 'Submitted station request for ' . $station_name, 'station_requests', $station_id);
            $requester = getCurrentUser();
            if ($requester) {
                appSendSmsToUser($requester, 'ERMS: Station request #' . $station_id . ' ya ' . $station_name . ' imepokelewa. Inasubiri Manager approval.');
            }
            appSendSmsToRoles($conn, ['Manager'], 'ERMS: Station request #' . $station_id . ' ya ' . $station_name . ' kutoka ' . trim((string) ($requester['full_name'] ?? 'Technician')) . ' inasubiri approval yako.', [(int) ($_SESSION['user_id'] ?? 0)]);

            if (isset($_POST['equipment_name']) && is_array($_POST['equipment_name'])) {
                $count = count($_POST['equipment_name']);
                for ($i = 0; $i < $count; $i++) {
                    $equipment_name = sanitize($_POST['equipment_name'][$i] ?? '');
                    $quantity = intval($_POST['equipment_quantity'][$i] ?? 0);
                    $source = sanitize($_POST['equipment_source'][$i] ?? 'Inventory');
                    $inventory_id = !empty($_POST['inventory_id'][$i]) ? intval($_POST['inventory_id'][$i]) : null;
                    $purchase_cost = floatval($_POST['purchase_cost'][$i] ?? 0);
                    $supplier = sanitize($_POST['supplier'][$i] ?? '');

                    if ($source === 'Inventory' && !empty($inventory_id)) {
                        $check_stmt = $conn->prepare('SELECT id FROM inventory WHERE id = ? AND COALESCE(is_deleted, 0) = 0');
                        if ($check_stmt) {
                            $check_stmt->bind_param('i', $inventory_id);
                            $check_stmt->execute();
                            $check_result = $check_stmt->get_result();
                            if ($check_result->num_rows === 0) {
                                $inventory_id = null;
                            }
                        }
                    } else {
                        $inventory_id = null;
                    }

                    $stmt2 = $conn->prepare('INSERT INTO station_equipment (station_request_id, equipment_name, quantity, source, inventory_id, purchase_cost, supplier) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    if ($stmt2) {
                        $stmt2->bind_param('isisids', $station_id, $equipment_name, $quantity, $source, $inventory_id, $purchase_cost, $supplier);
                        if (!$stmt2->execute()) {
                            error_log('Error inserting equipment: ' . $stmt2->error);
                        }
                    }
                }
            }

            $_SESSION['success_message'] = 'Station setup request submitted successfully';
            header('Location: stations.php');
            exit();
        } else {
            $error = 'Error submitting station request';
        }
    } else {
        $error = 'Could not prepare station request query';
    }
}

$inventory_items = $conn->query('SELECT id, item_name, quantity FROM inventory WHERE quantity > 0 AND COALESCE(is_deleted, 0) = 0 ORDER BY item_name');
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

<div class="row mb-4" id="request-new-station-setup">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-plus"></i> Request New Station Setup</h2>
        <a href="stations.php#station-setup-requests" class="btn btn-outline-secondary">
            <i class="fas fa-list"></i> Back to Station Setup Requests
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><?php echo htmlspecialchars(formatSuccessMessage($message)); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-plus"></i> New Station Request Form</h5>
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
                                            <?php if ($inventory_items): ?>
                                                <?php while ($item = $inventory_items->fetch_assoc()): ?>
                                                    <option value="<?php echo (int) $item['id']; ?>"><?php echo htmlspecialchars($item['item_name']); ?> (<?php echo (int) $item['quantity']; ?> available)</option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
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

<script>
function calculateTotal() {
    const equipment = parseFloat(document.getElementById('equipment_cost').value) || 0;
    const installation = parseFloat(document.getElementById('installation_cost').value) || 0;
    const transport = parseFloat(document.getElementById('transport_cost').value) || 0;
    const labor = parseFloat(document.getElementById('labor_cost').value) || 0;
    const misc = parseFloat(document.getElementById('misc_cost').value) || 0;
    document.getElementById('total_estimated_cost').value = (equipment + installation + transport + labor + misc).toFixed(2);
}

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('cost-input')) {
        calculateTotal();
    }
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

document.getElementById('addEquipment').addEventListener('click', function() {
    const container = document.getElementById('equipmentContainer');
    const newItem = container.querySelector('.equipment-item').cloneNode(true);
    newItem.querySelectorAll('input').forEach(input => input.value = '');
    newItem.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    newItem.querySelector('.inventory-fields').style.display = 'none';
    newItem.querySelector('.purchase-fields').style.display = 'block';
    bindEquipmentEvents(newItem);
    container.appendChild(newItem);
});

document.querySelectorAll('.equipment-item').forEach(bindEquipmentEvents);
calculateTotal();

<?php if ($success_message): ?>
document.addEventListener('DOMContentLoaded', function() {
    const toast = new bootstrap.Toast(document.getElementById('successToast'), { delay: 5000 });
    toast.show();
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>