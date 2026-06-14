<?php
header('Content-Type: application/json');
$allowedOrigins = ['http://localhost:5173', 'http://localhost:8080'];
if (in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit;

require_once '../../backend/config/config.php';
require_once '../../backend/classes/Database.php';
require_once '../../backend/classes/RateLimiter.php';
require_once '../../backend/classes/MemberAuth.php';
require_once '../../backend/classes/ActivityLogger.php';

$auth = new MemberAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rl = new RateLimiter();
    if ($rl->isIpThrottled($ip)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many requests. Please slow down.']);
        exit;
    }
    $rl->recordIpHit($ip);

    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';

    $result = $auth->login($email, $password);
    if ($result['success']) {
        // Init session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Proactive generation
        try {
            require_once '../../backend/classes/MealPlanner.php';
            $planner = new MealPlanner();
            $user = $auth->getCurrentUser();
            if ($user) {
                // Ensure 3 days of plans exist
                $planner->ensurePlansExist($user['user_id'] ?? $user['id'], 3);
            }
        } catch (Exception $e) {
            // Log but don't fail login
            error_log("Proactive gen failed: " . $e->getMessage());
        }

        // Log Activity
        try {
            $logger = new ActivityLogger();
            $logger->log($auth->getCurrentUser()['user_id'], 'login', ['method' => 'email_password']);
        } catch (Exception $e) {
        }

        echo json_encode(['success' => true, 'user' => $auth->getCurrentUser()]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => $result['message'] ?? 'Invalid email or password']);
    }
}
?>