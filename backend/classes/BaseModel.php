<?php
/**
 * Base Model Class
 * Provides common database operations for all models
 */

abstract class BaseModel
{
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $timestamps = true;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find a record by ID
     */
    public function find($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        return $this->db->fetch($sql, [':id' => $id]);
    }

    /**
     * Find all records with optional conditions
     */
    public function findAll($where = '1=1', $params = [], $orderBy = null, $limit = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$where}";

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Find first record matching conditions
     */
    public function findWhere($where, $params = [])
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$where} LIMIT 1";
        return $this->db->fetch($sql, $params);
    }

    /**
     * Create a new record
     */
    public function create($data)
    {
        // Filter data to only include fillable fields
        $filteredData = $this->filterFillable($data);

        // Add timestamps if enabled
        if ($this->timestamps) {
            $filteredData['created_at'] = date('Y-m-d H:i:s');
            $filteredData['updated_at'] = date('Y-m-d H:i:s');
        }

        return $this->db->insert($this->table, $filteredData);
    }

    /**
     * Update a record by ID
     */
    public function update($id, $data)
    {
        // Filter data to only include fillable fields
        $filteredData = $this->filterFillable($data);

        // Add updated timestamp if enabled
        if ($this->timestamps) {
            $filteredData['updated_at'] = date('Y-m-d H:i:s');
        }

        $where = "{$this->primaryKey} = :id";
        $whereParams = [':id' => $id];

        return $this->db->update($this->table, $filteredData, $where, $whereParams);
    }

    /**
     * Delete a record by ID
     */
    public function delete($id)
    {
        $where = "{$this->primaryKey} = :id";
        $params = [':id' => $id];

        return $this->db->delete($this->table, $where, $params);
    }

    /**
     * Count records with optional conditions
     */
    public function count($where = '1=1', $params = [])
    {
        return $this->db->count($this->table, $where, $params);
    }

    /**
     * Check if a record exists
     */
    public function exists($where, $params = [])
    {
        return $this->count($where, $params) > 0;
    }

    /**
     * Get paginated results
     */
    public function paginate($page = 1, $perPage = 20, $where = '1=1', $params = [], $orderBy = null)
    {
        $offset = ($page - 1) * $perPage;

        // Get total count
        $total = $this->count($where, $params);

        // Get records for current page
        $sql = "SELECT * FROM {$this->table} WHERE {$where}";

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $records = $this->db->fetchAll($sql, $params);

        return [
            'data' => $records,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }

    /**
     * Filter data to only include fillable fields
     */
    protected function filterFillable($data)
    {
        if (empty($this->fillable)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Validate required fields
     */
    protected function validateRequired($data, $required = [], $allowEmpty = false)
    {
        $missing = [];

        foreach ($required as $field) {
            if (!isset($data[$field]) || (!$allowEmpty && $data[$field] === '')) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new Exception("Missing required fields: " . implode(', ', $missing));
        }

        return true;
    }

    /**
     * Sanitize input data
     */
    protected function sanitize($data)
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Validate email format
     */
    protected function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Generate a random token
     */
    protected function generateToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Hash password
     */
    protected function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verify password
     */
    protected function verifyPassword($password, $hash)
    {
        // Check if hash is null or empty to avoid deprecation warning
        if ($hash === null || $hash === '') {
            return false;
        }
        return password_verify($password, $hash);
    }

    /**
     * Get the database instance
     */
    protected function getDb()
    {
        return $this->db;
    }

    /**
     * Execute raw SQL query
     */
    protected function query($sql, $params = [])
    {
        return $this->db->query($sql, $params);
    }

    /**
     * Begin database transaction
     */
    protected function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    protected function commit()
    {
        return $this->db->commit();
    }

    /**
     * Rollback database transaction
     */
    protected function rollback()
    {
        return $this->db->rollback();
    }
}
?>