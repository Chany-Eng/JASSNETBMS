<?php
session_start();

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
        header("Location: dashboard.php?error=unauthorized");
        exit();
    }
}

// Function to sanitize input
function sanitize($data) {
    global $conn;
    return $conn->real_escape_string(trim($data));
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
    $result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity < 5");
    $stats['low_stock'] = $result->fetch_assoc()['count'] ?? 0;
    
    return $stats;
}
?>