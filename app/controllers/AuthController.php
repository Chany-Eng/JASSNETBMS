<?php
/**
 * AuthController - Handle authentication operations
 */

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\User;

class AuthController extends BaseController
{
    private $userModel;

    public function __construct()
    {
        // Initialize model before parent so getCurrentUser() can use it
        $this->userModel = new User();
        parent::__construct();
    }

    /**
     * Override requireLogin for this controller
     */
    protected function requireLogin()
    {
        // Auth controller doesn't require login
    }

    /**
     * Display login page
     */
    public function login()
    {
        if ($this->isLoggedIn()) {
            $this->redirect(APP_URL . '/dashboard.php');
        }

        $message = null;
        $messageType = '';

        if (isset($_GET['session']) && $_GET['session'] === 'expired') {
            $message = 'Session expired. Please login again.';
            $messageType = 'warning';
        }

        if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
            $message = 'You do not have permission to access this page.';
            $messageType = 'error';
        }

        if (isset($_GET['login']) && $_GET['login'] === 'failed') {
            $message = 'Username or password is incorrect. Please try again.';
            $messageType = 'error';
        }

        if (isset($_GET['login']) && $_GET['login'] === 'locked') {
            $message = 'Too many failed attempts. Wait 4 minutes before trying again.';
            $messageType = 'warning';
        }

        $workspaceSlides = [];
        $loginTheme = [];
        $legacyConn = $GLOBALS['conn'] ?? null;
        if ($legacyConn instanceof \mysqli && function_exists('ensureSiteContentSchema')) {
            ensureSiteContentSchema($legacyConn);
            $workspaceSlides = appGetLoginSlides($legacyConn);
            $loginTheme = appGetLoginThemeSettings($legacyConn);
        }

        $this->data = [
            'message' => $message,
            'messageType' => $messageType,
            'otp_required' => $this->hasPendingOtp(),
            'otp_context' => $this->getOtpViewData(),
            'workspaceSlides' => $workspaceSlides,
            'loginTheme' => $loginTheme,
        ];

