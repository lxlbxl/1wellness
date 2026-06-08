<?php
/**
 * 1wellness Member Header Component
 */
?>
<header class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 mb-12">
    <div class="space-y-2">
        <div class="flex items-center gap-3 text-sage-300">
            <i data-lucide="calendar-days" class="w-4 h-4"></i>
            <span id="currentDate" class="text-[10px] font-bold uppercase tracking-[0.2em]"><?php echo date('l, F j, Y'); ?></span>
        </div>
        <h2 class="text-4xl font-serif text-sage-600 leading-tight">
            Welcome back, <span id="userName" class="text-sage-500">Member</span>
        </h2>
        <p class="text-sage-400 text-sm max-w-lg leading-relaxed">
            Your body is in its <span id="userPhase" class="font-bold text-sage-500">...</span> phase today. 
            Focus on <span id="phaseNurture" class="text-sage-400">gentle movement and warming foods</span>.
        </p>
    </div>
    <div class="flex items-center gap-4 lg:self-start">
        <button onclick="toggleNotifications()" class="w-12 h-12 rounded-2xl bg-white border border-sage-100 flex items-center justify-center text-sage-400 hover:bg-sage-50 transition-all relative">
            <i data-lucide="bell" class="w-5 h-5"></i>
            <span class="absolute top-3 right-3 w-2 h-2 bg-coral-400 rounded-full border-2 border-white"></span>
        </button>
        <div class="h-12 px-4 bg-white border border-sage-100 rounded-2xl flex items-center gap-3">
            <div id="userInitials" class="w-8 h-8 rounded-xl bg-sage-500 flex items-center justify-center text-[10px] font-bold text-white uppercase tracking-widest shadow-sm">
                ME
            </div>
            <div class="hidden sm:block text-left">
                <p id="userFullName" class="text-xs font-bold text-sage-600 leading-none">Member Name</p>
                <p id="userRole" class="text-[9px] font-medium text-sage-300 uppercase tracking-widest mt-1">Premium Plan</p>
            </div>
        </div>
    </div>
</header>
