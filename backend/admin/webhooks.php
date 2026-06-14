<?php
require_once 'auth.php';
require_once '../classes/Database.php';
require_once '../classes/WebhookDispatcher.php';

$dispatcher = new WebhookDispatcher();
$message = '';
$error = '';
$newSecret = null;

// ---------------- Actions ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';

    switch ($action) {
        case 'create':
            $headers = [];
            if (!empty($_POST['headers_json'])) {
                $headers = json_decode($_POST['headers_json'], true);
                if (!is_array($headers)) {
                    $error = 'Custom headers must be a JSON object, e.g. {"Authorization": "Bearer xyz"}';
                    break;
                }
            }
            $result = $dispatcher->createWebhook([
                'name' => $_POST['name'] ?? '',
                'url' => $_POST['url'] ?? '',
                'events' => $_POST['events'] ?? [],
                'secret' => $_POST['secret'] ?? '',
                'headers' => $headers,
            ]);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $message = "Webhook \"{$result['name']}\" created.";
                $newSecret = $result['secret']; // shown exactly once
                logActivity('webhook_created', $result['name'] . ' -> ' . $result['url']);
            }
            break;

        case 'update_events':
            $result = $dispatcher->updateWebhook($id, ['events' => $_POST['events'] ?? []]);
            $error = $result['error'] ?? '';
            if (!$error) $message = 'Webhook events updated.';
            break;

        case 'toggle':
            $current = $dispatcher->getWebhook($id);
            if ($current) {
                $newStatus = $current['status'] === 'active' ? 'paused' : 'active';
                $dispatcher->updateWebhook($id, ['status' => $newStatus]);
                $message = "Webhook {$newStatus}.";
            }
            break;

        case 'delete':
            $dispatcher->deleteWebhook($id);
            $message = 'Webhook deleted.';
            logActivity('webhook_deleted', $id);
            break;

        case 'test':
            $result = $dispatcher->sendTest($id);
            if ($result['success']) {
                $message = "Test delivered — HTTP {$result['http_code']}.";
            } else {
                $error = 'Test failed: ' . ($result['error'] ?? 'unknown') .
                    (!empty($result['response']) ? ' — ' . substr($result['response'], 0, 200) : '');
            }
            break;
    }
}

$webhooks = $dispatcher->listWebhooks();
$catalog = WebhookDispatcher::eventCatalog();
$viewDeliveries = $_GET['deliveries'] ?? null;
$deliveries = $viewDeliveries ? $dispatcher->recentDeliveries($viewDeliveries, 25) : [];

