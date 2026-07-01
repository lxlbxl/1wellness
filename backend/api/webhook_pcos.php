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

// This endpoint is called directly by the customer's browser right after a
// client-side Flutterwave success callback, so a shared-secret header (meant
// for genuine server-to-server calls, like flutterwave-webhook.php) can never
// be legitimately supplied here — it was rejecting 100% of real traffic.
// The only trustworthy check for a browser-originated claim is re-verifying
// the transaction against Flutterwave's own API, so that is now mandatory
// rather than skipped when transaction_id is absent.

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!isset($input['email']) || !isset($input['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields (email, name)']);
    exit;
}

$txRef = $input['tx_ref'] ?? $input['reference'] ?? null;
$txId  = $input['transaction_id'] ?? $input['order_id'] ?? null;

// 1. Idempotency — don't process the same transaction twice
if ($txRef && $integrity->saleExists($txRef, $txId)) {
    echo json_encode(['success' => true, 'duplicate' => true]);
    exit;
}

// 2. Re-verify against Flutterwave API; never trust POST body amount alone.
// A missing transaction_id is rejected outright rather than skipping verification.
if (!$txId) {
    $integrity->log('webhook', 'rejected', ['tx_ref' => $txRef,
        'detail' => ['source' => 'webhook_pcos', 'reason' => 'missing_transaction_id']]);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing transaction_id']);
    exit;
}

$verified = $integrity->verifyTransaction($txId);
if (!$verified) {
    $integrity->log('webhook', 'verify_failed', ['tx_ref' => $txRef, 'transaction_id' => $txId,
        'detail' => ['source' => 'webhook_pcos']]);
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Transaction verification failed']);
    exit;
}
// Confirm amount matches to guard against tampered-amount attacks
if (abs((float)($verified['amount'] ?? 0) - (float)($input['amount'] ?? 0)) > 0.01) {
    $integrity->log('webhook', 'rejected', ['tx_ref' => $txRef, 'transaction_id' => $txId,
        'detail' => ['source' => 'webhook_pcos', 'reason' => 'amount_mismatch',
                     'posted' => $input['amount'], 'verified' => $verified['amount']]]);
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Amount mismatch']);
    exit;
}
// Use verified canonical values
$input['amount']   = $verified['amount'];
$input['currency'] = $verified['currency'] ?? ($input['currency'] ?? 'USD');

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
        'plan_duration' => $planDuration,
        'session_id' => $input['session_id'] ?? null // A/B variant attribution
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
