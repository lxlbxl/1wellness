<?php
/**
 * POST /backend/api/track-event.php — funnel event ingestion
 *
 * Public endpoint used by js/tracking.js. Accepts the fixed funnel event
 * taxonomy, attributes events to the session's live A/B assignments, and
 * writes to funnel_tracking.
 *
 * Body (JSON):
 * {
 *   "session_id": "sess_...",          // required
 *   "funnel": "pcos|acne|weight|mens", // required
 *   "event": "view|assessment_start|assessment_complete|results_view|plan_select|checkout_init",
 *   "step": "optional step label",
 *   "url": "page url",
 *   "email": "optional",
 *   "metadata": { ... }                // optional, JSON-encoded into metadata column
 * }
 *
 * Notes:
 * - `purchase` is NOT accepted here. Purchases are logged server-side
 *   from the Flutterwave webhook handlers only.
 * - Client must gate calls on GDPR analytics consent (tracking.js does).
 * - Bot filtering: UA patterns + assessment_start arriving <2s after view.
 *
 * Responses: {"success":true,"recorded":N} | {"success":false,"error":"..."}
 */

header('Content-Type: application/json');

// CORS: reflect allowed origins (funnel subdomains)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
require_once __DIR__ . '/../config/config.php';
if ($origin && defined('CORS_ALLOWED_ORIGINS') && in_array($origin, CORS_ALLOWED_ORIGINS)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ExperimentManager.php';

const CLIENT_EVENTS = ['view', 'assessment_start', 'assessment_complete', 'results_view', 'plan_select', 'checkout_init'];

function fail($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function ok($data = [])
{
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$sessionId = trim((string) ($input['session_id'] ?? ''));
$funnel = preg_replace('/[^a-z]/', '', strtolower((string) ($input['funnel'] ?? '')));
$event = trim((string) ($input['event'] ?? ''));

if ($sessionId === '' || !preg_match('/^[A-Za-z0-9_\-\.]{8,100}$/', $sessionId)) {
    fail('Invalid session_id');
}
if (!in_array($funnel, ExperimentManager::FUNNELS)) {
    fail('Invalid funnel');
}
if (!in_array($event, CLIENT_EVENTS)) {
    fail('Invalid event. Allowed: ' . implode(', ', CLIENT_EVENTS) . ' (purchase is server-side only)');
}

// ---- Bot filter: user agent ----
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|phantom|selenium|lighthouse|pingdom|uptime|curl|wget|python-requests/i', $ua)) {
    ok(['recorded' => 0, 'filtered' => 'bot']);
}

try {
    $db = Database::getInstance();
    if ($db->isFileStorage()) {
        ok(['recorded' => 0, 'filtered' => 'no_database']);
    }
    $manager = new ExperimentManager();

    $now = date('Y-m-d H:i:s');
    $url = mb_substr((string) ($input['url'] ?? ''), 0, 500);
    $step = mb_substr((string) ($input['step'] ?? $event), 0, 100);
    $email = mb_substr((string) ($input['email'] ?? ''), 0, 255) ?: null;
    $metadata = isset($input['metadata']) && is_array($input['metadata'])
        ? json_encode($input['metadata'], JSON_UNESCAPED_SLASHES)
        : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // ---- Bot filter: implausible timing (assessment_start < 2s after view) ----
    if ($event === 'assessment_start') {
        $lastView = $db->fetch(
            "SELECT created_at FROM funnel_tracking
             WHERE session_id = :s AND event_type = 'view' ORDER BY id DESC LIMIT 1",
            [':s' => $sessionId]
        );
        if ($lastView && (time() - strtotime($lastView['created_at'])) < 2) {
            ok(['recorded' => 0, 'filtered' => 'timing']);
        }
    }

    // ---- Dedup: view + step events once per session per page per day ----
    $dedupEvents = ['view', 'results_view'];
    if (in_array($event, $dedupEvents)) {
        $dupe = $db->fetch(
            "SELECT id FROM funnel_tracking
             WHERE session_id = :s AND event_type = :e AND url = :u AND created_at >= :d LIMIT 1",
            [':s' => $sessionId, ':e' => $event, ':u' => $url, ':d' => date('Y-m-d') . ' 00:00:00']
        );
        if ($dupe) {
            ok(['recorded' => 0, 'deduped' => true]);
        }
    } else {
        // Funnel-step events: once per session (first touch counts)
        $dupe = $db->fetch(
            "SELECT id FROM funnel_tracking
             WHERE session_id = :s AND event_type = :e AND funnel_name = :f LIMIT 1",
            [':s' => $sessionId, ':e' => $event, ':f' => $funnel]
        );
        if ($dupe) {
            ok(['recorded' => 0, 'deduped' => true]);
        }
    }

    // ---- Attribution: stamp event with the session's live assignments ----
    $assignments = $manager->getSessionAssignments($sessionId);
    $rows = [];
    $base = [
        'session_id' => $sessionId,
        'funnel_name' => $funnel,
        'step_name' => $step,
        'event_type' => $event,
        'email' => $email,
        'metadata' => $metadata,
        'url' => $url,
        'ip_address' => $ip,
        'user_agent' => mb_substr($ua, 0, 500),
        'created_at' => $now,
    ];

    $stamped = false;
    foreach ($assignments as $a) {
        if ($a['funnel_name'] !== $funnel) {
            continue; // assignments from other funnels don't claim this event
        }
        $rows[] = array_merge($base, [
            'experiment_id' => (int) $a['experiment_id'],
            'variant_id' => (int) $a['variant_id'],
        ]);
        $stamped = true;
    }
    if (!$stamped) {
        $rows[] = $base;
    }

    foreach ($rows as $row) {
        $db->insert('funnel_tracking', $row);
    }

    ok(['recorded' => count($rows)]);
} catch (Exception $e) {
    error_log('track-event error: ' . $e->getMessage());
    fail('Internal error', 500);
}
