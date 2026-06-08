<?php
require_once __DIR__ . '/../config/config.php';

class SqliteDB
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite:' . DB_PATH);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // User methods
    public function createUser($data)
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (name, email, phone, user_type) VALUES (?, ?, ?, ?)');
        $stmt->execute([$data['name'], $data['email'], $data['phone'], $data['user_type']]);
        return $this->pdo->lastInsertId();
    }

    public function getUserByEmail($email)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function updateUser($id, $data)
    {
        $stmt = $this->pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ?, user_type = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$data['name'], $data['email'], $data['phone'], $data['user_type'], $id]);
    }

    // Assessment methods
    public function createAssessment($data)
    {
        $stmt = $this->pdo->prepare('INSERT INTO assessments (user_id, assessment_type, assessment_data) VALUES (?, ?, ?)');
        $stmt->execute([$data['user_id'], $data['assessment_type'], $data['assessment_data']]);
        return $this->pdo->lastInsertId();
    }

    // Sales methods
    public function createSale($data)
    {
        $stmt = $this->pdo->prepare('INSERT INTO sales (user_id, product_name, amount, currency, payment_status, transaction_id) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$data['user_id'], $data['product_name'], $data['amount'], $data['currency'], $data['payment_status'], $data['transaction_id']]);
        return $this->pdo->lastInsertId();
    }

    // Admin methods
    public function getAdminByUsername($username)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    public function getUsers($params = [])
    {
        $sql = 'SELECT * FROM users';
        if (isset($params['search'])) {
            $sql .= ' WHERE name LIKE ? OR email LIKE ?';
        }
        $stmt = $this->pdo->prepare($sql);
        if (isset($params['search'])) {
            $search = '%' . $params['search'] . '%';
            $stmt->execute([$search, $search]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public function getAssessments($params = [])
    {
        $sql = 'SELECT a.*, u.name, u.email FROM assessments a JOIN users u ON a.user_id = u.id';
        $conditions = [];
        $execParams = [];

        if (!empty($params['funnel'])) {
            $conditions[] = 'a.assessment_type = ?';
            $execParams[] = $params['funnel'];
        }

        if (!empty($params['status'])) {
            $conditions[] = 'a.status = ?';
            $execParams[] = $params['status'];
        }

        if (!empty($params['search'])) {
            $conditions[] = '(u.name LIKE ? OR u.email LIKE ?)';
            $searchTerm = '%' . $params['search'] . '%';
            $execParams[] = $searchTerm;
            $execParams[] = $searchTerm;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if (!empty($params['limit'])) {
            $sql .= ' LIMIT ?';
            $execParams[] = (int) $params['limit'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($execParams);
        return $stmt->fetchAll();
    }

    public function getAssessmentCount($params = [])
    {
        $sql = 'SELECT COUNT(*) FROM assessments a JOIN users u ON a.user_id = u.id';
        $conditions = [];
        $execParams = [];

        if (!empty($params['funnel'])) {
            $conditions[] = 'a.assessment_type = ?';
            $execParams[] = $params['funnel'];
        }

        if (!empty($params['status'])) {
            $conditions[] = 'a.status = ?';
            $execParams[] = $params['status'];
        }

        if (!empty($params['search'])) {
            $conditions[] = '(u.name LIKE ? OR u.email LIKE ?)';
            $searchTerm = '%' . $params['search'] . '%';
            $execParams[] = $searchTerm;
            $execParams[] = $searchTerm;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($execParams);
        return $stmt->fetchColumn();
    }

    public function getSales($params = [])
    {
        $sql = 'SELECT s.*, u.name as customer_name, u.email as customer_email FROM sales s JOIN users u ON s.user_id = u.id';
        $conditions = [];
        $execParams = [];

        if (isset($params['status'])) {
            $conditions[] = 's.payment_status = ?';
            $execParams[] = $params['status'];
        }

        if (isset($params['search'])) {
            $conditions[] = '(u.name LIKE ? OR u.email LIKE ? OR s.product_name LIKE ? OR s.transaction_id LIKE ?)';
            $search = '%' . $params['search'] . '%';
            $execParams = array_merge($execParams, [$search, $search, $search, $search]);
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if (!empty($params['limit'])) {
            $sql .= ' LIMIT ?';
            $execParams[] = (int) $params['limit'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($execParams);
        return $stmt->fetchAll();
    }

    public function getUserById($id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function deleteUser($id)
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function getAssessmentById($id)
    {
        $stmt = $this->pdo->prepare('SELECT a.*, u.name, u.email FROM assessments a JOIN users u ON a.user_id = u.id WHERE a.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function updateAssessment($id, $data)
    {
        if (isset($data['status'])) {
            $stmt = $this->pdo->prepare('UPDATE assessments SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$data['status'], $id]);
        }
        if (isset($data['note'])) {
            $stmt = $this->pdo->prepare("UPDATE assessments SET notes = json_insert(COALESCE(notes, '[]'), '$[#]', json(?)) WHERE id = ?");
            $stmt->execute([$data['note'], $id]);
        }
    }

    public function deleteAssessment($id)
    {
        $stmt = $this->pdo->prepare('DELETE FROM assessments WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function getTrackingData($params = [])
    {
        $sql = 'SELECT COUNT(id) as count, DATE(created_at) as date, assessment_type FROM assessments GROUP BY DATE(created_at), assessment_type';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
    }

    public function getSaleById($id)
    {
        $stmt = $this->pdo->prepare('SELECT s.*, u.name as customer_name, u.email as customer_email FROM sales s JOIN users u ON s.user_id = u.id WHERE s.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function updateSale($id, $data)
    {
        if (isset($data['payment_status'])) {
            $stmt = $this->pdo->prepare('UPDATE sales SET payment_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$data['payment_status'], $id]);
        }
    }

    public function addSaleNote($saleId, $note, $adminUsername)
    {
        $noteData = json_encode([
            'note' => $note,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $adminUsername
        ]);
        $stmt = $this->pdo->prepare("UPDATE sales SET notes = json_insert(COALESCE(notes, '[]'), '$[#]', json(?)) WHERE id = ?");
        $stmt->execute([$noteData, $saleId]);
    }

    public function getDashboardStats()
    {
        $stats = [];
        $stats['users'] = $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $stats['sales'] = $this->pdo->query('SELECT COUNT(*) FROM sales')->fetchColumn();
        $stats['assessments'] = $this->pdo->query('SELECT COUNT(*) FROM assessments')->fetchColumn();

        $funnels = ['pcos', 'acne', 'weight', 'egbon'];
        foreach ($funnels as $funnel) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM assessments WHERE assessment_type = ?');
            $stmt->execute([$funnel]);
            $stats['assessments_by_funnel'][$funnel] = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sales s JOIN assessments a ON s.user_id = a.user_id WHERE a.assessment_type = ?');
            $stmt->execute([$funnel]);
            $stats['sales_by_funnel'][$funnel] = $stmt->fetchColumn();
        }

        return $stats;
    }

    public function getDailyConversionData($days = 7)
    {
        $stmt = $this->pdo->query("
            SELECT
                date(created_at) as date,
                assessment_type,
                COUNT(*) as count
            FROM assessments
            WHERE created_at >= date('now', '-{$days} days')
            GROUP BY date, assessment_type
        ");
        $dailyAssessments = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

        $stmt = $this->pdo->query("
            SELECT
                date(s.created_at) as date,
                a.assessment_type,
                COUNT(*) as count
            FROM sales s
            JOIN assessments a ON s.user_id = a.user_id
            WHERE s.created_at >= date('now', '-{$days} days')
            GROUP BY date, a.assessment_type
        ");
        $dailySales = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

        return ['assessments' => $dailyAssessments, 'sales' => $dailySales];
    }

    public function getAdminActivityLogs($limit = 10)
    {
        $stmt = $this->pdo->prepare('SELECT a.*, ad.username FROM admin_activity_log a JOIN admins ad ON a.admin_id = ad.id ORDER BY a.created_at DESC LIMIT ?');
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function logAdminActivity($adminId, $action, $details)
    {
        $stmt = $this->pdo->prepare('INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)');
        $stmt->execute([$adminId, $action, $details]);
    }

    public function getSalesCount($params = [])
    {
        $sql = 'SELECT COUNT(*) FROM sales s';
        $execParams = [];
        $conditions = [];

        if (!empty($params['status'])) {
            $conditions[] = 's.payment_status = ?';
            $execParams[] = $params['status'];
        }

        if (!empty($params['search'])) {
            $conditions[] = '(u.name LIKE ? OR u.email LIKE ? OR s.product_name LIKE ? OR s.transaction_id LIKE ?)';
            $searchTerm = '%' . $params['search'] . '%';
            $execParams[] = $searchTerm;
            $execParams[] = $searchTerm;
            $execParams[] = $searchTerm;
            $execParams[] = $searchTerm;
        }

        if (!empty($conditions)) {
            $sql .= ' JOIN users u ON s.user_id = u.id WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($execParams);
        return $stmt->fetchColumn();
    }

    public function getTrackingLogs($funnel, $event, $limit)
    {
        // This is a placeholder as there is no tracking table in the schema
        return [];
    }
}