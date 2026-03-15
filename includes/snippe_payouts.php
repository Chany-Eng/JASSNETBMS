<?php

function snippeSchemaConnectionKey(mysqli $conn): string
{
    return function_exists('spl_object_id') ? (string) spl_object_id($conn) : spl_object_hash($conn);
}

function snippeCanAutoMigrateSchema(): bool
{
    $configured = getenv('SNIPPE_SCHEMA_AUTO_MIGRATE');
    if ($configured !== false) {
        return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
    }

    return PHP_SAPI === 'cli';
}

function snippeTableExists(mysqli $conn, string $tableName): bool
{
    $safeTable = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function snippeColumnExists(mysqli $conn, string $tableName, string $columnName): bool
{
    if (!snippeTableExists($conn, $tableName)) {
        return false;
    }

    $safeTable = $conn->real_escape_string($tableName);
    $safeColumn = $conn->real_escape_string($columnName);
    $result = $conn->query("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function snippeIndexExists(mysqli $conn, string $tableName, string $indexName): bool
{
    if (!snippeTableExists($conn, $tableName)) {
        return false;
    }

    $safeTable = $conn->real_escape_string($tableName);
    $safeIndex = $conn->real_escape_string($indexName);
    $result = $conn->query("SHOW INDEX FROM {$safeTable} WHERE Key_name = '{$safeIndex}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function snippeGetSupportedBanks(): array
{
    return [
        'ABSA' => 'ABSA BANK TANZANIA LTD',
        'ACCESS' => 'ACCESSBANK TANZANIA LTD',
        'AKIBA' => 'AKIBA COMMERCIAL BANK LTD',
        'AMANA' => 'AMANA BANK LIMITED',
        'AZANIA' => 'AZANIA BANK LIMITED',
        'BANCABC' => 'AFRICAN BANKING CORPORATION TANZANIA LIMITED',
        'BARODA' => 'BANK OF BARODA (TANZANIA) LTD',
        'BOA' => 'BANK OF AFRICA TANZANIA LIMITED',
        'BOI' => 'BANK OF INDIA (TANZANIA) LIMITED',
        'CANARA' => 'CANARA BANK TANZANIA LTD',
        'CITI' => 'CITIBANK TANZANIA LTD',
        'CRDB' => 'CRDB BANK PLC',
        'DASHENG' => 'CHINA DASHENG BANK LIMITED',
        'DCB' => 'DAR ES SALAAM COMMUNITY BANK LTD',
        'DTB' => 'DIAMOND TRUST BANK TANZANIA LTD',
        'ECOBANK' => 'ECOBANK TANZANIA LIMITED',
        'EQUITY' => 'EQUITY BANK TANZANIA LIMITED',
        'EXIM' => 'EXIM BANK (TANZANIA) LTD',
        'FNB' => 'FIRST NATIONAL BANK LIMITED',
        'GT BANK' => 'GUARANTY TRUST BANK (T) LTD',
        'HABIB' => 'HABIB AFRICAN BANK LIMITED',
        'ICB' => 'INTERNATIONAL COMMERCIAL BANK (TANZANIA) LIMITED',
        'IMBANK' => 'I&M BANK LIMITED',
        'KCB' => 'KCB BANK TANZANIA LIMITED',
        'KILIMANJARO' => 'KILIMANJARO CO-OPERATIVE BANK LTD',
        'MAENDELEO' => 'MAENDELEO BANK LTD',
        'MKOMBOZI' => 'MKOMBOZI COMMERCIAL BANK',
        'MWALIMU' => 'MWALIMU COMMERCIAL BANK PLC',
        'MWANGA' => 'MWANGA HAKIKA MICROFINANCE BANK LIMITED',
        'NBC' => 'NATIONAL BANK OF COMMERCE LTD',
        'NCBA' => 'NCBA BANK LIMITED',
        'NMB' => 'NATIONAL MICROFINANCE BANK LIMITED',
        'PBZ' => 'PEOPLE\'S BANK OF ZANZIBAR LTD',
        'SCB' => 'STANDARD CHARTERED BANK (T) LIMITED',
        'SELCOMPESA' => 'SELCOMPESA BANK LTD',
        'STANBIC' => 'STANBIC BANK TANZANIA LTD.',
        'TCB' => 'TANZANIA COMMERCIAL BANK PLC',
        'UBA' => 'UNITED BANK FOR AFRICA (T) LTD',
        'UCHUMI' => 'UCHUMI COMMERCIAL BANK (T) LTD',
        'YETU' => 'YETU MICROFINANCE BANK PLC',
    ];
}

function snippeNormalizePayoutChannel(?string $channel, string $default = 'bank'): string
{
    $normalized = strtolower(trim((string) $channel));
    if ($normalized === 'bank') {
        return 'bank';
    }

    return 'mobile';
}

function snippeGetMinimumPayoutAmount(?string $channel = null): int
{
    $normalizedChannel = snippeNormalizePayoutChannel($channel);
    if ($normalizedChannel === 'mobile') {
        $configured = (int) (getenv('SNIPPE_MIN_MOBILE_PAYOUT_AMOUNT') ?: 3000);
        return $configured > 0 ? $configured : 3000;
    }

    $configured = (int) (getenv('SNIPPE_MIN_BANK_PAYOUT_AMOUNT') ?: 5000);
    return $configured > 0 ? $configured : 5000;
}

function snippeNormalizeBankCode(string $value): string
{
    $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $value)));
    if ($normalized === '') {
        return '';
    }

    $banks = snippeGetSupportedBanks();
    if (isset($banks[$normalized])) {
        return $normalized;
    }

    foreach ($banks as $code => $name) {
        if ($normalized === strtoupper($name)) {
            return $code;
        }
    }

    return '';
}

function snippeGetBankDisplayName(string $value): string
{
    $code = snippeNormalizeBankCode($value);
    if ($code === '') {
        return trim($value);
    }

    $banks = snippeGetSupportedBanks();
    return $banks[$code] . ' (' . $code . ')';
}

function snippeRenderBankOptions(string $selected = ''): string
{
    $selectedCode = snippeNormalizeBankCode($selected);
    $options = '<option value="">Select Bank</option>';
    foreach (snippeGetSupportedBanks() as $code => $name) {
        $isSelected = $selectedCode === $code ? ' selected' : '';
        $options .= '<option value="' . htmlspecialchars($code, ENT_QUOTES) . '"' . $isSelected . '>' . htmlspecialchars($name . ' (' . $code . ')', ENT_QUOTES) . '</option>';
    }

    return $options;
}

function snippeEnsureUserPayoutFields(mysqli $conn): void
{
    static $ensuredConnections = [];

    if (!snippeCanAutoMigrateSchema()) {
        return;
    }

    $connectionKey = snippeSchemaConnectionKey($conn);
    if (isset($ensuredConnections[$connectionKey])) {
        return;
    }

    $columns = [
        'first_name' => "ALTER TABLE users ADD COLUMN first_name VARCHAR(100) NULL AFTER full_name",
        'middle_name' => "ALTER TABLE users ADD COLUMN middle_name VARCHAR(100) NULL AFTER first_name",
        'last_name' => "ALTER TABLE users ADD COLUMN last_name VARCHAR(100) NULL AFTER middle_name",
        'gender' => "ALTER TABLE users ADD COLUMN gender VARCHAR(20) NULL AFTER full_name",
        'bank_name' => "ALTER TABLE users ADD COLUMN bank_name VARCHAR(100) NULL AFTER email",
        'bank_account_number' => "ALTER TABLE users ADD COLUMN bank_account_number VARCHAR(50) NULL AFTER bank_name",
        'payout_phone' => "ALTER TABLE users ADD COLUMN payout_phone VARCHAR(20) NULL AFTER bank_account_number",
        'preferred_payout_channel' => "ALTER TABLE users ADD COLUMN preferred_payout_channel VARCHAR(20) NOT NULL DEFAULT 'mobile' AFTER payout_phone",
        'id_number' => "ALTER TABLE users ADD COLUMN id_number VARCHAR(100) NULL AFTER employee_id",
    ];

    foreach ($columns as $column => $sql) {
        if (!snippeColumnExists($conn, 'users', $column)) {
            $conn->query($sql);
        }
    }

    $ensuredConnections[$connectionKey] = true;
}

function snippeEnsurePayoutTables(mysqli $conn): void
{
    static $ensuredConnections = [];

    if (!snippeCanAutoMigrateSchema()) {
        return;
    }

    $connectionKey = snippeSchemaConnectionKey($conn);
    if (isset($ensuredConnections[$connectionKey])) {
        return;
    }

    snippeEnsureUserPayoutFields($conn);

    $conn->query(
        "CREATE TABLE IF NOT EXISTS snippe_payouts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            expense_request_id INT NULL,
            station_request_id INT NULL,
            user_id INT NOT NULL,
            provider VARCHAR(50) NOT NULL DEFAULT 'snippe',
            payout_channel VARCHAR(30) NOT NULL DEFAULT 'mobile',
            recipient_name VARCHAR(150) NOT NULL,
            recipient_phone VARCHAR(20) NULL,
            bank_name VARCHAR(100) NULL,
            bank_account_number VARCHAR(50) NULL,
            amount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
            fees_value DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_value DECIMAL(12,2) NOT NULL DEFAULT 0,
            reference VARCHAR(120) NOT NULL,
            external_reference VARCHAR(120) NULL,
            provider_payout_id VARCHAR(120) NULL,
            narration VARCHAR(255) NULL,
            failure_reason VARCHAR(255) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            raw_response LONGTEXT NULL,
            webhook_payload LONGTEXT NULL,
            sms_sent_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_snippe_payout_reference (reference),
            KEY idx_snippe_payout_status (status),
            KEY idx_snippe_payout_expense (expense_request_id),
            KEY idx_snippe_payout_station (station_request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $expenseRequestColumn = $conn->query("SHOW COLUMNS FROM snippe_payouts LIKE 'expense_request_id'");
    $expenseRequestInfo = $expenseRequestColumn instanceof mysqli_result ? $expenseRequestColumn->fetch_assoc() : null;
    if ($expenseRequestInfo && stripos((string) ($expenseRequestInfo['Null'] ?? ''), 'yes') === false) {
        $conn->query("ALTER TABLE snippe_payouts MODIFY COLUMN expense_request_id INT NULL");
    }

    if (!snippeColumnExists($conn, 'snippe_payouts', 'station_request_id')) {
        $conn->query("ALTER TABLE snippe_payouts ADD COLUMN station_request_id INT NULL AFTER expense_request_id");
    }

    if (!snippeIndexExists($conn, 'snippe_payouts', 'idx_snippe_payout_station')) {
        $conn->query("ALTER TABLE snippe_payouts ADD KEY idx_snippe_payout_station (station_request_id)");
    }

    if (!snippeColumnExists($conn, 'snippe_payouts', 'failure_reason')) {
        $conn->query("ALTER TABLE snippe_payouts ADD COLUMN failure_reason VARCHAR(255) NULL AFTER narration");
    }

    $ensuredConnections[$connectionKey] = true;
}

function snippePayoutNested(array $data, string $path, $default = '')
{
    $parts = explode('.', $path);
    $current = $data;
    foreach ($parts as $part) {
        if (!is_array($current) || !array_key_exists($part, $current)) {
            return $default;
        }
        $current = $current[$part];
    }
    return $current;
}

function snippePayoutValue($value, string $nestedPath, array $source, float $default = 0): float
{
    if (is_array($value)) {
        return (float) ($value['value'] ?? $default);
    }

    if ($value !== null && $value !== '') {
        return (float) $value;
    }

    return (float) snippePayoutNested($source, $nestedPath, $default);
}

function snippePayoutChannelValue(array $source, string $default = 'mobile'): string
{
    $channel = $source['channel'] ?? $default;
    if (is_array($channel)) {
        $channel = $channel['type'] ?? $channel['provider'] ?? $default;
    }

    $nestedType = snippePayoutNested($source, 'channel.type', '');
    if (is_string($nestedType) && $nestedType !== '') {
        $channel = $nestedType;
    }

    return strtolower(trim((string) $channel));
}

function snippePayoutFailureReason(array $source): string
{
    $candidates = [
        $source['failure_reason'] ?? '',
        $source['reason'] ?? '',
        snippePayoutNested($source, 'failure.reason', ''),
        snippePayoutNested($source, 'failure_reason.message', ''),
        snippePayoutNested($source, 'error.message', ''),
    ];

    foreach ($candidates as $candidate) {
        $value = trim((string) $candidate);
        if ($value !== '') {
            return mb_substr($value, 0, 255);
        }
    }

    return '';
}

function snippeNormalizePhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === null) {
        return '';
    }

    if (str_starts_with($digits, '255') && strlen($digits) === 12) {
        return $digits;
    }

    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return '255' . substr($digits, 1);
    }

    if (strlen($digits) === 9 && (str_starts_with($digits, '6') || str_starts_with($digits, '7'))) {
        return '255' . $digits;
    }

    return '';
}

function snippeGetWebhookUrl(): string
{
    if (defined('APP_URL') && trim((string) APP_URL) !== '') {
        return rtrim((string) APP_URL, '/') . '/pages/snippe_webhook.php';
    }

    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . '/jassnet-incame/pages/snippe_webhook.php';
    }

    return '';
}

