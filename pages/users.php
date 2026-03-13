<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payouts.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

requirePermission(['Super Admin']);
snippeEnsureUserPayoutFields($conn);
$bankOptions = snippeRenderBankOptions();

$message = '';
$error = '';

$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

function generateTemporaryPassword($length = 10) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
    $maxIndex = strlen($alphabet) - 1;
    $password = '';

    for ($index = 0; $index < $length; $index++) {
        $password .= $alphabet[random_int(0, $maxIndex)];
    }

    return $password;
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
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $bank_name = sanitize($_POST['bank_name'] ?? '');
        $bank_account_number = sanitize($_POST['bank_account_number'] ?? '');
        $payout_phone = sanitize($_POST['payout_phone'] ?? '');
        $preferred_payout_channel = sanitize($_POST['preferred_payout_channel'] ?? 'mobile');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($first_name === '' || $last_name === '' || empty($roles)) {
            $error = 'First name, last name, and at least one role are required';
        } else {
            $stmt = $conn->prepare('UPDATE users SET username = ?, role = ?, first_name = ?, middle_name = ?, last_name = ?, full_name = ?, employee_id = ?, id_number = ?, location = ?, gender = ?, phone = ?, email = ?, bank_name = ?, bank_account_number = ?, payout_phone = ?, preferred_payout_channel = ?, is_active = ? WHERE id = ?');
        }
        if (!$error && $stmt) {
            $stmt->bind_param('ssssssssssssssssii', $username, $role, $first_name, $middle_name, $last_name, $full_name, $employee_id, $id_number, $location, $gender, $phone, $email, $bank_name, $bank_account_number, $payout_phone, $preferred_payout_channel, $is_active, $user_id);
            if ($stmt->execute()) {
                appLogActivity($conn, 'UPDATE_USER', 'Updated user account for ' . $full_name, 'users', $user_id);
                $_SESSION['success_message'] = 'User updated successfully';
                header('Location: users.php');
                exit();
            }
            $error = 'Error updating user';
        } else {
            $error = 'Could not prepare update query';
        }
    } elseif (isset($_POST['reset_password'])) {
        $user_id = intval($_POST['user_id'] ?? 0);
        $userStmt = $conn->prepare('SELECT full_name, phone FROM users WHERE id = ? LIMIT 1');
        if ($userStmt) {
            $userStmt->bind_param('i', $user_id);
            $userStmt->execute();
            $userRecord = $userStmt->get_result()->fetch_assoc();

            if (!$userRecord) {
                $error = 'User not found';
            } elseif (trim((string) ($userRecord['phone'] ?? '')) === '') {
                $error = 'Cannot reset password by SMS because this user has no phone number saved';
            } else {
                $plainPassword = generateTemporaryPassword();
                $recipientName = trim((string) ($userRecord['full_name'] ?? 'User'));
                $smsMessage = 'Habari ' . $recipientName . ', password yako mpya ya JASSNET ni: ' . $plainPassword . '. Tafadhali ibadilishe mara baada ya kuingia.';
                $smsResponse = jassnet_sms((string) $userRecord['phone'], $smsMessage);

                if ($smsResponse === null) {
                    $error = 'Password reset cancelled because SMS could not be sent';
                } else {
                    $new_password = password_hash($plainPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('UPDATE users SET password = ?, password_last_changed = CURDATE() WHERE id = ?');
                    if ($stmt) {
                        $stmt->bind_param('si', $new_password, $user_id);
                        if ($stmt->execute()) {
                                appLogActivity($conn, 'RESET_PASSWORD', 'Reset password and sent SMS to user #' . $user_id, 'users', $user_id);
                            $_SESSION['success_message'] = 'Password reset and SMS sent successfully';
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
            $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        appLogActivity($conn, 'DELETE_USER', 'Deleted user account #' . $user_id, 'users', $user_id);
                        $_SESSION['success_message'] = 'User deleted successfully';
                        header('Location: users.php');
                        exit();
                    }
                    $error = 'User not found or already deleted';
                } else {
                    $error = 'Unable to delete user. This user may be linked to existing records.';
                }
            } else {
                $error = 'Could not prepare delete query';
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
                <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-users"></i> User Management</h2>
            <a href="add_user.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Add User
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><?php echo htmlspecialchars(formatSuccessMessage($message)); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row" id="users-list">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Users</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>ID No.</th>
                                <th>Username</th>
                                <th>Employee ID</th>
                                <th>Location</th>
                                <th>Gender</th>
                                <th>Role</th>
                                <th>Phone</th>
                                <th>Email</th>
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
                                    data-bank_name="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>"
                                    data-bank_account_number="<?php echo htmlspecialchars($user['bank_account_number'] ?? ''); ?>"
                                    data-payout_phone="<?php echo htmlspecialchars($user['payout_phone'] ?? ''); ?>"
                                    data-preferred_payout_channel="<?php echo htmlspecialchars($user['preferred_payout_channel'] ?? 'mobile'); ?>"
                                    data-is_active="<?php echo (int) ($user['is_active'] ?? 0); ?>">
                                    <td><?php echo htmlspecialchars($displayFullName); ?></td>
                                    <td><?php echo htmlspecialchars(($user['id_number'] ?? '') ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($user['employee_id'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars(($user['location'] ?? '') ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(($user['gender'] ?? '') ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['role'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars(($user['phone'] ?? '') ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(($user['email'] ?? '') ?: 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo !empty($user['is_active']) ? 'success' : 'danger'; ?>">
                                            <?php echo !empty($user['is_active']) ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info" onclick="viewUser(<?php echo (int) $user['id']; ?>)">View</button>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo (int) $user['id']; ?>)">Edit</button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?php echo (int) $user['id']; ?>)">Reset Password</button>
                                        <?php if ((int) $user['id'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo (int) $user['id']; ?>)">Delete</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted">No users found.</td>
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
                                <label for="edit_role" class="form-label">Roles *</label>
                                <select class="form-select" id="edit_role" name="role[]" multiple required>
                                    <option value="Sales">Sales</option>
                                    <option value="Technician">Technician</option>
                                    <option value="Store Keeper">Store Keeper</option>
                                    <option value="Manager">Manager</option>
                                    <option value="Director">Director</option>
                                    <option value="Accountant">Accountant</option>
                                    <option value="Super Admin">Super Admin</option>
                                </select>
                                <div class="form-text">Hold Ctrl (Cmd on Mac) to select multiple roles.</div>
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
                                <input type="tel" class="form-control" id="edit_payout_phone" name="payout_phone">
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
                <p id="resetPasswordMessage">A new temporary password will be generated and sent by SMS to this user's phone number.</p>
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
    document.getElementById('view_status').textContent = row.dataset.is_active === '1' ? 'Active' : 'Inactive';
    document.getElementById('view_role').textContent = row.dataset.role || 'N/A';
    document.getElementById('view_preferred_channel').textContent = row.dataset.preferred_payout_channel === 'bank' ? 'Bank Transfer' : 'Mobile Money';
    document.getElementById('view_bank_name').textContent = row.dataset.bank_name || 'N/A';
    document.getElementById('view_bank_account_number').textContent = row.dataset.bank_account_number || 'N/A';
    document.getElementById('view_payout_phone').textContent = row.dataset.payout_phone || 'N/A';

    new bootstrap.Modal(document.getElementById('viewUserModal')).show();
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
    document.getElementById('edit_bank_name').value = row.dataset.bank_name || '';
    document.getElementById('edit_bank_account_number').value = row.dataset.bank_account_number || '';
    document.getElementById('edit_payout_phone').value = row.dataset.payout_phone || '';
    document.getElementById('edit_preferred_payout_channel').value = row.dataset.preferred_payout_channel || 'mobile';

    const roleSelect = document.getElementById('edit_role');
    const roleData = row.dataset.role || '';
    const roles = roleData ? roleData.split(',').map(r => r.trim()) : [];
    for (let i = 0; i < roleSelect.options.length; i++) {
        roleSelect.options[i].selected = roles.includes(roleSelect.options[i].value);
    }

    document.getElementById('edit_is_active').checked = row.dataset.is_active === '1';
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function resetPassword(id) {
    const row = document.getElementById('userRow_' + id);
    document.getElementById('resetPasswordUserId').value = id;
    if (row) {
        const fullName = row.dataset.full_name || 'This user';
        const phone = row.dataset.phone || 'no phone number saved';
        document.getElementById('resetPasswordMessage').textContent = fullName + ' will receive a new temporary password by SMS at ' + phone + '.';
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

    bindUsernamePreview('edit_first_name', 'edit_last_name', 'edit_username');
});
</script>

<?php include '../includes/footer.php'; ?>