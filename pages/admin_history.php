<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

requirePermission(['Super Admin']);
ensureActivityLogSchema($conn);

$selectedAction = trim((string) ($_GET['action_filter'] ?? ''));
$selectedUser = intval($_GET['user_id'] ?? 0);

$conditions = [];
if ($selectedAction !== '') {
    $conditions[] = "al.action = '" . $conn->real_escape_string($selectedAction) . "'";
}
if ($selectedUser > 0) {
    $conditions[] = 'al.user_id = ' . $selectedUser;
}

$whereSql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$history = $conn->query(
    "SELECT al.*, u.full_name, u.username
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     {$whereSql}
     ORDER BY al.created_at DESC
     LIMIT 300"
);

$users = $conn->query('SELECT id, full_name, username FROM users ORDER BY full_name ASC');
$actions = $conn->query('SELECT DISTINCT action FROM activity_logs ORDER BY action ASC');

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-1"><i class="fas fa-history"></i> Admin History</h2>
            <div class="text-muted small">Track user actions with date, time, role, and description.</div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="user_id" class="form-label">User</label>
                            <select class="form-select" id="user_id" name="user_id">
                                <option value="0">All users</option>
                                <?php if ($users): ?>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                        <option value="<?php echo (int) $user['id']; ?>" <?php echo $selectedUser === (int) $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(($user['full_name'] ?? '') !== '' ? $user['full_name'] : ($user['username'] ?? 'User')); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="action_filter" class="form-label">Action</label>
                            <select class="form-select" id="action_filter" name="action_filter">
                                <option value="">All actions</option>
                                <?php if ($actions): ?>
                                    <?php while ($action = $actions->fetch_assoc()): ?>
                                        <?php $value = (string) ($action['action'] ?? ''); ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $selectedAction === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-list"></i> Activity Log</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($history && $history->num_rows > 0): ?>
                                    <?php while ($row = $history->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('d M Y H:i:s', strtotime((string) ($row['created_at'] ?? 'now')))); ?></td>
                                            <td><?php echo htmlspecialchars(($row['full_name'] ?? '') !== '' ? $row['full_name'] : (($row['username'] ?? '') !== '' ? $row['username'] : 'Unknown User')); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['user_role'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['action'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['description'] ?? '')); ?></td>
                                            <td>
                                                <div><?php echo htmlspecialchars((string) ($row['table_name'] ?? '')); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($row['ip_address'] ?? '')); ?></div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No activity history found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>