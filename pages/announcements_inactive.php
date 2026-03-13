<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

if (!hasPermission(['Super Admin'])) {
    header('Location: ../dashboard.php?error=unauthorized');
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

$error = '';
$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_announcement'])) {
    $announcementId = intval($_POST['announcement_id'] ?? 0);
    $stmt = $conn->prepare(
        "UPDATE announcements
         SET is_active = 1,
             expires_at = CASE
                WHEN expires_at IS NULL OR expires_at < NOW() THEN DATE_ADD(NOW(), INTERVAL 3 DAY)
                ELSE expires_at
             END
         WHERE id = ?"
    );
    if ($stmt) {
        $stmt->bind_param('i', $announcementId);
        $stmt->execute();
        $_SESSION['success_message'] = 'Announcement reactivated.';
        header('Location: announcements_inactive.php');
        exit();
    }
    $error = 'Failed to reactivate announcement.';
}

$inactiveAnnouncements = $conn->query(
    "SELECT a.id, a.message, a.created_at, a.expires_at, u.full_name, u.role
     FROM announcements a
     JOIN users u ON a.created_by = u.id
     WHERE a.is_active = 0
     ORDER BY a.created_at DESC
     LIMIT 30"
);

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-archive"></i> Inactive Announcements</h2>
        <span class="text-muted">Super Admin only</span>
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
        <?php if ($inactiveAnnouncements && $inactiveAnnouncements->num_rows > 0): ?>
            <?php while ($ian = $inactiveAnnouncements->fetch_assoc()): ?>
                <div class="border-bottom pb-3 mb-3">
                    <div><?php echo nl2br(htmlspecialchars($ian['message'])); ?></div>
                    <small class="text-muted">
                        By <?php echo htmlspecialchars($ian['full_name']); ?> (<?php echo htmlspecialchars($ian['role']); ?>)
                        | Created: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($ian['created_at']))); ?>
                        <?php if (!empty($ian['expires_at'])): ?>
                            | Previous Expiry: <?php echo htmlspecialchars(date('M d, Y', strtotime($ian['expires_at']))); ?>
                        <?php endif; ?>
                    </small>
                    <form method="POST" class="mt-2">
                        <input type="hidden" name="announcement_id" value="<?php echo (int) $ian['id']; ?>">
                        <button type="submit" name="reactivate_announcement" class="btn btn-sm btn-outline-success">Reactivate</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-muted">No inactive announcements.</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>