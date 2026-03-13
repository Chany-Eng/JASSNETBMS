<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payments.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!hasPermission(['Director', 'Super Admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit();
}

$force = isset($_GET['force']) && $_GET['force'] === '1';
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($force) {
    $result = snippeRunAutoSync(
        $conn,
        (int) (defined('SNIPPE_AUTO_SYNC_LIMIT') ? SNIPPE_AUTO_SYNC_LIMIT : 100),
        (int) (defined('SNIPPE_AUTO_SYNC_MAX_PAGES') ? SNIPPE_AUTO_SYNC_MAX_PAGES : 3),
        $userId,
        'live'
    );
} else {
    $result = snippeAutoSyncIfDue($conn, $userId);
    if ($result === null) {
        $result = [
            'ok' => true,
            'stats' => ['imported' => 0, 'skipped' => 0, 'failed' => 0],
            'message' => 'Snippe auto-sync not due yet.',
            'skipped' => true,
            'pages_processed' => 0,
        ];
    }
}

$stats = $result['stats'] ?? ['imported' => 0, 'skipped' => 0, 'failed' => 0];
$lastSync = snippeGetLastSync($conn);

echo json_encode([
    'ok' => (bool) ($result['ok'] ?? false),
    'message' => (string) ($result['message'] ?? ''),
    'imported' => (int) ($stats['imported'] ?? 0),
    'skipped' => (int) ($stats['skipped'] ?? 0),
    'failed' => (int) ($stats['failed'] ?? 0),
    'pages_processed' => (int) ($result['pages_processed'] ?? 0),
    'sync_skipped' => !empty($result['skipped']),
    'last_sync_at' => $lastSync['finished_at'] ?? $lastSync['started_at'] ?? null,
]);