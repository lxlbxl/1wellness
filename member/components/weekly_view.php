<?php
/**
 * 1wellness Member Weekly View Component
 */
?>
<div id="weeklyView" class="view-section hidden space-y-12">
    <div class="flex items-center justify-between pb-8 border-b border-sage-100">
        <div>
            <h2 class="text-4xl font-serif text-sage-600">Weekly Protocol</h2>
            <p class="text-sage-400 text-sm mt-2">Week of <?php echo date('M jS', strtotime('monday this week')); ?> - <?php echo date('M jS', strtotime('sunday this week')); ?></p>
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

    <!-- Weekly Carousel -->
    <div id="weeklySchedule" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-6">
        <!-- Week days will be injected via JS here -->
        <?php 
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach($days as $day): 
        ?>
        <div class="bg-white p-6 rounded-[2.5rem] border border-sage-50 shadow-sm hover:shadow-xl transition-all group cursor-pointer">
            <p class="text-[10px] font-bold text-sage-300 uppercase tracking-widest mb-4"><?php echo $day; ?></p>
            <div class="w-10 h-10 rounded-2xl bg-sage-50 flex items-center justify-center text-sage-400 mb-6 group-hover:bg-sage-500 group-hover:text-white transition-all">
                <i data-lucide="utensils" class="w-4 h-4"></i>
            </div>
            <h4 class="text-lg font-serif text-sage-600 mb-2">Protocol Day</h4>
            <p class="text-sage-400 text-[10px] leading-relaxed">Antioxidant-focused nutrition and moderate movement.</p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Weekly Performance -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 pt-12">
        <div class="lg:col-span-2 bg-sage-900 rounded-[3rem] p-10 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 p-10 opacity-10">
                <i data-lucide="sparkles" class="w-32 h-32"></i>
            </div>
            <div class="relative z-10">
                <h3 class="text-3xl font-serif mb-8">Weekly Performance</h3>
                <div class="grid grid-cols-7 gap-4 items-end h-64">
                    <?php 
                    $heights = [45, 80, 65, 90, 70, 85, 40];
                    foreach($heights as $height): 
                    ?>
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-full bg-white/10 rounded-2xl relative group overflow-hidden" style="height: <?php echo $height; ?>%">
                            <div class="absolute inset-0 bg-coral-400 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        </div>
                        <span class="text-[8px] font-bold text-white/40 uppercase tracking-widest"><?php echo substr($days[array_search($height, $heights)], 0, 3); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="bg-cream-100 p-10 rounded-[3rem] border border-white flex flex-col justify-between">
            <div class="space-y-6">
                <div class="w-16 h-16 rounded-3xl bg-white flex items-center justify-center text-coral-400 shadow-sm">
                    <i data-lucide="award" class="w-8 h-8"></i>
                </div>
                <h3 class="text-2xl font-serif text-sage-600">Weekly Achievement</h3>
                <p class="text-sage-400 text-sm leading-relaxed">
                    You've maintained consistent hydration for 5 days straight. Your endocrine system thanks you!
                </p>
                <div class="pt-6">
                    <span class="px-6 py-2 bg-white rounded-full text-[10px] font-bold uppercase tracking-widest text-sage-600 border border-sage-50">Hydration Hero</span>
                </div>
            </div>
            <button class="w-full py-4 bg-sage-500 text-white rounded-2xl font-bold text-[10px] uppercase tracking-widest shadow-xl shadow-sage-500/20 hover:scale-[1.02] transition-all">
                Share Progress
            </button>
        </div>
    </div>
</div>
