<?php
require_once 'auth.php';
require_once '../classes/Database.php';
require_once '../classes/Settings.php';
require_once '../classes/Mailer.php';
require_once '../classes/Notifications/Channels/ChannelAdapterInterface.php';
require_once '../classes/Notifications/Channels/EmailChannel.php';
require_once '../classes/Notifications/Channels/WhatsAppChannel.php';
require_once '../classes/Notifications/Channels/SmsChannel.php';
require_once '../classes/Notifications/TemplateRenderer.php';
require_once '../classes/Notifications/ConsentManager.php';
require_once '../classes/Notifications/NotificationService.php';

$ns   = NotificationService::getInstance();
$db   = Database::getInstance();
$msg  = '';
$err  = '';

// -----------------------------------------------------------------------
// POST handlers
// -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';

    if ($action === 'save_template') {
        $id = (int) ($input['id'] ?? 0);
        $fields = [
            'template_key'    => substr(preg_replace('/[^a-z0-9_]/', '', strtolower($input['template_key'] ?? '')), 0, 80),
            'channel'         => in_array($input['channel'] ?? '', ['email','whatsapp','sms']) ? $input['channel'] : 'email',
            'funnel'          => in_array($input['funnel'] ?? '', ['all','pcos','acne','weight','mens']) ? $input['funnel'] : 'all',
            'subject'         => substr($input['subject'] ?? '', 0, 255),
            'body'            => $input['body'] ?? '',
            'wa_template_name'=> substr($input['wa_template_name'] ?? '', 0, 120),
            'active'          => isset($input['active']) && $input['active'] ? 1 : 0,
            'updated_at'      => date('Y-m-d H:i:s'),
        ];
        try {
            if ($id > 0) {
                $db->update('notification_templates', $fields, "id = :id", [':id' => $id]);
            } else {
                $id = $db->insert('notification_templates', $fields);
            }
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete_template') {
        $id = (int) ($input['id'] ?? 0);
        if ($id > 0) {
            $db->execute("DELETE FROM notification_templates WHERE id = ?", [$id]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'test_send') {
        $tplKey  = $input['template_key'] ?? '';
        $channel = $input['channel'] ?? 'email';
        $funnel  = $input['funnel'] ?? 'all';
        $to      = $input['to'] ?? '';

        if (!$to) {
            echo json_encode(['success' => false, 'error' => 'recipient required']);
            exit;
        }

        $renderer = new TemplateRenderer();
        $rendered = $renderer->render($tplKey, $channel, [
            'name' => 'Admin Test', 'email' => $to, 'funnel' => $funnel,
            'type' => 'PCOS Test', 'streak_days' => 7,
        ], $funnel);

        if (!$rendered) {
            echo json_encode(['success' => false, 'error' => 'template not found']);
            exit;
        }

        $settings = Settings::getInstance();
        if ($channel === 'email') {
            require_once '../classes/Mailer.php';
            $mailer = new Mailer();
            $ok = $mailer->send($to, $rendered['subject'], $rendered['body']);
            echo json_encode(['success' => $ok, 'error' => $ok ? null : $mailer->getLastError()]);
        } else {
            echo json_encode(['success' => false, 'error' => 'channel not available in test mode — configure provider first']);
        }
        exit;
    }

    if ($action === 'seed_defaults') {
        // Force re-seed by deleting existing and reinserting
        $ns->seedTemplates();
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'unknown_action']);
    exit;
}

// -----------------------------------------------------------------------
// Data for page
// -----------------------------------------------------------------------
$templates = [];
try {
    $templates = $db->fetchAll(
        "SELECT * FROM notification_templates ORDER BY template_key, channel, funnel"
    ) ?: [];
} catch (Exception $e) {}

$channels = ['email', 'whatsapp', 'sms'];
$funnels  = ['all', 'pcos', 'acne', 'weight', 'mens'];

$pageTitle = 'Notification Templates — 1wellness Admin';
include 'includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="notifications.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back to Notifications</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Notification Templates</h2>
        <p class="text-[#6B7C70] mt-1">Edit message copy for all journey steps and channels</p>
    </div>
    <div class="flex gap-3">
        <button onclick="seedDefaults()"
            class="px-4 py-2 border border-[#EAEAE5] text-sm rounded-xl hover:bg-[#F2F4F1] transition-colors">
            Re-seed Defaults
        </button>
        <button onclick="openNewModal()"
            class="px-4 py-2 bg-[#2C3E35] text-white text-sm rounded-xl hover:bg-[#1a2621] transition-colors">
            + New Template
        </button>
    </div>
</div>

<!-- Template table -->
<div class="luxury-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-[#EAEAE5] bg-[#FAFAF8]">
                <tr>
                    <th class="text-left px-4 py-3 text-xs text-[#6B7C70] font-medium">Key</th>
                    <th class="text-left px-4 py-3 text-xs text-[#6B7C70] font-medium">Channel</th>
                    <th class="text-left px-4 py-3 text-xs text-[#6B7C70] font-medium">Funnel</th>
                    <th class="text-left px-4 py-3 text-xs text-[#6B7C70] font-medium">Subject / Preview</th>
                    <th class="text-left px-4 py-3 text-xs text-[#6B7C70] font-medium">Active</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($templates)): ?>
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-[#6B7C70] text-sm">
                    No templates yet. <button onclick="seedDefaults()" class="text-[#D97757] hover:underline">Seed defaults</button>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($templates as $t): ?>
            <tr class="border-b border-[#F2F4F1] hover:bg-[#FAFAF8]">
                <td class="px-4 py-3 font-mono text-xs text-[#2C3E35]"><?php echo htmlspecialchars($t['template_key']); ?></td>
                <td class="px-4 py-3">
                    <?php
                    $chIcon = match($t['channel']) {
                        'whatsapp' => '<i class="fab fa-whatsapp text-green-600 mr-1"></i>',
                        'sms'      => '<i class="fas fa-sms text-blue-500 mr-1"></i>',
                        default    => '<i class="fas fa-envelope text-[#D97757] mr-1"></i>',
                    };
                    echo $chIcon . htmlspecialchars($t['channel']);
                    ?>
                </td>
                <td class="px-4 py-3 text-[#6B7C70]"><?php echo htmlspecialchars($t['funnel']); ?></td>
                <td class="px-4 py-3 text-[#2C3E35] max-w-xs truncate">
                    <?php echo htmlspecialchars($t['subject'] ?: mb_substr(strip_tags($t['body']), 0, 60) . '…'); ?>
                </td>
                <td class="px-4 py-3">
                    <span class="text-xs px-2 py-0.5 rounded-full <?php echo $t['active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>">
                        <?php echo $t['active'] ? 'Active' : 'Off'; ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <button onclick="editTemplate(<?php echo htmlspecialchars(json_encode($t)); ?>)"
                        class="text-xs text-[#2C3E35] hover:underline mr-3">Edit</button>
                    <button onclick="testSend('<?php echo htmlspecialchars($t['template_key']); ?>','<?php echo $t['channel']; ?>','<?php echo $t['funnel']; ?>')"
                        class="text-xs text-[#6B7C70] hover:underline mr-3">Test</button>
                    <button onclick="deleteTemplate(<?php echo $t['id']; ?>)"
                        class="text-xs text-[#D97757] hover:underline">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit / New modal -->
<div id="tplModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-[#EAEAE5] flex justify-between items-center">
            <h3 class="text-xl font-serif text-[#2C3E35]" id="modalTitle">Edit Template</h3>
            <button onclick="closeModal()" class="text-[#6B7C70] hover:text-[#2C3E35]"><i class="fas fa-times"></i></button>
        </div>
        <form id="tplForm" onsubmit="saveTemplate(event)" class="p-6 space-y-4">
            <input type="hidden" id="tplId" name="id" value="0">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-[#6B7C70] mb-1">Template Key</label>
                    <input type="text" id="tplKey" name="template_key" required
                        class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm font-mono"
                        placeholder="purchase_confirm_1">
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#6B7C70] mb-1">Channel</label>
                    <select id="tplChannel" name="channel"
                        class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm">
                        <option value="email">Email</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="sms">SMS</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-[#6B7C70] mb-1">Funnel</label>
                    <select id="tplFunnel" name="funnel"
                        class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm">
                        <option value="all">All funnels</option>
                        <option value="pcos">PCOS</option>
                        <option value="acne">Acne</option>
                        <option value="weight">Weight Loss</option>
                        <option value="mens">Vitality (mens)</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-[#6B7C70] mb-1">Subject (email only)</label>
                <input type="text" id="tplSubject" name="subject"
                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-[#6B7C70] mb-1">Body</label>
                <p class="text-xs text-[#6B7C70] mb-1">Merge vars: <code class="font-mono">{{name}}</code> <code>{{email}}</code> <code>{{funnel_label}}</code> <code>{{resume_link}}</code> <code>{{plan_link}}</code> <code>{{portal_link}}</code> <code>{{streak_days}}</code></p>
                <textarea id="tplBody" name="body" rows="12" required
                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm font-mono"></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-[#6B7C70] mb-1">WhatsApp Template Name (Meta approved)</label>
                    <input type="text" id="tplWaName" name="wa_template_name"
                        class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm font-mono"
                        placeholder="purchase_confirm">
                </div>
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="tplActive" name="active" value="1" checked
                            class="w-4 h-4 accent-[#2C3E35]">
                        <span class="text-sm text-[#2C3E35]">Active</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2 border-t border-[#EAEAE5]">
                <button type="button" onclick="closeModal()"
                    class="px-5 py-2 border border-[#EAEAE5] rounded-xl text-sm hover:bg-[#F2F4F1]">Cancel</button>
                <button type="submit"
                    class="px-5 py-2 bg-[#2C3E35] text-white text-sm rounded-xl hover:bg-[#1a2621]">Save Template</button>
            </div>
        </form>
    </div>
</div>

<!-- Test send modal -->
<div id="testModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
        <h3 class="text-lg font-serif text-[#2C3E35] mb-4">Send Test Notification</h3>
        <input type="hidden" id="testKey">
        <input type="hidden" id="testChannel">
        <input type="hidden" id="testFunnel">
        <div class="mb-4">
            <label class="block text-xs font-medium text-[#6B7C70] mb-1">Recipient (email / phone)</label>
            <input type="text" id="testTo"
                class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm"
                placeholder="you@example.com">
        </div>
        <div id="testResult" class="mb-4 text-sm hidden"></div>
        <div class="flex justify-end gap-3">
            <button onclick="closeTestModal()" class="px-4 py-2 border border-[#EAEAE5] rounded-xl text-sm">Cancel</button>
            <button onclick="doTestSend()" class="px-4 py-2 bg-[#2C3E35] text-white text-sm rounded-xl">Send Test</button>
        </div>
    </div>
</div>

<script>
function openNewModal() {
    document.getElementById('tplId').value = '0';
    document.getElementById('tplKey').value = '';
    document.getElementById('tplChannel').value = 'email';
    document.getElementById('tplFunnel').value = 'all';
    document.getElementById('tplSubject').value = '';
    document.getElementById('tplBody').value = '';
    document.getElementById('tplWaName').value = '';
    document.getElementById('tplActive').checked = true;
    document.getElementById('modalTitle').textContent = 'New Template';
    document.getElementById('tplModal').classList.replace('hidden', 'flex');
}
function editTemplate(t) {
    document.getElementById('tplId').value = t.id;
    document.getElementById('tplKey').value = t.template_key;
    document.getElementById('tplChannel').value = t.channel;
    document.getElementById('tplFunnel').value = t.funnel;
    document.getElementById('tplSubject').value = t.subject || '';
    document.getElementById('tplBody').value = t.body || '';
    document.getElementById('tplWaName').value = t.wa_template_name || '';
    document.getElementById('tplActive').checked = !!parseInt(t.active);
    document.getElementById('modalTitle').textContent = 'Edit Template — ' + t.template_key;
    document.getElementById('tplModal').classList.replace('hidden', 'flex');
}
function closeModal() { document.getElementById('tplModal').classList.replace('flex', 'hidden'); }
function saveTemplate(e) {
    e.preventDefault();
    const data = {
        action: 'save_template',
        id: document.getElementById('tplId').value,
        template_key: document.getElementById('tplKey').value,
        channel: document.getElementById('tplChannel').value,
        funnel: document.getElementById('tplFunnel').value,
        subject: document.getElementById('tplSubject').value,
        body: document.getElementById('tplBody').value,
        wa_template_name: document.getElementById('tplWaName').value,
        active: document.getElementById('tplActive').checked ? 1 : 0,
    };
    fetch('notification-templates.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        if (d.success) { closeModal(); location.reload(); }
        else alert('Error: ' + d.error);
    });
}
function deleteTemplate(id) {
    if (!confirm('Delete this template?')) return;
    fetch('notification-templates.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete_template', id: id})
    }).then(r => r.json()).then(d => { if (d.success) location.reload(); });
}
function testSend(key, channel, funnel) {
    document.getElementById('testKey').value = key;
    document.getElementById('testChannel').value = channel;
    document.getElementById('testFunnel').value = funnel;
    document.getElementById('testTo').value = '';
    document.getElementById('testResult').classList.add('hidden');
    document.getElementById('testModal').classList.replace('hidden', 'flex');
}
function closeTestModal() { document.getElementById('testModal').classList.replace('flex', 'hidden'); }
function doTestSend() {
    const res = document.getElementById('testResult');
    fetch('notification-templates.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'test_send',
            template_key: document.getElementById('testKey').value,
            channel: document.getElementById('testChannel').value,
            funnel: document.getElementById('testFunnel').value,
            to: document.getElementById('testTo').value,
        })
    }).then(r => r.json()).then(d => {
        res.classList.remove('hidden');
        res.className = res.className.replace(/text-(green|red)-\d+/g, '');
        if (d.success) {
            res.textContent = 'Test sent successfully!';
            res.classList.add('text-green-700');
        } else {
            res.textContent = 'Failed: ' + (d.error || 'unknown');
            res.classList.add('text-red-600');
        }
    });
}
function seedDefaults() {
    if (!confirm('Seed default templates? This will add missing defaults (will not overwrite existing).')) return;
    fetch('notification-templates.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'seed_defaults'})
    }).then(r => r.json()).then(d => { if (d.success) location.reload(); });
}
</script>

<?php include 'includes/footer.php'; ?>
