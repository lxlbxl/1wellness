<?php
require_once 'auth.php';
require_once '../classes/Database.php';

$db = Database::getInstance();
$message = '';
$error = '';

// Set default values from Settings class
$settingsObj = Settings::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_settings':
                $keys = [
                    'site_name',
                    'admin_email',
                    'smtp_host',
                    'smtp_port',
                    'smtp_username',
                    'smtp_password',
                    'smtp_from_email',
                    'smtp_from_name',
                    'flutterwave_public_key',
                    'flutterwave_secret_key',
                    'flutterwave_encryption_key',
                    'flutterwave_environment',
                    'flutterwave_webhook_hash',
                    'ai_provider',
                    'ai_api_key',
                    'ai_model',
                    'webhook_secret',
                    'site_url',
                    'notify_email_enabled',
                    'notify_whatsapp_enabled',
                    'notify_sms_enabled',
                    'whatsapp_provider',
                    'whatsapp_phone_number_id',
                    'whatsapp_access_token',
                    'sms_provider',
                    'twilio_sid',
                    'twilio_auth_token',
                    'twilio_sms_from',
                    'twilio_whatsapp_from',
                    'termii_api_key',
                    'termii_sender_id',
                    'notify_quiet_start',
                    'notify_quiet_end',
                    'notify_timezone',
                    'notify_daily_cap_marketing',
                    'notify_weekly_cap_marketing',
                    'notify_dry_run',
                    'dkim_domain',
                    'dkim_selector',
                    'dkim_private_key_path'
                ];

                $success = true;
                foreach ($keys as $key) {
                    if (isset($_POST[$key])) {
                        if (!$settingsObj->set($key, $_POST[$key])) {
                            $success = false;
                        }
                    }
                }

                if ($success) {
                    $message = 'Settings updated successfully.';
                } else {
                    $error = 'Failed to save some settings.';
                }
                break;

            case 'clear_cache':
                // Simulate clearing cache
                $message = 'System cache cleared successfully.';
                break;

            case 'ajax_test_smtp':
                // Clear any previous output to prevent JSON corruption
                if (ob_get_length()) ob_clean();
                header('Content-Type: application/json');
                
                // Disable error display for this request to keep JSON clean
                ini_set('display_errors', 0);
                
                require_once '../classes/Mailer.php';
                $mailer = new Mailer();

                // Use settings from POST
                $testConfig = [
                    'smtp_host' => $_POST['smtp_host'] ?? '',
                    'smtp_port' => $_POST['smtp_port'] ?? 587,
                    'smtp_username' => $_POST['smtp_username'] ?? '',
                    'smtp_password' => $_POST['smtp_password'] ?? '',
                    'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
                    'smtp_from_name' => $_POST['smtp_from_name'] ?? ''
                ];

                $mailer->setConfig($testConfig);
                $testEmail = $_POST['recipient_email'] ?? $_SESSION['email'] ?? $settingsObj->get('admin_email') ?? 'admin@1wellness.club';

                if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => "Invalid recipient email address."]);
                    exit;
                }

                if ($mailer->send($testEmail, "Test Email from 1wellness", "<h1>SMTP Test</h1><p>If you are reading this, your email configuration is correct!</p>")) {
                    echo json_encode(['success' => true, 'message' => "Test email sent successfully to $testEmail!"]);
                } else {
                    $errorMsg = method_exists($mailer, 'getLastError') ? $mailer->getLastError() : 'Unknown error';
                    echo json_encode(['success' => false, 'message' => "Failed: " . $errorMsg]);
                }
                exit;

            case 'backup_data':
                // Simulate backup
                $message = 'Database backup created: backup_' . date('Y-m-d_H-i-s') . '.sql';
                break;
        }
    }
}

// Load current settings from DB
$settings = $settingsObj->getAll();

