<?php
/**
 * Weekly review data for the engagement loop.
 *
 * GET  — returns last 7 days' summary: tracker averages, compliance %, streak, milestones
 * POST — generates and stores this week's AI-written review summary
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/classes/Database.php';
require_once __DIR__ . '/../../backend/classes/MemberAuth.php';
require_once __DIR__ . '/../../backend/classes/StreakManager.php';

$auth = new MemberAuth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user   = $auth->getCurrentUser();
$userId = $user['user_id'];
$db     = Database::getInstance();
$sm     = new StreakManager();

$since7 = date('Y-m-d', strtotime('-6 days'));
$today  = date('Y-m-d');

// --- Tracker averages (last 7 days) ---
$trackerRows = $db->fetchAll(
    "SELECT metric_type, AVG(metric_value) AS avg_val, COUNT(*) AS days_logged
     FROM user_tracking
     WHERE user_id = ? AND DATE(logged_at) >= ?
     GROUP BY metric_type",
    [$userId, $since7]
) ?: [];

$trackerSummary = [];
foreach ($trackerRows as $r) {
    $trackerSummary[$r['metric_type']] = [
        'avg'         => round((float)$r['avg_val'], 1),
        'days_logged' => (int)$r['days_logged'],
    ];
}

// --- Activity compliance (last 7 days) ---
$activityRows = $db->fetchAll(
    "SELECT status, COUNT(*) AS c FROM activity_logs
     WHERE user_id = ? AND plan_date >= ?
     GROUP BY status",
    [$userId, $since7]
) ?: [];

$actTotals = ['completed' => 0, 'missed' => 0, 'pending' => 0];
foreach ($activityRows as $r) {
    $s = $r['status'] ?? 'pending';
    $actTotals[$s] = (int)$r['c'];
}
$totalActs   = array_sum($actTotals);
$compliance  = $totalActs > 0 ? round($actTotals['completed'] / $totalActs * 100) : 0;

// --- Streak ---
$streak = $sm->getStreak($userId);

// --- Milestones earned this week ---
$milestones = $db->fetchAll(
    "SELECT milestone, earned_at FROM member_milestones
     WHERE user_id = ? AND DATE(earned_at) >= ?
     ORDER BY earned_at DESC",
    [$userId, $since7]
) ?: [];

// --- Days logged count ---
$daysLogged = 0;
try {
    $dlRow = $db->fetch(
        "SELECT COUNT(DISTINCT DATE(logged_at)) AS c FROM user_tracking
         WHERE user_id = ? AND DATE(logged_at) >= ?",
        [$userId, $since7]
    );
    $daysLogged = (int)($dlRow['c'] ?? 0);
} catch (Exception $e) {}

echo json_encode([
    'success'         => true,
    'period'          => ['from' => $since7, 'to' => $today],
    'streak'          => $streak,
    'days_logged'     => $daysLogged,
    'compliance_pct'  => $compliance,
    'tracker_summary' => $trackerSummary,
    'activity_totals' => $actTotals,
    'milestones'      => $milestones,
]);
