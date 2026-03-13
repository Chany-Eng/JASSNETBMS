<?php

/**
 * Snippe Payments integration helpers.
 */

function snippeEnsureTables(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS income_external_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(50) NOT NULL,
            external_payment_id VARCHAR(120) NOT NULL,
            income_id INT NOT NULL,
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_provider_payment (provider, external_payment_id),
            FOREIGN KEY (income_id) REFERENCES income(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS snippe_sync_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            trigger_type VARCHAR(20) NOT NULL DEFAULT 'manual',
            limit_used INT NOT NULL DEFAULT 20,
            offset_used INT NOT NULL DEFAULT 0,
            imported_count INT NOT NULL DEFAULT 0,
            skipped_count INT NOT NULL DEFAULT 0,
            failed_count INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            message TEXT,
            triggered_by INT NULL,
            INDEX idx_started_at (started_at),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function snippeFetchPayments(int $limit = 20, int $offset = 0): array
{
    if (!defined('SNIPPE_API_KEY') || trim((string) SNIPPE_API_KEY) === '') {
        throw new RuntimeException('Snippe API key is missing. Set SNIPPE_API_KEY environment variable.');
    }

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);

    $baseUrl = rtrim((string) SNIPPE_API_BASE_URL, '/');
    $url = $baseUrl . '/v1/payments?limit=' . $limit . '&offset=' . $offset;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => defined('SNIPPE_API_TIMEOUT') ? (int) SNIPPE_API_TIMEOUT : 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SNIPPE_API_KEY,
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Snippe request failed: ' . $curlError);
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        throw new RuntimeException('Snippe response is not valid JSON.');
    }

    if ($status < 200 || $status >= 300) {
        $msg = $json['message'] ?? $json['error'] ?? ('HTTP ' . $status);
        throw new RuntimeException('Snippe API error: ' . $msg);
    }

    $payments = [];

    // Common patterns:
    // 1) { data: { items: [...] } }
    // 2) { data: [...] }
    // 3) { items: [...] }
    // 4) { payments: [...] }
    // 5) [ ... ]
    if (isset($json['data']['items']) && is_array($json['data']['items'])) {
        $payments = $json['data']['items'];
    } elseif (isset($json['data']) && is_array($json['data']) && array_keys($json['data']) === range(0, count($json['data']) - 1)) {
        $payments = $json['data'];
    } elseif (isset($json['items']) && is_array($json['items'])) {
        $payments = $json['items'];
    } elseif (isset($json['payments']) && is_array($json['payments'])) {
        $payments = $json['payments'];
    } elseif (array_keys($json) === range(0, count($json) - 1)) {
        $payments = $json;
    }

    return $payments;
}

function snippeMapPaymentMethod(string $value): string
{
    $v = strtolower($value);
    if (str_contains($v, 'cash')) {
        return 'Cash';
    }
    if (str_contains($v, 'bank')) {
        return 'Bank';
    }
    return 'Mobile Money';
}

function snippeGetNested(array $arr, string $path, $default = '')
{
    $parts = explode('.', $path);
    $current = $arr;
    foreach ($parts as $part) {
        if (!is_array($current) || !array_key_exists($part, $current)) {
            return $default;
        }
        $current = $current[$part];
    }
    return $current;
}

function snippeMergeSyncStats(array $baseStats, array $newStats): array
{
    return [
        'imported' => (int) ($baseStats['imported'] ?? 0) + (int) ($newStats['imported'] ?? 0),
        'skipped' => (int) ($baseStats['skipped'] ?? 0) + (int) ($newStats['skipped'] ?? 0),
        'failed' => (int) ($baseStats['failed'] ?? 0) + (int) ($newStats['failed'] ?? 0),
    ];
}

function snippeFormatSyncMessage(array $stats): string
{
    return 'Imported: ' . (int) ($stats['imported'] ?? 0) . ', Skipped: ' . (int) ($stats['skipped'] ?? 0) . ', Failed: ' . (int) ($stats['failed'] ?? 0);
}

function snippeAcquireLock(mysqli $conn, string $lockName = 'jassnet_snippe_auto_sync', int $timeout = 0): bool
{
    $stmt = $conn->prepare('SELECT GET_LOCK(?, ?) AS lock_status');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $lockName, $timeout);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    return (int) ($result['lock_status'] ?? 0) === 1;
}

function snippeReleaseLock(mysqli $conn, string $lockName = 'jassnet_snippe_auto_sync'): void
{
    $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('s', $lockName);
    $stmt->execute();
}

function snippeShouldAutoSync(?array $lastSync): bool
{
    if (!defined('SNIPPE_AUTO_SYNC_ENABLED') || !SNIPPE_AUTO_SYNC_ENABLED) {
        return false;
    }

    if (!$lastSync) {
        return true;
    }

    $reference = $lastSync['finished_at'] ?: $lastSync['started_at'] ?: null;
    if (!$reference) {
        return true;
    }

    $intervalMinutes = max(1, (int) (defined('SNIPPE_AUTO_SYNC_INTERVAL_MINUTES') ? SNIPPE_AUTO_SYNC_INTERVAL_MINUTES : 15));
    return (time() - strtotime($reference)) >= ($intervalMinutes * 60);
}

