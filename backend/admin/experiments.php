<?php
require_once 'auth.php';
require_once '../classes/Database.php';
require_once '../classes/ExperimentManager.php';
require_once '../classes/FunnelDiscovery.php';
require_once '../classes/Settings.php';

$manager = new ExperimentManager();
$message = '';
$error = '';

// ---------------- Actions ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    switch ($action) {
        case 'create':
            $variants = json_decode($_POST['variants_json'] ?? '[]', true);
            if (!is_array($variants)) {
                $error = 'Variants payload was not valid JSON.';
                break;
            }
            foreach ($variants as &$v) {
                if (!empty($v['overrides']) && is_string($v['overrides'])) {
                    $decoded = json_decode($v['overrides'], true);
                    if ($decoded === null && trim($v['overrides']) !== '') {
                        $error = "Overrides for variant \"{$v['name']}\" are not valid JSON.";
                        break 2;
                    }
                    $v['overrides'] = $decoded;
                }
            }
            unset($v);
            $result = $manager->createExperiment([
                'funnel_name' => $_POST['funnel_name'] ?? '',
                'name' => $_POST['name'] ?? '',
                'hypothesis' => $_POST['hypothesis'] ?? '',
                'stage' => $_POST['stage'] ?? '',
                'primary_metric' => $_POST['primary_metric'] ?? '',
                'burn_in_hours' => $_POST['burn_in_hours'] ?? 48,
                'min_exposure_floor' => $_POST['min_exposure_floor'] ?? 0.10,
                'min_samples_per_variant' => $_POST['min_samples_per_variant'] ?? 1000,
                'decision_p_best' => $_POST['decision_p_best'] ?? 0.95,
                'decision_expected_loss' => $_POST['decision_expected_loss'] ?? 0.005,
            ], $variants);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $message = "Experiment created (draft). Press Start when you're ready to serve traffic.";
                logActivity('experiment_created', "Experiment #{$result['id']}");
            }
            break;

        case 'start':
            $result = $manager->startExperiment($id);
            $error = $result['error'] ?? '';
            if (!$error) {
                $message = "Experiment started ({$result['status']}).";
                logActivity('experiment_started', "Experiment #$id");
            }
            break;

        case 'pause':
            $result = $manager->pauseExperiment($id);
            $error = $result['error'] ?? '';
            if (!$error) $message = 'Experiment paused.';
            break;

        case 'archive':
            $result = $manager->archiveExperiment($id);
            $error = $result['error'] ?? '';
            if (!$error) $message = 'Experiment archived.';
            break;

        case 'delete':
            $result = $manager->deleteExperiment($id);
            $error = $result['error'] ?? '';
            if (!$error) $message = 'Experiment deleted.';
            break;

        case 'recompute':
            $manager->recomputePosteriors();
            $message = 'Posteriors recomputed.';
            break;
    }
}

$experiments = $manager->listExperiments(['include_archived' => isset($_GET['archived'])]);
$approvalQueue = $manager->getApprovalQueue();

$discovery = new FunnelDiscovery();
$variantDirs = $discovery->getVariantDirectories();

$pageTitle = 'Experiments - 1wellness Admin';
include 'includes/header.php';

