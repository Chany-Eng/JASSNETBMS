<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payouts.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

requirePermission(['Super Admin']);
ensureUserIdentitySchema($conn);
snippeEnsureUserPayoutFields($conn);
$bankOptions = snippeRenderBankOptions();

$message = '';
$error = '';

$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

$userRoleOptions = [
    ['value' => 'Sales', 'icon' => 'fa-chart-line'],
    ['value' => 'Technician', 'icon' => 'fa-screwdriver-wrench'],
    ['value' => 'Store Keeper', 'icon' => 'fa-box-open'],
    ['value' => 'Manager', 'icon' => 'fa-user-tie'],
    ['value' => 'Director', 'icon' => 'fa-briefcase'],
    ['value' => 'Accountant', 'icon' => 'fa-calculator'],
    ['value' => 'Super Admin', 'icon' => 'fa-shield-halved'],
];

$closeRelativeOptions = ['Mama', 'Mke', 'Mtoto', 'Baba', 'Shangazi', 'Ndugu', 'Mlezi', 'Mwingine'];

function collectEditCloseRelativePayload(int $position): array
{
    return [
        'relationship' => sanitize($_POST['close_relative_' . $position . '_relationship'] ?? ''),
        'name' => sanitize($_POST['close_relative_' . $position . '_name'] ?? ''),
        'phone' => appNormalizeSmsPhone((string) ($_POST['close_relative_' . $position . '_phone'] ?? '')),
        'location' => sanitize($_POST['close_relative_' . $position . '_location'] ?? ''),
        'email' => sanitize($_POST['close_relative_' . $position . '_email'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_user'])) {
        $user_id = intval($_POST['user_id'] ?? 0);
        $roles = isset($_POST['role']) ? array_map('sanitize', $_POST['role']) : [];
        $role = implode(',', $roles);

        $first_name = sanitize($_POST['first_name'] ?? '');
        $middle_name = sanitize($_POST['middle_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $full_name = composeFullNameFromParts($first_name, $middle_name, $last_name);
        $username = generateUniqueUsername($conn, $first_name, $last_name, $user_id);
        $employee_id = sanitize($_POST['employee_id'] ?? '');
        $id_number = sanitize($_POST['id_number'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        $phone = appNormalizeSmsPhone((string) ($_POST['phone'] ?? ''));
        $email = sanitize($_POST['email'] ?? '');
        $closeRelativeOne = collectEditCloseRelativePayload(1);
        $closeRelativeTwo = collectEditCloseRelativePayload(2);
        $bank_name = sanitize($_POST['bank_name'] ?? '');
        $bank_account_number = sanitize($_POST['bank_account_number'] ?? '');
        $payout_phone = appNormalizeSmsPhone((string) ($_POST['payout_phone'] ?? ''));
        $preferred_payout_channel = sanitize($_POST['preferred_payout_channel'] ?? 'mobile');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($first_name === '' || $last_name === '' || empty($roles)) {
            $error = 'First name, last name, and at least one role are required';
        } else {
            $stmt = $conn->prepare('UPDATE users SET username = ?, role = ?, first_name = ?, middle_name = ?, last_name = ?, full_name = ?, employee_id = ?, id_number = ?, location = ?, gender = ?, phone = ?, email = ?, close_relative_1_relationship = ?, close_relative_1_name = ?, close_relative_1_phone = ?, close_relative_1_location = ?, close_relative_1_email = ?, close_relative_2_relationship = ?, close_relative_2_name = ?, close_relative_2_phone = ?, close_relative_2_location = ?, close_relative_2_email = ?, bank_name = ?, bank_account_number = ?, payout_phone = ?, preferred_payout_channel = ?, is_active = ? WHERE id = ?');
        }
        if (!$error && $stmt) {
            $bindTypes = str_repeat('s', 26) . 'ii';
            $stmt->bind_param($bindTypes, $username, $role, $first_name, $middle_name, $last_name, $full_name, $employee_id, $id_number, $location, $gender, $phone, $email, $closeRelativeOne['relationship'], $closeRelativeOne['name'], $closeRelativeOne['phone'], $closeRelativeOne['location'], $closeRelativeOne['email'], $closeRelativeTwo['relationship'], $closeRelativeTwo['name'], $closeRelativeTwo['phone'], $closeRelativeTwo['location'], $closeRelativeTwo['email'], $bank_name, $bank_account_number, $payout_phone, $preferred_payout_channel, $is_active, $user_id);
            if ($stmt->execute()) {
                appLogActivity($conn, 'UPDATE_USER', 'Updated user account for ' . $full_name, 'users', $user_id);
                $_SESSION['success_message'] = 'User updated successfully';
                header('Location: users.php');
                exit();
            }
            $error = 'Error updating user';
        } elseif (!$error) {
            $error = 'Could not prepare update query';
        }
    } elseif (isset($_POST['reset_password'])) {
        $user_id = intval($_POST['user_id'] ?? 0);
        $userStmt = $conn->prepare('SELECT username, full_name, phone, email FROM users WHERE id = ? LIMIT 1');
        if ($userStmt) {
            $userStmt->bind_param('i', $user_id);
            $userStmt->execute();
            $userRecord = $userStmt->get_result()->fetch_assoc();

            if (!$userRecord) {
                $error = 'User not found';
            } elseif (trim((string) ($userRecord['phone'] ?? '')) === '') {
                $error = 'Cannot reset password by SMS because this user has no phone number saved';
            } else {
                $plainPassword = appGenerateTemporaryPassword();
                $recipientName = trim((string) ($userRecord['full_name'] ?? 'User'));
                $smsResponse = appSendCredentialSms((string) $userRecord['phone'], $recipientName, (string) ($userRecord['username'] ?? ''), $plainPassword, 'reset');

                if (!is_array($smsResponse) || empty($smsResponse['success'])) {
                    $error = 'Password reset cancelled because SMS could not be sent';
                } else {
                    $new_password = password_hash($plainPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('UPDATE users SET password = ?, password_last_changed = CURDATE(), must_change_password = 1 WHERE id = ?');
                    if ($stmt) {
                        $stmt->bind_param('si', $new_password, $user_id);
                        if ($stmt->execute()) {
                            $emailResponse = appSendCredentialEmail((string) ($userRecord['email'] ?? ''), $recipientName, (string) ($userRecord['username'] ?? ''), $plainPassword, 'reset');
                            $emailAttempted = trim((string) ($userRecord['email'] ?? '')) !== '';
                            $emailSent = is_array($emailResponse) && !empty($emailResponse['success']);

                            $logMessage = 'Reset password and sent SMS to user #' . $user_id;
                            if ($emailAttempted) {
                                $logMessage .= $emailSent ? ' plus email.' : ', but email delivery failed.';
                            } else {
                                $logMessage .= '.';
                            }

                            appLogActivity($conn, 'RESET_PASSWORD', $logMessage, 'users', $user_id);
                            $_SESSION['success_message'] = ($emailAttempted && $emailSent)
                                ? 'Password reset and SMS plus email sent successfully'
                                : (($emailAttempted && !$emailSent)
                                    ? 'Password reset and SMS sent successfully, but email failed'
                                    : 'Password reset and SMS sent successfully');
                            header('Location: users.php');
                            exit();
                        } else {
                            $error = 'SMS sent but password update failed. Ask the user not to use the new password yet.';
                        }
                    } else {
                        $error = 'Could not prepare password reset query';
                    }
                }
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            $error = 'Invalid user selected for deletion';
        } elseif ($user_id === intval($_SESSION['user_id'] ?? 0)) {
            $error = 'You cannot delete your own account while logged in';
        } else {
            $userLookupStmt = $conn->prepare('SELECT full_name, is_active FROM users WHERE id = ? LIMIT 1');
            if ($userLookupStmt) {
                $userLookupStmt->bind_param('i', $user_id);
                $userLookupStmt->execute();
                $userRecord = $userLookupStmt->get_result()->fetch_assoc();
            } else {
                $userRecord = null;
            }

            if (!$userRecord) {
                $error = 'User not found or already deleted';
            } else {
                $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('i', $user_id);
                    try {
                        $stmt->execute();
                        if ($stmt->affected_rows > 0) {
                            appLogActivity($conn, 'DELETE_USER', 'Deleted user account for ' . trim((string) ($userRecord['full_name'] ?? ('user #' . $user_id))), 'users', $user_id);
                            $_SESSION['success_message'] = 'User deleted successfully';
                            header('Location: users.php');
                            exit();
                        }
                        $error = 'User not found or already deleted';
                    } catch (mysqli_sql_exception $e) {
                        if ((int) $e->getCode() === 1451) {
                            $deactivateStmt = $conn->prepare('UPDATE users SET is_active = 0 WHERE id = ?');
                            if ($deactivateStmt) {
                                $deactivateStmt->bind_param('i', $user_id);
                                $deactivateStmt->execute();

                                if ($deactivateStmt->affected_rows > 0 || (int) ($userRecord['is_active'] ?? 0) === 0) {
                                    appLogActivity($conn, 'DEACTIVATE_USER', 'Deactivated linked user account for ' . trim((string) ($userRecord['full_name'] ?? ('user #' . $user_id))), 'users', $user_id);
                                    $_SESSION['success_message'] = (int) ($userRecord['is_active'] ?? 0) === 0
                                        ? 'This user is already inactive. Linked records still prevent deletion'
                                        : 'User has linked records, so the account was deactivated instead of deleted';
                                    header('Location: users.php');
                                    exit();
                                }
                            }

                            $error = 'Unable to delete this user because linked records exist, and automatic deactivation failed.';
                        } else {
                            throw $e;
                        }
                    }
                } else {
                    $error = 'Could not prepare delete query';
                }
            }
        }
    }
}

$users = $conn->query('SELECT * FROM users ORDER BY full_name');
if (!$users) {
    $error = $error ?: 'Failed to load users list';
}
?>

<?php include '../includes/header.php'; ?>

<?php if ($success_message): ?>
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" style="font-size: 1.1rem; padding: 1rem; min-width: 360px;">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php echo renderPageHero([
    'eyebrow' => 'User Administration',
    'title' => 'User Management',
    'icon' => 'fa-users',
    'subtitle' => 'Manage staff accounts, roles, payout settings, identity details, and SMS password reset actions from one workspace.',
    'badges' => ['Role control', 'Identity records', 'SMS reset'],
    'actions' => [
        '<a href="add_user.php" class="btn btn-light"><i class="fas fa-user-plus"></i> Add User</a>',
    ],
]); ?>

<?php if ($message): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<style>
    .role-selection-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.85rem;
    }

    .role-option-card {
        position: relative;
    }

    .role-option-card input[type="checkbox"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .role-option-label {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-height: 64px;
        padding: 0.85rem 1rem;
        border: 1px solid #d8e2ed;
        border-radius: 16px;
        background: #f8fbff;
        color: #223047;
        font-weight: 700;
        cursor: pointer;
        transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }

    .role-option-label i {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(41, 105, 199, 0.1);
        color: #1d4f98;
        font-size: 0.95rem;
    }

    .role-option-card input[type="checkbox"]:checked + .role-option-label {
        border-color: #2969c7;
        background: #edf4fc;
        box-shadow: 0 16px 34px rgba(41, 105, 199, 0.12);
        transform: translateY(-1px);
    }

    .role-option-card input[type="checkbox"]:focus-visible + .role-option-label {
        outline: 3px solid rgba(41, 105, 199, 0.24);
        outline-offset: 2px;
    }

    .field-prefix-group {
        display: flex;
        align-items: stretch;
    }

    .field-prefix-group .field-prefix {
        display: inline-flex;
        align-items: center;
        padding: 0 0.9rem;
        border: 1px solid #ced4da;
        border-right: 0;
        border-radius: 0.375rem 0 0 0.375rem;
        background: #eef4fb;
        color: #36506d;
        font-weight: 700;
    }

    .field-prefix-group .form-control {
        border-radius: 0 0.375rem 0.375rem 0;
    }

    .users-table-wrap {
        overflow-x: auto;
    }

    .users-table {
        min-width: 1420px;
        margin-bottom: 0;
        vertical-align: middle;
    }

    .users-table th {
        white-space: nowrap;
        font-size: 0.78rem;
    }

    .users-table td {
        vertical-align: middle;
    }

    .users-table .user-name-cell {
        min-width: 200px;
        font-weight: 700;
        color: #223047;
    }

    .users-table .user-role-cell {
        min-width: 220px;
    }

    .users-table .user-contact-cell {
        min-width: 180px;
    }

    .users-table .user-actions-cell {
        min-width: 250px;
    }

    .user-role-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
    }

    .user-role-count {
        display: inline-flex;
        align-items: center;
        margin-bottom: 0.45rem;
        padding: 0.28rem 0.58rem;
        border-radius: 999px;
        background: #223047;
        color: #fff;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .user-role-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.32rem 0.6rem;
        border-radius: 999px;
        background: #edf4fc;
        color: #1d4f98;
        border: 1px solid #d4e2f3;
        font-size: 0.76rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .user-actions-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
    }

    .user-actions-group .btn {
        white-space: nowrap;
    }

    @media (max-width: 767.98px) {
        .users-table {
            min-width: 1200px;
        }
    }
</style>

<div class="row" id="users-list">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Users</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive users-table-wrap">
                    <table class="table table-striped table-modern table-workflow-actions users-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Employee ID</th>
                                <th>Gender</th>
                                <th>Role</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users && $users->num_rows > 0): ?>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                <?php $nameParts = splitFullNameParts((string) ($user['full_name'] ?? '')); ?>
                                <?php $firstName = trim((string) (($user['first_name'] ?? '') !== '' ? $user['first_name'] : ($nameParts['first_name'] ?? ''))); ?>
                                <?php $middleName = trim((string) (($user['middle_name'] ?? '') !== '' ? $user['middle_name'] : ($nameParts['middle_name'] ?? ''))); ?>
                                <?php $lastName = trim((string) (($user['last_name'] ?? '') !== '' ? $user['last_name'] : ($nameParts['last_name'] ?? ''))); ?>
                                <?php $displayFullName = composeFullNameFromParts($firstName, $middleName, $lastName); ?>
                                <?php $roleBadges = appParseRoleList((string) ($user['role'] ?? '')); ?>
                                <?php $roleCount = count($roleBadges); ?>
                                <tr id="userRow_<?php echo (int) $user['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                                    data-first_name="<?php echo htmlspecialchars($firstName); ?>"
                                    data-middle_name="<?php echo htmlspecialchars($middleName); ?>"
                                    data-last_name="<?php echo htmlspecialchars($lastName); ?>"
                                    data-full_name="<?php echo htmlspecialchars($displayFullName); ?>"
                                    data-employee_id="<?php echo htmlspecialchars($user['employee_id'] ?? ''); ?>"
                                    data-id_number="<?php echo htmlspecialchars($user['id_number'] ?? ''); ?>"
                                    data-role="<?php echo htmlspecialchars($user['role'] ?? ''); ?>"
                                    data-location="<?php echo htmlspecialchars($user['location'] ?? ''); ?>"
                                    data-gender="<?php echo htmlspecialchars($user['gender'] ?? ''); ?>"
                                    data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                    data-email="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                    data-close_relative_1_relationship="<?php echo htmlspecialchars($user['close_relative_1_relationship'] ?? ''); ?>"
                                    data-close_relative_1_name="<?php echo htmlspecialchars($user['close_relative_1_name'] ?? ''); ?>"
                                    data-close_relative_1_phone="<?php echo htmlspecialchars($user['close_relative_1_phone'] ?? ''); ?>"
                                    data-close_relative_1_location="<?php echo htmlspecialchars($user['close_relative_1_location'] ?? ''); ?>"
                                    data-close_relative_1_email="<?php echo htmlspecialchars($user['close_relative_1_email'] ?? ''); ?>"
                                    data-close_relative_2_relationship="<?php echo htmlspecialchars($user['close_relative_2_relationship'] ?? ''); ?>"
                                    data-close_relative_2_name="<?php echo htmlspecialchars($user['close_relative_2_name'] ?? ''); ?>"
                                    data-close_relative_2_phone="<?php echo htmlspecialchars($user['close_relative_2_phone'] ?? ''); ?>"
                                    data-close_relative_2_location="<?php echo htmlspecialchars($user['close_relative_2_location'] ?? ''); ?>"
                                    data-close_relative_2_email="<?php echo htmlspecialchars($user['close_relative_2_email'] ?? ''); ?>"
                                    data-bank_name="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>"
                                    data-bank_account_number="<?php echo htmlspecialchars($user['bank_account_number'] ?? ''); ?>"
                                    data-payout_phone="<?php echo htmlspecialchars($user['payout_phone'] ?? ''); ?>"
                                    data-preferred_payout_channel="<?php echo htmlspecialchars($user['preferred_payout_channel'] ?? 'mobile'); ?>"
                                    data-is_active="<?php echo (int) ($user['is_active'] ?? 0); ?>">
                                    <td class="user-name-cell"><?php echo htmlspecialchars($displayFullName); ?></td>
                                    <td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($user['employee_id'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars(($user['gender'] ?? '') ?: 'N/A'); ?></td>
                                    <td class="user-role-cell">
                                        <div class="user-role-count"><?php echo $roleCount; ?> <?php echo $roleCount === 1 ? 'Role' : 'Roles'; ?></div>
                                        <div class="user-role-badges">
                                            <?php if ($roleBadges !== []): ?>
                                                <?php foreach ($roleBadges as $roleBadge): ?>
                                                    <span class="user-role-badge"><?php echo htmlspecialchars($roleBadge); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="user-contact-cell"><?php echo htmlspecialchars(($user['phone'] ?? '') ?: 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo !empty($user['is_active']) ? 'success' : 'danger'; ?>">
                                            <?php echo !empty($user['is_active']) ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="user-actions-cell">
                                        <div class="user-actions-group">
                                        <button class="btn btn-sm btn-outline-info" onclick="viewUser(<?php echo (int) $user['id']; ?>)">View</button>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo (int) $user['id']; ?>)">Edit</button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?php echo (int) $user['id']; ?>)">Reset Password</button>
                                        <?php if ((int) $user['id'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo (int) $user['id']; ?>)">Delete</button>
                                        <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
                            </div>
                            <div class="mb-3">
                                <label for="edit_last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="edit_username" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="edit_employee_id" class="form-label">Employee ID *</label>
                                <input type="text" class="form-control" id="edit_employee_id" name="employee_id" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_id_number" class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="edit_id_number" name="id_number">
                            </div>
                            <div class="mb-3">
                                <label for="edit_location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="edit_location" name="location">
                            </div>
                            <div class="mb-3">
                                <label for="edit_gender" class="form-label">Gender</label>
                                <select class="form-select" id="edit_gender" name="gender">
                                    <option value="">Select gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Roles *</label>
                                <div id="edit_role" class="role-selection-grid" role="group" aria-label="Select one or more roles for editing">
                                    <?php foreach ($userRoleOptions as $option): ?>
                                        <div class="role-option-card">
                                            <input type="checkbox" id="edit_role_<?php echo strtolower(str_replace(' ', '_', $option['value'])); ?>" name="role[]" value="<?php echo htmlspecialchars($option['value']); ?>">
                                            <label class="role-option-label" for="edit_role_<?php echo strtolower(str_replace(' ', '_', $option['value'])); ?>"><i class="fas <?php echo htmlspecialchars($option['icon']); ?>"></i><span><?php echo htmlspecialchars($option['value']); ?></span></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Select one or more roles for this user account.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone">
                            </div>
                            <div class="mb-3">
                                <label for="edit_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="edit_email" name="email">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Close Relative 1</label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <select class="form-select" id="edit_close_relative_1_relationship" name="close_relative_1_relationship">
                                            <option value="">Relationship</option>
                                            <?php foreach ($closeRelativeOptions as $option): ?>
                                                <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-8">
                                        <input type="text" class="form-control" id="edit_close_relative_1_name" name="close_relative_1_name" placeholder="Full name">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="tel" class="form-control" id="edit_close_relative_1_phone" name="close_relative_1_phone" placeholder="Phone number">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="email" class="form-control" id="edit_close_relative_1_email" name="close_relative_1_email" placeholder="Email if available">
                                    </div>
                                    <div class="col-12">
                                        <input type="text" class="form-control" id="edit_close_relative_1_location" name="close_relative_1_location" placeholder="Location">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Close Relative 2</label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <select class="form-select" id="edit_close_relative_2_relationship" name="close_relative_2_relationship">
                                            <option value="">Relationship</option>
                                            <?php foreach ($closeRelativeOptions as $option): ?>
                                                <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-8">
                                        <input type="text" class="form-control" id="edit_close_relative_2_name" name="close_relative_2_name" placeholder="Full name">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="tel" class="form-control" id="edit_close_relative_2_phone" name="close_relative_2_phone" placeholder="Phone number">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="email" class="form-control" id="edit_close_relative_2_email" name="close_relative_2_email" placeholder="Email if available">
                                    </div>
                                    <div class="col-12">
                                        <input type="text" class="form-control" id="edit_close_relative_2_location" name="close_relative_2_location" placeholder="Location">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_bank_name" class="form-label">Bank</label>
                                <select class="form-select" id="edit_bank_name" name="bank_name">
                                    <?php echo $bankOptions; ?>
                                </select>
                                <div class="form-text">Stored value is the Snippe bank code used for automated bank payouts.</div>
                                <div class="mt-2">
                                    <a href="supported_banks.php" class="small text-decoration-none"><i class="fas fa-university"></i> View supported bank codes</a>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_bank_account_number" class="form-label">Bank Account No.</label>
                                <input type="text" class="form-control" id="edit_bank_account_number" name="bank_account_number">
                            </div>
                            <div class="mb-3">
                                <label for="edit_payout_phone" class="form-label">Payout Phone</label>
                                <div class="field-prefix-group">
                                    <span class="field-prefix">255</span>
                                    <input type="tel" class="form-control" id="edit_payout_phone" name="payout_phone" placeholder="7XXXXXXXX" inputmode="numeric" maxlength="12">
                                </div>
                                <div class="form-text">Mobile payout number will automatically start with 255.</div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_preferred_payout_channel" class="form-label">Preferred Payout Channel</label>
                                <select class="form-select" id="edit_preferred_payout_channel" name="preferred_payout_channel">
                                    <option value="mobile">Mobile Money</option>
                                    <option value="bank">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" checked>
                                    <label class="form-check-label" for="edit_is_active">Active User</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>First Name:</strong> <span id="view_first_name">-</span></div>
                    <div class="col-md-6"><strong>Middle Name:</strong> <span id="view_middle_name">-</span></div>
                    <div class="col-md-6"><strong>Last Name:</strong> <span id="view_last_name">-</span></div>
                    <div class="col-md-6"><strong>Full Name:</strong> <span id="view_full_name">-</span></div>
                    <div class="col-md-6"><strong>Username:</strong> <span id="view_username">-</span></div>
                    <div class="col-md-6"><strong>Employee ID:</strong> <span id="view_employee_id">-</span></div>
                    <div class="col-md-6"><strong>ID Number:</strong> <span id="view_id_number">-</span></div>
                    <div class="col-md-6"><strong>Gender:</strong> <span id="view_gender">-</span></div>
                    <div class="col-md-6"><strong>Location:</strong> <span id="view_location">-</span></div>
                    <div class="col-md-6"><strong>Phone:</strong> <span id="view_phone">-</span></div>
                    <div class="col-md-6"><strong>Email:</strong> <span id="view_email">-</span></div>
                    <div class="col-md-6"><strong>Close Relative 1:</strong> <span id="view_close_relative_1">-</span></div>
                    <div class="col-md-6"><strong>Close Relative 2:</strong> <span id="view_close_relative_2">-</span></div>
                    <div class="col-md-6"><strong>Status:</strong> <span id="view_status">-</span></div>
                    <div class="col-md-6"><strong>Roles:</strong> <span id="view_role">-</span></div>
                    <div class="col-md-6"><strong>Preferred Payout:</strong> <span id="view_preferred_channel">-</span></div>
                    <div class="col-md-6"><strong>Bank:</strong> <span id="view_bank_name">-</span></div>
                    <div class="col-md-6"><strong>Bank Account:</strong> <span id="view_bank_account_number">-</span></div>
                    <div class="col-md-6"><strong>Payout Phone:</strong> <span id="view_payout_phone">-</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="resetPasswordMessage">A new one-time temporary password will be generated and sent by SMS, and also by email if this user has an email address saved.</p>
                <form method="POST" id="resetPasswordForm">
                    <input type="hidden" name="user_id" id="resetPasswordUserId">
                    <button type="submit" name="reset_password" class="btn btn-warning">Yes, Reset Password</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                <form method="POST" id="deleteUserForm">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" name="delete_user" class="btn btn-danger">Yes, Delete User</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function viewUser(id) {
    const row = document.getElementById('userRow_' + id);
    if (!row) return;

    document.getElementById('view_first_name').textContent = row.dataset.first_name || 'N/A';
    document.getElementById('view_middle_name').textContent = row.dataset.middle_name || 'N/A';
    document.getElementById('view_last_name').textContent = row.dataset.last_name || 'N/A';
    document.getElementById('view_full_name').textContent = row.dataset.full_name || 'N/A';
    document.getElementById('view_username').textContent = row.dataset.username || 'N/A';
    document.getElementById('view_employee_id').textContent = row.dataset.employee_id || 'N/A';
    document.getElementById('view_id_number').textContent = row.dataset.id_number || 'N/A';
    document.getElementById('view_gender').textContent = row.dataset.gender || 'N/A';
    document.getElementById('view_location').textContent = row.dataset.location || 'N/A';
    document.getElementById('view_phone').textContent = row.dataset.phone || 'N/A';
    document.getElementById('view_email').textContent = row.dataset.email || 'N/A';
    document.getElementById('view_close_relative_1').textContent = formatCloseRelative(row, 1);
    document.getElementById('view_close_relative_2').textContent = formatCloseRelative(row, 2);
    document.getElementById('view_status').textContent = row.dataset.is_active === '1' ? 'Active' : 'Inactive';
    document.getElementById('view_role').textContent = formatRoleList(row.dataset.role);
    document.getElementById('view_preferred_channel').textContent = row.dataset.preferred_payout_channel === 'bank' ? 'Bank Transfer' : 'Mobile Money';
    document.getElementById('view_bank_name').textContent = row.dataset.bank_name || 'N/A';
    document.getElementById('view_bank_account_number').textContent = row.dataset.bank_account_number || 'N/A';
    document.getElementById('view_payout_phone').textContent = row.dataset.payout_phone || 'N/A';

    new bootstrap.Modal(document.getElementById('viewUserModal')).show();
}

function formatCloseRelative(row, position) {
    const relationship = row.dataset['close_relative_' + position + '_relationship'] || '';
    const name = row.dataset['close_relative_' + position + '_name'] || '';
    const phone = row.dataset['close_relative_' + position + '_phone'] || '';
    const location = row.dataset['close_relative_' + position + '_location'] || '';
    const email = row.dataset['close_relative_' + position + '_email'] || '';
    const parts = [relationship, name, phone, location, email].filter(Boolean);
    return parts.length > 0 ? parts.join(' | ') : 'N/A';
}

function formatRoleList(roleValue) {
    if (!roleValue) {
        return 'N/A';
    }

    const roles = roleValue.split(',').map(role => role.trim()).filter(Boolean);
    return roles.length > 0 ? roles.join(' | ') + ' (' + roles.length + ' ' + (roles.length === 1 ? 'role' : 'roles') + ')' : 'N/A';
}

function editUser(id) {
    const row = document.getElementById('userRow_' + id);
    if (!row) return;

    document.getElementById('editUserId').value = id;
    document.getElementById('edit_first_name').value = row.dataset.first_name || '';
    document.getElementById('edit_middle_name').value = row.dataset.middle_name || '';
    document.getElementById('edit_last_name').value = row.dataset.last_name || '';
    document.getElementById('edit_username').value = row.dataset.username || '';
    document.getElementById('edit_employee_id').value = row.dataset.employee_id || '';
    document.getElementById('edit_id_number').value = row.dataset.id_number || '';
    document.getElementById('edit_location').value = row.dataset.location || '';
    document.getElementById('edit_gender').value = row.dataset.gender || '';
    document.getElementById('edit_phone').value = row.dataset.phone || '';
    document.getElementById('edit_email').value = row.dataset.email || '';
    document.getElementById('edit_close_relative_1_relationship').value = row.dataset.close_relative_1_relationship || '';
    document.getElementById('edit_close_relative_1_name').value = row.dataset.close_relative_1_name || '';
    document.getElementById('edit_close_relative_1_phone').value = row.dataset.close_relative_1_phone || '';
    document.getElementById('edit_close_relative_1_location').value = row.dataset.close_relative_1_location || '';
    document.getElementById('edit_close_relative_1_email').value = row.dataset.close_relative_1_email || '';
    document.getElementById('edit_close_relative_2_relationship').value = row.dataset.close_relative_2_relationship || '';
    document.getElementById('edit_close_relative_2_name').value = row.dataset.close_relative_2_name || '';
    document.getElementById('edit_close_relative_2_phone').value = row.dataset.close_relative_2_phone || '';
    document.getElementById('edit_close_relative_2_location').value = row.dataset.close_relative_2_location || '';
    document.getElementById('edit_close_relative_2_email').value = row.dataset.close_relative_2_email || '';
    document.getElementById('edit_bank_name').value = row.dataset.bank_name || '';
    document.getElementById('edit_bank_account_number').value = row.dataset.bank_account_number || '';
    document.getElementById('edit_payout_phone').value = row.dataset.payout_phone || '';
    document.getElementById('edit_preferred_payout_channel').value = row.dataset.preferred_payout_channel || 'mobile';

    const roleData = row.dataset.role || '';
    const roles = roleData ? roleData.split(',').map(r => r.trim()) : [];
    document.querySelectorAll('#edit_role input[type="checkbox"]').forEach(function(input) {
        input.checked = roles.includes(input.value);
    });

    document.getElementById('edit_is_active').checked = row.dataset.is_active === '1';
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function resetPassword(id) {
    const row = document.getElementById('userRow_' + id);
    document.getElementById('resetPasswordUserId').value = id;
    if (row) {
        const fullName = row.dataset.full_name || 'This user';
        const phone = row.dataset.phone || 'no phone number saved';
        const email = row.dataset.email || '';
        document.getElementById('resetPasswordMessage').textContent = fullName + ' will receive a one-time temporary password by SMS at ' + phone + (email ? ' and by email at ' + email + '.' : '.');
    }
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}

function deleteUser(id) {
    document.getElementById('deleteUserId').value = id;
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    const toast = document.getElementById('successToast');
    if (toast) {
        const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
        bsToast.show();
    }

    function bindUsernamePreview(firstId, lastId, usernameId) {
        const firstInput = document.getElementById(firstId);
        const lastInput = document.getElementById(lastId);
        const usernameInput = document.getElementById(usernameId);
        if (!firstInput || !lastInput || !usernameInput) {
            return;
        }

        function slugPart(value) {
            return value
                .toLowerCase()
                .trim()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '.')
                .replace(/^\.+|\.+$/g, '');
        }

        function updatePreview() {
            const username = [slugPart(firstInput.value), slugPart(lastInput.value)].filter(Boolean).join('.');
            usernameInput.value = username;
        }

        firstInput.addEventListener('input', updatePreview);
        lastInput.addEventListener('input', updatePreview);
        updatePreview();
    }

    function normalizeTanzaniaPhone(value) {
        const digits = String(value || '').replace(/\D/g, '');
        if (digits === '') {
            return '';
        }

        if (digits.startsWith('255')) {
            return digits.slice(0, 12);
        }

        if (digits.startsWith('0')) {
            return ('255' + digits.slice(1)).slice(0, 12);
        }

        if (digits.startsWith('7') || digits.startsWith('6')) {
            return ('255' + digits).slice(0, 12);
        }

        return ('255' + digits.replace(/^255+/, '')).slice(0, 12);
    }

    ['edit_payout_phone', 'edit_phone', 'edit_close_relative_1_phone', 'edit_close_relative_2_phone'].forEach(function(fieldId) {
        const input = document.getElementById(fieldId);
        if (!input) {
            return;
        }

        input.addEventListener('focus', function() {
            input.value = normalizeTanzaniaPhone(input.value);
        });

        input.addEventListener('input', function() {
            input.value = normalizeTanzaniaPhone(input.value);
        });

        input.addEventListener('blur', function() {
            input.value = normalizeTanzaniaPhone(input.value);
        });
    });

    bindUsernamePreview('edit_first_name', 'edit_last_name', 'edit_username');
});
</script>

<?php include '../includes/footer.php'; ?>