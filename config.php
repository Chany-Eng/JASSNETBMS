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

if (!defined('SENDER_ID')) {
    define('SENDER_ID', 'JASSNET');
}

if (!defined('SMS_API_URL')) {
    define('SMS_API_URL', getenv('SMS_API_URL') ?: 'http://mshastra.com/sendsms_api.json.aspx');
}

if (!defined('SMS_API_USERNAME')) {
    define('SMS_API_USERNAME', getenv('SMS_API_USERNAME') ?: 'jassnet012');
}

if (!defined('SMS_API_PASSWORD')) {
    define('SMS_API_PASSWORD', getenv('SMS_API_PASSWORD') ?: 'p4_sm661');
}

if (!defined('WHATSAPP_API_VERSION')) {
    define('WHATSAPP_API_VERSION', getenv('WHATSAPP_API_VERSION') ?: 'v22.0');
}

if (!defined('WHATSAPP_API_BASE_URL')) {
    define('WHATSAPP_API_BASE_URL', getenv('WHATSAPP_API_BASE_URL') ?: 'https://graph.facebook.com');
}

if (!defined('WHATSAPP_API_TOKEN')) {
    define('WHATSAPP_API_TOKEN', getenv('WHATSAPP_API_TOKEN') ?: 'EAF0d4yFdn7YBQ7Fwyzdk0NI1LwszszGnBEg6UCZAKtdDF2TY9slp2rKQyboGWRzZAMwp8JKDLIfMV8hBb39x5x7FMKX1eZAHJwUFZAWJOBgA5OzWe8RRZB0YOyOc3KikNc1bynmMrcPycacE0XPYoVdhyxU8WPp6hrNHDsg0IGZBZB6SHB3PCP3sljN5FFZC8OfUw9rrpoQCo2pJBpfWqwaG7ipCdZCO9HIkP16YjFOdxnkZCeuYfkGhbTPP8jevt1AtzfZCvFj3JLogs7lJdRbIgowYYwm7udEiUSXkAZDZD');
}

if (!defined('WHATSAPP_PHONE_NUMBER_ID')) {
    define('WHATSAPP_PHONE_NUMBER_ID', getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '1023890624140571');
}

if (!defined('WHATSAPP_MESSAGE_MODE')) {
    define('WHATSAPP_MESSAGE_MODE', getenv('WHATSAPP_MESSAGE_MODE') ?: 'text');
}

if (!defined('WHATSAPP_DEFAULT_TEMPLATE')) {
    define('WHATSAPP_DEFAULT_TEMPLATE', getenv('WHATSAPP_DEFAULT_TEMPLATE') ?: 'hello_world');
}

if (!defined('WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE')) {
    define('WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE', getenv('WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE') ?: 'en_US');
}

if (!defined('WHATSAPP_OTP_TEMPLATE')) {
    define('WHATSAPP_OTP_TEMPLATE', getenv('WHATSAPP_OTP_TEMPLATE') ?: '');
}

if (!defined('WHATSAPP_OTP_TEMPLATE_LANGUAGE')) {
    define('WHATSAPP_OTP_TEMPLATE_LANGUAGE', getenv('WHATSAPP_OTP_TEMPLATE_LANGUAGE') ?: 'en_US');
}

if (!defined('WHATSAPP_ENABLED')) {
    define('WHATSAPP_ENABLED', getenv('WHATSAPP_ENABLED') !== false
        ? filter_var(getenv('WHATSAPP_ENABLED'), FILTER_VALIDATE_BOOLEAN)
        : (WHATSAPP_API_TOKEN !== '' && WHATSAPP_PHONE_NUMBER_ID !== '')
    );
}

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