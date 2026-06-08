<?php
/**
 * Admin Model Class
 * Handles admin user authentication and management
 */

class Admin extends BaseModel
{
    protected $table = 'admin_users';
    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'full_name',
        'role',
        'permissions',
        'status'
    ];

    /**
     * Create a new admin user
     */
    public function createAdmin($data)
    {
        // Validate required fields
        $required = ['username', 'email', 'password', 'full_name'];
        $this->validateRequired($data, $required);

        // Validate email format
        if (!$this->validateEmail($data['email'])) {
            throw new Exception("Invalid email format");
        }

        // Check if username or email already exists
        if ($this->usernameExists($data['username'])) {
            throw new Exception("Username already exists");
        }

        if ($this->emailExists($data['email'])) {
            throw new Exception("Email already exists");
        }

        // Sanitize data
        $data = $this->sanitize($data);

        // Hash password
        $data['password_hash'] = $this->hashPassword($data['password']);
        unset($data['password']);

        // Set default values
        $data['role'] = $data['role'] ?? 'admin';
        $data['status'] = $data['status'] ?? 'active';
        $data['permissions'] = $data['permissions'] ?? json_encode(['read', 'write']);

        return $this->create($data);
    }

    /**
     * Authenticate admin user
     */
    public function authenticate($username, $password)
    {
        // Find admin by username or email
        $admin = $this->findWhere(
            "(username = :identifier OR email = :identifier) AND status = 'active'",
            [':identifier' => $username]
        );

        if (!$admin) {
            return false;
        }

        // Check if password field exists (support both 'password_hash' and 'password' for file storage compatibility)
        $passwordField = $admin['password_hash'] ?? $admin['password'] ?? null;
        if (!$passwordField) {
            return false;
        }

        // Verify password
        if (!$this->verifyPassword($password, $passwordField)) {
            return false;
        }

        // Update last login
        $this->update($admin['id'], [
            'last_login' => date('Y-m-d H:i:s'),
            // 'login_count' => ($admin['login_count'] ?? 0) + 1 // login_count not in schema
        ]);

        // Remove password from returned data
        unset($admin['password_hash']);

        return $admin;
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username, $excludeId = null)
    {
        $where = "username = :username";
        $params = [':username' => $username];

        if ($excludeId) {
            $where .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        return $this->exists($where, $params);
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null)
    {
        $where = "email = :email";
        $params = [':email' => $email];

        if ($excludeId) {
            $where .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        return $this->exists($where, $params);
    }

    /**
     * Update admin profile
     */
    public function updateAdmin($id, $data)
    {
        // Check if admin exists
        $admin = $this->find($id);
        if (!$admin) {
            throw new Exception("Admin not found");
        }

        // If username is being updated, check for duplicates
        if (isset($data['username']) && $data['username'] !== $admin['username']) {
            if ($this->usernameExists($data['username'], $id)) {
                throw new Exception("Username already exists");
            }
        }

        // If email is being updated, check for duplicates
        if (isset($data['email']) && $data['email'] !== $admin['email']) {
            if (!$this->validateEmail($data['email'])) {
                throw new Exception("Invalid email format");
            }

            if ($this->emailExists($data['email'], $id)) {
                throw new Exception("Email already exists");
            }
        }

        // Hash password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password_hash'] = $this->hashPassword($data['password']);
            unset($data['password']);
        } else {
            // Remove password from update if not provided
            unset($data['password']);
        }

        // Sanitize data
        $data = $this->sanitize($data);

        return $this->update($id, $data);
    }

    /**
     * Change admin password
     */
    public function changePassword($id, $currentPassword, $newPassword)
    {
        $admin = $this->find($id);
        if (!$admin) {
            throw new Exception("Admin not found");
        }

        // Verify current password
        if (!$this->verifyPassword($currentPassword, $admin['password_hash'] ?? $admin['password'])) {
            throw new Exception("Current password is incorrect");
        }

        // Update password
        return $this->update($id, [
            'password_hash' => $this->hashPassword($newPassword)
        ]);
    }

    /**
     * Get admin by ID (without password)
     */
    public function getAdmin($id)
    {
        $admin = $this->find($id);
        if ($admin) {
            unset($admin['password']);
        }
        return $admin;
    }

    /**
     * Get all admins (without passwords)
     */
    public function getAllAdmins()
    {
        $admins = $this->findAll('1=1', [], 'created_at DESC');

        // Remove passwords
        foreach ($admins as &$admin) {
            unset($admin['password_hash']);
            unset($admin['password']);
        }

        return $admins;
    }

    /**
     * Deactivate admin
     */
    public function deactivateAdmin($id)
    {
        return $this->update($id, ['status' => 'inactive']);
    }

    /**
     * Activate admin
     */
    public function activateAdmin($id)
    {
        return $this->update($id, ['status' => 'active']);
    }

    /**
     * Log admin activity
     */
    public function logActivity($adminId, $action, $details = null)
    {
        $logData = [
            'admin_id' => $adminId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        return $this->db->insert('admin_logs', $logData);
    }

    /**
     * Get admin activity logs
     */
    public function getActivityLogs($adminId = null, $limit = 50)
    {
        $where = '1=1';
        $params = [];

        if ($adminId) {
            $where = 'admin_id = :admin_id';
            $params[':admin_id'] = $adminId;
        }

        $sql = "
            SELECT 
                al.*,
                au.username,
                au.full_name
            FROM admin_logs al
            LEFT JOIN admin_users au ON al.admin_id = au.id
            WHERE {$where}
            ORDER BY al.created_at DESC
            LIMIT {$limit}
        ";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Check admin permissions
     */
    public function hasPermission($adminId, $permission)
    {
        $admin = $this->find($adminId);
        if (!$admin) {
            return false;
        }

        // Super admin has all permissions
        if ($admin['role'] === 'super_admin') {
            return true;
        }

        // Check specific permissions
        $permissions = json_decode($admin['permissions'] ?? '[]', true);
        return in_array($permission, $permissions);
    }

    /**
     * Update admin permissions
     */
    public function updatePermissions($id, $permissions)
    {
        if (!is_array($permissions)) {
            throw new Exception("Permissions must be an array");
        }

        return $this->update($id, [
            'permissions' => json_encode($permissions)
        ]);
    }

    /**
     * Get admin statistics
     */
    public function getAdminStats()
    {
        $stats = [];

        // Total admins
        $stats['total'] = $this->count();

        // Active admins
        $stats['active'] = $this->count("status = 'active'");

        // Admins by role
        $roleStats = $this->db->fetchAll("
            SELECT role, COUNT(*) as count 
            FROM admin_users 
            GROUP BY role
        ");

        $stats['by_role'] = [];
        foreach ($roleStats as $stat) {
            $stats['by_role'][$stat['role']] = $stat['count'];
        }

        // Recent logins (last 7 days)
        $stats['recent_logins'] = $this->count(
            "last_login >= datetime('now', '-7 days')"
        );

        return $stats;
    }

    /**
     * Generate password reset token
     */
    public function generateResetToken($email)
    {
        $admin = $this->findWhere("email = :email", [':email' => $email]);
        if (!$admin) {
            throw new Exception("Admin not found");
        }

        $token = $this->generateToken();
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->update($admin['id'], [
            'reset_token' => $token,
            'reset_expires' => $expires
        ]);

        return $token;
    }

    /**
     * Reset password using token
     */
    public function resetPassword($token, $newPassword)
    {
        $admin = $this->findWhere(
            "reset_token = :token AND reset_expires > datetime('now')",
            [':token' => $token]
        );

        if (!$admin) {
            throw new Exception("Invalid or expired reset token");
        }

        return $this->update($admin['id'], [
            'password' => $this->hashPassword($newPassword),
            'reset_token' => null,
            'reset_expires' => null
        ]);
    }
}
?>