<?php

/**
 * Flutterwave Server Webhook (notification-system plan §6 Fix A2)
 *
 * The trusted entry point for purchase events. Unlike webhook_<funnel>.php
 * (which sit behind admin session auth and only ever receive browser posts),
 * this endpoint authenticates Flutterwave itself via the `verif-hash` header
 * (Admin → Settings → Payment → Webhook Secret Hash; set the same value in
 * the Flutterwave dashboard, Settings → Webhooks).
 *
 * Flow: verify hash → parse charge.completed → re-verify transaction against
 * the Flutterwave API → idempotent purchase handling via
 * AutomationOrchestrator (sale row, user provisioning, server-side
 * funnel_tracking purchase event with session attribution, outbound
 * webhooks). Every request — accepted or rejected — lands in
 * payment_webhook_log for the Admin Payment Integrity panel.
 *
 * Responses: 200 on processed/duplicate (stops Flutterwave retries),
 * 401/400 on auth/payload problems, 500 on transient failure (Flutterwave
 * will retry).
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../classes/Settings.php';
require_once __DIR__ . '/../classes/PaymentIntegrity.php';

$respond = function ($code, $body) {
    http_response_code($code);
    echo json_encode($body);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(405, ['status' => 'error', 'message' => 'Method not allowed']);
}

$integrity = new PaymentIntegrity();

// --- 1. Authenticate the sender (fail closed) -----------------------------
$signature = $_SERVER['HTTP_VERIF_HASH'] ?? '';
list($hashOk, $hashReason) = $integrity->checkWebhookHash($signature);
if (!$hashOk) {
    $integrity->log('webhook', 'rejected', ['detail' => ['reason' => $hashReason]]);
    // 503 when we are misconfigured (so the problem is visible upstream),
    // 401 when the caller is unauthenticated.
    $respond($hashReason === 'hash_not_configured' ? 503 : 401,
        ['status' => 'error', 'message' => $hashReason]);
}

// --- 2. Parse payload ------------------------------------------------------
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $integrity->log('webhook', 'rejected', ['detail' => ['reason' => 'invalid_json']]);
    $respond(400, ['status' => 'error', 'message' => 'Invalid JSON']);
}

$event = $payload['event'] ?? ($payload['event.type'] ?? '');
$data = $payload['data'] ?? [];

// Only successful charges create purchases; acknowledge everything else so
// Flutterwave does not retry events we intentionally ignore.
$isCharge = in_array($event, ['charge.completed', 'charge.success'], true)
    || (isset($data['status']) && $event === '');
if (!$isCharge || (($data['status'] ?? '') !== 'successful')) {
    $integrity->log('webhook', 'received', [
        'event' => $event ?: 'unknown',
        'tx_ref' => $data['tx_ref'] ?? null,
        'detail' => ['reason' => 'ignored_event_or_status', 'status' => $data['status'] ?? null],
    ]);
    $respond(200, ['status' => 'ignored']);
}

$txId = $data['id'] ?? null;
$txRef = $data['tx_ref'] ?? null;
if (!$txId || !$txRef) {
    $integrity->log('webhook', 'rejected', ['event' => $event, 'detail' => ['reason' => 'missing_tx_identifiers']]);
    $respond(400, ['status' => 'error', 'message' => 'Missing transaction identifiers']);
}

// --- 3. Idempotency short-circuit -------------------------------------------
if ($integrity->saleExists($txRef, $txId)) {
    $integrity->log('webhook', 'duplicate', [
        'event' => $event, 'tx_ref' => $txRef, 'transaction_id' => $txId,
    ]);
    $respond(200, ['status' => 'duplicate']);
}

// --- 4. Never trust the payload: re-verify against the Flutterwave API ------
$verified = $integrity->verifyTransaction($txId);
if (!$verified) {
    $integrity->log('webhook', 'verify_failed', [
        'event' => $event, 'tx_ref' => $txRef, 'transaction_id' => $txId,
        'detail' => ['reason' => 'api_verification_failed_or_not_successful'],
    ]);
    // 500 → Flutterwave retries; transient API failures self-heal, and the
    // daily reconciliation cron is the final backstop.
    $respond(500, ['status' => 'error', 'message' => 'Verification failed']);
}
if (($verified['tx_ref'] ?? '') !== $txRef) {
    $integrity->log('webhook', 'rejected', [
        'event' => $event, 'tx_ref' => $txRef, 'transaction_id' => $txId,
        'detail' => ['reason' => 'tx_ref_mismatch', 'verified_ref' => $verified['tx_ref'] ?? null],
    ]);
    $respond(400, ['status' => 'error', 'message' => 'Transaction reference mismatch']);
}

// --- 5. Map to a purchase and hand to the orchestrator ----------------------
$meta = $verified['meta'] ?? ($data['meta'] ?? []);
if (!is_array($meta)) {
    $meta = [];
}
list($funnel, $funnelExact) = PaymentIntegrity::resolveFunnel(
    $meta,
    ($txRef ?? '') . ' ' . ($verified['narration'] ?? '') . ' ' . json_encode($verified['customer'] ?? [])
);
$orderData = PaymentIntegrity::buildOrderData($verified, $meta, $funnel);

if (empty($orderData['email'])) {
    $integrity->log('webhook', 'error', [
        'event' => $event, 'tx_ref' => $txRef, 'transaction_id' => $txId, 'funnel' => $funnel,
        'detail' => ['reason' => 'no_customer_email'],
    ]);
    $respond(200, ['status' => 'error', 'message' => 'No customer email on transaction']);
}

try {
    require_once __DIR__ . '/../classes/User.php';
    require_once __DIR__ . '/../classes/Mailer.php';
    require_once __DIR__ . '/../classes/AIOrchestrator.php';
    require_once __DIR__ . '/../classes/MealPlanner.php';
    require_once __DIR__ . '/../classes/AutomationOrchestrator.php';

    $orchestrator = new AutomationOrchestrator();
    $assessmentData = [
        'pcos_type' => $meta['pcos_type'] ?? ($meta['type'] ?? 'General'),
    ];
    $result = $orchestrator->handlePurchase($orderData, $assessmentData, $funnel);

    $integrity->log('webhook', 'processed', [
        'event' => $event,
        'tx_ref' => $txRef,
        'transaction_id' => $txId,
        'amount' => $orderData['amount'],
        'currency' => $orderData['currency'],
        'session_id' => $orderData['session_id'],
        'funnel' => $funnel,
        'detail' => [
            'funnel_from_meta' => $funnelExact,
            'order_bump' => $orderData['order_bump'],
            'orchestrator_success' => (bool) ($result['success'] ?? false),
        ],
    ]);

    $respond(200, ['status' => 'success']);
} catch (Exception $e) {
    error_log('flutterwave-webhook: ' . $e->getMessage());
    $integrity->log('webhook', 'error', [
        'event' => $event, 'tx_ref' => $txRef, 'transaction_id' => $txId, 'funnel' => $funnel,
        'detail' => ['reason' => 'exception', 'message' => $e->getMessage()],
    ]);
    $respond(500, ['status' => 'error', 'message' => 'Processing failed']);
}
