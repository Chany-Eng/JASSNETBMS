<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payments.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Sales', 'Director', 'Super Admin']);

$canRunSnippeSync = hasPermission(['Director', 'Super Admin']);
$isSalesOnlyIncomeView = hasPermission(['Sales']) && !$canRunSnippeSync;

$message = '';
$error = '';
$autoSyncMessage = '';

// Check for success message
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_snippe_payments'])) {
    if (!$canRunSnippeSync) {
        $error = 'You are not authorized to sync Snippe payments.';
    } else {
        $limitInput = intval($_POST['snippe_limit'] ?? 20);
        $offsetInput = intval($_POST['snippe_offset'] ?? 0);

        $result = snippeRunSync($conn, $limitInput, $offsetInput, (int) $_SESSION['user_id'], 'manual');
        if ($result['ok']) {
            $syncStats = $result['stats'];
            $_SESSION['success_message'] = 'Snippe sync complete. Imported: ' . $syncStats['imported'] . ', Skipped: ' . $syncStats['skipped'] . ', Failed: ' . $syncStats['failed'];
            header('Location: view_income.php');
            exit();
        } else {
            $error = 'Snippe sync failed: ' . $result['message'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $canRunSnippeSync) {
    $autoSyncResult = snippeAutoSyncIfDue($conn, (int) $_SESSION['user_id']);
    if ($autoSyncResult && empty($autoSyncResult['skipped'])) {
        if ($autoSyncResult['ok']) {
            $syncStats = $autoSyncResult['stats'];
            $autoSyncMessage = 'Snippe auto-sync complete. Imported: ' . $syncStats['imported'] . ', Skipped: ' . $syncStats['skipped'] . ', Failed: ' . $syncStats['failed'];
        } else {
            $error = 'Snippe auto-sync failed: ' . $autoSyncResult['message'];
        }
    }
}

$lastSync = snippeGetLastSync($conn);

// Get income records with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$allowedPerPage = [10, 25, 50, 100, 200];
$limit = isset($_GET['per_page']) ? intval($_GET['per_page']) : 25;
if (!in_array($limit, $allowedPerPage, true)) {
    $limit = 25;
}
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$whereConditions = [];
if ($search) {
    $whereConditions[] = "(customer_name LIKE '%$search%' OR service_type LIKE '%$search%' OR transaction_reference LIKE '%$search%')";
}

if ($isSalesOnlyIncomeView) {
    $whereConditions[] = 'i.user_id = ' . (int) $_SESSION['user_id'];
}

$where_clause = '';
if (!empty($whereConditions)) {
    $where_clause = ' WHERE ' . implode(' AND ', $whereConditions);
}

$total_result = $conn->query("SELECT COUNT(*) as count FROM income i $where_clause");
$total_records = $total_result->fetch_assoc()['count'];
$total_pages = ceil($total_records / $limit);

$income_records = $conn->query("SELECT i.*, u.full_name FROM income i JOIN users u ON i.user_id = u.id $where_clause ORDER BY date DESC LIMIT $limit OFFSET $offset");

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

    .sync-toolbar {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: end;
    }

    .sync-live-status {
        min-height: 1.5rem;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="income-board d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="mb-1"><i class="fas fa-dollar-sign"></i> Income Records</h2>
                    <div class="small">Track manual income and Snippe payments in one full-width workspace.</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if (hasPermission(['Sales'])): ?>
                    <a href="add_income.php" class="btn btn-light">
                        <i class="fas fa-plus"></i> Add Income
                    </a>
                    <?php endif; ?>
                    <?php if ($canRunSnippeSync): ?>
                    <a href="snippe_sync_history.php" class="btn btn-outline-light">
                        <i class="fas fa-history"></i> Sync History
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="income-stat">
                <div class="label">Total Records</div>
                <div class="value"><?php echo (int) $total_records; ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="income-stat">
                <div class="label">Records Per Page</div>
                <div class="value"><?php echo (int) $limit; ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="income-stat">
                <div class="label">Last Imported</div>
                <div class="value"><?php echo (int) ($lastSync['imported_count'] ?? 0); ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="income-stat">
                <div class="label">Sync Status</div>
                <?php
                    $syncStatus = $lastSync['status'] ?? 'idle';
                    $syncStatusClass = $syncStatus === 'success' ? 'success' : ($syncStatus === 'failed' ? 'danger' : 'secondary');
                ?>
                <div class="value text-<?php echo $syncStatusClass; ?>">
                    <?php echo htmlspecialchars(strtoupper($syncStatus)); ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($autoSyncMessage): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-sync"></i> <?php echo htmlspecialchars($autoSyncMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-search"></i> Search Income Records</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-lg-8">
                            <input type="text" class="form-control" name="search" placeholder="Search by customer name, service type, or reference..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-sm-4 col-lg-2">
                            <select class="form-select" name="per_page">
                                <?php foreach ($allowedPerPage as $perPageOption): ?>
                                    <option value="<?php echo $perPageOption; ?>" <?php echo $limit === $perPageOption ? 'selected' : ''; ?>><?php echo $perPageOption; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-8 col-lg-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php if ($canRunSnippeSync): ?>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-sync"></i> Snippe Auto Sync</h5>
                </div>
                <div class="card-body">
                    <div class="small text-muted mb-3">
                        Auto-sync runs when this page opens and keeps checking in the background while this page stays open.
                    </div>
                    <div class="mb-3">
                        <strong>Last Sync:</strong>
                        <?php if ($lastSync): ?>
                            <div class="mt-1"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($lastSync['finished_at'] ?: $lastSync['started_at']))); ?></div>
                            <div class="mt-2 text-muted small">Imported: <?php echo (int) $lastSync['imported_count']; ?>, Skipped: <?php echo (int) $lastSync['skipped_count']; ?>, Failed: <?php echo (int) $lastSync['failed_count']; ?></div>
                        <?php else: ?>
                            <div class="mt-1 text-muted">No sync has run yet.</div>
                        <?php endif; ?>
                    </div>
                    <div id="snippeLiveSyncStatus" class="sync-live-status small text-muted mb-3">
                        Live sync checks every 60 seconds.
                    </div>
                    <form method="POST" class="sync-toolbar">
                        <div>
                            <label class="form-label">Limit</label>
                            <input type="number" name="snippe_limit" class="form-control" value="<?php echo (int) SNIPPE_AUTO_SYNC_LIMIT; ?>" min="1" max="100">
                        </div>
                        <div>
                            <label class="form-label">Offset</label>
                            <input type="number" name="snippe_offset" class="form-control" value="0" min="0">
                        </div>
                        <div>
                            <button type="submit" name="sync_snippe_payments" class="btn btn-outline-primary">
                                <i class="fas fa-sync"></i> Sync Now
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Income List (Total: <?php echo $total_records; ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" data-table-pagination="server" data-row-number-start="<?php echo $offset + 1; ?>">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Service Type</th>
                                    <th>Amount (Tshs.)</th>
                                    <th>Payment Method</th>
                                    <th>Reference</th>
                                    <th>Recorded By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($income_records->num_rows > 0): ?>
                                    <?php while ($row = $income_records->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($row['service_type']); ?></span>
                                        </td>
                                        <td><strong>Tshs. <?php echo number_format($row['amount'], 2); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                        <td><?php echo htmlspecialchars($row['transaction_reference'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td>
                                            <?php if ($row['receipt_file']): ?>
                                                <a href="../uploads/<?php echo htmlspecialchars($row['receipt_file']); ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-download"></i> Receipt
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No receipt</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-folder-open text-muted" style="font-size: 2rem;"></i>
                                            <p class="text-muted mt-2 mb-0">No income records found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&per_page=<?php echo $limit; ?>">First</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $limit; ?>">Previous</a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $limit; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $limit; ?>">Next</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $limit; ?>">Last</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    </div>
                    </div>
                </div>
            </div>
        </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var statusNode = document.getElementById('snippeLiveSyncStatus');
    if (!statusNode) {
        return;
    }

    var endpoint = 'snippe_income_sync.php';
    var syncInFlight = false;

    function setStatus(message, tone) {
        statusNode.className = 'sync-live-status small mb-3 text-' + tone;
        statusNode.textContent = message;
    }

    function runLiveSync(force) {
        if (syncInFlight) {
            return;
        }

        syncInFlight = true;
        var url = endpoint + (force ? '?force=1' : '');

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to reach Snippe sync service.');
                }

                return response.json();
            })
            .then(function (payload) {
                if (!payload.ok) {
                    throw new Error(payload.message || 'Snippe sync failed.');
                }

                if ((payload.imported || 0) > 0) {
                    setStatus('New Snippe payments imported. Refreshing the income list...', 'success');
                    window.location.reload();
                    return;
                }

                if (payload.sync_skipped) {
                    setStatus('Snippe auto-sync is up to date.', 'muted');
                    return;
                }

                setStatus(payload.message || 'Snippe sync check completed with no new records.', 'muted');
            })
            .catch(function (error) {
                setStatus(error.message || 'Live Snippe sync failed.', 'danger');
            })
            .finally(function () {
                syncInFlight = false;
            });
    }

    window.setTimeout(function () {
        runLiveSync(false);
    }, 5000);

    window.setInterval(function () {
        runLiveSync(false);
    }, 60000);
});
</script>

<?php include '../includes/footer.php'; ?>