$pageTitle = 'Webhooks - 1wellness Admin';
include 'includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Webhooks</h2>
        <p class="text-[#6B7C70] mt-1">Push events to n8n, Slack, Zapier or any HTTPS endpoint — signed with HMAC-SHA256</p>
    </div>
    <div>
        <button onclick="document.getElementById('webhookModal').classList.remove('hidden')"
            class="px-4 py-2 bg-[#2C3E35] text-white rounded-xl text-sm font-medium hover:bg-[#1a2621] transition-colors shadow-lg shadow-[#2C3E35]/20">
            <i class="fas fa-plus mr-2"></i> Add Webhook
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-4 p-4 bg-[#F2F4F1] border border-[#A4B4A6] text-[#2C3E35] rounded-xl flex items-center">
        <i class="fas fa-check-circle mr-3"></i><?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>
<?php if ($newSecret): ?>
    <div class="mb-4 p-4 bg-[#FFF8EE] border border-[#E8C893] text-[#2C3E35] rounded-xl">
        <p class="font-medium mb-1"><i class="fas fa-key text-[#B7791F] mr-2"></i>Signing secret (copy now — it is shown only once):</p>
        <code class="text-sm bg-white px-3 py-1.5 rounded border border-[#EAEAE5] select-all"><?php echo htmlspecialchars($newSecret); ?></code>
        <p class="text-xs text-[#6B7C70] mt-2">Verify deliveries with <span class="font-mono">X-Webhook-Signature = HMAC-SHA256(rawBody, secret)</span>. See docs/WEBHOOKS.md.</p>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-4 p-4 bg-[#FDF1E8] border border-[#D97757] text-[#D97757] rounded-xl flex items-center">
        <i class="fas fa-exclamation-circle mr-3"></i><?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Webhooks List -->
<div class="luxury-card overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8]">
        <h3 class="flex items-center text-lg font-serif text-[#2C3E35]">
            <i class="fas fa-plug mr-2 text-[#D97757] text-sm"></i>Configured Webhooks
        </h3>
    </div>

    <?php if (empty($webhooks)): ?>
        <div class="p-12 text-center text-[#6B7C70] bg-white">
            <div class="bg-[#F2F4F1] w-16 h-16 rounded-full flex items-center justify-center text-[#A4B4A6] mx-auto mb-4">
                <i class="fas fa-plug text-2xl"></i>
            </div>
            <p class="text-lg font-serif text-[#2C3E35] mb-2">No webhooks configured</p>
            <p class="text-sm mb-6">Create a webhook and pick which events should fire it.</p>
            <button onclick="document.getElementById('webhookModal').classList.remove('hidden')"
                class="px-4 py-2 bg-white border border-[#EAEAE5] text-[#2C3E35] rounded-lg text-sm font-medium hover:bg-[#F2F4F1] transition-colors">
                Create your first webhook
            </button>
        </div>
    <?php else: ?>
        <div class="divide-y divide-[#EAEAE5]">
            <?php foreach ($webhooks as $webhook):
                $active = ($webhook['status'] ?? 'active') === 'active';
            ?>
                <div class="p-6 bg-white hover:bg-[#FDFCF8] transition-colors">
                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-3 mb-1">
                                <h4 class="text-lg font-serif text-[#2C3E35]"><?php echo htmlspecialchars($webhook['name']); ?></h4>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?php echo $active ? 'bg-[#E6F4EA] text-[#1D4532]' : 'bg-[#FDF1E8] text-[#D97757]'; ?>">
                                    <?php echo htmlspecialchars($webhook['status'] ?? 'active'); ?>
                                </span>
                            </div>
                            <p class="text-[#6B7C70] font-mono text-xs mb-2 truncate max-w-xl"><?php echo htmlspecialchars($webhook['url']); ?></p>
                            <div class="flex flex-wrap gap-1.5 mb-2">
                                <?php foreach (($webhook['events'] ?? []) as $event): ?>
                                    <span class="px-2 py-0.5 text-[10px] uppercase font-bold tracking-wider rounded bg-[#F2F4F1] text-[#6B7C70] border border-[#EAEAE5]"
                                          title="<?php echo htmlspecialchars($catalog[$event]['description'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($event); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-xs text-[#A4B4A6]">
                                <i class="fas fa-circle-check text-[#1D4532] mr-1"></i><?php echo (int) ($webhook['success_count'] ?? 0); ?> delivered
                                <i class="fas fa-circle-xmark text-[#D97757] ml-3 mr-1"></i><?php echo (int) ($webhook['failure_count'] ?? 0); ?> failed
                                <?php if (!empty($webhook['last_triggered'])): ?> · last fired <?php echo htmlspecialchars($webhook['last_triggered']); ?><?php endif; ?>
                            </p>
                        </div>

                        <div class="flex items-center gap-1 shrink-0">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="test">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($webhook['id']); ?>">
                                <button type="submit" class="p-2 text-[#6B7C70] hover:text-[#2C3E35]" title="Send signed test payload now">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                            <a href="?deliveries=<?php echo urlencode($webhook['id']); ?>#deliveries" class="p-2 text-[#6B7C70] hover:text-[#2C3E35]" title="Recent deliveries">
                                <i class="fas fa-list-ul"></i>
                            </a>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($webhook['id']); ?>">
                                <button type="submit" class="p-2 text-[#6B7C70] hover:text-[#B7791F]" title="<?php echo $active ? 'Pause' : 'Resume'; ?>">
                                    <i class="fas <?php echo $active ? 'fa-pause' : 'fa-play'; ?>"></i>
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Delete this webhook? Pending deliveries will be dropped.');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($webhook['id']); ?>">
                                <button type="submit" class="p-2 text-[#6B7C70] hover:text-[#D97757]" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Inline event editing -->
                    <details class="mt-3">
                        <summary class="text-xs text-[#6B7C70] cursor-pointer hover:text-[#2C3E35]"><i class="fas fa-pen mr-1"></i>Edit subscribed events</summary>
                        <form method="POST" class="mt-3 p-4 bg-[#F9FAF9] rounded-xl border border-[#EAEAE5]">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="update_events">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($webhook['id']); ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <?php foreach ($catalog as $key => $meta): ?>
                                    <label class="flex items-start space-x-2 cursor-pointer text-sm">
                                        <input type="checkbox" name="events[]" value="<?php echo $key; ?>"
                                            <?php echo in_array($key, $webhook['events'] ?? []) ? 'checked' : ''; ?>
                                            class="mt-1 h-4 w-4 text-[#2C3E35] rounded border-[#A4B4A6]">
                                        <span><span class="text-[#2C3E35] font-medium"><?php echo htmlspecialchars($meta['label']); ?></span>
                                            <span class="block text-xs text-[#A4B4A6]"><?php echo htmlspecialchars($meta['description']); ?></span></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <button class="mt-3 px-4 py-1.5 bg-[#2C3E35] text-white rounded-lg text-xs">Save events</button>
                        </form>
                    </details>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($viewDeliveries): ?>
<!-- Recent deliveries -->
<div class="luxury-card overflow-hidden mb-8" id="deliveries">
    <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8] flex items-center justify-between">
        <h3 class="text-lg font-serif text-[#2C3E35]"><i class="fas fa-list-ul mr-2 text-[#D97757] text-sm"></i>Recent Deliveries — <?php echo htmlspecialchars($viewDeliveries); ?></h3>
        <a href="webhooks.php" class="text-xs text-[#6B7C70] hover:underline">close</a>
    </div>
    <?php if (empty($deliveries)): ?>
        <div class="p-8 text-center text-sm text-[#6B7C70]">No deliveries yet for this webhook.</div>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead><tr class="bg-[#FAFAF8] text-left text-xs uppercase tracking-wider text-[#6B7C70]">
                <th class="px-6 py-3">Event</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Attempts</th><th class="px-3 py-3">Created</th><th class="px-3 py-3">Next attempt</th>
            </tr></thead>
            <tbody class="divide-y divide-[#EAEAE5]">
                <?php foreach ($deliveries as $d):
                    $st = $d['status'] ?? '';
                    $color = $st === 'completed' ? 'text-[#1D4532]' : ($st === 'failed' ? 'text-[#D97757]' : 'text-[#B7791F]');
                ?>
                <tr class="hover:bg-[#FDFCF8]">
                    <td class="px-6 py-2.5 font-mono text-xs"><?php echo htmlspecialchars($d['event'] ?? ''); ?></td>
                    <td class="px-3 py-2.5 font-medium <?php echo $color; ?>"><?php echo htmlspecialchars($st); ?></td>
                    <td class="px-3 py-2.5"><?php echo (int) ($d['attempts'] ?? 0); ?></td>
                    <td class="px-3 py-2.5 text-xs text-[#6B7C70]"><?php echo htmlspecialchars($d['created_at'] ?? ''); ?></td>
                    <td class="px-3 py-2.5 text-xs text-[#6B7C70]"><?php echo htmlspecialchars($d['next_attempt'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<p class="text-sm text-[#6B7C70] mb-8">
    Deliveries are queued and sent by <span class="font-mono text-xs">backend/cron/process_webhooks.php</span> (run every minute)
    with exponential-backoff retries. API: <span class="font-mono text-xs">backend/api/webhooks-api.php</span> · Docs: <span class="font-mono text-xs">docs/WEBHOOKS.md</span>
</p>

<!-- ============ Add Webhook Modal ============ -->
<div id="webhookModal" class="fixed inset-0 z-50 hidden bg-[#2C3E35]/50 overflow-y-auto h-full w-full backdrop-blur-sm"
    onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="relative top-10 mx-auto mb-10 p-0 border-0 w-full max-w-xl shadow-2xl rounded-2xl bg-white overflow-hidden">
        <div class="p-6 border-b border-[#EAEAE5] bg-[#FAFAF8] flex justify-between items-center">
            <h3 class="text-xl font-serif text-[#2C3E35]">Add New Webhook</h3>
            <button onclick="document.getElementById('webhookModal').classList.add('hidden')"
                class="text-[#A4B4A6] hover:text-[#D97757]">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block text-sm font-medium text-[#2C3E35] mb-1">Name</label>
                <input type="text" name="name" required placeholder="e.g. n8n — Experiment Notifications"
                    class="w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
            </div>

            <div>
                <label class="block text-sm font-medium text-[#2C3E35] mb-1">Payload URL</label>
                <input type="url" name="url" required placeholder="https://"
                    class="w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
            </div>

            <div>
                <label class="block text-sm font-medium text-[#2C3E35] mb-2">Events to fire this webhook</label>
                <div class="space-y-2.5 bg-[#F9FAF9] p-4 rounded-lg border border-[#EAEAE5] max-h-72 overflow-y-auto">
                    <?php foreach ($catalog as $key => $meta): ?>
                        <label class="flex items-start space-x-3 cursor-pointer">
                            <input type="checkbox" name="events[]" value="<?php echo $key; ?>"
                                class="mt-0.5 form-checkbox h-4 w-4 text-[#2C3E35] rounded border-[#A4B4A6] focus:ring-[#2C3E35]">
                            <span>
                                <span class="text-[#2C3E35] text-sm font-medium"><?php echo htmlspecialchars($meta['label']); ?></span>
                                <span class="text-[#A4B4A6] text-xs font-mono ml-1"><?php echo $key; ?></span>
                                <span class="block text-xs text-[#6B7C70]"><?php echo htmlspecialchars($meta['description']); ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <details>
                <summary class="text-sm text-[#6B7C70] cursor-pointer hover:text-[#2C3E35]">Advanced (secret & custom headers)</summary>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="block text-xs text-[#6B7C70] mb-1">Signing secret (leave blank to auto-generate)</label>
                        <input type="text" name="secret" placeholder="auto-generated if empty"
                            class="w-full px-4 py-2 border border-[#EAEAE5] rounded-lg text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs text-[#6B7C70] mb-1">Custom headers (JSON object)</label>
                        <input type="text" name="headers_json" placeholder='{"Authorization": "Bearer xyz"}'
                            class="w-full px-4 py-2 border border-[#EAEAE5] rounded-lg text-sm font-mono">
                    </div>
                </div>
            </details>

            <div class="pt-4 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('webhookModal').classList.add('hidden')"
                    class="px-4 py-2 border border-[#EAEAE5] rounded-lg text-[#6B7C70] hover:bg-[#F2F4F1] transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="px-6 py-2 bg-[#2C3E35] text-white font-medium rounded-lg hover:bg-[#1a2621] transition-colors shadow-md">
                    Create Webhook
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
