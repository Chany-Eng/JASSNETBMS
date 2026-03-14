<?php
$pageTitle = 'Login';

$sessionMessage = $_SESSION['message'] ?? null;
$sessionMessageType = $_SESSION['message_type'] ?? 'info';

if ($sessionMessage) {
    unset($_SESSION['message'], $_SESSION['message_type']);
}

$displayMessage = $message ?? $sessionMessage;
$displayType = $messageType ?: $sessionMessageType;

$authTransition = $_SESSION['auth_transition'] ?? null;
$showLogoutTransition = is_array($authTransition) && (($authTransition['type'] ?? '') === 'logout');
$logoutTransitionName = trim((string) ($authTransition['name'] ?? 'User'));
$logoutTransitionTitle = 'Logout successful';
$logoutTransitionCopy = ($logoutTransitionName !== '' ? $logoutTransitionName : 'User') . ', session yako imefungwa salama.';

if ($showLogoutTransition) {
    unset($_SESSION['auth_transition']);
}

$alertClass = 'alert-info';
if ($displayType === 'error') {
    $alertClass = 'alert-danger';
} elseif ($displayType === 'success') {
    $alertClass = 'alert-success';
} elseif ($displayType === 'warning') {
    $alertClass = 'alert-warning';
}

$loginFeedbackTitle = 'Notice';
$loginFeedbackIcon = 'fa-circle-info';
if ($displayType === 'error') {
    $loginFeedbackTitle = 'Login failed';
    $loginFeedbackIcon = 'fa-triangle-exclamation';
} elseif ($displayType === 'warning') {
    $loginFeedbackTitle = 'Login paused';
    $loginFeedbackIcon = 'fa-shield-halved';
} elseif ($displayType === 'success') {
    $loginFeedbackTitle = 'Success';
    $loginFeedbackIcon = 'fa-circle-check';
}

$workspaceSlides = [
    [
        'image' => APP_URL . '/assets/image/11.png',
        'title' => 'Operations Visibility',
        'copy' => 'Monitor field activity, approvals, and reporting from one professional workspace.',
    ],
    [
        'image' => APP_URL . '/assets/image/12.jpg',
        'title' => 'Financial Control',
        'copy' => 'Follow payroll, receipts, and income workflows with clear business context.',
    ],
    [
        'image' => APP_URL . '/assets/image/4.png',
        'title' => 'Station Coordination',
        'copy' => 'Track station rollout, inventory movement, and operational readiness visually.',
    ],
];

