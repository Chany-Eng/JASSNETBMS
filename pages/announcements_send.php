<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

function ensureAnnouncementsTable(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message TEXT NOT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            is_active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $colRes = $conn->query("SHOW COLUMNS FROM announcements LIKE 'expires_at'");
    if ($colRes && $colRes->num_rows === 0) {
        $conn->query("ALTER TABLE announcements ADD COLUMN expires_at DATETIME NULL AFTER created_at");
    }
}

ensureAnnouncementsTable($conn);
$canPost = hasPermission(['Store Keeper', 'Manager', 'Director', 'Super Admin']);

if (!$canPost) {
    header('Location: ../dashboard.php?error=unauthorized');
    exit();
}

$error = '';
$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    if (!$canPost) {
        $error = 'You are not authorized to post announcements.';
    } else {
        $announcementMessage = trim($_POST['announcement_message'] ?? '');
        if ($announcementMessage === '') {
            $error = 'Announcement message is required.';
        } else {
            if (mb_strlen($announcementMessage) > 600) {
                $announcementMessage = mb_substr($announcementMessage, 0, 600);
            }

            $expiryInput = trim($_POST['announcement_expiry_date'] ?? '');
            if ($expiryInput !== '') {
                $expiresAt = date('Y-m-d 23:59:59', strtotime($expiryInput));
            } else {
                $expiresAt = date('Y-m-d 23:59:59', strtotime('+3 days'));
            }

            $stmt = $conn->prepare('INSERT INTO announcements (message, created_by, expires_at, is_active) VALUES (?, ?, ?, 1)');
            if ($stmt) {
                $stmt->bind_param('sis', $announcementMessage, $_SESSION['user_id'], $expiresAt);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Announcement sent successfully.';
                    header('Location: announcements_send.php');
                    exit();
                }
            }

            if (!$error) {
                $error = 'Failed to send announcement.';
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-paper-plane"></i> Send Announcement</h2>
        <span class="text-muted">Send to all users</span>
    </div>
</div>

<?php if ($success_message): ?>
<div class="alert alert-success"><?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" style="max-width: 760px;">
            <div class="mb-3">
                <label for="announcement_message" class="form-label">Message to all users</label>
                <textarea id="announcement_message" name="announcement_message" class="form-control" rows="6" maxlength="600" required></textarea>
            </div>
            <div class="mb-3">
                <label for="announcement_expiry_date" class="form-label">Expiry Date</label>
                <input type="date" id="announcement_expiry_date" name="announcement_expiry_date" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime('+3 days'))); ?>" min="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                <small class="text-muted">Default is 3 days.</small>
            </div>
            <button type="submit" name="post_announcement" class="btn btn-primary">Send Announcement</button>
        </form>
    </div>
</div>

<?php if ($success_message): ?>
<div class="modal fade" id="announcementSuccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Announcement Sent</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var successModalEl = document.getElementById('announcementSuccessModal');
    if (successModalEl) {
        var successModal = new bootstrap.Modal(successModalEl);
        successModal.show();
    }
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>