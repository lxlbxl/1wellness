<?php
require_once 'auth.php';
require_once '../classes/Database.php';
require_once '../classes/Settings.php';

$db       = Database::getInstance();
$settings = Settings::getInstance();

// ── Status helpers ────────────────────────────────────────────────────────────
function isSet(string $key, $settingsObj): bool
{
    $v = $settingsObj->get($key, '');
    return is_string($v) ? trim($v) !== '' : (bool) $v;
}

function badge(bool $ok, string $okLabel = 'Configured', string $failLabel = 'Not set'): string
{
    if ($ok) {
        return '<span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800"><i class="fas fa-check-circle"></i> ' . $okLabel . '</span>';
    }
    return '<span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-700"><i class="fas fa-times-circle"></i> ' . $failLabel . '</span>';
}

function optBadge(bool $ok, string $okLabel = 'Enabled', string $failLabel = 'Disabled'): string
{
    if ($ok) {
        return '<span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800"><i class="fas fa-check-circle"></i> ' . $okLabel . '</span>';
    }
    return '<span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-500"><i class="fas fa-circle"></i> ' . $failLabel . '</span>';
}

// ── Compute status for each step ──────────────────────────────────────────────
$appUrl  = rtrim($settings->get('site_url', $_SERVER['HTTP_HOST']), '/');
$domain  = defined('DKIM_DOMAIN') ? DKIM_DOMAIN : parse_url($appUrl, PHP_URL_HOST);
$domain  = $domain ?: '1wellness.club';
$sel     = defined('DKIM_SELECTOR') ? DKIM_SELECTOR : ($settings->get('dkim_selector', 'mail') ?: 'mail');
$dkimPath = defined('DKIM_PRIVATE_KEY_PATH') ? DKIM_PRIVATE_KEY_PATH : $settings->get('dkim_private_key_path', '');
$absRoot  = dirname(__DIR__, 2);
$dkimAbs  = $dkimPath ? (file_exists($dkimPath) ? $dkimPath : $absRoot . '/' . ltrim($dkimPath, '/\\')) : '';

$checks = [
    'db'          => !$db->isFileStorage(),
    'smtp'        => isSet('smtp_host', $settings) && isSet('smtp_username', $settings),
    'flw'         => isSet('flutterwave_secret_key', $settings),
    'flw_live'    => $settings->get('flutterwave_environment', 'sandbox') === 'production',
    'ai'          => isSet('ai_api_key', $settings),
    'dkim'        => $dkimAbs !== '' && file_exists($dkimAbs),
    'wa'          => (bool) $settings->get('notify_whatsapp_enabled', '0') && (isSet('whatsapp_phone_number_id', $settings) || isSet('twilio_sid', $settings)),
    'sms'         => (bool) $settings->get('notify_sms_enabled', '0') && (isSet('twilio_sid', $settings) || isSet('termii_api_key', $settings)),
    'email_on'    => (bool) $settings->get('notify_email_enabled', '1'),
];

$required  = array_sum(array_map('intval', [$checks['db'], $checks['smtp'], $checks['flw'], $checks['ai']]));
$total_req = 4;

$pageTitle = 'Setup Guide — 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="settings.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back to Settings</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Platform Setup Guide</h2>
        <p class="text-[#6B7C70] mt-1">Complete these steps before going live</p>
    </div>
    <div class="text-right">
        <div class="text-3xl font-serif font-bold text-[#2C3E35]"><?= $required ?><span class="text-lg font-normal text-[#6B7C70]">/<?= $total_req ?></span></div>
        <div class="text-xs text-[#6B7C70] uppercase tracking-wider">Required steps done</div>
    </div>
</div>

<!-- Progress bar -->
<div class="w-full bg-[#EAEAE5] rounded-full h-2 mb-10">
    <div class="bg-[#2C3E35] h-2 rounded-full transition-all duration-500"
         style="width: <?= round($required / $total_req * 100) ?>%"></div>
</div>

<div class="space-y-6 max-w-4xl">

<!-- ═══════════════════════════════════════════════════════════════════════════
     1. DATABASE
