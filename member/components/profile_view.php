<?php
/**
 * 1wellness Member Profile View Component
 */
?>
<div id="profileView" class="view-section hidden space-y-12">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 pb-8 border-b border-sage-100">
        <div class="space-y-4 max-w-2xl">
            <h2 class="text-4xl font-serif text-sage-600">Member Settings</h2>
            <p class="text-sage-400 text-sm leading-relaxed">
                Manage your personal information, protocol preferences, and subscription status.
            </p>
        </div>
        <div class="flex gap-4">
            <button onclick="handleLogout()" class="px-6 py-3 bg-white border border-coral-100 rounded-2xl text-xs font-bold text-coral-400 uppercase tracking-widest flex items-center gap-2 hover:bg-coral-50 transition-all">
                <i data-lucide="log-out" class="w-4 h-4"></i>
                Sign Out
            </button>
            <button onclick="saveProfile()" id="saveProfileBtn" class="px-6 py-3 bg-sage-500 text-white rounded-2xl text-xs font-bold uppercase tracking-widest shadow-xl shadow-sage-500/20 hover:scale-[1.02] transition-all">
                Save Changes
            </button>
        </div>
    </div>

    <!-- Profile Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
        <!-- Sidebar Navigation -->
        <div class="space-y-4">
            <button class="w-full text-left p-6 bg-white border border-sage-500/20 rounded-[2.5rem] shadow-sm flex items-center gap-4 group">
                <div class="w-10 h-10 rounded-2xl bg-sage-500 text-white flex items-center justify-center">
                    <i data-lucide="user" class="w-5 h-5"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-sage-600 uppercase tracking-widest">Personal Info</h4>
                    <p class="text-[9px] text-sage-300 font-medium">Basic identity details</p>
                </div>
            </button>
            <button class="w-full text-left p-6 bg-white border border-sage-50 rounded-[2.5rem] hover:bg-sage-50 transition-all flex items-center gap-4 group">
                <div class="w-10 h-10 rounded-2xl bg-sage-50 text-sage-400 group-hover:bg-white flex items-center justify-center transition-colors">
                    <i data-lucide="lock" class="w-5 h-5"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-sage-400 uppercase tracking-widest">Account Security</h4>
                    <p class="text-[9px] text-sage-300 font-medium">Password & Auth</p>
                </div>
            </button>
            <button class="w-full text-left p-6 bg-white border border-sage-50 rounded-[2.5rem] hover:bg-sage-50 transition-all flex items-center gap-4 group">
                <div class="w-10 h-10 rounded-2xl bg-sage-50 text-sage-400 group-hover:bg-white flex items-center justify-center transition-colors">
                    <i data-lucide="credit-card" class="w-5 h-5"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-sage-400 uppercase tracking-widest">Subscription</h4>
                    <p class="text-[9px] text-sage-300 font-medium">Billing & Renewal</p>
                </div>
            </button>
        </div>

        <!-- Form Content -->
        <div class="lg:col-span-2 space-y-12">
            <form id="profileForm" class="space-y-12">
                <!-- Personal Info -->
                <div class="bg-white p-10 rounded-[3rem] border border-sage-50 shadow-sm space-y-10">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-3">
                            <label class="text-[10px] font-bold text-sage-300 uppercase tracking-[0.2em] ml-2">Username</label>
                            <div class="relative group">
                                <i data-lucide="at-sign" class="absolute left-6 top-1/2 -translate-y-1/2 w-4 h-4 text-sage-200 group-focus-within:text-sage-500 transition-colors"></i>
                                <input type="text" name="username" readonly 
                                    class="w-full pl-14 pr-6 py-4 bg-sage-50/30 border border-sage-100 rounded-2xl text-sage-400 text-sm focus:outline-none cursor-not-allowed"
                                    value="member_user">
                            </div>
                        </div>
                        <div class="space-y-3">
                            <label class="text-[10px] font-bold text-sage-300 uppercase tracking-[0.2em] ml-2">Email Address</label>
                            <div class="relative group">
                                <i data-lucide="mail" class="absolute left-6 top-1/2 -translate-y-1/2 w-4 h-4 text-sage-200 group-focus-within:text-sage-500 transition-colors"></i>
                                <input type="email" name="email" 
                                    class="w-full pl-14 pr-6 py-4 bg-white border border-sage-100 rounded-2xl text-sage-600 text-sm focus:outline-none focus:border-sage-500 focus:shadow-sm transition-all"
                                    placeholder="member@example.com">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <label class="text-[10px] font-bold text-sage-300 uppercase tracking-[0.2em] ml-2">Phone Number</label>
                        <div class="relative group max-w-sm">
                            <i data-lucide="phone" class="absolute left-6 top-1/2 -translate-y-1/2 w-4 h-4 text-sage-200 group-focus-within:text-sage-500 transition-colors"></i>
                            <input type="tel" name="phone_number" 
                                class="w-full pl-14 pr-6 py-4 bg-white border border-sage-100 rounded-2xl text-sage-600 text-sm focus:outline-none focus:border-sage-500 focus:shadow-sm transition-all"
                                placeholder="+234 ...">
                        </div>
                    </div>
                </div>

                <!-- Subscription Status -->
                <div class="bg-sage-900 rounded-[3rem] p-10 text-white relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-10 opacity-10 transform scale-150 grayscale">
                        <i data-lucide="diamond" class="w-32 h-32"></i>
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-8">
                            <span class="px-4 py-1.5 bg-coral-400 rounded-full text-[9px] font-bold uppercase tracking-widest">Premium Plan</span>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-emerald-400 rounded-full"></span>
                                <span class="text-[10px] font-bold uppercase tracking-widest">Active</span>
                            </div>
                        </div>
                        <h3 class="text-3xl font-serif mb-4">90 Day PCOS Protocol</h3>
                        <div class="flex items-center gap-3 text-white/50 text-sm mb-10">
                            <i data-lucide="calendar" class="w-4 h-4"></i>
                            Renews on <span id="renewalDate" class="text-white font-medium">...</span>
                        </div>
                        <div class="flex gap-4">
                            <button onclick="openRenewalModal()" type="button" class="px-8 py-3 bg-white text-sage-900 rounded-2xl text-[10px] font-bold uppercase tracking-widest hover:scale-[1.02] transition-all">
                                Extend Protocol
                            </button>
                            <button type="button" class="px-8 py-3 bg-white/10 rounded-2xl text-[10px] font-bold uppercase tracking-widest hover:bg-white/20 transition-all border border-white/10">
                                View Billing
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
