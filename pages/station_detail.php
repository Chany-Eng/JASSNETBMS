<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Technician', 'Sales', 'Manager', 'Director', 'Accountant', 'Super Admin']);

$error = '';
$station_request = null;
$equipment_list = null;
$progress_list = null;
$station_costs = null;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = 'Invalid station request ID';
} else {
    $request_id = intval($_GET['id']);
    
    // Fetch station request details
    $stmt = $conn->prepare("SELECT sr.*, u.full_name as requested_by_name, au.full_name as approver_name, au.role as approver_role FROM station_requests sr JOIN users u ON sr.requested_by = u.id LEFT JOIN users au ON sr.approved_by = au.id WHERE sr.id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $station_request = $result->fetch_assoc();
    } else {
        $error = 'Station request not found';
    }
    
    // Fetch equipment list if exists
    if ($station_request) {
        $stmt = $conn->prepare("SELECT se.*, i.item_name as inventory_item FROM station_equipment se LEFT JOIN inventory i ON se.inventory_id = i.id WHERE se.station_request_id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $equipment_list = $stmt->get_result();
        
        // Fetch progress updates
        $stmt = $conn->prepare("SELECT * FROM station_progress WHERE station_request_id = ? ORDER BY date DESC");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $progress_list = $stmt->get_result();
        
        // Fetch station costs if submitted
        $stmt = $conn->prepare("SELECT sc.*, u.full_name as submitted_by_name FROM station_costs sc JOIN users u ON sc.submitted_by = u.id WHERE sc.station_request_id = ? ORDER BY sc.submission_date DESC LIMIT 1");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $station_costs = $stmt->get_result()->fetch_assoc();
    }
}

include '../includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Station Request Details</h2>
                    <a href="stations.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Stations
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($station_request): ?>
            <!-- Station Information Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-map-marker-alt"></i> Station Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Request ID:</strong> #<?php echo htmlspecialchars($station_request['id']); ?></p>
                                    <p><strong>Station Name:</strong> <?php echo htmlspecialchars($station_request['station_name']); ?></p>
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($station_request['location']); ?></p>
                                    <p><strong>GPS Coordinates:</strong> <?php echo htmlspecialchars($station_request['gps'] ?: 'N/A'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Request Date:</strong> <?php echo date('M d, Y', strtotime($station_request['request_date'])); ?></p>
                                    <p><strong>Requested By:</strong> <?php echo htmlspecialchars($station_request['requested_by_name']); ?></p>
                                    <p><strong>Installation Type:</strong> 
                                        <span class="badge bg-info"><?php echo htmlspecialchars($station_request['installation_type']); ?></span>
                                    </p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-<?php 
                                            echo ($station_request['status'] == 'Completed') ? 'success' : 
                                                 (($station_request['status'] == 'Rejected') ? 'danger' : 
                                                 (($station_request['status'] == 'Pending Approval') ? 'warning' :
                                                 (($station_request['status'] == 'Awaiting Accountant Approval') ? 'secondary' :
                                                 (($station_request['status'] == 'Approved') ? 'primary' : 'info')))); 
                                        ?>">
                                            <?php echo htmlspecialchars($station_request['status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Coverage Area:</strong></p>
                                    <p><?php echo htmlspecialchars($station_request['coverage_area'] ?: 'N/A'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Total Estimated Cost:</strong></p>
                                    <p class="text-primary font-weight-bold">
                                        Tshs. <?php echo number_format($station_request['total_estimated_cost'], 2); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <p><strong>Description:</strong></p>
                                    <p><?php echo htmlspecialchars($station_request['description']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Equipment Requirements Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-tools"></i> Equipment Requirements
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($equipment_list && $equipment_list->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Equipment Name</th>
                                                <th>Quantity</th>
                                                <th>Source</th>
                                                <th>Supplier / Inventory Item</th>
                                                <th>Unit Cost (Tshs.)</th>
                                                <th>Total Cost (Tshs.)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = $equipment_list->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['equipment_name']); ?></td>
                                                <td><?php echo intval($row['quantity']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['source'] == 'Inventory' ? 'success' : 'warning'; ?>">
                                                        <?php echo htmlspecialchars($row['source']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                        if ($row['source'] == 'Inventory' && $row['inventory_item']) {
                                                            echo htmlspecialchars($row['inventory_item']);
                                                        } else {
                                                            echo htmlspecialchars($row['supplier'] ?: 'N/A');
                                                        }
                                                    ?>
                                                </td>
                                                <td>Tshs. <?php echo number_format($row['purchase_cost'], 2); ?></td>
                                                <td class="font-weight-bold">Tshs. <?php echo number_format($row['purchase_cost'] * $row['quantity'], 2); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No equipment requirements added</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actual Costs Card (if submitted) -->
            <?php if ($station_costs): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-calculator"></i> Actual Costs Submitted
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Submitted By:</strong> <?php echo htmlspecialchars($station_costs['submitted_by_name']); ?></p>
                                    <p><strong>Submission Date:</strong> <?php echo date('M d, Y \a\t h:i A', strtotime($station_costs['submission_date'])); ?></p>
                                    <p><strong>Equipment Cost:</strong> Tshs. <?php echo number_format($station_costs['actual_equipment_cost'], 2); ?></p>
                                    <p><strong>Installation Cost:</strong> Tshs. <?php echo number_format($station_costs['actual_installation_cost'], 2); ?></p>
                                    <p><strong>Transport Cost:</strong> Tshs. <?php echo number_format($station_costs['actual_transport_cost'], 2); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Labor Cost:</strong> Tshs. <?php echo number_format($station_costs['actual_labor_cost'], 2); ?></p>
                                    <p><strong>Miscellaneous Cost:</strong> Tshs. <?php echo number_format($station_costs['actual_misc_cost'], 2); ?></p>
                                    <p><strong><span class="text-primary">Total Actual Cost:</span></strong> <span class="text-primary font-weight-bold">Tshs. <?php echo number_format($station_costs['total_actual_cost'], 2); ?></span></p>
                                    <?php if ($station_costs['receipt_file']): ?>
                                        <p><strong>Receipt:</strong> <a href="../uploads/<?php echo htmlspecialchars($station_costs['receipt_file']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Receipt</a></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($station_costs['cost_notes']): ?>
                                <div class="row">
                                    <div class="col-12">
                                        <p><strong>Cost Notes:</strong></p>
                                        <p><?php echo htmlspecialchars($station_costs['cost_notes']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($station_costs['approval_notes']): ?>
                                <div class="row">
                                    <div class="col-12">
                                        <hr>
                                        <p><strong>Accountant Review:</strong></p>
                                        <p><?php echo htmlspecialchars($station_costs['approval_notes']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Approval Status Card (if awaiting accountant approval) -->
            <?php if ($station_request['status'] == 'Awaiting Accountant Approval'): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-hourglass-half"></i> Awaiting Accountant Approval
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">This station's receipt and costs are pending review by the Accountant. Once approved, equipment will be issued and installation can begin.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Progress Updates Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-tasks"></i> Progress Updates
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($progress_list && $progress_list->num_rows > 0): ?>
                                <div class="timeline">
                                    <?php while ($row = $progress_list->fetch_assoc()): ?>
                                    <div class="timeline-item mb-4 pb-3 border-bottom">
                                        <div class="d-flex">
                                            <div class="timeline-marker bg-primary rounded-circle" style="width: 40px; height: 40px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: white;">
                                                <i class="fas fa-check"></i>
                                            </div>
                                            <div class="ms-3 flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($row['status']); ?></h6>
                                                <p class="text-muted mb-2">
                                                    <small><?php echo date('M d, Y \a\t h:i A', strtotime($row['date'])); ?></small>
                                                </p>
                                                <?php if ($row['notes']): ?>
                                                    <p class="mb-0"><?php echo htmlspecialchars($row['notes']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No progress updates yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approval Status Card (if approved) -->
            <?php if ($station_request['approved_by']): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-check-circle"></i> Approval Status
                            </h5>
                        </div>
                        <div class="card-body">
                            <p><i class="fas fa-check-circle text-success"></i> <strong>Approved</strong> by <?php echo htmlspecialchars($station_request['approver_name'] ?: 'Unknown'); ?> (<?php echo htmlspecialchars($station_request['approver_role'] ?: 'Unknown Role'); ?>)</p>
                            <?php if ($station_request['status'] == 'Equipment Issued'): ?>
                                <p><i class="fas fa-tools text-info"></i> <strong>Equipment Issued</strong> - Ready for installation</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<style>
.timeline-marker {
    flex-shrink: 0;
}

.timeline-item:last-child {
    border-bottom: none !important;
}
</style>

<?php include '../includes/footer.php'; ?>
