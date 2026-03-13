<?php
$pageTitle = 'Login';

$sessionMessage = $_SESSION['message'] ?? null;
$sessionMessageType = $_SESSION['message_type'] ?? 'info';

if ($sessionMessage) {
    unset($_SESSION['message'], $_SESSION['message_type']);
}

$displayMessage = $message ?? $sessionMessage;
$displayType = $messageType ?: $sessionMessageType;

$alertClass = 'alert-info';
if ($displayType === 'error') {
    $alertClass = 'alert-danger';
} elseif ($displayType === 'success') {
    $alertClass = 'alert-success';
} elseif ($displayType === 'warning') {
    $alertClass = 'alert-warning';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header text-center">
                        <h4 class="mb-0">JASSNET BMS Login</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($displayMessage)): ?>
                            <div class="alert <?= $alertClass ?>" role="alert">
                                <?= htmlspecialchars($displayMessage) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= APP_URL ?>/index.php" autocomplete="on">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" id="username" name="username" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" id="password" name="password" class="form-control" required>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Sign In</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
