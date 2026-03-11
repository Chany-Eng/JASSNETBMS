<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

// page accessible to relevant staff; addition restricted below
requirePermission(['Technician', 'Sales', 'Manager', 'Director', 'Accountant', 'Super Admin']);

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
                        $inventory_id = isset($_POST['inventory_id'][$i]) && !empty($_POST['inventory_id'][$i]) ? intval($_POST['inventory_id'][$i]) : null;
                        $purchase_cost = floatval($_POST['purchase_cost'][$i]);
                        $supplier = sanitize($_POST['supplier'][$i]);
                        
                        // Only validate inventory_id if source is 'Inventory'
                        if ($source == 'Inventory' && !empty($inventory_id)) {
                            // Check if inventory item exists
                            $check_stmt = $conn->prepare("SELECT id FROM inventory WHERE id = ?");
                            $check_stmt->bind_param("i", $inventory_id);
                            $check_stmt->execute();
                            $check_result = $check_stmt->get_result();
                            if ($check_result->num_rows == 0) {
                                $inventory_id = null; // Set to null if inventory doesn't exist
                            }
                        } else if ($source == 'Purchase') {
                            $inventory_id = null; // Purchase items don't have inventory ID
                        }
                        
                        $stmt2 = $conn->prepare("INSERT INTO station_equipment (station_request_id, equipment_name, quantity, source, inventory_id, purchase_cost, supplier) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt2->bind_param("isisdss", $station_id, $equipment_name, $quantity, $source, $inventory_id, $purchase_cost, $supplier);
                        if (!$stmt2->execute()) {
                            error_log("Error inserting equipment: " . $stmt2->error);
                        }
                    }
                }
                
                $_SESSION['success_message'] = 'Station setup request submitted successfully';
                header("Location: stations.php");
                exit();
            } else {
                $error = 'Error submitting station request';
            }
        }
    } elseif (isset($_POST['approve_superadmin'])) {
        if (!hasPermission(['Super Admin'])) {
            $error = 'You are not authorized to approve station requests';
        } else {
            $request_id = intval($_POST['request_id']);
            $stmt = $conn->prepare("UPDATE station_requests SET status = 'Approved', approved_by = ? WHERE id = ? AND status = 'Pending Approval'");
            $stmt->bind_param("ii", $_SESSION['user_id'], $request_id);
            $stmt->execute();
            
            $message = 'Station request approved by Super Admin. Requester can now submit actual costs and receipts.';
        }
    } elseif (isset($_POST['submit_costs'])) {
        $request_id = intval($_POST['request_id']);
        
        // Check if user is the requester
        $check_stmt = $conn->prepare("SELECT requested_by FROM station_requests WHERE id = ? AND status = 'Approved'");
        $check_stmt->bind_param("i", $request_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $request_data = $check_result->fetch_assoc();
        
        if (!$request_data || $request_data['requested_by'] != $_SESSION['user_id']) {
            $error = 'You can only submit costs for your approved station requests';
        } else {
            $actual_equipment_cost = floatval($_POST['actual_equipment_cost']);
            $actual_installation_cost = floatval($_POST['actual_installation_cost']);
            $actual_transport_cost = floatval($_POST['actual_transport_cost']);
            $actual_labor_cost = floatval($_POST['actual_labor_cost']);
            $actual_misc_cost = floatval($_POST['actual_misc_cost']);
            $total_actual_cost = $actual_equipment_cost + $actual_installation_cost + $actual_transport_cost + $actual_labor_cost + $actual_misc_cost;
            $cost_notes = sanitize($_POST['cost_notes']);
            
            $receipt_file = '';
            if (isset($_FILES['cost_receipt']) && $_FILES['cost_receipt']['error'] == 0) {
                $upload_result = uploadFile($_FILES['cost_receipt']);
                if (isset($upload_result['success'])) {
                    $receipt_file = $upload_result['success'];
                } else {
                    $error = $upload_result['error'];
                }
            }
            
            if (!$error) {
                $stmt = $conn->prepare("INSERT INTO station_costs (station_request_id, actual_equipment_cost, actual_installation_cost, actual_transport_cost, actual_labor_cost, actual_misc_cost, total_actual_cost, cost_notes, receipt_file, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("idddddsssi", $request_id, $actual_equipment_cost, $actual_installation_cost, $actual_transport_cost, $actual_labor_cost, $actual_misc_cost, $total_actual_cost, $cost_notes, $receipt_file, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    // Update station status to Awaiting Accountant Approval
                    $conn->query("UPDATE station_requests SET status = 'Awaiting Accountant Approval' WHERE id = $request_id");
                    $message = 'Receipt and costs submitted successfully. Awaiting Accountant approval.';
                } else {
                    $error = 'Error submitting costs';
                }
            }
        }
    } elseif (isset($_POST['approve_costs_accountant'])) {
        if (!hasPermission(['Accountant'])) {
            $error = 'You are not authorized to approve station costs';
        } else {
            $request_id = intval($_POST['request_id']);
            $approval_notes = sanitize($_POST['approval_notes'] ?? '');
            
            // Get requester phone number
            $requester_stmt = $conn->prepare("SELECT sr.requested_by, u.phone, sr.station_name FROM station_requests sr JOIN users u ON sr.requested_by = u.id WHERE sr.id = ?");
            $requester_stmt->bind_param("i", $request_id);
            $requester_stmt->execute();
            $requester_result = $requester_stmt->get_result();
            $requester_data = $requester_result->fetch_assoc();
            
            // Update station status to Equipment Issued
            $conn->query("UPDATE station_requests SET status = 'Equipment Issued' WHERE id = $request_id AND status = 'Awaiting Accountant Approval'");
            
            // Reserve equipment from inventory
            $equipment_result = $conn->query("SELECT * FROM station_equipment WHERE station_request_id = $request_id AND source = 'Inventory'");
            while ($equipment = $equipment_result->fetch_assoc()) {
                $inventory_id = $equipment['inventory_id'];
                $quantity = $equipment['quantity'];
                $conn->query("UPDATE inventory SET quantity = quantity - $quantity WHERE id = $inventory_id");
            }
            
            // Update station_costs with approval
            $conn->query("UPDATE station_costs SET approval_notes = '$approval_notes' WHERE station_request_id = $request_id");
            
            // Send SMS to requester if phone is available
            if ($requester_data && !empty($requester_data['phone'])) {
                $smsMsg = "Jamii salama! Your station '" . htmlspecialchars($requester_data['station_name']) . "' receipt and costs have been APPROVED by Accountant. Equipment is now issued. Installation can begin.";
                jassnet_sms($requester_data['phone'], $smsMsg);
            }
            
            $message = 'Receipt and costs approved by Accountant. Equipment has been issued and installation can begin. SMS notification sent to requester.';
        }
    } elseif (isset($_POST['reject_costs_accountant'])) {
        if (!hasPermission(['Accountant'])) {
            $error = 'You are not authorized to reject station costs';
        } else {
            $request_id = intval($_POST['request_id']);
            $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');
            
            // Get requester phone number
            $requester_stmt = $conn->prepare("SELECT sr.requested_by, u.phone, sr.station_name FROM station_requests sr JOIN users u ON sr.requested_by = u.id WHERE sr.id = ?");
            $requester_stmt->bind_param("i", $request_id);
            $requester_stmt->execute();
            $requester_result = $requester_stmt->get_result();
            $requester_data = $requester_result->fetch_assoc();
            
            // Update station status back to Approved
            $conn->query("UPDATE station_requests SET status = 'Approved' WHERE id = $request_id AND status = 'Awaiting Accountant Approval'");
            
            // Update station_costs with rejection reason
            $conn->query("UPDATE station_costs SET approval_notes = 'REJECTED: $rejection_reason' WHERE station_request_id = $request_id");
            
            // Send SMS to requester if phone is available
            if ($requester_data && !empty($requester_data['phone'])) {
                $smsMsg = "Jamii salama! Your station '" . htmlspecialchars($requester_data['station_name']) . "' receipt and costs have been REJECTED by Accountant. Reason: " . htmlspecialchars($rejection_reason) . " Please resubmit with corrections.";
                jassnet_sms($requester_data['phone'], $smsMsg);
            }
            
            $message = 'Receipt and costs rejected. Requester has been notified to resubmit. SMS notification sent to requester.';
        }
    } elseif (isset($_POST['update_progress'])) {
        $request_id = intval($_POST['request_id']);
        $status = sanitize($_POST['progress_status']);
        $notes = sanitize($_POST['progress_notes']);
        
        // Only allow progress updates for Equipment Issued status
        $check_stmt = $conn->prepare("SELECT status FROM station_requests WHERE id = ?");
        $check_stmt->bind_param("i", $request_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $current_status = $check_result->fetch_assoc()['status'];
        
        if ($current_status != 'Equipment Issued') {
            $error = 'Progress can only be updated after equipment is issued';
        } else {
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
    } elseif (isset($_POST['complete_station'])) {
        $request_id = intval($_POST['request_id']);
        
        // Check if user is the requester
        $check_stmt = $conn->prepare("SELECT requested_by FROM station_requests WHERE id = ?");
        $check_stmt->bind_param("i", $request_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $request_data = $check_result->fetch_assoc();
        
        if ($request_data['requested_by'] != $_SESSION['user_id']) {
            $error = 'You can only complete stations you requested';
        } else {
            $completion_date = sanitize($_POST['completion_date']);
            $actual_equipment_cost = floatval($_POST['actual_equipment_cost']);
            $actual_installation_cost = floatval($_POST['actual_installation_cost']);
            $actual_transport_cost = floatval($_POST['actual_transport_cost']);
            $actual_labor_cost = floatval($_POST['actual_labor_cost']);
            $actual_misc_cost = floatval($_POST['actual_misc_cost']);
            $total_actual_cost = $actual_equipment_cost + $actual_installation_cost + $actual_transport_cost + $actual_labor_cost + $actual_misc_cost;
            $completion_notes = sanitize($_POST['completion_notes']);
            
            $receipt_file = '';
            if (isset($_FILES['completion_receipt']) && $_FILES['completion_receipt']['error'] == 0) {
                $upload_result = uploadFile($_FILES['completion_receipt']);
                if (isset($upload_result['success'])) {
                    $receipt_file = $upload_result['success'];
                } else {
                    $error = $upload_result['error'];
                }
            }
            
            if (!$error) {
                $stmt = $conn->prepare("INSERT INTO station_completion (station_request_id, completion_date, actual_equipment_cost, actual_installation_cost, actual_transport_cost, actual_labor_cost, actual_misc_cost, total_actual_cost, completion_notes, receipt_file, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isdddddddss", $request_id, $completion_date, $actual_equipment_cost, $actual_installation_cost, $actual_transport_cost, $actual_labor_cost, $actual_misc_cost, $total_actual_cost, $completion_notes, $receipt_file, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    // Update station status to completed
                    $conn->query("UPDATE station_requests SET status = 'Completed' WHERE id = $request_id");
                    $message = 'Station completion data submitted successfully';
                } else {
                    $error = 'Error submitting completion data';
                }
            }
        }
    }
}

// Get station requests based on user role
$where_clause = '';
if ($_SESSION['role'] == 'Sales' || $_SESSION['role'] == 'Technician') {
    $where_clause = "WHERE sr.requested_by = {$_SESSION['user_id']}";
} elseif ($_SESSION['role'] == 'Manager' || $_SESSION['role'] == 'Director') {
    $where_clause = "WHERE sr.status IN ('Pending Approval', 'Approved', 'Awaiting Accountant Approval', 'Equipment Issued', 'Installation in Progress', 'Completed', 'Rejected')";
} elseif ($_SESSION['role'] == 'Accountant') {
    $where_clause = "WHERE sr.status IN ('Awaiting Accountant Approval', 'Equipment Issued', 'Installation in Progress', 'Completed')";
}

$station_requests = $conn->query("SELECT sr.*, u.full_name as requested_by_name, au.full_name as approver_name, au.role as approver_role FROM station_requests sr JOIN users u ON sr.requested_by = u.id LEFT JOIN users au ON sr.approved_by = au.id $where_clause ORDER BY request_date DESC");

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
                                <td>Tshs. <?php echo number_format($request['total_estimated_cost'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $request['status'] == 'Completed' ? 'success' : 
                                             ($request['status'] == 'Rejected' ? 'danger' : 
                                             ($request['status'] == 'Pending Approval' ? 'warning' : 
                                             ($request['status'] == 'Awaiting Accountant Approval' ? 'secondary' :
                                             ($request['status'] == 'Approved' ? 'primary' : 'info')))); 
                                    ?>"><?php echo $request['status']; ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewStation(<?php echo $request['id']; ?>)">View</button>
                                    <?php if ($_SESSION['role'] == 'Super Admin' && $request['status'] == 'Pending Approval'): ?>
                                        <button class="btn btn-sm btn-success" onclick="approveSuperAdmin(<?php echo $request['id']; ?>)">Approve</button>
                                    <?php elseif ($request['requested_by'] == $_SESSION['user_id'] && $request['status'] == 'Approved'): ?>
                                        <button class="btn btn-sm btn-warning" onclick="submitCosts(<?php echo $request['id']; ?>)">Submit Costs</button>
                                    <?php elseif (hasPermission(['Accountant']) && $request['status'] == 'Awaiting Accountant Approval'): ?>
                                        <button class="btn btn-sm btn-success" onclick="approveCostsAccountant(<?php echo $request['id']; ?>)">Approve</button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectCostsAccountant(<?php echo $request['id']; ?>)">Reject</button>
                                    <?php elseif (hasPermission(['Accountant']) && in_array($request['status'], ['Equipment Issued', 'Installation in Progress', 'Completed'])): ?>
                                        <button class="btn btn-sm btn-info" onclick="viewReceipt(<?php echo $request['id']; ?>)">View Receipt</button>
                                    <?php elseif (hasPermission(['Technician', 'Manager', 'Director', 'Super Admin']) && in_array($request['status'], ['Equipment Issued', 'Installation in Progress'])): ?>
                                        <button class="btn btn-sm btn-info" onclick="updateProgress(<?php echo $request['id']; ?>)">Update Progress</button>
                                    <?php elseif ($request['requested_by'] == $_SESSION['user_id'] && $request['status'] == 'Installation in Progress'): ?>
                                        <button class="btn btn-sm btn-success" onclick="completeStation(<?php echo $request['id']; ?>)">Complete Station</button>
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
<div class="modal fade" id="approveSuperAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Station Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this station setup request? This will issue equipment and mark the request as ready for installation.</p>
                <form method="POST" id="approveSuperAdminForm">
                    <input type="hidden" name="request_id" id="approveSuperAdminRequestId">
                    <button type="submit" name="approve_superadmin" class="btn btn-success">Yes, Approve</button>
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

<div class="modal fade" id="submitCostsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Actual Costs and Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="submitCostsForm">
                    <input type="hidden" name="request_id" id="submitCostsRequestId">
                    <p>Please provide the actual costs incurred and upload the receipt for this station setup.</p>
                    
                    <h6>Actual Costs</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="actual_equipment_cost" class="form-label">Equipment Cost</label>
                                <input type="number" class="form-control actual-cost-input" id="actual_equipment_cost" name="actual_equipment_cost" step="0.01" min="0" value="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="actual_installation_cost" class="form-label">Installation Cost</label>
                                <input type="number" class="form-control actual-cost-input" id="actual_installation_cost" name="actual_installation_cost" step="0.01" min="0" value="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="actual_transport_cost" class="form-label">Transport Cost</label>
                                <input type="number" class="form-control actual-cost-input" id="actual_transport_cost" name="actual_transport_cost" step="0.01" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="actual_labor_cost" class="form-label">Labor Cost</label>
                                <input type="number" class="form-control actual-cost-input" id="actual_labor_cost" name="actual_labor_cost" step="0.01" min="0" value="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="actual_misc_cost" class="form-label">Miscellaneous Cost</label>
                                <input type="number" class="form-control actual-cost-input" id="actual_misc_cost" name="actual_misc_cost" step="0.01" min="0" value="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="total_actual_cost" class="form-label">Total Actual Cost</label>
                                <input type="number" class="form-control" id="total_actual_cost" name="total_actual_cost" step="0.01" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cost_notes" class="form-label">Cost Notes</label>
                        <textarea class="form-control" id="cost_notes" name="cost_notes" rows="3" placeholder="Explain any variances from estimated costs..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cost_receipt" class="form-label">Receipt File</label>
                        <input type="file" class="form-control" id="cost_receipt" name="cost_receipt" accept="image/*,.pdf" required>
                        <small class="form-text text-muted">Upload receipt or invoice (PDF or image)</small>
                    </div>
                    
                    <button type="submit" name="submit_costs" class="btn btn-primary">Submit Costs</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Receipt Modal -->
<div class="modal fade" id="viewReceiptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Station Receipt & Costs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="receiptDetails">
                    <p class="text-muted">Loading receipt details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Costs Modal -->
<div class="modal fade" id="approveCostsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Station Costs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Review the submitted receipt and costs. Are they correct?</p>
                <form method="POST" id="approveCostsForm">
                    <input type="hidden" name="request_id" id="approveCostsRequestId">
                    <div class="mb-3">
                        <label for="approval_notes" class="form-label">Approval Notes (Optional)</label>
                        <textarea class="form-control" id="approval_notes" name="approval_notes" rows="3" placeholder="Add any notes about the approval..."></textarea>
                    </div>
                    <button type="submit" name="approve_costs_accountant" class="btn btn-success">Approve & Issue Equipment</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Costs Modal -->
<div class="modal fade" id="rejectCostsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Station Costs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Please provide a reason for rejecting these costs.</p>
                <form method="POST" id="rejectCostsForm">
                    <input type="hidden" name="request_id" id="rejectCostsRequestId">
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Rejection Reason *</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" placeholder="Explain why these costs are being rejected..." required></textarea>
                    </div>
                    <button type="submit" name="reject_costs_accountant" class="btn btn-danger">Reject & Return to Requester</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function viewStation(id) {
    window.location.href = 'station_detail.php?id=' + id;
}

function approveSuperAdmin(id) {
    document.getElementById('approveSuperAdminRequestId').value = id;
    new bootstrap.Modal(document.getElementById('approveSuperAdminModal')).show();
}

function updateProgress(id) {
    document.getElementById('updateProgressRequestId').value = id;
    new bootstrap.Modal(document.getElementById('updateProgressModal')).show();
}

function submitCosts(id) {
    document.getElementById('submitCostsRequestId').value = id;
    new bootstrap.Modal(document.getElementById('submitCostsModal')).show();
}

function viewReceipt(id) {
    window.location.href = 'station_detail.php?id=' + id + '#costs-section';
}

function approveCostsAccountant(id) {
    document.getElementById('approveCostsRequestId').value = id;
    new bootstrap.Modal(document.getElementById('approveCostsModal')).show();
}

function rejectCostsAccountant(id) {
    document.getElementById('rejectCostsRequestId').value = id;
    new bootstrap.Modal(document.getElementById('rejectCostsModal')).show();
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

// Calculate actual total cost
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('actual-cost-input')) {
        calculateActualTotal();
    }
});

function calculateActualTotal() {
    const equipment = parseFloat(document.getElementById('actual_equipment_cost').value) || 0;
    const installation = parseFloat(document.getElementById('actual_installation_cost').value) || 0;
    const transport = parseFloat(document.getElementById('actual_transport_cost').value) || 0;
    const labor = parseFloat(document.getElementById('actual_labor_cost').value) || 0;
    const misc = parseFloat(document.getElementById('actual_misc_cost').value) || 0;
    
    const total = equipment + installation + transport + labor + misc;
    document.getElementById('total_actual_cost').value = total.toFixed(2);
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