<?php
require_once 'auth.php';
require_once '../classes/Database.php';
require_once '../classes/Settings.php';
require_once '../classes/Mailer.php';
require_once '../classes/ChannelAdapterInterface.php';
require_once '../classes/EmailChannel.php';
require_once '../classes/TemplateRenderer.php';
require_once '../classes/NotificationService.php';

$ns       = NotificationService::getInstance();
$settings = Settings::getInstance();
$db       = Database::getInstance();

// ---- AJAX/POST actions -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json');

    $input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';

    if ($action === 'toggle_journey') {
        $key    = preg_replace('/[^a-z0-9_]/', '', $input['journey_key'] ?? '');
        $enable = !empty($input['enabled']);
        if ($key) {
            $settings->set('journey_' . $key . '_enabled', $enable ? '1' : '0');
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'cancel_row') {
        $id = (int) ($input['id'] ?? 0);
        if ($id > 0 && !$db->isFileStorage()) {
            $stmt = $db->getConnection()->prepare(
                "UPDATE notification_queue
                 SET status='cancelled', cancelled_reason='admin_cancel'
                 WHERE id=? AND status IN ('pending','failed')"
            );
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'test_send') {
        $to      = trim($input['recipient_email'] ?? '');
        $tplKey  = trim($input['template_key'] ?? 'purchase_confirm_email');
        $channel = 'email';

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
            exit;
        }

        $tpl = $ns->loadTemplate($tplKey, $channel, 'pcos');
        if (!$tpl) {
            echo json_encode(['success' => false, 'error' => "Template '$tplKey' not found."]);
            exit;
        }

        $sampleVars = [
            'name'          => 'Test User',
            'email'         => $to,
            'username'      => 'testuser',
            'password'      => 'TestPass123',
            'plan_label'    => '90-Day PCOS Plan',
            'plan_duration' => 90,
            'plan_end_date' => date('F j, Y', strtotime('+90 days')),
            'funnel'        => 'pcos',
            'amount'        => 197,
            'currency'      => 'USD',
            'funnel_name'   => 'PCOS',
            'checkout_url'  => rtrim(defined('APP_URL') ? APP_URL : '', '/') . '/pcos/select-plan.html',
        ];
        $rendered = TemplateRenderer::renderTemplate($tpl, $sampleVars);
        $adapter  = new EmailChannel();
        $result   = $adapter->send($to, $rendered['subject'], $rendered['body']);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success']
                ? "Test email sent to $to."
                : ('Send failed: ' . $result['error']),
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'unknown_action']);
    exit;
}

// ---- Page data ---------------------------------------------------------------
$stats  = $ns->queueStats(24);
$dryRun = (bool) $settings->get('notify_dry_run', '0');

$journeys = [
    'Conversion (C)'  => ['assessment_abandon', 'results_no_plan_view', 'checkout_abandon', 'nurture_long'],
    'Follow-up (F)'   => ['purchase_confirm', 'onboarding', 'order_bump_fulfil'],
    'Retention (R)'   => ['daily_nudge', 'streak_save', 'weekly_summary', 'winback', 'renewal_refill'],
];

$journeyLabels = [
    'assessment_abandon'    => 'C1 — Assessment Abandon',
    'results_no_plan_view'  => 'C2 — Results → No Plan View',
    'checkout_abandon'      => 'C3 — Checkout Abandon',
    'nurture_long'          => 'C4 — Long Nurture',
    'purchase_confirm'      => 'F1 — Purchase Confirm',
    'onboarding'            => 'F2 — Onboarding D1/D3/D7',
    'order_bump_fulfil'     => 'F4 — Expert Access',
    'daily_nudge'           => 'R1 — Daily Nudge',
    'streak_save'           => 'R3 — Streak Save',
    'weekly_summary'        => 'R4 — Weekly Summary',
    'winback'               => 'R7 — Winback',
    'renewal_refill'        => 'R6 — Renewal / Refill',
];

