<?php
try {
    require_once 'config/init.php';

    $controller = new \App\Controllers\AuthController();

    // Dispatch appropriate action based on request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->loginSubmit();
    } else {
        $controller->login();
    }
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('Application bootstrap error: ' . $e->getMessage());

    $showDetails = defined('APP_DEBUG') ? APP_DEBUG : true;
    $message = $showDetails
        ? $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()
        : 'Application error. Please contact administrator.';

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Application Error</title></head><body>';
    echo '<h2>Application could not start</h2>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
}