function snippeAutoSyncIfDue(mysqli $conn, ?int $userId = null): ?array
{
    $lastSync = snippeGetLastSync($conn);
    if (!snippeShouldAutoSync($lastSync)) {
        return null;
    }

    if (!snippeAcquireLock($conn)) {
        return [
            'ok' => true,
            'skipped' => true,
            'message' => 'Snippe auto-sync is already running.'
        ];
    }

    try {
        $pageSize = max(1, min(100, (int) (defined('SNIPPE_AUTO_SYNC_LIMIT') ? SNIPPE_AUTO_SYNC_LIMIT : 100)));
        $maxPages = max(1, min(10, (int) (defined('SNIPPE_AUTO_SYNC_MAX_PAGES') ? SNIPPE_AUTO_SYNC_MAX_PAGES : 3)));
        $result = snippeRunAutoSync($conn, $pageSize, $maxPages, $userId, 'auto');
        $result['skipped'] = false;
        return $result;
    } finally {
        snippeReleaseLock($conn);
    }
}

function snippeRunAutoSync(
    mysqli $conn,
    int $pageSize = 100,
    int $maxPages = 3,
    ?int $userId = null,
    string $triggerType = 'auto'
): array {
    snippeEnsureTables($conn);

    $pageSize = max(1, min(100, $pageSize));
    $maxPages = max(1, min(10, $maxPages));
    $effectiveUserId = $userId ?: 1;
    $startedAt = date('Y-m-d H:i:s');
    $stats = ['imported' => 0, 'skipped' => 0, 'failed' => 0];
    $pagesProcessed = 0;

    try {
        for ($page = 0; $page < $maxPages; $page++) {
            $offset = $page * $pageSize;
            $payments = snippeFetchPayments($pageSize, $offset);
            $pagesProcessed++;

            if (empty($payments)) {
                break;
            }

            $batchStats = snippeImportPayments($conn, $payments, $effectiveUserId);
            $stats = snippeMergeSyncStats($stats, $batchStats);

            if (count($payments) < $pageSize) {
                break;
            }
        }

        $finishedAt = date('Y-m-d H:i:s');
        $message = snippeFormatSyncMessage($stats) . ', Pages: ' . $pagesProcessed;

        snippeLogSync($conn, $triggerType, $pageSize, 0, $stats, 'success', $message, $userId, $startedAt, $finishedAt);

        return [
            'ok' => true,
            'stats' => $stats,
            'pages_processed' => $pagesProcessed,
            'message' => $message,
        ];
    } catch (Throwable $e) {
        $finishedAt = date('Y-m-d H:i:s');
        $stats['failed'] = max(1, (int) $stats['failed']);

        snippeLogSync($conn, $triggerType, $pageSize, 0, $stats, 'failed', $e->getMessage(), $userId, $startedAt, $finishedAt);

        return [
            'ok' => false,
            'stats' => $stats,
            'pages_processed' => $pagesProcessed,
            'message' => $e->getMessage(),
        ];
    }
}