// 7-day journey stats from notification_log
$journeyStats = [];
if (!$db->isFileStorage()) {
    try {
        $rows = $db->fetchAll(
            "SELECT journey_key, status, COUNT(*) AS c FROM notification_log
             WHERE created_at >= ? GROUP BY journey_key, status",
            [date('Y-m-d H:i:s', time() - 7 * 86400)]
        ) ?: [];
        foreach ($rows as $r) {
            $journeyStats[$r['journey_key']][$r['status']] = (int) $r['c'];
        }
    } catch (Exception $e) {
        error_log('Admin notifications journeyStats: ' . $e->getMessage());
    }
}

// Pending queue rows
$pendingRows = [];
if (!$db->isFileStorage()) {
    try {
        $pendingRows = $db->fetchAll(
            "SELECT id, journey_key, step, email, channel_ladder, send_after, attempts, status
             FROM notification_queue
             WHERE status IN ('pending','processing','failed')
             ORDER BY send_after ASC LIMIT 50"
        ) ?: [];
    } catch (Exception $e) {
        error_log('Admin notifications pendingRows: ' . $e->getMessage());
    }
}

$failures = $ns->recentFailures(20);

// Templates for test-send dropdown
$templates = [];
if (!$db->isFileStorage()) {
    try {
        $templates = $db->fetchAll(
            "SELECT DISTINCT template_key FROM notification_templates ORDER BY template_key"
        ) ?: [];
    } catch (Exception $e) {}
}

$pageTitle = 'Notifications — 1wellness Admin';
include 'includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Notifications</h2>
        <p class="text-[#6B7C70] mt-1">Journey queue, delivery stats, and channel health</p>
    </div>
    <div class="flex items-center gap-3">
        <?php if ($dryRun): ?>
        <span class="bg-amber-100 text-amber-800 text-xs font-semibold px-3 py-1 rounded-full border border-amber-200">
            <i class="fas fa-flask mr-1"></i>DRY-RUN ON
        </span>
        <?php endif; ?>
        <a href="settings.php#notifications" class="px-4 py-2 border border-[#EAEAE5] text-sm rounded-xl hover:bg-[#F2F4F1] transition-colors text-[#2C3E35]">
            <i class="fas fa-cog mr-1"></i>Channel Settings
        </a>
    </div>
</div>

