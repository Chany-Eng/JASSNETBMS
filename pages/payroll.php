<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payouts.php';
require_once '../includes/payroll_workflow.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

payrollEnsureSchema($conn);

$canCreatePayroll = hasPermission(['Accountant', 'Super Admin']);
$canManagerApprovePayroll = hasPermission(['Manager', 'Super Admin']);
$canDirectorApprovePayroll = hasPermission(['Director', 'Super Admin']);
$canFinalizePayroll = hasPermission(['Accountant', 'Super Admin']);
$canRejectPayroll = hasPermission(['Manager', 'Director', 'Accountant', 'Super Admin']);
$canExportPayroll = hasPermission(['Accountant', 'Director', 'Super Admin']);
$isPrivilegedPayrollViewer = hasPermission(['Accountant', 'Manager', 'Director', 'Super Admin']);

$message = '';
$error = '';
$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

function payrollBuildRecord(array $row): array
{
    $salaryMonth = (string) ($row['salary_month'] ?? date('Y-m-01'));
    $finalizedAt = (string) ($row['finalized_at'] ?? '');
    $row['salary_month_label'] = date('F Y', strtotime($salaryMonth));
    $row['paid_date_label'] = $finalizedAt !== '' ? date('d M Y', strtotime($finalizedAt)) : '-';
    $row['paid_day_label'] = $finalizedAt !== '' ? date('l', strtotime($finalizedAt)) : '-';
    $row['payout_method_label'] = payrollPayoutLabel((string) ($row['payout_channel'] ?? 'mobile'));
    $row['accountant_signature'] = trim((string) ($row['finalized_by_name'] ?? ''));
    return $row;
}

