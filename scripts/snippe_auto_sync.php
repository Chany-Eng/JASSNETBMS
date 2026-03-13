<?php
/**
 * Snippe auto-sync runner.
 *
 * Usage examples:
 * php scripts/snippe_auto_sync.php --limit=20 --offset=0
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit(1);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/snippe_payments.php';

$limit = 20;
$offset = 0;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = (int) substr($arg, 8);
    }
    if (str_starts_with($arg, '--offset=')) {
        $offset = (int) substr($arg, 9);
    }
}

$maxPages = (int) (defined('SNIPPE_AUTO_SYNC_MAX_PAGES') ? SNIPPE_AUTO_SYNC_MAX_PAGES : 3);
$result = snippeRunAutoSync($conn, $limit, $maxPages, null, 'auto');
if ($result['ok']) {
    echo '[OK] ' . $result['message'] . PHP_EOL;
    exit(0);
}

echo '[FAILED] ' . $result['message'] . PHP_EOL;
exit(1);