════════════════════════════════════════════════════════════════════════════ -->
<div class="luxury-card p-6">
    <div class="flex items-start justify-between gap-4 mb-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#F2F4F1] flex items-center justify-center text-[#2C3E35] font-bold text-sm">1</div>
            <div>
                <h3 class="text-lg font-serif text-[#2C3E35]">Database Connection</h3>
                <p class="text-xs text-[#6B7C70]">MySQL required for A/B engine, member data, and notifications</p>
            </div>
        </div>
        <?= badge($checks['db'], 'MySQL connected', 'File storage (limited)') ?>
    </div>
    <?php if (!$checks['db']): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900 space-y-2">
        <p class="font-semibold">Configure MySQL in your <code class="font-mono text-xs bg-amber-100 px-1 py-0.5 rounded">.env</code> file:</p>
        <pre class="bg-white border border-amber-200 rounded p-3 text-xs font-mono overflow-x-auto">DB_TYPE=mysql
DB_HOST=localhost
DB_NAME=1wellness
DB_USER=1wellness_user
DB_PASSWORD=your_password_here</pre>
        <p>Then run the migration: <code class="font-mono text-xs bg-amber-100 px-1 py-0.5 rounded">php backend/database/migrations/migrate.php</code></p>
    </div>
    <?php else: ?>
    <p class="text-sm text-emerald-700"><i class="fas fa-check mr-1"></i> Connected — <?= $db->isFileStorage() ? 'File' : 'MySQL' ?> storage active.</p>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     2. SMTP
════════════════════════════════════════════════════════════════════════════ -->
<div class="luxury-card p-6">
    <div class="flex items-start justify-between gap-4 mb-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#F2F4F1] flex items-center justify-center text-[#2C3E35] font-bold text-sm">2</div>
            <div>
                <h3 class="text-lg font-serif text-[#2C3E35]">Transactional Email (SMTP)</h3>
                <p class="text-xs text-[#6B7C70]">Sends purchase receipts, plan delivery emails, and notification journeys</p>
            </div>
        </div>
        <?= badge($checks['smtp']) ?>
    </div>
    <?php if (!$checks['smtp']): ?>
    <div class="bg-[#F9FAF9] border border-[#EAEAE5] rounded-xl p-4 text-sm space-y-3">
        <p>Go to <a href="settings.php#smtp" class="text-[#D97757] underline font-medium">Settings → Email Configuration</a> and fill in your SMTP credentials.</p>
        <p class="text-[#6B7C70]">Recommended providers: <strong>Gmail</strong> (free, 500/day), <strong>Mailgun</strong> (pay-as-you-go), <strong>SendGrid</strong> (free 100/day). For Gmail, use an <em>App Password</em>, not your main password.</p>
        <div class="bg-white border border-[#EAEAE5] rounded-lg p-3 font-mono text-xs text-[#2C3E35]">
SMTP_HOST=smtp.gmail.com<br>
SMTP_PORT=587<br>
SMTP_USERNAME=you@gmail.com<br>
SMTP_PASSWORD=your-app-password<br>
SMTP_FROM_EMAIL=noreply@1wellness.club<br>
SMTP_FROM_NAME=1wellness
        </div>
    </div>
    <?php else: ?>
    <p class="text-sm text-emerald-700"><i class="fas fa-check mr-1"></i> SMTP configured — host: <code class="font-mono text-xs"><?= htmlspecialchars($settings->get('smtp_host', '')) ?></code>. Use the <a href="settings.php" class="text-[#D97757] underline">Test Connection</a> button to verify.</p>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     3. DKIM / SPF / DMARC
