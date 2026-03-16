<?php
/**
 * User Model - Handles user operations
 */

namespace App\Models;

use App\Core\BaseModel;

class User extends BaseModel
{
    private const LOGIN_MAX_FAILURES = 3;
    private const LOGIN_LOCK_MINUTES = 4;
    private const LOGIN_ATTEMPT_WINDOW_MINUTES = 4;

    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $fillable = [
        'username',
        'email',
        'full_name',
        'password',
        'role',
        'is_active',
        'department',
        'phone',
        'address',
        'password_last_changed',
        'must_change_password',
        'close_relative_1_relationship',
        'close_relative_1_name',
        'close_relative_1_phone',
        'close_relative_1_location',
        'close_relative_1_email',
        'close_relative_2_relationship',
        'close_relative_2_name',
        'close_relative_2_phone',
        'close_relative_2_location',
        'close_relative_2_email',
    ];
    protected $timestamps = true;

    /**
     * Find user by username
     *
     * @param string $username
     * @return array|null
     */
    public function findByUsername($username)
    {
        return $this->findBy(['username' => $username]);
    }

    /**
     * Find user by email
     *
     * @param string $email
     * @return array|null
     */
    public function findByEmail($email)
    {
        return $this->findBy(['email' => $email]);
    }

    /**
     * Authenticate user
     *
     * @param string $username
     * @param string $password
     * @return array|null User data or null if failed
     */
    public function authenticate($username, $password)
    {
        $this->clearErrors();
        $user = $this->findByUsername($username);
        
        if (!$user) {
            $this->addError('username', 'Username not found');
            return null;
        }

        $isActive = true;
        if (array_key_exists('status', $user)) {
            $isActive = ($user['status'] === 'active');
        } elseif (array_key_exists('is_active', $user)) {
            $isActive = (bool) $user['is_active'];
        }

        if (!$isActive) {
            $this->addError('username', 'Account is inactive');
            return null;
        }

        if (!password_verify($password, $user['password'])) {
            $this->addError('password', 'Incorrect password');
            return null;
        }

        return $user;
    }

    /**
     * Check whether a username is currently locked from login.
     *
     * @param string $username
     * @return array|null
     */
    public function getLoginDelayState($username)
    {
        $usernameKey = $this->normalizeLoginAttemptKey($username);
        if ($usernameKey === '') {
            return null;
        }

        $this->ensureAuthSecuritySchema();
        $row = $this->findLoginAttemptRow($usernameKey);
        if (!$row) {
            return null;
        }

        $lockedUntilValue = trim((string) ($row['locked_until'] ?? ''));
        if ($lockedUntilValue === '') {
            return null;
        }

        $lockedUntil = new \DateTimeImmutable($lockedUntilValue);
        $now = new \DateTimeImmutable('now');
        if ($lockedUntil <= $now) {
            return null;
        }

        return [
            'locked_until' => $lockedUntil->format(DATETIME_FORMAT),
            'remaining_seconds' => max(1, $lockedUntil->getTimestamp() - $now->getTimestamp()),
            'attempt_count' => (int) ($row['attempt_count'] ?? 0),
        ];
    }

