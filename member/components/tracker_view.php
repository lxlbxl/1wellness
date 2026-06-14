<?php
/**
 * Vitals Sync / Tracker view — condition-driven.
 * Metrics rendered from ConditionsRegistry; data loaded from get-tracker-history.php.
 */
$trackerMetrics = $conditionCfg['tracker_metrics'] ?? [];
$trackerKeys    = array_column($trackerMetrics, 'key');
$metricsJson    = json_encode($trackerMetrics);
$keysParam      = implode(',', $trackerKeys);
?>
<div id="trackerView" class="view-section hidden space-y-12">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 pb-8 border-b border-sage-100">
        <div class="space-y-4 max-w-2xl">
            <h2 class="text-4xl font-serif text-sage-600">Vitals Sync</h2>
            <p class="text-sage-400 text-sm leading-relaxed">
                Track your daily metrics to build a picture of your progress over time.
            </p>
        </div>
        <div class="flex gap-4">
            <button onclick="openTrackerModal()" class="px-6 py-3 bg-sage-500 text-white rounded-2xl text-xs font-bold uppercase tracking-widest shadow-xl shadow-sage-500/20 hover:scale-[1.02] transition-all">
                Log Today
            </button>
        </div>
    </div>

    <!-- Metrics Grid — condition-driven -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8" id="trackerMetricGrid">
        <?php foreach ($trackerMetrics as $metric):
            $mKey   = htmlspecialchars($metric['key']);
            $mLabel = htmlspecialchars($metric['label']);
            $mType  = $metric['type'];
            $mMax   = $metric['max'] ?? 5;
        ?>
        <div class="bg-white p-8 rounded-[3rem] border border-sage-50 shadow-sm relative overflow-hidden group hover:shadow-2xl transition-all duration-500"
             data-metric="<?php echo $mKey; ?>">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 rounded-2xl bg-sage-50 text-sage-500 flex items-center justify-center transition-colors group-hover:bg-sage-500 group-hover:text-white">
                    <i data-lucide="activity" class="w-6 h-6"></i>
                </div>
                <div>
                    <h4 class="text-xl font-serif text-sage-600"><?php echo $mLabel; ?></h4>
                    <p class="text-[9px] font-bold text-sage-300 uppercase tracking-widest">14-day history</p>
                </div>
            </div>
            <div class="flex items-baseline gap-2 mb-2">
                <h3 id="tracker-val-<?php echo $mKey; ?>" class="text-4xl font-serif text-sage-600">—</h3>
                <?php if ($mType === 'scale'): ?>
                <span class="text-sage-300 text-[10px] font-bold uppercase tracking-widest">/ <?php echo $mMax; ?></span>
                <?php endif; ?>
            </div>
            <p id="tracker-sub-<?php echo $mKey; ?>" class="text-sage-400 text-xs font-medium">&nbsp;</p>
            <!-- Mini bar chart -->
            <div class="mt-6 h-16 flex items-end gap-0.5" id="tracker-chart-<?php echo $mKey; ?>">
                <?php for($b=0;$b<14;$b++): ?>
                <div class="flex-1 bg-sage-50 rounded-sm tracker-bar" style="height:20%"></div>
                <?php endfor; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 7-day compliance strip -->
    <div class="luxury-card p-8">
        <h3 class="font-serif text-[#2C3E35] text-xl mb-6">Last 7 Days</h3>
        <div class="grid grid-cols-7 gap-2" id="trackerCompliance">
            <?php for($d=6; $d>=0; $d--):
                $day = date('D', strtotime("-{$d} days"));
                $dt  = date('Y-m-d', strtotime("-{$d} days"));
            ?>
            <div class="flex flex-col items-center gap-2">
                <span class="text-[9px] font-bold text-sage-300 uppercase"><?php echo $day; ?></span>
                <div class="w-10 h-10 rounded-xl bg-sage-50 flex items-center justify-center compliance-dot"
                     data-date="<?php echo $dt; ?>"
                     title="<?php echo $dt; ?>">
                    <i data-lucide="minus" class="w-4 h-4 text-sage-300"></i>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- Log Tracker Modal -->