════════════════════════════════════════════════════════════════════════════ -->
<div class="luxury-card p-6">
    <div class="flex items-start justify-between gap-4 mb-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#F2F4F1] flex items-center justify-center text-[#2C3E35] font-bold text-sm">3</div>
            <div>
                <h3 class="text-lg font-serif text-[#2C3E35]">Email Deliverability (DKIM · SPF · DMARC)</h3>
                <p class="text-xs text-[#6B7C70]">Prevents emails landing in spam — critical for journey notification open rates</p>
            </div>
        </div>
        <?= badge($checks['dkim'], 'DKIM key found', 'DKIM not configured') ?>
    </div>

    <div class="space-y-4 text-sm">

        <!-- Step A: Generate keys -->
        <div class="border border-[#EAEAE5] rounded-xl overflow-hidden">
            <div class="bg-[#F9FAF9] px-4 py-2.5 border-b border-[#EAEAE5] flex items-center justify-between">
                <span class="font-medium text-[#2C3E35] text-xs uppercase tracking-wider">Step A — Generate DKIM keys (run once on your server)</span>
                <?= badge($checks['dkim'], 'Key exists', 'Missing') ?>
            </div>
            <div class="p-4">
                <p class="text-[#4A5D52] mb-3">SSH into your server and run:</p>
                <div class="relative">
                    <pre class="bg-[#2C3E35] text-[#FDFCF8] rounded-xl p-4 text-xs font-mono overflow-x-auto">php <?= htmlspecialchars($absRoot) ?>/backend/scripts/generate-dkim-keys.php</pre>
                    <button onclick="copyCode(this)" data-text="php <?= htmlspecialchars($absRoot) ?>/backend/scripts/generate-dkim-keys.php"
                        class="absolute top-2 right-2 text-white/60 hover:text-white text-xs px-2 py-1 rounded border border-white/20 hover:border-white/50 transition-colors">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <p class="text-[#6B7C70] mt-2 text-xs">The script creates <code class="font-mono bg-[#F2F4F1] px-1 rounded">backend/config/dkim/private.key</code> and prints the DNS record to add.</p>
                <?php if ($checks['dkim']): ?>
                <p class="text-emerald-700 mt-2"><i class="fas fa-check-circle mr-1"></i> Private key found at <code class="font-mono text-xs"><?= htmlspecialchars($dkimAbs) ?></code></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Step B: DNS records -->
        <div class="border border-[#EAEAE5] rounded-xl overflow-hidden">
            <div class="bg-[#F9FAF9] px-4 py-2.5 border-b border-[#EAEAE5]">
                <span class="font-medium text-[#2C3E35] text-xs uppercase tracking-wider">Step B — Add DNS records at your domain registrar</span>
            </div>
            <div class="p-4 space-y-4">
                <p class="text-[#6B7C70] text-xs">Log in to wherever you registered <strong><?= htmlspecialchars($domain) ?></strong> (Namecheap, Cloudflare, GoDaddy, etc.) and add these TXT records:</p>

                <!-- DKIM -->
                <div>
                    <p class="text-xs font-semibold text-[#2C3E35] mb-1.5"><i class="fas fa-key mr-1 text-[#D97757]"></i> DKIM (from the key generator output)</p>
                    <table class="w-full text-xs font-mono border border-[#EAEAE5] rounded-lg overflow-hidden">
                        <thead class="bg-[#F2F4F1] text-[#2C3E35]">
                            <tr><th class="text-left px-3 py-2">Name</th><th class="text-left px-3 py-2">Type</th><th class="text-left px-3 py-2">Value</th></tr>
                        </thead>
                        <tbody class="divide-y divide-[#EAEAE5] bg-white">
                            <tr>
                                <td class="px-3 py-2 text-[#2C3E35]"><?= htmlspecialchars($sel) ?>._domainkey.<?= htmlspecialchars($domain) ?></td>
                                <td class="px-3 py-2 text-[#6B7C70]">TXT</td>
                                <td class="px-3 py-2 text-[#6B7C70]">(paste from key generator output)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- SPF -->
                <div>
                    <p class="text-xs font-semibold text-[#2C3E35] mb-1.5"><i class="fas fa-shield-alt mr-1 text-[#D97757]"></i> SPF</p>
                    <table class="w-full text-xs font-mono border border-[#EAEAE5] rounded-lg overflow-hidden">
                        <thead class="bg-[#F2F4F1] text-[#2C3E35]">
                            <tr><th class="text-left px-3 py-2">Name</th><th class="text-left px-3 py-2">Type</th><th class="text-left px-3 py-2">Value</th></tr>
                        </thead>
                        <tbody class="divide-y divide-[#EAEAE5] bg-white">
                            <tr>
                                <td class="px-3 py-2 text-[#2C3E35]"><?= htmlspecialchars($domain) ?></td>
                                <td class="px-3 py-2 text-[#6B7C70]">TXT</td>
                                <td class="px-3 py-2 text-[#6B7C70]">v=spf1 include:_spf.google.com ~all</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="text-[#6B7C70] mt-1 text-xs">If you use another SMTP provider (Mailgun, SendGrid), replace with their SPF include. Only one SPF record per domain.</p>
                </div>

                <!-- DMARC -->
                <div>
                    <p class="text-xs font-semibold text-[#2C3E35] mb-1.5"><i class="fas fa-lock mr-1 text-[#D97757]"></i> DMARC</p>
                    <table class="w-full text-xs font-mono border border-[#EAEAE5] rounded-lg overflow-hidden">
                        <thead class="bg-[#F2F4F1] text-[#2C3E35]">
                            <tr><th class="text-left px-3 py-2">Name</th><th class="text-left px-3 py-2">Type</th><th class="text-left px-3 py-2">Value</th></tr>
                        </thead>
                        <tbody class="divide-y divide-[#EAEAE5] bg-white">
                            <tr>
                                <td class="px-3 py-2 text-[#2C3E35]">_dmarc.<?= htmlspecialchars($domain) ?></td>
                                <td class="px-3 py-2 text-[#6B7C70]">TXT</td>
                                <td class="px-3 py-2 text-[#6B7C70]">v=DMARC1; p=quarantine; rua=mailto:dmarc@<?= htmlspecialchars($domain) ?>; adkim=r; aspf=r</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="text-[#6B7C70] mt-1 text-xs">DNS changes propagate within 24 hours. Verify with <a href="https://mxtoolbox.com/dmarc.aspx" target="_blank" class="text-[#D97757] underline">MXToolbox DMARC checker</a>.</p>
                </div>
            </div>
        </div>

        <!-- Step C: Save path in Settings -->
        <div class="border border-[#EAEAE5] rounded-xl overflow-hidden">
            <div class="bg-[#F9FAF9] px-4 py-2.5 border-b border-[#EAEAE5]">
                <span class="font-medium text-[#2C3E35] text-xs uppercase tracking-wider">Step C — Save the key path in Settings</span>
            </div>
            <div class="p-4">
                <p class="text-[#4A5D52]">Go to <a href="settings.php#dkim" class="text-[#D97757] underline font-medium">Settings → DKIM Signing</a> and enter the key path, then save. Email will automatically sign with DKIM from that point.</p>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     4. PAYMENT
