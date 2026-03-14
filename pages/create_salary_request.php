<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payouts.php';
require_once '../includes/payroll_workflow.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

payrollEnsureSchema($conn);
requirePermission(['Accountant', 'Super Admin']);

$error = '';
$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

function payrollFetchCreateTargetUser(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare('SELECT id, full_name, employee_id, id_number, phone, payout_phone, bank_name, bank_account_number, preferred_payout_channel FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_salary_request'])) {
    $userId = intval($_POST['user_id'] ?? 0);
    $salaryMonthInput = trim((string) ($_POST['salary_month'] ?? ''));
    $salaryMonth = $salaryMonthInput !== '' ? $salaryMonthInput . '-01' : '';
    $basicSalary = (float) ($_POST['basic_salary'] ?? 0);
    $bonusAmount = (float) ($_POST['bonus_amount'] ?? 0);
    $deductionAmount = (float) ($_POST['deduction_amount'] ?? 0);
    $requestComment = sanitize($_POST['request_comment'] ?? '');
    $netSalary = $basicSalary + $bonusAmount - $deductionAmount;
    $targetUser = payrollFetchCreateTargetUser($conn, $userId);
    $selectedPayoutChannel = strtolower(trim((string) ($_POST['payout_channel'] ?? 'mobile')));
    if (!in_array($selectedPayoutChannel, ['cash', 'bank', 'mobile'], true)) {
        $selectedPayoutChannel = 'mobile';
    }

    if (!$targetUser) {
        $error = 'Selected user was not found';
    } elseif ($salaryMonth === '') {
        $error = 'Salary month is required';
    } elseif ($netSalary <= 0) {
        $error = 'Net salary must be greater than zero';
    } else {
        $payoutChannel = $selectedPayoutChannel;
        $payoutDestination = payrollResolvePayoutDestination($targetUser, $payoutChannel);

        if ($payoutDestination === '') {
            $error = 'Selected user does not have payout details saved for the chosen payout method';
        } else {
            $stmt = $conn->prepare('INSERT INTO salary_requests (user_id, salary_month, basic_salary, bonus_amount, deduction_amount, net_salary, payout_channel, payout_destination, request_comment, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('isddddsssi', $userId, $salaryMonth, $basicSalary, $bonusAmount, $deductionAmount, $netSalary, $payoutChannel, $payoutDestination, $requestComment, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $requestId = (int) $stmt->insert_id;
                    appLogActivity($conn, 'CREATE_SALARY_REQUEST', 'Created salary request for ' . ($targetUser['full_name'] ?? ('user #' . $userId)), 'salary_requests', $requestId);
                    appSendSmsToUser($targetUser, 'ERMS: Salary request #' . $requestId . ' ya ' . date('F Y', strtotime($salaryMonth)) . ' imeandaliwa. Inasubiri Manager approval.');
                    appSendSmsToRoles($conn, ['Manager'], 'ERMS: Salary request #' . $requestId . ' kwa ' . ($targetUser['full_name'] ?? ('user #' . $userId)) . ' inasubiri approval yako.');
                    $_SESSION['success_message'] = 'Salary request created successfully';
                    header('Location: payroll.php');
                    exit();
                }
            }
            $error = 'Failed to create salary request';
        }
    }
}

