<?php
// Keep legacy pages on the same session cookie used by the MVC entry points.
if (session_status() === PHP_SESSION_NONE) {
    session_name('JASSNET_SESSION');
    session_start();
}

// Include config
require_once __DIR__ . '/../config.php';

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to get current user
function getCurrentUser() {
    global $conn;
    if (!isLoggedIn()) return null;
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to check password expiration
function checkPasswordExpiration($user) {
    $last_changed = new DateTime($user['password_last_changed']);
    $now = new DateTime();
    $days_since_change = $now->diff($last_changed)->days;
    
    if ($days_since_change > 28) {
        return 'expired';
    } elseif ($days_since_change > 23) {
        return 'warning';
    }
    return 'ok';
}

// Function to validate password strength
function validatePassword($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
    return true;
}

// Function to check permissions (supports multiple roles per user, comma-separated)
// Super admin automatically bypasses checks.
function hasPermission($required_roles) {
    $user = getCurrentUser();
    if (!$user) return false;

    $userRoles = array_map('trim', explode(',', $user['role']));
    $lowerRoles = array_map('strtolower', $userRoles);

    // Super admin has all permissions
    if (in_array('super admin', $lowerRoles) || in_array('superadmin', $lowerRoles)) {
        return true;
    }

    foreach ($required_roles as $r) {
        if (in_array(strtolower($r), $lowerRoles)) {
            return true;
        }
    }
    return false;
}

// SMS configuration and helper - using professional gateway wrapper
require_once __DIR__ . '/jassnet_sms.php';

/**
 * Send an SMS via the JASSNET gateway.
 *
 * @param string $phone   Recipient phone number (international format)
 * @param string $message Text message body
 * @return array|null      Decoded JSON response from provider or null on failure
 */
function jassnet_sms($phone, $message) {
    global $smsSender;
    if (!isset($smsSender)) {
        return null;
    }
    return $smsSender->sendSMS($phone, $message, SENDER_ID);
}

// Function to redirect if not authorized
function requirePermission($required_roles) {
    if (!hasPermission($required_roles)) {
        header("Location: ../dashboard.php?error=unauthorized");
        exit();
    }
}

// Function to sanitize input
function sanitize($data) {
    global $conn;
    return $conn->real_escape_string(trim($data));
}

function dbColumnExists(mysqli $conn, string $tableName, string $columnName): bool
{
    $safeTable = $conn->real_escape_string($tableName);
    $safeColumn = $conn->real_escape_string($columnName);
    $result = $conn->query("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function dbTableExists(mysqli $conn, string $tableName): bool
{
    $safeTable = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function ensureActivityLogSchema(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            user_role VARCHAR(120) NULL,
            action VARCHAR(60) NOT NULL,
            description TEXT NOT NULL,
            table_name VARCHAR(120) NULL,
            record_id INT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_activity_logs_user (user_id),
            KEY idx_activity_logs_action (action),
            KEY idx_activity_logs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ensureNotificationSchema(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS user_notification_states (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            notification_key VARCHAR(190) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_notification_key (user_id, notification_key),
            KEY idx_user_notification_read (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function getNotificationReadMap(mysqli $conn, int $userId): array
{
    ensureNotificationSchema($conn);

    $map = [];
    $stmt = $conn->prepare('SELECT notification_key, is_read FROM user_notification_states WHERE user_id = ?');
    if (!$stmt) {
        return $map;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $map[(string) ($row['notification_key'] ?? '')] = (int) ($row['is_read'] ?? 0) === 1;
    }

    return $map;
}

function markNotificationAsRead(mysqli $conn, string $notificationKey): void
{
    if (!isLoggedIn()) {
        return;
    }

    $notificationKey = trim($notificationKey);
    if ($notificationKey === '') {
        return;
    }

    ensureNotificationSchema($conn);
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $stmt = $conn->prepare(
        'INSERT INTO user_notification_states (user_id, notification_key, is_read, read_at) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE is_read = VALUES(is_read), read_at = VALUES(read_at)'
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('is', $userId, $notificationKey);
    $stmt->execute();
}

function buildNotificationTarget(string $baseTarget, string $notificationKey): string
{
    $parts = explode('#', $baseTarget, 2);
    $path = $parts[0] ?? '';
    $hash = isset($parts[1]) ? '#' . $parts[1] : '';
    $separator = str_contains($path, '?') ? '&' : '?';
    return $path . $separator . 'mark_notification=' . rawurlencode($notificationKey) . $hash;
}

function appLogActivity(mysqli $conn, string $action, string $description, string $tableName = '', ?int $recordId = null): void
{
    if (!isLoggedIn()) {
        return;
    }

    ensureActivityLogSchema($conn);

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $userRole = trim((string) ($_SESSION['role'] ?? ''));
    $ipAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $userAgent = substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);
    $normalizedTableName = $tableName !== '' ? $tableName : null;

    $stmt = $conn->prepare('INSERT INTO activity_logs (user_id, user_role, action, description, table_name, record_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        'issssiss',
        $userId,
        $userRole,
        $action,
        $description,
        $normalizedTableName,
        $recordId,
        $ipAddress,
        $userAgent
    );
    $stmt->execute();
}

function appUsernameSlugPart(string $value): string
{
    $normalized = strtolower(trim($value));
    if ($normalized === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($converted !== false) {
            $normalized = strtolower($converted);
        }
    }

    $normalized = preg_replace('/[^a-z0-9]+/', '.', $normalized);
    return trim((string) $normalized, '.');
}

function composeFullNameFromParts(string $firstName, string $middleName = '', string $lastName = ''): string
{
    $parts = [trim($firstName), trim($middleName), trim($lastName)];
    $parts = array_values(array_filter($parts, static function ($part) {
        return $part !== '';
    }));

    return trim(implode(' ', $parts));
}

function splitFullNameParts(string $fullName): array
{
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];
    $parts = array_values(array_filter($parts, static function ($part) {
        return trim((string) $part) !== '';
    }));

    if (count($parts) <= 1) {
        return [
            'first_name' => $parts[0] ?? '',
            'middle_name' => '',
            'last_name' => '',
        ];
    }

    if (count($parts) === 2) {
        return [
            'first_name' => $parts[0],
            'middle_name' => '',
            'last_name' => $parts[1],
        ];
    }

    $lastName = array_pop($parts);
    $firstName = array_shift($parts);
    return [
        'first_name' => $firstName,
        'middle_name' => implode(' ', $parts),
        'last_name' => $lastName,
    ];
}

function generateUniqueUsername(mysqli $conn, string $firstName, string $lastName, ?int $excludeUserId = null): string
{
    $firstPart = appUsernameSlugPart($firstName);
    $lastPart = appUsernameSlugPart($lastName);
    $base = trim($firstPart . '.' . $lastPart, '.');

    if ($base === '') {
        $base = 'user';
    }

    $candidate = $base;
    $suffix = 1;
    while (true) {
        if ($excludeUserId !== null && $excludeUserId > 0) {
            $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('si', $candidate, $excludeUserId);
            }
        } else {
            $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $candidate);
            }
        }

        if (!$stmt) {
            return $candidate;
        }

        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        if (!$existing) {
            return $candidate;
        }

        $suffix++;
        $candidate = $base . $suffix;
    }
}

function getUserActionNotifications(mysqli $conn): array
{
    if (!isLoggedIn()) {
        return [];
    }

    $notifications = [];
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $readMap = getNotificationReadMap($conn, $currentUserId);

    $pushNotification = static function (array &$items, string $type, int $recordId, string $statusKey, string $title, string $description, string $target, string $dateValue) use ($readMap): void {
        $notificationKey = strtolower($type . ':' . $recordId . ':' . $statusKey);
        $items[] = [
            'type' => $type,
            'record_id' => $recordId,
            'notification_key' => $notificationKey,
            'title' => $title,
            'description' => $description,
            'target' => buildNotificationTarget($target, $notificationKey),
            'date_value' => $dateValue,
            'is_read' => (bool) ($readMap[$notificationKey] ?? false),
        ];
    };

    if (dbTableExists($conn, 'expense_requests')) {
        if (hasPermission(['Manager'])) {
            $result = $conn->query("SELECT id, category, request_date FROM expense_requests WHERE status = 'Pending Manager Approval' ORDER BY request_date DESC LIMIT 5");
            while ($result && ($row = $result->fetch_assoc())) {
                $pushNotification($notifications, 'expense', (int) $row['id'], 'pending-manager-approval', 'Expense needs manager approval', (string) ($row['category'] ?? 'Expense request'), 'pages/view_expense_requests.php#expenseRow_' . (int) $row['id'], (string) ($row['request_date'] ?? ''));
            }
        }

        if (hasPermission(['Director'])) {
            $result = $conn->query("SELECT id, category, request_date FROM expense_requests WHERE status = 'Pending Director Approval' ORDER BY request_date DESC LIMIT 5");
            while ($result && ($row = $result->fetch_assoc())) {
                $pushNotification($notifications, 'expense', (int) $row['id'], 'pending-director-approval', 'Expense needs director approval', (string) ($row['category'] ?? 'Expense request'), 'pages/view_expense_requests.php#expenseRow_' . (int) $row['id'], (string) ($row['request_date'] ?? ''));
            }
        }

        if (hasPermission(['Accountant'])) {
            $result = $conn->query("SELECT id, category, request_date FROM expense_requests WHERE status = 'Pending Accountant Processing' ORDER BY request_date DESC LIMIT 5");
            while ($result && ($row = $result->fetch_assoc())) {
                $pushNotification($notifications, 'expense', (int) $row['id'], 'pending-accountant-processing', 'Expense waiting for accountant', (string) ($row['category'] ?? 'Expense request'), 'pages/view_expense_requests.php#expenseRow_' . (int) $row['id'], (string) ($row['request_date'] ?? ''));
            }
        }

        $receiptWhere = hasPermission(['Super Admin'])
            ? "status = 'Waiting for Receipt'"
            : "status = 'Waiting for Receipt' AND requested_by = {$currentUserId}";
        $result = $conn->query("SELECT id, category, request_date FROM expense_requests WHERE {$receiptWhere} ORDER BY request_date DESC LIMIT 5");
        while ($result && ($row = $result->fetch_assoc())) {
            $pushNotification($notifications, 'expense', (int) $row['id'], 'waiting-for-receipt', 'Receipt upload required', (string) ($row['category'] ?? 'Expense request'), 'pages/view_expense_requests.php#expenseRow_' . (int) $row['id'], (string) ($row['request_date'] ?? ''));
        }
    }

    if (dbTableExists($conn, 'station_requests')) {
        if (hasPermission(['Manager'])) {
            $result = $conn->query("SELECT id, station_name, request_date FROM station_requests WHERE status = 'Pending Manager Approval' ORDER BY request_date DESC LIMIT 5");
            while ($result && ($row = $result->fetch_assoc())) {
                $pushNotification($notifications, 'station', (int) $row['id'], 'pending-manager-approval', 'Station needs manager approval', (string) ($row['station_name'] ?? 'Station request'), 'pages/stations.php#stationRow_' . (int) $row['id'], (string) ($row['request_date'] ?? ''));
            }
        }

        if (hasPermission(['Director'])) {
            $result = $conn->query("SELECT id, station_name, request_date FROM station_requests WHERE status = 'Pending Director Approval' ORDER BY request_date DESC LIMIT 5");
            while ($result && ($row = $result->fetch_assoc())) {
                $pushNotification($notifications, 'station', (int) $row['id'], 'pending-director-approval', 'Station needs director approval', (string) ($row['station_name'] ?? 'Station request'), 'pages/stations.php#stationRow_' . (int) $row['id'], (string) ($row['request_date'] ?? ''));
            }
        }

        if (hasPermission(['Accountant'])) {
            $result = $conn->query("SELECT id, station_name, request_date FROM station_requests WHERE status = 'Awaiting Accountant Approval' ORDER BY request_date DESC LIMIT 5");
            while ($result && ($row = $result->fetch_assoc())) {
                $pushNotification($notifications, 'station', (int) $row['id'], 'awaiting-accountant-approval', 'Station waiting for accountant', (string) ($row['station_name'] ?? 'Station request'), 'pages/stations.php#stationRow_' . (int) $row['id'], (string) ($row['request_date'] ?? ''));
            }
        }

        if (hasPermission(['Store Keeper'])) {
            $result = $conn->query("SELECT id, station_name, request_date FROM station_requests WHERE status = 'Pending Store Keeper Approval' ORDER BY request_date DESC LIMIT 5");
            while ($result && ($row = $result->fetch_assoc())) {
                $pushNotification($notifications, 'station', (int) $row['id'], 'pending-store-keeper-approval', 'Station waiting for store keeper', (string) ($row['station_name'] ?? 'Station request'), 'pages/stations.php#stationRow_' . (int) $row['id'], (string) ($row['request_date'] ?? ''));
            }
        }

        $result = $conn->query("SELECT id, station_name, request_date, status FROM station_requests WHERE requested_by = {$currentUserId} AND status IN ('Approved', 'Installation in Progress') ORDER BY request_date DESC LIMIT 5");
        while ($result && ($row = $result->fetch_assoc())) {
            $title = (string) ($row['status'] ?? '') === 'Approved' ? 'Submit station costs' : 'Complete your station request';
            $pushNotification($notifications, 'station', (int) $row['id'], strtolower(str_replace(' ', '-', (string) ($row['status'] ?? ''))), $title, (string) ($row['station_name'] ?? 'Station request'), 'pages/stations.php#stationRow_' . (int) $row['id'], (string) ($row['request_date'] ?? ''));
        }
    }

    if (dbTableExists($conn, 'salary_requests')) {
        if (hasPermission(['Manager'])) {
            $result = $conn->query("SELECT id, salary_month, created_at FROM salary_requests WHERE status = 'Pending Manager Approval' ORDER BY created_at DESC LIMIT 5");
            while ($result && ($row = $result->fetch_assoc())) {
                $monthLabel = date('F Y', strtotime((string) ($row['salary_month'] ?? date('Y-m-01'))));
                $pushNotification($notifications, 'payroll', (int) $row['id'], 'pending-manager-approval', 'Salary request needs manager approval', $monthLabel, 'pages/payroll.php#salaryRow_' . (int) $row['id'], (string) ($row['created_at'] ?? ''));
            }
        }

        if (hasPermission(['Director'])) {
            $result = $conn->query("SELECT id, salary_month, created_at FROM salary_requests WHERE status = 'Pending Director Approval' ORDER BY created_at DESC LIMIT 5");
            while ($result && ($row = $result->fetch_assoc())) {
                $monthLabel = date('F Y', strtotime((string) ($row['salary_month'] ?? date('Y-m-01'))));
                $pushNotification($notifications, 'payroll', (int) $row['id'], 'pending-director-approval', 'Salary request needs director approval', $monthLabel, 'pages/payroll.php#salaryRow_' . (int) $row['id'], (string) ($row['created_at'] ?? ''));
            }
        }

        if (hasPermission(['Accountant'])) {
            $result = $conn->query("SELECT id, salary_month, created_at FROM salary_requests WHERE status = 'Pending Accountant Final Approval' ORDER BY created_at DESC LIMIT 5");
            while ($result && ($row = $result->fetch_assoc())) {
                $monthLabel = date('F Y', strtotime((string) ($row['salary_month'] ?? date('Y-m-01'))));
                $pushNotification($notifications, 'payroll', (int) $row['id'], 'pending-accountant-final-approval', 'Salary request waiting for final accountant approval', $monthLabel, 'pages/payroll.php#salaryRow_' . (int) $row['id'], (string) ($row['created_at'] ?? ''));
            }
        }

        $result = $conn->query("SELECT id, salary_month, finalized_at FROM salary_requests WHERE user_id = {$currentUserId} AND status = 'Paid' ORDER BY finalized_at DESC LIMIT 3");
        while ($result && ($row = $result->fetch_assoc())) {
            $monthLabel = date('F Y', strtotime((string) ($row['salary_month'] ?? date('Y-m-01'))));
            $pushNotification($notifications, 'payroll', (int) $row['id'], 'paid', 'Salary paid', $monthLabel, 'pages/payroll.php#salaryRow_' . (int) $row['id'], (string) ($row['finalized_at'] ?? ''));
        }
    }

    usort($notifications, static function ($left, $right) {
        return strcmp((string) ($right['date_value'] ?? ''), (string) ($left['date_value'] ?? ''));
    });

    return array_slice($notifications, 0, 12);
}

function ensureInventorySoftDeleteSchema(mysqli $conn) {
    $isDeletedColumn = $conn->query("SHOW COLUMNS FROM inventory LIKE 'is_deleted'");
    if ($isDeletedColumn && $isDeletedColumn->num_rows === 0) {
        $conn->query("ALTER TABLE inventory ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    }

    $deletedAtColumn = $conn->query("SHOW COLUMNS FROM inventory LIKE 'deleted_at'");
    if ($deletedAtColumn && $deletedAtColumn->num_rows === 0) {
        $conn->query("ALTER TABLE inventory ADD COLUMN deleted_at DATETIME NULL AFTER is_deleted");
    }
}

function getSuccessActorLabel($user = null) {
    if (is_array($user)) {
        $fullName = trim((string) ($user['full_name'] ?? ''));
        $username = trim((string) ($user['username'] ?? ''));
        return $fullName !== '' ? $fullName : $username;
    }

    $sessionFullName = trim((string) ($_SESSION['full_name'] ?? ''));
    $sessionUsername = trim((string) ($_SESSION['username'] ?? ''));
    if ($sessionFullName !== '') {
        return $sessionFullName;
    }
    if ($sessionUsername !== '') {
        return $sessionUsername;
    }

    $currentUser = getCurrentUser();
    if (!$currentUser) {
        return '';
    }

    $fullName = trim((string) ($currentUser['full_name'] ?? ''));
    $username = trim((string) ($currentUser['username'] ?? ''));
    return $fullName !== '' ? $fullName : $username;
}

function formatSuccessMessage($message, $user = null) {
    $message = trim((string) $message);
    if ($message === '') {
        return '';
    }

    $actor = getSuccessActorLabel($user);
    if ($actor === '') {
        return $message;
    }

    if (stripos($message, $actor) !== false) {
        return $message;
    }

    return $message . ' By: ' . $actor;
}

// Function to upload file
function uploadFile($file, $target_dir = "uploads/") {
    // Use absolute path to ensure directory exists
    $upload_dir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . $target_dir;
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $target_file = $upload_dir . basename($file["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if file is actual image or PDF
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "pdf") {
        return ["error" => "Only JPG, JPEG, PNG & PDF files are allowed."];
    }
    
    // Check file size (5MB max)
    if ($file["size"] > 5000000) {
        return ["error" => "File is too large."];
    }
    
    // Generate unique filename
    $new_filename = uniqid() . "." . $imageFileType;
    $target_file = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ["success" => $new_filename];
    } else {
        return ["error" => "Error uploading file. Make sure the uploads directory exists and is writable."];
    }
}

// Function to get dashboard stats
function getDashboardStats() {
    global $conn;
    ensureInventorySoftDeleteSchema($conn);
    $stats = [];
    
    // Total Income Today
    $today = date('Y-m-d');
    $result = $conn->query("SELECT SUM(amount) as total FROM income WHERE date = '$today'");
    $stats['income_today'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Total Income This Week
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $result = $conn->query("SELECT SUM(amount) as total FROM income WHERE date >= '$week_start'");
    $stats['income_week'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Total Income This Month
    $month_start = date('Y-m-01');
    $result = $conn->query("SELECT SUM(amount) as total FROM income WHERE date >= '$month_start'");
    $stats['income_month'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Total Approved Expenses
    $result = $conn->query("SELECT SUM(amount_requested) as total FROM expense_requests WHERE status = 'Completed'");
    $stats['approved_expenses'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Pending Expense Requests
    $result = $conn->query("SELECT COUNT(*) as count FROM expense_requests WHERE status NOT IN ('Completed', 'Rejected')");
    $stats['pending_requests'] = $result->fetch_assoc()['count'] ?? 0;
    
    // Net Profit (simplified)
    $stats['net_profit'] = $stats['income_month'] - $stats['approved_expenses'];
    
    // Low Stock Items
    $result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity < 5 AND COALESCE(is_deleted, 0) = 0");
    $stats['low_stock'] = $result->fetch_assoc()['count'] ?? 0;
    
    return $stats;
}
?>