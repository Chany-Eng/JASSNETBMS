<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$user = getCurrentUser();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $address = sanitize($_POST['address']);
        
        $profile_photo = $user['profile_photo'];
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $upload_result = uploadFile($_FILES['profile_photo']);
            if (isset($upload_result['success'])) {
                $profile_photo = $upload_result['success'];
            } else {
                $error = $upload_result['error'];
            }
        }
        
        if (!$error) {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, address = ?, profile_photo = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $full_name, $phone, $email, $address, $profile_photo, $user['id']);
            
            if ($stmt->execute()) {
                $message = 'Profile updated successfully';
                $user = getCurrentUser(); // Refresh user data
            } else {
                $error = 'Error updating profile';
            }
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-user"></i> My Profile</h2>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <?php if ($user['profile_photo']): ?>
                    <img src="../uploads/<?php echo $user['profile_photo']; ?>" class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;" alt="Profile Photo">
                <?php else: ?>
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 150px; height: 150px;">
                        <i class="fas fa-user fa-3x text-muted"></i>
                    </div>
                <?php endif; ?>
                <h5><?php echo htmlspecialchars($user['full_name']); ?></h5>
                <p class="text-muted"><?php echo htmlspecialchars($user['role']); ?></p>
                <p class="text-muted">Employee ID: <?php echo htmlspecialchars($user['employee_id']); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                <div class="form-text">Username cannot be changed</div>
                            </div>
                            <div class="mb-3">
                                <label for="employee_id" class="form-label">Employee ID</label>
                                <input type="text" class="form-control" id="employee_id" value="<?php echo htmlspecialchars($user['employee_id']); ?>" readonly>
                                <div class="form-text">Employee ID cannot be changed</div>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <input type="text" class="form-control" id="role" value="<?php echo htmlspecialchars($user['role']); ?>" readonly>
                                <div class="form-text">Role cannot be changed</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="profile_photo" class="form-label">Profile Photo</label>
                        <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept=".jpg,.jpeg,.png">
                        <div class="form-text">Upload a new profile photo (JPG, PNG)</div>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>