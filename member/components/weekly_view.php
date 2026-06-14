<?php
/**
 * 1wellness Member Weekly View Component
 * Loads live data from /member/api/weekly-review.php
 */
$weekStart = date('M jS', strtotime('monday this week'));
$weekEnd   = date('M jS', strtotime('sunday this week'));
?>
<div id="weeklyView" class="view-section hidden space-y-12">
    <div class="flex items-center justify-between pb-8 border-b border-sage-100">
        <div>
            <h2 class="text-4xl font-serif text-sage-600">Weekly Protocol</h2>
            <p class="text-sage-400 text-sm mt-2">Week of <?php echo $weekStart; ?> &ndash; <?php echo $weekEnd; ?></p>
        </div>
        <div class="flex gap-4">
            <button class="w-12 h-12 rounded-2xl bg-white border border-sage-100 flex items-center justify-center text-sage-400 hover:bg-sage-50 transition-all">
                <i data-lucide="chevron-left" class="w-5 h-5"></i>
            </button>
            <button class="w-12 h-12 rounded-2xl bg-white border border-sage-100 flex items-center justify-center text-sage-400 hover:bg-sage-50 transition-all">
                <i data-lucide="chevron-right" class="w-5 h-5"></i>
            </button>
        </div>
    </div>

    <!-- Weekly Schedule -->
    <div id="weeklySchedule" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-6">
        <?php
        $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        foreach ($days as $i => $day):
            $ts  = strtotime('monday this week +' . $i . ' days');
            $dt  = date('Y-m-d', $ts);
            $isPast = $dt < date('Y-m-d');
            $isToday = $dt === date('Y-m-d');
        ?>
        <div class="bg-white p-6 rounded-[2.5rem] border <?php echo $isToday ? 'border-sage-500 shadow-lg shadow-sage-500/10' : 'border-sage-50 shadow-sm'; ?> hover:shadow-xl transition-all group cursor-pointer weekly-day-card" data-date="<?php echo $dt; ?>">
            <p class="text-[10px] font-bold <?php echo $isToday ? 'text-sage-500' : 'text-sage-300'; ?> uppercase tracking-widest mb-4"><?php echo $isToday ? 'Today' : $day; ?></p>
            <div class="w-10 h-10 rounded-2xl <?php echo $isToday ? 'bg-sage-500 text-white' : 'bg-sage-50 text-sage-400'; ?> flex items-center justify-center mb-6 group-hover:bg-sage-500 group-hover:text-white transition-all weekly-day-icon">
                <i data-lucide="utensils" class="w-4 h-4"></i>
            </div>
            <h4 class="text-lg font-serif text-sage-600 mb-2"><?php echo date('M j', $ts); ?></h4>
            <p class="text-sage-400 text-[10px] leading-relaxed weekly-day-status">Loading&hellip;</p>
            <!-- Compliance dot injected by JS -->
            <div class="mt-4 weekly-dot-row flex gap-1"></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Weekly Insights row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 pt-4">

        <!-- Performance chart (left 2/3) -->
        <div class="lg:col-span-2 bg-sage-900 rounded-[3rem] p-10 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 p-10 opacity-10">
                <i data-lucide="sparkles" class="w-32 h-32"></i>
            </div>
            <div class="relative z-10">
                <h3 class="text-3xl font-serif mb-2">Weekly Performance</h3>
                <p id="weeklyComplianceSub" class="text-white/50 text-sm mb-8">Loading&hellip;</p>

                <!-- Stat pills -->
                <div class="flex gap-6 mb-8 flex-wrap">
                    <div class="bg-white/10 rounded-2xl px-5 py-3 flex items-center gap-3">
                        <i data-lucide="flame" class="w-5 h-5 text-coral-400"></i>
                        <div>
                            <p class="text-[10px] text-white/50 uppercase tracking-widest">Streak</p>
                            <p id="weeklyStreakVal" class="text-2xl font-serif font-bold">0</p>
                        </div>
                    </div>
                    <div class="bg-white/10 rounded-2xl px-5 py-3 flex items-center gap-3">
                        <i data-lucide="calendar-check" class="w-5 h-5 text-emerald-400"></i>
                        <div>
                            <p class="text-[10px] text-white/50 uppercase tracking-widest">Days Logged</p>
                            <p id="weeklyDaysVal" class="text-2xl font-serif font-bold">0</p>
                        </div>
                    </div>
                    <div class="bg-white/10 rounded-2xl px-5 py-3 flex items-center gap-3">
                        <i data-lucide="target" class="w-5 h-5 text-yellow-300"></i>
                        <div>
                            <p class="text-[10px] text-white/50 uppercase tracking-widest">Compliance</p>
                            <p id="weeklyPctVal" class="text-2xl font-serif font-bold">0%</p>
                        </div>
                    </div>
                </div>

                <!-- 7-day bar chart -->
                <div id="weeklyBarChart" class="grid grid-cols-7 gap-4 items-end h-32">
                    <?php foreach ($days as $d): ?>
                    <div class="flex flex-col items-center gap-3 weekly-bar-col" data-day="<?php echo substr($d,0,3); ?>">
                        <div class="w-full bg-white/10 rounded-2xl overflow-hidden relative group weekly-bar" style="height: 10%">
                            <div class="absolute inset-0 bg-coral-400 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        </div>
                        <span class="text-[8px] font-bold text-white/40 uppercase tracking-widest"><?php echo substr($d,0,3); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right panel: achievements + tracker summary -->
        <div class="space-y-6">

            <!-- Milestones -->
            <div class="bg-cream-100 p-8 rounded-[3rem] border border-white">
                <div class="w-14 h-14 rounded-3xl bg-white flex items-center justify-center text-coral-400 shadow-sm mb-5">
                    <i data-lucide="award" class="w-7 h-7"></i>
                </div>
                <h3 class="text-xl font-serif text-sage-600 mb-3">This Week&rsquo;s Badges</h3>
                <div id="weeklyMilestones" class="space-y-2">
                    <p class="text-sage-400 text-xs">Loading&hellip;</p>
                </div>
            </div>

            <!-- Tracker averages -->
            <div class="bg-white p-8 rounded-[3rem] border border-sage-50 shadow-sm">
                <h3 class="text-xl font-serif text-sage-600 mb-5">7-Day Averages</h3>
                <div id="weeklyTrackerAvgs" class="space-y-4">
                    <p class="text-sage-400 text-xs">Loading&hellip;</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const MILESTONE_LABELS = {
        streak_1:            '1-Day Streak',
        streak_3:            '3-Day Streak',
        streak_7:            '7-Day Streak 🔥',
        streak_14:           '14-Day Streak',
        streak_30:           '30-Day Streak',
        streak_60:           '60-Day Streak',
        streak_90:           '90-Day Streak',
        first_log:           'First Daily Log',
        onboarding_complete: 'Onboarding Complete',
    };

    let loaded = false;

    async function loadWeeklyReview() {
        if (loaded) return;
        loaded = true;

        let data;
        try {
            const r = await fetch('/member/api/weekly-review.php');
            data = await r.json();
        } catch (e) {
            console.warn('weekly-review fetch failed', e);
            return;
        }
        if (!data.success) return;

        // --- Stat pills ---
        document.getElementById('weeklyStreakVal').textContent = data.streak || 0;
        document.getElementById('weeklyDaysVal').textContent   = data.days_logged || 0;
        const pct = data.compliance_pct || 0;
        document.getElementById('weeklyPctVal').textContent    = pct + '%';
        document.getElementById('weeklyComplianceSub').textContent =
            pct >= 70 ? 'Great week — keep the momentum!'
          : pct >= 40 ? 'Solid effort — small wins compound.'
          :             'Every log counts — start today.';

        // --- Bar chart: height proportional to compliance_pct spread over 7 days ---
        // Use days_logged to infer active days; distribute across the week
        // (weekly-review doesn't return per-day breakdown, so we use tracker history)
        try {
            const metrics = (window.CONDITION_CFG?.tracker_metrics || []).map(m => m.key).join(',');
            const hr = await fetch(`/backend/api/get-tracker-history.php?days=7&metrics=${metrics}`);
            const hd = await hr.json();
            if (hd.success && hd.dates) {
                const bars = document.querySelectorAll('.weekly-bar-col');
                bars.forEach(col => {
                    const dayAbbr = col.dataset.day; // 'Mon','Tue',...
                    // Find the date for this column
                    const dayIdx = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].indexOf(dayAbbr);
                    if (dayIdx === -1) return;
                    // Calculate the date for that weekday
                    const weekStart = new Date();
                    const dow = weekStart.getDay(); // 0=Sun
                    const diffToMon = (dow === 0) ? -6 : 1 - dow;
                    const monDate = new Date(weekStart);
                    monDate.setDate(weekStart.getDate() + diffToMon + dayIdx);
                    const isoDate = monDate.toISOString().slice(0, 10);
                    const dateIdx = hd.dates.indexOf(isoDate);
                    // Check if any metric was logged that day
                    let hasData = false;
                    if (dateIdx !== -1) {
                        for (const series of Object.values(hd.series)) {
                            if (series[dateIdx] !== null && series[dateIdx] !== undefined) {
                                hasData = true;
                                break;
                            }
                        }
                    }
                    const bar = col.querySelector('.weekly-bar');
                    if (bar) {
                        bar.style.height = hasData ? '80%' : '10%';
                        if (hasData) bar.classList.add('bg-white/30');
                    }
                    // Update day card status
                    const cards = document.querySelectorAll('.weekly-day-card');
                    const card = cards[dayIdx];
                    if (card) {
                        const statusEl = card.querySelector('.weekly-day-status');
                        if (statusEl) statusEl.textContent = hasData ? 'Logged' : (isoDate > new Date().toISOString().slice(0,10) ? 'Upcoming' : 'No log');
                        const dotRow = card.querySelector('.weekly-dot-row');
                        if (dotRow && hasData) {
                            dotRow.innerHTML = '<span class="w-2 h-2 rounded-full bg-sage-500 inline-block"></span>';
                        }
                        if (hasData) {
                            card.querySelector('.weekly-day-icon')?.classList.add('bg-sage-500','text-white');
                            card.querySelector('.weekly-day-icon')?.classList.remove('bg-sage-50','text-sage-400');
                        }
                    }
                });
            }
        } catch (e) { /* non-fatal */ }

        // --- Milestones ---
        const msContainer = document.getElementById('weeklyMilestones');
        if (data.milestones && data.milestones.length > 0) {
            msContainer.innerHTML = data.milestones.map(m => {
                const label = MILESTONE_LABELS[m.milestone] || m.milestone;
                return `<span class="inline-block px-4 py-1.5 bg-white rounded-full text-[10px] font-bold uppercase tracking-widest text-sage-600 border border-sage-50 mr-1 mb-1">${label}</span>`;
            }).join('');
        } else {
            msContainer.innerHTML = '<p class="text-sage-400 text-xs">No new badges this week — keep logging!</p>';
        }

        // --- Tracker averages ---
        const avgsContainer = document.getElementById('weeklyTrackerAvgs');
        const summary = data.tracker_summary || {};
        const keys = Object.keys(summary);
        if (keys.length > 0) {
            avgsContainer.innerHTML = keys.map(key => {
                const { avg, days_logged } = summary[key];
                const label = key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                const pctLogged = Math.round((days_logged / 7) * 100);
                return `<div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-sage-600">${label}</p>
                        <p class="text-[10px] text-sage-300 uppercase tracking-widest">${days_logged}/7 days</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-serif text-sage-600">${avg}</p>
                        <div class="w-16 h-1.5 bg-sage-50 rounded-full mt-1 overflow-hidden">
                            <div class="h-full bg-sage-500 rounded-full" style="width:${pctLogged}%"></div>
                        </div>
                    </div>
                </div>`;
            }).join('');
        } else {
            avgsContainer.innerHTML = '<p class="text-sage-400 text-xs">No tracker data this week yet.</p>';
        }

        // Re-init Lucide icons in case new ones were rendered
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // Trigger load when weeklyView becomes active via switchView
    const target = document.getElementById('weeklyView');
    if (target) {
        if (target.classList.contains('active')) {
            loadWeeklyReview();
        } else {
            const obs = new MutationObserver(() => {
                if (target.classList.contains('active')) {
                    loadWeeklyReview();
                    obs.disconnect();
                }
            });
            obs.observe(target, { attributes: true, attributeFilter: ['class'] });
        }
    }
})();
</script>
