<?php
require_once 'auth.php';
require_once '../classes/Database.php';
// Assuming Settings and FunnelDiscovery are autoloaded or included in auth.php or via composer if available
// If not, we might need to manually include them if they were relied upon.
// The original file had: require_once '../config/config.php'; // Included in auth.php
// And then used Settings::getInstance();
// Let's assume auth.php or config.php handles this as per original file.

// However, looking at the previous file content provided in view_file:
// $settings = Settings::getInstance();
// $discovery = new FunnelDiscovery();
// We need to ensure these classes are available.
// If they are not found, this script will fail. 
// Copied directly from original:
$settings = Settings::getInstance();
$discovery = new FunnelDiscovery();

$message = '';
$error = '';

// Helper function for sanitization
function sanitize_text_field($str)
{
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// Auto-sync funnels on page load
try {
    $syncResult = $discovery->syncFunnels();
    if ($syncResult['updated']) {
        $message = 'Auto-discovered ' . $syncResult['discovered'] . ' funnel(s) from filesystem.';
    }
} catch (Exception $e) {
    // Silently fail on auto-sync, but log if possible
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'sync_funnels') {
            // Manual funnel sync - FORCE update
            $syncResult = $discovery->syncFunnels(true);
            if ($syncResult['success']) {
                $message = 'Funnel sync completed! Discovered ' . $syncResult['discovered'] . ' funnel(s).';
            } else {
                $error = 'Funnel sync failed';
            }

        } elseif ($action === 'update_keys') {
            // Update Flutterwave Keys
            $publicKey = sanitize_text_field($_POST['flutterwave_public_key'] ?? '');
            $secretKey = sanitize_text_field($_POST['flutterwave_secret_key'] ?? '');

            $settings->set('flutterwave_public_key', $publicKey, 'string', 'Flutterwave Public Key');

            if (!empty($secretKey)) {
                $settings->set('flutterwave_secret_key', $secretKey, 'string', 'Flutterwave Secret Key');
            }

            $message = 'Payment keys updated successfully';

        } elseif ($action === 'update_funnel_plans') {
            // Update SINGLE Funnel Plans
            $funnelKey = $_POST['funnel_key'] ?? '';
            $funnelPlans = $_POST['funnel_plans'] ?? [];

            if ($funnelKey) {
                $allPlans = $settings->get('payment_plans', []); // Fetch fresh
                $oldFunnelPlans = $allPlans[$funnelKey] ?? [];

                // Initialize if not exists
                if (!is_array($allPlans))
                    $allPlans = [];

                // Clear existing plans for this funnel to allow full overwrite (handles deletions)
                $allPlans[$funnelKey] = [];

                if (!empty($funnelPlans) && is_array($funnelPlans)) {
                    foreach ($funnelPlans as $planKey => $planData) {
                        if (empty($planKey) || empty($planData['name']))
                            continue;

                        // Update the price in the actual HTML file
                        if (isset($planData['price'])) {
                            $discovery->updatePlanPrice($funnelKey, $planKey, $planData['price']);
                        }

                        // Preserve 'file' from old plans if available
                        $file = $oldFunnelPlans[$planKey]['file'] ?? ($planData['file'] ?? 'sales.html');

                        $allPlans[$funnelKey][$planKey] = [
                            'name' => sanitize_text_field($planData['name']),
                            'price' => floatval($planData['price']),
                            'currency' => sanitize_text_field($planData['currency'] ?? 'USD'),
                            'description' => sanitize_text_field($planData['description']),
                            'features' => array_filter(array_map('trim', explode("\n", $planData['features']))),
                            'file' => $file
                        ];
                    }
                }

                if ($settings->set('payment_plans', $allPlans, 'json', 'Payment plans configuration')) {
                    $message = 'Pricing for ' . strtoupper($funnelKey) . ' funnel updated successfully';
                } else {
                    $error = 'Failed to update pricing for ' . $funnelKey;
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get current settings
$currentPlans = $settings->get('payment_plans', []);
if (!is_array($currentPlans))
    $currentPlans = [];

$funnelRegistry = $discovery->getRegisteredFunnels();
$publicKey = $settings->get('flutterwave_public_key');
$hasSecretKey = !empty($settings->get('flutterwave_secret_key'));

$pageTitle = 'Pricing & Payments - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Pricing & Payments</h2>
        <p class="text-[#6B7C70] mt-1">Manage funnel pricing and payment gateways</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="text-xs text-[#6B7C70] bg-[#F2F4F1] px-3 py-1 rounded-full border border-[#EAEAE5]">
            <i class="fas fa-check-circle text-green-500 mr-1"></i>
            <?php echo count($funnelRegistry); ?> Funnels Detected
        </span>
        <form method="POST" class="inline">
            <input type="hidden" name="action" value="sync_funnels">
            <button type="submit"
                class="px-4 py-2 bg-[#2C3E35] text-white rounded-xl text-sm font-medium hover:bg-[#1a2621] transition-colors shadow-sm">
                <i class="fas fa-sync-alt mr-2"></i> Scan for Funnels
            </button>
        </form>
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

<!-- Tabs -->
<div class="mb-8 border-b border-[#EAEAE5]">
    <nav class="flex space-x-8" aria-label="Tabs">
        <button onclick="switchTab('plans')" id="tab-plans"
            class="pb-4 px-1 border-b-2 font-medium text-sm transition-colors border-[#D97757] text-[#2C3E35]">
            Pricing Plans
        </button>
        <button onclick="switchTab('settings')" id="tab-settings"
            class="pb-4 px-1 border-b-2 font-medium text-sm transition-colors border-transparent text-[#6B7C70] hover:text-[#2C3E35]">
            Payment Settings
        </button>
    </nav>
</div>

<!-- Pricing Plans Tab -->
<div id="content-plans" class="space-y-8 animate-fade-in-up">
    <?php
    // Iterate through registered funnels
    foreach ($funnelRegistry as $funnelKey => $funnelInfo):
        $plans = $currentPlans[$funnelKey] ?? [];
        ?>
        <div class="luxury-card overflow-hidden funnel-section" data-funnel="<?php echo htmlspecialchars($funnelKey); ?>">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_funnel_plans">
                <input type="hidden" name="funnel_key" value="<?php echo htmlspecialchars($funnelKey); ?>">

                <div
                    class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8] flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-serif text-[#2C3E35] flex items-center gap-2">
                            <i class="fas fa-folder text-[#D97757]"></i>
                            <?php echo htmlspecialchars(strtoupper($funnelKey)); ?>
                        </h3>
                        <p class="text-xs text-[#6B7C70] mt-1 ml-6">
                            <?php echo htmlspecialchars($funnelInfo['name']); ?>
                            <span class="text-[#A4B4A6]">(<?php echo htmlspecialchars($funnelInfo['directory']); ?>)</span>
                        </p>
                    </div>
                    <div class="flex gap-2 ml-6 md:ml-0">
                        <button type="button" onclick="addPlan('<?php echo htmlspecialchars($funnelKey); ?>')"
                            class="px-3 py-1.5 border border-[#EAEAE5] rounded-lg text-xs font-medium text-[#2C3E35] hover:bg-[#F2F4F1] transition-colors">
                            <i class="fas fa-plus mr-1"></i> Add Plan
                        </button>
                        <button type="submit"
                            class="px-3 py-1.5 bg-[#2C3E35] text-white rounded-lg text-xs font-medium hover:bg-[#1a2621] transition-colors shadow-sm">
                            <i class="fas fa-save mr-1"></i> Save Changes
                        </button>
                    </div>
                </div>

                <div class="p-6 bg-white plans-list space-y-6">
                    <?php if (empty($plans)): ?>
                        <div
                            class="text-center py-8 text-[#6B7C70] bg-[#F9FAF9] rounded-xl border border-dashed border-[#A4B4A6] no-plans-msg">
                            <i class="fas fa-tags text-2xl mb-2 text-[#A4B4A6]"></i>
                            <p class="text-sm">No pricing plans configured for this funnel yet.</p>
                            <button type="button" onclick="addPlan('<?php echo htmlspecialchars($funnelKey); ?>')"
                                class="text-[#D97757] text-sm hover:underline mt-2">Add first plan</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($plans as $planKey => $plan): ?>
                            <div
                                class="p-6 bg-[#FDFCF8] border border-[#EAEAE5] rounded-xl relative plan-item group transition-shadow hover:shadow-md">
                                <button type="button" onclick="removePlan(this)"
                                    class="absolute top-4 right-4 text-[#A4B4A6] hover:text-[#D97757] transition-colors p-1">
                                    <i class="fas fa-times"></i>
                                </button>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Plan
                                            ID</label>
                                        <input type="text" value="<?php echo htmlspecialchars($planKey); ?>"
                                            class="w-full px-4 py-2 bg-[#F2F4F1] border border-[#EAEAE5] rounded-lg text-[#6B7C70] text-sm font-mono cursor-not-allowed"
                                            readonly>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Plan
                                            Name</label>
                                        <input type="text" name="funnel_plans[<?php echo $planKey; ?>][name]"
                                            value="<?php echo htmlspecialchars($plan['name']); ?>"
                                            class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] text-sm font-medium"
                                            required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Price
                                            (USD)</label>
                                        <div class="relative">
                                            <span class="absolute left-4 top-2 text-[#6B7C70]">$</span>
                                            <input type="number" name="funnel_plans[<?php echo $planKey; ?>][price]"
                                                value="<?php echo htmlspecialchars($plan['price']); ?>"
                                                class="luxury-input w-full pl-8 pr-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] text-sm font-bold"
                                                required>
                                        </div>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Description</label>
                                        <input type="text" name="funnel_plans[<?php echo $planKey; ?>][description]"
                                            value="<?php echo htmlspecialchars($plan['description']); ?>"
                                            class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] text-sm">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Features
                                            (One per line)</label>
                                        <textarea name="funnel_plans[<?php echo $planKey; ?>][features]" rows="3"
                                            class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] text-sm font-mono"><?php echo htmlspecialchars(implode("\n", $plan['features'] ?? [])); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<!-- Payment Settings Tab -->
<div id="content-settings" class="hidden animate-fade-in-up">
    <div class="luxury-card p-8">
        <div class="md:grid md:grid-cols-3 md:gap-12">
            <div class="md:col-span-1 border-r border-[#EAEAE5] pr-8">
                <h3 class="text-xl font-serif text-[#2C3E35] mb-2">Flutterwave Configuration</h3>
                <p class="text-sm text-[#6B7C70] leading-relaxed">
                    Configure your Flutterwave API keys to accept payments.
                    Ensure your keys are kept secret and never shared.
                    <br><br>
                    <a href="https://dashboard.flutterwave.com/" target="_blank"
                        class="text-[#D97757] hover:underline">Get keys from dashboard &rarr;</a>
                </p>
            </div>
            <div class="mt-8 md:mt-0 md:col-span-2">
                <form method="POST" action="" class="space-y-6">
                    <input type="hidden" name="action" value="update_keys">

                    <div>
                        <label class="block text-sm font-medium text-[#2C3E35] mb-2">Public Key</label>
                        <input type="text" name="flutterwave_public_key"
                            value="<?php echo htmlspecialchars($publicKey); ?>"
                            placeholder="FLWPUBK-xxxxxxxxxxxxxxxxxxxxx-X"
                            class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[#2C3E35] mb-2">Secret Key</label>
                        <input type="password" name="flutterwave_secret_key"
                            placeholder="<?php echo $hasSecretKey ? 'Key is set (leave empty to keep)' : 'Enter Secret Key (FLWSECK-...)'; ?>"
                            class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm shadow-sm">
                        <p class="mt-2 text-xs text-[#6B7C70]">Used for verifying transactions and webhooks. Stored
                            securely.</p>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button type="submit"
                            class="px-6 py-2.5 bg-[#3F51B5] text-white font-medium rounded-xl hover:bg-[#303f9f] transition-colors shadow-lg shadow-[#3F51B5]/20">
                            Updates Keys
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        document.querySelectorAll('[id^="content-"]').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('[id^="tab-"]').forEach(el => {
            el.classList.remove('border-[#D97757]', 'text-[#2C3E35]');
            el.classList.add('border-transparent', 'text-[#6B7C70]');
        });

        document.getElementById('content-' + tab).classList.remove('hidden');
        const activeTab = document.getElementById('tab-' + tab);
        activeTab.classList.remove('border-transparent', 'text-[#6B7C70]');
        activeTab.classList.add('border-[#D97757]', 'text-[#2C3E35]');
    }

    function addPlan(funnelKey) {
        const planId = prompt("Enter Plan ID (e.g., 90-day, 30-day):");
        if (!planId) return;

        const key = planId.toLowerCase().replace(/[^a-z0-9-]/g, '-');
        const funnelSection = document.querySelector(`.funnel-section[data-funnel="${funnelKey}"]`);
        const list = funnelSection.querySelector('.plans-list');
        const noPlansMsg = list.querySelector('.no-plans-msg');

        if (noPlansMsg) {
            noPlansMsg.remove();
        }

        const html = `
            <div class="p-6 bg-[#FDFCF8] border border-[#EAEAE5] rounded-xl relative plan-item group transition-shadow hover:shadow-md animate-fade-in-up">
                <button type="button" onclick="removePlan(this)" class="absolute top-4 right-4 text-[#A4B4A6] hover:text-[#D97757] transition-colors p-1">
                    <i class="fas fa-times"></i>
                </button>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Plan ID</label>
                        <input type="text" value="${key}" class="w-full px-4 py-2 bg-[#F2F4F1] border border-[#EAEAE5] rounded-lg text-[#6B7C70] text-sm font-mono cursor-not-allowed" readonly>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Plan Name</label>
                        <input type="text" name="funnel_plans[${key}][name]" class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] text-sm font-medium" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Price (USD)</label>
                        <div class="relative">
                            <span class="absolute left-4 top-2 text-[#6B7C70]">$</span>
                            <input type="number" name="funnel_plans[${key}][price]" class="luxury-input w-full pl-8 pr-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] text-sm font-bold" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Description</label>
                        <input type="text" name="funnel_plans[${key}][description]" class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Features (One per line)</label>
                        <textarea name="funnel_plans[${key}][features]" rows="3" class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] text-sm font-mono"></textarea>
                    </div>
                </div>
            </div>
        `;

        list.insertAdjacentHTML('beforeend', html);
    }

    function removePlan(btn) {
        if (confirm('Remove this plan?')) {
            btn.closest('.plan-item').remove();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>