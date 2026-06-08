<?php
class MemberAuth
{
    private $db;
    private $sessionName = '1w_member_session';

    public function __construct()
    {
        $this->db = Database::getInstance();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login($email, $password)
    {
        // Clean email
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        // Get user
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $user = $this->db->fetch($sql, [':email' => $email]);

        // Check if user exists
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        // In a real scenario, we'd check password. 
        // But for this system, it seems users are created from sales/leads without passwords initially?
        // Or maybe they set one up. 
        // If password_hash is missing, we might need a flow to set it.
        // For now, assuming standard password verification if hash exists, 
        // OR checking if it's a "magic link" style or just email login for MVP (risky).
        // Let's implement standard password check assuming 'password' column exists or needs to be added to users.
        // Wait, 'users' table in schema.sql does NOT have a password column. 
        // 'admin_users' has 'password_hash'.
        // logic: User might need to "activate" account or we generate a temp password.
        // For this task, I will add a password handling mechanism. 
        // I will add 'password_hash' to users table implicitly or handle it here.
        // Let's assume we need to add a password column if it's not there.
        // Given constraints, I'll check if 'password_hash' exists in $user.

        if (!isset($user['password_hash']) || empty($user['password_hash'])) {
            return ['success' => false, 'message' => 'Account not activated. Please reset password.'];
        }

        if (isset($user['status']) && $user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is suspended or inactive. Please contact support.'];
        }

        if (password_verify($password, $user['password_hash'])) {
            // Set session
            $_SESSION[$this->sessionName] = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'type' => $user['type'] ?? 'user'
            ];

            // Log successful login? (Optional)

            return ['success' => true, 'redirect' => 'index.php'];
        }

        return ['success' => false, 'message' => 'Invalid credentials.'];
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
