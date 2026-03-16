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
$showLogoutTransition = is_array($authTransition) && in_array((string) ($authTransition['type'] ?? ''), ['logout', 'inactive-timeout'], true);
$logoutTransitionName = trim((string) ($authTransition['name'] ?? 'User'));
$logoutTransitionType = (string) ($authTransition['type'] ?? 'logout');
$logoutTransitionTitle = $logoutTransitionType === 'inactive-timeout' ? 'Session expired' : 'Logout successful';
$logoutTransitionCopy = $logoutTransitionType === 'inactive-timeout'
    ? 'No activity was detected for 4 minutes. Please sign in again to continue.'
    : (($logoutTransitionName !== '' ? $logoutTransitionName : 'User') . ', session yako imefungwa salama.');

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
$hideAuthToast = !empty($displayMessage)
    && $displayType === 'success'
    && stripos((string) $displayMessage, 'OTP') !== false;
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
            padding: 20px 0;
        }

        .login-shell-card {
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid #dbe5ef;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            background: #fff;
            min-height: min(680px, calc(100vh - 40px));
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
        }

        .login-shell-stack {
            min-height: inherit;
        }

        .login-split-column {
            display: flex;
        }

        .login-brand-panel {
            height: 100%;
            padding: 2.2rem;
            background: linear-gradient(145deg, #17365c 0%, #21518b 48%, #2969c7 100%);
            color: #fff;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 100%;
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

        .login-brand-title {
            font-size: clamp(2rem, 3vw, 2.85rem);
            line-height: 1.05;
            font-weight: 800;
            margin-bottom: 1rem;
            color: #fff;
        }

        .login-brand-copy {
            max-width: 35rem;
            color: rgba(228, 238, 251, 0.92);
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 1.1rem;
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
            margin-top: 0.9rem;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 18px 34px rgba(3, 10, 20, 0.22);
            flex: 1 1 auto;
            width: 100%;
            align-self: stretch;
            min-height: 330px;
            height: 330px;
        }

        .login-hero-slider .carousel-inner,
        .login-hero-slider .carousel-item {
            height: 100%;
        }

        .login-hero-slide {
            position: relative;
            height: 100%;
        }

        .login-hero-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
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
            padding: 2.2rem;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 100%;
        }

        .login-form-body {
            width: min(100%, 470px);
            margin: 0 auto;
        }

        .login-form-body.login-form-body--otp {
            width: min(100%, 420px);
        }

        .login-form-header {
            margin-bottom: 1.1rem;
            text-align: center;
        }

        .login-form-brandline {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.5rem 0.95rem;
            border-radius: 999px;
            background: linear-gradient(180deg, #eef5ff 0%, #f8fbff 100%);
            border: 1px solid #d9e7f6;
            color: #17365c;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 0.9rem;
        }

        .login-form-brandline i {
            color: #2969c7;
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

            .login-split-column {
                display: block;
            }

            .login-brand-panel,
            .login-form-panel {
                padding: 1.5rem;
            }

            .login-brand-panel {
                min-height: 340px;
            }

            .login-hero-slider {
                min-height: 240px;
                height: 240px;
            }

            .login-hero-slide img {
                height: 100%;
            }
        }

        @media (max-width: 575.98px) {
            body.login-shell {
                padding: 12px 0;
            }

            .login-shell-card {
                border-radius: 20px;
            }

            .login-brand-panel,
            .login-form-panel {
                padding: 1.2rem;
            }

            .login-form-logo {
                width: 96px;
                height: 96px;
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

    <?php if (!empty($displayMessage) && !$hideAuthToast): ?>
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
            <div class="col-xl-11 col-lg-11">
                <div class="login-shell-card">
                    <div class="row g-0 login-shell-stack align-items-stretch">
                        <div class="col-12 col-lg-7 login-split-column">
                            <div class="login-brand-panel">
                                <img src="<?= APP_URL ?>/assets/images/logo.png" alt="ERMS Logo" class="login-logo mb-4">
                                <h1 class="login-brand-title">JASSNET ERMS</h1>
                                <p class="login-brand-copy">JASSNET ERMS is a modern enterprise resource management system that enables efficient tracking of income, expenses, inventory, and network station setup requests within the company.</p>
                                <div id="loginWorkspaceSlider" class="carousel slide carousel-fade login-hero-slider" data-bs-ride="carousel" data-bs-interval="6000">
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
                        <div class="col-12 col-lg-5 login-split-column">
                            <div class="login-form-panel">
                                <div class="login-form-body<?= $otpRequired ? ' login-form-body--otp' : '' ?>">
                                    <div class="login-form-header">
                                        <img src="<?= APP_URL ?>/assets/images/logo.png" alt="ERMS Logo" class="login-form-logo mb-3">
                                        <div class="login-form-brandline"><i class="fas fa-building-shield"></i><span>JASSNET ERMS</span></div>
                                        <div class="text-uppercase fw-semibold small mb-2" style="letter-spacing: 0.14em; color: #1d4f98;"><?= $otpRequired ? 'OTP Verification' : 'Welcome Back' ?></div>
                                        <h3 class="login-form-title"><?= $otpRequired ? 'Confirm OTP code' : 'Sign in to continue' ?></h3>
                                        <p class="login-form-copy">
                                            <?php if ($otpRequired): ?>
                                                OTP imetumwa kwa contact zako zilizohifadhiwa kama SMS, WhatsApp, au Email. Weka code ya tarakimu 6 ili kufungua workspace yako.
                                            <?php else: ?>
                                                Use your ERMS username and password to access the professional admin dashboard.
                                            <?php endif; ?>
                                        </p>
                                    </div>
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
                                        <div class="d-grid gap-2 mt-3">
                                            <form method="POST" action="<?= APP_URL ?>/index.php">
                                                <input type="hidden" name="auth_action" value="resend_otp">
                                                <button type="submit" class="btn btn-outline-secondary w-100">Resend OTP</button>
                                            </form>
                                            <form method="POST" action="<?= APP_URL ?>/index.php">
                                                <input type="hidden" name="auth_action" value="cancel_otp">
                                                <button type="submit" class="btn btn-outline-dark w-100">Back</button>
                                            </form>
                                        </div>
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
                                        <div class="small mb-0"><?php if ($otpRequired): ?>Baada ya password sahihi, ERMS hutuma OTP kwa SMS, WhatsApp, na Email kulingana na contact zilizohifadhiwa na rules za delivery za kila channel.<?php else: ?>If your session expires or your password was reset by an administrator, sign in again with your latest credentials. For assistance, contact admin at 0774011615 or hassannabdaallah@gmail.com.<?php endif; ?></div>
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
