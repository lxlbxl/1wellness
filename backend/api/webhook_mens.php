<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../classes/Settings.php';
require_once __DIR__ . '/../classes/PaymentIntegrity.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../classes/AIOrchestrator.php';
require_once __DIR__ . '/../classes/MealPlanner.php';
require_once __DIR__ . '/../classes/AutomationOrchestrator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$integrity = new PaymentIntegrity();

$signature = $_SERVER['HTTP_VERIF_HASH'] ?? '';
[$hashOk, $hashReason] = $integrity->checkWebhookHash($signature);
if (!$hashOk) {
    $integrity->log('webhook', 'rejected', ['detail' => ['source' => 'webhook_mens', 'reason' => $hashReason]]);
    http_response_code($hashReason === 'hash_not_configured' ? 503 : 401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!isset($input['email']) || !isset($input['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields (email, name)']);
    exit;
}

$txRef = $input['tx_ref'] ?? $input['reference'] ?? null;
$txId  = $input['transaction_id'] ?? $input['order_id'] ?? null;

if ($txRef && $integrity->saleExists($txRef, $txId)) {
    echo json_encode(['success' => true, 'duplicate' => true]);
    exit;
}

if ($txId) {
    $verified = $integrity->verifyTransaction($txId);
    if (!$verified) {
        $integrity->log('webhook', 'verify_failed', ['tx_ref' => $txRef, 'transaction_id' => $txId,
            'detail' => ['source' => 'webhook_mens']]);
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Transaction verification failed']);
        exit;
    }
    if (abs((float)($verified['amount'] ?? 0) - (float)($input['amount'] ?? 0)) > 0.01) {
        $integrity->log('webhook', 'rejected', ['tx_ref' => $txRef, 'transaction_id' => $txId,
            'detail' => ['source' => 'webhook_mens', 'reason' => 'amount_mismatch',
                         'posted' => $input['amount'], 'verified' => $verified['amount']]]);
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Amount mismatch']);
        exit;
    }
    $input['amount']   = $verified['amount'];
    $input['currency'] = $verified['currency'] ?? ($input['currency'] ?? 'USD');
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
        'plan_duration' => $planDuration,
        'session_id' => $input['session_id'] ?? null // A/B variant attribution
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
