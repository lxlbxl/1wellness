<?php
require_once 'auth.php';
require_once '../classes/Database.php';
require_once '../classes/Settings.php';
require_once '../classes/PaymentIntegrity.php';

$db = Database::getInstance();
$settingsObj = Settings::getInstance();
$integrity = new PaymentIntegrity();

$message = '';
$error = '';

// "Send test webhook" — exercises the live endpoint with a signed sample
// payload. Verification against the Flutterwave API will (correctly) fail
// for the fake transaction, proving the endpoint authenticates + verifies.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_test_webhook') {
    $hash = trim((string) $settingsObj->get('flutterwave_webhook_hash', ''));
    if ($hash === '') {
        $error = 'Set the Webhook Secret Hash in Settings → Payment first.';
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $endpoint = $scheme . '://' . $_SERVER['HTTP_HOST']
            . preg_replace('#/admin/.*$#', '/api/flutterwave-webhook.php', $_SERVER['SCRIPT_NAME']);
        $payload = json_encode([
            'event' => 'charge.completed',
            'data' => [
                'id' => 999000111,
                'tx_ref' => 'TEST_' . uniqid(),
                'status' => 'successful',
                'amount' => 1,
                'currency' => 'USD',
                'customer' => ['email' => 'test@1wellness.club', 'name' => 'Webhook Test'],
                'meta' => ['session_id' => 'admin_test', 'funnel' => 'pcos', 'plan' => '90-day'],
            ],
        ]);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'verif-hash: ' . $hash],
            CURLOPT_SSL_VERIFYPEER => false, // local/self-signed test environments
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $integrity->log('test', 'received', [
            'tx_ref' => 'admin_test',
            'detail' => ['endpoint' => $endpoint, 'http_code' => $code, 'response' => substr((string) $body, 0, 300)],
        ]);

        if ($body === false) {
            $error = "Test call failed: $curlErr";
        } else {
            $message = "Test webhook sent to $endpoint — HTTP $code, response: " . htmlspecialchars(substr($body, 0, 200))
                . '. Expected: HTTP 500 "Verification failed" (auth passed, fake transaction correctly rejected by API verification).';
        }
    }
}

$hashConfigured = trim((string) $settingsObj->get('flutterwave_webhook_hash', '')) !== '';
$secretConfigured = trim((string) $settingsObj->get('flutterwave_secret_key', '')) !== '';
$environment = $settingsObj->get('flutterwave_environment', 'sandbox');

$counts = $integrity->statusCounts(24);
$logs = $integrity->recentLogs(50);
$unattributed = $integrity->unattributedSales(30);

// Reconciliation recoveries (7 days) — recurring recoveries = failing webhook
$recovered7d = 0;
foreach ($integrity->recentLogs(500) as $row) {
    if (($row['source'] ?? '') === 'reconciliation' && ($row['status'] ?? '') === 'recovered'
        && strtotime($row['created_at'] ?? '') > time() - 7 * 86400) {
        $recovered7d++;
    }
}

