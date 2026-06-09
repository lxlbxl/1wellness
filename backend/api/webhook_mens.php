<?php
header('Content-Type: application/json');
require_once '../admin/auth.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Settings.php';
require_once '../classes/Mailer.php';
require_once '../classes/AIOrchestrator.php';
require_once '../classes/MealPlanner.php';
require_once '../classes/AutomationOrchestrator.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

if (!isset($input['email']) || !isset($input['name'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields (email, name)']);
    exit;
}

try {
    $orchestrator = new AutomationOrchestrator();
    $product = $input['product'] ?? 'Men\'s Vitality Bundle';
    $planDuration = intval($input['plan_duration'] ?? 0);
    if ($planDuration === 0) {
        if (stripos($product, '90') !== false) $planDuration = 90;
        elseif (stripos($product, '30') !== false) $planDuration = 30;
        else $planDuration = (floatval($input['amount'] ?? 0) > 100) ? 90 : 30;
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

    $assessmentData = [
        'mens_type' => $input['mens_type'] ?? 'General',
        'age' => $input['age'] ?? 30,
        'allergies' => $input['allergies'] ?? 'None'
    ];

    $result = $orchestrator->handleMensPurchase($orderData, $assessmentData);
    echo json_encode($result);
} catch (Exception $e) {
    error_log("Webhook Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
