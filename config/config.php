<?php
/**
 * JASSNET Business Management System - Configuration
 * 
 * This file contains all configuration settings for the application
 */

// avoid re-definition when included multiple times
if (defined('APP_ROOT')) {
    return;
}

// Environment
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', APP_ENV === 'development');

// Application Name and Version
define('APP_NAME', 'JASSNET ERMS');
define('APP_BRAND', 'ERMS');
define('APP_VERSION', '2.0.0');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'jassnet_bms');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
if (!defined('APP_URL')) {
    define('APP_URL', 'http://localhost/jassnet-incame');
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(dirname(__FILE__)));
}

if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', APP_ROOT . '/public');
}

if (!defined('UPLOADS_PATH')) {
    define('UPLOADS_PATH', APP_ROOT . '/uploads');
}

if (!defined('ASSETS_PATH')) {
    define('ASSETS_PATH', '/assets');
}

// Session
define('SESSION_TIMEOUT', 3600);
define('SESSION_NAME', 'JASSNET_SESSION');

// Password Policy
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_EXPIRATION_DAYS', 28);
define('PASSWORD_WARNING_DAYS', 5);
define('LOGIN_OTP_EXPIRY_MINUTES', 5);
define('LOGIN_OTP_RESEND_SECONDS', 60);
define('LOGIN_OTP_MAX_ATTEMPTS', 5);

// File Upload Settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);

// SMS Configuration
define('SENDER_ID', 'JASSNET');
define('SMS_API_URL', getenv('SMS_API_URL') ?: 'http://mshastra.com/sendsms_api.json.aspx');
define('SMS_API_USERNAME', getenv('SMS_API_USERNAME') ?: 'jassnet012');
define('SMS_API_PASSWORD', getenv('SMS_API_PASSWORD') ?: 'p4_sm661');
define('SMS_API_KEY', getenv('SMS_API_KEY') ?: '');
define('SMS_PROVIDER', getenv('SMS_PROVIDER') ?: 'custom');

// WhatsApp Configuration
define('WHATSAPP_API_VERSION', getenv('WHATSAPP_API_VERSION') ?: 'v22.0');
define('WHATSAPP_API_BASE_URL', getenv('WHATSAPP_API_BASE_URL') ?: 'https://graph.facebook.com');
define('WHATSAPP_API_TOKEN', getenv('WHATSAPP_API_TOKEN') ?: 'EAF0d4yFdn7YBQ7Fwyzdk0NI1LwszszGnBEg6UCZAKtdDF2TY9slp2rKQyboGWRzZAMwp8JKDLIfMV8hBb39x5x7FMKX1eZAHJwUFZAWJOBgA5OzWe8RRZB0YOyOc3KikNc1bynmMrcPycacE0XPYoVdhyxU8WPp6hrNHDsg0IGZBZB6SHB3PCP3sljN5FFZC8OfUw9rrpoQCo2pJBpfWqwaG7ipCdZCO9HIkP16YjFOdxnkZCeuYfkGhbTPP8jevt1AtzfZCvFj3JLogs7lJdRbIgowYYwm7udEiUSXkAZDZD');
define('WHATSAPP_PHONE_NUMBER_ID', getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '1023890624140571');
define('WHATSAPP_MESSAGE_MODE', getenv('WHATSAPP_MESSAGE_MODE') ?: 'text');
define('WHATSAPP_DEFAULT_TEMPLATE', getenv('WHATSAPP_DEFAULT_TEMPLATE') ?: 'hello_world');
define('WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE', getenv('WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE') ?: 'en_US');
define('WHATSAPP_OTP_TEMPLATE', getenv('WHATSAPP_OTP_TEMPLATE') ?: '');
define('WHATSAPP_OTP_TEMPLATE_LANGUAGE', getenv('WHATSAPP_OTP_TEMPLATE_LANGUAGE') ?: 'en_US');
define('WHATSAPP_ENABLED', getenv('WHATSAPP_ENABLED') !== false
    ? filter_var(getenv('WHATSAPP_ENABLED'), FILTER_VALIDATE_BOOLEAN)
    : (WHATSAPP_API_TOKEN !== '' && WHATSAPP_PHONE_NUMBER_ID !== '')
);