function snippePayoutRequest(string $method, string $endpoint, ?array $payload = null, ?string $idempotencyKey = null): array
{
    if (!defined('SNIPPE_API_KEY') || trim((string) SNIPPE_API_KEY) === '') {
        throw new RuntimeException('Snippe API key is missing.');
    }

    $url = rtrim((string) SNIPPE_API_BASE_URL, '/') . $endpoint;
    $headers = [
        'Authorization: Bearer ' . SNIPPE_API_KEY,
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    if ($idempotencyKey !== null && $idempotencyKey !== '') {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => defined('SNIPPE_API_TIMEOUT') ? (int) SNIPPE_API_TIMEOUT : 20,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Snippe payout request failed: ' . $curlError);
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        throw new RuntimeException('Snippe payout response is not valid JSON.');
    }

    if ($status < 200 || $status >= 300 || (($json['status'] ?? '') === 'error')) {
        $message = $json['message'] ?? $json['error'] ?? ('HTTP ' . $status);
        throw new RuntimeException('Snippe payout error: ' . $message);
    }

    return $json;
}

function snippeGetExpensePayoutRecipient(mysqli $conn, int $userId): ?array
{
    snippeEnsureUserPayoutFields($conn);

    $stmt = $conn->prepare('SELECT id, full_name, username, phone, payout_phone, bank_name, bank_account_number, preferred_payout_channel, employee_id FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? ($result->fetch_assoc() ?: null) : null;
}

function snippeCreateExpensePayout(mysqli $conn, array $expenseRequest, array $recipient, float $amountPaid, ?int $actorUserId = null, ?string $channel = null): array
{
    snippeEnsurePayoutTables($conn);

    $expenseRequestId = (int) ($expenseRequest['id'] ?? 0);
    if ($expenseRequestId <= 0) {
        throw new RuntimeException('Invalid expense request for payout.');
    }

    $existingStmt = $conn->prepare("SELECT * FROM snippe_payouts WHERE expense_request_id = ? AND status IN ('pending', 'processing', 'queued') ORDER BY id DESC LIMIT 1");
    if ($existingStmt) {
        $existingStmt->bind_param('i', $expenseRequestId);
        $existingStmt->execute();
        $existing = $existingStmt->get_result()->fetch_assoc();
        if ($existing) {
            return ['existing' => true, 'payout' => $existing];
        }
    }

    $payoutChannel = snippeNormalizePayoutChannel($channel ?? ($recipient['preferred_payout_channel'] ?? 'mobile'));

    $recipientName = trim((string) ($recipient['full_name'] ?? $recipient['username'] ?? 'Recipient'));
    $recipientPhone = snippeNormalizePhone((string) (($recipient['payout_phone'] ?? '') !== '' ? $recipient['payout_phone'] : ($recipient['phone'] ?? '')));
    $bankCode = snippeNormalizeBankCode((string) ($recipient['bank_name'] ?? ''));
    $bankAccountNumber = trim((string) ($recipient['bank_account_number'] ?? ''));

    $amountValue = max(0, (int) round($amountPaid));
    if ($amountValue <= 0) {
        throw new RuntimeException('Payout amount must be greater than zero.');
    }
    $minimumPayoutAmount = snippeGetMinimumPayoutAmount($payoutChannel);
    if ($amountValue < $minimumPayoutAmount) {
        throw new RuntimeException('Snippe payout amount for ' . ($payoutChannel === 'bank' ? 'bank transfer' : 'mobile money') . ' must be at least ' . $minimumPayoutAmount . '.');
    }

    $payload = [
        'amount' => $amountValue,
        'recipient_name' => $recipientName,
        'narration' => 'Expense payout for request #' . $expenseRequestId,
        'metadata' => [
            'employee_id' => (string) ($recipient['employee_id'] ?? ''),
            'expense_request_id' => (string) $expenseRequestId,
            'requested_by' => (string) ($expenseRequest['requested_by'] ?? ''),
        ],
    ];

    if ($payoutChannel === 'bank') {
        if ($bankCode === '') {
            throw new RuntimeException('Selected user does not have a supported Snippe bank code saved.');
        }
        if ($bankAccountNumber === '') {
            throw new RuntimeException('Selected user does not have a bank account number saved.');
        }

        $payload['channel'] = 'bank';
        $payload['recipient_bank'] = $bankCode;
        $payload['recipient_account'] = $bankAccountNumber;
    } else {
        if ($recipientPhone === '') {
            throw new RuntimeException('Selected user does not have a valid payout phone number in format 255XXXXXXXXX.');
        }

        $payload['channel'] = 'mobile';
        $payload['recipient_phone'] = $recipientPhone;
    }

    $webhookUrl = snippeGetWebhookUrl();
    if ($webhookUrl !== '') {
        $payload['webhook_url'] = $webhookUrl;
    }

    $idempotencyKey = hash('sha256', 'expense-payout-' . $expenseRequestId . '-' . $amountValue . '-' . $payoutChannel . '-' . ($payoutChannel === 'bank' ? $bankCode . '-' . $bankAccountNumber : $recipientPhone));
    $response = snippePayoutRequest('POST', '/v1/payouts/send', $payload, $idempotencyKey);
    $data = $response['data'] ?? [];
    $reference = (string) ($data['reference'] ?? '');

    if ($reference === '') {
        throw new RuntimeException('Snippe payout response did not include a reference.');
    }

    $status = strtolower((string) ($data['status'] ?? 'pending'));
    $externalReference = (string) ($data['external_reference'] ?? '');
    $providerPayoutId = (string) ($data['id'] ?? '');
    $responseChannel = snippePayoutChannelValue($data, $payoutChannel);
    $feesValue = snippePayoutValue($data['fee_amount'] ?? ($data['fees'] ?? null), 'fees.value', $data, 0);
    $totalValue = snippePayoutValue($data['total'] ?? null, 'total.value', $data, $amountValue + $feesValue);
    $narration = (string) ($payload['narration'] ?? '');
    $responseRecipientPhone = snippeNormalizePhone((string) snippePayoutNested($data, 'recipient.phone', $recipientPhone));
    $responseBankCode = snippeNormalizeBankCode((string) snippePayoutNested($data, 'recipient.bank', $bankCode));
    $responseBankAccount = trim((string) snippePayoutNested($data, 'recipient.account', $bankAccountNumber));
    $rawResponse = json_encode($response);

    $failureReason = snippePayoutFailureReason($data);

    $insert = $conn->prepare('INSERT INTO snippe_payouts (expense_request_id, station_request_id, user_id, payout_channel, recipient_name, recipient_phone, bank_name, bank_account_number, amount_value, fees_value, total_value, reference, external_reference, provider_payout_id, narration, failure_reason, status, raw_response, created_by, completed_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$insert) {
        throw new RuntimeException('Could not prepare payout log insert.');
    }

    $completedAt = in_array($status, ['completed', 'success'], true) ? date('Y-m-d H:i:s') : null;
    $insert->bind_param(
        'iisssssdddsssssssis',
        $expenseRequestId,
        $recipient['id'],
        $responseChannel,
        $recipientName,
        $responseRecipientPhone,
        $responseBankCode,
        $responseBankAccount,
        $amountPaid,
        $feesValue,
        $totalValue,
        $reference,
        $externalReference,
        $providerPayoutId,
        $narration,
        $failureReason,
        $status,
        $rawResponse,
        $actorUserId,
        $completedAt
    );
    $insert->execute();

    $payoutId = (int) $conn->insert_id;
    $rowStmt = $conn->prepare('SELECT * FROM snippe_payouts WHERE id = ? LIMIT 1');
    $rowStmt->bind_param('i', $payoutId);
    $rowStmt->execute();
    $payoutRow = $rowStmt->get_result()->fetch_assoc() ?: [];

    if (in_array($status, ['completed', 'success'], true)) {
        snippeSendPayoutCompletedSms($conn, $payoutRow);
    }

    return ['existing' => false, 'payout' => $payoutRow, 'response' => $response];
}

function snippeCreateStationPayout(mysqli $conn, array $stationRequest, array $recipient, float $amountPaid, ?int $actorUserId = null, ?string $channel = null): array
{
    snippeEnsurePayoutTables($conn);

    $stationRequestId = (int) ($stationRequest['id'] ?? 0);
    if ($stationRequestId <= 0) {
        throw new RuntimeException('Invalid station request for payout.');
    }

    $existingStmt = $conn->prepare("SELECT * FROM snippe_payouts WHERE station_request_id = ? AND status IN ('pending', 'processing', 'queued') ORDER BY id DESC LIMIT 1");
    if ($existingStmt) {
        $existingStmt->bind_param('i', $stationRequestId);
        $existingStmt->execute();
        $existing = $existingStmt->get_result()->fetch_assoc();
        if ($existing) {
            return ['existing' => true, 'payout' => $existing];
        }
    }

    $payoutChannel = snippeNormalizePayoutChannel($channel ?? ($recipient['preferred_payout_channel'] ?? 'mobile'));
    $recipientName = trim((string) ($recipient['full_name'] ?? $recipient['username'] ?? 'Recipient'));
    $recipientPhone = snippeNormalizePhone((string) (($recipient['payout_phone'] ?? '') !== '' ? $recipient['payout_phone'] : ($recipient['phone'] ?? '')));
    $bankCode = snippeNormalizeBankCode((string) ($recipient['bank_name'] ?? ''));
    $bankAccountNumber = trim((string) ($recipient['bank_account_number'] ?? ''));
    $amountValue = max(0, (int) round($amountPaid));
    if ($amountValue <= 0) {
        throw new RuntimeException('Station payout amount must be greater than zero.');
    }
    $minimumPayoutAmount = snippeGetMinimumPayoutAmount($payoutChannel);
    if ($amountValue < $minimumPayoutAmount) {
        throw new RuntimeException('Snippe payout amount for ' . ($payoutChannel === 'bank' ? 'bank transfer' : 'mobile money') . ' must be at least ' . $minimumPayoutAmount . '.');
    }

    $payload = [
        'amount' => $amountValue,
        'recipient_name' => $recipientName,
        'narration' => 'Station payout for request #' . $stationRequestId,
        'metadata' => [
            'station_request_id' => (string) $stationRequestId,
            'requested_by' => (string) ($stationRequest['requested_by'] ?? ''),
            'employee_id' => (string) ($recipient['employee_id'] ?? ''),
        ],
    ];

    if ($payoutChannel === 'bank') {
        if ($bankCode === '' || $bankAccountNumber === '') {
            throw new RuntimeException('Selected user does not have complete bank payout details.');
        }
        $payload['channel'] = 'bank';
        $payload['recipient_bank'] = $bankCode;
        $payload['recipient_account'] = $bankAccountNumber;
    } else {
        if ($recipientPhone === '') {
            throw new RuntimeException('Selected user does not have a valid payout phone number.');
        }
        $payload['channel'] = 'mobile';
        $payload['recipient_phone'] = $recipientPhone;
    }

    $webhookUrl = snippeGetWebhookUrl();
    if ($webhookUrl !== '') {
        $payload['webhook_url'] = $webhookUrl;
    }

    $idempotencyKey = hash('sha256', 'station-payout-' . $stationRequestId . '-' . $amountValue . '-' . $payoutChannel . '-' . ($payoutChannel === 'bank' ? $bankCode . '-' . $bankAccountNumber : $recipientPhone));
    $response = snippePayoutRequest('POST', '/v1/payouts/send', $payload, $idempotencyKey);
    $data = $response['data'] ?? [];
    $reference = (string) ($data['reference'] ?? '');
    if ($reference === '') {
        throw new RuntimeException('Snippe station payout response did not include a reference.');
    }

    $status = strtolower((string) ($data['status'] ?? 'pending'));
    $externalReference = (string) ($data['external_reference'] ?? '');
    $providerPayoutId = (string) ($data['id'] ?? '');
    $responseChannel = snippePayoutChannelValue($data, $payoutChannel);
    $feesValue = snippePayoutValue($data['fee_amount'] ?? ($data['fees'] ?? null), 'fees.value', $data, 0);
    $totalValue = snippePayoutValue($data['total'] ?? null, 'total.value', $data, $amountValue + $feesValue);
    $narration = (string) ($payload['narration'] ?? '');
    $responseRecipientPhone = snippeNormalizePhone((string) snippePayoutNested($data, 'recipient.phone', $recipientPhone));
    $responseBankCode = snippeNormalizeBankCode((string) snippePayoutNested($data, 'recipient.bank', $bankCode));
    $responseBankAccount = trim((string) snippePayoutNested($data, 'recipient.account', $bankAccountNumber));
    $rawResponse = json_encode($response);

    $failureReason = snippePayoutFailureReason($data);

    $insert = $conn->prepare('INSERT INTO snippe_payouts (expense_request_id, station_request_id, user_id, payout_channel, recipient_name, recipient_phone, bank_name, bank_account_number, amount_value, fees_value, total_value, reference, external_reference, provider_payout_id, narration, failure_reason, status, raw_response, created_by, completed_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$insert) {
        throw new RuntimeException('Could not prepare station payout log insert.');
    }

    $completedAt = in_array($status, ['completed', 'success'], true) ? date('Y-m-d H:i:s') : null;
    $insert->bind_param(
        'iisssssdddsssssssis',
        $stationRequestId,
        $recipient['id'],
        $responseChannel,
        $recipientName,
        $responseRecipientPhone,
        $responseBankCode,
        $responseBankAccount,
        $amountPaid,
        $feesValue,
        $totalValue,
        $reference,
        $externalReference,
        $providerPayoutId,
        $narration,
        $failureReason,
        $status,
        $rawResponse,
        $actorUserId,
        $completedAt
    );
    $insert->execute();

    $payoutId = (int) $conn->insert_id;
    $rowStmt = $conn->prepare('SELECT * FROM snippe_payouts WHERE id = ? LIMIT 1');
    $rowStmt->bind_param('i', $payoutId);
    $rowStmt->execute();
    $payoutRow = $rowStmt->get_result()->fetch_assoc() ?: [];

    if (in_array($status, ['completed', 'success'], true)) {
        snippeSendPayoutCompletedSms($conn, $payoutRow);
    }

    return ['existing' => false, 'payout' => $payoutRow, 'response' => $response];
}

function snippeFetchPayoutStatus(string $reference): array
{
    $reference = trim($reference);
    if ($reference === '') {
        throw new RuntimeException('Missing payout reference.');
    }

    return snippePayoutRequest('GET', '/v1/payouts/' . rawurlencode($reference));
}

function snippeSendPayoutCompletedSms(mysqli $conn, array $payoutRow): void
{
    if (!empty($payoutRow['sms_sent_at'])) {
        return;
    }

    $reference = (string) ($payoutRow['reference'] ?? '');
    if ($reference === '') {
        return;
    }

    $phone = snippeNormalizePhone((string) ($payoutRow['recipient_phone'] ?? ''));
    if ($phone === '') {
        return;
    }

    $amount = number_format((float) ($payoutRow['amount_value'] ?? 0), 2);
    $name = (string) ($payoutRow['recipient_name'] ?? 'User');
    $message = 'JASSNET: Payout ya Tshs. ' . $amount . ' imeingia kwa ' . $name . '. Ref: ' . $reference . '.';

    jassnet_sms($phone, $message);

    $stmt = $conn->prepare('UPDATE snippe_payouts SET sms_sent_at = NOW() WHERE reference = ?');
    if ($stmt) {
        $stmt->bind_param('s', $reference);
        $stmt->execute();
    }
}

function snippeUpdatePayoutFromData(mysqli $conn, array $payoutData, ?string $rawPayload = null): ?array
{
    snippeEnsurePayoutTables($conn);

    $reference = (string) ($payoutData['reference'] ?? '');
    if ($reference === '') {
        return null;
    }

    $status = strtolower((string) ($payoutData['status'] ?? 'pending'));
    $externalReference = (string) ($payoutData['external_reference'] ?? '');
    $providerPayoutId = (string) ($payoutData['id'] ?? '');
    $recipientName = (string) snippePayoutNested($payoutData, 'recipient.name', '');
    $recipientPhone = snippeNormalizePhone((string) snippePayoutNested($payoutData, 'recipient.phone', ''));
    $bankName = snippeNormalizeBankCode((string) snippePayoutNested($payoutData, 'recipient.bank', ''));
    $bankAccountNumber = (string) snippePayoutNested($payoutData, 'recipient.account', '');
    $payoutChannel = snippePayoutChannelValue($payoutData, 'mobile');
    $feesValue = snippePayoutValue($payoutData['fee_amount'] ?? ($payoutData['fees'] ?? null), 'fees.value', $payoutData, 0);
    $totalValue = snippePayoutValue($payoutData['total'] ?? null, 'total.value', $payoutData, 0);
    $amountValue = snippePayoutValue($payoutData['amount'] ?? null, 'amount.value', $payoutData, 0);
    $narration = (string) ($payoutData['narration'] ?? '');
    $failureReason = snippePayoutFailureReason($payoutData);
    $completedAtRaw = (string) ($payoutData['completed_at'] ?? '');
    $completedAt = $completedAtRaw !== '' ? date('Y-m-d H:i:s', strtotime($completedAtRaw)) : null;
    $webhookPayload = $rawPayload !== null ? $rawPayload : json_encode($payoutData);
    $isFailedStatus = in_array($status, ['failed', 'cancelled', 'canceled'], true);
    $clearCompletionState = $isFailedStatus ? 1 : 0;
    $completedAtToStore = in_array($status, ['completed', 'success'], true) && $completedAt !== null ? $completedAt : null;

    $stmt = $conn->prepare('UPDATE snippe_payouts SET payout_channel = COALESCE(NULLIF(?, ""), payout_channel), recipient_name = COALESCE(NULLIF(?, ""), recipient_name), recipient_phone = COALESCE(NULLIF(?, ""), recipient_phone), bank_name = COALESCE(NULLIF(?, ""), bank_name), bank_account_number = COALESCE(NULLIF(?, ""), bank_account_number), amount_value = CASE WHEN ? > 0 THEN ? ELSE amount_value END, fees_value = ?, total_value = CASE WHEN ? > 0 THEN ? ELSE total_value END, external_reference = COALESCE(NULLIF(?, ""), external_reference), provider_payout_id = COALESCE(NULLIF(?, ""), provider_payout_id), narration = COALESCE(NULLIF(?, ""), narration), failure_reason = CASE WHEN ? = 1 THEN NULLIF(?, "") ELSE NULL END, status = ?, webhook_payload = ?, completed_at = CASE WHEN ? = 1 THEN NULL WHEN ? IS NOT NULL THEN ? ELSE completed_at END, sms_sent_at = CASE WHEN ? = 1 THEN NULL ELSE sms_sent_at END WHERE reference = ?');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param(
        'sssssdddddsssisssissis',
        $payoutChannel,
        $recipientName,
        $recipientPhone,
        $bankName,
        $bankAccountNumber,
        $amountValue,
        $amountValue,
        $feesValue,
        $totalValue,
        $totalValue,
        $externalReference,
        $providerPayoutId,
        $narration,
        $isFailedStatus,
        $failureReason,
        $status,
        $webhookPayload,
        $clearCompletionState,
        $completedAtToStore,
        $completedAtToStore,
        $clearCompletionState,
        $reference
    );
    $stmt->execute();

    $rowStmt = $conn->prepare('SELECT * FROM snippe_payouts WHERE reference = ? LIMIT 1');
    if (!$rowStmt) {
        return null;
    }
    $rowStmt->bind_param('s', $reference);
    $rowStmt->execute();
    $updatedRow = $rowStmt->get_result()->fetch_assoc() ?: null;

    if ($updatedRow && in_array($status, ['completed', 'success'], true)) {
        snippeSendPayoutCompletedSms($conn, $updatedRow);
    }

    return $updatedRow;
}

function snippeRefreshPendingPayouts(mysqli $conn, int $limit = 5): void
{
    snippeEnsurePayoutTables($conn);

    $limit = max(1, min(20, $limit));
    $query = "SELECT reference FROM snippe_payouts WHERE status IN ('pending', 'processing', 'queued') ORDER BY id DESC LIMIT {$limit}";
    $result = $conn->query($query);
    if (!$result) {
        return;
    }

    while ($row = $result->fetch_assoc()) {
        try {
            $response = snippeFetchPayoutStatus((string) $row['reference']);
            $data = $response['data'] ?? [];
            if (is_array($data) && !empty($data)) {
                snippeUpdatePayoutFromData($conn, $data, json_encode($response));
            }
        } catch (Throwable $e) {
            continue;
        }
    }
}
