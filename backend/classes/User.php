<?php
/**
 * User Model Class
 * Handles user-related database operations
 */

class User extends BaseModel
{
    protected $table = 'users';
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'username',
        'password_hash',
        'age',
        'gender',
        'location',
        'health_goals',
        'medical_history',
        'current_medications',
        'lifestyle_factors',
        'dietary_preferences',
        'exercise_routine',
        'stress_levels',
        'sleep_patterns',
        'consultation_preference',
        'condition_type',
        'marketing_consent',
        'data_consent',
        'status',
        'type'
    ];

    /**
     * Create a new user with validation
     */
    public function createUser($data)
    {
        // Handle 'name' if provided instead of first/last
        if (isset($data['name']) && (!isset($data['first_name']) || empty($data['first_name']))) {
            $parts = explode(' ', $data['name'], 2);
            $data['first_name'] = $parts[0];
            $data['last_name'] = $parts[1] ?? '';
        }

        // Validate required fields
        $required = ['first_name', 'email'];
        $this->validateRequired($data, $required);

        // Validate email format
        if (!$this->validateEmail($data['email'])) {
            throw new Exception("Invalid email format");
        }

        // Check if email already exists
        if ($this->emailExists($data['email'])) {
            throw new Exception("Email already exists");
        }

        // Sanitize data
        $data = $this->sanitize($data);

        // Set default values
        $data['status'] = $data['status'] ?? 'active';
        $data['type'] = $data['type'] ?? 'lead';
        $data['marketing_consent'] = $data['marketing_consent'] ?? 0;
        $data['data_consent'] = $data['data_consent'] ?? 1;
        $data['last_name'] = $data['last_name'] ?? '';

        return $this->create($data);
    }

    /**
     * Update user information
     */
    public function updateUser($id, $data)
    {
        // Check if user exists
        $user = $this->find($id);
        if (!$user) {
            throw new Exception("User not found");
        }

        // If email is being updated, check for duplicates
        if (isset($data['email']) && $data['email'] !== $user['email']) {
            if (!$this->validateEmail($data['email'])) {
                throw new Exception("Invalid email format");
            }

            if ($this->emailExists($data['email'])) {
                throw new Exception("Email already exists");
            }
        }

        // Sanitize data
        $data = $this->sanitize($data);

        return $this->update($id, $data);
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
     * Find user by email
     */
    public function findByEmail($email)
    {
        return $this->findWhere("email = :email", [':email' => $email]);
    }

    /**
     * Get users with assessments
     */
    public function getUsersWithAssessments($limit = null)
    {
        $sql = "
            SELECT 
                u.*,
                COUNT(DISTINCT p.id) as pcos_assessments,
                COUNT(DISTINCT a.id) as acne_assessments,
                COUNT(DISTINCT w.id) as weight_assessments,
                COUNT(DISTINCT s.id) as sales_count
            FROM users u
            LEFT JOIN pcos_assessments p ON u.id = p.user_id
            LEFT JOIN acne_assessments a ON u.id = a.user_id
            LEFT JOIN weight_assessments w ON u.id = w.user_id
            LEFT JOIN sales s ON u.id = s.user_id
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ";

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        return $this->db->fetchAll($sql);
    }

    /**
     * Get user statistics
     */
    public function getUserStats()
    {
        $stats = [];

        // Total users
        $stats['total'] = $this->count();

        // New users this week
        $stats['this_week'] = $this->count(
            "created_at >= datetime('now', '-7 days')"
        );

        // New users this month
        $stats['this_month'] = $this->count(
            "created_at >= datetime('now', '-30 days')"
        );

        // Active users (have assessments or sales)
        $sql = "
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            WHERE EXISTS (
                SELECT 1 FROM pcos_assessments p WHERE p.user_id = u.id
                UNION
                SELECT 1 FROM acne_assessments a WHERE a.user_id = u.id
                UNION
                SELECT 1 FROM weight_assessments w WHERE w.user_id = u.id
                UNION
                SELECT 1 FROM sales s WHERE s.user_id = u.id
            )
        ";
        $result = $this->db->fetch($sql);
        $stats['active'] = $result['count'];

        // Users by gender
        $genderStats = $this->db->fetchAll("
            SELECT gender, COUNT(*) as count 
            FROM users 
            WHERE gender IS NOT NULL AND gender != ''
            GROUP BY gender
        ");

        $stats['by_gender'] = [];
        foreach ($genderStats as $stat) {
            $stats['by_gender'][$stat['gender']] = $stat['count'];
        }

        // Users by age group
        $ageStats = $this->db->fetchAll("
            SELECT 
                CASE 
                    WHEN age < 18 THEN 'Under 18'
                    WHEN age BETWEEN 18 AND 25 THEN '18-25'
                    WHEN age BETWEEN 26 AND 35 THEN '26-35'
                    WHEN age BETWEEN 36 AND 45 THEN '36-45'
                    WHEN age BETWEEN 46 AND 55 THEN '46-55'
                    WHEN age > 55 THEN 'Over 55'
                    ELSE 'Unknown'
                END as age_group,
                COUNT(*) as count
            FROM users
            GROUP BY age_group
            ORDER BY 
                CASE age_group
                    WHEN 'Under 18' THEN 1
                    WHEN '18-25' THEN 2
                    WHEN '26-35' THEN 3
                    WHEN '36-45' THEN 4
                    WHEN '46-55' THEN 5
                    WHEN 'Over 55' THEN 6
                    ELSE 7
                END
        ");

        $stats['by_age'] = [];
        foreach ($ageStats as $stat) {
            $stats['by_age'][$stat['age_group']] = $stat['count'];
        }

        return $stats;
    }

    /**
     * Search users
     */
    public function searchUsers($query, $limit = 50)
    {
        $searchTerm = '%' . $query . '%';

        $sql = "
            SELECT * FROM users 
            WHERE first_name LIKE :search 
               OR last_name LIKE :search 
               OR email LIKE :search 
               OR phone LIKE :search
            ORDER BY 
                CASE 
                    WHEN first_name LIKE :search OR last_name LIKE :search THEN 1
                    WHEN email LIKE :search THEN 2
                    ELSE 3
                END,
                created_at DESC
            LIMIT :limit
        ";

        return $this->db->fetchAll($sql, [
            ':search' => $searchTerm,
            ':limit' => $limit
        ]);
    }

    /**
     * Get user's complete profile with assessments and sales
     */
    public function getUserProfile($id)
    {
        $user = $this->find($id);
        if (!$user) {
            return null;
        }

        // Get assessments
        $user['pcos_assessments'] = $this->db->fetchAll(
            "SELECT * FROM pcos_assessments WHERE user_id = :user_id ORDER BY created_at DESC",
            [':user_id' => $id]
        );

        $user['acne_assessments'] = $this->db->fetchAll(
            "SELECT * FROM acne_assessments WHERE user_id = :user_id ORDER BY created_at DESC",
            [':user_id' => $id]
        );

        $user['weight_assessments'] = $this->db->fetchAll(
            "SELECT * FROM weight_assessments WHERE user_id = :user_id ORDER BY created_at DESC",
            [':user_id' => $id]
        );

        // Get sales
        $user['sales'] = $this->db->fetchAll(
            "SELECT * FROM sales WHERE user_id = :user_id ORDER BY order_date DESC",
            [':user_id' => $id]
        );

        return $user;
    }

    /**
     * Deactivate user
     */
    public function deactivateUser($id)
    {
        return $this->update($id, ['status' => 'inactive']);
    }

    /**
     * Activate user
     */
    public function activateUser($id)
    {
        return $this->update($id, ['status' => 'active']);
    }

    /**
     * Get recent users
     */
    public function generateCredentials($name)
    {
        // Generate a username based on name + random suffix
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        $username = substr($base, 0, 8) . rand(100, 999);

        // Generate a secure random password
        $password = bin2hex(random_bytes(4)); // 8 chars hex

        return ['username' => $username, 'password' => $password];
    }

    public function loginById($userId)
    {
        $user = $this->db->fetch("SELECT * FROM admin_users WHERE id = ?", [$userId]);
        if ($user) {
            // Set Session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['logged_in'] = true;
            return true;
        }
        return false;
    }
}
?>