<div id="trackerModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl p-8 w-full max-w-md max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="flex justify-between items-center mb-6">
            <h3 class="font-serif text-2xl text-sage-600">Log Today's Metrics</h3>
            <button onclick="closeTrackerModal()" class="w-8 h-8 rounded-full bg-sage-50 flex items-center justify-center hover:bg-sage-100">
                <i data-lucide="x" class="w-4 h-4 text-sage-400"></i>
            </button>
        </div>
        <form id="trackerForm" class="space-y-5">
            <?php foreach ($trackerMetrics as $metric):
                $mKey   = htmlspecialchars($metric['key']);
                $mLabel = htmlspecialchars($metric['label']);
                $mType  = $metric['type'];
                $mMin   = $metric['min'] ?? 0;
                $mMax   = $metric['max'] ?? 100;
            ?>
            <div>
                <label class="block text-xs font-bold text-sage-400 uppercase tracking-widest mb-2"><?php echo $mLabel; ?></label>
                <?php if ($mType === 'scale'): ?>
                <div class="flex gap-2">
                    <?php for($s=1; $s<=$mMax; $s++): ?>
                    <button type="button"
                        onclick="setScale('<?php echo $mKey; ?>', <?php echo $s; ?>)"
                        id="scale-<?php echo $mKey; ?>-<?php echo $s; ?>"
                        class="flex-1 h-10 rounded-xl bg-sage-50 text-sm font-bold text-sage-400 hover:bg-sage-500 hover:text-white transition-colors scale-btn"
                        data-metric="<?php echo $mKey; ?>" data-val="<?php echo $s; ?>">
                        <?php echo $s; ?>
                    </button>
                    <?php endfor; ?>
                </div>
                <input type="hidden" id="input-<?php echo $mKey; ?>" name="<?php echo $mKey; ?>" value="">
                <?php elseif ($mType === 'boolean'): ?>
                <div class="flex gap-3">
                    <button type="button" onclick="setBool('<?php echo $mKey; ?>', 1)"
                        id="bool-<?php echo $mKey; ?>-yes"
                        class="flex-1 h-10 rounded-xl bg-sage-50 text-sm font-bold text-sage-400 hover:bg-sage-500 hover:text-white transition-colors">
                        Yes
                    </button>
                    <button type="button" onclick="setBool('<?php echo $mKey; ?>', 0)"
                        id="bool-<?php echo $mKey; ?>-no"
                        class="flex-1 h-10 rounded-xl bg-sage-50 text-sm font-bold text-sage-400 hover:bg-sage-500 hover:text-white transition-colors">
                        No
                    </button>
                </div>
                <input type="hidden" id="input-<?php echo $mKey; ?>" name="<?php echo $mKey; ?>" value="">
                <?php else: ?>
                <input type="number" id="input-<?php echo $mKey; ?>" name="<?php echo $mKey; ?>"
                    min="<?php echo $mMin; ?>" max="<?php echo $mMax; ?>" step="0.1"
                    placeholder="0"
                    class="w-full px-4 py-3 bg-sage-50 border border-sage-100 rounded-xl text-sage-600 focus:outline-none focus:border-sage-500 focus:ring-2 focus:ring-sage-500/20">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div id="trackerFormMsg" class="hidden text-sm text-center py-2 rounded-xl"></div>
            <button type="submit"
                class="w-full py-4 bg-sage-500 text-white font-bold rounded-2xl hover:bg-sage-600 transition-colors uppercase tracking-widest text-xs">
                Save Today's Log
            </button>
        </form>
    </div>
</div>