        $this->render('auth/login', $this->data);
    }

    /**
     * Handle login form submission
     */
    public function loginSubmit()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/index.php');
        }

        $authAction = trim((string) ($this->post('auth_action') ?? 'login'));
        if ($authAction === 'verify_otp') {
            $this->verifyOtpSubmit();
            return;
        }

        if ($authAction === 'resend_otp') {
            $this->resendOtpSubmit();
            return;
        }

        if ($authAction === 'cancel_otp') {
            $this->cancelOtpSubmit();
            return;
        }

        // Validate inputs
        $username = $this->sanitize($this->post('username'));
        $password = $this->post('password');
        $remember = $this->post('remember') === 'on';

        if (empty($username) || empty($password)) {
            $this->error('Username and password are required');
            $this->redirect(APP_URL . '/index.php?login=failed');
        }

        $lockState = $this->userModel->getLoginDelayState($username);
        if ($lockState) {
            $this->warning('Too many failed attempts. Try again in ' . $this->formatRemainingLockTime((int) $lockState['remaining_seconds']) . '.');
            $this->userModel->logSecurityEvent(
                'LOGIN_BLOCKED',
                'Blocked login attempt for username ' . $username . ' because a temporary 4-minute lock is active.',
                $this->userModel->findByUsername($username)
            );
            $this->redirect(APP_URL . '/index.php?login=locked');
        }

        // Authenticate user
        $user = $this->userModel->authenticate($username, $password);

        if (!$user) {
            $attemptedUser = $this->userModel->findByUsername($username);
            $errors = $this->userModel->getErrors();
            $errorMsg = !empty($errors) ? reset($errors) : 'Invalid credentials';

            $reasonCode = 'username_not_found';
            if (isset($errors['password'])) {
                $reasonCode = 'password_incorrect';
            } elseif (isset($errors['username']) && stripos((string) $errors['username'], 'inactive') !== false) {
                $reasonCode = 'inactive_account';
            }

            $attemptState = $this->userModel->recordFailedLoginAttempt($username, $attemptedUser, $reasonCode);
            $this->userModel->logSecurityEvent(
                $attemptState['is_locked'] ? 'LOGIN_LOCKED' : 'LOGIN_FAILED',
                $this->buildFailedLoginDescription($username, $errorMsg, $attemptState),
                $attemptedUser
            );

            if ($reasonCode === 'password_incorrect') {
                $this->userModel->sendSecuritySms($attemptedUser, 'wrong_password');
            }

            if ($attemptState['is_locked']) {
                $this->userModel->sendSecuritySms($attemptedUser, 'lockout', $attemptState['locked_until']);
                $errorMsg = 'Too many failed attempts. Wait 4 minutes before trying again.';
            } elseif ($reasonCode === 'password_incorrect' && $attemptState['remaining_attempts'] > 0) {
                $errorMsg .= '. Remaining attempts: ' . $attemptState['remaining_attempts'];
            }

            $this->error($errorMsg);
            $this->redirect(APP_URL . '/index.php?login=' . ($attemptState['is_locked'] ? 'locked' : 'failed'));
        }

        $this->userModel->clearFailedLoginAttempts($username);

        if (!$this->startOtpChallenge($user, $remember)) {
            $this->error('OTP haikuweza kutumwa kwenye SMS, WhatsApp, au Email. Wasiliana na administrator.');
            $this->redirect(APP_URL . '/index.php');
        }

        $this->success('Tumekutumia OTP kwenye channels zako zilizohifadhiwa kama SMS, WhatsApp, au Email. Ingiza code kuendelea.');
        $this->redirect(APP_URL . '/index.php');
    }

    /**
     * Verify submitted OTP and complete login.
     *
     * @return void
     */
    private function verifyOtpSubmit()
    {
        $pendingAuth = $this->getPendingOtpSession();
        if (!$pendingAuth) {
            $this->warning('OTP session imeisha. Ingia tena kwa username na password.');
            $this->redirect(APP_URL . '/index.php');
        }

        $otpCode = preg_replace('/\D+/', '', (string) ($this->post('otp_code') ?? ''));
        if ($otpCode === '' || strlen($otpCode) !== 6) {
            $this->error('Weka OTP ya tarakimu 6.');
            $this->redirect(APP_URL . '/index.php');
        }

        if (($pendingAuth['expires_at'] ?? 0) < time()) {
            $this->clearPendingOtpSession();
            $this->warning('OTP ime-expire. Ingia tena kuomba code mpya.');
            $this->redirect(APP_URL . '/index.php');
        }

        $attempts = (int) ($pendingAuth['attempts'] ?? 0) + 1;
        $_SESSION['pending_auth']['attempts'] = $attempts;
        if (!password_verify($otpCode, (string) ($pendingAuth['otp_hash'] ?? ''))) {
            if ($attempts >= LOGIN_OTP_MAX_ATTEMPTS) {
                $this->clearPendingOtpSession();
                $this->error('OTP imekosewa mara nyingi. Ingia tena kuanza upya.');
                $this->redirect(APP_URL . '/index.php');
            }

            $this->error('OTP si sahihi. Jaribu tena.');
            $this->redirect(APP_URL . '/index.php');
        }

        $user = $this->userModel->find((int) $pendingAuth['user_id']);
        if (!$user) {
            $this->clearPendingOtpSession();
            $this->error('Akaunti haikupatikana tena.');
            $this->redirect(APP_URL . '/index.php');
        }

        $this->clearPendingOtpSession();
        if (!empty($user['must_change_password'])) {
            $_SESSION['temp_user_id'] = (int) $user['id'];
            $_SESSION['temp_force_password_change'] = true;
            $this->logActivity('LOGIN_PASSWORD_CHANGE_REQUIRED', 'User authenticated with temporary password and must change it before accessing the system', 'users', $user['id']);
            $this->warning('Badili temporary password yako kwanza ndipo uendelee kutumia mfumo.');
            $this->redirect(APP_URL . '/change_password.php?expired=1');
        }

        $this->completeLogin($user, !empty($pendingAuth['remember']));
        $this->redirect(APP_URL . '/dashboard.php');
    }

    /**
     * Resend OTP for a pending login challenge.
     *
     * @return void
     */
    private function resendOtpSubmit()
    {
        $pendingAuth = $this->getPendingOtpSession();
        if (!$pendingAuth) {
            $this->warning('OTP session imeisha. Ingia tena kwa username na password.');
            $this->redirect(APP_URL . '/index.php');
        }

        $user = $this->userModel->find((int) $pendingAuth['user_id']);
        if (!$user || !$this->refreshOtpChallenge($user)) {
            $this->error('OTP haikuweza kutumwa tena kwenye SMS, WhatsApp, au Email.');
            $this->redirect(APP_URL . '/index.php');
        }

        $this->success('OTP mpya imetumwa kwenye channels zako zilizohifadhiwa kama SMS, WhatsApp, au Email.');
        $this->redirect(APP_URL . '/index.php');
    }

    /**
     * Cancel the current OTP challenge and return to the login form.
     *
     * @return void
     */
    private function cancelOtpSubmit()
    {
        $this->clearPendingOtpSession();
        $this->warning('OTP verification imefutwa. Ingia tena kwa username na password.');
        $this->redirect(APP_URL . '/index.php');
    }

    /**
     * Build a readable admin-history message for failed authentication.
     *
     * @param string $username
     * @param string $errorMsg
     * @param array $attemptState
     * @return string
     */
    private function buildFailedLoginDescription($username, $errorMsg, $attemptState)
    {
        $description = 'Failed login for username ' . $username . ': ' . $errorMsg . '.';

        if (!empty($attemptState['is_locked'])) {
            return $description . ' Account locked for 4 minutes after attempt ' . (int) ($attemptState['attempt_count'] ?? 0) . '.';
        }

        if (isset($attemptState['remaining_attempts'])) {
            return $description . ' Remaining attempts before temporary lock: ' . (int) $attemptState['remaining_attempts'] . '.';
        }

        return $description;
    }

    /**
     * Convert remaining seconds into a short lockout label.
     *
     * @param int $remainingSeconds
     * @return string
     */
    private function formatRemainingLockTime($remainingSeconds)
    {
        $minutes = (int) floor($remainingSeconds / 60);
        $seconds = $remainingSeconds % 60;

        if ($minutes <= 0) {
            return $seconds . ' seconds';
        }

        if ($seconds === 0) {
            return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
        }

        return $minutes . 'm ' . str_pad((string) $seconds, 2, '0', STR_PAD_LEFT) . 's';
    }

    /**
     * Start a pending OTP challenge in session.
     *
     * @param array $user
     * @param bool $remember
     * @return bool
     */
    private function startOtpChallenge($user, $remember)
    {
        $otpCode = (string) random_int(100000, 999999);
        $_SESSION['pending_auth'] = [
            'user_id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'full_name' => trim((string) ($user['full_name'] ?? $user['username'])),
            'phone' => (string) ($user['phone'] ?? ''),
            'remember' => $remember,
            'otp_code' => $otpCode,
            'otp_hash' => password_hash($otpCode, PASSWORD_DEFAULT),
            'expires_at' => time() + (LOGIN_OTP_EXPIRY_MINUTES * 60),
            'sent_at' => time(),
            'attempts' => 0,
            'delivery_warning' => '',
        ];

        $sent = $this->userModel->sendLoginOtpSms($user, $otpCode);
        if (!$sent) {
            if ($this->allowOtpTestingFallback()) {
                $_SESSION['pending_auth']['delivery_warning'] = 'OTP delivery skipped for testing because this account has no reachable SMS/WhatsApp destination.';
                return true;
            }

            $this->clearPendingOtpSession();
        }

        return $sent;
    }

    /**
     * Replace a pending OTP with a fresh code.
     *
     * @param array $user
     * @return bool
     */
    private function refreshOtpChallenge($user)
    {
        $otpCode = (string) random_int(100000, 999999);
        $_SESSION['pending_auth']['otp_code'] = $otpCode;
        $_SESSION['pending_auth']['otp_hash'] = password_hash($otpCode, PASSWORD_DEFAULT);
        $_SESSION['pending_auth']['expires_at'] = time() + (LOGIN_OTP_EXPIRY_MINUTES * 60);
        $_SESSION['pending_auth']['sent_at'] = time();
        $_SESSION['pending_auth']['attempts'] = 0;
        $_SESSION['pending_auth']['delivery_warning'] = '';

        $sent = $this->userModel->sendLoginOtpSms($user, $otpCode);
        if (!$sent && $this->allowOtpTestingFallback()) {
            $_SESSION['pending_auth']['delivery_warning'] = 'OTP delivery skipped for testing because this account has no reachable SMS/WhatsApp destination.';
            return true;
        }

        return $sent;
    }

    /**
     * Complete the authenticated session after OTP verification.
     *
     * @param array $user
     * @param bool $remember
     * @return void
     */
    private function completeLogin($user, $remember)
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = trim((string) ($user['full_name'] ?? '')) !== '' ? $user['full_name'] : $user['username'];
        $_SESSION['role'] = $user['role'];

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60);

            setcookie('remember_token', $token, $expiry, '/', '', false, true);
            setcookie('user_id', $user['id'], $expiry, '/', '', false, true);

            try {
                $this->userModel->update($user['id'], ['remember_token' => $token]);
            } catch (\Exception $e) {
            }
        }

        $this->userModel->updateLastLogin($user['id']);
        $this->logActivity('LOGIN', 'User logged in after OTP verification', 'users', $user['id']);

        $_SESSION['login_success'] = true;
        $_SESSION['login_success_name'] = $_SESSION['full_name'];
        $_SESSION['auth_transition'] = [
            'type' => 'login',
            'name' => $_SESSION['full_name'],
        ];
    }

    /**
     * Check whether there is an active OTP session.
     *
     * @return bool
     */
    private function hasPendingOtp()
    {
        $pendingAuth = $this->getPendingOtpSession();
        return $pendingAuth !== null && (int) ($pendingAuth['expires_at'] ?? 0) >= time();
    }

    /**
     * Return pending OTP session data.
     *
     * @return array|null
     */
    private function getPendingOtpSession()
    {
        $pendingAuth = $_SESSION['pending_auth'] ?? null;
        return is_array($pendingAuth) ? $pendingAuth : null;
    }

    /**
     * Clear pending OTP data.
     *
     * @return void
     */
    private function clearPendingOtpSession()
    {
        unset($_SESSION['pending_auth']);
    }

    /**
     * Build OTP view metadata.
     *
     * @return array|null
     */
    private function getOtpViewData()
    {
        $pendingAuth = $this->getPendingOtpSession();
        if (!$pendingAuth) {
            return null;
        }

        $maskedPhone = $this->maskPhoneNumber((string) ($pendingAuth['phone'] ?? ''));
        $remainingSeconds = max(0, (int) ($pendingAuth['expires_at'] ?? 0) - time());

        return [
            'name' => (string) ($pendingAuth['full_name'] ?? $pendingAuth['username'] ?? 'User'),
            'masked_phone' => $maskedPhone,
            'remaining_seconds' => $remainingSeconds,
            'otp_code' => (string) ($pendingAuth['otp_code'] ?? ''),
            'delivery_warning' => (string) ($pendingAuth['delivery_warning'] ?? ''),
        ];
    }

    private function allowOtpTestingFallback()
    {
        // Allow OTP testing fallback in development mode OR if notification senders aren't configured
        if (defined('APP_DEBUG') && APP_DEBUG) {
            return true;
        }
        
        // Also allow if we're not in production (development/staging environments)
        $appEnv = defined('APP_ENV') ? (string) APP_ENV : 'development';
        return $appEnv !== 'production';
    }

    /**
     * Mask a phone number for the OTP screen.
     *
     * @param string $phone
     * @return string
     */
    private function maskPhoneNumber($phone)
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || strlen($digits) < 4) {
            return 'namba iliyohifadhiwa';
        }

        return str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
    }

    /**
     * Handle logout
     */
    public function logout()
    {
        $logoutName = trim((string) ($_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'User')));

        if ($this->isLoggedIn()) {
            $this->logActivity('LOGOUT', 'User logged out', 'users', $_SESSION['user_id']);
        }

        // Clear session
        session_unset();
        session_destroy();

        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }

        $_SESSION['auth_transition'] = [
            'type' => 'logout',
            'name' => $logoutName,
        ];

        // Clear remember me cookie
        setcookie('remember_token', '', time() - 3600, '/');
        setcookie('user_id', '', time() - 3600, '/');

        $this->redirect(APP_URL . '/index.php');
    }

    /**
     * Display change password page
     */
    public function changePassword()
    {
        if (!$this->isLoggedIn()) {
            $this->redirect(APP_URL . '/index.php');
        }

        $user = $this->getCurrentUser();
        $message = $this->getMessage();
        $passwordStatus = $this->userModel->checkPasswordExpiration($user);

        $this->data = [
            'user' => $user,
            'message' => $message,
            'passwordStatus' => $passwordStatus,
        ];

        $this->render('auth/change_password', $this->data);
    }

    /**
     * Handle change password form submission
     */
    public function changePasswordSubmit()
    {
        if (!$this->isLoggedIn()) {
            $this->redirect(APP_URL . '/index.php');
        }

        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/change_password.php');
        }

        $userId = $_SESSION['user_id'];
        $user = $this->getCurrentUser();
        $currentPassword = $this->post('current_password');
        $newPassword = $this->post('new_password');
        $confirmPassword = $this->post('confirm_password');

        // Validate current password
        if (!password_verify($currentPassword, $user['password'])) {
            $this->error('Current password is incorrect');
            $this->redirect(APP_URL . '/change_password.php');
        }

        // Validate new password
        if ($newPassword !== $confirmPassword) {
            $this->error('New passwords do not match');
            $this->redirect(APP_URL . '/change_password.php');
        }

        $passwordValidation = $this->validatePassword($newPassword);
        if ($passwordValidation !== true) {
            // Multiple errors
            foreach ($passwordValidation as $error) {
                // Store errors in session
            }
            $this->error('Password does not meet requirements');
            $this->redirect(APP_URL . '/change_password.php');
        }

        // Update password
        if ($this->userModel->updatePassword($userId, $newPassword)) {
            $this->logActivity('PASSWORD_CHANGE', 'User changed password', 'users', $userId);
            $this->success('Password changed successfully');
            $this->redirect(APP_URL . '/dashboard.php');
        }

        $this->error('Failed to change password');
        $this->redirect(APP_URL . '/change_password.php');
    }

    /**
     * Validate password strength
     *
     * @param string $password
     * @return bool|array True if valid, array of errors if not
     */
    protected function validatePassword($password)
    {
        $errors = [];

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain number';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain special character';
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Check if user is logged in
     *
     * @return bool
     */
    protected function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current logged-in user
     *
     * @return array|null
     */
    protected function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return $this->userModel->find($_SESSION['user_id']);
    }
}
