<?php
/**
 * Log one or more tracker metrics for the authenticated member.
 *
 * POST body (JSON):
 *   metrics: { key: value, ... }   e.g. { "mood": 4, "sleep_hrs": 7, "hydration": 2.1 }
 *   date:    "YYYY-MM-DD"          optional, defaults to today
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true) ?: [];
$metrics = $body['metrics'] ?? [];
$date    = $body['date'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

if (empty($metrics) || !is_array($metrics)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No metrics provided']);
    exit;
}

$user   = $auth->getCurrentUser();
$userId = $user['user_id'];
$db     = Database::getInstance();
$saved  = 0;

foreach ($metrics as $key => $value) {
    $key   = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$key));
    $value = (float)$value;

    if (!$key) continue;

    // Upsert: one reading per metric per day
    $existing = $db->fetch(
        "SELECT id FROM user_tracking
         WHERE user_id = ? AND metric_type = ? AND DATE(logged_at) = ?",
        [$userId, $key, $date]
    );

    $loggedAt = $date . ' ' . date('H:i:s');

    if ($existing) {
        $db->execute(
            "UPDATE user_tracking SET metric_value = ?, logged_at = ? WHERE id = ?",
            [$value, $loggedAt, $existing['id']]
        );
    } else {
        $db->insert('user_tracking', [
            'user_id'      => $userId,
            'metric_type'  => $key,
            'metric_value' => $value,
            'unit'         => '',
            'logged_at'    => $loggedAt,
        ]);
    }
    $saved++;
}

// Update streak
$streak = 0;
try {
    $sm     = new StreakManager();
    $streak = $sm->recordActivity($userId);
} catch (Exception $e) { /* non-fatal */ }

echo json_encode(['success' => true, 'saved' => $saved, 'streak' => $streak]);
