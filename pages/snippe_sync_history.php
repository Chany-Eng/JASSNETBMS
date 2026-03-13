<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payments.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

requirePermission(['Sales', 'Accountant', 'Super Admin']);

$history = snippeGetSyncHistory($conn, 100);
$latestSync = !empty($history) ? $history[0] : null;

$summary = [
    'total' => count($history),
    'success' => 0,
    'failed' => 0,
    'imported' => 0,
];

foreach ($history as $row) {
    if (($row['status'] ?? '') === 'success') {
        $summary['success']++;
    } else {
        $summary['failed']++;
    }
    $summary['imported'] += (int) ($row['imported_count'] ?? 0);
}

include '../includes/header.php';
?>

<style>
    .income-board {
        background: linear-gradient(120deg, #0f4c5c 0%, #1d7874 100%);
        color: #fff;
        border-radius: 14px;
        padding: 1.2rem 1.5rem;
        box-shadow: 0 10px 24px rgba(15, 76, 92, 0.22);
    }

    .income-stat {
        border-radius: 12px;
        border: 1px solid #d9e4ec;
        background: #fff;
        padding: 0.95rem 1rem;
        height: 100%;
    }

    .income-stat .label {
        font-size: 0.82rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .income-stat .value {
        font-size: 1.45rem;
        font-weight: 700;
        color: #16324f;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="income-board d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="mb-1"><i class="fas fa-history"></i> Snippe Sync History</h2>
                    <div class="small">Review manual, automatic, and webhook Snippe sync activity in one place.</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="view_income.php" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back to Income
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="income-stat">
                <div class="label">Total Sync Runs</div>
                <div class="value"><?php echo (int) $summary['total']; ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="income-stat">
                <div class="label">Successful Runs</div>
                <div class="value text-success"><?php echo (int) $summary['success']; ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="income-stat">
                <div class="label">Failed Runs</div>
                <div class="value text-danger"><?php echo (int) $summary['failed']; ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="income-stat">
                <div class="label">Total Imported</div>
                <div class="value"><?php echo (int) $summary['imported']; ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock"></i> Latest Sync</h5>
                </div>
                <div class="card-body">
                    <?php if ($latestSync): ?>
                        <div class="mb-2"><strong>Finished:</strong> <?php echo htmlspecialchars($latestSync['finished_at'] ? date('M d, Y H:i', strtotime($latestSync['finished_at'])) : date('M d, Y H:i', strtotime($latestSync['started_at']))); ?></div>
                        <div class="mb-2"><strong>Trigger:</strong> <?php echo htmlspecialchars($latestSync['trigger_type']); ?></div>
                        <div class="mb-2"><strong>By:</strong> <?php echo htmlspecialchars($latestSync['triggered_by_name'] ?? 'System'); ?></div>
                        <div class="mb-2"><strong>Status:</strong> <span class="badge bg-<?php echo ($latestSync['status'] === 'success') ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars(strtoupper($latestSync['status'])); ?></span></div>
                        <div class="text-muted small">Imported: <?php echo (int) $latestSync['imported_count']; ?>, Skipped: <?php echo (int) $latestSync['skipped_count']; ?>, Failed: <?php echo (int) $latestSync['failed_count']; ?></div>
                    <?php else: ?>
                        <div class="text-muted">No sync logs found yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Sync Overview</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">This page shows every Snippe synchronization attempt made by manual action, automatic page-triggered sync, or webhook processing.</p>
                    <p class="text-muted mb-0">Use it to confirm imported transactions, identify failed runs quickly, and verify whether auto-sync is operating normally.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Auto/Manual Sync Logs</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Started</th>
                                    <th>Finished</th>
                                    <th>Trigger</th>
                                    <th>By</th>
                                    <th>Limit/Offset</th>
                                    <th>Imported</th>
                                    <th>Skipped</th>
                                    <th>Failed</th>
                                    <th>Status</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($history)): ?>
                                    <?php foreach ($history as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($row['started_at']))); ?></td>
                                            <td><?php echo htmlspecialchars($row['finished_at'] ? date('M d, Y H:i', strtotime($row['finished_at'])) : '-'); ?></td>
                                            <td><?php echo htmlspecialchars($row['trigger_type']); ?></td>
                                            <td><?php echo htmlspecialchars($row['triggered_by_name'] ?? 'System'); ?></td>
                                            <td><?php echo (int) $row['limit_used']; ?>/<?php echo (int) $row['offset_used']; ?></td>
                                            <td><?php echo (int) $row['imported_count']; ?></td>
                                            <td><?php echo (int) $row['skipped_count']; ?></td>
                                            <td><?php echo (int) $row['failed_count']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo ($row['status'] === 'success') ? 'success' : 'danger'; ?>">
                                                    <?php echo htmlspecialchars(strtoupper($row['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['message'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <i class="fas fa-folder-open text-muted" style="font-size: 2rem;"></i>
                                            <p class="text-muted mt-2 mb-0">No sync logs found.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include '../includes/footer.php'; ?>