════════════════════════════════════════════════════════════════════════════ -->
<div class="luxury-card p-6">
    <div class="flex items-start justify-between gap-4 mb-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#F2F4F1] flex items-center justify-center text-[#2C3E35] font-bold text-sm">4</div>
            <div>
                <h3 class="text-lg font-serif text-[#2C3E35]">Flutterwave Payments</h3>
                <p class="text-xs text-[#6B7C70]">Processes card, bank transfer, and mobile money purchases</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <?= badge($checks['flw']) ?>
            <?php if ($checks['flw']): ?>
                <?= badge($checks['flw_live'], 'Live mode', 'Sandbox mode') ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="text-sm space-y-2">
        <?php if (!$checks['flw']): ?>
        <p>Go to <a href="settings.php#payment" class="text-[#D97757] underline font-medium">Settings → Payment Configuration</a>.</p>
        <ol class="list-decimal list-inside text-[#6B7C70] space-y-1">
            <li>Sign up or log in at <a href="https://dashboard.flutterwave.com" target="_blank" class="text-[#D97757] underline">dashboard.flutterwave.com</a></li>
            <li>Go to Settings → API Keys — copy Public Key, Secret Key, and Encryption Key</li>
            <li>Go to Settings → Webhooks — paste your webhook URL and set a secret hash</li>
            <li>Webhook URL: <code class="font-mono text-xs bg-[#F2F4F1] px-1 py-0.5 rounded"><?= htmlspecialchars($appUrl) ?>/backend/api/flutterwave-webhook.php</code></li>
        </ol>
        <?php else: ?>
        <p class="text-emerald-700"><i class="fas fa-check mr-1"></i> Keys configured.</p>
        <?php if (!$checks['flw_live']): ?>
        <p class="text-amber-700"><i class="fas fa-exclamation-triangle mr-1"></i> Environment is set to <strong>Sandbox</strong>. Switch to <strong>Production</strong> in <a href="settings.php" class="text-[#D97757] underline">Settings → Payment</a> before going live.</p>
        <?php endif; ?>
        <p class="text-[#6B7C70] text-xs">Webhook URL (register in Flutterwave dashboard):
            <code class="font-mono bg-[#F2F4F1] px-1 py-0.5 rounded"><?= htmlspecialchars($appUrl) ?>/backend/api/flutterwave-webhook.php</code></p>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     5. AI
