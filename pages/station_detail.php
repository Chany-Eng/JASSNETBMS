<?php
require_once '../includes/functions.php';
require_once '../includes/station_workflow.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Technician', 'Sales', 'Manager', 'Director', 'Accountant', 'Store Keeper', 'Super Admin']);
stationEnsureWorkflowSchema($conn);
snippeEnsurePayoutTables($conn);
$defaultStationPayoutChannel = 'mobile';

$error = '';
$station_request = null;
$equipment_list = null;
$progress_list = null;
$station_costs = null;
$station_payout = null;
$station_payouts = null;
$station_payout_summary = [
    'total_actual_cost' => 0.0,
    'total_paid' => 0.0,
    'total_in_flight' => 0.0,
    'remaining_balance' => 0.0,
    'has_active_payout' => false,
    'has_costs' => false,
    'latest_payout' => null,
    'latest_payout_status' => '',
    'latest_failure_reason' => '',
    'can_finalize' => false,
    'next_status' => 'Awaiting Accountant Approval',
];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = 'Invalid station request ID';
} else {
    $request_id = intval($_GET['id']);
    
    // Fetch station request details
    $stmt = $conn->prepare("SELECT sr.*, u.full_name as requested_by_name, u.preferred_payout_channel as requester_payout_channel, au.full_name as approver_name, au.role as approver_role, mu.full_name as manager_name, du.full_name as director_name, acu.full_name as accountant_name, sku.full_name as storekeeper_name FROM station_requests sr JOIN users u ON sr.requested_by = u.id LEFT JOIN users au ON sr.approved_by = au.id LEFT JOIN users mu ON sr.manager_approved_by = mu.id LEFT JOIN users du ON sr.director_approved_by = du.id LEFT JOIN users acu ON sr.accountant_approved_by = acu.id LEFT JOIN users sku ON sr.storekeeper_approved_by = sku.id WHERE sr.id = ?");
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

        $stmt = $conn->prepare("SELECT * FROM snippe_payouts WHERE station_request_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $station_payout = $stmt->get_result()->fetch_assoc();

        $stmt = $conn->prepare("SELECT * FROM snippe_payouts WHERE station_request_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $station_payouts = $stmt->get_result();

        $station_payout_summary = stationGetPayoutSummary($conn, $request_id);
        if ($station_payout_summary['latest_payout']) {
            $station_payout = $station_payout_summary['latest_payout'];
        }
        $defaultStationPayoutChannel = snippeNormalizePayoutChannel((string) (($station_payout['payout_channel'] ?? '') !== '' ? $station_payout['payout_channel'] : ($station_request['requester_payout_channel'] ?? 'mobile')));
    }
}

$snippeMinimumPayoutAmount = snippeGetMinimumPayoutAmount($defaultStationPayoutChannel);

include '../includes/header.php';
?>

<div class="container-fluid py-4 detail-page detail-page-stack">
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
            <?php if ($station_request['status'] === 'Rejected'): ?>
            <div class="alert alert-danger border-0 shadow-sm">
                <strong>Station request rejected.</strong> This request is currently stopped. Review the rejection context and edit or recreate the request before sending it through the workflow again.
            </div>
            <?php endif; ?>

            <!-- Station Information Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card page-shell-card">
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
                                        <span class="badge bg-<?php echo stationStatusBadgeClass((string) $station_request['status']); ?>">
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
                    <div class="card page-shell-card">
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
                    <div class="card page-shell-card">
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

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card page-shell-card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-user-check"></i> Approval Workflow
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <strong>Manager:</strong><br>
                                    <?php echo htmlspecialchars(stationApprovalLabel($station_request['manager_name'] ?? null)); ?>
                                    <?php if (!empty($station_request['manager_comment'])): ?>
                                        <div class="small text-muted mt-1"><?php echo nl2br(htmlspecialchars($station_request['manager_comment'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Director:</strong><br>
                                    <?php echo htmlspecialchars(stationApprovalLabel($station_request['director_name'] ?? null)); ?>
                                    <?php if (!empty($station_request['director_comment'])): ?>
                                        <div class="small text-muted mt-1"><?php echo nl2br(htmlspecialchars($station_request['director_comment'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Accountant:</strong><br>
                                    <?php echo htmlspecialchars(stationApprovalLabel($station_request['accountant_name'] ?? null)); ?>
                                    <?php if (!empty($station_request['accountant_comment'])): ?>
                                        <div class="small text-muted mt-1"><?php echo nl2br(htmlspecialchars($station_request['accountant_comment'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Store Keeper:</strong><br>
                                    <?php echo htmlspecialchars(stationApprovalLabel($station_request['storekeeper_name'] ?? null)); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($station_request['status'] == 'Awaiting Accountant Approval'): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card page-shell-card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-hourglass-half"></i> Awaiting Accountant Approval
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($station_payout_summary['latest_payout_status']) && in_array($station_payout_summary['latest_payout_status'], ['failed', 'cancelled', 'canceled'], true)): ?>
                                <p class="mb-2 text-danger"><strong>Latest payout failed.</strong> The request remains with Accountant until another payout attempt succeeds.</p>
                                <p class="mb-0"><strong>Failure reason:</strong> <?php echo htmlspecialchars($station_payout_summary['latest_failure_reason'] ?: 'No reason returned by Snippe'); ?></p>
                            <?php elseif (!empty($station_payout_summary['has_active_payout'])): ?>
                                <p class="mb-0">A payout has already been submitted to Snippe and is still waiting for confirmation. The request remains with Accountant until Snippe confirms completion.</p>
                            <?php elseif ((float) $station_payout_summary['remaining_balance'] > 0 && (float) $station_payout_summary['total_paid'] > 0): ?>
                                <p class="mb-0">Partial payout has been completed. Remaining balance of Tshs. <?php echo number_format((float) $station_payout_summary['remaining_balance'], 2); ?> must still be paid before the workflow can continue.</p>
                                <?php if ((float) $station_payout_summary['remaining_balance'] < $snippeMinimumPayoutAmount): ?>
                                    <p class="mt-2 mb-0 text-danger">This remaining balance is below Snippe minimum payout for <?php echo $defaultStationPayoutChannel === 'bank' ? 'bank transfer' : 'mobile money'; ?> of Tshs. <?php echo number_format((float) $snippeMinimumPayoutAmount, 2); ?>, so it must be settled manually or combined before another Snippe payout attempt.</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="mb-0">This station's receipt and costs are pending review by the Accountant. Once payout is fully completed, equipment will be issued and installation can begin.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($station_request['status'] == 'Pending Store Keeper Approval'): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card page-shell-card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-box-open"></i> Waiting for Store Keeper Approval
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">Accountant has approved the station costs. Inventory items requested from store are waiting for Store Keeper approval and issue.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($station_payout): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card page-shell-card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-paper-plane"></i> Station Payout
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Total Actual Cost:</strong> Tshs. <?php echo number_format((float) $station_payout_summary['total_actual_cost'], 2); ?></p>
                                    <p><strong>Total Paid:</strong> Tshs. <?php echo number_format((float) $station_payout_summary['total_paid'], 2); ?></p>
                                    <p><strong>Remaining Balance:</strong> <span class="<?php echo (float) $station_payout_summary['remaining_balance'] > 0 ? 'text-warning' : 'text-success'; ?>">Tshs. <?php echo number_format((float) $station_payout_summary['remaining_balance'], 2); ?></span></p>
                                    <p><strong>Channel:</strong> <?php echo htmlspecialchars(stripos((string) $station_payout['payout_channel'], 'bank') !== false ? 'Bank Transfer' : 'Mobile Money'); ?></p>
                                    <p><strong>Recipient:</strong> <?php echo htmlspecialchars($station_payout['recipient_name']); ?></p>
                                    <p><strong>Recipient Phone:</strong> <?php echo htmlspecialchars($station_payout['recipient_phone'] ?: 'N/A'); ?></p>
                                    <p><strong>Recipient Bank:</strong> <?php echo htmlspecialchars($station_payout['bank_name'] ? snippeGetBankDisplayName((string) $station_payout['bank_name']) : 'N/A'); ?></p>
                                    <p><strong>Bank Account:</strong> <?php echo htmlspecialchars($station_payout['bank_account_number'] ?: 'N/A'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Status:</strong> <span class="badge bg-<?php echo in_array($station_payout['status'], ['completed', 'success'], true) ? 'success' : ($station_payout['status'] === 'failed' ? 'danger' : 'warning'); ?>"><?php echo htmlspecialchars(strtoupper($station_payout['status'])); ?></span></p>
                                    <p><strong>Reference:</strong> <?php echo htmlspecialchars($station_payout['reference']); ?></p>
                                    <p><strong>Fees:</strong> Tshs. <?php echo number_format((float) $station_payout['fees_value'], 2); ?></p>
                                    <p><strong>Total Deducted:</strong> Tshs. <?php echo number_format((float) $station_payout['total_value'], 2); ?></p>
                                    <p><strong>Failure Reason:</strong> <?php echo htmlspecialchars($station_payout['failure_reason'] ?: 'N/A'); ?></p>
                                    <p><strong>SMS Sent:</strong> <?php echo htmlspecialchars($station_payout['sms_sent_at'] ?: 'Pending'); ?></p>
                                </div>
                            </div>
                            <?php if ($station_payouts && $station_payouts->num_rows > 0): ?>
                                <hr>
                                <h6 class="mb-3">Payout Attempts</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Reference</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Failure Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($payoutRow = $station_payouts->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y \a\t h:i A', strtotime($payoutRow['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($payoutRow['reference']); ?></td>
                                                    <td>Tshs. <?php echo number_format((float) $payoutRow['amount_value'], 2); ?></td>
                                                    <td><span class="badge bg-<?php echo in_array($payoutRow['status'], ['completed', 'success'], true) ? 'success' : ($payoutRow['status'] === 'failed' ? 'danger' : 'warning'); ?>"><?php echo htmlspecialchars(strtoupper($payoutRow['status'])); ?></span></td>
                                                    <td><?php echo htmlspecialchars($payoutRow['failure_reason'] ?: '-'); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Progress Updates Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card page-shell-card">
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
                    <div class="card page-shell-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-check-circle"></i> Approval Status
                            </h5>
                        </div>
                        <div class="card-body">
                            <p><i class="fas fa-check-circle text-success"></i> <strong>Approved</strong> by <?php echo htmlspecialchars($station_request['approver_name'] ?: 'Unknown'); ?> (<?php echo htmlspecialchars($station_request['approver_role'] ?: 'Unknown Role'); ?>)</p>
                            <?php if ($station_request['status'] == 'Equipment Issued'): ?>
                                <p><i class="fas fa-tools text-info"></i> <strong>Equipment Issued</strong> - Ready for installation</p>
                            <?php elseif ($station_request['status'] == 'Ready for Installation'): ?>
                                <p><i class="fas fa-play-circle text-primary"></i> <strong>Ready for Installation</strong> - Financial approvals and payout are complete</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>
</div>

<style>
.detail-page-stack {
    max-width: 1180px;
}

.detail-page .card {
    border: 1px solid #dbe4f0;
    border-radius: 18px;
    overflow: hidden;
}

.detail-page .card-header {
    padding: 1rem 1.25rem;
}

.timeline-marker {
    flex-shrink: 0;
}

.timeline-item:last-child {
    border-bottom: none !important;
}
</style>

<?php include '../includes/footer.php'; ?>
