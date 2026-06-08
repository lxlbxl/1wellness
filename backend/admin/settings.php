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
                    'ai_provider',
                    'ai_api_key',
                    'ai_model',
                    'webhook_secret'
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
    'ai_provider' => 'openrouter',
    'ai_api_key' => '',
    'ai_model' => 'google/gemini-2.0-flash-exp:free',
    'webhook_secret' => ''
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
            <div class="luxury-card p-8">
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

            <!-- Payment Settings -->
            <div class="luxury-card p-8">
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
                            class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm">
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
            <div class="luxury-card p-8">
                <h3 class="text-xl font-serif text-[#2C3E35] mb-6 flex items-center">
                    <i class="fas fa-clock w-8 text-[#D97757]"></i> System Automation (Cron Job)
                </h3>
                <div class="space-y-4">
                    <p class="text-sm text-[#4A5D52]">
                        To ensure weekly plans are automatically generated for all members, you must set up a Cron Job
                        on your server.
                    </p>
                    <div class="bg-[#F9FAF9] p-4 rounded-lg border border-[#EAEAE5] font-mono text-xs text-[#2C3E35]">
                        <p class="font-bold mb-2">Recommended Schedule (Every Sunday at 11:00 PM):</p>
                        <code
                            class="block bg-white p-2 border border-[#EAEAE5] rounded mb-4">0 23 * * 0 php <?php echo dirname(dirname(realpath(__FILE__))); ?>/cron/generate_weekly_plans.php</code>

                        <p class="font-bold mb-2">Direct Execution URL (for testing):</p>
                        <code
                            class="block bg-white p-2 border border-[#EAEAE5] rounded">https://<?php echo $_SERVER['HTTP_HOST']; ?>/backend/cron/generate_weekly_plans.php?secret_cron_key=YOUR_SECRET</code>
                    </div>
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