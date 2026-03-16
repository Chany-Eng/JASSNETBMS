<?php
require_once '../includes/functions.php';
require_once '../includes/station_workflow.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

// page accessible to relevant staff; addition restricted below
requirePermission(['Technician', 'Sales', 'Manager', 'Director', 'Accountant', 'Store Keeper', 'Super Admin']);
stationEnsureWorkflowSchema($conn);
snippeEnsurePayoutTables($conn);
ensureInventorySoftDeleteSchema($conn);
$snippeMinimumBankPayoutAmount = snippeGetMinimumPayoutAmount('bank');
$snippeMinimumMobilePayoutAmount = snippeGetMinimumPayoutAmount('mobile');

function canEditStationRequest(array $row): bool
{
    $isOwner = (int) ($row['requested_by'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0);
    $editableStatuses = ['Pending Manager Approval', 'Rejected'];
    return hasPermission(['Super Admin']) || ($isOwner && in_array((string) ($row['status'] ?? ''), $editableStatuses, true));
}

function canHandleStationWorkflowStage(string $stage): bool
{
    if ($stage === 'manager') {
        return hasPermission(['Manager', 'Super Admin']);
    }

    if ($stage === 'director') {
        return hasPermission(['Director', 'Super Admin']);
    }

    if ($stage === 'accountant') {
        return hasPermission(['Accountant', 'Super Admin']);
    }

    if ($stage === 'storekeeper') {
        return hasPermission(['Store Keeper', 'Super Admin']);
    }

    return false;
}

function stationWorkflowActorLabel(string $role): string
{
    return hasPermission(['Super Admin']) ? 'Super Admin acting as ' . $role : $role;
}

function stationStageActingRole(string $status): string
{
    if ($status === 'Pending Manager Approval') {
        return 'Manager';
    }

    if ($status === 'Pending Director Approval') {
        return 'Director';
    }

    if ($status === 'Awaiting Accountant Approval') {
        return 'Accountant';
    }

    if ($status === 'Pending Store Keeper Approval') {
        return 'Store Keeper';
    }

    if (in_array($status, ['Approved', 'Installation in Progress'], true)) {
        return 'Requester';
    }

    return '';
}

function stationTableExists(mysqli $conn, string $tableName): bool
{
    $safeTable = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function stationFetchWorkflowRequest(mysqli $conn, int $requestId): ?array
{
    $stmt = $conn->prepare("SELECT sr.*, u.full_name AS requested_by_name, u.phone AS requested_by_phone FROM station_requests sr JOIN users u ON sr.requested_by = u.id WHERE sr.id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function stationNotifyWorkflowStage(mysqli $conn, array $row, string $stage, string $comment = ''): void
{
    $requestId = (int) ($row['id'] ?? 0);
    $stationName = trim((string) ($row['station_name'] ?? 'Station'));
    $requesterId = (int) ($row['requested_by'] ?? 0);
    $requesterName = trim((string) ($row['requested_by_name'] ?? 'Requester'));
    $phone = (string) ($row['requested_by_phone'] ?? '');

    if ($stage === 'submit') {
        appSendSmsToPhone($phone, 'ERMS: Station request #' . $requestId . ' ya ' . $stationName . ' imepokelewa. Inasubiri Manager approval.');
        appSendSmsToRoles($conn, ['Manager'], 'ERMS: Station request #' . $requestId . ' ya ' . $stationName . ' kutoka ' . $requesterName . ' inasubiri approval yako.', [$requesterId]);
        return;
    }

    if ($stage === 'manager_approved') {
        appSendSmsToPhone($phone, 'ERMS: Station request #' . $requestId . ' ya ' . $stationName . ' imekubaliwa na Manager. Inasubiri Director approval.');
        appSendSmsToRoles($conn, ['Director'], 'ERMS: Station request #' . $requestId . ' ya ' . $stationName . ' inasubiri Director approval yako.', [$requesterId]);
        return;
    }

    if ($stage === 'director_approved') {
        appSendSmsToPhone($phone, 'ERMS: Station request #' . $requestId . ' ya ' . $stationName . ' imekubaliwa na Director. Endelea na costs/receipts au accountant processing.');
        appSendSmsToRoles($conn, ['Accountant'], 'ERMS: Station request #' . $requestId . ' ya ' . $stationName . ' imefika stage ya accountant.', [$requesterId]);
        return;
    }

    if ($stage === 'rejected') {
        appSendSmsToPhone($phone, 'ERMS: Station request #' . $requestId . ' ya ' . $stationName . ' imekataliwa. Sababu: ' . ($comment !== '' ? $comment : '-'));
        return;
    }

    if ($stage === 'accountant_update') {
        appSendSmsToPhone($phone, 'ERMS: Station request #' . $requestId . ' ya ' . $stationName . ' imepitiwa na Accountant. ' . ($comment !== '' ? $comment : 'Kagua status mpya kwenye mfumo.'));
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && hasPermission(['Accountant', 'Super Admin'])) {
    snippeRefreshPendingPayouts($conn, 5);
}

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
            
            $status = 'Pending Manager Approval';
            $stmt = $conn->prepare("INSERT INTO station_requests (requested_by, station_name, location, gps, description, coverage_area, installation_type, total_estimated_cost, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssds", $_SESSION['user_id'], $station_name, $location, $gps, $description, $coverage_area, $installation_type, $total_estimated_cost, $status);
            
            if ($stmt->execute()) {
                $station_id = $conn->insert_id;
                appLogActivity($conn, 'CREATE_STATION_REQUEST', 'Submitted station request for ' . $station_name, 'station_requests', $station_id);
                $requestRow = stationFetchWorkflowRequest($conn, $station_id);
                if ($requestRow) {
                    stationNotifyWorkflowStage($conn, $requestRow, 'submit');
                }
                
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
                            $check_stmt = $conn->prepare("SELECT id FROM inventory WHERE id = ? AND COALESCE(is_deleted, 0) = 0");
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
                        $stmt2->bind_param("isisids", $station_id, $equipment_name, $quantity, $source, $inventory_id, $purchase_cost, $supplier);
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
    } elseif (isset($_POST['approve_manager'])) {
        if (!canHandleStationWorkflowStage('manager')) {
            $error = 'You are not authorized to do manager approval';
        } else {
            $request_id = intval($_POST['request_id']);
            $manager_comment = trim(sanitize($_POST['manager_comment'] ?? ''));
            if ($manager_comment === '') {
                $error = 'Manager comment is required before approval.';
            } else {
                $stmt = $conn->prepare("UPDATE station_requests SET status = 'Pending Director Approval', manager_approved_by = ?, manager_approved_at = NOW(), manager_comment = ? WHERE id = ? AND status = 'Pending Manager Approval'");
                $stmt->bind_param("isi", $_SESSION['user_id'], $manager_comment, $request_id);
                $stmt->execute();
                appLogActivity($conn, 'APPROVE_STATION_MANAGER', stationWorkflowActorLabel('Manager') . ' approved station request #' . $request_id, 'station_requests', $request_id);
                $requestRow = stationFetchWorkflowRequest($conn, $request_id);
                if ($requestRow) {
                    stationNotifyWorkflowStage($conn, $requestRow, 'manager_approved', $manager_comment);
                }
                $message = 'Station request approved by manager. Waiting for Director approval.';
            }
        }
    } elseif (isset($_POST['reject_manager'])) {
        if (!canHandleStationWorkflowStage('manager')) {
            $error = 'You are not authorized to reject station requests';
        } else {
            $request_id = intval($_POST['request_id']);
            $reason = trim(sanitize($_POST['manager_rejection_reason'] ?? ''));
            if ($reason === '') {
                $error = 'Manager rejection comment is required';
            } else {
                $stmt = $conn->prepare("UPDATE station_requests SET status = 'Rejected', manager_approved_by = ?, manager_approved_at = NOW(), manager_comment = ? WHERE id = ? AND status = 'Pending Manager Approval'");
                $stmt->bind_param("isi", $_SESSION['user_id'], $reason, $request_id);
                $stmt->execute();
                appLogActivity($conn, 'REJECT_STATION_MANAGER', stationWorkflowActorLabel('Manager') . ' rejected station request #' . $request_id . ' with reason: ' . $reason, 'station_requests', $request_id);
                $requestRow = stationFetchWorkflowRequest($conn, $request_id);
                if ($requestRow) {
                    stationNotifyWorkflowStage($conn, $requestRow, 'rejected', 'Manager: ' . $reason);
                }
                $message = 'Station request rejected by manager.';
            }
        }
    } elseif (isset($_POST['approve_director'])) {
        if (!canHandleStationWorkflowStage('director')) {
            $error = 'You are not authorized to do director approval';
        } else {
            $request_id = intval($_POST['request_id']);
            $director_comment = trim(sanitize($_POST['director_comment'] ?? ''));
            if ($director_comment === '') {
                $error = 'Director comment is required before approval.';
            } else {
                $stmt = $conn->prepare("UPDATE station_requests SET status = 'Approved', approved_by = ?, director_approved_by = ?, director_approved_at = NOW(), director_comment = ? WHERE id = ? AND status = 'Pending Director Approval'");
                $stmt->bind_param("iisi", $_SESSION['user_id'], $_SESSION['user_id'], $director_comment, $request_id);
                $stmt->execute();
                appLogActivity($conn, 'APPROVE_STATION_DIRECTOR', stationWorkflowActorLabel('Director') . ' approved station request #' . $request_id, 'station_requests', $request_id);
                $requestRow = stationFetchWorkflowRequest($conn, $request_id);
                if ($requestRow) {
                    stationNotifyWorkflowStage($conn, $requestRow, 'director_approved', $director_comment);
                }
                $message = 'Station request approved by director. Requester can now submit actual costs and receipts.';
            }
        }
    } elseif (isset($_POST['reject_director'])) {
        if (!canHandleStationWorkflowStage('director')) {
            $error = 'You are not authorized to reject station requests';
        } else {
            $request_id = intval($_POST['request_id']);
            $reason = trim(sanitize($_POST['director_rejection_reason'] ?? ''));
            if ($reason === '') {
                $error = 'Director rejection comment is required';
            } else {
                $stmt = $conn->prepare("UPDATE station_requests SET status = 'Rejected', director_approved_by = ?, director_approved_at = NOW(), director_comment = ? WHERE id = ? AND status = 'Pending Director Approval'");
                $stmt->bind_param("isi", $_SESSION['user_id'], $reason, $request_id);
                $stmt->execute();
                appLogActivity($conn, 'REJECT_STATION_DIRECTOR', stationWorkflowActorLabel('Director') . ' rejected station request #' . $request_id . ' with reason: ' . $reason, 'station_requests', $request_id);
                $requestRow = stationFetchWorkflowRequest($conn, $request_id);
                if ($requestRow) {
                    stationNotifyWorkflowStage($conn, $requestRow, 'rejected', 'Director: ' . $reason);
                }
                $message = 'Station request rejected by director.';
            }
        }
    } elseif (isset($_POST['submit_costs'])) {
        $request_id = intval($_POST['request_id']);
        
        // Check if user is the requester
        $check_stmt = $conn->prepare("SELECT requested_by FROM station_requests WHERE id = ? AND status = 'Approved'");
        $check_stmt->bind_param("i", $request_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $request_data = $check_result->fetch_assoc();
        
        if (!$request_data || ((int) $request_data['requested_by'] !== (int) ($_SESSION['user_id'] ?? 0) && !hasPermission(['Super Admin']))) {
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
                $stmt->bind_param("iddddddssi", $request_id, $actual_equipment_cost, $actual_installation_cost, $actual_transport_cost, $actual_labor_cost, $actual_misc_cost, $total_actual_cost, $cost_notes, $receipt_file, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    // Update station status to Awaiting Accountant Approval
                    $conn->query("UPDATE station_requests SET status = 'Awaiting Accountant Approval' WHERE id = $request_id");
                    $submitCostLog = hasPermission(['Super Admin']) && (int) ($request_data['requested_by'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0)
                        ? 'Super Admin submitted actual costs on behalf of requester for station request #' . $request_id
                        : 'Submitted actual costs for station request #' . $request_id;
                    appLogActivity($conn, 'SUBMIT_STATION_COSTS', $submitCostLog, 'station_requests', $request_id);
                    $message = 'Receipt and costs submitted successfully. Awaiting Accountant approval.';
                } else {
                    $error = 'Error submitting costs';
                }
            }
        }
    } elseif (isset($_POST['approve_costs_accountant'])) {
        if (!canHandleStationWorkflowStage('accountant')) {
            $error = 'You are not authorized to approve station costs';
        } else {
            $request_id = intval($_POST['request_id']);
            $approval_notes = trim(sanitize($_POST['approval_notes'] ?? ''));
            $payout_amount = isset($_POST['payout_amount']) ? floatval($_POST['payout_amount']) : 0;

            if ($approval_notes === '') {
                $error = 'Accountant comment is required before payout approval.';
            }
            
            // Get requester phone number
            $requester_data = null;
            if (!$error) {
                $requester_stmt = $conn->prepare("SELECT sr.requested_by, sr.station_name, u.phone, u.full_name, u.payout_phone, u.bank_name, u.bank_account_number, u.preferred_payout_channel, u.employee_id, sc.total_actual_cost, sc.id AS station_cost_id FROM station_requests sr JOIN users u ON sr.requested_by = u.id LEFT JOIN station_costs sc ON sc.id = (SELECT sc2.id FROM station_costs sc2 WHERE sc2.station_request_id = sr.id ORDER BY sc2.id DESC LIMIT 1) WHERE sr.id = ? LIMIT 1");
                $requester_stmt->bind_param("i", $request_id);
                $requester_stmt->execute();
                $requester_result = $requester_stmt->get_result();
                $requester_data = $requester_result->fetch_assoc();
            }

            if (!$error && !$requester_data) {
                $error = 'Station request not found';
            } elseif (!$error) {
                $summaryBefore = stationGetPayoutSummary($conn, $request_id);
                $remainingBefore = (float) ($summaryBefore['remaining_balance'] ?? 0);
                $payoutChannel = snippeNormalizePayoutChannel($requester_data['preferred_payout_channel'] ?? 'mobile');
                $minimumPayoutAmount = snippeGetMinimumPayoutAmount($payoutChannel);

                if (!$summaryBefore['has_costs']) {
                    $error = 'No submitted costs were found for this station request.';
                } elseif (!empty($summaryBefore['has_active_payout'])) {
                    $error = 'This station already has a payout waiting for Snippe confirmation. Wait for that result before sending another payout.';
                } elseif ($remainingBefore > 0.00001 && $payout_amount <= 0) {
                    $error = 'Enter a payout amount greater than zero.';
                } elseif ($remainingBefore > 0.00001 && $remainingBefore < $minimumPayoutAmount && $payout_amount > 0) {
                    $error = 'The remaining balance is below Snippe minimum payout for ' . ($payoutChannel === 'bank' ? 'bank transfer' : 'mobile money') . ' of Tshs. ' . number_format($minimumPayoutAmount, 2) . '. Please settle this balance manually or combine it before retrying.';
                } elseif ($remainingBefore >= $minimumPayoutAmount && $payout_amount > 0 && $payout_amount < $minimumPayoutAmount) {
                    $error = 'Snippe minimum payout for ' . ($payoutChannel === 'bank' ? 'bank transfer' : 'mobile money') . ' is Tshs. ' . number_format($minimumPayoutAmount, 2) . '. Enter at least that amount.';
                } elseif ($remainingBefore > 0.00001 && $payout_amount > ($remainingBefore + 0.00001)) {
                    $error = 'Payout amount cannot be greater than the remaining balance.';
                }
            }

            if (!$error) {
                $conn->begin_transaction();
                try {
                    $accountantCommentStmt = $conn->prepare("UPDATE station_requests SET accountant_comment = ? WHERE id = ?");
                    if ($accountantCommentStmt) {
                        $accountantCommentStmt->bind_param('si', $approval_notes, $request_id);
                        $accountantCommentStmt->execute();
                    }

                    $recipient = [
                        'id' => (int) $requester_data['requested_by'],
                        'full_name' => $requester_data['full_name'],
                        'phone' => $requester_data['phone'],
                        'payout_phone' => $requester_data['payout_phone'],
                        'bank_name' => $requester_data['bank_name'],
                        'bank_account_number' => $requester_data['bank_account_number'],
                        'preferred_payout_channel' => $requester_data['preferred_payout_channel'],
                        'employee_id' => $requester_data['employee_id'],
                    ];

                    $stationRequest = [
                        'id' => $request_id,
                        'requested_by' => $requester_data['requested_by'],
                    ];

                    $totalActualCost = (float) ($requester_data['total_actual_cost'] ?? 0);
                    $payoutSummary = 'No payout was needed';
                    $summaryAfter = null;
                    $finalStatus = 'Awaiting Accountant Approval';
                    $shouldNotifyReady = false;

                    if ($totalActualCost <= 0.00001) {
                        $finalStatus = stationNeedsStoreKeeperApproval($conn, $request_id) ? 'Pending Store Keeper Approval' : 'Ready for Installation';
                        $updateStation = $conn->prepare("UPDATE station_requests SET status = ?, accountant_approved_by = ?, accountant_approved_at = NOW(), approved_by = ? WHERE id = ? AND status = 'Awaiting Accountant Approval'");
                        if (!$updateStation) {
                            throw new RuntimeException('Could not prepare station approval update');
                        }
                        $updateStation->bind_param('siii', $finalStatus, $_SESSION['user_id'], $_SESSION['user_id'], $request_id);
                        if (!$updateStation->execute()) {
                            throw new RuntimeException('Could not update station approval status');
                        }
                        $shouldNotifyReady = true;
                    } elseif ($remainingBefore > 0.00001) {
                        $payoutResult = snippeCreateStationPayout($conn, $stationRequest, $recipient, $payout_amount, (int) $_SESSION['user_id']);
                        $payout = $payoutResult['payout'];
                        $payoutStatus = strtolower((string) ($payout['status'] ?? 'pending'));
                        $failureReason = trim((string) ($payout['failure_reason'] ?? ''));
                        $summaryAfter = stationSyncPayoutStatus($conn, $request_id);
                        $remainingAfter = (float) ($summaryAfter['remaining_balance'] ?? 0);
                        $finalStatus = (string) ($summaryAfter['next_status'] ?? 'Awaiting Accountant Approval');
                        $payoutSummary = 'Payout attempt: Tshs. ' . number_format((float) ($payout['amount_value'] ?? $payout_amount), 2) . ' | Status: ' . strtoupper($payoutStatus) . ' | Remaining balance: Tshs. ' . number_format($remainingAfter, 2);
                        if ($failureReason !== '') {
                            $payoutSummary .= ' | Failure reason: ' . $failureReason;
                        }
                        $shouldNotifyReady = !empty($summaryAfter['can_finalize']);
                    } else {
                        $summaryAfter = stationSyncPayoutStatus($conn, $request_id);
                        $finalStatus = (string) ($summaryAfter['next_status'] ?? 'Awaiting Accountant Approval');
                        $shouldNotifyReady = !empty($summaryAfter['can_finalize']);
                    }

                    $costApprovalNote = $approval_notes . ' | ' . $payoutSummary;

                    $costStmt = $conn->prepare("UPDATE station_costs SET approval_notes = ? WHERE id = ?");
                    if ($costStmt) {
                        $costId = (int) ($requester_data['station_cost_id'] ?? 0);
                        $costStmt->bind_param('si', $costApprovalNote, $costId);
                        $costStmt->execute();
                    }

                    $conn->commit();
                    appLogActivity($conn, 'APPROVE_STATION_ACCOUNTANT', stationWorkflowActorLabel('Accountant') . ' processed station request #' . $request_id . ' with status ' . $finalStatus, 'station_requests', $request_id);

                    if ($shouldNotifyReady && !empty($requester_data['phone'])) {
                        $smsMsg = "Jamii salama! Your station '" . $requester_data['station_name'] . "' costs have been approved by Accountant. " . ($finalStatus === 'Pending Store Keeper Approval' ? 'Waiting for Store Keeper to issue store items.' : 'Installation can now begin.');
                        jassnet_sms($requester_data['phone'], $smsMsg);
                    } else {
                        $requestRow = stationFetchWorkflowRequest($conn, $request_id);
                        if ($requestRow) {
                            stationNotifyWorkflowStage($conn, $requestRow, 'accountant_update', $payoutSummary);
                        }
                    }

                    if ($totalActualCost <= 0.00001) {
                        $message = $finalStatus === 'Pending Store Keeper Approval'
                            ? 'Accountant approved the costs. Waiting for Store Keeper approval for inventory items.'
                            : 'Accountant approved the costs. Station is ready for installation.';
                    } elseif (!empty($summaryAfter['latest_payout_status']) && in_array($summaryAfter['latest_payout_status'], ['failed', 'cancelled', 'canceled'], true)) {
                        $message = 'Snippe payout failed. Station remains with Accountant. ' . (!empty($summaryAfter['latest_failure_reason']) ? ('Reason: ' . $summaryAfter['latest_failure_reason']) : 'Review the payout details and retry.');
                    } elseif (!empty($summaryAfter['can_finalize'])) {
                        $message = $finalStatus === 'Pending Store Keeper Approval'
                            ? 'Full payout completed. Waiting for Store Keeper approval for inventory items.'
                            : 'Full payout completed. Station is ready for installation.';
                    } elseif (!empty($summaryAfter['has_active_payout'])) {
                        $message = 'Payout submitted to Snippe and is still pending. Station remains with Accountant until payout completion is confirmed.';
                    } else {
                        $message = 'Partial payout recorded. Remaining balance: Tshs. ' . number_format((float) ($summaryAfter['remaining_balance'] ?? 0), 2) . '. Station remains with Accountant until the balance is fully paid.';
                    }
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['reject_costs_accountant'])) {
        if (!canHandleStationWorkflowStage('accountant')) {
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
            
            $stmt = $conn->prepare("UPDATE station_requests SET status = 'Approved', accountant_comment = ? WHERE id = ? AND status = 'Awaiting Accountant Approval'");
            if ($stmt) {
                $stmt->bind_param('si', $rejection_reason, $request_id);
                $stmt->execute();
            }

            $costStmt = $conn->prepare("UPDATE station_costs SET approval_notes = ? WHERE station_request_id = ? ORDER BY id DESC LIMIT 1");
            if ($costStmt) {
                $rejectionNote = 'REJECTED: ' . $rejection_reason;
                $costStmt->bind_param('si', $rejectionNote, $request_id);
                $costStmt->execute();
            }
            
            // Send SMS to requester if phone is available
            if ($requester_data && !empty($requester_data['phone'])) {
                $smsMsg = "Jamii salama! Your station '" . htmlspecialchars($requester_data['station_name']) . "' receipt and costs have been REJECTED by Accountant. Reason: " . htmlspecialchars($rejection_reason) . " Please resubmit with corrections.";
                jassnet_sms($requester_data['phone'], $smsMsg);
            }
            $requestRow = stationFetchWorkflowRequest($conn, $request_id);
            if ($requestRow) {
                stationNotifyWorkflowStage($conn, $requestRow, 'rejected', 'Accountant: ' . $rejection_reason);
            }
            
            appLogActivity($conn, 'REJECT_STATION_ACCOUNTANT', stationWorkflowActorLabel('Accountant') . ' rejected submitted station costs for request #' . $request_id . ' with reason: ' . $rejection_reason, 'station_requests', $request_id);
            $message = 'Receipt and costs rejected. Requester has been notified to resubmit. SMS notification sent to requester.';
        }
    } elseif (isset($_POST['approve_storekeeper'])) {
        if (!canHandleStationWorkflowStage('storekeeper')) {
            $error = 'You are not authorized to do Store Keeper approval';
        } else {
            $request_id = intval($_POST['request_id']);
            $conn->begin_transaction();
            try {
                $equipment_result = $conn->query("SELECT inventory_id, quantity FROM station_equipment WHERE station_request_id = {$request_id} AND source = 'Inventory'");
                if (!$equipment_result || $equipment_result->num_rows === 0) {
                    throw new RuntimeException('No store inventory items found for this station request');
                }

                while ($equipment = $equipment_result->fetch_assoc()) {
                    $inventory_id = (int) $equipment['inventory_id'];
                    $quantity = (int) $equipment['quantity'];
                    $conn->query("UPDATE inventory SET quantity = quantity - {$quantity} WHERE id = {$inventory_id} AND quantity >= {$quantity}");
                }

                $stmt = $conn->prepare("UPDATE station_requests SET status = 'Equipment Issued', storekeeper_approved_by = ?, storekeeper_approved_at = NOW() WHERE id = ? AND status = 'Pending Store Keeper Approval'");
                if (!$stmt) {
                    throw new RuntimeException('Could not prepare Store Keeper update');
                }
                $stmt->bind_param('ii', $_SESSION['user_id'], $request_id);
                if (!$stmt->execute()) {
                    throw new RuntimeException('Could not save Store Keeper approval');
                }

                $conn->commit();
                appLogActivity($conn, 'APPROVE_STATION_STOREKEEPER', stationWorkflowActorLabel('Store Keeper') . ' issued equipment for station request #' . $request_id, 'station_requests', $request_id);
                $message = 'Store Keeper approved and issued station equipment successfully.';
            } catch (Throwable $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    } elseif (isset($_POST['reject_storekeeper'])) {
        if (!canHandleStationWorkflowStage('storekeeper')) {
            $error = 'You are not authorized to reject as Store Keeper';
        } else {
            $request_id = intval($_POST['request_id']);
            $stmt = $conn->prepare("UPDATE station_requests SET status = 'Ready for Installation' WHERE id = ? AND status = 'Pending Store Keeper Approval'");
            if ($stmt) {
                $stmt->bind_param('i', $request_id);
                $stmt->execute();
                appLogActivity($conn, 'REJECT_STATION_STOREKEEPER', stationWorkflowActorLabel('Store Keeper') . ' skipped store issue for station request #' . $request_id, 'station_requests', $request_id);
                $message = 'Store Keeper skipped store issue. Station remains ready for installation without issued inventory.';
            } else {
                $error = 'Could not prepare Store Keeper reject query';
            }
        }
    } elseif (isset($_POST['update_progress'])) {
        $request_id = intval($_POST['request_id']);
        $status = sanitize($_POST['progress_status']);
        $notes = sanitize($_POST['progress_notes']);
        if (!hasPermission(['Technician', 'Manager', 'Director', 'Super Admin', 'Store Keeper'])) {
            $error = 'You are not allowed to update station progress';
        }
        
        // Only allow progress updates after approvals are completed
        $current_status = '';
        if (!$error) {
            $check_stmt = $conn->prepare("SELECT status FROM station_requests WHERE id = ?");
            $check_stmt->bind_param("i", $request_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $current_status = $check_result->fetch_assoc()['status'];
        }
        
        if (!$error && !stationCanUpdateProgress((string) $current_status)) {
            $error = 'Progress can only be updated after approvals and payouts are completed';
        } elseif (!$error) {
            $stmt = $conn->prepare("INSERT INTO station_progress (station_request_id, status, notes) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $request_id, $status, $notes);
            
            if ($stmt->execute()) {
                // Update station status
                $conn->query("UPDATE station_requests SET status = '$status' WHERE id = $request_id");
                $progressActor = hasPermission(['Super Admin']) ? 'Super Admin updated station progress for request #' . $request_id : 'Updated station progress for request #' . $request_id;
                appLogActivity($conn, 'UPDATE_STATION_PROGRESS', $progressActor . ' to status ' . $status, 'station_requests', $request_id);
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
        
        if ((int) ($request_data['requested_by'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0) && !hasPermission(['Super Admin'])) {
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
                $stmt->bind_param("isddddddssi", $request_id, $completion_date, $actual_equipment_cost, $actual_installation_cost, $actual_transport_cost, $actual_labor_cost, $actual_misc_cost, $total_actual_cost, $completion_notes, $receipt_file, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    // Update station status to completed
                    $conn->query("UPDATE station_requests SET status = 'Completed' WHERE id = $request_id");
                    $completeActor = hasPermission(['Super Admin']) && (int) ($request_data['requested_by'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0)
                        ? 'Super Admin submitted station completion on behalf of requester for request #' . $request_id
                        : 'Submitted station completion for request #' . $request_id;
                    appLogActivity($conn, 'COMPLETE_STATION', $completeActor, 'station_requests', $request_id);
                    $message = 'Station completion data submitted successfully';
                } else {
                    $error = 'Error submitting completion data';
                }
            }
        }
    } elseif (isset($_POST['update_station_request'])) {
        $request_id = intval($_POST['request_id'] ?? 0);
        $station_name = sanitize($_POST['station_name'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $gps = sanitize($_POST['gps'] ?? '');
        $coverage_area = sanitize($_POST['coverage_area'] ?? '');
        $installation_type = sanitize($_POST['installation_type'] ?? 'Hotspot');
        $description = sanitize($_POST['description'] ?? '');
        $total_estimated_cost = floatval($_POST['total_estimated_cost'] ?? 0);

        $checkStmt = $conn->prepare('SELECT requested_by, status FROM station_requests WHERE id = ? LIMIT 1');
        if ($checkStmt) {
            $checkStmt->bind_param('i', $request_id);
            $checkStmt->execute();
            $existingRequest = $checkStmt->get_result()->fetch_assoc();

            if (!$existingRequest) {
                $error = 'Station request not found';
            } elseif (!canEditStationRequest(['requested_by' => $existingRequest['requested_by'], 'status' => $existingRequest['status']])) {
                $error = 'You are not allowed to edit this station request';
            } elseif ($station_name === '' || $location === '' || $description === '' || $total_estimated_cost <= 0) {
                $error = 'Please complete all required station fields before saving changes';
            } else {
                $stmt = $conn->prepare('UPDATE station_requests SET station_name = ?, location = ?, gps = ?, description = ?, coverage_area = ?, installation_type = ?, total_estimated_cost = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('ssssssdi', $station_name, $location, $gps, $description, $coverage_area, $installation_type, $total_estimated_cost, $request_id);
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = 'Station request updated successfully';
                        header('Location: stations.php');
                        exit();
                    }
                }
                $error = 'Failed to update station request';
            }
        } else {
            $error = 'Could not verify station request for editing';
        }
    } elseif (isset($_POST['delete_station_request'])) {
        if (!hasPermission(['Super Admin'])) {
            $error = 'Only Super Admin can delete station requests';
        } else {
            $request_id = intval($_POST['request_id'] ?? 0);
            $conn->begin_transaction();
            try {
                foreach (['station_completion', 'station_costs', 'station_progress', 'station_equipment'] as $table) {
                    if (!stationTableExists($conn, $table)) {
                        continue;
                    }
                    $stmt = $conn->prepare("DELETE FROM {$table} WHERE station_request_id = ?");
                    if ($stmt) {
                        $stmt->bind_param('i', $request_id);
                        $stmt->execute();
                    }
                }

                $deletePayouts = $conn->prepare('DELETE FROM snippe_payouts WHERE station_request_id = ?');
                if ($deletePayouts) {
                    $deletePayouts->bind_param('i', $request_id);
                    $deletePayouts->execute();
                }

                $deleteRequest = $conn->prepare('DELETE FROM station_requests WHERE id = ?');
                if (!$deleteRequest) {
                    throw new RuntimeException('Could not prepare station delete query');
                }
                $deleteRequest->bind_param('i', $request_id);
                $deleteRequest->execute();

                if ($deleteRequest->affected_rows <= 0) {
                    throw new RuntimeException('Station request not found or already removed');
                }

                $conn->commit();
                $_SESSION['success_message'] = 'Station request deleted successfully';
                header('Location: stations.php');
                exit();
            } catch (Throwable $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Get station requests based on role permissions.
$where_clause = '';
if ((hasPermission(['Sales']) || hasPermission(['Technician'])) && !hasPermission(['Manager', 'Director', 'Accountant', 'Store Keeper', 'Super Admin'])) {
    $where_clause = 'WHERE sr.requested_by = ' . (int) ($_SESSION['user_id'] ?? 0);
}

$station_requests = $conn->query("SELECT sr.*, u.full_name as requested_by_name, u.preferred_payout_channel as requester_payout_channel, au.full_name as approver_name, au.role as approver_role, mu.full_name as manager_name, du.full_name as director_name, acu.full_name as accountant_name, sku.full_name as storekeeper_name, (SELECT sc.total_actual_cost FROM station_costs sc WHERE sc.station_request_id = sr.id ORDER BY sc.id DESC LIMIT 1) AS latest_total_actual_cost, (SELECT COALESCE(SUM(CASE WHEN sp.status IN ('completed', 'success') THEN sp.amount_value ELSE 0 END), 0) FROM snippe_payouts sp WHERE sp.station_request_id = sr.id) AS total_station_paid, (SELECT COUNT(*) FROM snippe_payouts sp WHERE sp.station_request_id = sr.id AND sp.status IN ('pending', 'processing', 'queued')) AS active_station_payouts, (SELECT sp.status FROM snippe_payouts sp WHERE sp.station_request_id = sr.id ORDER BY sp.id DESC LIMIT 1) AS latest_payout_status, (SELECT sp.failure_reason FROM snippe_payouts sp WHERE sp.station_request_id = sr.id ORDER BY sp.id DESC LIMIT 1) AS latest_payout_failure_reason FROM station_requests sr JOIN users u ON sr.requested_by = u.id LEFT JOIN users au ON sr.approved_by = au.id LEFT JOIN users mu ON sr.manager_approved_by = mu.id LEFT JOIN users du ON sr.director_approved_by = du.id LEFT JOIN users acu ON sr.accountant_approved_by = acu.id LEFT JOIN users sku ON sr.storekeeper_approved_by = sku.id $where_clause ORDER BY request_date DESC");

// Get inventory items for equipment selection
$inventory_items = $conn->query("SELECT id, item_name, quantity FROM inventory WHERE quantity > 0 AND COALESCE(is_deleted, 0) = 0 ORDER BY item_name");
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

<?php
$stationHeroActions = [];
if (hasPermission(['Technician'])) {
    $stationHeroActions[] = '<a href="request_new_station_setup.php" class="btn btn-light"><i class="fas fa-plus"></i> Request New Station Setup</a>';
}
echo renderPageHero([
    'eyebrow' => 'Station Operations',
    'title' => 'Station Setup Management',
    'icon' => 'fa-broadcast-tower',
    'badges' => ['Deployment workflow', 'Cost approval', 'Installation tracking'],
    'actions' => $stationHeroActions,
]);
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars(formatSuccessMessage($message)); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row" id="station-setup-requests">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Station Setup Requests</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-modern table-workflow-actions">
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
                            <?php if ($station_requests && $station_requests->num_rows > 0): ?>
                            <?php while ($request = $station_requests->fetch_assoc()): ?>
                            <?php $remainingBalance = max(0, (float) ($request['latest_total_actual_cost'] ?? 0) - (float) ($request['total_station_paid'] ?? 0)); ?>
                            <tr id="stationRow_<?php echo (int) $request['id']; ?>"
                                data-station-name="<?php echo htmlspecialchars((string) ($request['station_name'] ?? ''), ENT_QUOTES); ?>"
                                data-location="<?php echo htmlspecialchars((string) ($request['location'] ?? ''), ENT_QUOTES); ?>"
                                data-gps="<?php echo htmlspecialchars((string) ($request['gps'] ?? ''), ENT_QUOTES); ?>"
                                data-description="<?php echo htmlspecialchars((string) ($request['description'] ?? ''), ENT_QUOTES); ?>"
                                data-coverage-area="<?php echo htmlspecialchars((string) ($request['coverage_area'] ?? ''), ENT_QUOTES); ?>"
                                data-installation-type="<?php echo htmlspecialchars((string) ($request['installation_type'] ?? 'Hotspot'), ENT_QUOTES); ?>"
                                data-total-estimated-cost="<?php echo htmlspecialchars((string) ((float) ($request['total_estimated_cost'] ?? 0)), ENT_QUOTES); ?>"
                                data-total-actual-cost="<?php echo htmlspecialchars((string) ((float) ($request['latest_total_actual_cost'] ?? 0)), ENT_QUOTES); ?>"
                                data-total-paid="<?php echo htmlspecialchars((string) ((float) ($request['total_station_paid'] ?? 0)), ENT_QUOTES); ?>"
                                data-remaining-balance="<?php echo htmlspecialchars((string) $remainingBalance, ENT_QUOTES); ?>"
                                data-requester-payout-channel="<?php echo htmlspecialchars((string) ($request['requester_payout_channel'] ?? 'mobile'), ENT_QUOTES); ?>"
                                data-latest-payout-status="<?php echo htmlspecialchars((string) ($request['latest_payout_status'] ?? ''), ENT_QUOTES); ?>"
                                data-latest-payout-failure="<?php echo htmlspecialchars((string) ($request['latest_payout_failure_reason'] ?? ''), ENT_QUOTES); ?>">
                                <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                <td><?php echo htmlspecialchars($request['station_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['location']); ?></td>
                                <td><?php echo htmlspecialchars($request['installation_type']); ?></td>
                                <td>Tshs. <?php echo number_format($request['total_estimated_cost'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo stationStatusBadgeClass((string) $request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                                    <?php if ((float) ($request['latest_total_actual_cost'] ?? 0) > 0): ?>
                                        <div class="small text-muted mt-1">Paid: Tshs. <?php echo number_format((float) ($request['total_station_paid'] ?? 0), 2); ?></div>
                                        <div class="small <?php echo $remainingBalance > 0 ? 'text-warning' : 'text-success'; ?>">Remaining: Tshs. <?php echo number_format($remainingBalance, 2); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($request['latest_payout_status']) && in_array(strtolower((string) $request['latest_payout_status']), ['failed', 'cancelled', 'canceled'], true)): ?>
                                        <div class="small text-danger">Payout failed<?php echo !empty($request['latest_payout_failure_reason']) ? ': ' . htmlspecialchars($request['latest_payout_failure_reason']) : ''; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $stationActingRole = hasPermission(['Super Admin']) ? stationStageActingRole((string) ($request['status'] ?? '')) : ''; ?>
                                    <?php if ($stationActingRole !== ''): ?>
                                        <span class="acting-role-badge">Admin as <?php echo htmlspecialchars($stationActingRole); ?></span>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewStation(<?php echo $request['id']; ?>)">View</button>
                                    <?php if (canEditStationRequest($request)): ?>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="editStationRequest(<?php echo $request['id']; ?>)">Edit</button>
                                    <?php endif; ?>
                                    <?php if (hasPermission(['Super Admin'])): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteStationRequest(<?php echo $request['id']; ?>)">Delete</button>
                                    <?php endif; ?>
                                    <?php if (canHandleStationWorkflowStage('manager') && $request['status'] == 'Pending Manager Approval'): ?>
                                        <button class="btn btn-sm btn-success" onclick="approveManager(<?php echo $request['id']; ?>)">Approve</button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectManager(<?php echo $request['id']; ?>)">Reject</button>
                                    <?php elseif (canHandleStationWorkflowStage('director') && $request['status'] == 'Pending Director Approval'): ?>
                                        <button class="btn btn-sm btn-success" onclick="approveDirector(<?php echo $request['id']; ?>)">Approve</button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectDirector(<?php echo $request['id']; ?>)">Reject</button>
                                    <?php elseif (($request['requested_by'] == $_SESSION['user_id'] || hasPermission(['Super Admin'])) && $request['status'] == 'Approved'): ?>
                                        <button class="btn btn-sm btn-warning" onclick="submitCosts(<?php echo $request['id']; ?>)">Submit Costs</button>
                                    <?php elseif (canHandleStationWorkflowStage('accountant') && $request['status'] == 'Awaiting Accountant Approval'): ?>
                                        <button class="btn btn-sm btn-success" onclick="approveCostsAccountant(<?php echo $request['id']; ?>)">Approve</button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectCostsAccountant(<?php echo $request['id']; ?>)">Reject</button>
                                    <?php elseif (canHandleStationWorkflowStage('storekeeper') && $request['status'] == 'Pending Store Keeper Approval'): ?>
                                        <button class="btn btn-sm btn-success" onclick="approveStoreKeeper(<?php echo $request['id']; ?>)">Approve Store</button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="rejectStoreKeeper(<?php echo $request['id']; ?>)">Skip Store</button>
                                    <?php elseif (($request['requested_by'] == $_SESSION['user_id'] || hasPermission(['Super Admin'])) && $request['status'] == 'Installation in Progress'): ?>
                                        <button class="btn btn-sm btn-success" onclick="completeStation(<?php echo $request['id']; ?>)">Complete Station</button>
                                    <?php elseif (hasPermission(['Accountant', 'Super Admin']) && in_array($request['status'], ['Pending Store Keeper Approval', 'Ready for Installation', 'Equipment Issued', 'Installation in Progress', 'Completed'], true)): ?>
                                        <button class="btn btn-sm btn-info" onclick="viewReceipt(<?php echo $request['id']; ?>)">View Receipt</button>
                                    <?php elseif (hasPermission(['Technician', 'Manager', 'Director', 'Super Admin', 'Store Keeper']) && stationCanUpdateProgress((string) $request['status'])): ?>
                                        <button class="btn btn-sm btn-info" onclick="updateProgress(<?php echo $request['id']; ?>)">Update Progress</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No station requests found in the database for your access level.
                                </td>
                            </tr>
                            <?php endif; ?>
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
                <h5 class="modal-title">Manager Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Approve this station request and send it to the Director for the second approval stage.</p>
                <form method="POST" id="approveManagerForm">
                    <input type="hidden" name="request_id" id="approveManagerRequestId">
                    <div class="mb-3">
                        <label for="manager_comment" class="form-label">Manager Comment *</label>
                        <textarea class="form-control" id="manager_comment" name="manager_comment" rows="3" placeholder="Add your approval comment..." required></textarea>
                    </div>
                    <button type="submit" name="approve_manager" class="btn btn-success">Approve as Manager</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectManagerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Station Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Reject this station request at the Manager stage?</p>
                <form method="POST" id="rejectManagerForm">
                    <input type="hidden" name="request_id" id="rejectManagerRequestId">
                    <div class="mb-3">
                        <label for="manager_rejection_reason" class="form-label">Rejection Comment *</label>
                        <textarea class="form-control" id="manager_rejection_reason" name="manager_rejection_reason" rows="3" placeholder="Explain why this station request was rejected..." required></textarea>
                    </div>
                    <button type="submit" name="reject_manager" class="btn btn-danger">Reject as Manager</button>
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
                <h5 class="modal-title">Director Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Approve this station request and allow the requester to submit actual costs and receipts.</p>
                <form method="POST" id="approveDirectorForm">
                    <input type="hidden" name="request_id" id="approveDirectorRequestId">
                    <div class="mb-3">
                        <label for="director_comment" class="form-label">Director Comment *</label>
                        <textarea class="form-control" id="director_comment" name="director_comment" rows="3" placeholder="Add your approval comment..." required></textarea>
                    </div>
                    <button type="submit" name="approve_director" class="btn btn-success">Approve as Director</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectDirectorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Station Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Reject this station request at the Director stage?</p>
                <form method="POST" id="rejectDirectorForm">
                    <input type="hidden" name="request_id" id="rejectDirectorRequestId">
                    <div class="mb-3">
                        <label for="director_rejection_reason" class="form-label">Rejection Comment *</label>
                        <textarea class="form-control" id="director_rejection_reason" name="director_rejection_reason" rows="3" placeholder="Explain why this station request was rejected..." required></textarea>
                    </div>
                    <button type="submit" name="reject_director" class="btn btn-danger">Reject as Director</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade app-mobile-fullscreen-modal" id="editStationRequestModal" tabindex="-1" aria-labelledby="editStationRequestModalLabel" aria-describedby="editStationRequestModalDescription">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStationRequestModalLabel">Edit Station Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="editStationRequestModalDescription" class="text-muted small mb-3">Update the planned station details, cost estimate, and description before saving.</p>
                <form method="POST" id="editStationRequestForm">
                    <input type="hidden" name="request_id" id="editStationRequestId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_station_name" class="form-label">Station Name *</label>
                            <input type="text" class="form-control" id="edit_station_name" name="station_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_station_location" class="form-label">Location *</label>
                            <input type="text" class="form-control" id="edit_station_location" name="location" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_station_gps" class="form-label">GPS</label>
                            <input type="text" class="form-control" id="edit_station_gps" name="gps">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_station_installation_type" class="form-label">Installation Type *</label>
                            <select class="form-select" id="edit_station_installation_type" name="installation_type" required>
                                <option value="Hotspot">Hotspot</option>
                                <option value="Tower">Tower</option>
                                <option value="Relay">Relay</option>
                                <option value="Fiber Node">Fiber Node</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_station_coverage_area" class="form-label">Coverage Area</label>
                            <input type="text" class="form-control" id="edit_station_coverage_area" name="coverage_area">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_station_total_estimated_cost" class="form-label">Estimated Cost *</label>
                            <input type="number" class="form-control" id="edit_station_total_estimated_cost" name="total_estimated_cost" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-12">
                            <label for="edit_station_description" class="form-label">Description *</label>
                            <textarea class="form-control" id="edit_station_description" name="description" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2 flex-wrap justify-content-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_station_request" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteStationRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Station Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">This will permanently remove the station request and all linked equipment, payout, cost, progress, and completion records.</p>
                <form method="POST" id="deleteStationRequestForm">
                    <input type="hidden" name="request_id" id="deleteStationRequestId">
                    <button type="submit" name="delete_station_request" class="btn btn-danger">Delete Request</button>
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

<div class="modal fade app-mobile-fullscreen-modal" id="submitCostsModal" tabindex="-1" aria-labelledby="submitCostsModalLabel" aria-describedby="submitCostsModalDescription">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="submitCostsModalLabel">Submit Actual Costs and Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="submitCostsForm">
                    <input type="hidden" name="request_id" id="submitCostsRequestId">
                    <p id="submitCostsModalDescription" class="text-muted small">Provide the actual cost breakdown and attach the receipt so the station setup can move to accountant review.</p>
                    
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
                    
                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_costs" class="btn btn-primary">Submit Costs</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Receipt Modal -->
<div class="modal fade app-mobile-fullscreen-modal" id="viewReceiptModal" tabindex="-1">
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
<div class="modal fade app-mobile-fullscreen-modal" id="approveCostsModal" tabindex="-1" aria-labelledby="approveCostsModalLabel" aria-describedby="approveCostsModalDescription">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveCostsModalLabel">Approve Station Costs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="approveCostsModalDescription" class="text-muted small">Review the submitted costs, confirm the payout amount, and leave an accountant comment before approval.</p>
                <form method="POST" id="approveCostsForm">
                    <input type="hidden" name="request_id" id="approveCostsRequestId">
                    <div class="alert alert-light border small" id="approveCostsSummary">
                        Remaining balance will appear here.
                    </div>
                    <div class="mb-3">
                        <label for="payout_amount" class="form-label">Payout Amount</label>
                        <input type="number" class="form-control" id="payout_amount" name="payout_amount" step="0.01" min="0" value="0">
                        <div class="form-text">You can send a partial amount now and continue paying the remaining balance later. Minimum payout is Tshs. <?php echo number_format((float) $snippeMinimumMobilePayoutAmount, 2); ?> for Mobile Money and Tshs. <?php echo number_format((float) $snippeMinimumBankPayoutAmount, 2); ?> for Bank Transfer.</div>
                    </div>
                    <div class="mb-3">
                        <label for="approval_notes" class="form-label">Accountant Comment *</label>
                        <textarea class="form-control" id="approval_notes" name="approval_notes" rows="3" placeholder="Add accountant approval comment..." required></textarea>
                    </div>
                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="approve_costs_accountant" class="btn btn-success">Approve Costs & Send Payout</button>
                    </div>
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

<div class="modal fade" id="approveStoreKeeperModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Store Keeper Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Approve and issue the requested store inventory for this station setup.</p>
                <form method="POST" id="approveStoreKeeperForm">
                    <input type="hidden" name="request_id" id="approveStoreKeeperRequestId">
                    <button type="submit" name="approve_storekeeper" class="btn btn-success">Approve & Issue Store Items</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectStoreKeeperModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Skip Store Issue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Continue this station without issuing store inventory now?</p>
                <form method="POST" id="rejectStoreKeeperForm">
                    <input type="hidden" name="request_id" id="rejectStoreKeeperRequestId">
                    <button type="submit" name="reject_storekeeper" class="btn btn-outline-danger">Skip Store Approval</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade app-mobile-fullscreen-modal" id="completeStationModal" tabindex="-1" aria-labelledby="completeStationModalLabel" aria-describedby="completeStationModalDescription">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="completeStationModalLabel">Complete Station Setup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="completeStationModalDescription" class="text-muted small mb-3">Enter the completion costs, receipt, and summary notes before submitting the final station handoff.</p>
                <form method="POST" enctype="multipart/form-data" id="completeStationForm">
                    <input type="hidden" name="request_id" id="completeStationRequestId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="completion_date" class="form-label">Completion Date</label>
                                <input type="date" class="form-control" id="completion_date" name="completion_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="completion_equipment_cost" class="form-label">Equipment Cost</label>
                                <input type="number" class="form-control completion-cost-input" id="completion_equipment_cost" name="actual_equipment_cost" step="0.01" min="0" value="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="completion_installation_cost" class="form-label">Installation Cost</label>
                                <input type="number" class="form-control completion-cost-input" id="completion_installation_cost" name="actual_installation_cost" step="0.01" min="0" value="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="completion_transport_cost" class="form-label">Transport Cost</label>
                                <input type="number" class="form-control completion-cost-input" id="completion_transport_cost" name="actual_transport_cost" step="0.01" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="completion_labor_cost" class="form-label">Labor Cost</label>
                                <input type="number" class="form-control completion-cost-input" id="completion_labor_cost" name="actual_labor_cost" step="0.01" min="0" value="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="completion_misc_cost" class="form-label">Miscellaneous Cost</label>
                                <input type="number" class="form-control completion-cost-input" id="completion_misc_cost" name="actual_misc_cost" step="0.01" min="0" value="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="completion_total_cost" class="form-label">Total Completion Cost</label>
                                <input type="number" class="form-control" id="completion_total_cost" step="0.01" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="completion_receipt" class="form-label">Completion Receipt</label>
                                <input type="file" class="form-control" id="completion_receipt" name="completion_receipt" accept="image/*,.pdf">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="completion_notes" class="form-label">Completion Notes</label>
                        <textarea class="form-control" id="completion_notes" name="completion_notes" rows="3" placeholder="Summarize the work completed..."></textarea>
                    </div>
                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="complete_station" class="btn btn-success">Submit Completion</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function viewStation(id) {
    window.location.href = 'station_detail.php?id=' + id;
}

function editStationRequest(id) {
    const row = document.getElementById('stationRow_' + id);
    if (!row) {
        return;
    }

    document.getElementById('editStationRequestId').value = id;
    document.getElementById('edit_station_name').value = row.dataset.stationName || '';
    document.getElementById('edit_station_location').value = row.dataset.location || '';
    document.getElementById('edit_station_gps').value = row.dataset.gps || '';
    document.getElementById('edit_station_coverage_area').value = row.dataset.coverageArea || '';
    document.getElementById('edit_station_installation_type').value = row.dataset.installationType || 'Hotspot';
    document.getElementById('edit_station_total_estimated_cost').value = row.dataset.totalEstimatedCost || '';
    document.getElementById('edit_station_description').value = row.dataset.description || '';

    new bootstrap.Modal(document.getElementById('editStationRequestModal')).show();
}

function deleteStationRequest(id) {
    document.getElementById('deleteStationRequestId').value = id;
    new bootstrap.Modal(document.getElementById('deleteStationRequestModal')).show();
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function approveManager(id) {
    document.getElementById('approveManagerRequestId').value = id;
    document.getElementById('manager_comment').value = '';
    new bootstrap.Modal(document.getElementById('approveManagerModal')).show();
}

function rejectManager(id) {
    document.getElementById('rejectManagerRequestId').value = id;
    document.getElementById('manager_rejection_reason').value = '';
    new bootstrap.Modal(document.getElementById('rejectManagerModal')).show();
}

function approveDirector(id) {
    document.getElementById('approveDirectorRequestId').value = id;
    document.getElementById('director_comment').value = '';
    new bootstrap.Modal(document.getElementById('approveDirectorModal')).show();
}

function rejectDirector(id) {
    document.getElementById('rejectDirectorRequestId').value = id;
    document.getElementById('director_rejection_reason').value = '';
    new bootstrap.Modal(document.getElementById('rejectDirectorModal')).show();
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
    const row = document.getElementById('stationRow_' + id);
    const mobileMinimumPayout = <?php echo (int) $snippeMinimumMobilePayoutAmount; ?>;
    const bankMinimumPayout = <?php echo (int) $snippeMinimumBankPayoutAmount; ?>;
    const totalActualCost = row ? parseFloat(row.dataset.totalActualCost || '0') : 0;
    const totalPaid = row ? parseFloat(row.dataset.totalPaid || '0') : 0;
    const remainingBalance = row ? parseFloat(row.dataset.remainingBalance || '0') : 0;
    const payoutChannel = row ? ((row.dataset.requesterPayoutChannel || 'mobile').toLowerCase() === 'bank' ? 'bank' : 'mobile') : 'mobile';
    const minimumPayout = payoutChannel === 'bank' ? bankMinimumPayout : mobileMinimumPayout;
    const latestPayoutStatus = row ? (row.dataset.latestPayoutStatus || '').toUpperCase() : '';
    const latestFailure = row ? (row.dataset.latestPayoutFailure || '') : '';
    document.getElementById('approveCostsRequestId').value = id;
    document.getElementById('approval_notes').value = '';
    document.getElementById('payout_amount').value = remainingBalance > 0 ? remainingBalance.toFixed(2) : '0.00';
    document.getElementById('payout_amount').required = remainingBalance > 0.00001;
    document.getElementById('payout_amount').min = remainingBalance >= minimumPayout ? String(minimumPayout) : '0';
    document.getElementById('payout_amount').setAttribute('max', remainingBalance > 0 ? remainingBalance.toFixed(2) : '0');
    document.getElementById('approveCostsSummary').innerHTML = 'Total approved costs: Tshs. ' + totalActualCost.toFixed(2)
        + '<br>Already paid: Tshs. ' + totalPaid.toFixed(2)
        + '<br>Remaining balance: Tshs. ' + remainingBalance.toFixed(2)
        + '<br>Payout channel: ' + (payoutChannel === 'bank' ? 'Bank Transfer' : 'Mobile Money')
        + '<br>Snippe minimum payout: Tshs. ' + minimumPayout.toFixed(2)
        + (latestPayoutStatus ? '<br>Latest payout status: ' + escapeHtml(latestPayoutStatus) : '')
        + (latestFailure ? '<br><span class="text-danger">Failure reason: ' + escapeHtml(latestFailure) + '</span>' : '')
        + (remainingBalance > 0 && remainingBalance < minimumPayout ? '<br><span class="text-danger">Remaining balance is below Snippe minimum. Settle it manually or combine it before retrying.</span>' : '');
    new bootstrap.Modal(document.getElementById('approveCostsModal')).show();
}

function rejectCostsAccountant(id) {
    document.getElementById('rejectCostsRequestId').value = id;
    new bootstrap.Modal(document.getElementById('rejectCostsModal')).show();
}

function approveStoreKeeper(id) {
    document.getElementById('approveStoreKeeperRequestId').value = id;
    new bootstrap.Modal(document.getElementById('approveStoreKeeperModal')).show();
}

function rejectStoreKeeper(id) {
    document.getElementById('rejectStoreKeeperRequestId').value = id;
    new bootstrap.Modal(document.getElementById('rejectStoreKeeperModal')).show();
}

function completeStation(id) {
    document.getElementById('completeStationRequestId').value = id;
    new bootstrap.Modal(document.getElementById('completeStationModal')).show();
}

function focusFirstModalField(modalId, selector) {
    const modalEl = document.getElementById(modalId);
    if (!modalEl) {
        return;
    }

    modalEl.addEventListener('shown.bs.modal', function() {
        const field = modalEl.querySelector(selector);
        if (field) {
            field.focus();
            if (typeof field.select === 'function' && (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA')) {
                field.select();
            }
        }
    });
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

function calculateCompletionTotal() {
    const equipment = parseFloat(document.getElementById('completion_equipment_cost').value) || 0;
    const installation = parseFloat(document.getElementById('completion_installation_cost').value) || 0;
    const transport = parseFloat(document.getElementById('completion_transport_cost').value) || 0;
    const labor = parseFloat(document.getElementById('completion_labor_cost').value) || 0;
    const misc = parseFloat(document.getElementById('completion_misc_cost').value) || 0;
    document.getElementById('completion_total_cost').value = (equipment + installation + transport + labor + misc).toFixed(2);
}

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('completion-cost-input')) {
        calculateCompletionTotal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    focusFirstModalField('editStationRequestModal', '#edit_station_name');
    focusFirstModalField('submitCostsModal', '#actual_equipment_cost');
    focusFirstModalField('approveCostsModal', '#payout_amount');
    focusFirstModalField('completeStationModal', '#completion_date');
});

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