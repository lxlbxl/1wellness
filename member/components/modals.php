<?php
/**
 * 1wellness Member Modals Component
 */
?>
<!-- Recipe Modal -->
<div id="recipeModal"
    class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-sage-900/40 backdrop-blur-sm p-4 transition-all duration-300">
    <div
        class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col relative overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <!-- Header -->
        <div class="p-6 border-b border-sage-100 bg-sage-50/30 flex justify-between items-center shrink-0">
            <div>
                <p class="text-[10px] font-bold text-sage-300 uppercase tracking-widest mb-1">Recipe Details</p>
                <h3 id="recipeModalTitle" class="font-serif text-xl text-sage-800 leading-tight"></h3>
            </div>
            <button onclick="closeRecipeModal()"
                class="w-8 h-8 rounded-full bg-white border border-sage-100 text-sage-400 flex items-center justify-center hover:bg-sage-50 transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Scrollable Content -->
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <div id="recipeModalContent" class="space-y-8">
                <!-- Content injected via JS -->
            </div>
        </div>

        <!-- Footer CTA -->
        <div class="p-4 border-t border-sage-100 bg-white shrink-0">
            <button onclick="closeRecipeModal()"
                class="w-full py-3 bg-sage-900 text-white rounded-xl font-bold text-sm shadow-lg shadow-sage-900/10 hover:shadow-sage-900/20 transition-all">
                Done
            </button>
        </div>
    </div>
</div>

<!-- Expiry Overlay - Shows when subscription is expired -->
<div id="expiryOverlay"
    class="fixed inset-0 z-[70] hidden bg-sage-900/90 backdrop-blur-xl flex items-center justify-center p-4">
    <div
        class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg p-10 text-center animate-in fade-in zoom-in-95 duration-300">
        <div class="w-20 h-20 bg-coral-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <i data-lucide="alert-circle" class="w-10 h-10 text-coral-500"></i>
        </div>

        <h2 class="font-serif text-3xl text-sage-800 mb-3">Your Plan Has Expired</h2>
        <p class="text-sage-500 mb-8 leading-relaxed">
            Your personalized protocol access has ended. Renew now to continue your healing journey and maintain your
            progress.
        </p>

        <div class="space-y-4">
            <button onclick="openRenewalModal()"
                class="w-full py-4 bg-sage-500 text-white rounded-2xl font-bold text-lg shadow-xl shadow-sage-500/20 hover:shadow-sage-500/30 hover:scale-[1.02] transition-all flex items-center justify-center gap-3">
                <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                Renew My Plan
            </button>

            <button onclick="handleLogout()"
                class="w-full py-4 bg-sage-50 text-sage-400 rounded-2xl font-bold hover:bg-sage-100 transition-all flex items-center justify-center gap-3">
                <i data-lucide="log-out" class="w-5 h-5"></i>
                Log Out
            </button>
        </div>

        <p class="mt-6 text-xs text-sage-300">
            Need help? <a href="https://wa.me/2348133149989" target="_blank"
                class="text-sage-500 hover:underline">Contact support</a>
        </p>
    </div>
</div>

<!-- Renewal Modal - Plan Selection and Payment -->
<div id="renewalModal" class="fixed inset-0 z-[80] hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-sage-900/60 backdrop-blur-sm" onclick="closeRenewalModal()"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div
                class="relative transform overflow-hidden rounded-[2.5rem] bg-white text-left shadow-2xl transition-all w-full max-w-4xl animate-in fade-in zoom-in-95 duration-300">
                <!-- Header -->
                <div class="px-8 pt-8 pb-6 border-b border-sage-100 flex justify-between items-center">
                    <div>
                        <p class="text-[10px] font-bold text-sage-300 uppercase tracking-widest mb-1">Renew Your Access
                        </p>
                        <h2 class="font-serif text-2xl text-sage-800">Choose Your Plan</h2>
                    </div>
                    <button onclick="closeRenewalModal()"
                        class="w-10 h-10 rounded-full bg-sage-50 text-sage-400 flex items-center justify-center hover:bg-sage-100 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Plans Container -->
                <div class="p-8">
                    <div id="renewalPlans" class="grid md:grid-cols-2 gap-6">
                        <!-- Plans will be loaded here by JavaScript -->
                        <div class="col-span-full py-12 flex justify-center">
                            <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-sage-500"></div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-8 py-6 bg-sage-50/50 border-t border-sage-100">
                    <div class="flex items-center gap-3 text-sm text-sage-500">
                        <i data-lucide="shield-check" class="w-5 h-5 text-sage-400"></i>
                        <span>Secure payment powered by Flutterwave. Your data is encrypted and protected.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mandatory Onboarding Modal -->
