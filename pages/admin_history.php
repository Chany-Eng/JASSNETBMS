<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

requirePermission(['Super Admin']);
ensureActivityLogSchema($conn);

if (!function_exists('adminHistoryActionMeta')) {
    function adminHistoryActionMeta(string $action): array
    {
        $normalized = strtoupper(trim($action));
        $isSecurity = in_array($normalized, ['LOGIN_FAILED', 'LOGIN_LOCKED', 'LOGIN_BLOCKED', 'LOGIN', 'LOGIN_FAILED_SMS', 'LOGIN_LOCKED_SMS'], true);

        return match ($normalized) {
            'LOGIN_FAILED' => [
                'label' => 'Security Alert',
                'badgeClass' => 'bg-danger-subtle text-danger border border-danger-subtle',
                'icon' => 'fas fa-triangle-exclamation',
                'rowClass' => 'admin-history-row-security',
                'is_security' => true,
            ],
            'LOGIN_LOCKED' => [
                'label' => 'Locked Out',
                'badgeClass' => 'bg-warning-subtle text-warning border border-warning-subtle',
                'icon' => 'fas fa-user-lock',
                'rowClass' => 'admin-history-row-security admin-history-row-lock',
                'is_security' => true,
            ],
            'LOGIN_BLOCKED' => [
                'label' => 'Blocked Retry',
                'badgeClass' => 'bg-dark-subtle text-dark border border-dark-subtle',
                'icon' => 'fas fa-ban',
                'rowClass' => 'admin-history-row-security admin-history-row-block',
                'is_security' => true,
            ],
            'LOGIN' => [
                'label' => 'Login',
                'badgeClass' => 'bg-success-subtle text-success border border-success-subtle',
                'icon' => 'fas fa-right-to-bracket',
                'rowClass' => '',
                'is_security' => $isSecurity,
            ],
            'LOGIN_FAILED_SMS' => [
                'label' => 'Failure SMS',
                'badgeClass' => 'bg-info-subtle text-info border border-info-subtle',
                'icon' => 'fas fa-message',
                'rowClass' => 'admin-history-row-security',
                'is_security' => true,
            ],
            'LOGIN_LOCKED_SMS' => [
                'label' => 'Lockout SMS',
                'badgeClass' => 'bg-info-subtle text-info border border-info-subtle',
                'icon' => 'fas fa-message',
                'rowClass' => 'admin-history-row-security admin-history-row-lock',
                'is_security' => true,
            ],
            'LOGOUT' => [
                'label' => 'Logout',
                'badgeClass' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                'icon' => 'fas fa-right-from-bracket',
                'rowClass' => '',
                'is_security' => false,
            ],
            default => [
                'label' => str_replace('_', ' ', $normalized !== '' ? $normalized : 'ACTIVITY'),
                'badgeClass' => 'bg-primary-subtle text-primary border border-primary-subtle',
                'icon' => 'fas fa-clock-rotate-left',
                'rowClass' => '',
                'is_security' => false,
            ],
        };
    }
}

if (!function_exists('adminHistorySecurityCountMeta')) {
    function adminHistorySecurityCountMeta(int $count): array
    {
        if ($count >= 10) {
            return [
                'badgeClass' => 'bg-danger text-white',
                'buttonClass' => 'btn-danger',
                'label' => 'High',
            ];
        }

        if ($count >= 5) {
            return [
                'badgeClass' => 'bg-warning text-dark',
                'buttonClass' => 'btn-warning',
                'label' => 'Medium',
            ];
        }

        if ($count >= 1) {
            return [
                'badgeClass' => 'bg-dark text-white',
                'buttonClass' => 'btn-outline-warning',
                'label' => 'Low',
            ];
        }

        return [
            'badgeClass' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            'buttonClass' => 'btn-outline-secondary',
            'label' => 'None',
        ];
    }
}

$selectedAction = trim((string) ($_GET['action_filter'] ?? ''));
$selectedUser = intval($_GET['user_id'] ?? 0);
$startDate = trim((string) ($_GET['start_date'] ?? ''));
$endDate = trim((string) ($_GET['end_date'] ?? ''));
$securityOnly = isset($_GET['security_only']) && $_GET['security_only'] === '1';

$securityActions = [
    'LOGIN_FAILED',
    'LOGIN_LOCKED',
    'LOGIN_BLOCKED',
    'LOGIN_FAILED_SMS',
    'LOGIN_LOCKED_SMS',
];

if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = '';
}
if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = '';
}

$conditions = [];
if ($selectedAction !== '') {
    $conditions[] = "al.action = '" . $conn->real_escape_string($selectedAction) . "'";
}
if ($securityOnly) {
    $escapedSecurityActions = array_map(static function ($action) use ($conn) {
        return "'" . $conn->real_escape_string($action) . "'";
    }, $securityActions);
    $conditions[] = 'al.action IN (' . implode(', ', $escapedSecurityActions) . ')';
}
if ($selectedUser > 0) {
    $conditions[] = 'al.user_id = ' . $selectedUser;
}
if ($startDate !== '') {
    $conditions[] = "DATE(al.created_at) >= '" . $conn->real_escape_string($startDate) . "'";
}
if ($endDate !== '') {
    $conditions[] = "DATE(al.created_at) <= '" . $conn->real_escape_string($endDate) . "'";
}

