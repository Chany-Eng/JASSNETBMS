<?php
/**
 * Application Initialization
 * 
 * Initialize all application settings, load config, autoloader, and databases
 */

// Load configuration first using a direct path.
// config.php defines APP_ROOT and other application constants.
require_once dirname(__DIR__) . '/config/config.php';

// Fallback in case APP_ROOT is not defined by configuration.
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load autoloader
require_once APP_ROOT . '/config/Autoloader.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Set error handler
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Check if user session timeout
if (isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: " . APP_URL . "/index.php?session=expired");
        exit();
    }
}
$_SESSION['last_activity'] = time();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
