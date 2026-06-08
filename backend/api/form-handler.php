<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

function setSecureCorsHeaders() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : [];
    
    if (in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        header('Access-Control-Allow-Credentials: true');
    } else {
        header('Access-Control-Allow-Origin: ' . ($allowedOrigins[0] ?? ''));
    }
}

setSecureCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function validateCsrfToken($token) {
    // Allow bypass for local development if defined
    if (defined('APP_ENV') && APP_ENV === 'development') {
        return true;
    }
    
    if (empty($token)) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function checkRateLimit($ip, $limit = 100, $window = 3600) {
    $db = Database::getInstance();
    $tableExists = $db->fetch("SELECT name FROM sqlite_master WHERE type='table' AND name='rate_limits'");
    
    if (!$tableExists) {
        $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            request_count INTEGER DEFAULT 0,
            window_start INTEGER NOT NULL,
            INDEX idx_ip (ip_address),
            INDEX idx_window (window_start)
        )");
    }
    
    $now = time();
    $windowStart = $now - $window;
    
    $existing = $db->fetch("SELECT * FROM rate_limits WHERE ip_address = ? AND window_start > ?", 
        [$_SERVER['REMOTE_ADDR'], $windowStart]);
    
    if ($existing) {
        if ($existing['request_count'] >= $limit) {
            return false;
        }
        $db->exec("UPDATE rate_limits SET request_count = request_count + 1 WHERE id = ?", [$existing['id']]);
    } else {
        $db->insert('rate_limits', [
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'request_count' => 1,
            'window_start' => $now
        ]);
    }
    
    return true;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit();
}

$clientToken = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($clientToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit();
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIp, API_RATE_LIMIT ?? 100, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
    exit();
}

$formType = sanitizeInput($data['form_type'] ?? 'unknown');
$response = ['success' => false, 'message' => '', 'data' => []];

