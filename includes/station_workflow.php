<?php

require_once __DIR__ . '/snippe_payouts.php';

function stationEnsureWorkflowSchema(mysqli $conn): void
{
    $columns = [
        'manager_approved_by' => "ALTER TABLE station_requests ADD COLUMN manager_approved_by INT NULL AFTER approved_by",
        'manager_approved_at' => "ALTER TABLE station_requests ADD COLUMN manager_approved_at DATETIME NULL AFTER manager_approved_by",
        'manager_comment' => "ALTER TABLE station_requests ADD COLUMN manager_comment TEXT NULL AFTER manager_approved_at",
        'director_approved_by' => "ALTER TABLE station_requests ADD COLUMN director_approved_by INT NULL AFTER manager_approved_at",
        'director_approved_at' => "ALTER TABLE station_requests ADD COLUMN director_approved_at DATETIME NULL AFTER director_approved_by",
        'director_comment' => "ALTER TABLE station_requests ADD COLUMN director_comment TEXT NULL AFTER director_approved_at",
        'accountant_approved_by' => "ALTER TABLE station_requests ADD COLUMN accountant_approved_by INT NULL AFTER director_approved_at",
        'accountant_approved_at' => "ALTER TABLE station_requests ADD COLUMN accountant_approved_at DATETIME NULL AFTER accountant_approved_by",
        'accountant_comment' => "ALTER TABLE station_requests ADD COLUMN accountant_comment TEXT NULL AFTER accountant_approved_at",
        'storekeeper_approved_by' => "ALTER TABLE station_requests ADD COLUMN storekeeper_approved_by INT NULL AFTER accountant_approved_at",
        'storekeeper_approved_at' => "ALTER TABLE station_requests ADD COLUMN storekeeper_approved_at DATETIME NULL AFTER storekeeper_approved_by",
    ];

    foreach ($columns as $column => $sql) {
        $result = $conn->query("SHOW COLUMNS FROM station_requests LIKE '{$column}'");
        if ($result && $result->num_rows === 0) {
            $conn->query($sql);
        }
    }

    $statusColumn = $conn->query("SHOW COLUMNS FROM station_requests LIKE 'status'");
    $statusRow = $statusColumn ? $statusColumn->fetch_assoc() : null;
    $statusType = strtolower((string) ($statusRow['Type'] ?? ''));
    $requiredStatuses = [
        'Pending Manager Approval',
        'Pending Director Approval',
        'Approved',
        'Awaiting Accountant Approval',
        'Pending Store Keeper Approval',
        'Ready for Installation',
        'Equipment Issued',
        'Installation in Progress',
        'Completed',
        'Rejected',
    ];

    $needsAlter = false;
    foreach ($requiredStatuses as $status) {
        if (stripos($statusType, strtolower($status)) === false) {
            $needsAlter = true;
            break;
        }
    }

    if ($needsAlter) {
        $enumValues = array_map(static function ($value) {
            return "'" . $value . "'";
        }, $requiredStatuses);
        $conn->query("ALTER TABLE station_requests MODIFY COLUMN status ENUM(" . implode(', ', $enumValues) . ") NOT NULL DEFAULT 'Pending Manager Approval'");
    }

    $conn->query("UPDATE station_requests SET status = 'Pending Manager Approval' WHERE status = 'Pending Approval'");
}

function stationNeedsStoreKeeperApproval(mysqli $conn, int $requestId): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM station_equipment WHERE station_request_id = ? AND source = 'Inventory'");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return ((int) ($result['total'] ?? 0)) > 0;
}

function stationStatusBadgeClass(string $status): string
{
    if ($status === 'Completed') {
        return 'success';
    }
    if ($status === 'Rejected') {
        return 'danger';
    }
    if (in_array($status, ['Pending Manager Approval', 'Pending Director Approval', 'Awaiting Accountant Approval', 'Pending Store Keeper Approval'], true)) {
        return 'warning';
    }
    if (in_array($status, ['Approved', 'Ready for Installation'], true)) {
        return 'primary';
    }
    if (in_array($status, ['Equipment Issued', 'Installation in Progress'], true)) {
        return 'info';
    }
    return 'secondary';
}

function stationCanUpdateProgress(string $status): bool
{
    return in_array($status, ['Ready for Installation', 'Equipment Issued', 'Installation in Progress'], true);
}

function stationApprovalLabel(?string $name, string $fallback = '-'): string
{
    $name = trim((string) $name);
    return $name !== '' ? $name : $fallback;
}

