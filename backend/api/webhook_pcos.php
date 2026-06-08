<?php
header('Content-Type: application/json');
require_once '../admin/auth.php'; // Ensure database connection
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Settings.php';
require_once '../classes/Mailer.php';
require_once '../classes/AIOrchestrator.php';
require_once '../classes/MealPlanner.php';
require_once '../classes/AutomationOrchestrator.php';

// Allow CORS if needed
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON Input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Fallback to POST vars
    $input = $_POST;
}

// Validate Input
if (!isset($input['email']) || !isset($input['name'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields (email, name)']);
    exit;
}

try {
    $orchestrator = new AutomationOrchestrator();

    // Prepare Customer Data
    $product = $input['product'] ?? 'PCOS Bundle';

    // Determine plan duration from product name or explicit field
    $planDuration = intval($input['plan_duration'] ?? 0);
    if ($planDuration === 0) {
        if (stripos($product, '90') !== false) {
            $planDuration = 90;
        } elseif (stripos($product, '30') !== false) {
            $planDuration = 30;
        } else {
            $amount = floatval($input['amount'] ?? 0);
            $planDuration = ($amount > 100) ? 90 : 30; // USD 197 vs 97
        }
    }

    $orderData = [
        'email' => $input['email'],
        'name' => $input['name'],
        'transaction_id' => $input['transaction_id'] ?? $input['order_id'] ?? null,
        'tx_ref' => $input['tx_ref'] ?? $input['reference'] ?? null,
        'amount' => $input['amount'] ?? 0,
        'currency' => $input['currency'] ?? 'USD',
        'product' => $product,
        'plan_duration' => $planDuration
    ];

    // Prepare Assessment Data
    $assessmentData = [
        'pcos_type' => $input['pcos_type'] ?? 'General',
        'cycle_length' => $input['cycle_length'] ?? 28,
        'last_period_date' => $input['last_period_date'] ?? date('Y-m-d'),
        'allergies' => $input['allergies'] ?? 'None'
    ];

    // Handle Purchase (Creates/Updates User + Logs Sale + Activity)
    $result = $orchestrator->handlePCOSPurchase($orderData, $assessmentData);

    echo json_encode($result);
} catch (Exception $e) {
    error_log("Webhook Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