// Snippe Payment Gateway
define('SNIPPE_API_BASE_URL', getenv('SNIPPE_API_BASE_URL') ?: 'https://api.snippe.sh');
define('SNIPPE_API_KEY', getenv('SNIPPE_API_KEY') ?: 'snp_017365ea48893db7b92c8f249ca36d6d49515afd63390f39817e00140ae47329');
define('SNIPPE_API_TIMEOUT', 20);
define('SNIPPE_AUTO_SYNC_ENABLED', getenv('SNIPPE_AUTO_SYNC_ENABLED') !== false ? filter_var(getenv('SNIPPE_AUTO_SYNC_ENABLED'), FILTER_VALIDATE_BOOLEAN) : true);
define('SNIPPE_AUTO_SYNC_INTERVAL_MINUTES', (int) (getenv('SNIPPE_AUTO_SYNC_INTERVAL_MINUTES') ?: 15));
define('SNIPPE_AUTO_SYNC_LIMIT', (int) (getenv('SNIPPE_AUTO_SYNC_LIMIT') ?: 100));
define('SNIPPE_AUTO_SYNC_MAX_PAGES', (int) (getenv('SNIPPE_AUTO_SYNC_MAX_PAGES') ?: 3));
define('SNIPPE_WEBHOOK_SECRET', getenv('SNIPPE_WEBHOOK_SECRET') ?: 'whsec_bf0076a5cb2a5f4e513ba6d2ed8c1347b258c8cb93ab0e765e45709364394bf8');

// Pagination
define('ITEMS_PER_PAGE', 25);

// Date Format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'M d, Y');
define('DISPLAY_DATETIME_FORMAT', 'M d, Y h:i A');

// Currency
define('CURRENCY_SYMBOL', 'Tshs.');
define('CURRENCY_CODE', 'TZS');

// Timezone
date_default_timezone_set('UTC');

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// User Roles
define('ROLE_SUPER_ADMIN', 'Super Admin');
define('ROLE_DIRECTOR', 'Director');
define('ROLE_MANAGER', 'Manager');
define('ROLE_ACCOUNTANT', 'Accountant');
define('ROLE_STORE_KEEPER', 'Store Keeper');
define('ROLE_TECHNICIAN', 'Technician');
define('ROLE_SALES', 'Sales');

// Permissions (roles that can perform actions)
define('CAN_MANAGE_USERS', ['Super Admin', 'Director']);
define('CAN_APPROVE_EXPENSES', ['Super Admin', 'Director', 'Manager', 'Accountant']);
define('CAN_APPROVE_STATIONS', ['Super Admin', 'Director', 'Manager']);
define('CAN_MANAGE_INVENTORY', ['Super Admin', 'Store Keeper', 'Manager']);
define('CAN_VIEW_REPORTS', ['Super Admin', 'Director', 'Manager', 'Accountant', 'Sales']);
define('CAN_REQUEST_EXPENSE', ['Super Admin', 'Technician', 'Sales']);
define('CAN_REQUEST_STATION', ['Super Admin', 'Technician']);
define('CAN_ADD_INCOME', ['Super Admin', 'Sales', 'Technician']);

// Expense Request Status
define('EXPENSE_STATUS_PENDING', 'Pending');
define('EXPENSE_STATUS_APPROVED', 'Approved');
define('EXPENSE_STATUS_REJECTED', 'Rejected');
define('EXPENSE_STATUS_COMPLETED', 'Completed');

// Station Request Status
define('STATION_STATUS_PENDING', 'Pending');
define('STATION_STATUS_APPROVED', 'Approved');
define('STATION_STATUS_IN_PROGRESS', 'In Progress');
define('STATION_STATUS_COMPLETED', 'Completed');
define('STATION_STATUS_REJECTED', 'Rejected');