function stationGetPayoutSummary(mysqli $conn, int $requestId): array
{
    snippeEnsurePayoutTables($conn);

    $summary = [
        'total_actual_cost' => 0.0,
        'total_paid' => 0.0,
        'total_in_flight' => 0.0,
        'remaining_balance' => 0.0,
        'has_active_payout' => false,
        'has_costs' => false,
        'latest_payout' => null,
        'latest_payout_status' => '',
        'latest_failure_reason' => '',
        'can_finalize' => false,
        'next_status' => 'Awaiting Accountant Approval',
    ];

    $costStmt = $conn->prepare("SELECT id, total_actual_cost FROM station_costs WHERE station_request_id = ? ORDER BY id DESC LIMIT 1");
    if ($costStmt) {
        $costStmt->bind_param('i', $requestId);
        $costStmt->execute();
        $costRow = $costStmt->get_result()->fetch_assoc() ?: null;
        if ($costRow) {
            $summary['has_costs'] = true;
            $summary['total_actual_cost'] = (float) ($costRow['total_actual_cost'] ?? 0);
        }
    }

    $sumStmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN status IN ('completed', 'success') THEN amount_value ELSE 0 END), 0) AS total_paid, COALESCE(SUM(CASE WHEN status IN ('pending', 'processing', 'queued') THEN amount_value ELSE 0 END), 0) AS total_in_flight FROM snippe_payouts WHERE station_request_id = ?");
    if ($sumStmt) {
        $sumStmt->bind_param('i', $requestId);
        $sumStmt->execute();
        $sumRow = $sumStmt->get_result()->fetch_assoc() ?: [];
        $summary['total_paid'] = (float) ($sumRow['total_paid'] ?? 0);
        $summary['total_in_flight'] = (float) ($sumRow['total_in_flight'] ?? 0);
        $summary['has_active_payout'] = $summary['total_in_flight'] > 0;
    }

    $latestStmt = $conn->prepare("SELECT * FROM snippe_payouts WHERE station_request_id = ? ORDER BY id DESC LIMIT 1");
    if ($latestStmt) {
        $latestStmt->bind_param('i', $requestId);
        $latestStmt->execute();
        $summary['latest_payout'] = $latestStmt->get_result()->fetch_assoc() ?: null;
    }

    $summary['latest_payout_status'] = strtolower((string) (($summary['latest_payout']['status'] ?? '')));
    $summary['latest_failure_reason'] = trim((string) (($summary['latest_payout']['failure_reason'] ?? '')));
    $summary['remaining_balance'] = max(0, $summary['total_actual_cost'] - $summary['total_paid']);
    $summary['next_status'] = stationNeedsStoreKeeperApproval($conn, $requestId) ? 'Pending Store Keeper Approval' : 'Ready for Installation';
    $summary['can_finalize'] = $summary['has_costs']
        && $summary['remaining_balance'] <= 0.00001
        && !$summary['has_active_payout']
        && in_array($summary['latest_payout_status'], ['completed', 'success'], true);

    return $summary;
}

function stationSyncPayoutStatus(mysqli $conn, int $requestId): array
{
    $summary = stationGetPayoutSummary($conn, $requestId);

    $statusStmt = $conn->prepare('SELECT status FROM station_requests WHERE id = ? LIMIT 1');
    if (!$statusStmt) {
        return $summary;
    }

    $statusStmt->bind_param('i', $requestId);
    $statusStmt->execute();
    $statusRow = $statusStmt->get_result()->fetch_assoc() ?: null;
    $currentStatus = (string) ($statusRow['status'] ?? '');
    $syncableStatuses = ['Awaiting Accountant Approval', 'Pending Store Keeper Approval', 'Ready for Installation'];
    if (!in_array($currentStatus, $syncableStatuses, true)) {
        return $summary;
    }

    if ($summary['can_finalize']) {
        $nextStatus = (string) $summary['next_status'];
        $actorId = (int) (($summary['latest_payout']['created_by'] ?? 0));

        if ($actorId > 0) {
            $stmt = $conn->prepare("UPDATE station_requests SET status = ?, accountant_approved_by = COALESCE(accountant_approved_by, ?), accountant_approved_at = COALESCE(accountant_approved_at, NOW()), approved_by = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('siii', $nextStatus, $actorId, $actorId, $requestId);
                $stmt->execute();
            }
        } else {
            $stmt = $conn->prepare("UPDATE station_requests SET status = ?, accountant_approved_at = COALESCE(accountant_approved_at, NOW()) WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $nextStatus, $requestId);
                $stmt->execute();
            }
        }
    } else {
        $stmt = $conn->prepare("UPDATE station_requests SET status = 'Awaiting Accountant Approval', accountant_approved_by = NULL, accountant_approved_at = NULL WHERE id = ? AND status IN ('Awaiting Accountant Approval', 'Pending Store Keeper Approval', 'Ready for Installation')");
        if ($stmt) {
            $stmt->bind_param('i', $requestId);
            $stmt->execute();
        }
        $summary['next_status'] = 'Awaiting Accountant Approval';
    }

    return stationGetPayoutSummary($conn, $requestId);
}