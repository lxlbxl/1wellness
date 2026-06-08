<?php
/**
 * N8N Export API - Production-Ready Data Export for Automation
 *
 * FEATURES:
 * - Fetch Users (with is_buyer flag)
 * - Fetch Sales (with detailed filtering)
 * - Fetch Assessments (Parses JSON, Flattens User Info)
 * - CRUD (Update/Delete records via POST)
 *
 * STANDARD USAGE:
 * GET /n8n-export.php?type=assessments&include_user=1       <- Get all assessments with user names
 * GET /n8n-export.php?type=assessments&user_id=523          <- Get history for specific user
 * GET /n8n-export.php?type=users&exclude_buyers=1           <- Get leads who haven't bought yet
 *
 * AUTHENTICATION:
 * Header: X-API-KEY: your_key
 * Query: ?api_key=your_key
 */

// 1. Output Control
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Error Handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

$response = ['success' => false, 'error' => 'Unknown error'];
$httpCode = 200;

try {
    // 3. Dependencies
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../classes/Database.php';

    // 4. Authentication
    $validKey = defined('N8N_API_KEY') ? N8N_API_KEY : '';
    $providedKey = null;

    // Try headers first
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $headersLower = array_change_key_case($headers, CASE_LOWER);
        $providedKey = $headersLower['x-api-key'] ?? null;
    }
    // Fallback to Server/Get
    if (!$providedKey) $providedKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
    if (!$providedKey) $providedKey = $_GET['api_key'] ?? null;

    if (empty($validKey) || !$providedKey || $providedKey !== $validKey) {
        $httpCode = 401;
        throw new Exception('Unauthorized: Invalid or missing API Key.');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // ===========================================
    // HANDLE POST REQUESTS (Updates/Deletes)
    // ===========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            $httpCode = 400;
            throw new Exception('Invalid JSON body');
        }

        $action = $input['action'] ?? 'update';
        $type = $input['type'] ?? '';
        $id = $input['id'] ?? null;
        $data = $input['data'] ?? null;

        $allowedTables = [
            'assessments', 'users', 'sales', 'contacts',
            'pcos_assessments', 'acne_assessments', 'weight_assessments',
            'member_profiles', 'funnel_tracking'
        ];

        if (!in_array($type, $allowedTables)) {
            $httpCode = 400;
            throw new Exception("Invalid type: $type.");
        }

        if ($action === 'update') {
            if (!$id || !$data || !is_array($data)) {
                $httpCode = 400;
                throw new Exception('Update requires: id and data (object)');
            }
            unset($data['id'], $data['created_at']);
            $data['updated_at'] = date('Y-m-d H:i:s');

            $rowCount = $db->update($type, $data, "id = :id", [':id' => $id]);

            $response = [
                'success' => true,
                'action' => 'update',
                'type' => $type,
                'id' => $id,
                'rows_affected' => $rowCount
            ];

        } elseif ($action === 'delete') {
            if (!$id) {
                $httpCode = 400;
                throw new Exception('Delete requires: id');
            }
            $stmt = $conn->prepare("DELETE FROM $type WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $rowCount = $stmt->rowCount();

            $response = [
                'success' => true,
                'action' => 'delete',
                'type' => $type,
                'id' => $id,
                'rows_affected' => $rowCount
            ];
        } else {
            $httpCode = 400;
            throw new Exception("Invalid action: $action.");
        }

        ob_clean();
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===========================================
    // HANDLE GET REQUESTS (Read)
    // ===========================================

    // 1. Parse Parameters
    $type = $_GET['type'] ?? 'assessments';
    $id = $_GET['id'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $page = max(1, intval($_GET['page'] ?? 1));
    
    // Filters
    $email = $_GET['email'] ?? null;
    $phone = $_GET['phone'] ?? null;
    $status = $_GET['status'] ?? null;
    $assessmentType = $_GET['assessment_type'] ?? null;
    $includeUser = ($_GET['include_user'] ?? '0') === '1';
    
    // Advanced User Filters
    $userType = $_GET['user_type'] ?? null;
    $excludeBuyers = ($_GET['exclude_buyers'] ?? '0') === '1';

    // Date Filters
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    if ($startDate && strlen($startDate) === 10) $startDate .= ' 00:00:00';
    if ($endDate && strlen($endDate) === 10) $endDate .= ' 23:59:59';

    // Validate Type
    $validTypes = [
        'sales', 'assessments', 'users', 'contacts',
        'pcos_assessments', 'acne_assessments', 'weight_assessments',
        'funnel_tracking', 'member_profiles'
    ];

    if (!in_array($type, $validTypes)) {
        $httpCode = 400;
        throw new Exception("Invalid type: $type. Allowed: " . implode(', ', $validTypes));
    }

    // 2. Build Query
    $whereClauses = [];
    $params = [];

    // ID Filters
    if ($id) {
        $whereClauses[] = "id = :id";
        $params[':id'] = $id;
    }
    if ($userId) {
        $whereClauses[] = "user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    // Date Filters
    if ($startDate) {
        $whereClauses[] = "created_at >= :start_date";
        $params[':start_date'] = $startDate;
    }
    if ($endDate) {
        $whereClauses[] = "created_at <= :end_date";
        $params[':end_date'] = $endDate;
    }

    // Email Filter (Smart Lookup)
    if ($email) {
        $operator = strpos($email, '*') !== false ? 'LIKE' : '=';
        $emailValue = str_replace('*', '%', $email);

        if ($type === 'users') {
            // Direct filter for users table
            $whereClauses[] = "email $operator :email";
            $params[':email'] = $emailValue;
        } else {
            // Subquery for other tables (finds user_id via email)
            // This allows finding assessments by email even if the table only has user_id
            $whereClauses[] = "user_id IN (SELECT id FROM users WHERE email $operator :email)";
            $params[':email'] = $emailValue;
        }
    }

    // Phone Filter
    if ($phone) {
        $operator = strpos($phone, '*') !== false ? 'LIKE' : '=';
        $phoneValue = str_replace('*', '%', $phone);
        
        if ($type === 'users') {
            $whereClauses[] = "phone $operator :phone";
            $params[':phone'] = $phoneValue;
        } else {
            $whereClauses[] = "user_id IN (SELECT id FROM users WHERE phone $operator :phone)";
            $params[':phone'] = $phoneValue;
        }
    }

    // Type Specific Filters
    if ($status) {
        $whereClauses[] = "status = :status";
        $params[':status'] = $status;
    }
    if ($assessmentType) {
        $whereClauses[] = "assessment_type = :assessment_type";
        $params[':assessment_type'] = $assessmentType;
    }
    if ($userType && $type === 'users') {
        $whereClauses[] = "type = :user_type";
        $params[':user_type'] = $userType;
    }

    // Exclude Buyers (Users only)
    if ($excludeBuyers && $type === 'users') {
        $whereClauses[] = "id NOT IN (SELECT DISTINCT user_id FROM sales WHERE user_id IS NOT NULL)";
    }

    $whereSQL = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // 3. Select Fields & Sorting
    $sortField = $_GET['sort'] ?? 'created_at';
    $sortOrder = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    
    // Sanitize sort
    $allowedSort = ['id', 'created_at', 'updated_at', 'email', 'amount', 'status'];
    if (!in_array($sortField, $allowedSort)) $sortField = 'created_at';

    $selectFields = "*";
    
    // Add computed 'is_buyer' column for users table
    if ($type === 'users') {
        $selectFields = "*, (SELECT COUNT(*) FROM sales WHERE sales.user_id = users.id) > 0 as is_buyer";
    }

    // 4. Pagination
    $offset = ($page - 1) * $limit;
    $limitSQL = $limit > 0 ? "LIMIT $limit OFFSET $offset" : "";

    // 5. Execute Query
    $sql = "SELECT $selectFields FROM $type $whereSQL ORDER BY $sortField $sortOrder $limitSQL";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get Total Count (for pagination meta)
    if ($limit > 0) {
        $countSql = "SELECT COUNT(*) as total FROM $type $whereSQL";
        $countStmt = $conn->prepare($countSql);
        foreach ($params as $key => $value) $countStmt->bindValue($key, $value);
        $countStmt->execute();
        $totalCount = intval($countStmt->fetch(PDO::FETCH_ASSOC)['total']);
    } else {
        $totalCount = count($data);
    }

    // ===========================================
    // DATA POST-PROCESSING
    // ===========================================

    // 1. Fetch & Flatten User Data
    if ($includeUser && count($data) > 0) {
        $userIds = array_filter(array_unique(array_column($data, 'user_id')));
        
        if (count($userIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            
            // Fetch users with is_buyer flag
            $userSql = "SELECT id, first_name, last_name, email, phone, (SELECT COUNT(*) FROM sales WHERE sales.user_id = users.id) > 0 as is_buyer FROM users WHERE id IN ($placeholders)";
            
            $userStmt = $conn->prepare($userSql);
            $userStmt->execute(array_values($userIds));
            $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
            $usersById = array_column($users, null, 'id');

            foreach ($data as &$row) {
                $uid = $row['user_id'] ?? null;
                if ($uid && isset($usersById[$uid])) {
                    $u = $usersById[$uid];
                    
                    // FLATTEN: Merge Key User Fields to Root
                    $row['name'] = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                    
                    // Only overwrite if empty or missing in the source table
                    if (empty($row['email'])) $row['email'] = $u['email'];
                    if (empty($row['phone'])) $row['phone'] = $u['phone'];
                    
                    // Attach is_buyer if not present
                    if (!isset($row['is_buyer'])) $row['is_buyer'] = $u['is_buyer'];
                }
            }
            unset($row);
        }
    }

    // 2. Parse JSON Fields
    foreach ($data as &$row) {
        $row = parseJsonFields($row);
    }
    unset($row);

    // 6. Build Response
    $totalPages = $limit > 0 ? (int) ceil($totalCount / $limit) : 1;
    
    $response = [
        'success' => true,
        'type' => $type,
        'meta' => [
            'total' => $totalCount,
            'count' => count($data),
            'page' => $page,
            'limit' => $limit > 0 ? $limit : 'unlimited',
            'pages' => $totalPages
        ],
        'data' => $data
    ];

} catch (Exception $e) {
    if ($httpCode === 200) $httpCode = 500;
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $httpCode
    ];
}

/**
 * Parse JSON fields in a record
 */
function parseJsonFields($row)
{
    $jsonFields = [
        'symptoms', 'lifestyle_factors', 'diet_preferences', 'goals',
        'assessment_data', 'recommendations', 'tracking_data', 'answers',
        'customer_data', 'product_data', 'metadata', 'allergies',
        'dietary_preferences', 'messages', 'details', 'acne_type',
        'triggers', 'plan_data'
    ];

    foreach ($jsonFields as $field) {
        if (isset($row[$field]) && is_string($row[$field])) {
            $decoded = json_decode($row[$field], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row[$field] = $decoded;
            }
        }
    }
    return $row;
}

http_response_code($httpCode);
ob_clean();
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;