function snippeImportPayments(mysqli $conn, array $payments, int $userId): array
{
    snippeEnsureTables($conn);

    $stats = [
        'imported' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    foreach ($payments as $payment) {
        try {
            if (!is_array($payment)) {
                $stats['skipped']++;
                continue;
            }

            $status = strtolower((string) ($payment['status'] ?? ''));
            if (!in_array($status, ['paid', 'successful', 'success', 'completed'], true)) {
                $stats['skipped']++;
                continue;
            }

            $externalId = (string) ($payment['id'] ?? $payment['payment_id'] ?? $payment['transaction_id'] ?? '');
            if ($externalId === '') {
                $stats['skipped']++;
                continue;
            }

            $checkStmt = $conn->prepare('SELECT id FROM income_external_payments WHERE provider = ? AND external_payment_id = ? LIMIT 1');
            $provider = 'snippe';
            $checkStmt->bind_param('ss', $provider, $externalId);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->fetch_assoc();
            if ($exists) {
                $stats['skipped']++;
                continue;
            }

            $amountRaw = $payment['amount'] ?? $payment['total_amount'] ?? 0;
            if (is_array($amountRaw)) {
                $amountRaw = $amountRaw['value'] ?? $amountRaw['amount'] ?? 0;
            }
            $amount = (float) $amountRaw;
            if ($amount <= 0) {
                $stats['skipped']++;
                continue;
            }

            $dateRaw = (string) ($payment['completed_at'] ?? $payment['paid_at'] ?? $payment['date'] ?? $payment['created_at'] ?? date('Y-m-d'));
            $date = date('Y-m-d', strtotime($dateRaw));
            if ($date === '1970-01-01') {
                $date = date('Y-m-d');
            }

            $customerName = (string) ($payment['customer_name'] ?? snippeGetNested($payment, 'customer.name', ''));
            if ($customerName === '') {
                $firstName = (string) snippeGetNested($payment, 'customer.first_name', '');
                $lastName = (string) snippeGetNested($payment, 'customer.last_name', '');
                $fullName = trim($firstName . ' ' . $lastName);
                $customerName = $fullName !== '' ? $fullName : 'Snippe Customer';
            }
            $phone = (string) ($payment['phone'] ?? snippeGetNested($payment, 'customer.phone', ''));
            $paymentMethodRaw = (string) ($payment['payment_method'] ?? $payment['method'] ?? snippeGetNested($payment, 'channel.type', 'mobile_money'));
            $paymentMethod = snippeMapPaymentMethod($paymentMethodRaw);
            $reference = (string) ($payment['reference'] ?? $payment['transaction_reference'] ?? $payment['external_reference'] ?? $externalId);
            $serviceType = (string) ($payment['service_type'] ?? 'Subscription');
            $validServiceTypes = ['WiFi Voucher', 'Installation', 'Router Sale', 'Subscription', 'Other'];
            if (!in_array($serviceType, $validServiceTypes, true)) {
                $serviceType = 'Other';
            }
            $notes = 'Imported from Snippe payment gateway. External ID: ' . $externalId;

            $insertIncome = $conn->prepare('INSERT INTO income (date, customer_name, phone, service_type, amount, payment_method, transaction_reference, notes, receipt_file, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $receiptFile = '';
            $insertIncome->bind_param('ssssdssssi', $date, $customerName, $phone, $serviceType, $amount, $paymentMethod, $reference, $notes, $receiptFile, $userId);
            $insertIncome->execute();

            $incomeId = (int) $conn->insert_id;
            $insertMap = $conn->prepare('INSERT INTO income_external_payments (provider, external_payment_id, income_id) VALUES (?, ?, ?)');
            $insertMap->bind_param('ssi', $provider, $externalId, $incomeId);
            $insertMap->execute();

            $stats['imported']++;
        } catch (Throwable $e) {
            $stats['failed']++;
        }
    }

    return $stats;
}

function snippeLogSync(
    mysqli $conn,
    string $triggerType,
    int $limit,
    int $offset,
    array $stats,
    string $status,
    string $message,
    ?int $triggeredBy,
    string $startedAt,
    string $finishedAt
): void {
    $stmt = $conn->prepare(
        'INSERT INTO snippe_sync_logs (started_at, finished_at, trigger_type, limit_used, offset_used, imported_count, skipped_count, failed_count, status, message, triggered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        return;
    }

    $imported = (int) ($stats['imported'] ?? 0);
    $skipped = (int) ($stats['skipped'] ?? 0);
    $failed = (int) ($stats['failed'] ?? 0);
    $triggeredByVal = $triggeredBy;

    $stmt->bind_param(
        'sssiiiiissi',
        $startedAt,
        $finishedAt,
        $triggerType,
        $limit,
        $offset,
        $imported,
        $skipped,
        $failed,
        $status,
        $message,
        $triggeredByVal
    );
    $stmt->execute();
}

function snippeRunSync(
    mysqli $conn,
    int $limit = 20,
    int $offset = 0,
    ?int $userId = null,
    string $triggerType = 'manual'
): array {
    snippeEnsureTables($conn);

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $effectiveUserId = $userId ?: 1;
    $startedAt = date('Y-m-d H:i:s');

    try {
        $payments = snippeFetchPayments($limit, $offset);
        $stats = snippeImportPayments($conn, $payments, $effectiveUserId);
        $finishedAt = date('Y-m-d H:i:s');
        $status = 'success';
        $message = snippeFormatSyncMessage($stats);

        snippeLogSync($conn, $triggerType, $limit, $offset, $stats, $status, $message, $userId, $startedAt, $finishedAt);

        return [
            'ok' => true,
            'stats' => $stats,
            'message' => $message,
        ];
    } catch (Throwable $e) {
        $finishedAt = date('Y-m-d H:i:s');
        $stats = ['imported' => 0, 'skipped' => 0, 'failed' => 1];
        $status = 'failed';
        $message = $e->getMessage();

        snippeLogSync($conn, $triggerType, $limit, $offset, $stats, $status, $message, $userId, $startedAt, $finishedAt);

        return [
            'ok' => false,
            'stats' => $stats,
            'message' => $message,
        ];
    }
}

function snippeGetLastSync(mysqli $conn): ?array
{
    snippeEnsureTables($conn);
    $res = $conn->query('SELECT * FROM snippe_sync_logs ORDER BY id DESC LIMIT 1');
    if (!$res) {
        return null;
    }
    return $res->fetch_assoc() ?: null;
}

function snippeGetSyncHistory(mysqli $conn, int $limit = 50): array
{
    snippeEnsureTables($conn);
    $limit = max(1, min(200, $limit));
    $rows = [];
    $sql = "SELECT l.*, u.full_name AS triggered_by_name
            FROM snippe_sync_logs l
            LEFT JOIN users u ON l.triggered_by = u.id
            ORDER BY l.id DESC
            LIMIT {$limit}";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}
