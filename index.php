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

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Application Error</title><style>';
    echo 'body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:linear-gradient(135deg,#edf4fb 0%,#f7fbff 48%,#e8f0f8 100%);display:flex;align-items:center;justify-content:center;min-height:100vh;color:#223047;}';
    echo '.error-shell{width:min(720px,calc(100vw - 32px));background:#fff;border:1px solid #dbe5ef;border-radius:28px;box-shadow:0 24px 60px rgba(15,23,42,.12);overflow:hidden;}';
    echo '.error-banner{padding:32px;background:linear-gradient(145deg,#17365c 0%,#21518b 48%,#2969c7 100%);color:#fff;}';
    echo '.error-body{padding:28px 32px;}';
    echo '.error-chip{display:inline-block;padding:8px 14px;border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.16);font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;}';
    echo '.error-copy{color:#60758d;line-height:1.6;}';
    echo '.error-action{display:inline-flex;align-items:center;gap:8px;margin-top:18px;padding:12px 18px;border-radius:14px;background:#2969c7;color:#fff;text-decoration:none;font-weight:700;}';
    echo '</style></head><body>';
    echo '<div class="error-shell"><div class="error-banner"><div class="error-chip">JASSNET Entry Point</div><h2>Application could not start</h2><p>Please review the startup error below or return to the sign-in page after the issue is resolved.</p></div><div class="error-body"><div class="error-copy">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</div><a class="error-action" href="index.php">Return to Login</a></div></div>';
    echo '</body></html>';
}
