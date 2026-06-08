<?php
/**
 * 1wellness Member Vitals Sync View Component
 */
?>
<div id="trackerView" class="view-section hidden space-y-12">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 pb-8 border-b border-sage-100">
        <div class="space-y-4 max-w-2xl">
            <h2 class="text-4xl font-serif text-sage-600">Vitals Sync</h2>
            <p class="text-sage-400 text-sm leading-relaxed">
                Connect your body's biometric data to your PCOS protocol for a truly tailored experience.
            </p>
        </div>
        <div class="flex gap-4">
            <button class="px-6 py-3 bg-sage-500 text-white rounded-2xl text-xs font-bold uppercase tracking-widest shadow-xl shadow-sage-500/20 hover:scale-[1.02] transition-all">
                Manual Entry
            </button>
        </div>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
        <!-- Weight Metric -->
        <div class="bg-white p-8 rounded-[3rem] border border-sage-50 shadow-sm relative overflow-hidden group hover:shadow-2xl transition-all duration-500">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 rounded-2xl bg-sage-50 text-sage-500 flex items-center justify-center transition-colors group-hover:bg-sage-500 group-hover:text-white">
                    <i data-lucide="scale" class="w-6 h-6"></i>
                </div>
                <div>
                    <h4 class="text-xl font-serif text-sage-600">Current Weight</h4>
                    <p class="text-[9px] font-bold text-sage-300 uppercase tracking-widest">Last Synced: Today</p>
                </div>
            </div>
            <div class="flex items-baseline gap-2 mb-2">
                <h3 id="vitalsWeight" class="text-4xl font-serif text-sage-600">--.-</h3>
                <span class="text-sage-300 text-[10px] font-bold uppercase tracking-widest">KG</span>
            </div>
            <p class="text-emerald-500 text-xs font-medium flex items-center gap-1">
                <i data-lucide="trending-down" class="w-4 h-4"></i> -0.4kg from last week
            </p>
            <div class="mt-8 h-24 flex items-end gap-1">
                <?php for($i=1; $i<=14; $i++): ?>
                <div class="flex-1 bg-sage-50 rounded-full hover:bg-sage-100 transition-all cursor-help" style="height: <?php echo rand(40, 100); ?>%"></div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- BMI Metric -->
        <div class="bg-white p-8 rounded-[3rem] border border-sage-50 shadow-sm relative overflow-hidden group hover:shadow-2xl transition-all duration-500">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 rounded-2xl bg-coral-50 text-coral-400 flex items-center justify-center transition-colors group-hover:bg-coral-400 group-hover:text-white">
                    <i data-lucide="activity" class="w-6 h-6"></i>
                </div>
                <div>
                    <h4 class="text-xl font-serif text-sage-600">BMI Index</h4>
                    <p class="text-[9px] font-bold text-sage-300 uppercase tracking-widest">Metabolic Profile</p>
                </div>
            </div>
            <div class="flex items-baseline gap-2 mb-2">
                <h3 id="vitalsBmi" class="text-4xl font-serif text-sage-600">--.-</h3>
                <span class="text-sage-300 text-[10px] font-bold uppercase tracking-widest">Score</span>
            </div>
            <p id="bmiStatus" class="text-sage-400 text-xs font-medium uppercase tracking-[0.15em]">Healthy Range</p>
            <div class="mt-8 pt-8 border-t border-sage-50">
                <div class="h-2 bg-sage-50 rounded-full relative">
                    <div id="bmiMarker" class="absolute w-4 h-4 bg-coral-400 border-2 border-white rounded-full shadow-md -top-1 transition-all duration-1000" style="left: 45%"></div>
                </div>
                <div class="flex justify-between text-[8px] font-bold text-sage-300 uppercase tracking-widest mt-3">
                    <span>18.5</span>
                    <span>24.9</span>
                    <span>29.9</span>
                </div>
            </div>
        </div>

        <!-- Sleep Metric -->
        <div class="bg-white p-8 rounded-[3rem] border border-sage-50 shadow-sm relative overflow-hidden group hover:shadow-2xl transition-all duration-500">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center transition-colors group-hover:bg-indigo-500 group-hover:text-white">
                    <i data-lucide="moon" class="w-6 h-6"></i>
                </div>
                <div>
                    <h4 class="text-xl font-serif text-sage-600">Sleep Quality</h4>
                    <p class="text-[9px] font-bold text-sage-300 uppercase tracking-widest">Circadian Rhythm</p>
                </div>
            </div>
            <div class="flex items-baseline gap-2 mb-2">
                <h3 class="text-4xl font-serif text-sage-600">7h 45m</h3>
            </div>
            <p class="text-indigo-400 text-xs font-medium uppercase tracking-widest mt-2 overflow-hidden whitespace-nowrap">84% Quality Score • Rested</p>
            <div class="mt-8 flex gap-2 justify-between">
                <?php for($i=1; $i<=7; $i++): ?>
                <div class="w-2.5 h-12 bg-indigo-50 rounded-full relative group/sleep">
                    <div class="absolute bottom-0 left-0 right-0 bg-indigo-400 rounded-full group-hover/sleep:bg-indigo-600 transition-all" style="height: <?php echo rand(50, 100); ?>%"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Integration Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 pt-12 items-center">
        <div class="p-10 bg-sage-900 rounded-[3rem] text-white space-y-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-10 opacity-10 transform scale-150">
                <i data-lucide="link" class="w-32 h-32 text-white"></i>
            </div>
            <div class="relative z-10">
                <h3 class="text-3xl font-serif mb-4">Device Integration</h3>
                <p class="text-white/60 text-sm leading-relaxed max-w-md mb-10">
                    Sync your favorite wearables to automatically track movement, hydration, and sleep patterns.
                </p>
                <div class="space-y-4">
                    <div class="flex items-center gap-4 p-4 bg-white/10 rounded-2xl border border-white/10 group cursor-not-allowed grayscale">
                        <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center text-white/50">
                            <i data-lucide="smartphone" class="w-6 h-6"></i>
                        </div>
                        <div class="flex-1">
                            <h5 class="text-sm font-bold">Apple Health</h5>
                            <p class="text-[9px] uppercase tracking-widest text-white/40">Coming Soon</p>
                        </div>
                        <button class="px-4 py-2 bg-white/10 rounded-xl text-[10px] font-bold uppercase tracking-widest">Connect</button>
                    </div>
                    <div class="flex items-center gap-4 p-4 bg-white/10 rounded-2xl border border-white/10 group cursor-not-allowed grayscale">
                        <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center text-white/50">
                            <i data-lucide="gauge" class="w-6 h-6"></i>
                        </div>
                        <div class="flex-1">
                            <h5 class="text-sm font-bold">Google Fit</h5>
                            <p class="text-[9px] uppercase tracking-widest text-white/40">Coming Soon</p>
                        </div>
                        <button class="px-4 py-2 bg-white/10 rounded-xl text-[10px] font-bold uppercase tracking-widest">Connect</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="p-8 bg-cream-100 rounded-[2.5rem] border border-white relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-8 opacity-10 transform rotate-12 transition-transform group-hover:scale-110">
                    <i data-lucide="info" class="w-12 h-12 text-sage-500"></i>
                </div>
                <h4 class="text-xl font-serif text-sage-600 mb-4">Why track vitals?</h4>
                <p class="text-sage-400 text-xs leading-relaxed max-w-sm">
                    Biometric data helps us adjust your protocol based on actual physiological responses. Tracking your weight and BMI provides insights into metabolic health markers relevant to PCOS management.
                </p>
            </div>
        </div>
    </div>
</div>
