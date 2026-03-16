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
$canManage = hasPermission(['Super Admin']);

$conn->query("UPDATE announcements SET is_active = 0 WHERE is_active = 1 AND expires_at IS NOT NULL AND expires_at < NOW()");

$error = '';
$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_announcement'])) {
    if (!$canManage) {
        $error = 'Only Super Admin can deactivate announcements.';
    } else {
        $announcementId = intval($_POST['announcement_id'] ?? 0);
        $stmt = $conn->prepare('UPDATE announcements SET is_active = 0 WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $announcementId);
            $stmt->execute();
            $_SESSION['success_message'] = 'Announcement deactivated.';
            header('Location: announcements_latest.php');
            exit();
        }
        $error = 'Failed to deactivate announcement.';
    }
}

$activeAnnouncements = $conn->query(
    "SELECT a.id, a.message, a.created_at, a.expires_at, u.full_name, u.role
     FROM announcements a
     JOIN users u ON a.created_by = u.id
     WHERE a.is_active = 1
       AND (a.expires_at IS NULL OR a.expires_at >= NOW())
     ORDER BY a.created_at DESC
     LIMIT 30"
);

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-list"></i> Latest Announcements</h2>
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
        <?php if ($activeAnnouncements && $activeAnnouncements->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-modern table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 45%;">Announcement</th>
                            <th>Published By</th>
                            <th>Created</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <?php if ($canManage): ?>
                                <th class="text-end">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($an = $activeAnnouncements->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark mb-1">Announcement #<?php echo (int) $an['id']; ?></div>
                                    <div class="text-muted small"><?php echo nl2br(htmlspecialchars($an['message'])); ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($an['full_name']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($an['role']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($an['created_at']))); ?></td>
                                <td><?php echo !empty($an['expires_at']) ? htmlspecialchars(date('M d, Y', strtotime($an['expires_at']))) : '<span class="text-muted">No expiry</span>'; ?></td>
                                <td><span class="app-status-chip"><i class="fas fa-bullhorn"></i> Active</span></td>
                                <?php if ($canManage): ?>
                                    <td class="text-end">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger deactivate-announcement-btn"
                                            data-announcement-id="<?php echo (int) $an['id']; ?>"
                                            data-announcement-preview="<?php echo htmlspecialchars(mb_strimwidth($an['message'], 0, 80, '...')); ?>"
                                        >Delete/Deactivate</button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-muted">No announcements found.</div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="deactivateAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deactivate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Do you want to deactivate this announcement?</p>
                <div class="small text-muted" id="deactivateAnnouncementPreview"></div>
            </div>
            <div class="modal-footer">
                <form method="POST" class="m-0">
                    <input type="hidden" name="announcement_id" id="deactivateAnnouncementId" value="">
                    <button type="submit" name="deactivate_announcement" class="btn btn-danger">Yes, Deactivate</button>
                </form>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deactivateModalEl = document.getElementById('deactivateAnnouncementModal');
    const deactivateModal = deactivateModalEl ? new bootstrap.Modal(deactivateModalEl) : null;
    const hiddenIdInput = document.getElementById('deactivateAnnouncementId');
    const previewText = document.getElementById('deactivateAnnouncementPreview');

    document.querySelectorAll('.deactivate-announcement-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (hiddenIdInput) {
                hiddenIdInput.value = this.getAttribute('data-announcement-id') || '';
            }
            if (previewText) {
                previewText.textContent = this.getAttribute('data-announcement-preview') || '';
            }
            if (deactivateModal) {
                deactivateModal.show();
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>