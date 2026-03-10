<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Super Admin']);

$message = '';
$error = '';

// Check for success message
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

// helper to generate new employee IDs
function generateEmployeeId($conn) {
    $year = date('Y');
    $prefix = "JSN/Z$year/";
    $result = $conn->query("SELECT employee_id FROM users WHERE employee_id LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $last = $row['employee_id'];
        $num = intval(substr($last, strrpos($last, '/') + 1));
        $num++;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $username = sanitize($_POST['username']);
        $plain_pass = $_POST['password'];
        $password = password_hash($plain_pass, PASSWORD_DEFAULT);
        // roles come as array
        $roles = isset($_POST['role']) ? array_map('sanitize', $_POST['role']) : [];
        $role = implode(',', $roles);
        $full_name = sanitize($_POST['full_name']);
        // auto-generate employee id
        $employee_id = generateEmployeeId($conn);
        $location = sanitize($_POST['location']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name, employee_id, location, phone, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $username, $password, $role, $full_name, $employee_id, $location, $phone, $email);
        
        if ($stmt->execute()) {
            $message = 'User added successfully';
            // send SMS if phone starts with 255
            if (strpos($phone, '255') === 0) {
                $smsMsg = "karibu kwenye familia ya JASSNET username $username , password $plain_pass and Employee ID $employee_id";
                jassnet_sms($phone, $smsMsg);
            }
        } else {
            $error = 'Error adding user';
        }
    } elseif (isset($_POST['update_user'])) {
        $user_id = intval($_POST['user_id']);
        $roles = isset($_POST['role']) ? array_map('sanitize', $_POST['role']) : [];
        $role = implode(',', $roles);
        $full_name = sanitize($_POST['full_name']);
        $employee_id = sanitize($_POST['employee_id']);
        $location = sanitize($_POST['location']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE users SET role = ?, full_name = ?, employee_id = ?, location = ?, phone = ?, email = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("ssssssii", $role, $full_name, $employee_id, $location, $phone, $email, $is_active, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'User added successfully';
            header("Location: users.php");
            exit();
        } else {
            $error = 'Error updating user';
        }
    } elseif (isset($_POST['reset_password'])) {
        $user_id = intval($_POST['user_id']);
        $role = sanitize($_POST['role']);
        $full_name = sanitize($_POST['full_name']);
        $employee_id = sanitize($_POST['employee_id']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE users SET role = ?, full_name = ?, employee_id = ?, phone = ?, email = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("sssssii", $role, $full_name, $employee_id, $phone, $email, $is_active, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'User updated successfully';
            header("Location: users.php");
            exit();
        } else {
            $error = 'Error updating user';
        }
    } elseif (isset($_POST['reset_password'])) {
        $user_id = intval($_POST['user_id']);
        $new_password = password_hash('password', PASSWORD_DEFAULT); // Default password
        
        $stmt = $conn->prepare("UPDATE users SET password = ?, password_last_changed = CURDATE() WHERE id = ?");
        $stmt->bind_param("si", $new_password, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Password reset to "password"';
            header("Location: users.php");
            exit();
        } else {
            $error = 'Error resetting password';
        }
    }
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY full_name");
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
        <h2><i class="fas fa-users"></i> User Management</h2>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-plus"></i> Add New User</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" value="password" required>
                                <div class="form-text">Default password is "password"</div>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Roles *</label>
                                <select class="form-select" id="role" name="role[]" multiple required>
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
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="employee_id" class="form-label">Employee ID *</label>
                                <input type="text" class="form-control" id="employee_id" name="employee_id" readonly value="<?php echo generateEmployeeId($conn); ?>">
                                <div class="form-text">Automatically generated</div>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location">
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
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
                                <th>Username</th>
                                <th>Employee ID</th>
                                <th>Location</th>
                                <th>Role</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users->fetch_assoc()): ?>
                            <tr id="userRow_<?php echo $user['id']; ?>"
                                    data-full_name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                    data-employee_id="<?php echo htmlspecialchars($user['employee_id']); ?>"
                                    data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                    data-location="<?php echo htmlspecialchars($user['location']); ?>"
                                    data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                    data-email="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                    data-is_active="<?php echo $user['is_active']; ?>">
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['employee_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['location']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo $user['id']; ?>)">Edit</button>
                                    <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?php echo $user['id']; ?>)">Reset Password</button>
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

<!-- Edit User Modal -->
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
                                <label for="edit_full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_employee_id" class="form-label">Employee ID *</label>
                                <input type="text" class="form-control" id="edit_employee_id" name="employee_id" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="edit_location" name="location">
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
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" checked>
                                    <label class="form-check-label" for="edit_is_active">
                                        Active User
                                    </label>
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

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reset this user's password to "password"?</p>
                <form method="POST" id="resetPasswordForm">
                    <input type="hidden" name="user_id" id="resetPasswordUserId">
                    <button type="submit" name="reset_password" class="btn btn-warning">Yes, Reset Password</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editUser(id) {
    const row = document.getElementById('userRow_' + id);
    if (!row) return;
    // populate modal fields
    document.getElementById('editUserId').value = id;
    document.getElementById('edit_full_name').value = row.dataset.full_name;
    document.getElementById('edit_employee_id').value = row.dataset.employee_id;
    document.getElementById('edit_location').value = row.dataset.location;
    document.getElementById('edit_phone').value = row.dataset.phone;
    document.getElementById('edit_email').value = row.dataset.email;
    
    // set roles multi-select
    const roleSelect = document.getElementById('edit_role');
    const roles = row.dataset.role.split(',').map(r => r.trim());
    for (let i = 0; i < roleSelect.options.length; i++) {
        roleSelect.options[i].selected = roles.includes(roleSelect.options[i].value);
    }
    
    // active status
    document.getElementById('edit_is_active').checked = row.dataset.is_active == '1';
    
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function resetPassword(id) {
    document.getElementById('resetPasswordUserId').value = id;
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}
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