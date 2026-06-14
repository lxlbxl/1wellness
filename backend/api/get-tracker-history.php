<?php
/**
 * Returns tracker readings for chart display (last N days, default 14).
 *
 * GET ?days=14&metrics=mood,energy,sleep_hrs
 *
 * Response: { success, dates[], series: { metric_key: [values...] } }
 * Values are null for days with no reading.
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

$days         = min(90, max(7, (int)($_GET['days'] ?? 14)));
$metricsParam = trim($_GET['metrics'] ?? '');
$user         = $auth->getCurrentUser();
$userId       = $user['user_id'];
$db           = Database::getInstance();

// Build date axis
$dates = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $dates[] = date('Y-m-d', strtotime("-{$i} days"));
}

// Determine which metrics to return
$allowedKeys = [];
if ($metricsParam) {
    foreach (explode(',', $metricsParam) as $k) {
        $clean = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($k)));
        if ($clean) $allowedKeys[] = $clean;
    }
}

$since = $dates[0];
$rows  = $db->fetchAll(
    "SELECT metric_type, metric_value, DATE(logged_at) AS log_date
     FROM user_tracking
     WHERE user_id = ? AND DATE(logged_at) >= ?
     ORDER BY logged_at ASC",
    [$userId, $since]
) ?: [];

// Build lookup: metric → date → value
$lookup = [];
foreach ($rows as $r) {
    $lookup[$r['metric_type']][$r['log_date']] = (float)$r['metric_value'];
}

// Filter to requested metrics (or all if none specified)
$metricKeys = $allowedKeys ?: array_keys($lookup);

$series = [];
foreach ($metricKeys as $key) {
    $series[$key] = array_map(
        fn($d) => $lookup[$key][$d] ?? null,
        $dates
    );
}

// Latest values (for the current-value card display)
$latest = [];
foreach ($metricKeys as $key) {
    foreach (array_reverse($dates) as $d) {
        if (isset($lookup[$key][$d])) {
            $latest[$key] = $lookup[$key][$d];
            break;
        }
    }
}

echo json_encode([
    'success' => true,
    'dates'   => $dates,
    'series'  => $series,
    'latest'  => $latest,
]);
