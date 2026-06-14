<?php

/**
 * Payment Reconciliation Worker (notification-system plan §6 Fix A3)
 *
 * Daily backstop for missed/failed Flutterwave webhooks: pulls the last 48h
 * of successful transactions from the Flutterwave API, diffs them against
 * the local sales ledger by tx_ref, and processes any misses through the
 * same AutomationOrchestrator path the webhook uses (idempotent).
 *
 * Recovered sales are logged with source='reconciliation' so the Admin
 * Payment Integrity panel surfaces them — recurring recoveries mean the
 * webhook is silently failing and needs attention.
 *
 * Cron: 15 2 * * * php /path/to/backend/cron/reconcile_payments.php
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/classes/Database.php';
require_once APP_ROOT . '/classes/BaseModel.php';
require_once APP_ROOT . '/classes/Settings.php';
require_once APP_ROOT . '/classes/PaymentIntegrity.php';

set_time_limit(280);

echo '[' . date('Y-m-d H:i:s') . "] Starting payment reconciliation...\n";

$integrity = new PaymentIntegrity();

$from = date('Y-m-d', strtotime('-2 days'));
$to = date('Y-m-d', strtotime('+1 day')); // inclusive window

$transactions = $integrity->listTransactions($from, $to);
echo 'Fetched ' . count($transactions) . " successful transactions from Flutterwave ($from → $to)\n";

if (empty($transactions)) {
    // Distinguish "no sales" from "API/credentials problem" in the log.
    $integrity->log('reconciliation', 'ok', [
        'detail' => ['window' => "$from..$to", 'transactions' => 0, 'recovered' => 0],
    ]);
    echo "Nothing to reconcile.\n";
    exit;
}

$recovered = 0;
$errors = 0;

foreach ($transactions as $tx) {
    $txRef = $tx['tx_ref'] ?? null;
    $txId = $tx['id'] ?? null;
    if (!$txRef || !$txId) {
        continue;
    }
    if (($tx['status'] ?? '') !== 'successful') {
        continue;
    }
    if ($integrity->saleExists($txRef, $txId)) {
        continue; // already recorded (normal case)
    }

    echo "MISSED sale detected: $txRef (id $txId) — recovering... ";

    try {
        $meta = is_array($tx['meta'] ?? null) ? $tx['meta'] : [];
        list($funnel, $funnelExact) = PaymentIntegrity::resolveFunnel(
            $meta,
            $txRef . ' ' . ($tx['narration'] ?? '')
        );
        $orderData = PaymentIntegrity::buildOrderData($tx, $meta, $funnel);

        if (empty($orderData['email'])) {
            throw new Exception('no customer email on transaction');
        }

        require_once APP_ROOT . '/classes/User.php';
        require_once APP_ROOT . '/classes/Mailer.php';
        require_once APP_ROOT . '/classes/AIOrchestrator.php';
        require_once APP_ROOT . '/classes/MealPlanner.php';
        require_once APP_ROOT . '/classes/AutomationOrchestrator.php';

        $orchestrator = new AutomationOrchestrator();
        $result = $orchestrator->handlePurchase(
            $orderData,
            ['pcos_type' => $meta['pcos_type'] ?? ($meta['type'] ?? 'General')],
            $funnel
        );

        $integrity->log('reconciliation', 'recovered', [
            'tx_ref' => $txRef,
            'transaction_id' => $txId,
            'amount' => $orderData['amount'],
            'currency' => $orderData['currency'],
            'session_id' => $orderData['session_id'],
            'funnel' => $funnel,
            'detail' => [
                'funnel_from_meta' => $funnelExact,
                'orchestrator_success' => (bool) ($result['success'] ?? false),
            ],
        ]);
        $recovered++;
        echo "done\n";
    } catch (Exception $e) {
        $errors++;
        echo 'FAILED: ' . $e->getMessage() . "\n";
        $integrity->log('reconciliation', 'error', [
            'tx_ref' => $txRef,
            'transaction_id' => $txId,
            'detail' => ['message' => $e->getMessage()],
        ]);
    }
}

// Summary row powers the admin panel's "missed webhooks per day" view and
// alerting: recovered > 0 means the live webhook silently failed.
$integrity->log('reconciliation', 'ok', [
    'detail' => [
        'window' => "$from..$to",
        'transactions' => count($transactions),
        'recovered' => $recovered,
        'errors' => $errors,
    ],
]);

if ($recovered > 0) {
    error_log("ALERT: payment reconciliation recovered $recovered missed sale(s) — Flutterwave webhook may be failing. Check Admin → Payment Integrity.");
}

echo '[' . date('Y-m-d H:i:s') . "] Done. Recovered: $recovered, Errors: $errors\n";
