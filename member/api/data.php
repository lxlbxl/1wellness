<?php
header('Content-Type: application/json');
$allowedOrigins = ['http://localhost:5173', 'http://localhost:8080'];
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit;
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit;

require_once '../../backend/config/config.php';
require_once '../../backend/classes/Database.php';
require_once '../../backend/classes/MemberAuth.php';
require_once '../../backend/classes/MealPlanner.php';

$auth = new MemberAuth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $userSession = $auth->getCurrentUser();
    $userId = $userSession['user_id'] ?? $userSession['id'];
    $db = Database::getInstance();
    $user = $db->fetch("SELECT * FROM users WHERE id = :id", [':id' => $userId]);
    $planner = new MealPlanner();

    $action = $_GET['action'] ?? 'dashboard_data';

    switch ($action) {
        case 'dashboard_data':
            $viewDate = $_GET['date'] ?? date('Y-m-d');
            $profile = $db->fetch("SELECT * FROM member_profiles WHERE user_id = :uid", [':uid' => $userId]);
            if (!$profile) $profile = [];

            $conditionType = $profile['condition_type'] ?? $user['condition_type'] ?? 'pcos';

            // Query the correct assessment table based on condition
            $assessment = null;
            $assessmentTables = [
                'pcos' => 'pcos_assessments',
                'acne' => 'acne_assessments',
                'weight' => 'weight_assessments',
                'mens' => 'mens_assessments'
            ];
            $assessTable = $assessmentTables[$conditionType] ?? 'pcos_assessments';
            $assessment = $db->fetch("SELECT * FROM \"{$assessTable}\" WHERE user_id = :uid", [':uid' => $userId]);

            if (!$assessment) $assessment = [];

            $funnelPhase = $planner->getFunnelPhase($conditionType, $profile);
            $cycleData = $conditionType === 'pcos' ? $planner->calculateCyclePhase(
                $profile['last_period_date'] ?? 'now',
                $profile['cycle_length'] ?? 28,
                $viewDate
            ) : $funnelPhase;

            // Calculate program week
            $startDateStr = $profile['start_date'] ?? $user['created_at'] ?? date('Y-m-d');
            $start = new DateTime($startDateStr);
            $now = new DateTime($viewDate);
            $daysIn = $now->diff($start)->days + 1;
            $programWeek = ceil($daysIn / 7);

            $plan = $planner->getTodayPlan($userId, $viewDate);

            // Calculate subscription days left
            $expiryDate = $profile['subscription_expiry'] ?? null;
            $daysLeft = 0;
            $subStatus = 'expired';

            if ($expiryDate) {
                $todayCalc = (new DateTime())->setTime(0, 0, 0);
                $expiryCalc = (new DateTime($expiryDate))->setTime(0, 0, 0);
                $interval = $todayCalc->diff($expiryCalc);
                $daysLeft = (int) $interval->format('%r%a');
                if ($daysLeft > 0) $subStatus = 'active';
            }

            $funnelLabels = [
                'pcos' => ['label' => 'PCOS Type', 'value' => $profile['pcos_type'] ?? 'General'],
                'acne' => ['label' => 'Skin Type', 'value' => $profile['skin_type'] ?? 'Combination'],
                'weight' => ['label' => 'Focus Area', 'value' => 'Weight Management'],
                'mens' => ['label' => 'Focus Area', 'value' => 'Men\'s Vitality']
            ];
            $funnelInfo = $funnelLabels[$conditionType] ?? $funnelLabels['pcos'];

            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $userId,
                    'email' => $user['email'],
                    'first_name' => !empty($user['first_name']) ? $user['first_name'] : $user['name'],
                    'last_name' => $user['last_name'] ?? '',
                    'phone' => $user['phone'] ?? '',
                    'age' => $user['age'] ?? $assessment['age'] ?? '',
                    'condition_type' => $conditionType,
                    'condition_label' => $funnelInfo['label'],
                    'condition_value' => $funnelInfo['value'],
                    'program_week' => $programWeek,
                    'tier' => $profile['subscription_tier'] ?? '30-day',
                    'days_left' => $daysLeft,
                    'subscription_status' => $subStatus,
                ],
                'body' => [
                    'weight' => $assessment['weight'] ?? $assessment['current_weight'] ?? '',
                    'height' => $assessment['height'] ?? '',
                    'cycle_length' => $profile['cycle_length'] ?? 28,
                    'last_period_date' => $profile['last_period_date'] ?? '',
                    'allergies' => $profile['allergies'] ?? '',
                    'dietary_preferences' => $profile['dietary_preferences'] ?? '',
                    'condition_type' => $conditionType
                ],
                'cycle' => $cycleData,
                'plan' => $plan
            ]);
            break;

        case 'weekly_plans':
            // ... same weekly_plans logic ...
            $today = new DateTime();
            $weeklyPlans = [];
            for ($i = 0; $i < 7; $i++) {
                $date = clone $today;
                $date->modify("+$i day");
                $dateStr = $date->format('Y-m-d');
                $sql = "SELECT plan_data FROM daily_plans WHERE user_id = :uid AND plan_date = :date";
                $planRow = $db->fetch($sql, [':uid' => $userId, ':date' => $dateStr]);
                $weeklyPlans[] = [
                    'date' => $dateStr,
                    'display_date' => $date->format('D, M j'),
                    'is_today' => $i === 0,
                    'plan' => $planRow ? json_decode($planRow['plan_data'], true) : null
                ];
            }
            echo json_encode(['success' => true, 'plans' => $weeklyPlans]);
            break;

        case 'proactive_gen':
            $days = intval($_GET['days'] ?? 3);
            $force = isset($_GET['force']) && $_GET['force'] === 'true';

            if ($force) {
                // Delete today's plan to force regeneration
                $dateStr = date('Y-m-d');
                $db->query(
                    "DELETE FROM daily_plans WHERE user_id = :uid AND plan_date = :date",
                    [':uid' => $userId, ':date' => $dateStr]
                );
            }

            $results = $planner->ensurePlansExist($userId, $days);
            echo json_encode(['success' => true, 'results' => $results]);
            break;

        case 'regenerate_plan':
            $dateStr = $_GET['date'] ?? date('Y-m-d');
            // Delete existing
            $db->query(
                "DELETE FROM daily_plans WHERE user_id = :uid AND plan_date = :date",
                [':uid' => $userId, ':date' => $dateStr]
            );
            // Generate new
            $newPlan = $planner->generateDailyPlan($userId, $dateStr, 'manual_regen');
            echo json_encode(['success' => true, 'plan' => $newPlan]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'API Error: ' . $e->getMessage()]);
}
?>