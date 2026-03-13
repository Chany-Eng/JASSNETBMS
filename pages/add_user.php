<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payouts.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Super Admin']);
snippeEnsureUserPayoutFields($conn);
$bankOptions = snippeRenderBankOptions();

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
        $first_name = sanitize($_POST['first_name'] ?? '');
        $middle_name = sanitize($_POST['middle_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $full_name = composeFullNameFromParts($first_name, $middle_name, $last_name);
        $username = generateUniqueUsername($conn, $first_name, $last_name);
        $plain_pass = $_POST['password'];
        $password = password_hash($plain_pass, PASSWORD_DEFAULT);
        $roles = isset($_POST['role']) ? array_map('sanitize', $_POST['role']) : [];
        $role = implode(',', $roles);
        $employee_id = generateEmployeeId($conn);
        $id_number = sanitize($_POST['id_number'] ?? '');
        $location = sanitize($_POST['location']);
        $gender = sanitize($_POST['gender'] ?? '');
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $bank_name = sanitize($_POST['bank_name'] ?? '');
        $bank_account_number = sanitize($_POST['bank_account_number'] ?? '');
        $payout_phone = sanitize($_POST['payout_phone'] ?? '');
        $preferred_payout_channel = sanitize($_POST['preferred_payout_channel'] ?? 'mobile');

        if ($first_name === '' || $last_name === '' || empty($roles)) {
            $error = 'First name, last name, and at least one role are required';
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, first_name, middle_name, last_name, full_name, employee_id, id_number, location, gender, phone, email, bank_name, bank_account_number, payout_phone, preferred_payout_channel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssssssssss", $username, $password, $role, $first_name, $middle_name, $last_name, $full_name, $employee_id, $id_number, $location, $gender, $phone, $email, $bank_name, $bank_account_number, $payout_phone, $preferred_payout_channel);

            if ($stmt && $stmt->execute()) {
                appLogActivity($conn, 'CREATE_USER', 'Created user account for ' . $full_name, 'users', (int) $stmt->insert_id);
                $_SESSION['success_message'] = 'User added successfully';
                header("Location: users.php");
                exit();
            } else {
                $error = 'Error adding user';
            }
        }
    }
}
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

<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-user-plus"></i> Add New User</h2>
            <a href="users.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars(formatSuccessMessage($message)); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-plus"></i> User Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username_preview" readonly>
                                <div class="form-text">Generated automatically as firstname.lastname</div>
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
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name">
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="full_name_preview" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name_preview" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="employee_id" class="form-label">Employee ID *</label>
                                <input type="text" class="form-control" id="employee_id" name="employee_id" readonly value="<?php echo generateEmployeeId($conn); ?>">
                                <div class="form-text">Automatically generated</div>
                            </div>
                            <div class="mb-3">
                                <label for="id_number" class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="id_number" name="id_number" placeholder="Zanzibar ID / NIDA / other ID number">
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location">
                            </div>
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Select gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="mb-3">
                                <label for="bank_name" class="form-label">Bank</label>
                                <select class="form-select" id="bank_name" name="bank_name">
                                    <?php echo $bankOptions; ?>
                                </select>
                                <div class="form-text">Choose the exact Snippe bank code for automated bank payouts.</div>
                                <div class="mt-2">
                                    <a href="supported_banks.php" class="small text-decoration-none"><i class="fas fa-university"></i> View supported bank codes</a>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="bank_account_number" class="form-label">Bank Account No.</label>
                                <input type="text" class="form-control" id="bank_account_number" name="bank_account_number">
                            </div>
                            <div class="mb-3">
                                <label for="payout_phone" class="form-label">Payout Phone</label>
                                <input type="tel" class="form-control" id="payout_phone" name="payout_phone" placeholder="2557XXXXXXXX">
                            </div>
                            <div class="mb-3">
                                <label for="preferred_payout_channel" class="form-label">Preferred Payout Channel</label>
                                <select class="form-select" id="preferred_payout_channel" name="preferred_payout_channel">
                                    <option value="mobile">Mobile Money</option>
                                    <option value="bank">Bank Transfer</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toast = document.getElementById('successToast');
    if (toast) {
        const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
        bsToast.show();
    }

    const firstNameInput = document.getElementById('first_name');
    const middleNameInput = document.getElementById('middle_name');
    const lastNameInput = document.getElementById('last_name');
    const usernameInput = document.getElementById('username');
    const fullNamePreviewInput = document.getElementById('full_name_preview');

    function slugPart(value) {
        return value
            .toLowerCase()
            .trim()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '.')
            .replace(/^\.+|\.+$/g, '');
    }

    function updateGeneratedFields() {
        const firstName = firstNameInput.value.trim();
        const middleName = middleNameInput.value.trim();
        const lastName = lastNameInput.value.trim();
        const username = [slugPart(firstName), slugPart(lastName)].filter(Boolean).join('.');
        const fullName = [firstName, middleName, lastName].filter(Boolean).join(' ');

        usernameInput.value = username;
        fullNamePreviewInput.value = fullName;
    }

    [firstNameInput, middleNameInput, lastNameInput].forEach(function(input) {
        input.addEventListener('input', updateGeneratedFields);
    });

    updateGeneratedFields();
});
</script>

<?php include '../includes/footer.php'; ?>