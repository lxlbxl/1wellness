<?php
/**
 * 1wellness Member Dashboard View Component
 */
?>
<div id="dashboardView" class="view-section space-y-12">
    <!-- Top Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Primary metric card (condition-specific) -->
        <?php
        $dashIcon  = htmlspecialchars($conditionCfg['icon'] ?? 'activity');
        $dashLabel = htmlspecialchars($conditionCfg['dashboard_label'] ?? 'Today');
        $dashTerm  = $conditionCfg['terminology'] ?? [];
        ?>
        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-sage-50 relative overflow-hidden group hover:shadow-xl transition-all duration-500">
            <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:scale-110 transition-transform duration-500">
                <i data-lucide="<?php echo $dashIcon; ?>" class="w-16 h-16"></i>
            </div>
            <div class="relative z-10 flex flex-col h-full">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-2xl bg-sage-50 text-sage-500 flex items-center justify-center">
                        <i data-lucide="calendar" class="w-5 h-5"></i>
                    </div>
                    <p class="text-[10px] font-bold text-sage-300 uppercase tracking-widest"><?php echo $dashLabel; ?></p>
                </div>
                <h3 id="statPrimaryValue" class="text-3xl font-serif text-sage-600 mb-2">—</h3>
                <p id="statPrimarySubtext" class="text-sage-400 text-xs font-medium">&nbsp;</p>
                <div class="mt-8 flex items-end gap-2">
                    <div class="flex-1 h-1.5 bg-sage-50 rounded-full overflow-hidden">
                        <div id="primaryProgress" class="h-full bg-sage-500 rounded-full transition-all duration-1000" style="width: 0%"></div>
                    </div>
                    <span id="primaryPercent" class="text-[10px] font-bold text-sage-300">0%</span>
                </div>
            </div>
        </div>

        <!-- Hydration -->
        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-sage-50 relative overflow-hidden group hover:shadow-xl transition-all duration-500">
            <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:scale-110 transition-transform duration-500">
                <i data-lucide="droplet" class="w-16 h-16"></i>
            </div>
            <div class="relative z-10 flex flex-col h-full">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-500 flex items-center justify-center">
                        <i data-lucide="droabets" class="w-5 h-5"></i>
                    </div>
                    <p class="text-[10px] font-bold text-sage-300 uppercase tracking-widest">Hydration</p>
                </div>
                <div class="flex items-baseline gap-2 mb-2">
                    <h3 id="statHydration" class="text-3xl font-serif text-sage-600">1.2</h3>
                    <span class="text-sage-400 text-xs font-medium lowercase">Liters</span>
                </div>
                <p class="text-sage-400 text-xs font-medium">Goal: 2.5L</p>
                <div class="mt-8 flex gap-2 overflow-hidden justify-between">
                    <?php for($i=1; $i<=8; $i++): ?>
                    <button onclick="logHydration(<?php echo $i; ?>)" class="w-6 h-6 rounded-lg bg-blue-50 border border-blue-100 hover:bg-blue-100 transition-colors flex items-center justify-center group/glass">
                        <i data-lucide="glass-water" class="w-3 h-3 text-blue-200 group-hover/glass:text-blue-500"></i>
                    </button>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Fruit Intake -->
        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-sage-50 relative overflow-hidden group hover:shadow-xl transition-all duration-500">
            <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:scale-110 transition-transform duration-500">
                <i data-lucide="apple" class="w-16 h-16"></i>
            </div>
            <div class="relative z-10 flex flex-col h-full">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-2xl bg-coral-50 text-coral-400 flex items-center justify-center">
                        <i data-lucide="grapes" class="w-5 h-5"></i>
                    </div>
                    <p class="text-[10px] font-bold text-sage-300 uppercase tracking-widest">Fruit Intake</p>
                </div>
                <div class="flex items-baseline gap-2 mb-2">
                    <h3 id="statFruit" class="text-3xl font-serif text-sage-600">2</h3>
                    <span class="text-sage-400 text-xs font-medium lowercase">Servings</span>
                </div>
                <p class="text-sage-400 text-xs font-medium">Goal: 3 / day</p>
                <div class="mt-8 flex gap-2">
                    <?php for($i=1; $i<=3; $i++): ?>
                    <button onclick="logFruit(<?php echo $i; ?>)" class="w-8 h-8 rounded-xl bg-coral-50 border border-coral-100 hover:bg-coral-100 transition-colors flex items-center justify-center">
                        <i data-lucide="plus" class="w-4 h-4 text-coral-300"></i>
                    </button>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Movement -->
        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-sage-50 relative overflow-hidden group hover:shadow-xl transition-all duration-500">
            <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:scale-110 transition-transform duration-500">
                <i data-lucide="bike" class="w-16 h-16"></i>
            </div>
            <div class="relative z-10 flex flex-col h-full">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-2xl bg-sage-50 text-sage-500 flex items-center justify-center">
                        <i data-lucide="footprints" class="w-5 h-5"></i>
                    </div>
                    <p class="text-[10px] font-bold text-sage-300 uppercase tracking-widest">Movement</p>
                </div>
                <div class="flex items-baseline gap-2 mb-2">
                    <h3 id="statMovement" class="text-3xl font-serif text-sage-600">Active</h3>
                </div>
                <p id="statMovementDesc" class="text-sage-400 text-xs font-medium">Yoga Session • 30m</p>
                <button onclick="logMovement()" class="mt-8 py-2 px-6 rounded-xl bg-sage-500 text-white text-[10px] font-bold uppercase tracking-widest hover:shadow-lg transition-all self-start">
                    Log Activity
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
        <!-- Center Pillar: Meal Focus -->
        <div class="lg:col-span-2 space-y-12">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-1 h-8 bg-sage-500 rounded-full"></div>
                    <h3 class="text-2xl font-serif text-sage-600">Today's Protocol</h3>
                </div>
                <div class="flex gap-2">
                    <button class="w-10 h-10 rounded-full bg-white border border-sage-100 flex items-center justify-center text-sage-400">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </button>
                    <button class="w-10 h-10 rounded-full bg-white border border-sage-100 flex items-center justify-center text-sage-400">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <div id="mealSection" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Breakfast Card -->
                <div class="bg-white p-1 rounded-[3rem] shadow-sm hover:shadow-2xl transition-all duration-500 group border border-sage-50">
                    <div class="relative overflow-hidden rounded-[2.5rem] aspect-[4/3] mb-6">
                        <div class="absolute inset-0 bg-sage-900/20 group-hover:bg-sage-900/0 transition-all duration-500 z-10"></div>
                        <img id="mealBfImg" src="https://images.unsplash.com/photo-1494390248081-4e521a5940db?auto=format&fit=crop&q=80" alt="Breakfast" class="w-full h-full object-cover transform group-hover:scale-105 transition-all duration-700">
                        <div class="absolute top-6 left-6 z-20">
                            <span class="px-4 py-2 bg-white/90 backdrop-blur-md rounded-full text-[10px] font-bold uppercase tracking-widest text-sage-600 shadow-sm">Breakfast</span>
                        </div>
                    </div>
                    <div class="p-8 pt-0 space-y-4">
                        <h4 id="mealBfTitle" class="text-2xl font-serif text-sage-600 group-hover:text-sage-500 transition-colors">Berry & Protein Smoothie</h4>
                        <p id="mealBfDesc" class="text-sage-400 text-sm leading-relaxed line-clamp-2">Loaded with antioxidants and hormone-balancing proteins for a perfect start.</p>
                        <div class="flex items-center gap-3 pt-4">
                            <button onclick="viewRecipe('breakfast')" class="flex-1 py-3 bg-sage-50 group-hover:bg-sage-500 text-sage-500 group-hover:text-white rounded-2xl text-[10px] uppercase font-bold tracking-widest transition-all">
                                View Recipe
                            </button>
                            <label class="w-12 h-12 rounded-2xl bg-white border border-sage-100 flex items-center justify-center cursor-pointer hover:bg-sage-50 transition-all">
                                <input type="checkbox" id="checkBf" onchange="toggleCheck('breakfast')" class="hidden">
                                <i data-lucide="check" class="w-5 h-5 text-sage-200 transition-colors check-icon"></i>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Lunch Card -->
                <div class="bg-white p-1 rounded-[3rem] shadow-sm hover:shadow-2xl transition-all duration-500 group border border-sage-50">
                    <div class="relative overflow-hidden rounded-[2.5rem] aspect-[4/3] mb-6">
                        <div class="absolute inset-0 bg-sage-900/20 group-hover:bg-sage-900/0 transition-all duration-500 z-10"></div>
                        <img id="mealLhImg" src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&q=80" alt="Lunch" class="w-full h-full object-cover transform group-hover:scale-105 transition-all duration-700">
                        <div class="absolute top-6 left-6 z-20">
                            <span class="px-4 py-2 bg-white/90 backdrop-blur-md rounded-full text-[10px] font-bold uppercase tracking-widest text-sage-600 shadow-sm">Lunch</span>
                        </div>
                    </div>
                    <div class="p-8 pt-0 space-y-4">
                        <h4 id="mealLhTitle" class="text-2xl font-serif text-sage-600 group-hover:text-sage-500 transition-colors">Quinoa Nourish Bowl</h4>
                        <p id="mealLhDesc" class="text-sage-400 text-sm leading-relaxed line-clamp-2">High-fiber grains with colorful vegetables and healthy fats.</p>
                        <div class="flex items-center gap-3 pt-4">
                            <button onclick="viewRecipe('lunch')" class="flex-1 py-3 bg-sage-50 group-hover:bg-sage-500 text-sage-500 group-hover:text-white rounded-2xl text-[10px] uppercase font-bold tracking-widest transition-all">
                                View Recipe
                            </button>
                            <label class="w-12 h-12 rounded-2xl bg-white border border-sage-100 flex items-center justify-center cursor-pointer hover:bg-sage-50 transition-all">
                                <input type="checkbox" id="checkLh" onchange="toggleCheck('lunch')" class="hidden">
                                <i data-lucide="check" class="w-5 h-5 text-sage-200 transition-colors check-icon"></i>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Pillar: Insights & Community -->
        <div class="space-y-12">
            <div class="flex items-center gap-4">
                <div class="w-1 h-8 bg-sage-500 rounded-full"></div>
                <h3 class="text-2xl font-serif text-sage-600">Herbal Insights</h3>
            </div>

            <div class="space-y-6">
                <!-- Insight 1 -->
                <div class="bg-cream-100/50 p-8 rounded-[2.5rem] border border-white relative overflow-hidden group">
                    <div class="absolute top-0 right-0 p-8 opacity-10 transform scale-150 rotate-12 transition-transform group-hover:scale-[1.7]">
                        <i data-lucide="sprout" class="w-12 h-12 text-sage-500"></i>
                    </div>
                    <div class="relative z-10">
                        <span class="text-[9px] font-bold text-sage-400 uppercase tracking-widest mb-4 block">Herbal Profile</span>
                        <h4 class="text-xl font-serif text-sage-600 mb-3">Spearmint Power</h4>
                        <p class="text-sage-400 text-xs leading-relaxed mb-6">Traditional support for healthy androgen levels. 2 cups daily recommended.</p>
                        <button class="flex items-center gap-2 text-sage-500 font-bold text-[10px] uppercase tracking-widest group/btn">
                            Learn More
                            <i data-lucide="arrow-right" class="w-4 h-4 transform group-hover/btn:translate-x-1 transition-transform"></i>
                        </button>
                    </div>
                </div>

                <!-- Community Plug -->
                <div class="bg-sage-900 p-8 rounded-[2.5rem] text-white shadow-2xl shadow-sage-900/20 relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent"></div>
                    <div class="relative z-10 text-center">
                        <div class="w-20 h-20 bg-white/10 rounded-full mx-auto mb-6 flex items-center justify-center backdrop-blur-md">
                            <i data-lucide="users" class="w-8 h-8"></i>
                        </div>
                        <h4 class="text-2xl font-serif mb-3">Join the Collective</h4>
                        <p class="text-white/60 text-xs leading-relaxed mb-8">Connect with 2,400+ women on the same journey.</p>
                        <button onclick="window.open('https://chat.whatsapp.com/G5gM1zYJImH3E7mZIBO9C1', '_blank')" 
                                class="w-full py-4 bg-white text-sage-900 rounded-2xl font-bold text-[10px] uppercase tracking-widest hover:scale-[1.02] transition-all">
                            Access Community
                        </button>
                    </div>
                </div>

                <!-- Progress Circle -->
                <div class="bg-white p-8 rounded-[2.5rem] border border-sage-50 flex flex-col items-center text-center">
                    <p class="text-[10px] font-bold text-sage-300 uppercase tracking-widest mb-6">Weekly Goal Progress</p>
                    <div class="relative w-32 h-32 mb-6">
                        <svg class="w-full h-full transform -rotate-90">
                            <circle cx="64" cy="64" r="58" stroke="currentColor" stroke-width="8" fill="transparent" class="text-sage-50"></circle>
                            <circle cx="64" cy="64" r="58" stroke="currentColor" stroke-width="8" fill="transparent" 
                                    stroke-dasharray="364.4" stroke-dashoffset="109.3"
                                    class="text-coral-400 transition-all duration-1000"></circle>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-serif text-sage-600">70%</span>
                            <span class="text-[8px] font-bold text-sage-300 uppercase tracking-widest">Complete</span>
                        </div>
                    </div>
                    <p class="text-sage-400 text-xs leading-tight">Excellent work this week! You've hit 5 out of 7 daily protocols.</p>
                </div>
            </div>
        </div>
    </div>
</div>