    /**
     * Record a failed login attempt and apply the temporary lock when needed.
     *
     * @param string $username
     * @param array|null $user
     * @param string $reasonCode
     * @return array
     */
    public function recordFailedLoginAttempt($username, $user = null, $reasonCode = 'invalid_credentials')
    {
        $usernameKey = $this->normalizeLoginAttemptKey($username);
        $now = new \DateTimeImmutable('now');
        $windowStart = $now->modify('-' . self::LOGIN_ATTEMPT_WINDOW_MINUTES . ' minutes');

        if ($usernameKey === '') {
            return [
                'attempt_count' => 1,
                'remaining_attempts' => self::LOGIN_MAX_FAILURES - 1,
                'is_locked' => false,
                'locked_until' => null,
            ];
        }

        $this->ensureAuthSecuritySchema();
        $existing = $this->findLoginAttemptRow($usernameKey);
        $attemptCount = 0;

        if ($existing) {
            $firstAttemptValue = trim((string) ($existing['first_attempt_at'] ?? ''));
            $lockedUntilValue = trim((string) ($existing['locked_until'] ?? ''));
            $isFreshWindow = $firstAttemptValue !== '' && new \DateTimeImmutable($firstAttemptValue) >= $windowStart;
            $isStillLocked = $lockedUntilValue !== '' && new \DateTimeImmutable($lockedUntilValue) > $now;

            if ($isFreshWindow || $isStillLocked) {
                $attemptCount = (int) ($existing['attempt_count'] ?? 0);
            }
        }

        $attemptCount++;
        $lockedUntil = null;
        if ($attemptCount > self::LOGIN_MAX_FAILURES) {
            $lockedUntil = $now->modify('+' . self::LOGIN_LOCK_MINUTES . ' minutes');
        }

        $query = 'INSERT INTO login_security_attempts (username, user_id, attempt_count, first_attempt_at, last_attempt_at, locked_until, last_error_code, last_ip, created_at, updated_at) '
            . 'VALUES (:username, :user_id, :attempt_count, :first_attempt_at, :last_attempt_at, :locked_until, :last_error_code, :last_ip, :created_at, :updated_at) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'user_id = VALUES(user_id), '
            . 'attempt_count = VALUES(attempt_count), '
            . 'first_attempt_at = VALUES(first_attempt_at), '
            . 'last_attempt_at = VALUES(last_attempt_at), '
            . 'locked_until = VALUES(locked_until), '
            . 'last_error_code = VALUES(last_error_code), '
            . 'last_ip = VALUES(last_ip), '
            . 'updated_at = VALUES(updated_at)';

        $this->db->prepare($query);
        $this->db->bind(':username', $usernameKey);
        $this->db->bind(':user_id', isset($user['id']) ? (int) $user['id'] : null);
        $this->db->bind(':attempt_count', $attemptCount);
        $this->db->bind(':first_attempt_at', $now->format(DATETIME_FORMAT));
        $this->db->bind(':last_attempt_at', $now->format(DATETIME_FORMAT));
        $this->db->bind(':locked_until', $lockedUntil ? $lockedUntil->format(DATETIME_FORMAT) : null);
        $this->db->bind(':last_error_code', $reasonCode);
        $this->db->bind(':last_ip', $this->getRequestIpAddress());
        $this->db->bind(':created_at', $now->format(DATETIME_FORMAT));
        $this->db->bind(':updated_at', $now->format(DATETIME_FORMAT));
        $this->db->execute();

        return [
            'attempt_count' => $attemptCount,
            'remaining_attempts' => max(0, self::LOGIN_MAX_FAILURES - $attemptCount),
            'is_locked' => $lockedUntil !== null,
            'locked_until' => $lockedUntil ? $lockedUntil->format(DATETIME_FORMAT) : null,
            'lock_minutes' => self::LOGIN_LOCK_MINUTES,
        ];
    }

    /**
     * Clear stored failed-login state after a successful sign-in.
     *
     * @param string $username
     * @return void
     */
    public function clearFailedLoginAttempts($username)
    {
        $usernameKey = $this->normalizeLoginAttemptKey($username);
        if ($usernameKey === '') {
            return;
        }

        $this->ensureAuthSecuritySchema();
        $this->db->prepare('DELETE FROM login_security_attempts WHERE username = :username');
        $this->db->bind(':username', $usernameKey);
        $this->db->execute();
    }

    /**
     * Write a security event into activity_logs so admins can review it.
     *
     * @param string $action
     * @param string $description
     * @param array|null $user
     * @return void
     */
    public function logSecurityEvent($action, $description, $user = null)
    {
        $this->ensureAuthSecuritySchema();

        $columns = ['user_id', 'action', 'description', 'table_name', 'record_id', 'created_at'];
        $values = [
            ':user_id' => isset($user['id']) ? (int) $user['id'] : null,
            ':action' => $action,
            ':description' => $description,
            ':table_name' => 'auth_security',
            ':record_id' => isset($user['id']) ? (int) $user['id'] : null,
            ':created_at' => date(DATETIME_FORMAT),
        ];

        if ($this->tableColumnExists('activity_logs', 'user_role')) {
            $columns[] = 'user_role';
            $values[':user_role'] = trim((string) ($user['role'] ?? ''));
        }

        if ($this->tableColumnExists('activity_logs', 'ip_address')) {
            $columns[] = 'ip_address';
            $values[':ip_address'] = $this->getRequestIpAddress();
        }

        if ($this->tableColumnExists('activity_logs', 'user_agent')) {
            $columns[] = 'user_agent';
            $values[':user_agent'] = substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);
        }

