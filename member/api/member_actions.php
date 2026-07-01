<?php
// Start output buffering to capture any unwanted PHP warnings/notices
ob_start();

require_once '../../backend/config/config.php';
require_once '../../backend/classes/Database.php';
require_once '../../backend/classes/MemberAuth.php';
require_once '../../backend/classes/MealPlanner.php';
require_once '../../backend/classes/ActivityLogger.php';

// Prepare strict JSON response
header('Content-Type: application/json');
$allowedOrigins = ['http://localhost:5173', 'http://localhost:8080', 'http://localhost:3210'];
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit;

$response = ['success' => false, 'error' => 'Unknown error'];

try {
    $auth = new MemberAuth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Unauthorized');
    }

    $user = $auth->getCurrentUser();
    $userId = $user['user_id'];
    $db = Database::getInstance();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_hydration':
            $liters = floatval($_POST['liters'] ?? 0);
            $date = date('Y-m-d');

            $plan = $db->fetch("SELECT id, plan_data FROM daily_plans WHERE user_id = :uid AND plan_date = :date", [':uid' => $userId, ':date' => $date]);
            if ($plan) {
                $data = json_decode($plan['plan_data'], true);
                $data['hydration'] = $liters;
                $db->update('daily_plans', ['plan_data' => json_encode($data)], "id = :id", [':id' => $plan['id']]);
            } else {
                $data = ['hydration' => $liters];
                $db->insert('daily_plans', [
                    'user_id' => $userId,
                    'plan_date' => $date,
                    'plan_data' => json_encode($data)
                ]);
            }
            $response = ['success' => true, 'liters' => $liters];
            break;

        case 'toggle_supplement':
            $suppId = $_POST['supp_id'] ?? '';
            $completed = ($_POST['completed'] ?? 'false') === 'true';
            $date = date('Y-m-d');

            $plan = $db->fetch("SELECT id, plan_data FROM daily_plans WHERE user_id = :uid AND plan_date = :date", [':uid' => $userId, ':date' => $date]);
            if ($plan) {
                $data = json_decode($plan['plan_data'], true);
                if (!isset($data['supplements']))
                    $data['supplements'] = [];
                $data['supplements'][$suppId] = $completed;
                $db->update('daily_plans', ['plan_data' => json_encode($data)], "id = :id", [':id' => $plan['id']]);
            } else {
                $data = ['supplements' => [$suppId => $completed]];
                $db->insert('daily_plans', [
                    'user_id' => $userId,
                    'plan_date' => $date,
                    'plan_data' => json_encode($data)
                ]);
            }
            $response = ['success' => true, 'completed' => $completed];
            break;

        case 'swap_meal':
            $mealType = $_POST['meal_type'] ?? '';
            $planner = new MealPlanner();
            $result = $planner->swapMeal($userId, $mealType);
            $response = $result;
            break;

        case 'update_profile':
            $firstName = $_POST['first_name'] ?? '';
            $lastName = $_POST['last_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $password = $_POST['password'] ?? '';

            $db->update('users', [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'updated_at' => date('Y-m-d H:i:s')
            ], "id = :id", [':id' => $userId]);

            if (!empty($password)) {
                $db->update('users', [
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT)
                ], "id = :id", [':id' => $userId]);
            }
            $response = ['success' => true];
            break;

        case 'update_body_data':
            $weight = floatval($_POST['weight'] ?? 0);
            $height = floatval($_POST['height'] ?? 0);
            $age = intval($_POST['age'] ?? 0);
            $conditionType = $_POST['condition_type'] ?? $_POST['pcos_type'] ?? 'pcos';
            $conditionType = in_array($conditionType, ['pcos','acne','weight','mens']) ? $conditionType : 'pcos';
            $cycleLength = intval($_POST['cycle_length'] ?? 28);
            $lastPeriod = $_POST['last_period_date'] ?? '';
            $allergies = $_POST['allergies'] ?? '';
            $dietPrefs = $_POST['dietary_preferences'] ?? '';

            // Calculate BMI
            $bmi = ($height > 0) ? ($weight / (($height / 100) ** 2)) : 0;
            $bmi = round($bmi, 2);

            // Check if profile exists
            $profileExists = $db->fetch("SELECT id FROM member_profiles WHERE user_id = :uid", [':uid' => $userId]);

            $profileData = [
                'age' => $age,
                'weight' => $weight,
                'height' => $height,
                'bmi' => $bmi,
                'allergies' => $allergies,
                'dietary_preferences' => $dietPrefs,
                'condition_type' => $conditionType,
                'cycle_length' => $cycleLength,
                'last_period_date' => $lastPeriod,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Set condition-specific label
            if ($conditionType === 'pcos') $profileData['pcos_type'] = $_POST['pcos_type'] ?? 'General';
            if ($conditionType === 'acne') $profileData['skin_type'] = $_POST['skin_type'] ?? 'Combination';

            if ($profileExists) {
                $db->update('member_profiles', $profileData, "user_id = :uid", [':uid' => $userId]);
            } else {
                $db->insert('member_profiles', array_merge($profileData, [
                    'user_id' => $userId,
                    'subscription_tier' => '30-day',
                    'subscription_status' => 'active',
                    'start_date' => date('Y-m-d'),
                    'subscription_expiry' => date('Y-m-d', strtotime('+30 days')),
                    'created_at' => date('Y-m-d H:i:s')
                ]));
            }

            // Update the correct assessment table based on condition
            $assessmentTables = [
                'pcos' => 'pcos_assessments',
                'acne' => 'acne_assessments',
                'weight' => 'weight_assessments',
                'mens' => 'mens_assessments'
            ];
            $assessTable = $assessmentTables[$conditionType] ?? 'pcos_assessments';
            $existsGen = $db->fetch("SELECT id FROM \"{$assessTable}\" WHERE user_id = :uid", [':uid' => $userId]);

            $assessData = [
                'age' => $age,
                'weight' => $weight,
                'height' => $height,
                'bmi' => $bmi,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($existsGen) {
                $db->update($assessTable, $assessData, "user_id = :uid", [':uid' => $userId]);
            } else {
                $db->insert($assessTable, array_merge($assessData, [
                    'id' => 'GEN_' . uniqid(),
                    'user_id' => $userId,
                    'email' => $user['email'],
                    'assessment_type' => 'manual',
                    'created_at' => date('Y-m-d H:i:s')
                ]));
            }

            // Sync age and mark onboarded if not already set
            $userSync = ['updated_at' => date('Y-m-d H:i:s')];
            if ($age > 0) $userSync['age'] = $age;
            $onboardedRow = $db->fetch("SELECT onboarded_at FROM users WHERE id = :id", [':id' => $userId]);
            if (empty($onboardedRow['onboarded_at'])) {
                $userSync['onboarded_at'] = date('Y-m-d H:i:s');
            }
            $db->update('users', $userSync, "id = :id", [':id' => $userId]);

            // Award onboarding milestone
            try {
                $db->insert('member_milestones', [
                    'user_id'   => $userId,
                    'milestone' => 'onboarding_complete',
                    'earned_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) { /* UNIQUE: already awarded */ }

            $response = ['success' => true];
            break;

        case 'verify_renewal':
            $transactionId = $_POST['transaction_id'] ?? '';
            $txRef         = $_POST['tx_ref'] ?? '';
            $tier          = in_array($_POST['tier'] ?? '', ['30-day', '90-day']) ? $_POST['tier'] : '30-day';

            if (!$transactionId) throw new Exception('transaction_id required');

            // Server-side verification via Flutterwave API — never trust the client amount
            require_once '../../backend/classes/Settings.php';
            require_once '../../backend/classes/PaymentIntegrity.php';
            $integrity = new PaymentIntegrity();
            $txData    = $integrity->verifyTransaction($transactionId);
            if (!$txData) {
                throw new Exception('Payment verification failed — please contact support.');
            }

            // Expected price is derived from this user's actual funnel/condition and
            // the admin-configured pricing, not a hardcoded PCOS-only price map -
            // otherwise a pricing change in the admin panel silently breaks renewals
            // for every condition until the hardcoded map is manually updated too.
            $profileRow = $db->fetch("SELECT condition_type FROM member_profiles WHERE user_id = :uid", [':uid' => $userId]);
            $condition  = in_array($profileRow['condition_type'] ?? '', ['pcos', 'acne', 'weight', 'mens'])
                ? $profileRow['condition_type'] : 'pcos';

            $defaultAmounts = ['30-day' => 97, '90-day' => 197];
            $settings       = Settings::getInstance();
            $plans          = $settings->get('payment_plans');
            $expected       = $plans[$condition][$tier]['price'] ?? $defaultAmounts[$tier] ?? 97;
            $verified = (float)($txData['amount'] ?? 0);
            if (abs($verified - $expected) > 1.0) {
                error_log("Renewal amount mismatch: expected $expected, got $verified for user $userId");
                throw new Exception('Amount mismatch — transaction not applied.');
            }

            // Idempotency: don't apply same tx twice
            $dupSale = $db->fetch("SELECT id FROM sales WHERE transaction_id = :tx", [':tx' => $transactionId]);
            if ($dupSale) {
                $response = ['success' => true, 'message' => 'Already applied', 'new_expiry' => null];
                break;
            }

            $daysToAdd = ($tier === '90-day') ? 90 : 30;
            $profile = $db->fetch("SELECT subscription_expiry FROM member_profiles WHERE user_id = :uid", [':uid' => $userId]);
            $currentExpiry = $profile['subscription_expiry'] ? new DateTime($profile['subscription_expiry']) : new DateTime();
            if ($currentExpiry < new DateTime()) $currentExpiry = new DateTime();
            $currentExpiry->modify("+{$daysToAdd} day");
            $newExpiry = $currentExpiry->format('Y-m-d');

            $db->update('member_profiles', [
                'subscription_tier'   => $tier,
                'subscription_expiry' => $newExpiry,
                'subscription_status' => 'active',
                'updated_at'          => date('Y-m-d H:i:s'),
            ], "user_id = :uid", [':uid' => $userId]);

            $db->insert('sales', [
                'id'             => 'RNW_' . uniqid(),
                'user_id'        => $userId,
                'transaction_id' => $transactionId,
                'tx_ref'         => $txRef,
                'email'          => $user['email'],
                'name'           => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                'product_type'   => 'renewal',
                'product_name'   => $tier . ' Plan Renewal',
                'amount'         => $verified,
                'currency'       => $txData['currency'] ?? 'USD',
                'payment_status' => 'completed',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $response = ['success' => true, 'new_expiry' => $newExpiry];
            break;

        case 'mark_activity_done':
            $activityType = $_POST['activity_type'] ?? ''; // e.g., meal_breakfast, meal_lunch, movement, herbal_tea_morning
            $activityName = $_POST['activity_name'] ?? '';
            $scheduledStart = $_POST['scheduled_start'] ?? '';
            $scheduledEnd = $_POST['scheduled_end'] ?? '';
            $planDate = $_POST['plan_date'] ?? date('Y-m-d');

            if (empty($activityType)) {
                $response = ['success' => false, 'error' => 'Activity type is required'];
                break;
            }

            // Check if within time window
            $now = new DateTime();
            $today = $now->format('Y-m-d');
            $currentTime = $now->format('H:i');

            // If the activity is for today and past the end time, mark as missed
            $status = 'completed';
            $canComplete = true;

            if ($planDate === $today && !empty($scheduledEnd)) {
                if ($currentTime > $scheduledEnd) {
                    // Past the time window - check if it's within 30 min grace period
                    $endTime = new DateTime($today . ' ' . $scheduledEnd);
                    $gracePeriod = clone $endTime;
                    $gracePeriod->modify('+30 minutes');

                    if ($now > $gracePeriod) {
                        $canComplete = false;
                        $status = 'missed';
                    }
                }
            } elseif ($planDate < $today) {
                // Past day - cannot complete
                $canComplete = false;
                $status = 'missed';
            }

            // Check if already logged
            $existing = $db->fetch(
                "SELECT id, status FROM activity_logs WHERE user_id = :uid AND plan_date = :date AND activity_type = :type",
                [':uid' => $userId, ':date' => $planDate, ':type' => $activityType]
            );

            if ($existing) {
                // Update existing
                if ($canComplete && $existing['status'] !== 'completed') {
                    $db->update('activity_logs', [
                        'completed_at' => date('Y-m-d H:i:s'),
                        'status' => 'completed',
                        'updated_at' => date('Y-m-d H:i:s')
                    ], "id = :id", [':id' => $existing['id']]);
                    $response = ['success' => true, 'status' => 'completed', 'message' => 'Activity marked as done'];
                } else {
                    $response = ['success' => false, 'error' => 'Activity already logged or time window has passed', 'status' => $existing['status']];
                }
            } else {
                // Insert new
                $db->insert('activity_logs', [
                    'user_id' => $userId,
                    'plan_date' => $planDate,
                    'activity_type' => $activityType,
                    'activity_name' => $activityName,
                    'scheduled_start' => $scheduledStart ?: '00:00',
                    'scheduled_end' => $scheduledEnd ?: '23:59',
                    'completed_at' => $canComplete ? date('Y-m-d H:i:s') : null,
                    'status' => $status,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                $response = [
                    'success' => $canComplete,
                    'status' => $status,
                    'message' => $canComplete ? 'Activity marked as done' : 'Time window has passed - activity marked as missed'
                ];
            }
            break;

        case 'get_activity_logs':
            $startDate = $_POST['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
            $endDate = $_POST['end_date'] ?? date('Y-m-d');

            $logs = $db->fetchAll(
                "SELECT * FROM activity_logs WHERE user_id = :uid AND plan_date BETWEEN :start AND :end ORDER BY plan_date DESC, scheduled_start ASC",
                [':uid' => $userId, ':start' => $startDate, ':end' => $endDate]
            );

            // Calculate compliance stats
            $totalActivities = count($logs);
            $completed = 0;
            $missed = 0;

            foreach ($logs as $log) {
                if ($log['status'] === 'completed')
                    $completed++;
                elseif ($log['status'] === 'missed')
                    $missed++;
            }

            $complianceRate = $totalActivities > 0 ? round(($completed / $totalActivities) * 100) : 0;

            $response = [
                'success' => true,
                'logs' => $logs,
                'stats' => [
                    'total' => $totalActivities,
                    'completed' => $completed,
                    'missed' => $missed,
                    'pending' => $totalActivities - $completed - $missed,
                    'compliance_rate' => $complianceRate
                ]
            ];
            break;

        case 'get_herbal_products':
            $conditionType = $_POST['condition_type'] ?? 'pcos';

            $products = $db->fetchAll(
                "SELECT * FROM herbal_products WHERE is_active = 1 AND (recommended_for LIKE :cond OR recommended_for LIKE '%\"all\"%') ORDER BY name",
                [':cond' => '%"' . $conditionType . '"%']
            );

            $response = ['success' => true, 'products' => $products];
            break;

        case 'cancel_subscription':
            $reason  = trim(strip_tags($_POST['reason'] ?? ''));
            $confirm = $_POST['confirm'] ?? '';
            if ($confirm !== 'yes') {
                $response = ['success' => false, 'error' => 'Confirmation required'];
                break;
            }

            $profile = $db->fetch(
                "SELECT subscription_expiry, subscription_status, start_date FROM member_profiles WHERE user_id = :uid",
                [':uid' => $userId]
            );

            // Check 30-day money-back window
            $startDate  = $profile['start_date'] ?? null;
            $inWindow   = false;
            $windowNote = '';
            if ($startDate) {
                $daysSince = (new DateTime())->diff(new DateTime($startDate))->days;
                $inWindow  = $daysSince <= 30;
                $windowNote = $inWindow
                    ? 'You are within the 30-day guarantee window. A refund request has been created.'
                    : 'Your 30-day guarantee window has passed. Access continues until ' . ($profile['subscription_expiry'] ?? 'end of period') . '.';
            }

            $db->update('member_profiles', [
                'subscription_status' => 'cancelled',
                'updated_at'          => date('Y-m-d H:i:s'),
            ], "user_id = :uid", [':uid' => $userId]);

            // Log cancellation
            $db->insert('activity_logs', [
                'user_id'        => $userId,
                'plan_date'      => date('Y-m-d'),
                'activity_type'  => 'cancellation',
                'activity_name'  => 'Subscription cancelled' . ($reason ? ': ' . substr($reason, 0, 200) : ''),
                'scheduled_start'=> '00:00',
                'scheduled_end'  => '23:59',
                'status'         => 'completed',
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            // Auto-create refund request if in window
            if ($inWindow) {
                try {
                    $db->insert('activity_logs', [
                        'user_id'        => $userId,
                        'plan_date'      => date('Y-m-d'),
                        'activity_type'  => 'refund_request',
                        'activity_name'  => 'Auto-refund: within 30-day guarantee',
                        'scheduled_start'=> '00:00',
                        'scheduled_end'  => '23:59',
                        'status'         => 'pending',
                        'created_at'     => date('Y-m-d H:i:s'),
                    ]);
                } catch (Exception $e) { /* non-fatal */ }
            }

            $response = ['success' => true, 'in_window' => $inWindow, 'note' => $windowNote];
            break;

        case 'refund_request':
            $reason = trim(strip_tags($_POST['reason'] ?? ''));
            $profile = $db->fetch(
                "SELECT start_date, subscription_status FROM member_profiles WHERE user_id = :uid",
                [':uid' => $userId]
            );
            $startDate = $profile['start_date'] ?? null;
            $daysSince = $startDate ? (new DateTime())->diff(new DateTime($startDate))->days : 999;

            if ($daysSince > 30) {
                $response = ['success' => false, 'error' => 'Outside the 30-day money-back window.'];
                break;
            }

            try {
                $db->insert('activity_logs', [
                    'user_id'        => $userId,
                    'plan_date'      => date('Y-m-d'),
                    'activity_type'  => 'refund_request',
                    'activity_name'  => 'Refund request' . ($reason ? ': ' . substr($reason, 0, 200) : ''),
                    'scheduled_start'=> '00:00',
                    'scheduled_end'  => '23:59',
                    'status'         => 'pending',
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) { /* already filed */ }

            $response = ['success' => true, 'message' => 'Refund request submitted. Our team will process it within 3 business days.'];
            break;

        default:
            $response = ['success' => false, 'error' => 'Invalid action'];
            break;
    }
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

// Clear any buffered output (warnings, notices, etc.)
ob_end_clean();

// Output strict JSON
echo json_encode($response);
?>