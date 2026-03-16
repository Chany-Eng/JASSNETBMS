<?php
/**
 * Direct login endpoint for testing and development
 * This is a simpler alternative to the full OTP flow
 * 
 * Usage: POST /login_direct.php with username and password
 */

// Must use the same session name as the rest of the application
session_name('JASSNET_SESSION');
session_start();

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_once 'config/init.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['message'] = 'Username and password are required';
    $_SESSION['message_type'] = 'error';
    header('Location: index.php');
    exit;
}

try {
    $userModel = new \App\Models\User();
    $user = $userModel->authenticate($username, $password);
    
    if (!$user) {
        $errors = $userModel->getErrors();
        $_SESSION['message'] = !empty($errors) ? reset($errors) : 'Invalid credentials';
        $_SESSION['message_type'] = 'error';
        header('Location: index.php');
        exit;
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = trim((string) ($user['full_name'] ?? '')) !== '' ? $user['full_name'] : $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['profile_photo'] = $user['profile_photo'] ?? '';
    
    // Log the login
    error_log('User ' . $username . ' logged in successfully via direct login endpoint');
    
    // Redirect to dashboard
    header('Location: dashboard.php');
    exit;
    
} catch (\Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    $_SESSION['message'] = 'An error occurred during login';
    $_SESSION['message_type'] = 'error';
    header('Location: index.php');
    exit;
}
?>