<script>
// ── Tracker view logic ─────────────────────────────────────────────────────
(function(){
    const METRICS = <?php echo $metricsJson; ?>;
    const KEYS_PARAM = '<?php echo $keysParam; ?>';

    function loadTrackerHistory() {
        fetch('/backend/api/get-tracker-history.php?days=14&metrics=' + KEYS_PARAM)
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                // Populate latest values
                Object.entries(d.latest || {}).forEach(([key, val]) => {
                    const el = document.getElementById('tracker-val-' + key);
                    if (el) el.textContent = Number.isInteger(val) ? val : val.toFixed(1);
                });
                // Populate mini bar charts
                Object.entries(d.series || {}).forEach(([key, vals]) => {
                    const chart = document.getElementById('tracker-chart-' + key);
                    if (!chart) return;
                    const bars = chart.querySelectorAll('.tracker-bar');
                    const nonNull = vals.filter(v => v !== null);
                    const max = nonNull.length ? Math.max(...nonNull) : 1;
                    bars.forEach((bar, i) => {
                        const v = vals[i];
                        const pct = v !== null && max > 0 ? Math.max(8, Math.round((v / max) * 100)) : 8;
                        bar.style.height = pct + '%';
                        bar.style.background = v !== null ? 'var(--sage-500, #2C3E35)' : '#E3E8E1';
                        bar.title = v !== null ? key + ': ' + v : 'No data';
                    });
                });
                // Compliance dots
                const byDate = {};
                Object.entries(d.series || {}).forEach(([key, vals]) => {
                    d.dates.forEach((date, i) => {
                        if (vals[i] !== null) byDate[date] = (byDate[date] || 0) + 1;
                    });
                });
                document.querySelectorAll('.compliance-dot').forEach(dot => {
                    const date = dot.dataset.date;
                    if (byDate[date]) {
                        dot.innerHTML = '<i data-lucide="check" class="w-4 h-4 text-emerald-500"></i>';
                        dot.classList.add('bg-emerald-50');
                    }
                });
                if (typeof lucide !== 'undefined') lucide.createIcons();
            })
            .catch(() => {});
    }

    // Scale button helpers
    window.setScale = function(key, val) {
        document.getElementById('input-' + key).value = val;
        document.querySelectorAll('[id^="scale-' + key + '-"]').forEach(btn => {
            const bVal = parseInt(btn.dataset.val);
            btn.classList.toggle('bg-sage-500', bVal <= val);
            btn.classList.toggle('text-white', bVal <= val);
            btn.classList.toggle('bg-sage-50', bVal > val);
            btn.classList.toggle('text-sage-400', bVal > val);
        });
    };
    window.setBool = function(key, val) {
        document.getElementById('input-' + key).value = val;
        const yes = document.getElementById('bool-' + key + '-yes');
        const no  = document.getElementById('bool-' + key + '-no');
        if (yes && no) {
            yes.classList.toggle('bg-sage-500', val === 1); yes.classList.toggle('text-white', val === 1);
            yes.classList.toggle('bg-sage-50', val !== 1);  yes.classList.toggle('text-sage-400', val !== 1);
            no.classList.toggle('bg-sage-500', val === 0);  no.classList.toggle('text-white', val === 0);
            no.classList.toggle('bg-sage-50', val !== 0);   no.classList.toggle('text-sage-400', val !== 0);
        }
    };

    window.openTrackerModal = function() {
        document.getElementById('trackerModal').classList.remove('hidden');
    };
    window.closeTrackerModal = function() {
        document.getElementById('trackerModal').classList.add('hidden');
    };

    // Form submit
    document.getElementById('trackerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const metrics = {};
        METRICS.forEach(m => {
            const el = document.getElementById('input-' + m.key);
            if (el && el.value !== '') metrics[m.key] = parseFloat(el.value);
        });
        if (!Object.keys(metrics).length) {
            showMsg('Please fill in at least one metric.', 'error'); return;
        }
        fetch('/backend/api/log-tracker.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'include',
            body: JSON.stringify({ metrics })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showMsg('Saved! ' + d.saved + ' metric(s) logged.', 'success');
                setTimeout(() => { closeTrackerModal(); loadTrackerHistory(); }, 1200);
            } else {
                showMsg(d.error || 'Save failed.', 'error');
            }
        })
        .catch(() => showMsg('Network error.', 'error'));
    });

    function showMsg(msg, type) {
        const el = document.getElementById('trackerFormMsg');
        el.textContent = msg;
        el.className = 'text-sm text-center py-2 rounded-xl ' + (type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700');
        el.classList.remove('hidden');
    }

    // Load when tracker view becomes active
    document.addEventListener('DOMContentLoaded', () => {
        const tv = document.getElementById('trackerView');
        if (!tv) return;
        const obs = new MutationObserver(() => {
            if (tv.classList.contains('active')) loadTrackerHistory();
        });
        obs.observe(tv, { attributes: true, attributeFilter: ['class'] });
    });
})();
</script>
