<?php
/**
 * 1wellness Member Sidebar Component
 */
?>
<!-- Desktop Sidebar -->
<aside class="hidden lg:flex fixed left-0 top-0 bottom-0 w-80 bg-white border-r border-sage-100 flex-col z-50">
    <div class="p-8 border-b border-sage-50 bg-sage-50/10">
        <div class="flex items-center gap-4">
            <div
                class="w-12 h-12 rounded-2xl bg-sage-500 flex items-center justify-center text-white shadow-lg shadow-sage-500/20">
                <i data-lucide="leaf" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="font-serif text-xl text-sage-600 leading-tight">1wellness</h1>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-sage-300">PCOS Protocol</p>
            </div>
        </div>
    </div>

    <nav class="flex-1 p-6 space-y-2 overflow-y-auto custom-scrollbar">
        <p class="text-[10px] font-bold text-sage-300 uppercase tracking-widest px-4 mb-4">Core Protocol</p>

        <button onclick="switchView('dashboard')" data-view="dashboard"
            class="nav-link w-full flex items-center gap-4 p-4 rounded-2xl text-sage-400 hover:bg-sage-50 transition-all group active">
            <div
                class="w-10 h-10 rounded-xl bg-sage-50 group-hover:bg-white flex items-center justify-center transition-colors">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
            </div>
            <span class="font-medium">Overview</span>
        </button>

        <button onclick="switchView('nourish')" data-view="nourish"
            class="nav-link w-full flex items-center gap-4 p-4 rounded-2xl text-sage-400 hover:bg-sage-50 transition-all group">
            <div
                class="w-10 h-10 rounded-xl bg-sage-50 group-hover:bg-white flex items-center justify-center transition-colors">
                <i data-lucide="utensils" class="w-5 h-5"></i>
            </div>
            <span class="font-medium">Nourish Plan</span>
        </button>

        <button onclick="switchView('weekly')" data-view="weekly"
            class="nav-link w-full flex items-center gap-4 p-4 rounded-2xl text-sage-400 hover:bg-sage-50 transition-all group">
            <div
                class="w-10 h-10 rounded-xl bg-sage-50 group-hover:bg-white flex items-center justify-center transition-colors">
                <i data-lucide="calendar" class="w-5 h-5"></i>
            </div>
            <span class="font-medium">Weekly View</span>
        </button>

        <div class="pt-6">
            <p class="text-[10px] font-bold text-sage-300 uppercase tracking-widest px-4 mb-4">Wellness Tools</p>

            <button onclick="switchView('tracker')" data-view="tracker"
                class="nav-link w-full flex items-center gap-4 p-4 rounded-2xl text-sage-400 hover:bg-sage-50 transition-all group">
                <div
                    class="w-10 h-10 rounded-xl bg-sage-50 group-hover:bg-white flex items-center justify-center transition-colors">
                    <i data-lucide="line-chart" class="w-5 h-5"></i>
                </div>
                <span class="font-medium">Vitals Sync</span>
            </button>

            <button onclick="switchView('profile')" data-view="profile"
                class="nav-link w-full flex items-center gap-4 p-4 rounded-2xl text-sage-400 hover:bg-sage-50 transition-all group">
                <div
                    class="w-10 h-10 rounded-xl bg-sage-50 group-hover:bg-white flex items-center justify-center transition-colors">
                    <i data-lucide="user-settings" class="w-5 h-5"></i>
                </div>
                <span class="font-medium">Profile</span>
            </button>

            <!-- Transactions link removed - Admin only feature -->
        </div>
    </nav>

    <div class="p-6 border-t border-sage-50 space-y-4">
        <div class="bg-sage-500 rounded-2xl p-5 text-white shadow-xl shadow-sage-500/20 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 transform translate-x-2 -translate-y-2">
                <i data-lucide="message-circle" class="w-12 h-12"></i>
            </div>
            <p class="text-[10px] font-bold uppercase tracking-widest opacity-60 mb-1">Human Support</p>
            <p class="text-xs font-medium mb-4 leading-relaxed">Need help with your protocol? Chat with a specialist.
            </p>
            <button onclick="chatWithSpecialist()"
                class="w-full py-2.5 bg-white/10 hover:bg-white/20 rounded-xl text-[10px] uppercase font-bold tracking-widest transition-all backdrop-blur-sm border border-white/10">
                Start Chat
            </button>
        </div>

        <button onclick="handleLogout()"
            class="w-full flex items-center gap-4 p-4 rounded-2xl text-coral-400 hover:bg-coral-50 transition-all group">
            <div
                class="w-10 h-10 rounded-xl bg-coral-50 group-hover:bg-white flex items-center justify-center transition-colors">
                <i data-lucide="log-out" class="w-5 h-5"></i>
            </div>
            <span class="font-bold text-sm uppercase tracking-widest">Logout</span>
        </button>
    </div>
</aside>

<!-- Mobile Navigation -->
<nav
    class="lg:hidden fixed bottom-0 left-0 right-0 bg-sage-500/95 backdrop-blur-xl border-t border-white/10 px-6 py-4 flex justify-between items-center z-50 rounded-t-[2.5rem] shadow-2xl">
    <button onclick="switchView('dashboard')" data-view="dashboard"
        class="mobile-nav-link active flex flex-col items-center gap-1.5 text-white/50 transition-all">
        <i data-lucide="layout-dashboard" class="w-6 h-6"></i>
        <div class="active-dot hidden w-1 h-1 rounded-full bg-white"></div>
    </button>
    <button onclick="switchView('nourish')" data-view="nourish"
        class="mobile-nav-link flex flex-col items-center gap-1.5 text-white/50 transition-all">
        <i data-lucide="utensils" class="w-6 h-6"></i>
        <div class="active-dot hidden w-1 h-1 rounded-full bg-white"></div>
    </button>
    <button onclick="switchView('weekly')" data-view="weekly"
        class="mobile-nav-link flex flex-col items-center gap-1.5 text-white/50 transition-all">
        <i data-lucide="calendar" class="w-6 h-6"></i>
        <div class="active-dot hidden w-1 h-1 rounded-full bg-white"></div>
    </button>
    <button onclick="switchView('tracker')" data-view="tracker"
        class="mobile-nav-link flex flex-col items-center gap-1.5 text-white/50 transition-all">
        <i data-lucide="line-chart" class="w-6 h-6"></i>
        <div class="active-dot hidden w-1 h-1 rounded-full bg-white"></div>
    </button>
    <button onclick="switchView('profile')" data-view="profile"
        class="mobile-nav-link flex flex-col items-center gap-1.5 text-white/50 transition-all">
        <i data-lucide="user" class="w-6 h-6"></i>
        <div class="active-dot hidden w-1 h-1 rounded-full bg-white"></div>
    </button>
</nav>