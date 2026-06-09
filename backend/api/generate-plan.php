<?php
/**
 * API Endpoint: Generate Plan PDF (all funnels)
 *
 * POST /api/generate-plan.php
 * Body: { email, name, type, assessment: {...} }
 * Returns: PDF binary (application/pdf)
 */

// CORS headers
require_once __DIR__ . '/../config/config.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : [];
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// Bootstrap
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Settings.php';
require_once __DIR__ . '/../classes/AIOrchestrator.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../classes/PcosGenerator.php';

// Parse request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$name = $input['name'] ?? 'Friend';
$email = $input['email'] ?? '';
$type = $input['type'] ?? 'pcos';
$assessment = $input['assessment'] ?? [];

if (empty($assessment)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing assessment data']);
    exit;
}

try {
    set_time_limit(180);
    ini_set('memory_limit', '256M');

    $generator = new PcosGenerator();
    $pdfBinary = $generator->generate($assessment, $name, $email);

    $typeLabel = strtoupper($type);
    $filename = preg_replace('/\s+/', '_', $name) . "_90Day_{$typeLabel}_Protocol.pdf";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfBinary));
    echo $pdfBinary;

} catch (Exception $e) {
    error_log('[generate-plan] Error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to generate plan. Please try again.']);
}