$whereSql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$historyQuery =
    "SELECT al.*, u.full_name, u.username
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     {$whereSql}
     ORDER BY al.created_at DESC";

if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'excel'], true)) {
    $exportResult = $conn->query($historyQuery);
    $headers = ['Date/Time', 'User', 'Role', 'Action', 'Description', 'Table', 'Record ID', 'IP Address'];

    if ($_GET['export'] === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="admin_history_' . date('Ymd_His') . '.xls"');
        echo '<table border="1"><tr>';
        foreach ($headers as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr>';
        while ($exportResult && ($row = $exportResult->fetch_assoc())) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars(date('d M Y H:i:s', strtotime((string) ($row['created_at'] ?? 'now')))) . '</td>';
            echo '<td>' . htmlspecialchars(($row['full_name'] ?? '') !== '' ? $row['full_name'] : (($row['username'] ?? '') !== '' ? $row['username'] : 'Unknown User')) . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['user_role'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['action'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['description'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['table_name'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['record_id'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['ip_address'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        exit();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="admin_history_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    while ($exportResult && ($row = $exportResult->fetch_assoc())) {
        fputcsv($output, [
            date('d M Y H:i:s', strtotime((string) ($row['created_at'] ?? 'now'))),
            ($row['full_name'] ?? '') !== '' ? $row['full_name'] : (($row['username'] ?? '') !== '' ? $row['username'] : 'Unknown User'),
            (string) ($row['user_role'] ?? ''),
            (string) ($row['action'] ?? ''),
            (string) ($row['description'] ?? ''),
            (string) ($row['table_name'] ?? ''),
            (string) ($row['record_id'] ?? ''),
            (string) ($row['ip_address'] ?? ''),
        ]);
    }
    fclose($output);
    exit();
}

$history = $conn->query(
    $historyQuery . ' LIMIT 300'
);

$securitySummaryQuery =
    "SELECT
        SUM(CASE WHEN action IN ('LOGIN_FAILED', 'LOGIN_LOCKED', 'LOGIN_BLOCKED') THEN 1 ELSE 0 END) AS security_events,
        SUM(CASE WHEN action = 'LOGIN_FAILED' THEN 1 ELSE 0 END) AS failed_logins,
        SUM(CASE WHEN action = 'LOGIN_LOCKED' THEN 1 ELSE 0 END) AS locked_accounts,
        SUM(CASE WHEN action = 'LOGIN_BLOCKED' THEN 1 ELSE 0 END) AS blocked_retries,
        SUM(CASE WHEN action IN ('LOGIN_FAILED_SMS', 'LOGIN_LOCKED_SMS') THEN 1 ELSE 0 END) AS security_sms
     FROM activity_logs al
     {$whereSql}";
$securitySummary = $conn->query($securitySummaryQuery);
$securityMetrics = $securitySummary ? ($securitySummary->fetch_assoc() ?: []) : [];
$securityQuickCount = (int) ($securityMetrics['security_events'] ?? 0) + (int) ($securityMetrics['security_sms'] ?? 0);
$securityQuickMeta = adminHistorySecurityCountMeta($securityQuickCount);

$securityToggleQuery = http_build_query([
    'user_id' => $selectedUser,
    'action_filter' => $selectedAction,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'security_only' => $securityOnly ? '0' : '1',
]);

$users = $conn->query('SELECT id, full_name, username FROM users ORDER BY full_name ASC');
$actions = $conn->query('SELECT DISTINCT action FROM activity_logs ORDER BY action ASC');

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <style>
        .admin-history-summary-card {
            border-radius: 18px;
            border: 1px solid #dbe5ef;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
        }

        .admin-history-summary-card.security {
            border-color: rgba(220, 53, 69, 0.18);
            background: linear-gradient(180deg, #fff7f8 0%, #fff 100%);
        }

        .admin-history-summary-value {
            font-size: 1.65rem;
            font-weight: 800;
            color: #1f2f45;
        }

        .admin-history-table .badge {
            font-size: 0.76rem;
            letter-spacing: 0.03em;
            padding: 0.5rem 0.65rem;
            border-radius: 999px;
        }

        .admin-history-row-security {
            background: rgba(220, 53, 69, 0.04);
        }

        .admin-history-row-lock {
            background: rgba(255, 193, 7, 0.08);
        }

        .admin-history-row-block {
            background: rgba(33, 37, 41, 0.06);
        }

        .admin-history-source {
            min-width: 150px;
        }

        .admin-history-description {
            min-width: 320px;
        }
    </style>

    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-1"><i class="fas fa-history"></i> Admin History</h2>
            <div class="text-muted small">Track user actions with date, time, role, and description.</div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card admin-history-summary-card security h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-uppercase small fw-semibold text-danger mb-2">Security Events</div>
                            <div class="admin-history-summary-value"><?php echo (int) ($securityMetrics['security_events'] ?? 0); ?></div>
                            <div class="text-muted small">Failed, locked, and blocked authentication attempts within the current filter set.</div>
                        </div>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fas fa-shield-halved me-1"></i>Priority</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card admin-history-summary-card h-100">
                <div class="card-body">
                    <div class="text-uppercase small fw-semibold text-warning mb-2">Locked Accounts</div>
                    <div class="admin-history-summary-value"><?php echo (int) ($securityMetrics['locked_accounts'] ?? 0); ?></div>
                    <div class="text-muted small">Accounts temporarily delayed after more than three failed login attempts.</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card admin-history-summary-card h-100">
                <div class="card-body">
                    <div class="text-uppercase small fw-semibold text-primary mb-2">Failed Logins</div>
                    <div class="admin-history-summary-value"><?php echo (int) ($securityMetrics['failed_logins'] ?? 0); ?></div>
                    <div class="text-muted small">Credential mismatches captured for admin review and follow-up.</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card admin-history-summary-card h-100">
                <div class="card-body">
                    <div class="text-uppercase small fw-semibold text-info mb-2">Security SMS</div>
                    <div class="admin-history-summary-value"><?php echo (int) ($securityMetrics['security_sms'] ?? 0); ?></div>
                    <div class="text-muted small">Audit count for wrong-password and lockout SMS notifications sent to users.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
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
                        <div class="col-md-3">
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
                        <div class="col-md-2">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        <input type="hidden" name="security_only" value="<?php echo $securityOnly ? '1' : '0'; ?>">
                        <div class="col-md-2 d-flex align-items-end gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="admin_history.php?<?php echo htmlspecialchars($securityToggleQuery); ?>" class="btn <?php echo $securityOnly ? 'btn-warning' : $securityQuickMeta['buttonClass']; ?>">
                                <i class="fas fa-shield-halved"></i> <?php echo $securityOnly ? 'All Activity' : 'Security Only'; ?>
                                <span class="badge ms-1 <?php echo htmlspecialchars($securityQuickMeta['badgeClass']); ?>" title="<?php echo htmlspecialchars($securityQuickMeta['label']); ?> security volume"><?php echo htmlspecialchars($securityQuickMeta['label']); ?></span>
                                <span class="badge ms-1 <?php echo htmlspecialchars($securityQuickMeta['badgeClass']); ?>" title="Security event count"><?php echo $securityQuickCount; ?></span>
                            </a>
                            <a href="admin_history.php?<?php echo htmlspecialchars(http_build_query(['user_id' => $selectedUser, 'action_filter' => $selectedAction, 'start_date' => $startDate, 'end_date' => $endDate, 'security_only' => $securityOnly ? '1' : '0', 'export' => 'csv'])); ?>" class="btn btn-outline-secondary">CSV</a>
                            <a href="admin_history.php?<?php echo htmlspecialchars(http_build_query(['user_id' => $selectedUser, 'action_filter' => $selectedAction, 'start_date' => $startDate, 'end_date' => $endDate, 'security_only' => $securityOnly ? '1' : '0', 'export' => 'excel'])); ?>" class="btn btn-outline-success">Excel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Activity Log</h5>
                    <div class="small text-muted">Security entries carry highlighted badges and shaded rows for faster review.</div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle admin-history-table">
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
                                        <?php $actionMeta = adminHistoryActionMeta((string) ($row['action'] ?? '')); ?>
                                        <tr class="<?php echo htmlspecialchars($actionMeta['rowClass']); ?>">
                                            <td><?php echo htmlspecialchars(date('d M Y H:i:s', strtotime((string) ($row['created_at'] ?? 'now')))); ?></td>
                                            <td><?php echo htmlspecialchars(($row['full_name'] ?? '') !== '' ? $row['full_name'] : (($row['username'] ?? '') !== '' ? $row['username'] : 'Unknown User')); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['user_role'] ?? '')); ?></td>
                                            <td>
                                                <div class="d-flex flex-column gap-2">
                                                    <span class="badge <?php echo htmlspecialchars($actionMeta['badgeClass']); ?>">
                                                        <i class="<?php echo htmlspecialchars($actionMeta['icon']); ?> me-1"></i><?php echo htmlspecialchars($actionMeta['label']); ?>
                                                    </span>
                                                    <span class="small text-muted"><?php echo htmlspecialchars((string) ($row['action'] ?? '')); ?></span>
                                                </div>
                                            </td>
                                            <td class="admin-history-description">
                                                <?php echo htmlspecialchars((string) ($row['description'] ?? '')); ?>
                                            </td>
                                            <td class="admin-history-source">
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