════════════════════════════════════════════════════════════════════════════ -->
<div class="luxury-card p-6">
    <div class="flex items-start justify-between gap-4 mb-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#F2F4F1] flex items-center justify-center text-[#2C3E35] font-bold text-sm">5</div>
            <div>
                <h3 class="text-lg font-serif text-[#2C3E35]">AI Treatment Plan Engine</h3>
                <p class="text-xs text-[#6B7C70]">Generates personalised protocols and member daily plans</p>
            </div>
        </div>
        <?= badge($checks['ai']) ?>
    </div>
    <div class="text-sm">
        <?php if (!$checks['ai']): ?>
        <p>Go to <a href="settings.php#ai" class="text-[#D97757] underline font-medium">Settings → AI Assessment</a>. Recommended: Anthropic Claude API.</p>
        <div class="mt-2 bg-[#F9FAF9] border border-[#EAEAE5] rounded-xl p-3 font-mono text-xs text-[#2C3E35] space-y-1">
            <div>Provider: <strong>anthropic</strong></div>
            <div>Model: <strong>claude-sonnet-4-6</strong></div>
            <div>Key format: <strong>sk-ant-api03-…</strong></div>
        </div>
        <?php else: ?>
        <p class="text-emerald-700"><i class="fas fa-check mr-1"></i> Provider: <code class="font-mono text-xs"><?= htmlspecialchars($settings->get('ai_provider', '')) ?></code> · Model: <code class="font-mono text-xs"><?= htmlspecialchars($settings->get('ai_model', '')) ?></code></p>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     6. CRON JOBS
════════════════════════════════════════════════════════════════════════════ -->
<div class="luxury-card p-6">
    <div class="flex items-start justify-between gap-4 mb-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#F2F4F1] flex items-center justify-center text-[#2C3E35] font-bold text-sm">6</div>
            <div>
                <h3 class="text-lg font-serif text-[#2C3E35]">Cron Jobs</h3>
                <p class="text-xs text-[#6B7C70]">Scheduled tasks for notification journeys, A/B posteriors, and plan generation</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-700"><i class="fas fa-server"></i> Server setup required</span>
    </div>

    <div class="space-y-4 text-sm">
        <div class="border border-[#EAEAE5] rounded-xl overflow-hidden">
            <div class="bg-[#F9FAF9] px-4 py-2.5 border-b border-[#EAEAE5] flex items-center justify-between">
                <span class="font-medium text-[#2C3E35] text-xs uppercase tracking-wider">Option A — Run the installer (Linux/Mac)</span>
            </div>
            <div class="p-4">
                <p class="text-[#4A5D52] mb-2">SSH into your server as the web user and run once:</p>
                <div class="relative">
                    <pre class="bg-[#2C3E35] text-[#FDFCF8] rounded-xl p-4 text-xs font-mono overflow-x-auto">sudo -u www-data bash <?= htmlspecialchars($absRoot) ?>/backend/cron/install-cron.sh</pre>
                    <button onclick="copyCode(this)" data-text="sudo -u www-data bash <?= htmlspecialchars($absRoot) ?>/backend/cron/install-cron.sh"
                        class="absolute top-2 right-2 text-white/60 hover:text-white text-xs px-2 py-1 rounded border border-white/20 hover:border-white/50 transition-colors">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="border border-[#EAEAE5] rounded-xl overflow-hidden">
            <div class="bg-[#F9FAF9] px-4 py-2.5 border-b border-[#EAEAE5] flex items-center justify-between">
                <span class="font-medium text-[#2C3E35] text-xs uppercase tracking-wider">Option B — Add manually to crontab</span>
                <button onclick="copyAllCron()" class="text-xs text-[#D97757] font-medium hover:underline"><i class="fas fa-copy mr-1"></i>Copy all</button>
            </div>
            <div class="p-4">
                <p class="text-[#6B7C70] mb-3 text-xs">Run <code class="font-mono bg-[#F2F4F1] px-1 rounded">crontab -e</code> as the web user and paste:</p>
                <pre id="cronBlock" class="bg-[#2C3E35] text-[#FDFCF8] rounded-xl p-4 text-xs font-mono overflow-x-auto leading-5"><?php
