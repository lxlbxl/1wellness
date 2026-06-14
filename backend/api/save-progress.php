<?php
/**
 * Save one answer of an in-progress assessment.
 *
 * POST body (JSON):
 *   session_id  string  — anonymous session token from frontend localStorage
 *   funnel      string  — 'pcos'|'acne'|'weight'|'mens'
 *   step        int     — current question index (0-based)
 *   key         string  — answer field name
 *   value       mixed   — answer value
 *   email       string? — capture once collected
 *   completed   bool?   — true when final submission happens
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true);
$sessionId = trim($body['session_id'] ?? '');
$funnel    = preg_replace('/[^a-z0-9_]/', '', strtolower($body['funnel'] ?? 'pcos'));
$step      = (int)($body['step'] ?? 0);
$key       = trim($body['key'] ?? '');
$value     = $body['value'] ?? null;
$email     = filter_var($body['email'] ?? '', FILTER_SANITIZE_EMAIL) ?: null;
$completed = !empty($body['completed']);

if (!$sessionId || !$key) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing session_id or key']);
    exit;
}

// Sanitise funnel against known values
$allowedFunnels = ['pcos', 'acne', 'weight', 'mens', 'general'];
if (!in_array($funnel, $allowedFunnels, true)) {
    $funnel = 'pcos';
}

try {
    $db  = Database::getInstance();
    $pdo = $db->getConnection();

    $ensureTable = "CREATE TABLE IF NOT EXISTS assessment_progress (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id VARCHAR(100) NOT NULL,
        funnel     VARCHAR(30)  NOT NULL DEFAULT 'pcos',
        step       SMALLINT     NOT NULL DEFAULT 0,
        answers    TEXT         NOT NULL DEFAULT '{}',
        email      VARCHAR(255),
        completed  TINYINT(1)   NOT NULL DEFAULT 0,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(session_id, funnel)
    )";
    $pdo->exec($ensureTable);

    // Load existing row
    $existing = $db->fetch(
        "SELECT * FROM assessment_progress WHERE session_id = ? AND funnel = ?",
        [$sessionId, $funnel]
    );

    $answers = [];
    if ($existing) {
        $answers = json_decode($existing['answers'] ?? '{}', true) ?: [];
    }
    $answers[$key] = $value;

    $now = date('Y-m-d H:i:s');

    if ($existing) {
        $db->execute(
            "UPDATE assessment_progress
             SET step = ?, answers = ?, email = COALESCE(NULLIF(?, ''), email),
                 completed = ?, updated_at = ?
             WHERE session_id = ? AND funnel = ?",
            [
                max($step, (int)($existing['step'] ?? 0)),
                json_encode($answers),
                $email ?? '',
                $completed ? 1 : (int)($existing['completed'] ?? 0),
                $now,
                $sessionId,
                $funnel,
            ]
        );
    } else {
        $db->insert('assessment_progress', [
            'session_id' => $sessionId,
            'funnel'     => $funnel,
            'step'       => $step,
            'answers'    => json_encode($answers),
            'email'      => $email,
            'completed'  => $completed ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    echo json_encode(['success' => true, 'step' => $step, 'keys_saved' => count($answers)]);
} catch (Exception $e) {
    error_log('save-progress.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