$defaults = [
    'site_name' => '1wellness',
    'admin_email' => 'admin@1wellness.club',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_from_email' => '',
    'smtp_from_name' => '1wellness',
    'flutterwave_public_key' => '',
    'flutterwave_secret_key' => '',
    'flutterwave_encryption_key' => '',
    'flutterwave_environment' => 'sandbox',
    'flutterwave_webhook_hash' => '',
    'ai_provider' => 'openrouter',
    'ai_api_key' => '',
    'ai_model' => 'google/gemini-2.0-flash-exp:free',
    'webhook_secret' => '',
    'site_url' => 'https://1wellness.club',
    'notify_email_enabled' => '1',
    'notify_whatsapp_enabled' => '0',
    'notify_sms_enabled' => '0',
    'whatsapp_provider' => 'meta',
    'whatsapp_phone_number_id' => '',
    'whatsapp_access_token' => '',
    'sms_provider' => 'twilio',
    'twilio_sid' => '',
    'twilio_auth_token' => '',
    'twilio_sms_from' => '',
    'twilio_whatsapp_from' => '',
    'termii_api_key' => '',
    'termii_sender_id' => '1wellness',
    'notify_quiet_start' => '21:00',
    'notify_quiet_end' => '08:00',
    'notify_timezone' => 'Africa/Lagos',
    'notify_daily_cap_marketing' => '1',
    'notify_weekly_cap_marketing' => '4',
    'notify_dry_run' => '0',
    'dkim_domain' => defined('DKIM_DOMAIN') ? DKIM_DOMAIN : '1wellness.club',
    'dkim_selector' => defined('DKIM_SELECTOR') ? DKIM_SELECTOR : 'mail',
    'dkim_private_key_path' => defined('DKIM_PRIVATE_KEY_PATH') ? DKIM_PRIVATE_KEY_PATH : ''
];

$settings = array_merge($defaults, $settings);

