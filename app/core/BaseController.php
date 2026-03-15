<?php
/**
 * BaseController Class - Abstract base class for all controllers
 * 
 * Provides common functionality for handling requests and responses
 */

namespace App\Core;

abstract class BaseController
{
    protected $db;
    protected $data = [];
    protected $errors = [];
    protected $message = '';
    protected $messageType = 'info';
    protected $user;
    protected $view;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }

        $this->db = Database::getInstance();
        $this->requireLogin();
        $this->user = $this->getCurrentUser();
        if ($this->user) {
            $_SESSION['username'] = (string) ($this->user['username'] ?? ($_SESSION['username'] ?? ''));
            $_SESSION['full_name'] = trim((string) ($this->user['full_name'] ?? '')) !== ''
                ? (string) $this->user['full_name']
                : (string) ($this->user['username'] ?? ($_SESSION['full_name'] ?? 'User'));
            $_SESSION['role'] = trim((string) ($this->user['role'] ?? ''));
            $_SESSION['profile_photo'] = (string) ($this->user['profile_photo'] ?? ($_SESSION['profile_photo'] ?? ''));
        }
    }

    /**
     * Require user to be logged in
     */
    protected function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            header("Location: " . APP_URL . "/index.php");
            exit();
        }
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
     * Check user permissions
     *
     * @param array $requiredRoles
     * @return bool
     */
    public function hasPermission($requiredRoles)
    {
        if (!$this->user) {
            return false;
        }

        $userRoles = array_map('trim', explode(',', $this->user['role']));
        
        // Super admin bypasses all checks
        if (in_array('Super Admin', $userRoles)) {
            return true;
        }

        foreach ($requiredRoles as $role) {
            if (in_array($role, $userRoles)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Require specific permission
     *
     * @param array $requiredRoles
     */
    protected function requirePermission($requiredRoles)
    {
        if (!$this->hasPermission($requiredRoles)) {
            $this->error('Unauthorized access');
            header("Location: " . APP_URL . "/dashboard.php");
            exit();
        }
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

        $db = Database::getInstance();
        $db->prepare("SELECT * FROM users WHERE id = :id");
        $db->bind(':id', $_SESSION['user_id']);
        return $db->fetch();
    }

    /**
     * Render view
     *
     * @param string $view View file path
     * @param array $data Data to pass to view
     */
    protected function render($view, $data = [])
    {
        $this->data = array_merge($this->data, $data);
        
        // Make data available to view
        extract($this->data);

        // Expose the shared mysqli connection to hybrid views that still rely on
        // legacy helper functions for notifications and audit hooks.
        $conn = $GLOBALS['conn'] ?? null;

        // make controller available within views for permission checks, helpers
        $controller = $this;

        $viewPath = APP_ROOT . '/app/views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            die('View not found: ' . htmlspecialchars($viewPath));
        }

        // The view itself handles ob_start/ob_get_clean and includes the layout.
        // Just include it directly — no extra buffering layer needed.
        include $viewPath;
    }

    /**
     * Render JSON response
     *
     * @param array $data
     * @param int $statusCode
     */
    protected function json($data, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit();
    }

    /**
     * Redirect to URL
     *
     * @param string $url
     */
    protected function redirect($url)
    {
        header("Location: $url");
        exit();
    }

    /**
     * Set success message
     *
     * @param string $message
     */
    protected function success($message)
    {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = 'success';
    }

    /**
     * Set error message
     *
     * @param string $message
     */
    protected function error($message)
    {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = 'error';
    }

    /**
     * Set warning message
     *
     * @param string $message
     */
    protected function warning($message)
    {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = 'warning';
    }

    /**
     * Get message from session
     *
     * @return array|null
     */
    protected function getMessage()
    {
        if (isset($_SESSION['message'])) {
            $message = [
                'text' => $_SESSION['message'],
                'type' => $_SESSION['message_type'] ?? 'info',
            ];
            
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            
            return $message;
        }
        return null;
    }

    /**
     * Validate required fields
     *
     * @param array $fields Fields to validate
     * @param array $data Data to validate against
     * @return bool
     */
    protected function validateRequired($fields, $data)
    {
        $this->errors = [];
        
        foreach ($fields as $field) {
            if (empty($data[$field] ?? null)) {
                $this->errors[$field] = ucfirst($field) . ' is required';
            }
        }

        return empty($this->errors);
    }

    /**
     * Validate email format
     *
     * @param string $email
     * @return bool
     */
    protected function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate password strength
     *
     * @param string $password
     * @return bool
     */
    protected function validatePassword($password)
    {
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $this->errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
            return false;
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $this->errors['password'] = 'Password must contain uppercase letter';
            return false;
        }
        if (!preg_match('/[a-z]/', $password)) {
            $this->errors['password'] = 'Password must contain lowercase letter';
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            $this->errors['password'] = 'Password must contain number';
            return false;
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $this->errors['password'] = 'Password must contain special character';
            return false;
        }
        return true;
    }

    /**
     * Get POST data
     *
     * @param string|null $key Specific key to retrieve
     * @return mixed
     */
    protected function post($key = null)
    {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? null;
    }

    /**
     * Get GET data
     *
     * @param string|null $key Specific key to retrieve
     * @return mixed
     */
    protected function query($key = null)
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? null;
    }

    /**
     * Get REQUEST data
     *
     * @param string|null $key Specific key to retrieve
     * @return mixed
     */
    protected function request($key = null)
    {
        if ($key === null) {
            return $_REQUEST;
        }
        return $_REQUEST[$key] ?? null;
    }

    /**
     * Sanitize input
     *
     * @param string $data
     * @return string
     */
    protected function sanitize($data)
    {
        return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Log activity
     *
     * @param string $action
     * @param string $description
     * @param string $table
     * @param int|null $recordId
     */
    protected function logActivity($action, $description, $table, $recordId = null)
    {
        if (!$this->user) {
            return;
        }

        try {
            $db = Database::getInstance();
            $query = "INSERT INTO activity_logs (user_id, action, description, table_name, record_id, created_at) 
                      VALUES (:user_id, :action, :description, :table, :record_id, :created_at)";
            
            $db->prepare($query);
            $db->bind(':user_id', $this->user['id']);
            $db->bind(':action', $action);
            $db->bind(':description', $description);
            $db->bind(':table', $table);
            $db->bind(':record_id', $recordId);
            $db->bind(':created_at', date(DATETIME_FORMAT));
            
            $db->execute();
        } catch (\Exception $e) {
            // activity_logs table may not exist — silently skip
        }
    }

    /**
     * Check method is POST
     *
     * @return bool
     */
    protected function isPost()
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Check method is GET
     *
     * @return bool
     */
    protected function isGet()
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    /**
     * Get CSRF token
     *
     * @return string
     */
    protected function getCsrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     *
     * @param string $token
     * @return bool
     */
    protected function verifyCsrfToken($token)
    {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}
