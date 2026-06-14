<?php
require_once 'auth.php';
require_once '../classes/Database.php';
require_once '../classes/ExperimentManager.php';

$manager = new ExperimentManager();
$message = '';
$error = '';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

// ---------------- Actions ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';
    $variantId = (int) ($_POST['variant_id'] ?? 0);

    switch ($action) {
        case 'kill_variant':
            $result = $manager->killVariant($variantId, 'admin', isset($_POST['generate_challenger']));
            $error = $result['error'] ?? '';
            if (!$error) {
                $message = 'Variant killed.';
                if (!empty($result['challenger']) && empty($result['challenger']['error'])) {
                    $message .= " AI challenger \"{$result['challenger']['name']}\" proposed ({$result['challenger']['compliance']}) — see approval queue.";
                } elseif (!empty($result['challenger']['error'])) {
                    $message .= ' Challenger generation failed: ' . $result['challenger']['error'];
                }
                logActivity('variant_killed', "Variant #$variantId");
            }
            break;

        case 'approve_variant':
            $result = $manager->approveVariant($variantId);
            $error = $result['error'] ?? '';
            if (!$error) { $message = 'Challenger approved — now serving traffic.'; logActivity('variant_approved', "Variant #$variantId"); }
            break;

        case 'reject_variant':
            $result = $manager->rejectVariant($variantId);
            $error = $result['error'] ?? '';
            if (!$error) $message = 'Challenger rejected.';
            break;

        case 'update_overrides':
            $result = $manager->updateVariantOverrides($variantId, $_POST['overrides'] ?? '', $_POST['variant_name'] ?? null);
            $error = $result['error'] ?? '';
            if (!$error) $message = 'Variant overrides updated.';
            break;

        case 'conclude':
            $result = $manager->concludeExperiment($id, (int) ($_POST['winner_variant_id'] ?? 0));
            $error = $result['error'] ?? '';
            if (!$error) {
                $message = "Experiment concluded. Winner: {$result['winner']}" . ($result['lift'] ? " (lift {$result['lift']})" : '') . '. 100% of traffic now routes to the winner.';
                logActivity('experiment_concluded', "Experiment #$id");
            }
            break;

        case 'start':
            $result = $manager->startExperiment($id);
            $error = $result['error'] ?? '';
            if (!$error) $message = "Experiment started ({$result['status']}).";
            break;

        case 'pause':
            $result = $manager->pauseExperiment($id);
            $error = $result['error'] ?? '';
            if (!$error) $message = 'Experiment paused.';
            break;
    }
}

$stats = $manager->getExperimentStats($id);
if (!$stats) {
    header('Location: experiments.php');
    exit;
}
$exp = $stats['experiment'];

$pageTitle = htmlspecialchars($exp['name']) . ' - Experiments';
include 'includes/header.php';