<!-- Queue stat cards -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <?php
    $statDefs = [
        ['key' => 'pending_total', 'label' => 'Pending',        'cls' => 'text-blue-700'],
        ['key' => 'sent',          'label' => 'Sent (24h)',      'cls' => 'text-green-700'],
        ['key' => 'failed',        'label' => 'Failed (24h)',    'cls' => 'text-[#D97757]'],
        ['key' => 'suppressed',    'label' => 'Suppressed (24h)','cls' => 'text-[#6B7C70]'],
        ['key' => 'cancelled',     'label' => 'Cancelled (24h)', 'cls' => 'text-[#6B7C70]'],
    ];
    foreach ($statDefs as $s): ?>
    <div class="luxury-card p-5 text-center">
        <p class="text-2xl font-serif <?php echo $s['cls']; ?>"><?php echo number_format((int) ($stats[$s['key']] ?? 0)); ?></p>
        <p class="text-[10px] font-bold text-[#A4B4A6] uppercase tracking-wider mt-1"><?php echo $s['label']; ?></p>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Left: journey toggles + test send -->
    <div class="lg:col-span-1 space-y-6">

        <?php foreach ($journeys as $group => $keys): ?>
        <div class="luxury-card p-6">
            <h3 class="font-serif text-[#2C3E35] font-semibold mb-4"><?php echo htmlspecialchars($group); ?></h3>
            <div class="space-y-0">
            <?php foreach ($keys as $jk):
                $enabled  = $settings->get('journey_' . $jk . '_enabled', '1') !== '0';
                $jSent    = (int) ($journeyStats[$jk]['sent'] ?? 0);
                $jFailed  = (int) ($journeyStats[$jk]['failed'] ?? 0);
                $isTransact = NotificationService::isTransactional($jk);
            ?>
            <div class="flex items-center justify-between py-3 border-b border-[#F2F4F1] last:border-0" id="journey-row-<?php echo $jk; ?>">
                <div class="min-w-0 mr-3">
                    <p class="text-sm font-medium text-[#2C3E35] truncate"><?php echo htmlspecialchars($journeyLabels[$jk] ?? $jk); ?></p>
                    <p class="text-xs text-[#A4B4A6]">
                        <?php echo $jSent; ?> sent · <?php echo $jFailed; ?> failed (7d)
                        <?php if ($isTransact): ?><span class="ml-1 text-[9px] bg-[#E3E8E1] text-[#2C3E35] px-1.5 py-0.5 rounded">TX</span><?php endif; ?>
                    </p>
                </div>
                <button
                    onclick="toggleJourney('<?php echo $jk; ?>', <?php echo $enabled ? 'false' : 'true'; ?>, this)"
                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus:outline-none <?php echo $enabled ? 'bg-[#2C3E35]' : 'bg-[#D5DAD5]'; ?>"
                    title="<?php echo $enabled ? 'Click to disable' : 'Click to enable'; ?>">
                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform <?php echo $enabled ? 'translate-x-6' : 'translate-x-1'; ?>"></span>
                </button>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Test send -->
        <div class="luxury-card p-6">
            <h3 class="font-serif text-[#2C3E35] font-semibold mb-4">Send Test Email</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wider mb-1">Template</label>
                    <select id="testTplKey" class="luxury-input w-full px-3 py-2 text-sm border border-[#EAEAE5] rounded-lg">
                        <?php foreach ($templates as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['template_key']); ?>">
                            <?php echo htmlspecialchars($t['template_key']); ?>
                        </option>
                        <?php endforeach; ?>
                        <?php if (empty($templates)): ?>
                        <option value="purchase_confirm_email">purchase_confirm_email</option>
                        <option value="checkout_abandon_1_email">checkout_abandon_1_email</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wider mb-1">Recipient</label>
                    <input type="email" id="testRecipient"
                        value="<?php echo htmlspecialchars($settings->get('admin_email', '')); ?>"
                        class="luxury-input w-full px-3 py-2 text-sm border border-[#EAEAE5] rounded-lg">
                </div>
                <button onclick="sendTestEmail()" id="testSendBtn"
                    class="w-full py-2 bg-[#2C3E35] text-white text-sm font-medium rounded-lg hover:bg-[#1f2d26] transition-colors">
                    <i class="fas fa-paper-plane mr-2"></i>Send Test
                </button>
                <p id="testSendResult" class="text-xs text-center hidden"></p>
            </div>
        </div>

    </div>

    <!-- Right: queue + failures -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Pending queue -->
        <div class="luxury-card overflow-hidden">
            <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8] flex justify-between items-center">
                <h3 class="font-serif text-[#2C3E35] font-semibold">Pending Queue (next 50)</h3>
                <span class="text-sm text-[#6B7C70]"><?php echo count($pendingRows); ?> row(s)</span>
            </div>
            <?php if (empty($pendingRows)): ?>
            <p class="px-6 py-6 text-sm text-[#6B7C70]">Queue is empty — nothing pending or failed.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[#F9FAF9]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Journey</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">To</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Channel</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Send After</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F2F4F1]">
                    <?php foreach ($pendingRows as $r): ?>
                    <tr class="hover:bg-[#FAFAF8]" id="qrow-<?php echo $r['id']; ?>">
                        <td class="px-4 py-3 text-sm font-medium text-[#2C3E35]">
                            <?php echo htmlspecialchars($r['journey_key']); ?>#<?php echo $r['step']; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-[#6B7C70] max-w-[120px] truncate">
                            <?php echo htmlspecialchars($r['email'] ?? '—'); ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-[#6B7C70]">
                            <?php echo htmlspecialchars($r['channel_ladder']); ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-[#6B7C70] whitespace-nowrap">
                            <?php echo $r['send_after'] ? date('M j H:i', strtotime($r['send_after'])) : '—'; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php
                            $badge = match($r['status']) {
                                'pending'    => 'bg-blue-100 text-blue-700',
                                'processing' => 'bg-yellow-100 text-yellow-700',
                                'failed'     => 'bg-red-100 text-red-700',
                                default      => 'bg-[#F2F4F1] text-[#6B7C70]',
                            };
                            ?>
                            <span class="text-xs px-2 py-0.5 rounded-full <?php echo $badge; ?>">
                                <?php echo ucfirst($r['status']); ?><?php if ((int)$r['attempts'] > 0): ?> ×<?php echo $r['attempts']; ?><?php endif; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="cancelRow(<?php echo (int)$r['id']; ?>)"
                                class="text-xs text-[#D97757] hover:underline">Cancel</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent failures -->
        <div class="luxury-card p-6">
            <h3 class="font-serif text-[#2C3E35] font-semibold mb-4">
                Recent Failures
                <span class="text-sm font-normal text-[#6B7C70] ml-2">(last 20)</span>
            </h3>
            <?php if (empty($failures)): ?>
            <p class="text-sm text-[#6B7C70]">No failures recorded. Good.</p>
            <?php else: ?>
            <div class="space-y-3">
            <?php foreach ($failures as $f): ?>
            <div class="border border-red-100 bg-red-50/30 rounded-lg p-3">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="font-medium text-sm text-[#2C3E35]">
                            <?php echo htmlspecialchars($f['journey_key'] ?? ''); ?>#<?php echo $f['step'] ?? ''; ?>
                        </span>
                        <span class="text-xs text-[#6B7C70] ml-2 font-mono">
                            <?php echo htmlspecialchars($f['channel'] ?? ''); ?>
                        </span>
                        <span class="text-xs text-[#6B7C70] ml-1">
                            → <?php echo htmlspecialchars($f['email'] ?? ''); ?>
                        </span>
                    </div>
                    <span class="text-xs text-[#A4B4A6] whitespace-nowrap ml-3">
                        <?php echo $f['created_at'] ? date('M j H:i', strtotime($f['created_at'])) : ''; ?>
                    </span>
                </div>
                <?php if (!empty($f['error'])): ?>
                <p class="text-xs text-red-600 mt-1.5 font-mono bg-white/70 rounded px-2 py-1">
                    <?php echo htmlspecialchars($f['error']); ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Setup checklist -->
<div class="mt-6 luxury-card p-5 text-xs text-[#6B7C70]">
    <p class="font-semibold text-[#2C3E35] mb-1">Cron setup</p>
    <p><code class="font-mono">* * * * *  php backend/cron/send_notifications.php</code> — notification dispatcher (every minute)</p>
    <p class="mt-0.5"><code class="font-mono">15 2 * * *  php backend/cron/reconcile_payments.php</code> — missed-webhook backstop (daily)</p>
</div>

<script>
function toggleJourney(key, enable, btn) {
    btn.disabled = true;
    btn.style.opacity = '0.5';
    fetch('notifications.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'toggle_journey', journey_key: key, enabled: enable})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            location.reload();
        } else {
            btn.disabled = false;
            btn.style.opacity = '1';
            alert('Toggle failed: ' + (d.error || 'Unknown error'));
        }
    })
    .catch(() => { btn.disabled = false; btn.style.opacity = '1'; });
}

function cancelRow(id) {
    if (!confirm('Cancel this queued notification?')) return;
    fetch('notifications.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'cancel_row', id: id})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const row = document.getElementById('qrow-' + id);
            if (row) row.remove();
        }
    });
}

function sendTestEmail() {
    const btn       = document.getElementById('testSendBtn');
    const result    = document.getElementById('testSendResult');
    const recipient = document.getElementById('testRecipient').value.trim();
    const tplKey    = document.getElementById('testTplKey').value;

    if (!recipient) { alert('Enter a recipient email.'); return; }

    btn.disabled = true;
    btn.textContent = 'Sending…';
    result.classList.add('hidden');

    fetch('notifications.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'test_send', recipient_email: recipient, template_key: tplKey})
    })
    .then(r => r.json())
    .then(d => {
        result.textContent = d.message || (d.success ? 'Sent.' : 'Failed.');
        result.className   = 'text-xs text-center mt-1 ' + (d.success ? 'text-green-700' : 'text-[#D97757]');
        result.classList.remove('hidden');
    })
    .catch(e => {
        result.textContent = 'Network error.';
        result.className   = 'text-xs text-center mt-1 text-[#D97757]';
        result.classList.remove('hidden');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send Test';
    });
}
</script>

<?php include 'includes/footer.php'; ?>
