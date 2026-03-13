<?php
/**
 * User Model - Handles user operations
 */

namespace App\Models;

use App\Core\BaseModel;

class User extends BaseModel
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $fillable = [
        'username',
        'email',
        'full_name',
        'password',
        'role',
        'status',
        'department',
        'phone',
        'address',
        'password_last_changed',
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
        $data['status'] = $data['status'] ?? 'active';

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
        return $this->getAll(['*'], ['status' => 'active'], 'full_name ASC');
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
}
