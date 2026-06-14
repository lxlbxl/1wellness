<?php
/**
 * Retrieve saved assessment progress for a session.
 *
 * GET ?session_id=X&funnel=pcos
 *
 * Returns: {success, step, answers, completed, updated_at}
 * Returns 404 if no progress found (fresh start).
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$sessionId = trim($_GET['session_id'] ?? '');
$funnel    = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['funnel'] ?? 'pcos'));

if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing session_id']);
    exit;
}

try {
    $db  = Database::getInstance();
    $row = $db->fetch(
        "SELECT step, answers, completed, updated_at
         FROM assessment_progress WHERE session_id = ? AND funnel = ?",
        [$sessionId, $funnel]
    );

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No progress found']);
        exit;
    }

    echo json_encode([
        'success'    => true,
        'step'       => (int)$row['step'],
        'answers'    => json_decode($row['answers'] ?? '{}', true) ?: [],
        'completed'  => (bool)$row['completed'],
        'updated_at' => $row['updated_at'],
    ]);
} catch (Exception $e) {
    error_log('get-progress.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