function payrollFetchWorkflowRequest(mysqli $conn, int $requestId): ?array
{
    $stmt = $conn->prepare('SELECT sr.*, u.full_name AS employee_name, u.phone, u.employee_id FROM salary_requests sr JOIN users u ON sr.user_id = u.id WHERE sr.id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function payrollNotifyStage(mysqli $conn, array $row, string $stage, string $comment = ''): void
{
    $requestId = (int) ($row['id'] ?? 0);
    $employeeName = trim((string) ($row['employee_name'] ?? 'Employee'));
    $salaryMonth = (string) ($row['salary_month'] ?? date('Y-m-01'));
    $monthLabel = date('F Y', strtotime($salaryMonth));
    $netSalary = number_format((float) ($row['net_salary'] ?? 0), 2);
    $phone = (string) ($row['phone'] ?? '');

    if ($stage === 'manager_approved') {
        appSendSmsToPhone($phone, 'ERMS: Salary request #' . $requestId . ' ya ' . $monthLabel . ' imekubaliwa na Manager. Inasubiri Director approval.');
        appSendSmsToRoles($conn, ['Director'], 'ERMS: Salary request #' . $requestId . ' kwa ' . $employeeName . ' inasubiri Director approval yako.');
        return;
    }

    if ($stage === 'director_approved') {
        appSendSmsToPhone($phone, 'ERMS: Salary request #' . $requestId . ' ya ' . $monthLabel . ' imekubaliwa na Director. Inasubiri final accountant approval.');
        appSendSmsToRoles($conn, ['Accountant'], 'ERMS: Salary request #' . $requestId . ' kwa ' . $employeeName . ' inasubiri final processing yako.');
        return;
    }

    if ($stage === 'finalized') {
        appSendSmsToPhone($phone, 'ERMS: Salary request #' . $requestId . ' ya ' . $monthLabel . ' imelipwa. Net salary: Tshs. ' . $netSalary . '.' . ($comment !== '' ? ' Ref: ' . $comment : ''));
        return;
    }

    if ($stage === 'rejected') {
        appSendSmsToPhone($phone, 'ERMS: Salary request #' . $requestId . ' ya ' . $monthLabel . ' imekataliwa. Sababu: ' . ($comment !== '' ? $comment : '-'));
    }
}

function payrollUserCanAccess(array $row, bool $isPrivileged): bool
{
    if ($isPrivileged) {
        return true;
    }

    return (int) ($row['user_id'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0);
}

function payrollWorkflowActorLabel(string $role): string
{
    return hasPermission(['Super Admin']) ? 'Super Admin acting as ' . $role : $role;
}

function payrollStageActingRole(string $status): string
{
    if ($status === 'Pending Manager Approval') {
        return 'Manager';
    }

    if ($status === 'Pending Director Approval') {
        return 'Director';
    }

    if ($status === 'Pending Accountant Final Approval') {
        return 'Accountant';
    }

    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_manager_salary'])) {
        if (!$canManagerApprovePayroll) {
            $error = 'You are not allowed to approve as manager';
        } else {
            $requestId = intval($_POST['request_id'] ?? 0);
            $comment = sanitize($_POST['manager_comment'] ?? '');
            if ($comment === '') {
                $error = 'Manager comment is required';
            } else {
                $stmt = $conn->prepare("UPDATE salary_requests SET manager_comment = ?, manager_approved_by = ?, manager_approved_at = NOW(), status = 'Pending Director Approval' WHERE id = ? AND status = 'Pending Manager Approval'");
                if ($stmt) {
                    $stmt->bind_param('sii', $comment, $_SESSION['user_id'], $requestId);
                    $stmt->execute();
                    appLogActivity($conn, 'APPROVE_SALARY_MANAGER', payrollWorkflowActorLabel('Manager') . ' approved salary request #' . $requestId, 'salary_requests', $requestId);
                    $salaryRow = payrollFetchWorkflowRequest($conn, $requestId);
                    if ($salaryRow) {
                        payrollNotifyStage($conn, $salaryRow, 'manager_approved', $comment);
                    }
                    $_SESSION['success_message'] = 'Salary request approved by manager';
                    header('Location: payroll.php');
                    exit();
                }
            }
        }
    } elseif (isset($_POST['approve_director_salary'])) {
        if (!$canDirectorApprovePayroll) {
            $error = 'You are not allowed to approve as director';
        } else {
            $requestId = intval($_POST['request_id'] ?? 0);
            $comment = sanitize($_POST['director_comment'] ?? '');
            if ($comment === '') {
                $error = 'Director comment is required';
            } else {
                $stmt = $conn->prepare("UPDATE salary_requests SET director_comment = ?, director_approved_by = ?, director_approved_at = NOW(), status = 'Pending Accountant Final Approval' WHERE id = ? AND status = 'Pending Director Approval'");
                if ($stmt) {
                    $stmt->bind_param('sii', $comment, $_SESSION['user_id'], $requestId);
                    $stmt->execute();
                    appLogActivity($conn, 'APPROVE_SALARY_DIRECTOR', payrollWorkflowActorLabel('Director') . ' approved salary request #' . $requestId, 'salary_requests', $requestId);
                    $salaryRow = payrollFetchWorkflowRequest($conn, $requestId);
                    if ($salaryRow) {
                        payrollNotifyStage($conn, $salaryRow, 'director_approved', $comment);
                    }
                    $_SESSION['success_message'] = 'Salary request approved by director';
                    header('Location: payroll.php');
                    exit();
                }
            }
        }
    } elseif (isset($_POST['finalize_salary_payment'])) {
        if (!$canFinalizePayroll) {
            $error = 'You are not allowed to finalize salary payments';
        } else {
            $requestId = intval($_POST['request_id'] ?? 0);
            $comment = sanitize($_POST['accountant_final_comment'] ?? '');
            $paymentReference = sanitize($_POST['payment_reference'] ?? '');
            if ($comment === '') {
                $error = 'Final accountant comment is required';
            } else {
                $salaryStmt = $conn->prepare('SELECT sr.*, u.full_name, u.employee_id, u.phone, u.payout_phone, u.bank_name, u.bank_account_number, u.preferred_payout_channel FROM salary_requests sr JOIN users u ON sr.user_id = u.id WHERE sr.id = ? AND sr.status = ? LIMIT 1');
                $pendingStatus = 'Pending Accountant Final Approval';
                if ($salaryStmt) {
                    $salaryStmt->bind_param('is', $requestId, $pendingStatus);
                    $salaryStmt->execute();
                    $salaryRow = $salaryStmt->get_result()->fetch_assoc() ?: null;
                } else {
                    $salaryRow = null;
                }

                if (!$salaryRow) {
                    $error = 'Salary request not found or is no longer waiting for final approval';
                } else {
                    try {
                        $selectedPayoutChannel = strtolower(trim((string) ($salaryRow['payout_channel'] ?? 'mobile')));
                        $successMessage = 'Salary payment sent successfully to user payout destination';
                        if ($selectedPayoutChannel === 'cash') {
                            if ($paymentReference === '') {
                                $paymentReference = 'CASH-' . date('YmdHis');
                            }
                            $successMessage = 'Cash salary payment recorded successfully';
                        } else {
                            $payoutResult = payrollSendSalaryPayout($salaryRow, $salaryRow);
                            if ($paymentReference === '') {
                                $paymentReference = (string) ($payoutResult['reference'] ?? '');
                            }
                        }
                        $stmt = $conn->prepare("UPDATE salary_requests SET accountant_final_comment = ?, payment_reference = ?, finalized_by = ?, finalized_at = NOW(), status = 'Paid' WHERE id = ? AND status = 'Pending Accountant Final Approval'");
                        if ($stmt) {
                            $stmt->bind_param('ssii', $comment, $paymentReference, $_SESSION['user_id'], $requestId);
                            $stmt->execute();
                            appLogActivity($conn, 'FINALIZE_SALARY_PAYMENT', payrollWorkflowActorLabel('Accountant') . ' finalized salary payment #' . $requestId . ' via ' . payrollPayoutLabel($selectedPayoutChannel) . ' reference ' . $paymentReference, 'salary_requests', $requestId);
                            $salaryRow = payrollFetchWorkflowRequest($conn, $requestId);
                            if ($salaryRow) {
                                $salaryRow['net_salary'] = $salaryRow['net_salary'] ?? ($salaryRow['net_salary'] ?? 0);
                                payrollNotifyStage($conn, $salaryRow, 'finalized', $paymentReference);
                            }
                            $_SESSION['success_message'] = $successMessage;
                            header('Location: payroll.php');
                            exit();
                        }
                    } catch (Throwable $e) {
                        $error = $e->getMessage();
                    }
                }
            }
        }
    } elseif (isset($_POST['reject_salary_request'])) {
        if (!$canRejectPayroll) {
            $error = 'You are not allowed to reject salary requests';
        } else {
            $requestId = intval($_POST['request_id'] ?? 0);
            $comment = sanitize($_POST['rejection_comment'] ?? '');
            $salaryRowBeforeReject = payrollFetchWorkflowRequest($conn, $requestId);
            $rejectRole = payrollStageActingRole((string) ($salaryRowBeforeReject['status'] ?? ''));
            if ($comment === '') {
                $error = 'Rejection comment is required';
            } else {
                $stmt = $conn->prepare("UPDATE salary_requests SET rejection_comment = ?, rejected_by = ?, rejected_at = NOW(), status = 'Rejected' WHERE id = ? AND status <> 'Paid'");
                if ($stmt) {
                    $stmt->bind_param('sii', $comment, $_SESSION['user_id'], $requestId);
                    $stmt->execute();
                    $rejectActor = $rejectRole !== '' ? payrollWorkflowActorLabel($rejectRole) : (hasPermission(['Super Admin']) ? 'Super Admin' : 'Reviewer');
                    appLogActivity($conn, 'REJECT_SALARY_REQUEST', $rejectActor . ' rejected salary request #' . $requestId . ' with reason: ' . $comment, 'salary_requests', $requestId);
                    $salaryRow = payrollFetchWorkflowRequest($conn, $requestId);
                    if ($salaryRow) {
                        payrollNotifyStage($conn, $salaryRow, 'rejected', $comment);
                    }
                    $_SESSION['success_message'] = 'Salary request rejected';
                    header('Location: payroll.php');
                    exit();
                }
            }
        }
    }
}

$whereClause = $isPrivilegedPayrollViewer ? '' : 'WHERE sr.user_id = ' . (int) ($_SESSION['user_id'] ?? 0);
$salaryRequestsResult = $conn->query(
    "SELECT sr.*, 
            u.full_name AS employee_name,
            u.employee_id,
            u.id_number,
            creator.full_name AS created_by_name,
            manager.full_name AS manager_name,
            director.full_name AS director_name,
            finalizer.full_name AS finalized_by_name
     FROM salary_requests sr
     JOIN users u ON sr.user_id = u.id
     LEFT JOIN users creator ON sr.created_by = creator.id
     LEFT JOIN users manager ON sr.manager_approved_by = manager.id
     LEFT JOIN users director ON sr.director_approved_by = director.id
     LEFT JOIN users finalizer ON sr.finalized_by = finalizer.id
     {$whereClause}
     ORDER BY sr.created_at DESC"
);

$salaryRows = [];
$salarySummary = [
    'total' => 0,
    'pending' => 0,
    'paid' => 0,
    'rejected' => 0,
];

if ($salaryRequestsResult) {
    while ($row = $salaryRequestsResult->fetch_assoc()) {
        $row = payrollBuildRecord($row);
        $salaryRows[] = $row;
        $salarySummary['total']++;
        if ((string) ($row['status'] ?? '') === 'Paid') {
            $salarySummary['paid']++;
        } elseif ((string) ($row['status'] ?? '') === 'Rejected') {
            $salarySummary['rejected']++;
        } else {
            $salarySummary['pending']++;
        }
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    if (!$canExportPayroll) {
        $error = 'You are not allowed to export payroll data';
    } else {
        $paidRows = array_values(array_filter($salaryRows, static function ($row) {
            return (string) ($row['status'] ?? '') === 'Paid';
        }));
        payrollExportExcel('salary_payments_' . date('Ymd_His'), $paidRows);
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'batch-pdf') {
    if (!$canExportPayroll) {
        $error = 'You are not allowed to export payroll data';
    } else {
        $paidRows = array_values(array_filter($salaryRows, static function ($row) {
            return (string) ($row['status'] ?? '') === 'Paid';
        }));
        payrollRenderBatchPdf($paidRows);
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $requestId = intval($_GET['id'] ?? 0);
    foreach ($salaryRows as $row) {
        if ((int) ($row['id'] ?? 0) === $requestId && payrollUserCanAccess($row, $isPrivilegedPayrollViewer)) {
            payrollRenderPayslipPdf($row);
        }
    }
    $error = 'Payslip not found';
}

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <?php
    $payrollHeroActions = [];
    if ($canCreatePayroll) {
        $payrollHeroActions[] = '<a href="create_salary_request.php" class="btn btn-light"><i class="fas fa-plus-circle"></i> Create Salary Request</a>';
    }
    if ($canExportPayroll) {
        $payrollHeroActions[] = '<a href="payroll.php?export=excel" class="btn btn-outline-light" data-no-loader><i class="fas fa-file-excel"></i> Export Excel</a>';
        $payrollHeroActions[] = '<a href="payroll.php?export=batch-pdf" class="btn btn-outline-light" data-no-loader><i class="fas fa-file-pdf"></i> Export PDF</a>';
    }
    echo renderPageHero([
        'eyebrow' => 'Payroll Control',
        'title' => 'Salary Requests',
        'icon' => 'fa-money-check-dollar',
        'badges' => ['Multi-stage approvals', 'Excel export', 'Payslip PDF'],
        'actions' => $payrollHeroActions,
        'stats' => [
            ['label' => 'Total Requests', 'value' => (string) $salarySummary['total'], 'hint' => 'All salary workflow entries'],
            ['label' => 'Pending', 'value' => (string) $salarySummary['pending'], 'hint' => 'Awaiting action', 'tone' => 'warning'],
            ['label' => 'Paid', 'value' => (string) $salarySummary['paid'], 'hint' => 'Finalized payouts', 'tone' => 'success'],
            ['label' => 'Rejected', 'value' => (string) $salarySummary['rejected'], 'hint' => 'Closed without payout', 'tone' => 'danger'],
        ],
    ]);
    ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-list"></i> Salary Requests</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-modern table-workflow-actions align-middle">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Employee</th>
                                    <th>Net Salary</th>
                                    <th>Payout</th>
                                    <th>Status</th>
                                    <th>Comments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($salaryRows)): ?>
                                    <?php foreach ($salaryRows as $row): ?>
                                        <tr id="salaryRow_<?php echo (int) $row['id']; ?>">
                                            <td><?php echo htmlspecialchars($row['salary_month_label']); ?></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($row['employee_name']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars(($row['employee_id'] ?? '') ?: 'No employee ID'); ?></div>
                                            </td>
                                            <td>Tshs. <?php echo number_format((float) ($row['net_salary'] ?? 0), 2); ?></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($row['payout_method_label']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($row['payout_destination'] ?? '')); ?></div>
                                            </td>
                                            <td><span class="badge bg-<?php echo payrollStatusBadgeClass((string) ($row['status'] ?? '')); ?>"><?php echo htmlspecialchars((string) ($row['status'] ?? '')); ?></span></td>
                                            <td class="small">
                                                <?php if (!empty($row['request_comment'])): ?><div><strong>Req:</strong> <?php echo htmlspecialchars($row['request_comment']); ?></div><?php endif; ?>
                                                <?php if (!empty($row['manager_comment'])): ?><div><strong>Mgr:</strong> <?php echo htmlspecialchars($row['manager_comment']); ?></div><?php endif; ?>
                                                <?php if (!empty($row['director_comment'])): ?><div><strong>Dir:</strong> <?php echo htmlspecialchars($row['director_comment']); ?></div><?php endif; ?>
                                                <?php if (!empty($row['accountant_final_comment'])): ?><div><strong>Acc:</strong> <?php echo htmlspecialchars($row['accountant_final_comment']); ?></div><?php endif; ?>
                                                <?php if (!empty($row['rejection_comment'])): ?><div class="text-danger"><strong>Reject:</strong> <?php echo htmlspecialchars($row['rejection_comment']); ?></div><?php endif; ?>
                                            </td>
                                            <td>
                                                <?php $payrollActingRole = hasPermission(['Super Admin']) ? payrollStageActingRole((string) ($row['status'] ?? '')) : ''; ?>
                                                <?php if ($payrollActingRole !== ''): ?>
                                                    <span class="acting-role-badge">Admin as <?php echo htmlspecialchars($payrollActingRole); ?></span>
                                                <?php endif; ?>
                                                <?php if ((string) ($row['status'] ?? '') === 'Paid'): ?>
                                                    <a href="payroll.php?export=pdf&id=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-outline-danger" data-no-loader>PDF</a>
                                                <?php endif; ?>
                                                <?php if ($canManagerApprovePayroll && (string) ($row['status'] ?? '') === 'Pending Manager Approval'): ?>
                                                    <button type="button" class="btn btn-sm btn-success" onclick="openPayrollModal('manager', <?php echo (int) $row['id']; ?>)">Manager Approve</button>
                                                <?php endif; ?>
                                                <?php if ($canDirectorApprovePayroll && (string) ($row['status'] ?? '') === 'Pending Director Approval'): ?>
                                                    <button type="button" class="btn btn-sm btn-info" onclick="openPayrollModal('director', <?php echo (int) $row['id']; ?>)">Director Approve</button>
                                                <?php endif; ?>
                                                <?php if ($canFinalizePayroll && (string) ($row['status'] ?? '') === 'Pending Accountant Final Approval'): ?>
                                                    <button type="button" class="btn btn-sm btn-primary" onclick="openPayrollModal('finalize', <?php echo (int) $row['id']; ?>)">Finalize Payment</button>
                                                <?php endif; ?>
                                                <?php if ($canRejectPayroll && !in_array((string) ($row['status'] ?? ''), ['Paid', 'Rejected'], true)): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="openPayrollModal('reject', <?php echo (int) $row['id']; ?>)">Reject</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No salary requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="managerPayrollModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Manager Approval</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="POST"><input type="hidden" name="request_id" id="managerPayrollRequestId"><div class="mb-3"><label for="manager_payroll_comment" class="form-label">Manager Comment *</label><textarea class="form-control" id="manager_payroll_comment" name="manager_comment" rows="3" required></textarea></div><button type="submit" name="approve_manager_salary" class="btn btn-success">Approve</button></form></div></div></div>
</div>

<div class="modal fade" id="directorPayrollModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Director Approval</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="POST"><input type="hidden" name="request_id" id="directorPayrollRequestId"><div class="mb-3"><label for="director_payroll_comment" class="form-label">Director Comment *</label><textarea class="form-control" id="director_payroll_comment" name="director_comment" rows="3" required></textarea></div><button type="submit" name="approve_director_salary" class="btn btn-info text-white">Approve</button></form></div></div></div>
</div>

<div class="modal fade" id="finalizePayrollModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Finalize Salary Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="POST"><input type="hidden" name="request_id" id="finalizePayrollRequestId"><div class="mb-3"><label for="payment_reference" class="form-label">Payment Reference</label><input type="text" class="form-control" id="payment_reference" name="payment_reference" placeholder="Optional bank/mobile reference"></div><div class="mb-3"><label for="accountant_final_comment" class="form-label">Final Accountant Comment *</label><textarea class="form-control" id="accountant_final_comment" name="accountant_final_comment" rows="3" required></textarea></div><button type="submit" name="finalize_salary_payment" class="btn btn-primary">Confirm Payment</button></form></div></div></div>
</div>

<div class="modal fade" id="rejectPayrollModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Reject Salary Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="POST"><input type="hidden" name="request_id" id="rejectPayrollRequestId"><div class="mb-3"><label for="rejection_comment" class="form-label">Rejection Comment *</label><textarea class="form-control" id="rejection_comment" name="rejection_comment" rows="3" required></textarea></div><button type="submit" name="reject_salary_request" class="btn btn-danger">Reject</button></form></div></div></div>
</div>

<script>
function openPayrollModal(type, id) {
    if (type === 'manager') {
        document.getElementById('managerPayrollRequestId').value = id;
        document.getElementById('manager_payroll_comment').value = '';
        new bootstrap.Modal(document.getElementById('managerPayrollModal')).show();
        return;
    }

    if (type === 'director') {
        document.getElementById('directorPayrollRequestId').value = id;
        document.getElementById('director_payroll_comment').value = '';
        new bootstrap.Modal(document.getElementById('directorPayrollModal')).show();
        return;
    }

    if (type === 'finalize') {
        document.getElementById('finalizePayrollRequestId').value = id;
        document.getElementById('payment_reference').value = '';
        document.getElementById('accountant_final_comment').value = '';
        new bootstrap.Modal(document.getElementById('finalizePayrollModal')).show();
        return;
    }

    document.getElementById('rejectPayrollRequestId').value = id;
    document.getElementById('rejection_comment').value = '';
    new bootstrap.Modal(document.getElementById('rejectPayrollModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
});
</script>

<?php include '../includes/footer.php'; ?>