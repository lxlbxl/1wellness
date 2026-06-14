<?php
/**
 * API Endpoint: Generate Plan PDF (all funnels)
 *
 * POST /api/generate-plan.php
 * Body: { email, name, type, assessment: {...} }
 * Returns: PDF binary (application/pdf)
 *
 * Routes to condition-specific generators via ProtocolGeneratorFactory:
 * - pcos  → PcosProtocolGenerator (CycleSync)
 * - acne  → AcneProtocolGenerator (GlowClear)
 * - weight → WeightProtocolGenerator (LeanFlow)
 * - mens  → MensProtocolGenerator (Vitale)
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

// New generator architecture
require_once __DIR__ . '/../classes/ModuleManifest.php';
require_once __DIR__ . '/../classes/RegionProfile.php';
require_once __DIR__ . '/../classes/AbstractProtocolGenerator.php';
require_once __DIR__ . '/../classes/ProtocolGeneratorFactory.php';
require_once __DIR__ . '/../classes/PcosProtocolGenerator.php';
require_once __DIR__ . '/../classes/AcneProtocolGenerator.php';
require_once __DIR__ . '/../classes/WeightProtocolGenerator.php';
require_once __DIR__ . '/../classes/MensProtocolGenerator.php';

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

    // Resolve condition from type/funnel
    $condition = resolveCondition($type, $assessment);

    // Resolve region profile for localization
    $regionProfile = resolveRegionProfile($input);

    // Get condition-specific generator via factory
    $generator = ProtocolGeneratorFactory::for($condition);

    // Generate plan with region context
    $pdfBinary = $generator->generate($assessment, $name, $email, $regionProfile);

    // Brand names per condition
    $brandNames = [
        'pcos' => 'CycleSync',
        'acne' => 'GlowClear',
        'weight' => 'LeanFlow',
        'mens' => 'Vitale',
    ];
    $brand = $brandNames[$condition] ?? '1Wellness';

    $typeLabel = strtoupper($brand);
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

/**
 * Resolve condition from type/funnel name.
 */
function resolveCondition(string $type, array $assessment): string
{
    // Normalize type
    $type = strtolower(trim($type));

    // Direct mapping
    $mapping = [
        'pcos' => 'pcos',
        'cycle-sync' => 'pcos',
        'cyclesync' => 'pcos',
        'acne' => 'acne',
        'glowclear' => 'acne',
        'glow-clear' => 'acne',
        'weight' => 'weight',
        'leanflow' => 'weight',
        'lean-flow' => 'weight',
        'mens' => 'mens',
        'men' => 'mens',
        'vitale' => 'mens',
    ];

    if (isset($mapping[$type])) {
        return $mapping[$type];
    }

    // Check assessment for condition hint
    $condition = $assessment['condition'] ?? $assessment['funnel'] ?? '';
    if (!empty($condition) && isset($mapping[strtolower($condition)])) {
        return $mapping[strtolower($condition)];
    }

    // Default to PCOS for backwards compatibility
    return 'pcos';
}

/**
 * Resolve region profile from request.
 */
function resolveRegionProfile(array $input): array
{
    // If region profile is directly provided
    if (!empty($input['region_profile'])) {
        return $input['region_profile'];
    }

    // Build from user location data
    $country = $input['country'] ?? $input['country_code'] ?? '';
    $city = $input['city'] ?? $input['region_city'] ?? '';
    $cuisinePref = $input['cuisine_pref'] ?? $input['dietary_preference'] ?? '';

    if (empty($country)) {
        // Try to get from user record if email provided
        $email = $input['email'] ?? '';
        if (!empty($email)) {
            try {
                $db = new Database();
                $stmt = $db->getConnection()->prepare("SELECT country_code, country_name, region_city, cuisine_pref, measurement_system FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $country = $user['country_code'] ?? $user['country_name'] ?? '';
                    $city = $user['region_city'] ?? '';
                    $cuisinePref = $user['cuisine_pref'] ?? '';
                }
            } catch (Exception $e) {
                // Silently fail - will use default region
            }
        }
    }

    return [
        'country' => $country,
        'country_code' => $input['country_code'] ?? '',
        'city' => $city,
        'cuisine_pref' => $cuisinePref,
        'measurement_system' => $input['measurement_system'] ?? 'metric',
    ];
}