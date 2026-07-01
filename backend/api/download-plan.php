<?php
/**
 * API Endpoint: Download a previously generated plan PDF.
 *
 * GET /api/download-plan.php?token=...&filename=...
 *
 * The token is produced by generate-plan.php (random 32-hex bin2hex(random_bytes(16)))
 * and the PDF is stored in backend/storage/generated_plans/{token}.pdf, swept on a
 * 2-hour TTL. `filename` only controls the suggested download name and is sanitized
 * before being placed in the Content-Disposition header.
 */

require_once __DIR__ . '/../config/config.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = defined('CORS_ALLOWED_ORIGINS') ? CORS_ALLOWED_ORIGINS : [];
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

$token = $_GET['token'] ?? '';

if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

$path = __DIR__ . '/../storage/generated_plans/' . $token . '.pdf';

if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'This download link has expired. Please contact support.']);
    exit;
}

$filename = $_GET['filename'] ?? 'My_90Day_Protocol.pdf';
// Strip anything that could break out of the header value or the filename itself.
$filename = preg_replace('/[\r\n"\\\\\/]/', '', $filename);
if ($filename === '') {
    $filename = 'My_90Day_Protocol.pdf';
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
