<?php
require_once __DIR__ . '/RateLimiter.php';

class MemberAuth
{
    private $db;
    private $sessionName = '1w_member_session';
    private $rateLimiter;

    public function __construct()
    {
        $this->db = Database::getInstance();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->rateLimiter = new RateLimiter();
    }

    public function login($identifier, $password)
    {
        $identifier = filter_var($identifier, FILTER_SANITIZE_EMAIL) ?: $identifier;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($this->rateLimiter->isLockedOut($identifier, $ip)) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please try again in 15 minutes.'];
        }

        // Try email first, then username
        $sql = "SELECT * FROM users WHERE email = :id OR username = :id2 LIMIT 1";
        $user = $this->db->fetch($sql, [':id' => $identifier, ':id2' => $identifier]);

        if (!$user) {
            $this->rateLimiter->recordFailure($identifier, $ip);
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        if (!isset($user['password_hash']) || empty($user['password_hash'])) {
            return ['success' => false, 'message' => 'Account not activated. Please reset password.'];
        }

        if (isset($user['status']) && $user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is suspended or inactive. Please contact support.'];
        }

        if (password_verify($password, $user['password_hash'])) {
            $this->rateLimiter->clearAttempts($identifier);

            $displayName = !empty($user['first_name']) ? $user['first_name'] : ($user['name'] ?? $user['username'] ?? $user['email']);

            $_SESSION[$this->sessionName] = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'first_name' => $displayName,
                'username' => $user['username'] ?? '',
                'type' => $user['type'] ?? 'user'
            ];

            // Update last login
            $this->db->update('users', [
                'last_login' => date('Y-m-d H:i:s'),
                'login_count' => ($user['login_count'] ?? 0) + 1
            ], "id = :id", [':id' => $user['id']]);

            return ['success' => true, 'redirect' => 'index.php'];
        }

        $this->rateLimiter->recordFailure($identifier, $ip);
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    public function logout()
    {
        unset($_SESSION[$this->sessionName]);
        // session_destroy(); // careful not to kill admin session if shared?
        // Better to just unset specific key
        return true;
    }

    public function isLoggedIn()
    {
        return isset($_SESSION[$this->sessionName]) && !empty($_SESSION[$this->sessionName]['user_id']);
    }

    public function getCurrentUser()
    {
        return $_SESSION[$this->sessionName] ?? null;
    }

    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    // Helper to set password for a user (used in registration/reset)
    public function setPassword($userId, $password)
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // We need to ensure users table has password_hash column. 
        // I'll add a check/column creation in db update if possible, 
        // but for now relying on this SQL working.
        return $this->db->update('users', ['password_hash' => $hash], "id = :id", [':id' => $userId]);
    }
}