try {
    $db = Database::getInstance();
    switch ($formType) {
        case 'assessment':
            $response = handleAssessment($data, $db);
            break;
        case 'sales':
            $response = handleSales($data, $db);
            break;
        case 'tracking':
            $response = handleTracking($data, $db);
            break;
        case 'nurture_queue':
            $response = handleNurtureQueue($data, $db);
            break;
        default:
            $response = ['success' => false, 'message' => 'Unknown form type'];
    }
} catch (Exception $e) {
    error_log("Form handler error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'An error occurred. Please try again later.'];
}

echo json_encode($response);

function handleAssessment($data, $db)
{
    $requiredFields = ['email', 'assessment_data'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }

    $email = sanitizeInput($data['email']);
    if (!validateEmail($email)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }

    $assessmentType = sanitizeInput($data['assessment_type'] ?? 'general');
    $allowedTypes = ['general', 'pcos', 'weight', 'acne'];
    if (!in_array($assessmentType, $allowedTypes)) {
        $assessmentType = 'general';
    }
    
    $name = sanitizeInput($data['name'] ?? explode('@', $email)[0]);
    $phone = sanitizeInput($data['phone'] ?? '');

    // Check if user exists
    $user = $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);

    if (!$user) {
        // Create user
        $userId = $db->insert('users', [
            'first_name' => $name,
            'name' => $name,  // Also set name for admin display
            'email' => $email,
            'phone' => $phone,
            'type' => 'lead',
            'condition_type' => $assessmentType,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        $userId = $user['id'];
        // Update condition_type if not set
        if (empty($user['condition_type'])) {
            $db->update('users', ['condition_type' => $assessmentType], 'id = :id', [':id' => $userId]);
        }
    }

    // Parse assessment data for score extraction
    $assessmentDataParsed = is_string($data['assessment_data'])
        ? json_decode($data['assessment_data'], true)
        : $data['assessment_data'];

    // Extract score if available (from pcosType.scores or direct score)
    $score = null;
    if (isset($assessmentDataParsed['pcosType']['scores'])) {
        $score = array_sum($assessmentDataParsed['pcosType']['scores']);
    } elseif (isset($assessmentDataParsed['score'])) {
        $score = $assessmentDataParsed['score'];
    }

    // Create assessment with all required fields
    $assessmentId = uniqid('assess_');
    $assessmentData = is_string($data['assessment_data']) ? $data['assessment_data'] : json_encode($data['assessment_data']);

    $db->insert('assessments', [
        'id' => $assessmentId,
        'user_id' => $userId,
        'email' => $email,
        'name' => $name,
        'phone' => $phone,
        'assessment_type' => $assessmentType,
        'assessment_data' => $assessmentData,
        'score' => $score,
        'status' => 'completed',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    return [
        'success' => true,
        'message' => 'Assessment saved successfully',
        'data' => [
            'assessment_id' => $assessmentId,
            'email' => $email,
            'type' => $assessmentType
        ]
    ];
}

function handleSales($data, $db)
{
    $requiredFields = ['email', 'amount'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }

    $email = sanitizeInput($data['email']);
    if (!validateEmail($email)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }

    $amount = floatval($data['amount']);
    if ($amount <= 0 || $amount > 1000000) {
        return ['success' => false, 'message' => 'Invalid amount'];
    }

    $name = sanitizeInput($data['name'] ?? explode('@', $email)[0]);
    $phone = sanitizeInput($data['phone'] ?? '');
    $productType = sanitizeInput($data['product_type'] ?? 'general');
    $productName = sanitizeInput($data['product_name'] ?? $data['product'] ?? ucfirst($productType) . ' Plan');

    // Determine plan duration from product name or explicit field
    $planDuration = intval($data['plan_duration'] ?? 0);
    if ($planDuration <= 0) {
        if (stripos($productName, '90') !== false) {
            $planDuration = 90;
        } elseif (stripos($productName, '30') !== false) {
            $planDuration = 30;
        } else {
            $planDuration = ($amount > 40000) ? 90 : 30;
        }
    }

    $planStartDate = date('Y-m-d H:i:s');
    $planEndDate = date('Y-m-d H:i:s', strtotime("+{$planDuration} days"));

    // Check if user exists
    $user = $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);

    if (!$user) {
        $userId = $db->insert('users', [
            'first_name' => $name,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'type' => 'customer',
            'status' => 'active',
            'condition_type' => $productType,
            'plan_duration' => $planDuration,
            'plan_start_date' => $planStartDate,
            'plan_end_date' => $planEndDate,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        $userId = $user['id'];
        // Upgrade lead to customer and activate
        $db->update('users', [
            'type' => 'customer',
            'status' => 'active',
            'plan_duration' => $planDuration,
            'plan_start_date' => $planStartDate,
            'plan_end_date' => $planEndDate
        ], 'id = :id', [':id' => $userId]);
    }

    $saleId = uniqid('sale_');
    $db->insert('sales', [
        'id' => $saleId,
        'user_id' => $userId,
        'email' => $email,
        'name' => $name,
        'phone' => $phone,
        'product_type' => $productType,
        'product_name' => $productName,
        'amount' => $data['amount'],
        'currency' => $data['currency'] ?? 'USD',
        'payment_status' => $data['payment_status'] ?? 'pending',
        'tx_ref' => $data['tx_ref'] ?? $data['transaction_id'] ?? '',
        'plan_duration' => $planDuration,
        'plan_start_date' => $planStartDate,
        'plan_end_date' => $planEndDate,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    return [
        'success' => true,
        'message' => 'Sale recorded successfully',
        'data' => [
            'sale_id' => $saleId,
            'transaction_id' => $data['transaction_id'] ?? '',
            'plan_duration' => $planDuration,
            'plan_end_date' => $planEndDate
        ]
    ];
}

function handleTracking($data, $db)
{
    try {
        $emailInput = $data['email'] ?? null;
        if ($emailInput && !validateEmail($emailInput)) {
            $emailInput = null;
        }
        
        $allowedFunnels = ['pcos', 'weight', 'acne', 'sales', 'nurture'];
        $funnelName = sanitizeInput($data['funnel_name'] ?? 'unknown');
        if (!in_array($funnelName, $allowedFunnels)) {
            $funnelName = 'unknown';
        }
        
        $allowedEvents = ['view', 'click', 'submit', 'purchase', 'exit'];
        $eventType = sanitizeInput($data['event_type'] ?? 'view');
        if (!in_array($eventType, $allowedEvents)) {
            $eventType = 'view';
        }

        $db->insert('funnel_tracking', [
            'session_id' => sanitizeInput($data['session_id'] ?? uniqid('sess_')),
            'user_id' => $data['user_id'] ?? null,
            'email' => $emailInput ? sanitizeInput($emailInput) : null,
            'funnel_name' => $funnelName,
            'step_name' => sanitizeInput($data['step_name'] ?? 'unknown'),
            'event_type' => $eventType,
            'metadata' => is_string($data['metadata'] ?? null) ? $data['metadata'] : json_encode($data['metadata'] ?? []),
            'url' => sanitizeInput($data['url'] ?? ''),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return ['success' => true, 'message' => 'Tracking saved'];
    } catch (Exception $e) {
        error_log("Tracking error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Tracking failed'];
    }
}

function handleNurtureQueue($data, $db)
{
    try {
        $email = sanitizeInput($data['email'] ?? '');
        if (empty($email) || !validateEmail($email)) {
            return ['success' => false, 'message' => 'Valid email is required for nurture'];
        }

        $existing = $db->fetch("SELECT id FROM nurture_queue WHERE email = ? AND status = 'pending'", [$email]);
        if ($existing) {
            return ['success' => true, 'message' => 'Already in nurture queue'];
        }

        $customer = $db->fetch("SELECT id FROM users WHERE email = ? AND type = 'customer'", [$email]);
        if ($customer) {
            return ['success' => true, 'message' => 'Already a customer, skipping nurture'];
        }

        $db->insert('nurture_queue', [
            'email' => $email,
            'name' => sanitizeInput($data['name'] ?? ''),
            'phone' => sanitizeInput($data['phone'] ?? ''),
            'pcos_type' => sanitizeInput($data['pcos_type'] ?? ''),
            'confidence' => sanitizeInput($data['confidence'] ?? ''),
            'funnel' => sanitizeInput($data['funnel'] ?? 'pcos'),
            'session_id' => sanitizeInput($data['session_id'] ?? ''),
            'assessment_completed_at' => $data['assessment_completed_at'] ?? date('Y-m-d H:i:s'),
            'sales_page_viewed_at' => $data['sales_page_viewed_at'] ?? date('Y-m-d H:i:s'),
            'status' => 'pending',
            'nurture_step' => 0,
            'next_contact_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return ['success' => true, 'message' => 'Added to nurture queue'];
    } catch (Exception $e) {
        error_log("Nurture queue error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Nurture queue failed. Please try again later.'];
    }
}
