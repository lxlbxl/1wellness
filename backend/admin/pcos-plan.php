<?php
require_once 'auth.php';
require_once '../classes/Database.php';
require_once '../classes/Settings.php';

$db       = Database::getInstance();
$settings = Settings::getInstance();

$message = '';
$error   = '';

// ─── Paths ───────────────────────────────────────────
$systemPromptFile = __DIR__ . '/../prompts/system-prompt.md';
$userPromptFile   = __DIR__ . '/../prompts/user-prompt.md';
$templateFile     = __DIR__ . '/../templates/plan-template.html';

// ─── Handle POST actions ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. Save System Prompt (file + DB seed) ──
    if ($action === 'save_system_prompt') {
        $text = $_POST['system_prompt'] ?? '';
        if (empty($text)) {
            $error = 'System prompt cannot be empty.';
        } else {
            file_put_contents($systemPromptFile, $text);

            // Also upsert into DB so AIOrchestrator's DB-based lookup works
            $existing = $db->fetch("SELECT id FROM system_prompts WHERE prompt_key = 'pcos_plan'");
            if ($existing) {
                $db->update('system_prompts', [
                    'prompt_text'  => $text,
                    'description'  => 'PCOS 90-Day Plan Generator — System Prompt',
                    'updated_at'   => date('Y-m-d H:i:s'),
                ], "id = " . (int)$existing['id']);
            } else {
                $db->insert('system_prompts', [
                    'prompt_key'  => 'pcos_plan',
                    'prompt_text' => $text,
                    'description' => 'PCOS 90-Day Plan Generator — System Prompt',
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
            }
            $message = 'System prompt saved and synced to database.';
        }
    }

    // ── 2. Save User Prompt ──
    elseif ($action === 'save_user_prompt') {
        $text = $_POST['user_prompt'] ?? '';
        if (empty($text)) {
            $error = 'User prompt template cannot be empty.';
        } else {
            file_put_contents($userPromptFile, $text);
            $message = 'User prompt template saved.';
        }
    }

    // ── 3. Save PDF Template ──
    elseif ($action === 'save_template') {
        $html = $_POST['template_html'] ?? '';
        if (empty($html)) {
            $error = 'PDF template cannot be empty.';
        } else {
            file_put_contents($templateFile, $html);
            $message = 'PDF template saved.';
        }
    }

    // ── 4. Save AI Settings ──
    elseif ($action === 'save_ai_settings') {
        $fields = [
            'ai_provider' => ['type' => 'string', 'desc' => 'AI Provider (openrouter/openai/gemini)'],
            'ai_api_key'  => ['type' => 'string', 'desc' => 'AI API Key'],
            'ai_model'    => ['type' => 'string', 'desc' => 'AI Model ID'],
        ];
        foreach ($fields as $key => $meta) {
            $val = trim($_POST[$key] ?? '');
            if ($key === 'ai_api_key' && empty($val)) continue; // don't overwrite with blank
            if (!empty($val)) {
                $settings->set($key, $val, $meta['type'], $meta['desc']);
            }
        }
        // Numeric/boolean settings
        $pcosMeta = [
            'pcos_max_retries'  => ['type' => 'integer', 'desc' => 'PCOS Generator: Max AI Retries'],
            'pcos_temperature'  => ['type' => 'string',  'desc' => 'PCOS Generator: AI Temperature'],
            'pcos_max_tokens'   => ['type' => 'integer', 'desc' => 'PCOS Generator: Max Tokens'],
            'pcos_send_email'   => ['type' => 'boolean', 'desc' => 'PCOS Generator: Send PDF by Email'],
        ];
        foreach ($pcosMeta as $key => $meta) {
            $val = $_POST[$key] ?? '';
            if ($key === 'pcos_send_email') {
                $val = isset($_POST[$key]) ? true : false;
            }
            $settings->set($key, $val, $meta['type'], $meta['desc']);
        }
        $message = 'AI settings saved.';
    }

    // ── 5. Save PCOS Pricing ──
    elseif ($action === 'save_pcos_pricing') {
        $price    = floatval($_POST['pcos_price'] ?? 0);
        $name     = htmlspecialchars(trim($_POST['pcos_plan_name'] ?? '90-Day PCOS Protocol'), ENT_QUOTES);
        $currency = htmlspecialchars(trim($_POST['pcos_currency'] ?? 'USD'), ENT_QUOTES);
        $desc     = htmlspecialchars(trim($_POST['pcos_description'] ?? ''), ENT_QUOTES);

        // Persist using the same structure pricing.php uses
        $allPlans = $settings->get('payment_plans', []);
        if (!is_array($allPlans)) $allPlans = [];
        $allPlans['pcos']['90-day'] = [
            'name'        => $name,
            'price'       => $price,
            'currency'    => $currency,
            'description' => $desc,
            'features'    => array_filter(array_map('trim', explode("\n", $_POST['pcos_features'] ?? ''))),
            'file'        => 'pcos/index.html',
        ];
        $settings->set('payment_plans', $allPlans, 'json', 'Payment plans configuration');
        $message = 'PCOS pricing updated.';
    }
}

// ─── Load current values ──────────────────────────────
$systemPromptText = file_exists($systemPromptFile) ? file_get_contents($systemPromptFile) : '';
$userPromptText   = file_exists($userPromptFile)   ? file_get_contents($userPromptFile)   : '';
$templateHtml     = file_exists($templateFile)     ? file_get_contents($templateFile)     : '';

// AI settings
$aiProvider    = $settings->get('ai_provider', 'openrouter');
$aiApiKey      = $settings->get('ai_api_key', '');
$aiModel       = $settings->get('ai_model', 'google/gemini-2.0-flash-exp:free');
$maxRetries    = $settings->get('pcos_max_retries', 3);
$temperature   = $settings->get('pcos_temperature', '0.7');
$maxTokens     = $settings->get('pcos_max_tokens', 16000);
$sendEmail     = $settings->get('pcos_send_email', false);

// Pricing
$allPlans  = $settings->get('payment_plans', []);
$pcosPlan  = $allPlans['pcos']['90-day'] ?? [];

$pageTitle = 'PCOS Plan Manager — 1wellness Admin';
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">PCOS Plan Manager</h2>
        <p class="text-[#6B7C70] mt-1">Control every aspect of the 90-Day PCOS Protocol PDF — prompts, template, AI, and pricing.</p>
    </div>
    <div class="flex gap-2 text-sm">
        <a href="../pcos/index.html" target="_blank"
            class="bg-white text-[#2C3E35] border border-[#EAEAE5] px-4 py-2 rounded-xl hover:bg-[#F2F4F1] transition-all flex items-center gap-2">
            <i class="fas fa-external-link-alt"></i> View Sales Page
        </a>
        <a href="assessments.php"
            class="bg-white text-[#2C3E35] border border-[#EAEAE5] px-4 py-2 rounded-xl hover:bg-[#F2F4F1] transition-all flex items-center gap-2">
            <i class="fas fa-clipboard-list"></i> View Assessments
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 bg-[#F2F4F1] border border-[#A4B4A6] text-[#2C3E35] rounded-xl flex items-center gap-3">
        <i class="fas fa-check-circle text-green-600"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-6 p-4 bg-[#FDF1E8] border border-[#D97757] text-[#D97757] rounded-xl flex items-center gap-3">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Tabs -->
<div class="mb-8 border-b border-[#EAEAE5]">
    <nav class="flex space-x-6" aria-label="Tabs">
        <?php
        $tabs = [
            'prompts'  => ['icon' => 'fa-brain',       'label' => 'Prompts'],
            'template' => ['icon' => 'fa-file-code',   'label' => 'PDF Template'],
            'ai'       => ['icon' => 'fa-robot',       'label' => 'AI Settings'],
            'pricing'  => ['icon' => 'fa-tags',        'label' => 'Pricing'],
        ];
        $activeTab = $_GET['tab'] ?? 'prompts';
        foreach ($tabs as $key => $t):
            $isActive = ($activeTab === $key);
            $cls = $isActive ? 'border-[#D97757] text-[#2C3E35]' : 'border-transparent text-[#6B7C70] hover:text-[#2C3E35]';
        ?>
            <button onclick="switchTab('<?php echo $key; ?>')" id="tab-<?php echo $key; ?>"
                class="pb-4 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 <?php echo $cls; ?>">
                <i class="fas <?php echo $t['icon']; ?>"></i> <?php echo $t['label']; ?>
            </button>
        <?php endforeach; ?>
    </nav>
</div>

<!-- ═══════════════════════════════════════════════════ -->
<!-- TAB: PROMPTS                                       -->
<!-- ═══════════════════════════════════════════════════ -->
<div id="content-prompts" class="space-y-8">

    <!-- System Prompt -->
    <div class="luxury-card p-8">
        <div class="flex items-start justify-between mb-6">
            <div>
                <h3 class="text-2xl font-serif text-[#2C3E35]">System Prompt</h3>
                <p class="text-sm text-[#6B7C70] mt-1">
                    Defines the AI's persona, PCOS knowledge base, output JSON schema, and safety guardrails.
                    Saved to <code class="bg-[#F2F4F1] px-2 py-0.5 rounded text-xs">backend/prompts/system-prompt.md</code>
                    and synced to the database as <code class="bg-[#F2F4F1] px-2 py-0.5 rounded text-xs">pcos_plan</code>.
                </p>
            </div>
            <span class="text-xs text-[#A4B4A6] bg-[#F2F4F1] px-3 py-1 rounded-full">Key: pcos_plan</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_system_prompt">
            <div class="mb-4">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Instructions (Markdown)</label>
                    <div class="flex gap-2">
                        <button type="button" onclick="toggleFullscreen('systemPromptEl')"
                            class="text-xs px-3 py-1 border border-[#EAEAE5] rounded-lg text-[#6B7C70] hover:bg-[#F2F4F1] flex items-center gap-1">
                            <i class="fas fa-expand-alt"></i> Expand
                        </button>
                        <span id="sys-chars" class="text-xs text-[#A4B4A6] self-center"></span>
                    </div>
                </div>
                <textarea id="systemPromptEl" name="system_prompt" rows="20"
                    class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-xs leading-relaxed bg-[#FAFAF8] transition-all"
                    oninput="document.getElementById('sys-chars').textContent = this.value.length + ' chars'"
                    placeholder="You are an expert PCOS specialist..."><?php echo htmlspecialchars($systemPromptText); ?></textarea>
            </div>
            <div class="flex justify-between items-center">
                <p class="text-xs text-[#A4B4A6]"><i class="fas fa-info-circle mr-1"></i> Changes take effect immediately on the next plan generation.</p>
                <button type="submit"
                    class="px-6 py-2.5 bg-[#2C3E35] text-white font-medium rounded-xl hover:bg-[#1a2621] transition-colors shadow-lg shadow-[#2C3E35]/20 flex items-center gap-2">
                    <i class="fas fa-save"></i> Save System Prompt
                </button>
            </div>
        </form>
    </div>

    <!-- User Prompt Template -->
    <div class="luxury-card p-8">
        <div class="flex items-start justify-between mb-6">
            <div>
                <h3 class="text-2xl font-serif text-[#2C3E35]">User Prompt Template</h3>
                <p class="text-sm text-[#6B7C70] mt-1">
                    The client-specific message sent to the AI. Use <code class="bg-[#F2F4F1] px-1 py-0.5 rounded text-xs">&#123;&#123;NAME&#125;&#125;</code>,
                    <code class="bg-[#F2F4F1] px-1 py-0.5 rounded text-xs">&#123;&#123;PCOS_TYPE&#125;&#125;</code>,
                    <code class="bg-[#F2F4F1] px-1 py-0.5 rounded text-xs">&#123;&#123;SYMPTOMS&#125;&#125;</code>,
                    <code class="bg-[#F2F4F1] px-1 py-0.5 rounded text-xs">&#123;&#123;GOALS&#125;&#125;</code> etc. as placeholders.
                </p>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_user_prompt">
            <div class="mb-4">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Template (Markdown)</label>
                    <span id="user-chars" class="text-xs text-[#A4B4A6]"></span>
                </div>
                <textarea name="user_prompt" rows="14"
                    class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-xs leading-relaxed bg-[#FAFAF8]"
                    oninput="document.getElementById('user-chars').textContent = this.value.length + ' chars'"
                    placeholder="Generate a 90-day protocol for {{NAME}}..."><?php echo htmlspecialchars($userPromptText); ?></textarea>
            </div>
            <div class="bg-[#F2F4F1] rounded-xl p-4 mb-4">
                <p class="text-xs font-bold text-[#2C3E35] mb-2 uppercase tracking-wider">Available Placeholders</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (['NAME', 'PCOS_TYPE', 'AGE', 'SYMPTOMS', 'GOALS', 'BMI', 'CYCLE_STATUS', 'DIETARY_RESTRICTIONS', 'MEDICATIONS', 'EXERCISE_LEVEL', 'SLEEP_QUALITY', 'STRESS_LEVEL'] as $ph): ?>
                        <code class="bg-white border border-[#EAEAE5] text-[#D97757] px-2 py-1 rounded text-xs cursor-pointer hover:bg-[#FDF1E8]"
                            onclick="navigator.clipboard.writeText('{{<?php echo $ph; ?>}}')">&#123;&#123;<?php echo $ph; ?>&#125;&#125;</code>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-[#A4B4A6] mt-2">Click to copy. These are injected from the client's assessment data.</p>
            </div>
            <div class="flex justify-end">
                <button type="submit"
                    class="px-6 py-2.5 bg-[#2C3E35] text-white font-medium rounded-xl hover:bg-[#1a2621] transition-colors shadow-lg shadow-[#2C3E35]/20 flex items-center gap-2">
                    <i class="fas fa-save"></i> Save User Prompt
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════ -->
<!-- TAB: PDF TEMPLATE                                  -->
<!-- ═══════════════════════════════════════════════════ -->
<div id="content-template" class="hidden">
    <div class="luxury-card p-8">
        <div class="flex items-start justify-between mb-6">
            <div>
                <h3 class="text-2xl font-serif text-[#2C3E35]">PDF Template</h3>
                <p class="text-sm text-[#6B7C70] mt-1">
                    The HTML/CSS layout used by dompdf to render the PDF. Uses inline CSS and
                    <code class="bg-[#F2F4F1] px-1 py-0.5 rounded text-xs">&#123;&#123;PLACEHOLDER&#125;&#125;</code> tokens
                    replaced at generation time.
                </p>
            </div>
            <div class="flex gap-2">
                <span class="text-xs text-[#A4B4A6] bg-[#F2F4F1] px-3 py-1 rounded-full self-start">
                    <?php echo number_format(strlen($templateHtml)); ?> bytes
                </span>
                <button type="button" onclick="toggleFullscreen('templateEl')"
                    class="text-xs px-3 py-1 border border-[#EAEAE5] rounded-lg text-[#6B7C70] hover:bg-[#F2F4F1] flex items-center gap-1">
                    <i class="fas fa-expand-alt"></i> Fullscreen
                </button>
            </div>
        </div>

        <!-- Available tokens reference -->
        <div class="bg-[#F2F4F1] rounded-xl p-4 mb-6">
            <p class="text-xs font-bold text-[#2C3E35] mb-2 uppercase tracking-wider">Template Tokens</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <?php
                $tokens = [
                    '{{NAME}}', '{{PCOS_TYPE}}', '{{AGE}}', '{{DATE}}',
                    '{{SUMMARY}}', '{{ROOT_CAUSE}}', '{{ENCOURAGEMENT}}', '{{GOALS}}',
                    '{{PHASE_1_TITLE}}', '{{PHASE_1_FOCUS}}', '{{PHASE_1_DESCRIPTION}}', '{{PHASE_1_WEEKS}}',
                    '{{PHASE_2_TITLE}}', '{{PHASE_2_FOCUS}}', '{{PHASE_2_DESCRIPTION}}', '{{PHASE_2_WEEKS}}',
                    '{{PHASE_3_TITLE}}', '{{PHASE_3_FOCUS}}', '{{PHASE_3_DESCRIPTION}}', '{{PHASE_3_WEEKS}}',
                    '{{MORNING_ROUTINE}}', '{{AFTERNOON_ROUTINE}}', '{{EVENING_ROUTINE}}', '{{MEAL_PLAN_DAYS_1_4}}',
                    '{{MEAL_PLAN_DAYS_5_7}}', '{{SUPPLEMENTS}}', '{{HERBAL_PROTOCOLS}}', '{{LIFESTYLE_TIPS}}',
                    '{{TRACKING_GUIDANCE}}', '{{YEAR}}',
                ];
                foreach ($tokens as $tok): ?>
                    <code class="bg-white border border-[#EAEAE5] text-[#D97757] px-2 py-1 rounded text-xs truncate cursor-pointer hover:bg-[#FDF1E8]"
                        onclick="navigator.clipboard.writeText('<?php echo $tok; ?>')" title="<?php echo $tok; ?>">
                        <?php echo htmlspecialchars($tok); ?>
                    </code>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-[#A4B4A6] mt-2">Click to copy. Rendered tokens output HTML — don't double-escape.</p>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="save_template">
            <div class="mb-4">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">HTML Source</label>
                    <span id="tmpl-chars" class="text-xs text-[#A4B4A6]"><?php echo number_format(strlen($templateHtml)); ?> chars</span>
                </div>
                <textarea id="templateEl" name="template_html" rows="30"
                    class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-xs leading-relaxed bg-[#FAFAF8]"
                    oninput="document.getElementById('tmpl-chars').textContent = this.value.length.toLocaleString() + ' chars'"
                    spellcheck="false"><?php echo htmlspecialchars($templateHtml); ?></textarea>
            </div>
            <div class="flex justify-between items-center">
                <p class="text-xs text-[#A4B4A6]">
                    <i class="fas fa-exclamation-triangle text-amber-500 mr-1"></i>
                    Always keep inline CSS — dompdf does not load external stylesheets.
                </p>
                <button type="submit"
                    class="px-6 py-2.5 bg-[#2C3E35] text-white font-medium rounded-xl hover:bg-[#1a2621] transition-colors shadow-lg shadow-[#2C3E35]/20 flex items-center gap-2">
                    <i class="fas fa-save"></i> Save Template
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════ -->
<!-- TAB: AI SETTINGS                                   -->
<!-- ═══════════════════════════════════════════════════ -->
<div id="content-ai" class="hidden">
    <form method="POST">
        <input type="hidden" name="action" value="save_ai_settings">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

            <!-- Provider & Model -->
            <div class="luxury-card p-8">
                <h3 class="text-xl font-serif text-[#2C3E35] mb-6">Provider & Model</h3>
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">AI Provider</label>
                        <select name="ai_provider"
                            class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] bg-white text-[#2C3E35]">
                            <?php foreach (['openrouter' => 'OpenRouter (multi-model)', 'openai' => 'OpenAI (direct)', 'gemini' => 'Gemini (direct)'] as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo $aiProvider === $val ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-[#A4B4A6] mt-2">OpenRouter gives access to 200+ models including Gemini, Claude, and GPT.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">API Key</label>
                        <input type="password" name="ai_api_key"
                            placeholder="<?php echo !empty($aiApiKey) ? 'Key is set (leave blank to keep)' : 'Enter API key...'; ?>"
                            class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm">
                        <?php if (!empty($aiApiKey)): ?>
                            <p class="text-xs text-green-600 mt-1"><i class="fas fa-check-circle mr-1"></i> API key is configured.</p>
                        <?php else: ?>
                            <p class="text-xs text-amber-600 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i> No API key set — plan generation will fail.</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Model ID</label>
                        <input type="text" name="ai_model" value="<?php echo htmlspecialchars($aiModel); ?>"
                            placeholder="google/gemini-2.0-flash-exp:free"
                            class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm">
                        <p class="text-xs text-[#A4B4A6] mt-2">
                            Examples: <code>google/gemini-2.0-flash-exp:free</code>, <code>openai/gpt-4o</code>, <code>anthropic/claude-3-5-sonnet</code>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Generation Parameters -->
            <div class="luxury-card p-8">
                <h3 class="text-xl font-serif text-[#2C3E35] mb-6">Generation Parameters</h3>
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Max Tokens</label>
                        <input type="number" name="pcos_max_tokens" value="<?php echo htmlspecialchars($maxTokens); ?>"
                            min="4000" max="32000" step="1000"
                            class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                        <p class="text-xs text-[#A4B4A6] mt-2">Higher = more detailed plan. Recommended: 12,000–16,000.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Temperature (0.0 – 1.0)</label>
                        <input type="number" name="pcos_temperature" value="<?php echo htmlspecialchars($temperature); ?>"
                            min="0" max="1" step="0.05"
                            class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                        <p class="text-xs text-[#A4B4A6] mt-2">0.7 = creative but consistent. Lower = more predictable output.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Max Retries on Failure</label>
                        <input type="number" name="pcos_max_retries" value="<?php echo htmlspecialchars($maxRetries); ?>"
                            min="1" max="5"
                            class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                        <p class="text-xs text-[#A4B4A6] mt-2">If AI fails or returns invalid JSON, how many times to retry before using fallback content.</p>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-[#F2F4F1] rounded-xl">
                        <div>
                            <p class="text-sm font-medium text-[#2C3E35]">Send PDF by Email</p>
                            <p class="text-xs text-[#6B7C70] mt-0.5">Email the generated PDF to the client after generation.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="pcos_send_email" value="1" class="sr-only peer"
                                <?php echo $sendEmail ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-[#EAEAE5] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2C3E35]"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit"
                class="px-8 py-3 bg-[#2C3E35] text-white font-medium rounded-xl hover:bg-[#1a2621] transition-colors shadow-lg shadow-[#2C3E35]/20 flex items-center gap-2">
                <i class="fas fa-save"></i> Save AI Settings
            </button>
        </div>
    </form>
</div>

<!-- ═══════════════════════════════════════════════════ -->
<!-- TAB: PRICING                                       -->
<!-- ═══════════════════════════════════════════════════ -->
<div id="content-pricing" class="hidden">
    <div class="luxury-card p-8">
        <div class="flex items-start justify-between mb-6">
            <div>
                <h3 class="text-2xl font-serif text-[#2C3E35]">PCOS Plan Pricing</h3>
                <p class="text-sm text-[#6B7C70] mt-1">Set the price shown on the sales page and used at checkout.</p>
            </div>
            <?php if (!empty($pcosPlan)): ?>
                <div class="bg-[#F2F4F1] border border-[#EAEAE5] rounded-xl px-5 py-4 text-right">
                    <div class="text-xs text-[#6B7C70] uppercase tracking-wider mb-1">Current Price</div>
                    <div class="text-3xl font-serif font-bold text-[#2C3E35]">
                        $<?php echo number_format($pcosPlan['price'] ?? 0); ?>
                    </div>
                    <div class="text-xs text-[#A4B4A6]"><?php echo htmlspecialchars($pcosPlan['currency'] ?? 'USD'); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="save_pcos_pricing">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Plan Name</label>
                    <input type="text" name="pcos_plan_name" required
                        value="<?php echo htmlspecialchars($pcosPlan['name'] ?? '90-Day PCOS Protocol'); ?>"
                        class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Currency</label>
                    <select name="pcos_currency"
                        class="w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] bg-white text-[#2C3E35]">
                        <?php foreach (['USD' => '$ US Dollar (USD)', 'GBP' => '£ British Pound (GBP)', 'MXN' => '$ Mexican Peso (MXN)'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo ($pcosPlan['currency'] ?? 'USD') === $val ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Price</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 text-[#6B7C70] font-bold">$</span>
                        <input type="number" name="pcos_price" required min="0" step="100"
                            value="<?php echo htmlspecialchars($pcosPlan['price'] ?? ''); ?>"
                            class="luxury-input w-full pl-8 pr-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] text-xl font-bold">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Short Description</label>
                    <input type="text" name="pcos_description"
                        value="<?php echo htmlspecialchars($pcosPlan['description'] ?? 'Personalized 90-Day PCOS Protocol PDF'); ?>"
                        class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Features (one per line)</label>
                    <textarea name="pcos_features" rows="6"
                        class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm"
                        placeholder="Personalized 90-day meal plan&#10;Custom supplement protocol&#10;Weekly action plans..."><?php echo htmlspecialchars(implode("\n", $pcosPlan['features'] ?? [])); ?></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="submit"
                    class="px-8 py-3 bg-[#D97757] text-white font-medium rounded-xl hover:bg-[#B54D2F] transition-colors shadow-lg shadow-[#D97757]/30 flex items-center gap-2">
                    <i class="fas fa-save"></i> Update Pricing
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Tab switching
    const defaultTab = '<?php echo $activeTab; ?>';

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
        history.replaceState(null, '', '?tab=' + tab);
    }

    // Fullscreen textarea toggle
    function toggleFullscreen(id) {
        const el = document.getElementById(id);
        if (el.classList.contains('h-screen')) {
            el.classList.remove('h-screen', 'fixed', 'inset-0', 'z-50', 'rounded-none', 'border-0');
            el.rows = id === 'templateEl' ? 30 : 20;
        } else {
            el.classList.add('h-screen', 'fixed', 'inset-0', 'z-50', 'rounded-none', 'border-0');
        }
    }

    // On load — show correct tab and update char counts
    window.addEventListener('DOMContentLoaded', () => {
        switchTab(defaultTab);

        const sysEl = document.getElementById('systemPromptEl');
        if (sysEl) document.getElementById('sys-chars').textContent = sysEl.value.length + ' chars';

        const tmplEl = document.getElementById('templateEl');
        // Already pre-rendered char count via PHP
    });

    // Tab from URL param on load
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam && document.getElementById('content-' + tabParam)) {
        switchTab(tabParam);
    }
</script>

<?php include 'includes/footer.php'; ?>