        $placeholders = implode(', ', array_keys($values));
        $query = 'INSERT INTO activity_logs (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
        $this->db->prepare($query);
        foreach ($values as $parameter => $value) {
            $this->db->bind($parameter, $value);
        }
        $this->db->execute();
    }

    /**
     * Send an SMS security notice when a valid account receives failed login attempts.
     *
     * @param array|null $user
     * @param string $eventType
     * @param string|null $lockedUntil
     * @return void
     */
    public function sendSecuritySms($user, $eventType, $lockedUntil = null)
    {
        if (!$user || trim((string) ($user['phone'] ?? '')) === '') {
            return;
        }

        $this->ensureSecuritySendersLoaded();

        $phone = $this->normalizeSecurityPhone((string) $user['phone']);
        if ($phone === '') {
            return;
        }

        $recipientName = trim((string) ($user['full_name'] ?? ($user['username'] ?? 'User')));
        $username = trim((string) ($user['username'] ?? 'account'));
        $message = '';
        $action = '';

        if ($eventType === 'wrong_password') {
            $message = 'Security alert ' . $recipientName . ': kuna login attempt ya JASSNET yenye password sio sahihi kwenye username ' . $username . '. Kama si wewe, wasiliana na admin.';
            $action = 'LOGIN_FAILED_SMS';
        } elseif ($eventType === 'lockout') {
            $untilText = $lockedUntil ? date('H:i', strtotime($lockedUntil)) : 'baada ya dakika 4';
            $message = 'Security alert ' . $recipientName . ': account yako ya JASSNET imezuiwa kwa muda baada ya login attempts nyingi. Jaribu tena saa ' . $untilText . '.';
            $action = 'LOGIN_LOCKED_SMS';
        }

        if ($message === '') {
            return;
        }

        $responses = $this->sendSecurityTextChannels($phone, $message);
        $channelSummary = $this->buildSecurityChannelSummary($responses);
        $wasSuccessful = $this->didAnySecurityChannelSucceed($responses);
        $eventVerb = $wasSuccessful ? 'sent' : 'failed';
        $this->logSecurityEvent($action, 'Security notification ' . $eventVerb . ' to ' . $phone . ' for username ' . $username . '. Channels: ' . $channelSummary, $user);
    }

    /**
     * Send a login OTP to the user phone number.
     *
     * @param array|null $user
     * @param string $otpCode
     * @return bool
     */
    public function sendLoginOtpSms($user, $otpCode)
    {
        if (!$user) {
            return false;
        }

        $this->ensureSecuritySendersLoaded();

        $phone = $this->normalizeSecurityPhone((string) ($user['phone'] ?? ''));
        $email = trim((string) ($user['email'] ?? ''));
        if ($phone === '' && $email === '') {
            return false;
        }

        $recipientName = trim((string) ($user['full_name'] ?? ($user['username'] ?? 'User')));
        $message = 'Dear ' . $recipientName . ', OTP yako ya kuingia ni ' . $otpCode . '. Ita-expire baada ya dakika ' . (int) LOGIN_OTP_EXPIRY_MINUTES . '.';

        $responses = $this->sendOtpChannels($phone, $email, $recipientName, $message, (string) $otpCode);
        $channelSummary = $this->buildSecurityChannelSummary($responses);
        $wasSuccessful = $this->didAnySecurityChannelSucceed($responses);
        $destinationSummary = implode(', ', array_filter([$phone !== '' ? $phone : null, $email !== '' ? $email : null])) ?: 'no destination';
        $this->logSecurityEvent('LOGIN_OTP_SMS', 'Login OTP notification ' . ($wasSuccessful ? 'sent' : 'failed') . ' to ' . $destinationSummary . '. Channels: ' . $channelSummary, $user);

        return $wasSuccessful;
    }

    /**
     * Hash password
     *
     * @param string $password
     * @return string
     */
    public function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Create user
     *
     * @param array $data
     * @return int|false User ID or false
     */
    public function create($data)
    {
        // Hash password
        if (isset($data['password'])) {
            $data['password'] = $this->hashPassword($data['password']);
        }

        // Set password changed date
        $data['password_last_changed'] = date(DATETIME_FORMAT);
        // Use is_active column (the status column does not exist in this schema)
        if (!isset($data['is_active'])) {
            $data['is_active'] = isset($data['status']) ? ($data['status'] === 'active' ? 1 : 0) : 1;
        }
        unset($data['status']);

        return parent::create($data);
    }

    /**
     * Update password
     *
     * @param int $userId
     * @param string $password
     * @return bool
     */
    public function updatePassword($userId, $password)
    {
        $hashedPassword = $this->hashPassword($password);
        
        return $this->update($userId, [
            'password' => $hashedPassword,
            'password_last_changed' => date(DATETIME_FORMAT),
            'must_change_password' => 0,
        ]);
    }

    /**
     * Check if password is expired
     *
     * @param array $user
     * @return string 'ok', 'warning', or 'expired'
     */
    public function checkPasswordExpiration($user)
    {
        $lastChanged = new \DateTime($user['password_last_changed']);
        $now = new \DateTime();
        $daysSinceChange = $now->diff($lastChanged)->days;

        if ($daysSinceChange > PASSWORD_EXPIRATION_DAYS) {
            return 'expired';
        } elseif ($daysSinceChange > (PASSWORD_EXPIRATION_DAYS - PASSWORD_WARNING_DAYS)) {
            return 'warning';
        }
        return 'ok';
    }

    /**
     * Update last login
     *
     * @param int $userId
     * @return bool
     */
    public function updateLastLogin($userId)
    {
        try {
            return $this->update($userId, [
                'last_login' => date(DATETIME_FORMAT),
            ]);
        } catch (\Exception $e) {
            // Column may not exist in this database version — silently skip
            return false;
        }
    }

    /**
     * Get active users
     *
     * @return array
     */
    public function getActiveUsers()
    {
        return $this->getAll(['*'], ['is_active' => 1], 'full_name ASC');
    }

    /**
     * Get users by role
     *
     * @param string $role
     * @return array
     */
    public function getByRole($role)
    {
        return $this->getAll(
            ['*'],
            [],
            'full_name ASC'
        );
    }

    /**
     * Check if email exists
     *
     * @param string $email
     * @param int|null $excludeId User ID to exclude
     * @return bool
     */
    public function emailExists($email, $excludeId = null)
    {
        if ($excludeId) {
            // Check if email exists but not for this user
            $this->db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $this->db->bind(':email', $email);
            $this->db->bind(':id', $excludeId);
            return $this->db->fetch() !== null;
        }

        return $this->exists(['email' => $email]);
    }

    /**
     * Check if username exists
     *
     * @param string $username
     * @param int|null $excludeId User ID to exclude
     * @return bool
     */
    public function usernameExists($username, $excludeId = null)
    {
        if ($excludeId) {
            // Check if username exists but not for this user
            $this->db->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
            $this->db->bind(':username', $username);
            $this->db->bind(':id', $excludeId);
            return $this->db->fetch() !== null;
        }

        return $this->exists(['username' => $username]);
    }

    /**
     * Ensure tables needed for auth-rate limiting and audit logging exist.
     *
     * @return void
     */
    private function ensureAuthSecuritySchema()
    {
        $this->db->prepare(
            'CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                user_role VARCHAR(120) NULL,
                action VARCHAR(60) NOT NULL,
                description TEXT NOT NULL,
                table_name VARCHAR(120) NULL,
                record_id INT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_activity_logs_user (user_id),
                KEY idx_activity_logs_action (action),
                KEY idx_activity_logs_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->db->execute();

        $this->db->prepare(
            'CREATE TABLE IF NOT EXISTS login_security_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(191) NOT NULL,
                user_id INT NULL,
                attempt_count INT NOT NULL DEFAULT 0,
                first_attempt_at DATETIME NOT NULL,
                last_attempt_at DATETIME NOT NULL,
                locked_until DATETIME NULL,
                last_error_code VARCHAR(60) NULL,
                last_ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_login_security_username (username),
                KEY idx_login_security_user (user_id),
                KEY idx_login_security_locked_until (locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->db->execute();
    }

    /**
     * Fetch the stored login attempt state for a username.
     *
     * @param string $usernameKey
     * @return array|null
     */
    private function findLoginAttemptRow($usernameKey)
    {
        $this->db->prepare('SELECT * FROM login_security_attempts WHERE username = :username LIMIT 1');
        $this->db->bind(':username', $usernameKey);
        return $this->db->fetch();
    }

    /**
     * Normalize usernames used in the login attempt table.
     *
     * @param string $username
     * @return string
     */
    private function normalizeLoginAttemptKey($username)
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower(trim((string) $username), 'UTF-8')
            : strtolower(trim((string) $username));
    }

    /**
     * Check whether a given column exists on a table.
     *
     * @param string $tableName
     * @param string $columnName
     * @return bool
     */
    private function tableColumnExists($tableName, $columnName)
    {
        $this->db->prepare('SHOW COLUMNS FROM ' . $tableName . ' LIKE :column_name');
        $this->db->bind(':column_name', $columnName);
        return $this->db->fetch() !== false;
    }

    /**
     * Get requester IP for security logs.
     *
     * @return string
     */
    private function getRequestIpAddress()
    {
        return substr(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45);
    }

    private function ensureSecuritySendersLoaded(): void
    {
        if (!\class_exists('JASSnetSender', false)) {
            require_once APP_ROOT . '/includes/jassnet_sms.php';
        }

        if (!isset($GLOBALS['smsSender']) && isset($smsSender)) {
            $GLOBALS['smsSender'] = $smsSender;
        }

        if (!\class_exists('JASSnetWhatsAppSender', false)) {
            require_once APP_ROOT . '/includes/jassnet_whatsapp.php';
        }

        if (!isset($GLOBALS['whatsAppSender']) && isset($whatsAppSender)) {
            $GLOBALS['whatsAppSender'] = $whatsAppSender;
        }

        if (!\class_exists('JASSnetMailSender', false)) {
            require_once APP_ROOT . '/includes/jassnet_mail.php';
        }

        if (!isset($GLOBALS['mailSender']) && isset($mailSender)) {
            $GLOBALS['mailSender'] = $mailSender;
        }
    }

    private function sendSecurityTextChannels(string $phone, string $message): array
    {
        $responses = [];

        if (isset($GLOBALS['smsSender'])) {
            $responses['sms'] = $GLOBALS['smsSender']->sendSMS($phone, $message, \defined('SENDER_ID') ? SENDER_ID : 'JASSNET');
        }

        if (isset($GLOBALS['whatsAppSender'])) {
            $responses['whatsapp'] = $GLOBALS['whatsAppSender']->sendTextMessage($phone, $message);
        }

        return $responses;
    }

    private function sendOtpChannels(string $phone, string $email, string $recipientName, string $message, string $otpCode): array
    {
        $responses = [];

        if ($phone !== '' && isset($GLOBALS['smsSender'])) {
            $responses['sms'] = $GLOBALS['smsSender']->sendSMS($phone, $message, \defined('SENDER_ID') ? SENDER_ID : 'JASSNET');
        }

        if ($phone !== '' && isset($GLOBALS['whatsAppSender'])) {
            if (\defined('WHATSAPP_OTP_TEMPLATE') && trim((string) WHATSAPP_OTP_TEMPLATE) !== '') {
                $responses['whatsapp'] = $GLOBALS['whatsAppSender']->sendOtpTemplateMessage($phone, $otpCode, (int) LOGIN_OTP_EXPIRY_MINUTES);
            } else {
                $fallback = $GLOBALS['whatsAppSender']->sendTextMessage($phone, $message);
                $fallback['warning'] = 'otp_text_requires_open_whatsapp_window_or_approved_template';
                $responses['whatsapp'] = $fallback;
            }
        }

        if ($email !== '' && isset($GLOBALS['mailSender'])) {
            $responses['email'] = $GLOBALS['mailSender']->sendOtpEmail($email, $recipientName, $otpCode, (int) LOGIN_OTP_EXPIRY_MINUTES);
        }

        return $responses;
    }

    private function didAnySecurityChannelSucceed(array $responses): bool
    {
        foreach ($responses as $response) {
            if (!is_array($response) || !empty($response['success'])) {
                return true;
            }
        }

        return false;
    }

    private function buildSecurityChannelSummary(array $responses): string
    {
        if ($responses === []) {
            return 'no providers available';
        }

        $parts = [];
        foreach ($responses as $channel => $response) {
            if (is_array($response)) {
                $parts[] = $channel . '=' . json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $parts[] = $channel . '=no structured response';
            }
        }

        return implode('; ', $parts);
    }

    /**
     * Normalize local phone values into Tanzanian international format for SMS alerts.
     *
     * @param string $phone
     * @return string
     */
    private function normalizeSecurityPhone($phone)
    {
        $digits = preg_replace('/\D+/', '', trim((string) $phone));
        if ($digits === null || $digits === '') {
            return '';
        }

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255' . substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '255' . $digits;
        }

        return '';
    }
}