$pageTitle = 'Payment Integrity - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Payment Integrity</h2>
        <p class="text-[#6B7C70] mt-1">Flutterwave webhook health, reconciliation, and purchase attribution</p>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="send_test_webhook">
        <button type="submit"
            class="bg-[#2C3E35] text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-[#1f2d26] transition-colors">
            <i class="fas fa-vial mr-2"></i>Send Test Webhook
        </button>
    </form>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 bg-[#E3E8E1] text-[#2C3E35] rounded-lg text-sm"><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-6 p-4 bg-[#FDF1E8] text-[#D97757] rounded-lg text-sm"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Configuration status -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <div class="luxury-card p-6">
        <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Webhook Secret Hash</p>
        <?php if ($hashConfigured): ?>
            <p class="text-[#2C3E35] font-medium"><i class="fas fa-check-circle text-green-600 mr-2"></i>Configured</p>
        <?php else: ?>
            <p class="text-[#D97757] font-medium"><i class="fas fa-exclamation-triangle mr-2"></i>Not set — webhook rejects all calls</p>
            <a href="settings.php" class="text-xs text-[#D97757] underline">Configure in Settings → Payment</a>
        <?php endif; ?>
    </div>
    <div class="luxury-card p-6">
        <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">API Secret Key (verification)</p>
        <?php if ($secretConfigured): ?>
            <p class="text-[#2C3E35] font-medium"><i class="fas fa-check-circle text-green-600 mr-2"></i>Configured</p>
        <?php else: ?>
            <p class="text-[#D97757] font-medium"><i class="fas fa-exclamation-triangle mr-2"></i>Missing — transactions cannot be verified</p>
        <?php endif; ?>
        <p class="text-xs text-[#6B7C70] mt-1">Environment: <span class="font-mono"><?php echo htmlspecialchars($environment); ?></span></p>
    </div>
    <div class="luxury-card p-6 <?php echo $recovered7d > 0 ? 'border border-[#D97757]' : ''; ?>">
        <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Reconciliation Recoveries (7d)</p>
        <p class="text-2xl font-serif <?php echo $recovered7d > 0 ? 'text-[#D97757]' : 'text-[#2C3E35]'; ?>">
            <?php echo $recovered7d; ?></p>
        <p class="text-xs text-[#6B7C70] mt-1"><?php echo $recovered7d > 0
            ? 'Sales recovered by cron — the live webhook is missing events!'
            : 'No missed webhooks recovered — healthy.'; ?></p>
    </div>
</div>

<!-- 24h webhook activity -->
<div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-8">
    <?php
    $tiles = [
        'processed' => ['Processed', 'text-green-700'],
        'duplicate' => ['Duplicates', 'text-[#6B7C70]'],
        'received' => ['Ignored', 'text-[#6B7C70]'],
        'rejected' => ['Rejected', 'text-[#D97757]'],
        'verify_failed' => ['Verify Failed', 'text-[#D97757]'],
        'error' => ['Errors', 'text-[#D97757]'],
    ];
    foreach ($tiles as $key => $tile): ?>
        <div class="luxury-card p-4 text-center">
            <p class="text-2xl font-serif <?php echo $tile[1]; ?>"><?php echo $counts[$key] ?? 0; ?></p>
            <p class="text-[10px] font-bold text-[#A4B4A6] uppercase tracking-wider mt-1"><?php echo $tile[0]; ?> (24h)</p>
        </div>
    <?php endforeach; ?>
</div>

<!-- Unattributed purchases -->
<div class="luxury-card overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8] flex justify-between items-center">
        <h3 class="flex items-center text-lg font-serif text-[#2C3E35]">
            <i class="fas fa-unlink mr-2 text-[#D97757] text-sm"></i>Unattributed Purchases (30 days)
        </h3>
        <span class="text-sm text-[#6B7C70]"><?php echo count($unattributed); ?> sale(s) without session attribution</span>
    </div>
    <?php if (empty($unattributed)): ?>
        <p class="px-6 py-6 text-sm text-[#6B7C70]">All recent sales carry a funnel session — A/B attribution and journey cancellation are working.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-[#F9FAF9]">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Tx Ref</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Funnel</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#F2F4F1]">
                    <?php foreach ($unattributed as $sale): ?>
                        <tr>
                            <td class="px-6 py-3 text-sm text-[#6B7C70] whitespace-nowrap"><?php echo htmlspecialchars($sale['created_at'] ?? ''); ?></td>
                            <td class="px-6 py-3 text-sm font-mono text-[#2C3E35]"><?php echo htmlspecialchars($sale['tx_ref'] ?? '—'); ?></td>
                            <td class="px-6 py-3 text-sm text-[#2C3E35]"><?php echo htmlspecialchars($sale['email'] ?? ''); ?></td>
                            <td class="px-6 py-3 text-sm text-[#6B7C70]"><?php echo htmlspecialchars($sale['product_type'] ?? ''); ?></td>
                            <td class="px-6 py-3 text-sm text-right text-[#2C3E35]">
                                <?php echo htmlspecialchars(($sale['currency'] ?? 'USD') . ' ' . number_format((float) ($sale['amount'] ?? 0), 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Recent webhook log -->
<div class="luxury-card overflow-hidden">
    <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8] flex justify-between items-center">
        <h3 class="flex items-center text-lg font-serif text-[#2C3E35]">
            <i class="fas fa-stream mr-2 text-[#D97757] text-sm"></i>Recent Payment Webhook Activity
        </h3>
        <span class="text-sm text-[#6B7C70]">Last 50 events</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[#F9FAF9]">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Timestamp</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Source</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Tx Ref</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Session</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Funnel</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Detail</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#F2F4F1]">
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="px-6 py-6 text-sm text-[#6B7C70]">No payment webhook activity recorded yet.
                        Once the Webhook Secret Hash is set and the Flutterwave dashboard points at
                        <code class="font-mono">backend/api/flutterwave-webhook.php</code>, events appear here.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $row):
                        $status = $row['status'] ?? '';
                        $badge = in_array($status, ['processed', 'recovered', 'ok'])
                            ? 'bg-[#E3E8E1] text-[#2C3E35]'
                            : (in_array($status, ['duplicate', 'received']) ? 'bg-[#F2F4F1] text-[#6B7C70]' : 'bg-[#FDF1E8] text-[#D97757]');
                        ?>
                        <tr>
                            <td class="px-6 py-3 text-sm text-[#6B7C70] whitespace-nowrap"><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                            <td class="px-6 py-3 text-sm text-[#6B7C70]"><?php echo htmlspecialchars($row['source'] ?? ''); ?></td>
                            <td class="px-6 py-3"><span class="px-2 py-1 rounded text-xs font-bold <?php echo $badge; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                            <td class="px-6 py-3 text-sm font-mono text-[#2C3E35]"><?php echo htmlspecialchars($row['tx_ref'] ?? '—'); ?></td>
                            <td class="px-6 py-3 text-sm font-mono text-[#6B7C70]"><?php echo htmlspecialchars($row['session_id'] ?? '—'); ?></td>
                            <td class="px-6 py-3 text-sm text-[#6B7C70]"><?php echo htmlspecialchars($row['funnel'] ?? '—'); ?></td>
                            <td class="px-6 py-3 text-xs text-[#6B7C70] max-w-xs truncate" title="<?php echo htmlspecialchars($row['detail'] ?? ''); ?>">
                                <?php echo htmlspecialchars(substr((string) ($row['detail'] ?? ''), 0, 80)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6 text-xs text-[#6B7C70] leading-relaxed">
    <p><strong>Setup checklist:</strong>
        1) Settings → Payment: set Secret Key + Webhook Secret Hash ·
        2) Flutterwave Dashboard → Settings → Webhooks: URL = <code class="font-mono">…/backend/api/flutterwave-webhook.php</code>, Secret hash = same value ·
        3) Cron: <code class="font-mono">15 2 * * * php backend/cron/reconcile_payments.php</code> (daily missed-webhook backstop).
    </p>
</div>

<?php include 'includes/footer.php'; ?>
