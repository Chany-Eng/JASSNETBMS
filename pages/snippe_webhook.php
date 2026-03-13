<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payments.php';
require_once '../includes/snippe_payouts.php';
require_once '../includes/station_workflow.php';
require_once '../includes/expense_workflow.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit();
}

if (!defined('SNIPPE_WEBHOOK_SECRET') || trim((string) SNIPPE_WEBHOOK_SECRET) === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Webhook secret not configured']);
    exit();
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid payload']);
    exit();
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$signature = '';
foreach (['X-Snippe-Signature', 'Snippe-Signature', 'X-Webhook-Signature'] as $key) {
    if (!empty($headers[$key])) {
        $signature = (string) $headers[$key];
        break;
    }
}

if ($signature === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Missing signature header']);
    exit();
}

$expected = hash_hmac('sha256', $rawBody, SNIPPE_WEBHOOK_SECRET);
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Invalid signature']);
    exit();
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit();
}

$eventType = strtolower((string) ($payload['event'] ?? $payload['type'] ?? ''));
$payment = [];
if (isset($payload['data']) && is_array($payload['data'])) {
    $payment = $payload['data'];
} elseif (isset($payload['payment']) && is_array($payload['payment'])) {
    $payment = $payload['payment'];
} elseif (isset($payload['id'])) {
    $payment = $payload;
}

if (empty($payment)) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'message' => 'No payment payload to import']);
    exit();
}

$objectType = strtolower((string) ($payment['object'] ?? $payload['object'] ?? ''));
if ($objectType === 'payout' || str_contains($eventType, 'payout')) {
    snippeEnsurePayoutTables($conn);
    stationEnsureWorkflowSchema($conn);
    expenseEnsureWorkflowSchema($conn);
    $updatedPayout = snippeUpdatePayoutFromData($conn, $payment, $rawBody);
    if ($updatedPayout && !empty($updatedPayout['station_request_id'])) {
        stationSyncPayoutStatus($conn, (int) $updatedPayout['station_request_id']);
    }
    if ($updatedPayout && !empty($updatedPayout['expense_request_id'])) {
        expenseSyncPayoutStatus($conn, (int) $updatedPayout['expense_request_id']);
    }
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'message' => $updatedPayout ? ('Payout webhook processed. Status: ' . ($updatedPayout['status'] ?? 'unknown')) : 'Payout webhook received but no matching payout record was found'
    ]);
    exit();
}

if ($eventType !== '' && !in_array($eventType, ['payment.paid', 'payment.completed', 'payment.success', 'payment.successful', 'payment.created'], true)) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'message' => 'Event ignored']);
    exit();
}

snippeEnsureTables($conn);
$result = snippeImportPayments($conn, [$payment], 1);
$status = ($result['imported'] > 0 && $result['failed'] === 0) ? 'success' : (($result['failed'] > 0) ? 'failed' : 'skipped');
$message = 'Webhook processed. Imported: ' . $result['imported'] . ', Skipped: ' . $result['skipped'] . ', Failed: ' . $result['failed'];

snippeLogSync(
    $conn,
    'webhook',
    1,
    0,
    $result,
    $status,
    $message,
    null,
    date('Y-m-d H:i:s'),
    date('Y-m-d H:i:s')
);

http_response_code(200);
echo json_encode(['ok' => true, 'message' => $message]);