<div id="onboardingModal" class="fixed inset-0 z-[60] hidden" role="dialog" aria-modal="true" data-backdrop="static">
    <div class="fixed inset-0 bg-sage-500/80 backdrop-blur-xl"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <div
                class="relative transform overflow-hidden rounded-[3rem] bg-cream-50 text-left shadow-2xl transition-all sm:w-full sm:max-w-xl border border-sage-100">
                <!-- Progress Line -->
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-sage-100">
                    <div id="boardingProgress" class="h-full bg-coral-400 transition-all duration-700 ease-out"
                        style="width: 33%"></div>
                </div>

                <form onsubmit="saveOnboarding(event)" class="flex flex-col h-full">
                    <!-- Header -->
                    <div class="px-10 pt-12 pb-6">
                        <span class="text-[10px] font-bold uppercase tracking-[0.3em] text-sage-300 block mb-2">Step
                            <span id="currentStepNum">1</span> of 3</span>
                        <h2 id="boardingStepTitle" class="text-4xl font-serif text-sage-500 mb-2">Vital Metrics</h2>
                        <p class="text-sage-400 text-sm leading-relaxed">Let's start with your basic body measurements.
                        </p>
                    </div>

                    <!-- Step Content -->
                    <div class="px-10 pb-12 flex-1">
                        <!-- Step 1: Vitals -->
                        <div class="boarding-step space-y-8 animate-fade-in-up" data-step="1">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <label class="block text-xs font-bold text-sage-400 uppercase tracking-widest">Your
                                        Weight</label>
                                    <div class="relative">
                                        <input type="number" name="weight" required step="0.1" placeholder="00.0"
                                            class="w-full text-4xl font-serif bg-transparent border-b-2 border-sage-100 py-4 focus:outline-none focus:border-sage-500 transition-colors">
                                        <span class="absolute right-0 bottom-4 text-sage-300 font-medium">KG</span>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <label class="block text-xs font-bold text-sage-400 uppercase tracking-widest">Your
                                        Age</label>
                                    <div class="relative">
                                        <input type="number" name="age" required min="13" max="90" placeholder="25"
                                            class="w-full text-4xl font-serif bg-transparent border-b-2 border-sage-100 py-4 focus:outline-none focus:border-sage-500 transition-colors">
                                        <span class="absolute right-0 bottom-4 text-sage-300 font-medium">YRS</span>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-6">
                                <div class="flex justify-between items-center">
                                    <label class="block text-xs font-bold text-sage-400 uppercase tracking-widest">Your
                                        Height</label>
                                    <div class="flex bg-sage-50 rounded-full p-1 border border-sage-100 scale-90">
                                        <label
                                            class="px-4 py-1 rounded-full text-xs font-bold cursor-pointer transition-all has-[:checked]:bg-white has-[:checked]:shadow-sm has-[:checked]:text-sage-500 text-sage-300">
                                            <input type="radio" name="h_unit" value="cm" checked class="hidden"
                                                onchange="toggleHeightUnit()"> CM
                                        </label>
                                        <label
                                            class="px-4 py-1 rounded-full text-xs font-bold cursor-pointer transition-all has-[:checked]:bg-white has-[:checked]:shadow-sm has-[:checked]:text-sage-500 text-sage-300">
                                            <input type="radio" name="h_unit" value="ft" class="hidden"
                                                onchange="toggleHeightUnit()"> FT
                                        </label>
                                    </div>
                                </div>
                                <div id="height_cm_row" class="relative">
                                    <input type="number" name="height_cm" step="1" placeholder="170"
                                        class="w-full text-5xl font-serif bg-transparent border-b-2 border-sage-100 py-4 focus:outline-none focus:border-sage-500 transition-colors">
                                    <span class="absolute right-0 bottom-4 text-sage-300 font-medium">CM</span>
                                </div>
                                <div id="height_ft_row" class="hidden flex gap-8">
                                    <div class="relative flex-1">
                                        <input type="number" name="height_ft" placeholder="5"
                                            class="w-full text-5xl font-serif bg-transparent border-b-2 border-sage-100 py-4 focus:outline-none focus:border-sage-500 transition-colors">
                                        <span class="absolute right-0 bottom-4 text-sage-300 font-medium">FT</span>
                                    </div>
                                    <div class="relative flex-1">
                                        <input type="number" name="height_in" placeholder="10"
                                            class="w-full text-5xl font-serif bg-transparent border-b-2 border-sage-100 py-4 focus:outline-none focus:border-sage-500 transition-colors">
                                        <span class="absolute right-0 bottom-4 text-sage-300 font-medium">IN</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Hormonal Profile -->
                        <div class="boarding-step hidden space-y-8 animate-fade-in-up" data-step="2">
                            <div class="space-y-4">
                                <label class="block text-xs font-bold text-sage-400 uppercase tracking-widest">PCOS
                                    Type</label>
                                <div class="grid grid-cols-1 gap-4">
                                    <label
                                        class="relative flex items-center p-4 border-2 border-sage-100 rounded-2xl cursor-pointer hover:bg-sage-50 transition-all has-[:checked]:border-sage-500 has-[:checked]:bg-sage-50/50 group">
                                        <input type="radio" name="pcos_type" value="Insulin Resistant" class="hidden"
                                            required>
                                        <div class="flex-1">
                                            <div class="font-bold text-sage-500">Insulin Resistant</div>
                                            <div class="text-[10px] text-sage-400 leading-tight">Weight gain, fatigue,
                                                sugar cravings.</div>
                                        </div>
                                        <i data-lucide="check-circle-2"
                                            class="w-5 h-5 text-sage-500 opacity-0 group-has-[:checked]:opacity-100 transition-opacity"></i>
                                    </label>
                                    <label
                                        class="relative flex items-center p-4 border-2 border-sage-100 rounded-2xl cursor-pointer hover:bg-sage-50 transition-all has-[:checked]:border-sage-500 has-[:checked]:bg-sage-50/50 group">
                                        <input type="radio" name="pcos_type" value="Adrenal" class="hidden">
                                        <div class="flex-1">
                                            <div class="font-bold text-sage-500">Adrenal Driven</div>
                                            <div class="text-[10px] text-sage-400 leading-tight">High stress, anxiety,
                                                sleep issues.</div>
                                        </div>
                                        <i data-lucide="check-circle-2"
                                            class="w-5 h-5 text-sage-500 opacity-0 group-has-[:checked]:opacity-100 transition-opacity"></i>
                                    </label>
                                    <label
                                        class="relative flex items-center p-4 border-2 border-sage-100 rounded-2xl cursor-pointer hover:bg-sage-50 transition-all has-[:checked]:border-sage-500 has-[:checked]:bg-sage-50/50 group">
                                        <input type="radio" name="pcos_type" value="Inflammatory" class="hidden">
                                        <div class="flex-1">
                                            <div class="font-bold text-sage-500">Inflammatory</div>
                                            <div class="text-[10px] text-sage-400 leading-tight">Acne, headaches, joint
                                                pain.</div>
                                        </div>
                                        <i data-lucide="check-circle-2"
                                            class="w-5 h-5 text-sage-500 opacity-0 group-has-[:checked]:opacity-100 transition-opacity"></i>
                                    </label>
                                    <label
                                        class="relative flex items-center p-4 border-2 border-sage-100 rounded-2xl cursor-pointer hover:bg-sage-50 transition-all has-[:checked]:border-sage-500 has-[:checked]:bg-sage-50/50 group">
                                        <input type="radio" name="pcos_type" value="Post-Pill" class="hidden">
                                        <div class="flex-1">
                                            <div class="font-bold text-sage-500">Post-Pill</div>
                                            <div class="text-[10px] text-sage-400 leading-tight">Symptoms flared after
                                                birth control.</div>
                                        </div>
                                        <i data-lucide="check-circle-2"
                                            class="w-5 h-5 text-sage-500 opacity-0 group-has-[:checked]:opacity-100 transition-opacity"></i>
                                    </label>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label
                                        class="block text-[10px] font-bold text-sage-400 uppercase tracking-widest">Cycle
                                        Length</label>
                                    <input type="number" name="cycle_length" value="28"
                                        class="w-full p-3 bg-white border border-sage-100 rounded-xl focus:outline-none focus:border-sage-500">
                                </div>
                                <div class="space-y-2">
                                    <label
                                        class="block text-[10px] font-bold text-sage-400 uppercase tracking-widest">Last
                                        Period</label>
                                    <input type="date" name="last_period_date"
                                        class="w-full p-3 bg-white border border-sage-100 rounded-xl focus:outline-none focus:border-sage-500">
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Diet & Custom -->
                        <div class="boarding-step hidden space-y-8 animate-fade-in-up" data-step="3">
                            <div class="space-y-4">
                                <label class="block text-xs font-bold text-sage-400 uppercase tracking-widest">Allergies
                                    / Intolerances</label>
                                <textarea name="allergies" rows="3" placeholder="No Allergies" required
                                    class="w-full p-6 bg-white border-2 border-sage-100 rounded-3xl focus:outline-none focus:border-sage-500 transition-all resize-none"></textarea>
                            </div>
                            <div class="space-y-4">
                                <label class="block text-xs font-bold text-sage-400 uppercase tracking-widest">Meal
                                    Preferences</label>
                                <textarea name="dietary_preferences" rows="3" placeholder="Nigerian Meal" required
                                    class="w-full p-6 bg-white border-2 border-sage-100 rounded-3xl focus:outline-none focus:border-sage-500 transition-all resize-none"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Error Container -->
                    <div id="onboardingError"
                        class="hidden mx-10 mb-4 p-4 bg-red-50 border border-red-100 rounded-xl text-red-600 text-sm flex items-start gap-2 animate-in fade-in slide-in-from-top-2">
                    </div>

                    <!-- Footer -->
                    <div class="px-10 pb-10 flex gap-4 mt-auto">
                        <button type="button" onclick="prevBoardingStep()"
                            class="boarding-prev-btn px-6 py-4 rounded-2xl bg-sage-50 text-sage-400 hover:bg-sage-100 transition-all hidden">
                            <i data-lucide="arrow-left" class="w-5 h-5"></i>
                        </button>
                        <button type="button" onclick="nextBoardingStep()"
                            class="boarding-next-btn flex-1 bg-sage-500 text-white font-bold py-4 rounded-2xl shadow-xl shadow-sage-500/20 hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                            <span>Continue</span> <i data-lucide="arrow-right" class="w-5 h-5"></i>
                        </button>
                        <button type="submit"
                            class="boarding-submit-btn flex-1 bg-coral-400 text-white font-bold py-4 rounded-2xl shadow-xl shadow-coral-400/20 hover:scale-[1.02] transition-all hidden flex items-center justify-center gap-2">
                            <span>Begin My Journey</span> <i data-lucide="sparkles" class="w-5 h-5"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>