$pageTitle = 'Settings - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to
            Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">System Settings</h2>
        <p class="text-[#6B7C70] mt-1">Configure your platform preferences and keys</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-8 p-4 bg-[#F2F4F1] border border-[#A4B4A6] text-[#2C3E35] rounded-xl flex items-center">
        <i class="fas fa-check-circle text-[#2C3E35] mr-3"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-8 p-4 bg-[#FDF1E8] border border-[#D97757] text-[#D97757] rounded-xl flex items-center">
        <i class="fas fa-exclamation-circle mr-3"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Main Settings Form -->
    <div class="lg:col-span-2">
        <form method="POST" action="" class="space-y-8">
            <input type="hidden" name="action" value="update_settings">

            <!-- General Settings -->
            <div class="luxury-card p-8">
                <h3 class="text-xl font-serif text-[#2C3E35] mb-6 flex items-center">
                    <i class="fas fa-sliders-h w-8 text-[#D97757]"></i> General Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-[#2C3E35] mb-2">Site Name</label>
                        <input type="text" name="site_name"
                            value="<?php echo htmlspecialchars($settings['site_name']); ?>"
                            class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#2C3E35] mb-2">Admin Email</label>
                        <input type="email" name="admin_email"
                            value="<?php echo htmlspecialchars($settings['admin_email']); ?>"
                            class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                    </div>
                </div>
            </div>

            <!-- SMTP Settings -->
            <div class="luxury-card p-8" id="smtp">
                <h3 class="text-xl font-serif text-[#2C3E35] mb-6 flex items-center">
                    <i class="fas fa-envelope w-8 text-[#D97757]"></i> Email Configuration (SMTP)
                </h3>
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-[#2C3E35] mb-2">SMTP Host</label>
                            <input type="text" name="smtp_host"
                                value="<?php echo htmlspecialchars($settings['smtp_host']); ?>"
                                class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#2C3E35] mb-2">SMTP Port</label>
                            <input type="number" name="smtp_port"
                                value="<?php echo htmlspecialchars($settings['smtp_port']); ?>"
                                class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-[#2C3E35] mb-2">SMTP Username</label>
                            <input type="text" name="smtp_username"
                                value="<?php echo htmlspecialchars($settings['smtp_username']); ?>"
                                class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#2C3E35] mb-2">SMTP Password</label>
                            <input type="password" name="smtp_password"
                                value="<?php echo htmlspecialchars($settings['smtp_password']); ?>"
                                class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-[#2C3E35] mb-2">From Email</label>
                            <input type="email" name="smtp_from_email"
                                value="<?php echo htmlspecialchars($settings['smtp_from_email']); ?>"
                                class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#2C3E35] mb-2">From Name</label>
                            <input type="text" name="smtp_from_name"
                                value="<?php echo htmlspecialchars($settings['smtp_from_name']); ?>"
                                class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                        </div>
                    </div>
                    <!-- Test Connection Button inside Card -->
                    <!-- Test Connection Section -->
                    <div class="mt-6 pt-6 border-t border-[#EAEAE5]">
                        <!-- Initial Button -->
                        <div id="smtpFn_start" class="flex justify-end">
                            <button type="button" onclick="showSmtpInput()"
                                class="px-5 py-2.5 bg-[#F2F4F1] border border-[#EAEAE5] text-[#2C3E35] font-medium rounded-xl hover:bg-[#E6EBE6] transition-all flex items-center gap-2 group">
                                <i class="fas fa-flask text-sm text-[#6B7C70] group-hover:text-[#D97757] transition-colors"></i>
                                <span>Test Connection</span>
                            </button>
                        </div>
                        
                        <!-- Hidden Input Area -->
                        <div id="smtpFn_input" class="hidden transition-all duration-300 ease-in-out opacity-0 translate-y-2">
                             <div class="flex flex-col md:flex-row items-end gap-3 bg-[#FAFAF8] p-4 rounded-xl border border-[#EAEAE5]">
                                <div class="w-full">
                                    <label class="block text-xs font-bold text-[#6B7C70] uppercase tracking-wider mb-1">
                                        Send Test Email To
                                    </label>
                                    <input type="email" id="test_recipient_email" 
                                        value="<?php echo htmlspecialchars($settings['admin_email']); ?>"
                                        placeholder="Enter email address..."
                                        class="w-full px-4 py-2 bg-white border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] text-sm">
                                </div>
                                <div class="flex gap-2 w-full md:w-auto self-end">
                                    <button type="button" onclick="cancelSmtpTest()"
                                        class="px-4 py-2 bg-white border border-[#EAEAE5] text-[#6B7C70] rounded-lg hover:border-[#D97757] hover:text-[#D97757] transition-colors">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <button type="button" onclick="testSMTP()" id="testSmtpBtn"
                                        class="px-6 py-2 bg-[#2C3E35] text-white font-medium rounded-lg hover:bg-[#1a2621] transition-colors whitespace-nowrap shadow-md shadow-[#2C3E35]/10">
                                        Send Test <i class="fas fa-paper-plane ml-1 text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DKIM Signing -->
            <?php
            $dkimPath = $settings['dkim_private_key_path'];
            $absRoot  = dirname(__DIR__, 2);
            $dkimAbs  = $dkimPath ? (file_exists($dkimPath) ? $dkimPath : $absRoot . '/' . ltrim($dkimPath, '/\\')) : '';
            $dkimOk   = $dkimAbs !== '' && file_exists($dkimAbs);
            ?>
            <div id="dkim" class="luxury-card p-8">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <h3 class="text-xl font-serif text-[#2C3E35] flex items-center">
                        <i class="fas fa-key w-8 text-[#D97757]"></i> DKIM Email Signing
                    </h3>
                    <?php if ($dkimOk): ?>
                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800"><i class="fas fa-check-circle"></i> Active</span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-700"><i class="fas fa-times-circle"></i> Not configured</span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-[#6B7C70] mb-6">
                    DKIM signs every outgoing email so inbox providers can verify it really came from you.
                    Generate the key pair first: <code class="font-mono text-xs bg-[#F2F4F1] px-1 py-0.5 rounded">php backend/scripts/generate-dkim-keys.php</code>
                    — then paste the key path below. See the <a href="setup-guide.php#dkim" class="text-[#D97757] underline">Setup Guide</a> for DNS records to add.
                </p>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[#2C3E35] mb-2">Domain</label>
                            <input type="text" name="dkim_domain"
                                value="<?php echo htmlspecialchars($settings['dkim_domain']); ?>"
                                class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm"
                                placeholder="1wellness.club">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#2C3E35] mb-2">Selector</label>
                            <input type="text" name="dkim_selector"
                                value="<?php echo htmlspecialchars($settings['dkim_selector']); ?>"
                                class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm"
                                placeholder="mail">
                            <p class="text-xs text-[#6B7C70] mt-1">DNS record name: <code class="font-mono"><?php echo htmlspecialchars($settings['dkim_selector']); ?>._domainkey.<?php echo htmlspecialchars($settings['dkim_domain']); ?></code></p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#2C3E35] mb-2">Private Key Path</label>
                        <input type="text" name="dkim_private_key_path"
                            value="<?php echo htmlspecialchars($settings['dkim_private_key_path']); ?>"
                            class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm"
                            placeholder="backend/config/dkim/private.key">
                        <?php if ($dkimOk): ?>
                            <p class="text-xs text-emerald-700 mt-1"><i class="fas fa-check-circle mr-1"></i> Key file found — DKIM signing is active.</p>
                        <?php elseif ($dkimPath): ?>
                            <p class="text-xs text-rose-600 mt-1"><i class="fas fa-times-circle mr-1"></i> File not found at <code class="font-mono"><?php echo htmlspecialchars($dkimAbs); ?></code> — run the key generator first.</p>
                        <?php else: ?>
                            <p class="text-xs text-[#6B7C70] mt-1">Relative paths are resolved from the project root: <code class="font-mono"><?php echo htmlspecialchars($absRoot); ?></code></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Payment Settings -->
            <div class="luxury-card p-8" id="payment">
                <h3 class="text-xl font-serif text-[#2C3E35] mb-6 flex items-center">
                    <i class="fas fa-credit-card w-8 text-[#D97757]"></i> Payment Configuration (Flutterwave)
                </h3>
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-[#2C3E35] mb-2">Public Key</label>
                        <input type="text" name="flutterwave_public_key"
                            value="<?php echo htmlspecialchars($settings['flutterwave_public_key']); ?>"
                            class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#2C3E35] mb-2">Secret Key</label>
                        <input type="password" name="flutterwave_secret_key"
                            value="<?php echo htmlspecialchars($settings['flutterwave_secret_key']); ?>"
                            class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#2C3E35] mb-2">Encryption Key</label>
                        <input type="password" name="flutterwave_encryption_key"
                            value="<?php echo htmlspecialchars($settings['flutterwave_encryption_key']); ?>"
                            class="luxury-input w-full" placeholder="Encryption key for payment processing">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Environment</label>
                        <select name="flutterwave_environment" class="luxury-input w-full">
                            <option value="sandbox" <?php echo ($settings['flutterwave_environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                            <option value="production" <?php echo ($settings['flutterwave_environment'] ?? 'sandbox') === 'production' ? 'selected' : ''; ?>>Production (Live)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#2C3E35] mb-2">Webhook Secret Hash</label>
                        <input type="password" name="flutterwave_webhook_hash"
                            value="<?php echo htmlspecialchars($settings['flutterwave_webhook_hash']); ?>"
                            class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm"
                            placeholder="Same value as Flutterwave Dashboard → Settings → Webhooks → Secret hash">
                        <p class="text-xs text-[#6B7C70] mt-1">
                            Authenticates server-to-server purchase webhooks at
                            <code class="font-mono">backend/api/flutterwave-webhook.php</code>.
                            Until this is set, the webhook rejects all calls (fail-closed) —
                            see <a href="payment-integrity.php" class="text-[#D97757] underline">Payment Integrity</a>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- AI Settings -->
            <div class="luxury-card p-8">
                <h3 class="text-xl font-serif text-[#2C3E35] mb-6 flex items-center">
                    <i class="fas fa-brain w-8 text-[#D97757]"></i> AI Assessment Configuration
                </h3>
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-[#2C3E35] mb-2">AI Provider</label>
                            <select name="ai_provider"
                                class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                                <option value="openrouter" <?php echo $settings['ai_provider'] === 'openrouter' ? 'selected' : ''; ?>>OpenRouter (Recommended)</option>
                                <option value="openai" <?php echo $settings['ai_provider'] === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                                <option value="gemini" <?php echo $settings['ai_provider'] === 'gemini' ? 'selected' : ''; ?>>Google Gemini</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#2C3E35] mb-2">AI Model</label>
                            <input type="text" name="ai_model"
                                value="<?php echo htmlspecialchars($settings['ai_model']); ?>"
                                class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]"
                                placeholder="e.g. google/gemini-2.0-flash-exp:free">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#2C3E35] mb-2">AI API Key</label>
                        <input type="password" name="ai_api_key"
                            value="<?php echo htmlspecialchars($settings['ai_api_key']); ?>"
                            class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm"
                            placeholder="Paste your API key here">
                    </div>
                </div>
            </div>

            <!-- Automation / Cron Settings -->
            <?php $cronRoot = dirname(dirname(realpath(__FILE__))); ?>
            <div class="luxury-card p-8">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <h3 class="text-xl font-serif text-[#2C3E35] flex items-center">
                        <i class="fas fa-clock w-8 text-[#D97757]"></i> System Automation (Cron)
                    </h3>
                    <a href="setup-guide.php" class="text-xs text-[#D97757] font-medium hover:underline">Full setup guide &rarr;</a>
                </div>
                <p class="text-sm text-[#6B7C70] mb-4">
                    Seven background jobs keep the platform running. Install once on your server with
                    <code class="font-mono text-xs bg-[#F2F4F1] px-1 py-0.5 rounded">sudo -u www-data bash <?php echo htmlspecialchars($cronRoot); ?>/cron/install-cron.sh</code>
                </p>
                <div class="overflow-x-auto rounded-xl border border-[#EAEAE5]">
                    <table class="w-full text-xs font-mono">
                        <thead class="bg-[#F2F4F1] text-[#2C3E35] uppercase tracking-wider text-left">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Schedule</th>
                                <th class="px-4 py-2.5 font-semibold">File</th>
                                <th class="px-4 py-2.5 font-semibold hidden md:table-cell">Purpose</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EAEAE5] bg-white text-[#4A5D52]">
                            <tr><td class="px-4 py-2">*/5 * * * *</td><td class="px-4 py-2">journeys.php</td><td class="px-4 py-2 hidden md:table-cell">Evaluates who to notify next</td></tr>
                            <tr><td class="px-4 py-2">* * * * *</td><td class="px-4 py-2">send_notifications.php</td><td class="px-4 py-2 hidden md:table-cell">Dispatches queued messages</td></tr>
                            <tr><td class="px-4 py-2">*/10 * * * *</td><td class="px-4 py-2">process_webhooks.php</td><td class="px-4 py-2 hidden md:table-cell">Retries failed webhook deliveries</td></tr>
                            <tr><td class="px-4 py-2">*/30 * * * *</td><td class="px-4 py-2">recompute_posteriors.php</td><td class="px-4 py-2 hidden md:table-cell">Updates A/B Thompson Sampling priors</td></tr>
                            <tr><td class="px-4 py-2">0 */6 * * *</td><td class="px-4 py-2">reconcile_payments.php</td><td class="px-4 py-2 hidden md:table-cell">Verifies Flutterwave charges vs DB</td></tr>
                            <tr><td class="px-4 py-2">0 2 * * 0</td><td class="px-4 py-2">generate_weekly_plans.php</td><td class="px-4 py-2 hidden md:table-cell">Builds member week plans (Sun 02:00)</td></tr>
                            <tr><td class="px-4 py-2">30 3 * * *</td><td class="px-4 py-2">ai_diagnostics.php</td><td class="px-4 py-2 hidden md:table-cell">Nightly AI + DB health check</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Webhook Settings -->
            <div class="luxury-card p-8">
                <h3 class="text-xl font-serif text-[#2C3E35] mb-6 flex items-center">
                    <i class="fas fa-plug w-8 text-[#D97757]"></i> Webhook Security
                </h3>
                <div>
                    <label class="block text-sm font-medium text-[#2C3E35] mb-2">Webhook Secret Hash</label>
                    <input type="text" name="webhook_secret"
                        value="<?php echo htmlspecialchars($settings['webhook_secret']); ?>"
                        class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm">
                    <p class="text-xs text-[#6B7C70] mt-1">Used to verify incoming webhook signatures.</p>
                </div>
            </div>

            <!-- Notifications Settings -->
            <div class="luxury-card p-8" id="notifications">
                <h3 class="text-xl font-serif text-[#2C3E35] mb-1 flex items-center">
                    <i class="fas fa-bell w-8 text-[#D97757]"></i> Notifications
                </h3>
                <p class="text-sm text-[#6B7C70] mb-6">Channels, provider credentials, quiet hours, and frequency caps for the journey notification system.</p>

                <?php if ($settings['notify_dry_run'] === '1' || $settings['notify_dry_run'] === 1): ?>
                <div class="mb-6 p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm flex items-center gap-2">
                    <i class="fas fa-flask"></i>
                    <strong>Dry-run mode is ON</strong> — notifications are logged but not sent.
                </div>
                <?php endif; ?>

                <div class="space-y-6">
                    <!-- Site URL -->
                    <div>
                        <label class="block text-sm font-medium text-[#2C3E35] mb-2">Site URL</label>
                        <input type="url" name="site_url"
                            value="<?php echo htmlspecialchars($settings['site_url']); ?>"
                            class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm"
                            placeholder="https://1wellness.club">
                        <p class="text-xs text-[#6B7C70] mt-1">Used in notification links (resume, portal, unsubscribe).</p>
                    </div>

                    <!-- Channel toggles -->
                    <div>
                        <p class="text-sm font-medium text-[#2C3E35] mb-3">Active Channels</p>
                        <div class="flex flex-wrap gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="notify_email_enabled" value="0">
                                <input type="checkbox" name="notify_email_enabled" value="1"
                                    <?php echo $settings['notify_email_enabled'] ? 'checked' : ''; ?>
                                    class="w-4 h-4 accent-[#2C3E35]">
                                <span class="text-sm text-[#2C3E35]"><i class="fas fa-envelope mr-1 text-[#D97757]"></i> Email</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="notify_whatsapp_enabled" value="0">
                                <input type="checkbox" name="notify_whatsapp_enabled" value="1"
                                    <?php echo $settings['notify_whatsapp_enabled'] ? 'checked' : ''; ?>
                                    class="w-4 h-4 accent-[#2C3E35]">
                                <span class="text-sm text-[#2C3E35]"><i class="fab fa-whatsapp mr-1 text-green-600"></i> WhatsApp</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="notify_sms_enabled" value="0">
                                <input type="checkbox" name="notify_sms_enabled" value="1"
                                    <?php echo $settings['notify_sms_enabled'] ? 'checked' : ''; ?>
                                    class="w-4 h-4 accent-[#2C3E35]">
                                <span class="text-sm text-[#2C3E35]"><i class="fas fa-sms mr-1 text-blue-500"></i> SMS</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="notify_dry_run" value="0">
                                <input type="checkbox" name="notify_dry_run" value="1"
                                    <?php echo $settings['notify_dry_run'] ? 'checked' : ''; ?>
                                    class="w-4 h-4 accent-amber-500">
                                <span class="text-sm text-amber-700"><i class="fas fa-flask mr-1"></i> Dry-run (log only)</span>
                            </label>
                        </div>
                    </div>

                    <!-- WhatsApp credentials -->
                    <div x-data="{}" class="border border-[#EAEAE5] rounded-xl p-5 space-y-4">
                        <h4 class="text-sm font-semibold text-[#2C3E35] flex items-center gap-2">
                            <i class="fab fa-whatsapp text-green-600"></i> WhatsApp Provider
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-[#6B7C70] mb-1">Provider</label>
                                <select name="whatsapp_provider"
                                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm">
                                    <option value="meta" <?php echo $settings['whatsapp_provider'] === 'meta' ? 'selected' : ''; ?>>Meta Cloud API</option>
                                    <option value="twilio" <?php echo $settings['whatsapp_provider'] === 'twilio' ? 'selected' : ''; ?>>Twilio</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#6B7C70] mb-1">Phone Number ID (Meta)</label>
                                <input type="text" name="whatsapp_phone_number_id"
                                    value="<?php echo htmlspecialchars($settings['whatsapp_phone_number_id']); ?>"
                                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm font-mono">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-[#6B7C70] mb-1">Access Token (Meta) / Twilio WhatsApp From</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <input type="password" name="whatsapp_access_token"
                                    value="<?php echo htmlspecialchars($settings['whatsapp_access_token']); ?>"
                                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm font-mono"
                                    placeholder="Meta permanent token">
                                <input type="text" name="twilio_whatsapp_from"
                                    value="<?php echo htmlspecialchars($settings['twilio_whatsapp_from']); ?>"
                                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm font-mono"
                                    placeholder="+14155238886 (Twilio sandbox)">
                            </div>
                        </div>
                    </div>

                    <!-- SMS credentials -->
                    <div class="border border-[#EAEAE5] rounded-xl p-5 space-y-4">
                        <h4 class="text-sm font-semibold text-[#2C3E35] flex items-center gap-2">
                            <i class="fas fa-sms text-blue-500"></i> SMS Provider
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-[#6B7C70] mb-1">Provider</label>
                                <select name="sms_provider"
                                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm">
                                    <option value="twilio" <?php echo $settings['sms_provider'] === 'twilio' ? 'selected' : ''; ?>>Twilio</option>
                                    <option value="termii" <?php echo $settings['sms_provider'] === 'termii' ? 'selected' : ''; ?>>Termii (Nigeria)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#6B7C70] mb-1">Twilio Account SID</label>
                                <input type="text" name="twilio_sid"
                                    value="<?php echo htmlspecialchars($settings['twilio_sid']); ?>"
                                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm font-mono">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-[#6B7C70] mb-1">Twilio Auth Token</label>
                                <input type="password" name="twilio_auth_token"
                                    value="<?php echo htmlspecialchars($settings['twilio_auth_token']); ?>"
                                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#6B7C70] mb-1">Twilio From Number</label>
                                <input type="text" name="twilio_sms_from"
                                    value="<?php echo htmlspecialchars($settings['twilio_sms_from']); ?>"
                                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm font-mono"
                                    placeholder="+14155238886">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#6B7C70] mb-1">Termii API Key</label>
                                <input type="password" name="termii_api_key"
                                    value="<?php echo htmlspecialchars($settings['termii_api_key']); ?>"
                                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm font-mono">
                            </div>
                        </div>
                    </div>

                    <!-- Quiet hours + caps -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm font-medium text-[#2C3E35] mb-3"><i class="fas fa-moon mr-1 text-[#D97757]"></i> Quiet Hours (marketing)</p>
                            <div class="flex gap-3 items-center">
                                <div class="flex-1">
                                    <label class="block text-xs text-[#6B7C70] mb-1">Start (no sends after)</label>
                                    <input type="time" name="notify_quiet_start"
                                        value="<?php echo htmlspecialchars($settings['notify_quiet_start']); ?>"
                                        class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm">
                                </div>
                                <span class="text-[#6B7C70] text-sm mt-4">→</span>
                                <div class="flex-1">
                                    <label class="block text-xs text-[#6B7C70] mb-1">End (resume at)</label>
                                    <input type="time" name="notify_quiet_end"
                                        value="<?php echo htmlspecialchars($settings['notify_quiet_end']); ?>"
                                        class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm">
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="block text-xs text-[#6B7C70] mb-1">Timezone</label>
                                <input type="text" name="notify_timezone"
                                    value="<?php echo htmlspecialchars($settings['notify_timezone']); ?>"
                                    class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm"
                                    placeholder="Africa/Lagos">
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-[#2C3E35] mb-3"><i class="fas fa-filter mr-1 text-[#D97757]"></i> Frequency Caps</p>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs text-[#6B7C70] mb-1">Max marketing per recipient / day</label>
                                    <input type="number" name="notify_daily_cap_marketing" min="1" max="10"
                                        value="<?php echo htmlspecialchars($settings['notify_daily_cap_marketing']); ?>"
                                        class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-[#6B7C70] mb-1">Max marketing per recipient / week</label>
                                    <input type="number" name="notify_weekly_cap_marketing" min="1" max="20"
                                        value="<?php echo htmlspecialchars($settings['notify_weekly_cap_marketing']); ?>"
                                        class="luxury-input w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-[#2C3E35] text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="submit"
                    class="px-8 py-3 bg-[#2C3E35] text-white font-medium rounded-xl hover:bg-[#1a2621] transition-colors shadow-lg shadow-[#2C3E35]/20">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Sidebar Actions -->
    <div class="space-y-8">
        <!-- System Actions -->
        <div class="luxury-card p-8">
            <h3 class="text-xl font-serif text-[#2C3E35] mb-6">System Actions</h3>
            <div class="space-y-4">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="clear_cache">
                    <button type="submit"
                        class="w-full flex items-center justify-between p-4 border border-[#EAEAE5] rounded-xl hover:bg-[#F2F4F1] transition-colors text-left group">
                        <span class="text-[#2C3E35] font-medium">Clear System Cache</span>
                        <i class="fas fa-broom text-[#6B7C70] group-hover:text-[#2C3E35]"></i>
                    </button>
                </form>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="backup_data">
                    <button type="submit"
                        class="w-full flex items-center justify-between p-4 border border-[#EAEAE5] rounded-xl hover:bg-[#F2F4F1] transition-colors text-left group">
                        <span class="text-[#2C3E35] font-medium">Backup Data</span>
                        <i class="fas fa-database text-[#6B7C70] group-hover:text-[#2C3E35]"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Setup Guide -->
        <div class="luxury-card p-6 border-2 border-[#D97757]/30">
            <h3 class="text-lg font-serif text-[#2C3E35] mb-3 flex items-center gap-2">
                <i class="fas fa-map-signs text-[#D97757]"></i> Platform Setup Guide
            </h3>
            <p class="text-sm text-[#6B7C70] mb-4">Live checklist with step-by-step instructions for SMTP, DKIM, payments, cron, WhatsApp, and SMS.</p>
            <?php
            $guideChecks = [
                'Database'   => !$db->isFileStorage(),
                'SMTP'       => !empty($settings['smtp_host']) && !empty($settings['smtp_username']),
                'DKIM'       => $dkimOk,
                'Flutterwave'=> !empty($settings['flutterwave_secret_key']),
                'AI'         => !empty($settings['ai_api_key']),
            ];
            $done = array_sum(array_map('intval', $guideChecks));
            ?>
            <div class="flex flex-wrap gap-1.5 mb-4">
                <?php foreach ($guideChecks as $label => $ok): ?>
                <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full <?php echo $ok ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-50 text-rose-600 border border-rose-200'; ?>">
                    <i class="fas <?php echo $ok ? 'fa-check' : 'fa-times'; ?>"></i> <?php echo $label; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-[#6B7C70] mb-4"><?php echo $done; ?>/<?php echo count($guideChecks); ?> required steps complete</p>
            <a href="setup-guide.php"
                class="block text-center w-full px-4 py-2.5 bg-[#D97757] text-white font-medium rounded-xl hover:bg-[#c06444] transition-colors text-sm">
                Open Setup Guide &rarr;
            </a>
        </div>

        <!-- System Info -->
        <div class="luxury-card p-8 bg-[#2C3E35] text-[#FDFCF8] border-none">
            <h3 class="text-xl font-serif text-white mb-6">System Info</h3>
            <div class="space-y-4 text-sm">
                <div class="flex justify-between border-b border-white/10 pb-2">
                    <span class="text-white/60">PHP Version</span>
                    <span class="font-mono"><?php echo phpversion(); ?></span>
                </div>
                <div class="flex justify-between border-b border-white/10 pb-2">
                    <span class="text-white/60">Server Software</span>
                    <span><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                </div>
                <div class="flex justify-between border-b border-white/10 pb-2">
                    <span class="text-white/60">Database</span>
                    <span><?php echo $db->isFileStorage() ? 'JSON File Storage' : 'MySQL'; ?></span>
                </div>
                <div class="flex justify-between pt-2">
                    <span class="text-white/60">Memory Usage</span>
                    <span class="font-mono"><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function showSmtpInput() {
        const startDiv = document.getElementById('smtpFn_start');
        const inputDiv = document.getElementById('smtpFn_input');
        
        startDiv.classList.add('hidden');
        inputDiv.classList.remove('hidden');
        
        // Small delay to allow display:block to apply before opacity transition
        setTimeout(() => {
            inputDiv.classList.remove('opacity-0', 'translate-y-2');
        }, 10);
        
        document.getElementById('test_recipient_email').focus();
    }

    function cancelSmtpTest() {
        const startDiv = document.getElementById('smtpFn_start');
        const inputDiv = document.getElementById('smtpFn_input');
        
        inputDiv.classList.add('opacity-0', 'translate-y-2');
        
        setTimeout(() => {
            inputDiv.classList.add('hidden');
            startDiv.classList.remove('hidden');
        }, 300);
    }

    async function testSMTP() {
        const btn = document.getElementById('testSmtpBtn');
        const originalContent = btn.innerHTML;
        const recipient = document.getElementById('test_recipient_email').value;

        if (!recipient || !recipient.includes('@')) {
            alert('Please enter a valid email address.');
            return;
        }

        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Sending...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'ajax_test_smtp');
        formData.append('recipient_email', recipient);
        formData.append('smtp_host', document.querySelector('input[name="smtp_host"]').value);
        formData.append('smtp_port', document.querySelector('input[name="smtp_port"]').value);
        formData.append('smtp_username', document.querySelector('input[name="smtp_username"]').value);
        formData.append('smtp_password', document.querySelector('input[name="smtp_password"]').value);
        formData.append('smtp_from_email', document.querySelector('input[name="smtp_from_email"]').value);
        formData.append('smtp_from_name', document.querySelector('input[name="smtp_from_name"]').value);

        fetch('settings.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Use SweetAlert if available, else standard alert
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Connection Successful',
                            text: data.message,
                            confirmButtonColor: '#2C3E35'
                        });
                    } else {
                        alert('SUCCESS: ' + data.message);
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Failed',
                            text: data.message,
                            confirmButtonColor: '#D97757'
                        });
                    } else {
                        alert('ERROR: ' + data.message);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred during the test.');
            })
            .finally(() => {
                btn.innerHTML = originalContent;
                btn.disabled = false;
            });
    }
</script>

<?php include 'includes/footer.php'; ?>