$statusColors = [
    'draft' => 'bg-[#F2F4F1] text-[#6B7C70]',
    'burn_in' => 'bg-[#FFF4E0] text-[#B7791F]',
    'active' => 'bg-[#E6F4EA] text-[#1D4532]',
    'paused' => 'bg-[#FDF1E8] text-[#D97757]',
    'concluded' => 'bg-[#E3E8E1] text-[#2C3E35]',
    'archived' => 'bg-[#F2F4F1] text-[#A4B4A6]',
];
?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Experiments</h2>
        <p class="text-[#6B7C70] mt-1">Thompson Sampling A/B engine — winners earn traffic automatically</p>
    </div>
    <div class="flex gap-3">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="recompute">
            <button type="submit" class="px-4 py-2 bg-white border border-[#EAEAE5] text-[#2C3E35] rounded-xl text-sm font-medium hover:bg-[#F2F4F1] transition-colors" title="Refresh posteriors now (normally hourly cron)">
                <i class="fas fa-sync-alt mr-2"></i>Recompute
            </button>
        </form>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')"
            class="px-4 py-2 bg-[#2C3E35] text-white rounded-xl text-sm font-medium hover:bg-[#1a2621] transition-colors shadow-lg shadow-[#2C3E35]/20">
            <i class="fas fa-flask mr-2"></i>New Experiment
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-8 p-4 bg-[#F2F4F1] border border-[#A4B4A6] text-[#2C3E35] rounded-xl flex items-center">
        <i class="fas fa-check-circle mr-3"></i><?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-8 p-4 bg-[#FDF1E8] border border-[#D97757] text-[#D97757] rounded-xl flex items-center">
        <i class="fas fa-exclamation-circle mr-3"></i><?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($approvalQueue)): ?>
    <div class="mb-8 p-5 bg-[#FFF8EE] border border-[#E8C893] rounded-2xl">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-[#F5E6CC] flex items-center justify-center text-[#B7791F]">
                    <i class="fas fa-robot"></i>
                </div>
                <div>
                    <p class="font-serif text-lg text-[#2C3E35]"><?php echo count($approvalQueue); ?> AI challenger<?php echo count($approvalQueue) > 1 ? 's' : ''; ?> awaiting your approval</p>
                    <p class="text-sm text-[#6B7C70]">Nothing AI-generated serves traffic without human sign-off.</p>
                </div>
            </div>
            <a href="experiment-detail.php?id=<?php echo (int) $approvalQueue[0]['experiment_id']; ?>#approval"
               class="px-4 py-2 bg-[#B7791F] text-white rounded-xl text-sm font-medium hover:opacity-90">Review</a>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($experiments)): ?>
    <div class="luxury-card p-12 text-center text-[#6B7C70]">
        <div class="bg-[#F2F4F1] w-16 h-16 rounded-full flex items-center justify-center text-[#A4B4A6] mx-auto mb-4">
            <i class="fas fa-flask text-2xl"></i>
        </div>
        <p class="text-lg font-serif text-[#2C3E35] mb-2">No experiments yet</p>
        <p class="text-sm mb-6">Create your first experiment to start optimizing a funnel.</p>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')"
            class="px-4 py-2 bg-white border border-[#EAEAE5] text-[#2C3E35] rounded-lg text-sm font-medium hover:bg-[#F2F4F1]">
            Create your first experiment
        </button>
    </div>
<?php else: ?>
    <div class="space-y-6">
        <?php foreach ($experiments as $exp):
            $totalExp = 0;
            foreach ($exp['variants'] as $v) { $totalExp += (int) $v['exposures']; }
            $days = $exp['started_at'] ? max(0, floor((time() - strtotime($exp['started_at'])) / 86400)) : 0;
            $badge = $statusColors[$exp['status']] ?? 'bg-[#F2F4F1] text-[#6B7C70]';
        ?>
        <div class="luxury-card p-6">
            <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 text-[10px] uppercase font-bold tracking-wider rounded bg-[#F2F4F1] text-[#6B7C70] border border-[#EAEAE5]"><?php echo htmlspecialchars($exp['funnel_name']); ?></span>
                        <span class="px-2 py-0.5 text-[10px] uppercase font-bold tracking-wider rounded bg-[#F2F4F1] text-[#6B7C70] border border-[#EAEAE5]"><?php echo htmlspecialchars($exp['stage']); ?></span>
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?php echo $badge; ?>"><?php echo str_replace('_', '-', $exp['status']); ?></span>
                    </div>
                    <a href="experiment-detail.php?id=<?php echo (int) $exp['id']; ?>" class="text-xl font-serif text-[#2C3E35] hover:text-[#D97757]">
                        <?php echo htmlspecialchars($exp['name']); ?>
                    </a>
                    <p class="text-sm text-[#6B7C70] mt-1">
                        Metric: <span class="font-mono"><?php echo htmlspecialchars($exp['primary_metric']); ?></span>
                        <?php if ($exp['started_at']): ?> · <?php echo $days; ?> day<?php echo $days == 1 ? '' : 's'; ?> running<?php endif; ?>
                        · <?php echo number_format($totalExp); ?> exposures
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <?php if (in_array($exp['status'], ['draft', 'paused'])): ?>
                        <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="start"><input type="hidden" name="id" value="<?php echo (int) $exp['id']; ?>">
                            <button class="px-3 py-1.5 bg-[#1D4532] text-white rounded-lg text-xs font-medium hover:opacity-90"><i class="fas fa-play mr-1"></i>Start</button></form>
                    <?php elseif (in_array($exp['status'], ['burn_in', 'active'])): ?>
                        <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="pause"><input type="hidden" name="id" value="<?php echo (int) $exp['id']; ?>">
                            <button class="px-3 py-1.5 bg-white border border-[#EAEAE5] text-[#6B7C70] rounded-lg text-xs font-medium hover:bg-[#F2F4F1]"><i class="fas fa-pause mr-1"></i>Pause</button></form>
                    <?php endif; ?>
                    <a href="experiment-detail.php?id=<?php echo (int) $exp['id']; ?>" class="px-3 py-1.5 bg-white border border-[#EAEAE5] text-[#2C3E35] rounded-lg text-xs font-medium hover:bg-[#F2F4F1]">
                        Detail <i class="fas fa-arrow-right ml-1"></i></a>
                    <?php if (in_array($exp['status'], ['draft', 'archived'])): ?>
                        <form method="POST" onsubmit="return confirm('Delete this experiment permanently?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $exp['id']; ?>">
                            <button class="p-2 text-[#6B7C70] hover:text-[#D97757]" title="Delete"><i class="fas fa-trash"></i></button></form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Variant bars: traffic share + P(best) + RPV -->
            <div class="mt-5 space-y-2">
                <?php foreach ($exp['variants'] as $v):
                    if (in_array($v['status'], ['rejected'])) continue;
                    $share = $totalExp > 0 ? ((int) $v['exposures']) / $totalExp : 0;
                    $cr = ((int) $v['exposures']) > 0 ? ((int) $v['conversions']) / ((int) $v['exposures']) : 0;
                    $rpv = ((int) $v['exposures']) > 0 ? ((float) $v['revenue_total']) / ((int) $v['exposures']) : 0;
                    $isWinner = (int) $exp['winner_variant_id'] === (int) $v['id'];
                    $dim = in_array($v['status'], ['killed']) ? 'opacity-50' : '';
                ?>
                <div class="flex items-center gap-3 text-sm <?php echo $dim; ?>">
                    <div class="w-44 truncate text-[#2C3E35]">
                        <?php if ($isWinner): ?><i class="fas fa-crown text-[#B7791F] mr-1"></i><?php endif; ?>
                        <?php if ($v['source'] === 'ai_challenger'): ?><i class="fas fa-robot text-[#A4B4A6] mr-1" title="AI challenger"></i><?php endif; ?>
                        <?php echo htmlspecialchars($v['name']); ?>
                        <?php if ($v['status'] === 'killed'): ?><span class="text-[10px] text-[#D97757] uppercase ml-1">killed</span><?php endif; ?>
                        <?php if ($v['status'] === 'pending_approval'): ?><span class="text-[10px] text-[#B7791F] uppercase ml-1">pending</span><?php endif; ?>
                    </div>
                    <div class="flex-1 bg-[#F2F4F1] rounded-full h-3 overflow-hidden">
                        <div class="h-3 rounded-full <?php echo $isWinner ? 'bg-[#B7791F]' : 'bg-[#1D4532]'; ?>" style="width: <?php echo round($share * 100, 1); ?>%"></div>
                    </div>
                    <div class="w-14 text-right text-[#6B7C70] font-mono text-xs"><?php echo round($share * 100, 1); ?>%</div>
                    <div class="w-24 text-right text-[#6B7C70] font-mono text-xs" title="P(best)">P(best) <?php echo round(((float) $v['p_best']) * 100); ?>%</div>
                    <div class="w-20 text-right text-[#6B7C70] font-mono text-xs" title="Conversion rate"><?php echo round($cr * 100, 2); ?>%</div>
                    <div class="w-20 text-right text-[#2C3E35] font-mono text-xs" title="Revenue per visitor">$<?php echo number_format($rpv, 2); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<p class="mt-6 text-sm text-[#6B7C70]">
    <a href="?<?php echo isset($_GET['archived']) ? '' : 'archived=1'; ?>" class="hover:underline">
        <?php echo isset($_GET['archived']) ? 'Hide archived' : 'Show archived'; ?>
    </a>
    · Full engine docs: <span class="font-mono text-xs">docs/AB-ENGINE.md</span>
</p>

<!-- ============ Create Experiment Modal ============ -->
<div id="createModal" class="fixed inset-0 z-50 hidden bg-[#2C3E35]/50 overflow-y-auto h-full w-full backdrop-blur-sm"
    onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="relative top-10 mx-auto mb-10 border-0 w-full max-w-3xl shadow-2xl rounded-2xl bg-white overflow-hidden"
         x-data="experimentForm()">
        <div class="p-6 border-b border-[#EAEAE5] bg-[#FAFAF8] flex justify-between items-center">
            <h3 class="text-xl font-serif text-[#2C3E35]">New Experiment</h3>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-[#A4B4A6] hover:text-[#D97757]">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form method="POST" class="p-6 space-y-5" @submit="serializeVariants($event)">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="variants_json" id="variants_json">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#2C3E35] mb-1">Funnel</label>
                    <select name="funnel_name" x-model="funnel" required class="w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35]">
                        <?php foreach (ExperimentManager::FUNNELS as $f): ?>
                            <option value="<?php echo $f; ?>"><?php echo $f; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#2C3E35] mb-1">Name</label>
                    <input type="text" name="name" required placeholder="e.g. Hero headline urgency test"
                        class="w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#2C3E35] mb-1">Stage</label>
                    <select name="stage" required class="w-full px-4 py-2 border border-[#EAEAE5] rounded-lg">
                        <?php foreach (ExperimentManager::STAGES as $s): ?>
                            <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-[#A4B4A6] mt-1">Max one live experiment per funnel stage.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#2C3E35] mb-1">Primary metric (reward)</label>
                    <select name="primary_metric" required class="w-full px-4 py-2 border border-[#EAEAE5] rounded-lg">
                        <?php foreach (array_keys(ExperimentManager::METRICS) as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === 'assessment_start' ? 'selected' : ''; ?>><?php echo $m; ?><?php echo $m === 'purchase_rpv' ? ' (revenue per visitor)' : ''; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-[#A4B4A6] mt-1">Judge on the downstream-adjacent metric for the stage.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-[#2C3E35] mb-1">Hypothesis</label>
                <textarea name="hypothesis" rows="2" placeholder="We believe ... because ... measured by ..."
                    class="w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35]"></textarea>
            </div>

            <!-- Variants -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-[#2C3E35]">Variants</label>
                    <button type="button" @click="addVariant()" class="text-xs text-[#D97757] font-medium hover:underline"><i class="fas fa-plus mr-1"></i>Add variant</button>
                </div>
                <div class="space-y-3">
                    <template x-for="(v, i) in variants" :key="i">
                        <div class="p-4 bg-[#F9FAF9] rounded-xl border border-[#EAEAE5]">
                            <div class="flex gap-3 items-start">
                                <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <input type="text" x-model="v.name" :placeholder="i === 0 ? 'Control' : 'B: urgency headline'"
                                        class="px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm bg-white">
                                    <select x-model="v.type" class="px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm bg-white" :disabled="i === 0">
                                        <option value="control">control (current page)</option>
                                        <option value="element">element (override copy/price)</option>
                                        <option value="structural">structural (whole alternate dir)</option>
                                    </select>
                                </div>
                                <button type="button" x-show="i > 0" @click="variants.splice(i, 1)" class="p-2 text-[#A4B4A6] hover:text-[#D97757]"><i class="fas fa-times"></i></button>
                            </div>
                            <div x-show="v.type === 'element'" class="mt-3">
                                <textarea x-model="v.overrides" rows="4" placeholder='{"text": {"[data-exp=&apos;headline&apos;]": "New headline"}, "config": {"salePrice": 87}}'
                                    class="w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-xs font-mono bg-white"></textarea>
                                <p class="text-xs text-[#A4B4A6] mt-1">Override JSON: text / html / attr / style / config maps keyed by [data-exp] selectors.</p>
                            </div>
                            <div x-show="v.type === 'structural'" class="mt-3">
                                <select x-model="v.directory" class="w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm bg-white">
                                    <option value="">— pick a {funnel}__{slug} directory —</option>
                                    <?php foreach ($variantDirs as $d): ?>
                                        <option value="<?php echo htmlspecialchars($d['directory']); ?>" data-funnel="<?php echo htmlspecialchars($d['funnel']); ?>"><?php echo htmlspecialchars($d['directory']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-[#A4B4A6] mt-1">Create the directory first (e.g. <span class="font-mono">pcos__longform/</span> with its own index.html).</p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Guardrails -->
            <details class="bg-[#F9FAF9] rounded-xl border border-[#EAEAE5] p-4">
                <summary class="text-sm font-medium text-[#2C3E35] cursor-pointer">Guardrail settings (sensible defaults)</summary>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-xs text-[#6B7C70] mb-1">Burn-in hours</label>
                        <input type="number" name="burn_in_hours" value="48" min="0" class="w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm bg-white">
                    </div>
                    <div>
                        <label class="block text-xs text-[#6B7C70] mb-1">Exposure floor</label>
                        <input type="number" name="min_exposure_floor" value="0.10" step="0.01" min="0" max="0.5" class="w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm bg-white">
                    </div>
                    <div>
                        <label class="block text-xs text-[#6B7C70] mb-1">Min samples / variant</label>
                        <input type="number" name="min_samples_per_variant" value="1000" min="50" class="w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm bg-white">
                    </div>
                    <div>
                        <label class="block text-xs text-[#6B7C70] mb-1">Decide at P(best) &gt;</label>
                        <input type="number" name="decision_p_best" value="0.95" step="0.001" min="0.5" max="0.999" class="w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm bg-white">
                    </div>
                    <div>
                        <label class="block text-xs text-[#6B7C70] mb-1">Max expected loss</label>
                        <input type="number" name="decision_expected_loss" value="0.005" step="0.0001" min="0" class="w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-sm bg-white">
                    </div>
                </div>
            </details>

            <div class="pt-2 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                    class="px-4 py-2 border border-[#EAEAE5] rounded-lg text-[#6B7C70] hover:bg-[#F2F4F1]">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-[#2C3E35] text-white font-medium rounded-lg hover:bg-[#1a2621] shadow-md">
                    Create Experiment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function experimentForm() {
    return {
        funnel: 'pcos',
        variants: [
            { name: 'Control', type: 'control', overrides: '', directory: '' },
            { name: '', type: 'element', overrides: '', directory: '' }
        ],
        addVariant() {
            this.variants.push({ name: '', type: 'element', overrides: '', directory: '' });
        },
        serializeVariants(e) {
            const out = this.variants.map(v => ({
                name: v.name || (v.type === 'control' ? 'Control' : 'Variant'),
                type: v.type,
                directory: v.type === 'structural' ? v.directory : null,
                overrides: v.type === 'element' && v.overrides.trim() ? v.overrides : null
            }));
            document.getElementById('variants_json').value = JSON.stringify(out);
        }
    };
}
</script>

<?php include 'includes/footer.php'; ?>