$waterfallLabels = [
    'view' => 'Views',
    'assessment_start' => 'Assess. start',
    'assessment_complete' => 'Assess. done',
    'results_view' => 'Results',
    'plan_select' => 'Plan select',
    'checkout_init' => 'Checkout',
    'purchase' => 'Purchase',
];
?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="experiments.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; All experiments</a>
        <h2 class="text-3xl font-serif text-[#2C3E35]"><?php echo htmlspecialchars($exp['name']); ?></h2>
        <p class="text-[#6B7C70] mt-1">
            <span class="font-mono"><?php echo htmlspecialchars($exp['funnel_name']); ?></span> ·
            <?php echo htmlspecialchars($exp['stage']); ?> stage ·
            metric <span class="font-mono"><?php echo htmlspecialchars($exp['primary_metric']); ?></span> ·
            status <strong><?php echo str_replace('_', '-', $exp['status']); ?></strong>
            <?php if ($stats['days_running']): ?> · <?php echo $stats['days_running']; ?> days running<?php endif; ?>
        </p>
        <?php if (!empty($exp['hypothesis'])): ?>
            <p class="text-sm text-[#A4B4A6] italic mt-1">"<?php echo htmlspecialchars($exp['hypothesis']); ?>"</p>
        <?php endif; ?>
    </div>
    <div class="flex gap-2">
        <?php if (in_array($exp['status'], ['draft', 'paused'])): ?>
            <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="start"><input type="hidden" name="id" value="<?php echo $id; ?>">
                <button class="px-4 py-2 bg-[#1D4532] text-white rounded-xl text-sm font-medium hover:opacity-90"><i class="fas fa-play mr-2"></i>Start</button></form>
        <?php elseif (in_array($exp['status'], ['burn_in', 'active'])): ?>
            <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="pause"><input type="hidden" name="id" value="<?php echo $id; ?>">
                <button class="px-4 py-2 bg-white border border-[#EAEAE5] text-[#6B7C70] rounded-xl text-sm font-medium hover:bg-[#F2F4F1]"><i class="fas fa-pause mr-2"></i>Pause</button></form>
        <?php endif; ?>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-8 p-4 bg-[#F2F4F1] border border-[#A4B4A6] text-[#2C3E35] rounded-xl"><i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-8 p-4 bg-[#FDF1E8] border border-[#D97757] text-[#D97757] rounded-xl"><i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- ============ Funnel waterfall per variant ============ -->
<div class="luxury-card overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8] flex items-center justify-between">
        <h3 class="text-lg font-serif text-[#2C3E35]"><i class="fas fa-water mr-2 text-[#D97757] text-sm"></i>Funnel Waterfall</h3>
        <span class="text-xs text-[#A4B4A6]">distinct sessions per step</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-[#FAFAF8] text-left text-xs uppercase tracking-wider text-[#6B7C70]">
                    <th class="px-6 py-3">Variant</th>
                    <?php foreach ($waterfallLabels as $label): ?><th class="px-3 py-3 text-right"><?php echo $label; ?></th><?php endforeach; ?>
                    <th class="px-3 py-3 text-right">CR</th>
                    <th class="px-3 py-3 text-right">RPV</th>
                    <th class="px-3 py-3 text-right">P(best)</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#EAEAE5]">
                <?php foreach ($stats['variants'] as $v):
                    if ($v['status'] === 'rejected') continue;
                    $isWinner = (int) $exp['winner_variant_id'] === $v['id'];
                    $dim = $v['status'] === 'killed' ? 'opacity-50' : '';
                ?>
                <tr class="<?php echo $dim; ?> hover:bg-[#FDFCF8]">
                    <td class="px-6 py-3">
                        <div class="flex items-center gap-2">
                            <?php if ($isWinner): ?><i class="fas fa-crown text-[#B7791F]"></i><?php endif; ?>
                            <?php if ($v['source'] === 'ai_challenger'): ?><i class="fas fa-robot text-[#A4B4A6]" title="AI challenger"></i><?php endif; ?>
                            <span class="text-[#2C3E35] font-medium"><?php echo htmlspecialchars($v['name']); ?></span>
                            <span class="text-[10px] uppercase text-[#A4B4A6]"><?php echo $v['type']; ?></span>
                            <?php if ($v['status'] !== 'active' && !$isWinner): ?><span class="text-[10px] uppercase text-[#D97757]"><?php echo $v['status']; ?></span><?php endif; ?>
                        </div>
                    </td>
                    <?php foreach (array_keys($waterfallLabels) as $step): ?>
                        <td class="px-3 py-3 text-right font-mono text-xs text-[#6B7C70]"><?php echo number_format($v['waterfall'][$step]); ?></td>
                    <?php endforeach; ?>
                    <td class="px-3 py-3 text-right font-mono text-xs"><?php echo round($v['conversion_rate'] * 100, 2); ?>%</td>
                    <td class="px-3 py-3 text-right font-mono text-xs">$<?php echo number_format($v['rpv'], 2); ?></td>
                    <td class="px-3 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <div class="w-16 bg-[#F2F4F1] rounded-full h-2"><div class="h-2 rounded-full bg-[#1D4532]" style="width:<?php echo round($v['p_best'] * 100); ?>%"></div></div>
                            <span class="font-mono text-xs w-9 text-right"><?php echo round($v['p_best'] * 100); ?>%</span>
                        </div>
                    </td>
                    <td class="px-6 py-3 text-right whitespace-nowrap">
                        <?php if ($v['status'] === 'active' && $v['type'] !== 'control' && in_array($exp['status'], ['burn_in', 'active', 'paused'])): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Kill this variant? Optionally generates an AI challenger to replace it.');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="kill_variant">
                                <input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="variant_id" value="<?php echo $v['id']; ?>">
                                <input type="hidden" name="generate_challenger" value="1">
                                <button class="px-2 py-1 text-xs text-[#D97757] hover:underline" title="Kill + ask AI for a challenger"><i class="fas fa-skull mr-1"></i>Kill</button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array($exp['status'], ['burn_in', 'active', 'paused']) && in_array($v['status'], ['active'])): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Conclude the experiment with this variant as winner? 100% of traffic will route to it.');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="conclude">
                                <input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="winner_variant_id" value="<?php echo $v['id']; ?>">
                                <button class="px-2 py-1 text-xs text-[#1D4532] hover:underline" title="Promote as winner"><i class="fas fa-crown mr-1"></i>Promote</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ Daily trend chart ============ -->
<div class="luxury-card p-6 mb-8">
    <h3 class="text-lg font-serif text-[#2C3E35] mb-4"><i class="fas fa-chart-line mr-2 text-[#D97757] text-sm"></i>Daily Trend (30 days)</h3>
    <canvas id="trendChart" height="90"></canvas>
</div>

<!-- ============ AI insight ============ -->
<?php $insight = $stats['latest_insight']; ?>
<div class="luxury-card p-6 mb-8" id="insight">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-serif text-[#2C3E35]"><i class="fas fa-brain mr-2 text-[#D97757] text-sm"></i>Latest AI Diagnostic</h3>
        <?php if ($insight): ?><span class="text-xs text-[#A4B4A6]"><?php echo htmlspecialchars($insight['created_at']); ?></span><?php endif; ?>
    </div>
    <?php if (!$insight): ?>
        <p class="text-sm text-[#6B7C70]">No diagnostic yet. The weekly cron (<span class="font-mono text-xs">backend/cron/ai_diagnostics.php</span>) will populate this, or run it manually.</p>
    <?php else:
        $c = is_array($insight['content']) ? $insight['content'] : (json_decode($insight['content'], true) ?: []);
    ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
            <?php if (!empty($c['funnel_leaks'])): ?>
            <div>
                <p class="font-medium text-[#2C3E35] mb-2">Funnel leaks</p>
                <ul class="space-y-1.5">
                    <?php foreach ($c['funnel_leaks'] as $leak): ?>
                        <li class="flex items-start gap-2 text-[#6B7C70]">
                            <span class="px-1.5 py-0.5 text-[10px] uppercase rounded font-bold <?php echo ($leak['severity'] ?? '') === 'high' ? 'bg-[#FDE8E8] text-[#C0392B]' : 'bg-[#FFF4E0] text-[#B7791F]'; ?>"><?php echo htmlspecialchars($leak['severity'] ?? '?'); ?></span>
                            <span><strong><?php echo htmlspecialchars($leak['stage'] ?? ''); ?></strong> — <?php echo htmlspecialchars($leak['vs_baseline'] ?? ''); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if (!empty($c['variant_analysis'])): ?>
            <div>
                <p class="font-medium text-[#2C3E35] mb-2">Variant analysis</p>
                <ul class="space-y-1.5">
                    <?php foreach ($c['variant_analysis'] as $va): ?>
                        <li class="text-[#6B7C70]"><strong class="text-[#2C3E35]"><?php echo htmlspecialchars($va['variant'] ?? ''); ?>:</strong> <?php echo htmlspecialchars($va['verdict'] ?? ''); ?> <span class="text-[#A4B4A6]"><?php echo htmlspecialchars($va['likely_cause'] ?? ''); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if (!empty($c['suggestions'])): ?>
            <div class="md:col-span-2">
                <p class="font-medium text-[#2C3E35] mb-2">Test suggestions</p>
                <ol class="space-y-1.5 list-decimal list-inside">
                    <?php foreach ($c['suggestions'] as $s): ?>
                        <li class="text-[#6B7C70]"><strong class="text-[#2C3E35]"><?php echo htmlspecialchars($s['test_idea'] ?? ''); ?></strong>
                            (<?php echo htmlspecialchars($s['stage'] ?? ''); ?>, expected: <?php echo htmlspecialchars($s['expected_impact'] ?? '?'); ?>) — <?php echo htmlspecialchars($s['rationale'] ?? ''); ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>
            <?php if (!empty($c['overlap_warnings'])): ?>
            <div class="md:col-span-2 p-3 bg-[#FDF1E8] rounded-lg text-[#D97757]">
                <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars(implode('; ', array_map(function ($w) { return is_string($w) ? $w : json_encode($w); }, $c['overlap_warnings']))); ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ============ Approval queue ============ -->
<div class="luxury-card overflow-hidden mb-8" id="approval">
    <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8]">
        <h3 class="text-lg font-serif text-[#2C3E35]"><i class="fas fa-robot mr-2 text-[#D97757] text-sm"></i>AI Challenger Approval Queue</h3>
    </div>
    <?php if (empty($stats['approval_queue'])): ?>
        <div class="p-8 text-center text-sm text-[#6B7C70]">No challengers awaiting approval. Kill a losing variant to have the AI propose a replacement.</div>
    <?php else: ?>
        <?php
        // Control copy for side-by-side diff
        require_once '../classes/ChallengerGenerator.php';
        $controlCopy = (new ChallengerGenerator())->extractControlCopy($exp['funnel_name']);
        ?>
        <div class="divide-y divide-[#EAEAE5]">
            <?php foreach ($stats['approval_queue'] as $q):
                $ov = is_array($q['overrides']) ? $q['overrides'] : [];
                $compliant = $q['compliance_status'] === 'compliant';
            ?>
            <div class="p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
                    <div>
                        <span class="text-lg font-serif text-[#2C3E35]"><?php echo htmlspecialchars($q['name']); ?></span>
                        <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full <?php echo $compliant ? 'bg-[#E6F4EA] text-[#1D4532]' : 'bg-[#FDE8E8] text-[#C0392B]'; ?>">
                            <i class="fas <?php echo $compliant ? 'fa-shield-check fa-check' : 'fa-triangle-exclamation'; ?> mr-1"></i><?php echo $compliant ? 'compliant' : 'NON-COMPLIANT'; ?>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" onsubmit="return confirm('Approve this AI challenger? It will start receiving traffic.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="approve_variant">
                            <input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="variant_id" value="<?php echo (int) $q['id']; ?>">
                            <button class="px-4 py-2 bg-[#1D4532] text-white rounded-lg text-xs font-medium hover:opacity-90" <?php echo $compliant ? '' : 'title="Review the compliance notes before approving"'; ?>>
                                <i class="fas fa-check mr-1"></i>Approve</button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="reject_variant">
                            <input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="variant_id" value="<?php echo (int) $q['id']; ?>">
                            <button class="px-4 py-2 bg-white border border-[#EAEAE5] text-[#D97757] rounded-lg text-xs font-medium hover:bg-[#FDF1E8]">
                                <i class="fas fa-times mr-1"></i>Reject</button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($q['ai_rationale'])): ?>
                    <p class="text-sm text-[#6B7C70] mb-3"><i class="fas fa-lightbulb text-[#B7791F] mr-1"></i><?php echo htmlspecialchars($q['ai_rationale']); ?></p>
                <?php endif; ?>
                <?php if (!$compliant && !empty($q['compliance_notes'])): ?>
                    <p class="text-sm text-[#C0392B] mb-3"><i class="fas fa-shield-halved mr-1"></i><?php echo htmlspecialchars($q['compliance_notes']); ?></p>
                <?php endif; ?>

                <!-- Side-by-side diff vs control -->
                <?php if (!empty($ov['text']) || !empty($ov['html'])): ?>
                <div class="overflow-x-auto mb-3">
                    <table class="w-full text-xs border border-[#EAEAE5] rounded-lg overflow-hidden">
                        <thead><tr class="bg-[#FAFAF8] text-[#6B7C70] text-left">
                            <th class="px-3 py-2 w-44">Element</th><th class="px-3 py-2">Control (current)</th><th class="px-3 py-2">Challenger</th>
                        </tr></thead>
                        <tbody class="divide-y divide-[#EAEAE5]">
                            <?php foreach (array_merge($ov['text'] ?? [], $ov['html'] ?? []) as $sel => $newVal): ?>
                                <tr>
                                    <td class="px-3 py-2 font-mono text-[#A4B4A6]"><?php echo htmlspecialchars($sel); ?></td>
                                    <td class="px-3 py-2 text-[#6B7C70]"><?php echo htmlspecialchars($controlCopy[$sel] ?? '—'); ?></td>
                                    <td class="px-3 py-2 text-[#1D4532] font-medium"><?php echo htmlspecialchars(strip_tags($newVal)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Editable raw overrides -->
                <details>
                    <summary class="text-xs text-[#6B7C70] cursor-pointer hover:text-[#2C3E35]">Edit raw overrides JSON</summary>
                    <form method="POST" class="mt-2">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="update_overrides">
                        <input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="variant_id" value="<?php echo (int) $q['id']; ?>">
                        <textarea name="overrides" rows="6" class="w-full px-3 py-2 border border-[#EAEAE5] rounded-lg text-xs font-mono"><?php echo htmlspecialchars(json_encode($ov, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></textarea>
                        <button class="mt-2 px-4 py-1.5 bg-[#2C3E35] text-white rounded-lg text-xs">Save edits</button>
                    </form>
                </details>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const trend = <?php echo json_encode($stats['daily_trend'], JSON_NUMERIC_CHECK); ?>;
    const variants = <?php echo json_encode(array_map(function ($v) {
        return ['id' => $v['id'], 'name' => $v['name']];
    }, $stats['variants']), JSON_NUMERIC_CHECK); ?>;
    const isRevenue = <?php echo json_encode($exp['reward_type'] === 'revenue'); ?>;
    const metricCol = <?php echo json_encode([
        'assessment_start' => 'assessment_starts',
        'assessment_complete' => 'assessment_completes',
        'results_view' => 'results_views',
        'plan_select' => 'plan_selects',
        'checkout_init' => 'checkout_inits',
        'purchase' => 'purchases',
        'purchase_rpv' => 'purchases',
    ][$exp['primary_metric']] ?? 'purchases'); ?>;

    const dates = [...new Set(trend.map(r => r.metric_date))].sort();
    const palette = ['#1D4532', '#D97757', '#B7791F', '#5B7C99', '#8E6C88', '#4A8C7C'];

    const datasets = variants.map((v, i) => {
        const data = dates.map(d => {
            const row = trend.find(r => r.variant_id == v.id && r.metric_date === d);
            if (!row) return null;
            const exp = Number(row.exposures) || 0;
            if (!exp) return 0;
            return isRevenue ? +(Number(row.revenue) / exp).toFixed(2)
                             : +((Number(row[metricCol]) / exp) * 100).toFixed(2);
        });
        return {
            label: v.name, data,
            borderColor: palette[i % palette.length],
            backgroundColor: palette[i % palette.length] + '22',
            tension: 0.3, spanGaps: true, pointRadius: 2
        };
    });

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: { labels: dates, datasets },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: isRevenue ? 'RPV ($/visitor)' : 'Conversion rate (%)', font: { size: 11 } }
                }
            }
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