$userOptions = $conn->query("SELECT id, full_name, employee_id, preferred_payout_channel, payout_phone, bank_account_number, bank_name FROM users WHERE COALESCE(is_active, 1) = 1 ORDER BY full_name ASC");

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <?php echo renderPageHero([
        'eyebrow' => 'Payroll Preparation',
        'title' => 'Create Salary Request',
        'icon' => 'fa-plus-circle',
        'subtitle' => 'Prepare a new salary payment request for manager, director, and final accountant approval in one guided form.',
        'badges' => ['Cash', 'Bank transfer', 'Mobile money'],
        'actions' => [
            '<a href="payroll.php" class="btn btn-light"><i class="fas fa-list"></i> View Salary Requests</a>',
        ],
    ]); ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-money-check-dollar"></i> Salary Request Form</h5></div>
                <div class="card-body">
                    <form method="POST" class="row g-3" id="createSalaryRequestForm">
                        <div class="col-xl-4 col-lg-6 col-12">
                            <label for="salary_user_id" class="form-label">User *</label>
                            <select class="form-select" id="salary_user_id" name="user_id" required>
                                <option value="">Select user</option>
                                <?php if ($userOptions): ?>
                                    <?php while ($user = $userOptions->fetch_assoc()): ?>
                                        <?php $defaultChannel = strtolower((string) ($user['preferred_payout_channel'] ?? 'mobile')) === 'bank' ? 'bank' : 'mobile'; ?>
                                        <?php $mobileDestination = trim((string) (($user['payout_phone'] ?? '') !== '' ? $user['payout_phone'] : '')); ?>
                                        <?php $bankDestination = trim((string) (($user['bank_name'] ?? '') . ' ' . ($user['bank_account_number'] ?? ''))); ?>
                                        <option value="<?php echo (int) $user['id']; ?>" data-default-channel="<?php echo htmlspecialchars($defaultChannel); ?>" data-mobile-destination="<?php echo htmlspecialchars($mobileDestination); ?>" data-bank-destination="<?php echo htmlspecialchars($bankDestination); ?>" data-cash-destination="Cash Payment">
                                            <?php echo htmlspecialchars(($user['full_name'] ?? 'User') . ' - ' . (($user['employee_id'] ?? '') ?: 'No employee ID')); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-12">
                            <label for="salary_month" class="form-label">Salary Month *</label>
                            <input type="month" class="form-control" id="salary_month" name="salary_month" value="<?php echo date('Y-m'); ?>" required>
                        </div>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-12">
                            <label for="basic_salary" class="form-label">Basic Salary *</label>
                            <input type="number" class="form-control payroll-calc" id="basic_salary" name="basic_salary" step="0.01" min="0" required>
                        </div>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-12">
                            <label for="bonus_amount" class="form-label">Bonus</label>
                            <input type="number" class="form-control payroll-calc" id="bonus_amount" name="bonus_amount" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-12">
                            <label for="deduction_amount" class="form-label">Deductions</label>
                            <input type="number" class="form-control payroll-calc" id="deduction_amount" name="deduction_amount" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-6 col-12">
                            <label for="net_salary_preview" class="form-label">Net Salary</label>
                            <input type="text" class="form-control" id="net_salary_preview" readonly>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-6 col-12">
                            <label for="payout_channel" class="form-label">Preferred Payout *</label>
                            <select class="form-select" id="payout_channel" name="payout_channel" required>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="mobile" selected>Mobile Money</option>
                            </select>
                        </div>
                        <div class="col-xl-4 col-lg-4 col-md-12 col-12">
                            <label for="payout_destination_preview" class="form-label">Payout Destination</label>
                            <input type="text" class="form-control" id="payout_destination_preview" readonly>
                        </div>
                        <div class="col-xl-4 col-lg-6 col-md-6 col-12">
                            <label for="payout_channel_preview" class="form-label">Selected Payout</label>
                            <input type="text" class="form-control" id="payout_channel_preview" readonly>
                        </div>
                        <div class="col-xl-8 col-lg-6 col-md-6 col-12">
                            <label for="request_comment" class="form-label">Accountant Request Comment</label>
                            <textarea class="form-control" id="request_comment" name="request_comment" rows="3" placeholder="Describe this salary payment request..."></textarea>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" name="create_salary_request" class="btn btn-primary">Create Salary Request</button>
                            <a href="payroll.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updatePayrollPreview() {
    const basic = parseFloat(document.getElementById('basic_salary')?.value || '0');
    const bonus = parseFloat(document.getElementById('bonus_amount')?.value || '0');
    const deductions = parseFloat(document.getElementById('deduction_amount')?.value || '0');
    const net = basic + bonus - deductions;
    const netField = document.getElementById('net_salary_preview');
    if (netField) {
        netField.value = net.toFixed(2);
    }

    const userSelect = document.getElementById('salary_user_id');
    const selected = userSelect ? userSelect.options[userSelect.selectedIndex] : null;
    const payoutChannelSelect = document.getElementById('payout_channel');
    const selectedChannel = payoutChannelSelect ? (payoutChannelSelect.value || 'mobile') : 'mobile';
    const destinationField = document.getElementById('payout_destination_preview');
    const channelField = document.getElementById('payout_channel_preview');
    let destination = '';
    if (selected) {
        if (selectedChannel === 'bank') {
            destination = selected.dataset.bankDestination || '';
        } else if (selectedChannel === 'cash') {
            destination = selected.dataset.cashDestination || 'Cash Payment';
        } else {
            destination = selected.dataset.mobileDestination || '';
        }
    }
    if (destinationField) {
        destinationField.value = destination;
    }
    if (channelField) {
        channelField.value = selected ? (selectedChannel === 'bank' ? 'Bank Transfer' : (selectedChannel === 'cash' ? 'Cash' : 'Mobile Money')) : '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.payroll-calc').forEach(function(input) {
        input.addEventListener('input', updatePayrollPreview);
    });
    const userSelect = document.getElementById('salary_user_id');
    if (userSelect) {
        userSelect.addEventListener('change', updatePayrollPreview);
    }
    const payoutChannelSelect = document.getElementById('payout_channel');
    if (payoutChannelSelect) {
        payoutChannelSelect.addEventListener('change', updatePayrollPreview);
    }
    updatePayrollPreview();
});
</script>

<?php include '../includes/footer.php'; ?>