$p = htmlspecialchars($absRoot);
echo "# 1wellness — do not edit this block, re-run install-cron.sh to update\n";
echo "*/5 * * * * php {$p}/backend/cron/journeys.php >> {$p}/backend/logs/journeys.log 2>&1\n";
echo "* * * * * php {$p}/backend/cron/send_notifications.php >> {$p}/backend/logs/send_notifications.log 2>&1\n";
echo "*/10 * * * * php {$p}/backend/cron/process_webhooks.php >> {$p}/backend/logs/process_webhooks.log 2>&1\n";
echo "*/30 * * * * php {$p}/backend/cron/recompute_posteriors.php >> {$p}/backend/logs/recompute_posteriors.log 2>&1\n";
echo "0 */6 * * * php {$p}/backend/cron/reconcile_payments.php >> {$p}/backend/logs/reconcile_payments.log 2>&1\n";
echo "0 2 * * 0 php {$p}/backend/cron/generate_weekly_plans.php >> {$p}/backend/logs/generate_weekly_plans.log 2>&1\n";
echo "30 3 * * * php {$p}/backend/cron/ai_diagnostics.php >> {$p}/backend/logs/ai_diagnostics.log 2>&1";
?></pre>
            </div>
        </div>

        <div class="bg-[#F9FAF9] border border-[#EAEAE5] rounded-xl p-4 text-xs text-[#6B7C70] space-y-1">
            <p class="font-semibold text-[#2C3E35]">What each job does:</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-1 mt-2">
                <div><code class="font-mono">journeys.php</code> — evaluates who to notify next (every 5 min)</div>
                <div><code class="font-mono">send_notifications.php</code> — dispatches queued messages (every 1 min)</div>
                <div><code class="font-mono">process_webhooks.php</code> — retries failed webhook deliveries (every 10 min)</div>
                <div><code class="font-mono">recompute_posteriors.php</code> — updates A/B Thompson Sampling priors (every 30 min)</div>
                <div><code class="font-mono">reconcile_payments.php</code> — verifies Flutterwave charges vs DB (every 6 hr)</div>
                <div><code class="font-mono">generate_weekly_plans.php</code> — builds member week plans (Sunday 02:00)</div>
                <div><code class="font-mono">ai_diagnostics.php</code> — nightly health check on AI + DB (03:30 daily)</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     7. WHATSAPP (Optional)
