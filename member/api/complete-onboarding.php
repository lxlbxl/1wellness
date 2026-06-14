<?php
/**
 * Mark a member as onboarded and save profile data from the onboarding flow.
 *
 * POST body (JSON or form):
 *   weight, age, height_cm, condition_subtype, cycle_length, last_period_date,
 *   goal_weight, allergies, dietary_preferences
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/classes/Database.php';
require_once __DIR__ . '/../../backend/classes/MemberAuth.php';

$auth = new MemberAuth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$user   = $auth->getCurrentUser();
$userId = $user['user_id'];
$db     = Database::getInstance();

$weight      = (float)($body['weight']           ?? 0);
$age         = (int)($body['age']                ?? 0);
$heightCm    = (float)($body['height_cm']        ?? 0);
$heightFt    = (float)($body['height_ft']        ?? 0);
$heightIn    = (float)($body['height_in']        ?? 0);
$subtype     = trim($body['condition_subtype']   ?? '');
$cycleLen    = (int)($body['cycle_length']        ?? 28);
$lastPeriod  = trim($body['last_period_date']    ?? '');
$goalWeight  = (float)($body['goal_weight']      ?? 0);
$allergies   = trim($body['allergies']           ?? '');
$dietPrefs   = trim($body['dietary_preferences'] ?? '');

// Resolve height to cm
if ($heightCm <= 0 && ($heightFt > 0 || $heightIn > 0)) {
    $heightCm = round(($heightFt * 12 + $heightIn) * 2.54, 1);
}

// Calculate BMI
$bmi = ($heightCm > 0 && $weight > 0) ? round($weight / (($heightCm / 100) ** 2), 2) : null;

$now = date('Y-m-d H:i:s');

try {
    // Mark user as onboarded
    $userUpdate = ['onboarded_at' => $now, 'updated_at' => $now];
    if ($age > 0)      $userUpdate['age']    = $age;
    if ($weight > 0)   $userUpdate['weight'] = $weight;
    if ($heightCm > 0) $userUpdate['height'] = $heightCm;
    if ($bmi)          $userUpdate['bmi']    = $bmi;

    $db->execute(
        "UPDATE users SET " . implode(', ', array_map(fn($k) => "$k = ?", array_keys($userUpdate)))
        . " WHERE id = ?",
        [...array_values($userUpdate), $userId]
    );

    // Upsert member_profiles
    $profileExists = $db->fetch("SELECT id FROM member_profiles WHERE user_id = ?", [$userId]);
    $profileData = [
        'age'                  => $age ?: null,
        'weight'               => $weight ?: null,
        'height'               => $heightCm ?: null,
        'bmi'                  => $bmi,
        'allergies'            => $allergies,
        'dietary_preferences'  => $dietPrefs,
        'cycle_length'         => $cycleLen,
        'last_period_date'     => $lastPeriod ?: null,
        'updated_at'           => $now,
    ];
    if ($goalWeight > 0) $profileData['goal_weight'] = $goalWeight;
    if ($subtype)        $profileData['condition_subtype'] = $subtype;

    if ($profileExists) {
        $db->execute(
            "UPDATE member_profiles SET "
            . implode(', ', array_map(fn($k) => "$k = ?", array_keys($profileData)))
            . " WHERE user_id = ?",
            [...array_values($profileData), $userId]
        );
    } else {
        $profileData['user_id']              = $userId;
        $profileData['created_at']           = $now;
        $profileData['subscription_tier']    = '30-day';
        $profileData['subscription_status']  = 'active';
        $profileData['start_date']           = date('Y-m-d');
        $profileData['subscription_expiry']  = date('Y-m-d', strtotime('+30 days'));
        $db->insert('member_profiles', $profileData);
    }

    // Award onboarding milestone
    try {
        $db->insert('member_milestones', [
            'user_id'   => $userId,
            'milestone' => 'onboarding_complete',
            'earned_at' => $now,
            'meta'      => json_encode(['subtype' => $subtype]),
        ]);
    } catch (Exception $e) { /* UNIQUE constraint — already awarded */ }

    echo json_encode(['success' => true, 'onboarded_at' => $now]);
} catch (Exception $e) {
    error_log('complete-onboarding.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
