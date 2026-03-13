<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jassnet_bms";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

// Snippe Payment Gateway configuration (use environment variables for secrets)
if (!defined('SNIPPE_API_BASE_URL')) {
    define('SNIPPE_API_BASE_URL', getenv('SNIPPE_API_BASE_URL') ?: 'https://api.snippe.sh');
}

if (!defined('SNIPPE_API_KEY')) {
    define('SNIPPE_API_KEY', getenv('SNIPPE_API_KEY') ?: 'snp_017365ea48893db7b92c8f249ca36d6d49515afd63390f39817e00140ae47329');
}

if (!defined('SNIPPE_API_TIMEOUT')) {
    define('SNIPPE_API_TIMEOUT', 20);
}

if (!defined('SNIPPE_AUTO_SYNC_ENABLED')) {
    define('SNIPPE_AUTO_SYNC_ENABLED', true);
}

if (!defined('SNIPPE_AUTO_SYNC_INTERVAL_MINUTES')) {
    define('SNIPPE_AUTO_SYNC_INTERVAL_MINUTES', 15);
}

if (!defined('SNIPPE_AUTO_SYNC_LIMIT')) {
    define('SNIPPE_AUTO_SYNC_LIMIT', 100);
}

if (!defined('SNIPPE_AUTO_SYNC_MAX_PAGES')) {
    define('SNIPPE_AUTO_SYNC_MAX_PAGES', 3);
}

if (!defined('SNIPPE_WEBHOOK_SECRET')) {
    define('SNIPPE_WEBHOOK_SECRET', getenv('SNIPPE_WEBHOOK_SECRET') ?: 'whsec_bf0076a5cb2a5f4e513ba6d2ed8c1347b258c8cb93ab0e765e45709364394bf8');
}
?>