════════════════════════════════════════════════════════════════════════════ -->
<div class="luxury-card p-6">
    <div class="flex items-start justify-between gap-4 mb-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#F2F4F1] flex items-center justify-center text-[#6B7C70] font-bold text-sm">7</div>
            <div>
                <h3 class="text-lg font-serif text-[#2C3E35]">WhatsApp Notifications <span class="text-xs font-sans font-normal text-[#6B7C70] ml-2">Optional</span></h3>
                <p class="text-xs text-[#6B7C70]">Recovery and retention messages via WhatsApp. Higher open rates than email.</p>
            </div>
        </div>
        <?= optBadge($checks['wa'], 'Enabled', 'Disabled') ?>
    </div>
    <div class="space-y-3 text-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="border border-[#EAEAE5] rounded-xl p-4">
                <p class="font-semibold text-[#2C3E35] mb-2"><i class="fab fa-meta mr-1"></i> Option A: Meta Cloud API (recommended)</p>
                <ol class="text-[#6B7C70] text-xs space-y-1 list-decimal list-inside">
                    <li>Create a Meta Business account at <a href="https://business.facebook.com" target="_blank" class="text-[#D97757]">business.facebook.com</a></li>
                    <li>Set up WhatsApp Business at <a href="https://developers.facebook.com/docs/whatsapp" target="_blank" class="text-[#D97757]">developers.facebook.com</a></li>
                    <li>Complete Business Verification (2–5 business days)</li>
                    <li>Create a System User with <em>WhatsApp Business</em> permissions</li>
                    <li>Generate a permanent access token</li>
                    <li>Find your Phone Number ID in the dashboard</li>
                    <li>Paste both into <a href="settings.php#notifications" class="text-[#D97757]">Settings → Notifications → WhatsApp</a></li>
                </ol>
            </div>
            <div class="border border-[#EAEAE5] rounded-xl p-4">
                <p class="font-semibold text-[#2C3E35] mb-2"><i class="fas fa-phone-alt mr-1"></i> Option B: Twilio</p>
                <ol class="text-[#6B7C70] text-xs space-y-1 list-decimal list-inside">
                    <li>Sign up at <a href="https://twilio.com" target="_blank" class="text-[#D97757]">twilio.com</a></li>
                    <li>Enable the WhatsApp Sandbox (instant) or submit for approval</li>
                    <li>Copy Account SID, Auth Token, and WhatsApp sender number</li>
                    <li>Set <em>Provider</em> to Twilio in <a href="settings.php#notifications" class="text-[#D97757]">Settings → Notifications → WhatsApp</a></li>
                </ol>
            </div>
        </div>
        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <strong>Meta lead time:</strong> Business Verification typically takes 2–5 business days. Start this process early.
            Outside the 24-hour messaging window, Meta requires pre-approved message templates.
        </p>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     8. SMS (Optional)
════════════════════════════════════════════════════════════════════════════ -->
<div class="luxury-card p-6">
    <div class="flex items-start justify-between gap-4 mb-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#F2F4F1] flex items-center justify-center text-[#6B7C70] font-bold text-sm">8</div>
            <div>
                <h3 class="text-lg font-serif text-[#2C3E35]">SMS Notifications <span class="text-xs font-sans font-normal text-[#6B7C70] ml-2">Optional</span></h3>
                <p class="text-xs text-[#6B7C70]">Fallback channel for members without WhatsApp</p>
            </div>
        </div>
        <?= optBadge($checks['sms'], 'Enabled', 'Disabled') ?>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div class="border border-[#EAEAE5] rounded-xl p-4">
            <p class="font-semibold text-[#2C3E35] mb-2">Twilio (global)</p>
            <ol class="text-[#6B7C70] text-xs space-y-1 list-decimal list-inside">
                <li>Get Account SID + Auth Token from <a href="https://console.twilio.com" target="_blank" class="text-[#D97757]">console.twilio.com</a></li>
                <li>Buy or verify a phone number</li>
                <li>Enter credentials in <a href="settings.php#notifications" class="text-[#D97757]">Settings → Notifications → SMS</a></li>
            </ol>
        </div>
        <div class="border border-[#EAEAE5] rounded-xl p-4">
            <p class="font-semibold text-[#2C3E35] mb-2">Termii (Nigeria-optimised)</p>
            <ol class="text-[#6B7C70] text-xs space-y-1 list-decimal list-inside">
                <li>Sign up at <a href="https://termii.com" target="_blank" class="text-[#D97757]">termii.com</a></li>
                <li>Get your API key from the dashboard</li>
                <li>Set provider to Termii and paste key in <a href="settings.php#notifications" class="text-[#D97757]">Settings → SMS</a></li>
            </ol>
            <p class="text-xs text-emerald-700 mt-2"><i class="fas fa-info-circle mr-1"></i> Better delivery rates and lower cost for Nigerian numbers than Twilio.</p>
        </div>
    </div>
</div>

</div><!-- /max-w-4xl -->

<script>
function copyCode(btn) {
    const text = btn.getAttribute('data-text');
    navigator.clipboard.writeText(text).then(() => {
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 2000);
    });
}

function copyAllCron() {
    const text = document.getElementById('cronBlock').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.currentTarget;
        btn.innerHTML = '<i class="fas fa-check mr-1"></i>Copied!';
        setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy mr-1"></i>Copy all'; }, 2000);
    });
}
</script>

<?php include 'includes/footer.php'; ?>
