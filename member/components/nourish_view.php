<?php
/**
 * 1wellness Member Nourish View Component
 */
?>
<div id="nourishView" class="view-section hidden space-y-12">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 pb-8 border-b border-sage-100">
        <div class="space-y-4 max-w-2xl">
            <h2 class="text-4xl font-serif text-sage-600">Nourish Protocol</h2>
            <p class="text-sage-400 text-sm leading-relaxed">
                Your personalized nutrition plan centers on stabilizing insulin and reducing systemic inflammation. 
                Focus on high-quality proteins and low-glycemic carbohydrates today.
            </p>
        </div>
        <div class="flex gap-4">
            <button class="px-6 py-3 bg-white border border-sage-100 rounded-2xl text-xs font-bold text-sage-400 uppercase tracking-widest flex items-center gap-2 hover:bg-sage-50 transition-all">
                <i data-lucide="download" class="w-4 h-4"></i>
                Download PDF
            </button>
            <button class="px-6 py-3 bg-sage-500 text-white rounded-2xl text-xs font-bold uppercase tracking-widest shadow-xl shadow-sage-500/20 hover:scale-[1.02] transition-all">
                Change Preferences
            </button>
        </div>
    </div>

    <!-- Meal Schedule -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
        <!-- Breakfast Card -->
        <div class="bg-white p-8 rounded-[3rem] shadow-sm border border-sage-50 space-y-6 group hover:shadow-2xl transition-all duration-500">
            <div class="relative overflow-hidden rounded-[2.5rem] aspect-square mb-6">
                <img id="nourishBfImg" src="https://images.unsplash.com/photo-1494390248081-4e521a5940db?auto=format&fit=crop&q=80" alt="Breakfast" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-sage-900/40 via-transparent to-transparent"></div>
                <div class="absolute bottom-6 left-6 flex items-center gap-3">
                    <span class="px-4 py-2 bg-white/90 backdrop-blur-md rounded-full text-[10px] font-bold uppercase tracking-widest text-sage-600">7:30 AM</span>
                </div>
            </div>
            <div class="space-y-3">
                <h3 id="nourishBfTitle" class="text-2xl font-serif text-sage-600 group-hover:text-sage-500 transition-colors">Berry Smoothie Bowl</h3>
                <p id="nourishBfDesc" class="text-sage-400 text-sm leading-relaxed">Antioxidant-rich berries with flax seeds for hormonal balance.</p>
                <div class="flex items-center gap-4 pt-4">
                    <div class="flex -space-x-2">
                        <div class="w-8 h-8 rounded-full border-2 border-white bg-sage-50 text-sage-300 flex items-center justify-center text-[10px] font-bold">FR</div>
                        <div class="w-8 h-8 rounded-full border-2 border-white bg-coral-50 text-coral-400 flex items-center justify-center text-[10px] font-bold">PR</div>
                        <div class="w-8 h-8 rounded-full border-2 border-white bg-blue-50 text-blue-500 flex items-center justify-center text-[10px] font-bold">HF</div>
                    </div>
                    <button onclick="viewRecipe('breakfast')" class="ml-auto text-[10px] font-bold text-sage-400 uppercase tracking-widest hover:text-sage-500 transition-colors flex items-center gap-1">
                        View Details <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Lunch Card -->
        <div class="bg-white p-8 rounded-[3rem] shadow-sm border border-sage-50 space-y-6 group hover:shadow-2xl transition-all duration-500">
            <div class="relative overflow-hidden rounded-[2.5rem] aspect-square mb-6">
                <img id="nourishLhImg" src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&q=80" alt="Lunch" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-sage-900/40 via-transparent to-transparent"></div>
                <div class="absolute bottom-6 left-6 flex items-center gap-3">
                    <span class="px-4 py-2 bg-white/90 backdrop-blur-md rounded-full text-[10px] font-bold uppercase tracking-widest text-sage-600">1:00 PM</span>
                </div>
            </div>
            <div class="space-y-3">
                <h3 id="nourishLhTitle" class="text-2xl font-serif text-sage-600 group-hover:text-sage-500 transition-colors">Salmon Quinoa Bowl</h3>
                <p id="nourishLhDesc" class="text-sage-400 text-sm leading-relaxed">Omega-3 rich healthy fats paired with complex fiber-rich grains.</p>
                <div class="flex items-center gap-4 pt-4">
                    <div class="flex -space-x-2">
                        <div class="w-8 h-8 rounded-full border-2 border-white bg-sage-50 text-sage-300 flex items-center justify-center text-[10px] font-bold">O3</div>
                        <div class="w-8 h-8 rounded-full border-2 border-white bg-coral-50 text-coral-400 flex items-center justify-center text-[10px] font-bold">PR</div>
                        <div class="w-8 h-8 rounded-full border-2 border-white bg-blue-50 text-blue-500 flex items-center justify-center text-[10px] font-bold">FI</div>
                    </div>
                    <button onclick="viewRecipe('lunch')" class="ml-auto text-[10px] font-bold text-sage-400 uppercase tracking-widest hover:text-sage-500 transition-colors flex items-center gap-1">
                        View Details <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Dinner Card -->
        <div class="bg-white p-8 rounded-[3rem] shadow-sm border border-sage-50 space-y-6 group hover:shadow-2xl transition-all duration-500">
            <div class="relative overflow-hidden rounded-[2.5rem] aspect-square mb-6">
                <img id="nourishDnImg" src="https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&q=80" alt="Dinner" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-sage-900/40 via-transparent to-transparent"></div>
                <div class="absolute bottom-6 left-6 flex items-center gap-3">
                    <span class="px-4 py-2 bg-white/90 backdrop-blur-md rounded-full text-[10px] font-bold uppercase tracking-widest text-sage-600">7:00 PM</span>
                </div>
            </div>
            <div class="space-y-3">
                <h3 id="nourishDnTitle" class="text-2xl font-serif text-sage-600 group-hover:text-sage-500 transition-colors">Stuffed Sweet Potato</h3>
                <p id="nourishDnDesc" class="text-sage-400 text-sm leading-relaxed">Light protein combined with vitamin-rich sweet potatoes.</p>
                <div class="flex items-center gap-4 pt-4">
                    <div class="flex -space-x-2">
                        <div class="w-8 h-8 rounded-full border-2 border-white bg-sage-50 text-sage-300 flex items-center justify-center text-[10px] font-bold">VT</div>
                        <div class="w-8 h-8 rounded-full border-2 border-white bg-coral-50 text-coral-400 flex items-center justify-center text-[10px] font-bold">PR</div>
                    </div>
                    <button onclick="viewRecipe('dinner')" class="ml-auto text-[10px] font-bold text-sage-400 uppercase tracking-widest hover:text-sage-500 transition-colors flex items-center gap-1">
                        View Details <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Nutrition Tools -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 pt-12">
        <!-- Shopping List -->
        <div class="bg-sage-50/50 p-10 rounded-[3rem] border border-sage-100">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-sage-500 shadow-sm">
                        <i data-lucide="shopping-basket" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h4 class="text-2xl font-serif text-sage-600">Shopping List</h4>
                        <p class="text-[10px] font-bold text-sage-300 uppercase tracking-widest">Protocol Essentials</p>
                    </div>
                </div>
                <button class="text-sage-400 hover:text-sage-600">
                    <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                </button>
            </div>
            <div id="shoppingListContainer" class="space-y-4">
                <!-- Shopping list items injected via JS -->
                <div class="flex items-center gap-4 p-4 bg-white/60 rounded-2xl border border-white/40 group hover:border-sage-200 transition-all">
                    <div class="w-6 h-6 rounded-lg border-2 border-sage-100 flex items-center justify-center group-hover:border-sage-300 cursor-pointer">
                        <i data-lucide="check" class="w-4 h-4 text-sage-200 opacity-0 group-hover:opacity-100"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-sage-600">Avocado</p>
                        <p class="text-[10px] text-sage-400 uppercase tracking-widest font-medium">Qty: 3 Units • Produce</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Herbal Protocol -->
        <div class="bg-coral-500/5 p-10 rounded-[3rem] border border-coral-500/10">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 rounded-2xl bg-coral-500 flex items-center justify-center text-white shadow-lg shadow-coral-500/20">
                    <i data-lucide="sparkles" class="w-6 h-6"></i>
                </div>
                <div>
                    <h4 class="text-2xl font-serif text-sage-600">Herbal Stack</h4>
                    <p class="text-[10px] font-bold text-coral-400 uppercase tracking-widest">Daily Supplements</p>
                </div>
            </div>
            <div class="space-y-6">
                <div class="flex items-start gap-6 p-6 bg-white/40 rounded-3xl border border-white relative overflow-hidden group hover:bg-white/60 transition-all">
                    <div class="p-3 bg-white rounded-2xl flex items-center justify-center text-coral-400 shadow-sm border border-coral-500/5 transition-transform group-hover:scale-110">
                        <i data-lucide="milk" class="w-6 h-6"></i>
                    </div>
                    <div class="space-y-2">
                        <h5 class="text-lg font-serif text-sage-600">Spearmint Tea</h5>
                        <p class="text-sage-400 text-xs leading-relaxed">2 cups daily, morning & evening. Known to help maintain healthy androgen levels.</p>
                        <div class="flex items-center gap-4 pt-2">
                            <span class="flex items-center gap-1.5 text-[9px] font-bold uppercase tracking-widest text-coral-400">
                                <i data-lucide="clock" class="w-3 h-3"></i> Morning
                            </span>
                            <span class="flex items-center gap-1.5 text-[9px] font-bold uppercase tracking-widest text-coral-400">
                                <i data-lucide="clock" class="w-3 h-3"></i> Night
                            </span>
                        </div>
                    </div>
                </div>
                <!-- More items can be added here -->
            </div>
        </div>
    </div>
</div>