$otpRequired = !empty($otp_required);
$otpContext = is_array($otp_context ?? null) ? $otp_context : [];
$otpRemainingSeconds = (int) ($otpContext['remaining_seconds'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        body.login-shell {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(41, 105, 199, 0.2), transparent 25%),
                radial-gradient(circle at bottom right, rgba(45, 157, 120, 0.16), transparent 22%),
                linear-gradient(135deg, #edf4fb 0%, #f7fbff 48%, #e8f0f8 100%);
            display: flex;
            align-items: center;
        }

        .login-shell-card {
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid #dbe5ef;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            background: #fff;
            min-height: min(860px, calc(100vh - 36px));
        }

        .login-brand-panel {
            height: 100%;
            padding: 2rem;
            background: linear-gradient(145deg, #17365c 0%, #21518b 48%, #2969c7 100%);
            color: #fff;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .login-brand-panel::after {
            content: '';
            position: absolute;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            right: -100px;
            top: -80px;
        }

        .login-brand-panel > * {
            position: relative;
            z-index: 1;
        }

        .login-logo {
            width: 62px;
            height: 62px;
            border-radius: 18px;
            background: #fff;
            padding: 7px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.16);
        }

        .login-form-logo {
            width: 118px;
            height: 118px;
            border-radius: 999px;
            padding: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
            border: 1px solid #dbe5ef;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .login-hero-slider {
            margin-top: 0.5rem;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 18px 34px rgba(3, 10, 20, 0.22);
            flex: 1 1 auto;
        }

        .login-hero-slide {
            position: relative;
            min-height: 100%;
        }

        .login-hero-slide img {
            width: 100%;
            height: min(420px, calc(100vh - 280px));
            object-fit: cover;
            display: block;
        }

        .login-hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(11, 22, 39, 0.06) 0%, rgba(8, 18, 32, 0.72) 100%);
            display: flex;
            align-items: flex-end;
            padding: 1.15rem 1.2rem;
        }

        .login-hero-copy {
            max-width: 88%;
        }

        .login-hero-copy h4 {
            margin-bottom: 0.3rem;
            font-size: 1.04rem;
            font-weight: 800;
            color: #fff;
        }

        .login-hero-copy p {
            margin: 0;
            font-size: 0.88rem;
            color: rgba(235, 244, 255, 0.86);
            line-height: 1.55;
        }

        .login-hero-slider .carousel-indicators {
            margin-bottom: 0.75rem;
        }

        .login-hero-slider .carousel-indicators button {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: none;
            opacity: 0.55;
        }

        .login-hero-slider .carousel-indicators .active {
            opacity: 1;
            transform: scale(1.18);
        }

        .login-hero-slider .carousel-control-prev,
        .login-hero-slider .carousel-control-next {
            width: 13%;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .login-hero-slider:hover .carousel-control-prev,
        .login-hero-slider:hover .carousel-control-next {
            opacity: 1;
        }

        .login-hero-slider .carousel-control-prev-icon,
        .login-hero-slider .carousel-control-next-icon {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 999px;
            background-color: rgba(255, 255, 255, 0.22);
            background-size: 55% 55%;
        }

        .login-form-panel {
            padding: 2rem;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .login-form-header {
            margin-bottom: 1.1rem;
            text-align: center;
        }

        .login-form-title {
            color: #223047;
            font-weight: 800;
            margin-bottom: 0.45rem;
        }

        .login-form-copy {
            color: #71829b;
            margin-bottom: 1.5rem;
        }

        .login-help-strip {
            margin-top: 1.25rem;
            padding: 0.95rem 1rem;
            border-radius: 16px;
            background: #f6faff;
            border: 1px solid #dbe5ef;
            color: #52657d;
        }

        .login-form-footer {
            margin-top: auto;
            padding-top: 1rem;
            font-size: 0.84rem;
            color: #7b8ba1;
            text-align: center;
        }

        .login-feedback-toast {
            min-width: min(380px, calc(100vw - 24px));
            border-radius: 20px;
            box-shadow: 0 22px 50px rgba(15, 23, 42, 0.16);
            background: rgba(255, 255, 255, 0.98);
        }

        .login-feedback-toast .toast-header {
            border-bottom: 1px solid #e8eff7;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }

        .login-feedback-toast .toast-body {
            color: #41546d;
            line-height: 1.55;
        }

        @media (max-width: 991.98px) {
            .login-shell-card {
                min-height: auto;
            }
        }
    </style>
</head>
<body class="login-shell">
    <div class="app-loading-overlay" id="authLoadingOverlay" aria-hidden="true">
        <div class="app-loading-card">
            <div class="app-loading-spinner"></div>
            <h5 data-loading-title>Signing you in</h5>
            <p data-loading-message>Please wait while ERMS verifies your account and prepares your workspace.</p>
        </div>
    </div>

    <?php if (!empty($displayMessage)): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="loginFeedbackToast" class="toast login-feedback-toast border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-white">
                <i class="fas <?= htmlspecialchars($loginFeedbackIcon) ?> me-2 text-primary"></i>
                <strong class="me-auto"><?= htmlspecialchars($loginFeedbackTitle) ?></strong>
                <small class="text-muted">now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"><?= htmlspecialchars($displayMessage) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="container py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-11">
                <div class="login-shell-card">
                    <div class="row g-0 align-items-stretch">
                        <div class="col-lg-6 d-none d-lg-flex">
                            <div class="login-brand-panel">
                                <img src="<?= APP_URL ?>/assets/images/logo.png" alt="ERMS Logo" class="login-logo mb-4">
                                <div class="text-uppercase fw-semibold small mb-2" style="letter-spacing: 0.14em; color: rgba(226, 240, 255, 0.9);">Professional Admin Workspace</div>
                                <h2 class="text-white mb-3">JASSNET ERMS</h2>
                                <div id="loginWorkspaceSlider" class="carousel slide login-hero-slider" data-bs-ride="carousel" data-bs-interval="6000">
                                    <div class="carousel-indicators">
                                        <?php foreach ($workspaceSlides as $slideIndex => $slide): ?>
                                            <button type="button" data-bs-target="#loginWorkspaceSlider" data-bs-slide-to="<?= $slideIndex ?>" class="<?= $slideIndex === 0 ? 'active' : '' ?>" <?= $slideIndex === 0 ? 'aria-current="true"' : '' ?> aria-label="Slide <?= $slideIndex + 1 ?>"></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="carousel-inner">
                                        <?php foreach ($workspaceSlides as $slideIndex => $slide): ?>
                                            <div class="carousel-item <?= $slideIndex === 0 ? 'active' : '' ?>">
                                                <div class="login-hero-slide">
                                                    <img src="<?= htmlspecialchars($slide['image']) ?>" alt="<?= htmlspecialchars($slide['title']) ?>">
                                                    <div class="login-hero-overlay">
                                                        <div class="login-hero-copy">
                                                            <h4><?= htmlspecialchars($slide['title']) ?></h4>
                                                            <p><?= htmlspecialchars($slide['copy']) ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#loginWorkspaceSlider" data-bs-slide="prev" aria-label="Previous slide">
                                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#loginWorkspaceSlider" data-bs-slide="next" aria-label="Next slide">
                                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 d-flex">
                            <div class="login-form-panel">
                                <div class="login-form-header">
                                    <img src="<?= APP_URL ?>/assets/images/logo.png" alt="ERMS Logo" class="login-form-logo mb-3">
                                    <div class="text-uppercase fw-semibold small mb-2" style="letter-spacing: 0.14em; color: #1d4f98;"><?= $otpRequired ? 'OTP Verification' : 'Welcome Back' ?></div>
                                    <h3 class="login-form-title"><?= $otpRequired ? 'Confirm OTP code' : 'Sign in to continue' ?></h3>
                                    <p class="login-form-copy">
                                        <?php if ($otpRequired): ?>
                                            OTP imetumwa kwa <?= htmlspecialchars((string) ($otpContext['masked_phone'] ?? 'namba yako')) ?> kupitia SMS, na WhatsApp pia ikiwa channel hiyo iko tayari kwenye account yako. Weka code ya tarakimu 6 ili kufungua workspace yako.
                                        <?php else: ?>
                                            Use your ERMS username and password to access the professional admin dashboard.
                                        <?php endif; ?>
                                    </p>
                                </div>
                        <?php if (!empty($displayMessage)): ?>
                            <div class="alert <?= $alertClass ?>" role="alert">
                                <?= htmlspecialchars($displayMessage) ?>
                            </div>
                        <?php endif; ?>

                                <?php if ($otpRequired): ?>
                                    <form method="POST" action="<?= APP_URL ?>/index.php" autocomplete="one-time-code">
                                        <input type="hidden" name="auth_action" value="verify_otp">
                                        <div class="mb-3">
                                            <label for="otp_code" class="form-label">OTP Code</label>
                                            <input type="text" id="otp_code" name="otp_code" class="form-control form-control-lg text-center" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required>
                                            <div class="form-text">OTP ita-expire baada ya dakika <?= (int) LOGIN_OTP_EXPIRY_MINUTES ?>.</div>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">Verify OTP</button>
                                    </form>
                                    <form method="POST" action="<?= APP_URL ?>/index.php" class="mt-3">
                                        <input type="hidden" name="auth_action" value="resend_otp">
                                        <button type="submit" class="btn btn-outline-secondary w-100">Resend OTP</button>
                                    </form>
                                    <div class="small text-muted mt-3 text-center">Session: <?= htmlspecialchars((string) ($otpContext['name'] ?? 'User')) ?><?php if ($otpRemainingSeconds > 0): ?> | <?= (int) ceil($otpRemainingSeconds / 60) ?> min left<?php endif; ?></div>
                                    <?php if (!empty($otpContext['otp_code'])): ?>
                                        <div class="small text-center mt-2">
                                            <span class="fw-semibold text-dark">Testing OTP:</span>
                                            <span class="badge bg-warning text-dark"><?= htmlspecialchars((string) $otpContext['otp_code']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($otpContext['delivery_warning'])): ?>
                                        <div class="small text-warning text-center mt-2">
                                            <?= htmlspecialchars((string) $otpContext['delivery_warning']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="POST" action="<?= APP_URL ?>/index.php" autocomplete="on">
                                        <input type="hidden" name="auth_action" value="login">
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
                                <?php endif; ?>

                                <div class="login-help-strip">
                                    <div class="fw-semibold mb-1"><i class="fas fa-shield-halved me-2 text-primary"></i>Secure internal access</div>
                                    <div class="small mb-0"><?php if ($otpRequired): ?>Baada ya password sahihi, ERMS hutuma OTP kwa SMS, na WhatsApp pia ikiwa account ya biashara ina template au chat window inayoruhusu delivery.<?php else: ?>If your session expires or your password was reset by an administrator, sign in again with your latest credentials.<?php endif; ?></div>
                                </div>

                                <div class="login-form-footer">
                                    &copy; <?= date('Y') ?> JASSNET ERMS. All rights reserved.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.querySelector('form[action="<?= APP_URL ?>/index.php"]');
            const authLoadingOverlay = document.getElementById('authLoadingOverlay');
            const loginFeedbackToast = document.getElementById('loginFeedbackToast');
            const showOverlay = function(options) {
                if (!authLoadingOverlay) {
                    return;
                }

                if (window.JassnetLoadingOverlay) {
                    window.JassnetLoadingOverlay.show(authLoadingOverlay, options || {});
                    return;
                }

                authLoadingOverlay.classList.add('show');
            };
            const hideOverlay = function() {
                if (!authLoadingOverlay) {
                    return;
                }

                if (window.JassnetLoadingOverlay) {
                    window.JassnetLoadingOverlay.hide(authLoadingOverlay);
                    window.JassnetLoadingOverlay.reset(authLoadingOverlay);
                    return;
                }

                authLoadingOverlay.classList.remove('show');
            };

            if (loginFeedbackToast && window.bootstrap) {
                window.setTimeout(function() {
                    bootstrap.Toast.getOrCreateInstance(loginFeedbackToast, { delay: 4200 }).show();
                }, 180);
            }

            if (loginForm) {
                loginForm.addEventListener('submit', function() {
                    showOverlay({
                        title: <?= json_encode($otpRequired ? 'Verifying OTP' : 'Signing you in', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                        message: <?= json_encode($otpRequired ? 'Please wait while ERMS verifies your OTP and opens your workspace.' : 'Please wait while ERMS verifies your account and prepares your workspace.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                    });
                });
            }

            const logoutTransition = <?= json_encode($showLogoutTransition ? ['title' => $logoutTransitionTitle, 'message' => $logoutTransitionCopy] : null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            if (logoutTransition && authLoadingOverlay) {
                showOverlay(logoutTransition);
                window.setTimeout(function() {
                    hideOverlay();
                }, 1100);
            }
        });
    </script>
</body>
</html>
