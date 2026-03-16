<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payouts.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Super Admin']);
ensureUserIdentitySchema($conn);
snippeEnsureUserPayoutFields($conn);
$bankOptions = snippeRenderBankOptions();

$message = '';
$error = '';
$submittedRoles = isset($_POST['role']) && is_array($_POST['role']) ? array_map('strval', $_POST['role']) : [];
$closeRelativeOptions = ['Mama', 'Mke', 'Mtoto', 'Baba', 'Shangazi', 'Ndugu', 'Mlezi', 'Mwingine'];

function oldInput(string $key, string $default = ''): string
{
    return htmlspecialchars((string) ($_POST[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

function oldSelected(string $key, string $value): string
{
    return ((string) ($_POST[$key] ?? '') === $value) ? 'selected' : '';
}

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

function collectCloseRelativePayload(int $position): array
{
    $relationship = sanitize($_POST['close_relative_' . $position . '_relationship'] ?? '');
    $name = sanitize($_POST['close_relative_' . $position . '_name'] ?? '');
    $phone = appNormalizeSmsPhone((string) ($_POST['close_relative_' . $position . '_phone'] ?? ''));
    $location = sanitize($_POST['close_relative_' . $position . '_location'] ?? '');
    $email = sanitize($_POST['close_relative_' . $position . '_email'] ?? '');

    return [
        'relationship' => $relationship,
        'name' => $name,
        'phone' => $phone,
        'location' => $location,
        'email' => $email,
    ];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $middle_name = sanitize($_POST['middle_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $full_name = composeFullNameFromParts($first_name, $middle_name, $last_name);
        $username = generateUniqueUsername($conn, $first_name, $last_name);
        $plain_pass = appGenerateTemporaryPassword();
        $password = password_hash($plain_pass, PASSWORD_DEFAULT);
        $roles = isset($_POST['role']) ? array_map('sanitize', $_POST['role']) : [];
        $role = implode(',', $roles);
        $employee_id = generateEmployeeId($conn);
        $id_number = sanitize($_POST['id_number'] ?? '');
        $location = sanitize($_POST['location']);
        $gender = sanitize($_POST['gender'] ?? '');
        $phone = appNormalizeSmsPhone((string) ($_POST['phone'] ?? ''));
        $email = sanitize($_POST['email']);
        $closeRelativeOne = collectCloseRelativePayload(1);
        $closeRelativeTwo = collectCloseRelativePayload(2);
        $bank_name = sanitize($_POST['bank_name'] ?? '');
        $bank_account_number = sanitize($_POST['bank_account_number'] ?? '');
        $payout_phone = appNormalizeSmsPhone((string) ($_POST['payout_phone'] ?? ''));
        $preferred_payout_channel = sanitize($_POST['preferred_payout_channel'] ?? 'mobile');

        if ($first_name === '' || $last_name === '' || empty($roles)) {
            $error = 'First name, last name, and at least one role are required';
        } elseif ($phone === '') {
            $error = 'Phone number is required so the new user can receive the welcome SMS and OTP';
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, first_name, middle_name, last_name, full_name, employee_id, id_number, location, gender, phone, email, close_relative_1_relationship, close_relative_1_name, close_relative_1_phone, close_relative_1_location, close_relative_1_email, close_relative_2_relationship, close_relative_2_name, close_relative_2_phone, close_relative_2_location, close_relative_2_email, bank_name, bank_account_number, payout_phone, preferred_payout_channel, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            if ($stmt) {
                $stmt->bind_param(
                    "sssssssssssssssssssssssssss",
                    $username,
                    $password,
                    $role,
                    $first_name,
                    $middle_name,
                    $last_name,
                    $full_name,
                    $employee_id,
                    $id_number,
                    $location,
                    $gender,
                    $phone,
                    $email,
                    $closeRelativeOne['relationship'],
                    $closeRelativeOne['name'],
                    $closeRelativeOne['phone'],
                    $closeRelativeOne['location'],
                    $closeRelativeOne['email'],
                    $closeRelativeTwo['relationship'],
                    $closeRelativeTwo['name'],
                    $closeRelativeTwo['phone'],
                    $closeRelativeTwo['location'],
                    $closeRelativeTwo['email'],
                    $bank_name,
                    $bank_account_number,
                    $payout_phone,
                    $preferred_payout_channel
                );
            }

            if ($stmt && $stmt->execute()) {
                $newUserId = (int) $stmt->insert_id;
                $smsResponse = appSendCredentialSms($phone, $full_name, $username, $plain_pass, 'welcome');
                $emailResponse = appSendCredentialEmail($email, $full_name, $username, $plain_pass, 'welcome');
                $smsSent = is_array($smsResponse) && !empty($smsResponse['success']);
                $emailAttempted = trim($email) !== '';
                $emailSent = is_array($emailResponse) && !empty($emailResponse['success']);

                $channelSummary = [];
                $channelSummary[] = $smsSent ? 'welcome SMS sent' : 'welcome SMS failed';
                if ($emailAttempted) {
                    $channelSummary[] = $emailSent ? 'welcome email sent' : 'welcome email failed';
                }

                appLogActivity($conn, 'CREATE_USER', 'Created user account for ' . $full_name . '. ' . ucfirst(implode('; ', $channelSummary)) . '.', 'users', $newUserId);
                if ($smsSent && $emailSent) {
                    $_SESSION['success_message'] = 'User added successfully and welcome SMS plus email sent';
                } elseif ($smsSent) {
                    $_SESSION['success_message'] = $emailAttempted
                        ? 'User added successfully and welcome SMS sent, but email failed'
                        : 'User added successfully and welcome SMS sent';
                } elseif ($emailSent) {
                    $_SESSION['success_message'] = 'User added successfully and welcome email sent, but SMS failed. Username: ' . $username . ' | Temporary password: ' . $plain_pass;
                } else {
                    $_SESSION['success_message'] = 'User added successfully, but welcome SMS failed' . ($emailAttempted ? ' and email failed' : '') . '. Username: ' . $username . ' | Temporary password: ' . $plain_pass;
                }
                header("Location: users.php");
                exit();
            } elseif (!$stmt) {
                $error = 'Could not prepare user creation query';
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
</style>

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
                                <input type="text" class="form-control" id="password" value="Generated automatically" readonly>
                                <div class="form-text">A temporary one-time password will be generated automatically and sent by SMS, and also by email if an email address is provided.</div>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Roles *</label>
                                <div id="role" class="role-selection-grid" role="group" aria-label="Select one or more roles">
                                    <div class="role-option-card">
                                        <input type="checkbox" id="role_sales" name="role[]" value="Sales" <?php echo in_array('Sales', $submittedRoles, true) ? 'checked' : ''; ?>>
                                        <label class="role-option-label" for="role_sales"><i class="fas fa-chart-line"></i><span>Sales</span></label>
                                    </div>
                                    <div class="role-option-card">
                                        <input type="checkbox" id="role_technician" name="role[]" value="Technician" <?php echo in_array('Technician', $submittedRoles, true) ? 'checked' : ''; ?>>
                                        <label class="role-option-label" for="role_technician"><i class="fas fa-screwdriver-wrench"></i><span>Technician</span></label>
                                    </div>
                                    <div class="role-option-card">
                                        <input type="checkbox" id="role_store_keeper" name="role[]" value="Store Keeper" <?php echo in_array('Store Keeper', $submittedRoles, true) ? 'checked' : ''; ?>>
                                        <label class="role-option-label" for="role_store_keeper"><i class="fas fa-box-open"></i><span>Store Keeper</span></label>
                                    </div>
                                    <div class="role-option-card">
                                        <input type="checkbox" id="role_content_manager" name="role[]" value="Content Manager" <?php echo in_array('Content Manager', $submittedRoles, true) ? 'checked' : ''; ?>>
                                        <label class="role-option-label" for="role_content_manager"><i class="fas fa-palette"></i><span>Content Manager</span></label>
                                    </div>
                                    <div class="role-option-card">
                                        <input type="checkbox" id="role_manager" name="role[]" value="Manager" <?php echo in_array('Manager', $submittedRoles, true) ? 'checked' : ''; ?>>
                                        <label class="role-option-label" for="role_manager"><i class="fas fa-user-tie"></i><span>Manager</span></label>
                                    </div>
                                    <div class="role-option-card">
                                        <input type="checkbox" id="role_director" name="role[]" value="Director" <?php echo in_array('Director', $submittedRoles, true) ? 'checked' : ''; ?>>
                                        <label class="role-option-label" for="role_director"><i class="fas fa-briefcase"></i><span>Director</span></label>
                                    </div>
                                    <div class="role-option-card">
                                        <input type="checkbox" id="role_accountant" name="role[]" value="Accountant" <?php echo in_array('Accountant', $submittedRoles, true) ? 'checked' : ''; ?>>
                                        <label class="role-option-label" for="role_accountant"><i class="fas fa-calculator"></i><span>Accountant</span></label>
                                    </div>
                                    <div class="role-option-card">
                                        <input type="checkbox" id="role_super_admin" name="role[]" value="Super Admin" <?php echo in_array('Super Admin', $submittedRoles, true) ? 'checked' : ''; ?>>
                                        <label class="role-option-label" for="role_super_admin"><i class="fas fa-shield-halved"></i><span>Super Admin</span></label>
                                    </div>
                                </div>
                                <div class="form-text">Select one or more roles for this user account.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo oldInput('first_name'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo oldInput('middle_name'); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo oldInput('last_name'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="full_name_preview" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name_preview" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="employee_id" class="form-label">Employee ID *</label>
                                <input type="text" class="form-control" id="employee_id" name="employee_id" readonly value="<?php echo oldInput('employee_id', generateEmployeeId($conn)); ?>">
                                <div class="form-text">Automatically generated</div>
                            </div>
                            <div class="mb-3">
                                <label for="id_number" class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="id_number" name="id_number" value="<?php echo oldInput('id_number'); ?>" placeholder="Zanzibar ID / NIDA / other ID number">
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo oldInput('location'); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Select gender</option>
                                    <option value="Male" <?php echo oldSelected('gender', 'Male'); ?>>Male</option>
                                    <option value="Female" <?php echo oldSelected('gender', 'Female'); ?>>Female</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo oldInput('phone'); ?>" required>
                                <div class="form-text">Required for welcome SMS and login OTP delivery.</div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo oldInput('email'); ?>">
                                <div class="form-text">Optional, but recommended so the welcome SMS content is also delivered by email.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Close Relative 1</label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <select class="form-select" name="close_relative_1_relationship">
                                            <option value="">Relationship</option>
                                            <?php foreach ($closeRelativeOptions as $option): ?>
                                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo oldSelected('close_relative_1_relationship', $option); ?>><?php echo htmlspecialchars($option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-8">
                                        <input type="text" class="form-control" name="close_relative_1_name" value="<?php echo oldInput('close_relative_1_name'); ?>" placeholder="Full name">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="tel" class="form-control" id="close_relative_1_phone" name="close_relative_1_phone" value="<?php echo oldInput('close_relative_1_phone'); ?>" placeholder="Phone number">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="email" class="form-control" name="close_relative_1_email" value="<?php echo oldInput('close_relative_1_email'); ?>" placeholder="Email if available">
                                    </div>
                                    <div class="col-12">
                                        <input type="text" class="form-control" name="close_relative_1_location" value="<?php echo oldInput('close_relative_1_location'); ?>" placeholder="Location">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Close Relative 2</label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <select class="form-select" name="close_relative_2_relationship">
                                            <option value="">Relationship</option>
                                            <?php foreach ($closeRelativeOptions as $option): ?>
                                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo oldSelected('close_relative_2_relationship', $option); ?>><?php echo htmlspecialchars($option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-8">
                                        <input type="text" class="form-control" name="close_relative_2_name" value="<?php echo oldInput('close_relative_2_name'); ?>" placeholder="Full name">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="tel" class="form-control" id="close_relative_2_phone" name="close_relative_2_phone" value="<?php echo oldInput('close_relative_2_phone'); ?>" placeholder="Phone number">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="email" class="form-control" name="close_relative_2_email" value="<?php echo oldInput('close_relative_2_email'); ?>" placeholder="Email if available">
                                    </div>
                                    <div class="col-12">
                                        <input type="text" class="form-control" name="close_relative_2_location" value="<?php echo oldInput('close_relative_2_location'); ?>" placeholder="Location">
                                    </div>
                                </div>
                                <div class="form-text">You can save up to two close relatives with phone, location, and email when available.</div>
                            </div>
                            <div class="mb-3">
                                <label for="bank_name" class="form-label">Bank</label>
                                <select class="form-select" id="bank_name" name="bank_name" data-selected-value="<?php echo oldInput('bank_name'); ?>">
                                    <?php echo $bankOptions; ?>
                                </select>
                                <div class="form-text">Choose the exact Snippe bank code for automated bank payouts.</div>
                                <div class="mt-2">
                                    <a href="supported_banks.php" class="small text-decoration-none"><i class="fas fa-university"></i> View supported bank codes</a>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="bank_account_number" class="form-label">Bank Account No.</label>
                                <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" value="<?php echo oldInput('bank_account_number'); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="payout_phone" class="form-label">Payout Phone</label>
                                <div class="field-prefix-group">
                                    <span class="field-prefix">255</span>
                                    <input type="tel" class="form-control" id="payout_phone" name="payout_phone" value="<?php echo oldInput('payout_phone'); ?>" placeholder="7XXXXXXXX" inputmode="numeric" maxlength="12">
                                </div>
                                <div class="form-text">Mobile payout number will automatically start with 255.</div>
                            </div>
                            <div class="mb-3">
                                <label for="preferred_payout_channel" class="form-label">Preferred Payout Channel</label>
                                <select class="form-select" id="preferred_payout_channel" name="preferred_payout_channel">
                                    <option value="mobile" <?php echo oldSelected('preferred_payout_channel', 'mobile'); ?>>Mobile Money</option>
                                    <option value="bank" <?php echo oldSelected('preferred_payout_channel', 'bank'); ?>>Bank Transfer</option>
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
    const payoutPhoneInput = document.getElementById('payout_phone');
    const phoneInput = document.getElementById('phone');
    const bankNameSelect = document.getElementById('bank_name');

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

    if (payoutPhoneInput) {
        payoutPhoneInput.value = normalizeTanzaniaPhone(payoutPhoneInput.value);

        payoutPhoneInput.addEventListener('focus', function() {
            payoutPhoneInput.value = normalizeTanzaniaPhone(payoutPhoneInput.value);
        });

        payoutPhoneInput.addEventListener('input', function() {
            payoutPhoneInput.value = normalizeTanzaniaPhone(payoutPhoneInput.value);
        });

        payoutPhoneInput.addEventListener('blur', function() {
            payoutPhoneInput.value = normalizeTanzaniaPhone(payoutPhoneInput.value);
        });
    }

    ['phone', 'close_relative_1_phone', 'close_relative_2_phone'].forEach(function(fieldId) {
        const input = document.getElementById(fieldId);
        if (!input) {
            return;
        }

        input.addEventListener('blur', function() {
            input.value = normalizeTanzaniaPhone(input.value);
        });
    });

    if (bankNameSelect && bankNameSelect.dataset.selectedValue) {
        bankNameSelect.value = bankNameSelect.dataset.selectedValue;
    }

    updateGeneratedFields();
});
</script>

<?php include '../includes/footer.php'; ?>