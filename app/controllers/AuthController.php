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

        $this->data = [
            'message' => $message,
            'messageType' => $messageType,
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

        // Validate inputs
        $username = $this->sanitize($this->post('username'));
        $password = $this->post('password');
        $remember = $this->post('remember') === 'on';

        if (empty($username) || empty($password)) {
            $this->error('Username and password are required');
            $this->redirect(APP_URL . '/index.php');
        }

        // Authenticate user
        $user = $this->userModel->authenticate($username, $password);

        if (!$user) {
            $errors = $this->userModel->getErrors();
            $errorMsg = !empty($errors) ? reset($errors) : 'Invalid credentials';
            $this->error($errorMsg);
            $this->redirect(APP_URL . '/index.php');
        }

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Remember me
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60); // 30 days

            setcookie('remember_token', $token, $expiry, '/', '', false, true);
            setcookie('user_id', $user['id'], $expiry, '/', '', false, true);

            // Save token to database (optional — column may not exist)
            try {
                $this->userModel->update($user['id'], ['remember_token' => $token]);
            } catch (\Exception $e) {
                // Silently skip if remember_token column doesn't exist
            }
        }

        // Update last login
        $this->userModel->updateLastLogin($user['id']);

        // Log activity
        $this->logActivity('LOGIN', 'User logged in', 'users', $user['id']);

        // Flash message
        $_SESSION['login_success'] = true;

        $this->redirect(APP_URL . '/dashboard.php');
    }

    /**
     * Handle logout
     */
    public function logout()
    {
        if ($this->isLoggedIn()) {
            $this->logActivity('LOGOUT', 'User logged out', 'users', $_SESSION['user_id']);
        }

        // Clear session
        session_unset();
        session_destroy();

        // Clear remember me cookie
        setcookie('remember_token', '', time() - 3600, '/');
        setcookie('user_id', '', time() - 3600, '/');

        $this->redirect(APP_URL . '/index.php?message=logged_out');
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
