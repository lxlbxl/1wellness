/**
 * 1wellness Member Dashboard JS
 * Extracted from index.html
 */

let userData = null;

// Initialize Lucide
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}

// 1. Initial Auth Check & Data Fetch
async function initialize() {
    try {
        // Quick session check
        const authRes = await fetch('/member/api/check_auth.php', { credentials: 'include' });
        const authData = await authRes.json();

        if (!authData.authenticated) {
            window.location.href = 'login.html';
            return;
        }

        await fetchData();
        
        const loader = document.getElementById('loader');
        if (loader) loader.classList.add('hidden');

        if (userData && userData.user.subscription_status === 'expired') {
            const expiryOverlay = document.getElementById('expiryOverlay');
            if (expiryOverlay) expiryOverlay.classList.remove('hidden');
        } else {
            const app = document.getElementById('app');
            if (app) app.classList.remove('hidden');
            updateGreeting();
        }
    } catch (e) {
        console.error("Auth check failed", e);
        window.location.href = 'login.html';
    }
}

function updateGreeting() {
    const hour = new Date().getHours();
    let greeting = 'Good morning';

    if (hour >= 12 && hour < 17) {
        greeting = 'Good afternoon';
    } else if (hour >= 17 || hour < 5) {
        greeting = 'Good evening';
    }

    const el = document.getElementById('greetingText');
    if (el) el.innerText = greeting;
}

async function fetchData() {
    try {
        const response = await fetch('/member/api/data.php', { credentials: 'include' });
        if (response.status === 401) {
            window.location.href = 'login.html';
            return;
        }
        const data = await response.json();
        if (data.success) {
            userData = data;

            // --- PROFILE COMPLETENESS CHECK ---
            const p = userData.user;
            const b = userData.body;

            const pcosType = (p.pcos_type || "").toLowerCase();
            const allergies = (b.allergies || "").toLowerCase();
            const prefs = (b.dietary_preferences || "").toLowerCase();

            const isProfileIncomplete =
                !pcosType || pcosType === 'general' || pcosType === 'pcos' || pcosType === 'unknown' ||
                (!allergies && allergies !== '') || 
                !allergies ||
                !prefs;

            if (isProfileIncomplete) {
                console.log("Profile incomplete, showing modal", { pcosType, allergies, prefs });
                const onboardingModal = document.getElementById('onboardingModal');
                if (onboardingModal) {
                    onboardingModal.classList.remove('hidden');
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
                return;
            }
            // ----------------------------------

            renderDashboard();
            const nourishSection = document.getElementById('nourish');
            if (nourishSection && nourishSection.classList.contains('active')) {
                renderNourish();
            }

            // Trigger Proactive Gen if not done this session
            if (!sessionStorage.getItem('proactive_triggered')) {
                triggerProactiveGen();
            }
        } else {
            alert('Error loading data: ' + data.error);
        }
    } catch (err) {
        console.error("Dashboard Fetch Error:", err);
        alert('Fatal Error: Failed to connect to dashboard API. Please check your internet or contact support.');
    }
}

// 2. View Management
function switchView(viewId) {
    document.querySelectorAll('.view-section').forEach(s => s.classList.remove('active'));
    // Support both 'dashboard' and 'dashboardView' ID patterns
    const targetSection = document.getElementById(viewId) || document.getElementById(viewId + 'View');
    if (targetSection) targetSection.classList.add('active');

    document.querySelectorAll('.nav-link, .mobile-nav-link').forEach(link => {
        if (link.dataset.view === viewId) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    if (viewId === 'dashboard') renderDashboard();
    if (viewId === 'nourish') renderNourish();
    if (viewId === 'weekly') renderWeekly();
    if (viewId === 'tracker') renderBodyForm();
    if (viewId === 'profile') renderProfileForm();
}

function renderBodyForm() {
    if (!userData) return;
    const { body, user } = userData;
    const fields = {
        'body_weight': body.weight,
        'body_height': body.height,
        'body_age': user.age,
        'body_pcos_type': user.pcos_type,
        'body_cycle_length': body.cycle_length,
        'body_last_period': body.last_period_date,
        'body_allergies': body.allergies,
        'body_diet_prefs': body.dietary_preferences
    };

    for (const [id, val] of Object.entries(fields)) {
        const el = document.getElementById(id);
        if (el) el.value = val;
    }
}

async function saveBodyData(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="animate-spin w-5 h-5 border-2 border-white border-t-transparent rounded-full mx-auto"></div>';
    btn.disabled = true;

    const formData = new FormData(e.target);
    formData.append('action', 'update_body_data');

    try {
        const res = await fetch('/member/api/member_actions.php', { method: 'POST', body: formData, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            await fetchData();
            alert('Body data updated successfully!');
        } else {
            alert('Error: ' + data.error);
        }
    } catch (err) {
        console.error(err);
        alert('Connection error');
    } finally {
        btn.innerHTML = originalContent;
        btn.disabled = false;
    }
}

function renderProfileForm() {
    if (!userData) return;
    const { user } = userData;
    
    const fields = {
        'profile_first_name': user.first_name || '',
        'profile_last_name': user.last_name || '',
        'profile_email': user.email || '',
        'profile_phone': user.phone || ''
    };

    for (const [id, val] of Object.entries(fields)) {
        const el = document.getElementById(id);
        if (el) el.value = val;
    }

    const tierEl = document.getElementById('profile_tier_big');
    if (tierEl) tierEl.textContent = (user.tier || '30-day').toUpperCase() + " Plan";
    
    const passEl = document.getElementById('profile_password');
    if (passEl) passEl.value = '';

    // Update subscription info
    const statusEl = document.getElementById('profile_status_big');
    if (statusEl) {
        const isActive = user.subscription_status === 'active';
        statusEl.innerHTML = `<span class="${isActive ? 'text-green-500' : 'text-coral-500'}">${user.subscription_status.charAt(0).toUpperCase() + user.subscription_status.slice(1)}</span>`;
        
        const iconEl = document.getElementById('statusIcon');
        if (iconEl) {
            iconEl.className = `w-12 h-12 rounded-2xl flex items-center justify-center ${isActive ? 'bg-green-500/10 text-green-500' : 'bg-coral-500/10 text-coral-500'}`;
            iconEl.innerHTML = `<i data-lucide="${isActive ? 'check-circle' : 'alert-circle'}" class="w-6 h-6"></i>`;
        }
    }

    const daysLeftEl = document.getElementById('profile_days_left_big');
    if (daysLeftEl) {
        daysLeftEl.textContent = user.days_left > 0 ? `${user.days_left} days left on protocol` : (user.days_left === 0 ? 'Protocol expired' : 'Pending activation');
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function switchProfileTab(tab) {
    // Update buttons
    document.querySelectorAll('.profile-tab').forEach(btn => {
        btn.classList.remove('border-sage-500', 'text-sage-600');
        btn.classList.add('border-transparent', 'text-sage-300');
    });
    const activeTab = document.getElementById('tab-' + tab);
    if (activeTab) {
        activeTab.classList.remove('border-transparent', 'text-sage-300');
        activeTab.classList.add('border-sage-500', 'text-sage-600');
    }

    // Update content
    document.querySelectorAll('.profile-content').forEach(c => c.classList.add('hidden'));
    const content = document.getElementById('content-' + tab);
    if (content) content.classList.remove('hidden');

    // Show/hide Save button
    const actions = document.getElementById('profileActions');
    if (actions) {
        if (tab === 'personal') {
            actions.classList.remove('hidden');
        } else {
            actions.classList.add('hidden');
        }
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

async function saveProfile(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="animate-spin w-5 h-5 border-2 border-white border-t-transparent rounded-full mx-auto"></div>';
    btn.disabled = true;

    const formData = new FormData(e.target);
    formData.append('action', 'update_profile');

    try {
        const res = await fetch('/member/api/member_actions.php', { method: 'POST', body: formData, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            await fetchData();
            alert('Profile updated successfully!');
        } else {
            alert('Error: ' + data.error);
        }
    } catch (err) {
        console.error(err);
        alert('Connection error');
    } finally {
        btn.innerHTML = originalContent;
        btn.disabled = false;
    }
}

async function renderWeekly() {
    const container = document.getElementById('weeklyContainer');
    if (!container) return;
    container.innerHTML = '<div class="col-span-full py-12 flex justify-center"><div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-sage-500"></div></div>';

    try {
        const res = await fetch('/member/api/data.php?action=weekly_plans', { credentials: 'include' });
        const data = await res.json();

        if (data.success) {
            container.innerHTML = data.plans.map(day => `
                <div class="bg-white rounded-[2rem] p-6 border border-sage-100 shadow-sm transition-all hover:shadow-md flex flex-col ${day.is_today ? 'ring-2 ring-sage-500 ring-offset-4' : ''}">
                    <div class="text-center mb-6 pb-4 border-b border-sage-50">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] ${day.is_today ? 'text-sage-500' : 'text-sage-200'}">${day.is_today ? 'Today' : 'Upcoming'}</span>
                        <h4 class="font-serif text-xl text-sage-500 mt-1">${day.display_date}</h4>
                    </div>

                    <div class="space-y-4 flex-1">
                        ${day.plan && day.plan.meals ? `
                            <div class="bg-sage-50/30 p-3 rounded-xl">
                                <p class="text-[9px] font-bold text-sage-300 uppercase mb-1">Breakfast</p>
                                <p class="text-xs font-medium text-sage-500 line-clamp-1">${day.plan.meals.breakfast?.name || 'Pending...'}</p>
                            </div>
                            <div class="bg-sage-50/30 p-3 rounded-xl">
                                <p class="text-[9px] font-bold text-sage-300 uppercase mb-1">Lunch</p>
                                <p class="text-xs font-medium text-sage-500 line-clamp-1">${day.plan.meals.lunch?.name || 'Pending...'}</p>
                            </div>
                            <div class="bg-sage-50/30 p-3 rounded-xl">
                                <p class="text-[9px] font-bold text-sage-300 uppercase mb-1">Dinner</p>
                                <p class="text-xs font-medium text-sage-500 line-clamp-1">${day.plan.meals.dinner?.name || 'Pending...'}</p>
                            </div>
                            <div class="pt-2">
                                <p class="text-[9px] font-bold text-coral-400 uppercase mb-1 text-center">Movement</p>
                                <p class="text-[10px] italic text-sage-400 text-center">${day.plan.workout?.name || 'Rest & Recovery'}</p>
                            </div>
                        ` : `
                            <div class="h-full flex flex-col items-center justify-center py-6 text-center opacity-30">
                                <i data-lucide="sparkles" class="w-6 h-6 mb-2"></i>
                                <p class="text-[10px] font-bold uppercase">Pending</p>
                            </div>
                        `}
                    </div>
                    
                    <button onclick="viewDay('${day.date}')" class="mt-6 w-full py-3 bg-sage-50 text-sage-400 text-[10px] font-bold uppercase tracking-widest rounded-xl hover:bg-sage-500 hover:text-white transition-all">
                        View Protocol
                    </button>
                </div>
            `).join('');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    } catch (e) {
        container.innerHTML = '<p class="col-span-full text-center text-red-500">Failed to load weekly plans.</p>';
    }
}

async function viewDay(date) {
    const loader = document.getElementById('loader');
    if (loader) loader.classList.remove('hidden');
    try {
        const res = await fetch(`/member/api/data.php?action=dashboard_data&date=${date}`, { credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            userData = data;
            switchView('dashboard');
        }
    } catch (e) {
        console.error(e);
    } finally {
        if (loader) loader.classList.add('hidden');
    }
}

async function triggerProactiveGen(force = false) {
    sessionStorage.setItem('proactive_triggered', 'true');

    // Show generating state immediately in the main container if it exists
    const container = document.getElementById('mealsContainer');
    if (container) {
        container.innerHTML = `
            <div class="col-span-full py-16 text-center bg-white/50 rounded-[3rem] border border-sage-100">
                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-sage-500 mx-auto mb-6"></div>
                <h3 class="text-2xl font-serif text-sage-600 mb-2">Curating Your Protocol</h3>
                <p class="text-sage-400 max-w-md mx-auto mb-8">
                    Your personalized nutrition plan is being generated by our AI based on your hormonal profile.
                    This usually takes about 10-15 seconds.
                </p>
            </div>
        `;
    }

    try {
        // Call generation
        const url = force ? '/member/api/data.php?action=regenerate_plan' : '/member/api/data.php?action=proactive_gen&days=3';
        await fetch(url, { credentials: 'include' });

        // Wait 2s then reload to show data
        setTimeout(() => window.location.reload(), 2000);
    } catch (e) {
        console.error(e);
    }
}

function showSculpting() {
    const overlay = document.getElementById('sculptingOverlay');
    if (overlay) {
        overlay.classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

// 3. Render Logic
function renderDashboard() {
    if (!userData) return;
    const { user, cycle, plan } = userData;

    const viewDate = userData.plan?.plan_date || new Date().toISOString().split('T')[0];
    const isToday = viewDate === new Date().toISOString().split('T')[0];

    const userName = user.first_name || user.name || (user.email ? user.email.split('@')[0] : "Member");
    const userNameEl = document.getElementById('userName');
    if (userNameEl) userNameEl.textContent = userName;

    const currentDateEl = document.getElementById('currentDate');
    if (currentDateEl) {
        currentDateEl.textContent = new Date(viewDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        // Add "Back to Today" if not today
        if (!isToday) {
            currentDateEl.innerHTML = `${currentDateEl.textContent} <button onclick="goToday()" class="ml-2 text-coral-400 hover:underline text-[10px] uppercase font-bold">Back to Today</button>`;
        }
    }

    const week = user.program_week;
    const phaseIndicator = document.getElementById('phaseIndicator');
    if (phaseIndicator) phaseIndicator.textContent = week <= 4 ? "Month 1: Repair" : (week <= 8 ? "Month 2: Reset" : "Month 3: Restore");

    const cyclePhase = document.getElementById('cyclePhase');
    if (cyclePhase) cyclePhase.textContent = cycle.phase;
    
    const cycleDay = document.getElementById('cycleDay');
    if (cycleDay) cycleDay.textContent = `Day ${cycle.day} of cycle`;
    
    const pcosTag = document.getElementById('pcosTag');
    if (pcosTag) pcosTag.textContent = user.pcos_type + " Type";
    
    const hydVal = document.getElementById('hydrationVal');
    if (hydVal) hydVal.textContent = (plan.hydration || 0).toFixed(1) + 'L';

    // Daily Fruit Protocol
    const fruit = plan.fruit_ritual || {};
    const fruitName = fruit.name || 'Green Apple';
    const fruitPortion = fruit.portion || '2 medium fruits';
    const fruitBenefit = fruit.benefits || 'High in potassium & fiber';
    const fruitReason = fruit.why_it_works || 'Fiber helps regulate blood sugar while potassium supports healthy fluid balance.';

    const supplementList = document.getElementById('supplementList');
    if (supplementList) {
        supplementList.innerHTML = `
            <div class="bg-gradient-to-br from-terracotta-50 to-orange-50/50 rounded-2xl p-5 border border-terracotta-100/50 relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 text-4xl transform translate-x-2 -translate-y-2">🍎</div>
                
                <div class="relative z-10">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h5 class="text-[10px] font-bold text-terracotta-400 uppercase tracking-widest mb-1">Daily Fruit Protocol</h5>
                            <h4 id="fruitName" class="font-serif text-xl text-terracotta-900 leading-tight">${fruitName}</h4>
                            <p class="text-xs text-terracotta-600 mt-1 font-medium">${fruitPortion}</p>
                        </div>
                        <button id="fruitDoneBtn" onclick="markActivityDone('fruit_ritual')" 
                            class="w-10 h-10 rounded-full bg-white border border-terracotta-100 text-terracotta-400 flex items-center justify-center hover:bg-terracotta-500 hover:text-white transition-all shadow-sm">
                            <i data-lucide="check" class="w-5 h-5"></i>
                        </button>
                    </div>
                    
                    <div class="bg-white/60 rounded-xl p-3 border border-white/50 backdrop-blur-sm">
                        <p class="text-[10px] font-bold text-terracotta-400 uppercase mb-1">Why this works</p>
                        <p id="fruitReason" class="text-xs text-terracotta-800 leading-relaxed">${fruitReason}</p>
                    </div>
                    
                    <div class="mt-3 flex items-center gap-2 text-[10px] text-terracotta-600/80">
                        <i data-lucide="zap" class="w-3 h-3 text-terracotta-500"></i>
                        <span id="fruitBenefit">${fruitBenefit}</span>
                    </div>
                </div>
            </div>
        `;
    }

    // Fill Workout/Movement with Nigerian-focused walking data
    const workout = plan.workout || {};
    const workoutNameEl = document.getElementById('workoutName');
    if (workoutNameEl) workoutNameEl.textContent = workout.name || 'Morning & Evening Walk';
    
    const workoutDescEl = document.getElementById('workoutDesc');
    if (workoutDescEl) workoutDescEl.textContent = workout.description || 'Brisk walking routine to support hormonal balance and improve insulin sensitivity.';

    // Update step target if available
    const stepsTargetEl = document.getElementById('stepsTarget');
    if (stepsTargetEl && workout.steps_target) {
        stepsTargetEl.textContent = workout.steps_target.toLocaleString() + ' steps';
    }

    // Update time range if available
    const workoutTimeEl = document.getElementById('workoutTimeRange');
    if (workoutTimeEl && workout.activities && workout.activities.length > 0) {
        const timeHTML = workout.activities.map(a => `<span class="block">${a.time || ''}</span>`).join('');
        workoutTimeEl.innerHTML = timeHTML || '<span>6:00 AM - 6:30 PM</span>';
    }

    // Update herbal tea section if plan includes it
    const herbalTea = plan.herbal_tea || {};
    if (herbalTea.morning) {
        const morningTeaName = document.getElementById('morningTeaName');
        if (morningTeaName) morningTeaName.textContent = herbalTea.morning.name || 'PCOS Morning Harmony Blend';
        
        const morningTeaBenefits = document.getElementById('morningTeaBenefits');
        if (morningTeaBenefits) morningTeaBenefits.textContent = herbalTea.morning.benefits || 'Supports insulin sensitivity and hormonal balance';
        
        const morningTeaTime = document.getElementById('morningTeaTime');
        if (morningTeaTime) morningTeaTime.textContent = `${herbalTea.morning.time_start || '10:00'} - ${herbalTea.morning.time_end || '10:30'}`;
    }
    if (herbalTea.evening) {
        const eveningTeaName = document.getElementById('eveningTeaName');
        if (eveningTeaName) eveningTeaName.textContent = herbalTea.evening.name || 'PCOS Evening Calm Blend';
        
        const eveningTeaBenefits = document.getElementById('eveningTeaBenefits');
        if (eveningTeaBenefits) eveningTeaBenefits.textContent = herbalTea.evening.benefits || 'Reduces cortisol and promotes restful sleep';
        
        const eveningTeaTime = document.getElementById('eveningTeaTime');
        if (eveningTeaTime) eveningTeaTime.textContent = `${herbalTea.evening.time_start || '20:30'} - ${herbalTea.evening.time_end || '21:00'}`;
    }

    // Update meal preview with time ranges
    const mealPreviewGrid = document.getElementById('mealPreviewGrid');
    if (mealPreviewGrid) {
        const meals = ['breakfast', 'lunch', 'dinner'];
        mealPreviewGrid.innerHTML = meals.map(m => {
            const meal = plan?.meals?.[m];
            const mealName = meal?.name;
            const display = mealName || 'Planning...';
            const pulse = !mealName ? 'animate-pulse' : '';
            const timeRange = meal?.time_start && meal?.time_end ? `${meal.time_start} - ${meal.time_end}` : '';
            return `
            <div class="flex items-center gap-6 p-6 rounded-3xl bg-sage-50/50 border border-sage-50 group hover:border-sage-200 transition-all cursor-pointer" onclick="markActivityDone('meal_${m}')">
                <div class="w-20 h-20 rounded-2xl bg-sage-100 flex items-center justify-center text-3xl transition-transform group-hover:scale-110">
                    ${m === 'breakfast' ? '🥣' : (m === 'lunch' ? '🥗' : '🍲')}
                </div>
                <div class="flex-1 ${pulse}">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-[10px] font-bold text-sage-300 uppercase tracking-[0.2em]">${m}</p>
                        ${timeRange ? `<span class="text-[9px] text-sage-400">${timeRange}</span>` : ''}
                    </div>
                    <h4 class="text-xl font-medium">${display}</h4>
                </div>
                <button class="p-3 rounded-xl bg-sage-100 hover:bg-sage-500 hover:text-white text-sage-400 transition-all opacity-0 group-hover:opacity-100" title="Mark as Done">
                    <i data-lucide="check" class="w-4 h-4"></i>
                </button>
            </div>
        `}).join('');
    }

    // Check and update activity button states based on time windows
    updateActivityButtonStates();

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function renderNourish() {
    if (!userData) return;
    const { plan } = userData;

    const meals = ['breakfast', 'lunch', 'dinner'];
    const container = document.getElementById('mealsContainer');
    if (!container) return;

    // Check if we have valid meals
    const hasMeals = plan.meals && Object.keys(plan.meals).length > 0 && plan.meals.breakfast;

    if (!hasMeals) {
        container.innerHTML = `
            <div class="col-span-full py-16 text-center bg-white/50 rounded-[3rem] border border-sage-100">
                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-sage-500 mx-auto mb-6"></div>
                <h3 class="text-2xl font-serif text-sage-600 mb-2">Curating Your Protocol</h3>
                <p class="text-sage-400 max-w-md mx-auto mb-8">
                    Your personalized nutrition plan is being generated by our AI based on your hormonal profile.
                    This usually takes about 10-15 seconds.
                </p>
                <button onclick="triggerProactiveGen(true)" class="px-8 py-3 bg-sage-500 text-white rounded-xl font-bold text-sm shadow-xl shadow-sage-500/10 hover:shadow-sage-500/20 transition-all">
                    Regenerate Plan
                </button>
            </div>
        `;
    } else {
        container.innerHTML = meals.map(m => {
            const meal = plan.meals[m];
            if (!meal) return '';
            return `
                <div class="group h-full flex flex-col bg-white rounded-[2rem] p-6 border border-sage-100/50 shadow-sm hover:shadow-md hover:border-sage-200 transition-all duration-300">
                    <!-- Header -->
                    <div class="flex items-center gap-4 mb-6">
                        <div class="shrink-0 w-12 h-12 rounded-2xl bg-sage-50 text-sage-600 flex items-center justify-center text-2xl shadow-inner">
                            ${m === 'breakfast' ? '🥣' : (m === 'lunch' ? '🥗' : '🍲')}
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-sage-300 uppercase tracking-widest mb-1">${m}</p>
                            <h4 class="font-serif text-xl text-sage-700 leading-tight line-clamp-2">${meal.name}</h4>
                        </div>
                    </div>
                    
                    <!-- Body -->
                    <div class="flex-1 mb-6">
                        <p class="text-sm text-sage-500 leading-relaxed line-clamp-4">${meal.description}</p>
                    </div>
                    
                    <!-- Footer -->
                    <div class="mt-auto pt-6 border-t border-sage-50">
                        <button onclick="openRecipeModal('${m}')" class="w-full py-3.5 bg-sage-50 text-sage-600 rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-sage-100 hover:text-sage-700 transition-colors flex items-center justify-center gap-2 group-hover:gap-3">
                            <span>View Recipe</span>
                            <i data-lucide="arrow-right" class="w-3 h-3 transition-transform"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    const shoppingList = document.getElementById('shoppingList');
    if (shoppingList) {
        const list = plan.shopping_list || [];
        shoppingList.innerHTML = list.length > 0
            ? list.map(item => `
                    <div class="flex items-center gap-3 bg-white/10 p-4 rounded-2xl border border-white/5 group hover:bg-white/20 transition-all">
                    <div class="w-5 h-5 rounded-md border-2 border-white/20 group-hover:border-white/40 flex items-center justify-center text-[10px]">
                       <i data-lucide="check" class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                    </div>
                    <span class="text-sm font-medium opacity-90">${item.quantity} ${item.item}</span>
                </div>
                    `).join('')
            : '<p class="opacity-60 text-sm italic">Generate a plan to see items.</p>';
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

async function updateHydration(amount) {
    const newVal = Math.max(0, (userData.plan.hydration || 0) + amount);
    userData.plan.hydration = newVal;
    const hydValEl = document.getElementById('hydrationVal');
    if (hydValEl) hydValEl.textContent = newVal.toFixed(1) + 'L';

    const formData = new FormData();
    formData.append('action', 'update_hydration');
    formData.append('liters', newVal);
    fetch('/member/api/member_actions.php', { method: 'POST', body: formData, credentials: 'include' });
}

async function toggleSupplement(suppId) {
    if (!userData.plan.supplements) userData.plan.supplements = {};
    userData.plan.supplements[suppId] = !userData.plan.supplements[suppId];

    const formData = new FormData();
    formData.append('action', 'toggle_supplement');
    formData.append('supp_id', suppId);
    formData.append('completed', userData.plan.supplements[suppId] ? 'true' : 'false');
    fetch('/member/api/member_actions.php', { method: 'POST', body: formData, credentials: 'include' });
}

// Activity Tracking Functions
async function markActivityDone(activityType, event) {
    if (event) event.stopPropagation();

    const plan = userData?.plan || {};
    let activityName = '';
    let scheduledStart = '';
    let scheduledEnd = '';

    // Determine activity details based on type
    if (activityType.startsWith('meal_')) {
        const mealType = activityType.replace('meal_', '');
        const meal = plan.meals?.[mealType];
        activityName = meal?.name || mealType;
        scheduledStart = meal?.time_start || '';
        scheduledEnd = meal?.time_end || '';
    } else if (activityType === 'movement') {
        activityName = plan.workout?.name || 'Daily Walk';
        scheduledStart = plan.workout?.time_start || '06:00';
        scheduledEnd = plan.workout?.time_end || '18:30';
    } else if (activityType === 'herbal_tea_morning') {
        activityName = plan.herbal_tea?.morning?.name || 'Morning Herbal Tea';
        scheduledStart = plan.herbal_tea?.morning?.time_start || '10:00';
        scheduledEnd = plan.herbal_tea?.morning?.time_end || '10:30';
    } else if (activityType === 'herbal_tea_evening') {
        activityName = plan.herbal_tea?.evening?.name || 'Evening Herbal Tea';
        scheduledStart = plan.herbal_tea?.evening?.time_start || '20:30';
        scheduledEnd = plan.herbal_tea?.evening?.time_end || '21:00';
    } else if (activityType === 'fruit_ritual') {
        activityName = plan.fruit_ritual?.name || 'Fruit Protocol';
        scheduledStart = plan.fruit_ritual?.time_start || '10:00';
        scheduledEnd = plan.fruit_ritual?.time_end || '11:00';
    }

    const formData = new FormData();
    formData.append('action', 'mark_activity_done');
    formData.append('activity_type', activityType);
    formData.append('activity_name', activityName);
    formData.append('scheduled_start', scheduledStart);
    formData.append('scheduled_end', scheduledEnd);
    formData.append('plan_date', plan.plan_date || new Date().toISOString().split('T')[0]);

    try {
        const response = await fetch('/member/api/member_actions.php', { method: 'POST', body: formData, credentials: 'include' });
        const data = await response.json();

        if (data.success) {
            showNotification('✓ Activity marked as done!', 'success');
            updateButtonState(activityType, 'completed');
        } else {
            if (data.status === 'missed') {
                showNotification('⏰ Time window has passed - activity marked as missed', 'warning');
                updateButtonState(activityType, 'missed');
            } else if (data.status === 'completed') {
                showNotification('Already marked as done!', 'info');
            } else {
                showNotification(data.error || 'Failed to log activity', 'error');
            }
        }
    } catch (err) {
        console.error('Activity logging error:', err);
        showNotification('Connection error', 'error');
    }
}

function updateButtonState(activityType, status) {
    let btnId = '';
    if (activityType === 'movement') btnId = 'movementDoneBtn';
    else if (activityType === 'herbal_tea_morning') btnId = 'morningTeaDoneBtn';
    else if (activityType === 'herbal_tea_evening') btnId = 'eveningTeaDoneBtn';
    else if (activityType === 'fruit_ritual') btnId = 'fruitDoneBtn';

    const btn = document.getElementById(btnId);
    if (btn) {
        if (status === 'completed') {
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i>';
            btn.classList.add('bg-terracotta-500', 'text-white');
            btn.classList.remove('bg-white', 'text-terracotta-400', 'hover:bg-terracotta-500', 'hover:text-white');
        } else if (status === 'missed') {
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="x-circle" class="w-4 h-4"></i>';
            btn.classList.add('bg-red-500/20', 'text-red-400');
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function updateActivityButtonStates() {
    const now = new Date();
    const currentTime = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
    const plan = userData?.plan || {};

    // Check herbal tea times
    const herbalTea = plan.herbal_tea || {};

    // Morning tea
    if (herbalTea.morning?.time_end && currentTime > herbalTea.morning.time_end) {
        const gracePeriod = addMinutesToTime(herbalTea.morning.time_end, 30);
        if (currentTime > gracePeriod) {
            disableButton('morningTeaDoneBtn');
        }
    }

    // Evening tea
    if (herbalTea.evening?.time_end && currentTime > herbalTea.evening.time_end) {
        const gracePeriod = addMinutesToTime(herbalTea.evening.time_end, 30);
        if (currentTime > gracePeriod) {
            disableButton('eveningTeaDoneBtn');
        }
    }

    // Fruit Ritual
    const fruit = plan.fruit_ritual || {};
    if (fruit.time_end && currentTime > fruit.time_end) {
        const gracePeriod = addMinutesToTime(fruit.time_end, 30);
        if (currentTime > gracePeriod) {
            disableButton('fruitDoneBtn');
        }
    }
}

function disableButton(btnId) {
    const btn = document.getElementById(btnId);
    if (btn && !btn.disabled) {
        btn.disabled = true;
        btn.classList.add('opacity-40', 'cursor-not-allowed');
        btn.title = 'Time window has passed';
    }
}

function addMinutesToTime(timeStr, minutes) {
    const [hours, mins] = timeStr.split(':').map(Number);
    const totalMins = hours * 60 + mins + minutes;
    const newHours = Math.floor(totalMins / 60) % 24;
    const newMins = totalMins % 60;
    return newHours.toString().padStart(2, '0') + ':' + newMins.toString().padStart(2, '0');
}

function showNotification(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-amber-500',
        info: 'bg-sage-500'
    };

    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-2xl shadow-lg z-[200] animate-fade-in-up font-medium`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function openHerbalShop(productKey) {
    if (event) event.preventDefault();
    const shopUrl = `https://1wellness.club/shop?product=${productKey}`;

    if (productKey === 'bundle') {
        showNotification('🌿 Herbal Bundle offer - Opening shop...', 'info');
    }

    window.open(shopUrl, '_blank');
}

async function handleMealSwap(mealType) {
    const btn = event.currentTarget;
    const originalIcon = btn.innerHTML;
    btn.innerHTML = '<div class="animate-spin w-5 h-5 border-2 border-current border-t-transparent rounded-full"></div>';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'swap_meal');
    formData.append('meal_type', mealType);

    try {
        const response = await fetch('/member/api/member_actions.php', { method: 'POST', body: formData, credentials: 'include' });
        const resData = await response.json();
        if (resData.success) {
            userData.plan.meals[mealType] = resData.meal;
            renderNourish();
        } else {
            alert('Swap failed: ' + resData.error);
        }
    } catch (err) {
        console.error(err);
    } finally {
        btn.innerHTML = originalIcon;
        btn.disabled = false;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

async function handleLogout() {
    try {
        await fetch('/member/api/logout.php', { credentials: 'include' });
        window.location.href = 'login.html';
    } catch (e) {
        window.location.href = 'login.html';
    }
}

function chatWithSpecialist() {
    switchView('ai_chat');
}

async function goToday() {
    await initialize();
    switchView('dashboard');
}

// --- Renewal Logic ---
function openRenewalModal() {
    const renewalModal = document.getElementById('renewalModal');
    if (renewalModal) {
        renewalModal.classList.remove('hidden');
        fetchRenewalPlans();
    }
}

function closeRenewalModal() {
    const renewalModal = document.getElementById('renewalModal');
    if (renewalModal) renewalModal.classList.add('hidden');
}

async function fetchRenewalPlans() {
    const container = document.getElementById('renewalPlans');
    if (!container) return;
    try {
        const res = await fetch('/member/api/get-pricing.php', { credentials: 'include' });
        const data = await res.json();

        if (data.success) {
            const plans = data.data.plans;
            const funnel = userData?.user?.condition_type || 'pcos';
            const activePlans = plans[funnel] || plans['pcos'] || {};
            const pubKey = data.data.config.flutterwavePublicKey;

            container.innerHTML = Object.entries(activePlans).map(([key, plan]) => {
                const features = plan.features || [];
                const description = plan.description || 'Professional health protocol management.';

                return `
                <div class="luxury-card p-8 border border-sage-100 hover:border-sage-500 transition-all group flex flex-col justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-coral-400 uppercase tracking-widest">${key.replace('-', ' ')} Plan</span>
                        <h3 class="text-2xl font-serif text-sage-600 mt-2 mb-4">${plan.name}</h3>
                        <p class="text-sage-400 text-sm mb-6">${description}</p>
                        <ul class="space-y-2 mb-8">
                            ${features.map(f => `<li class="text-xs text-sage-400 flex items-center gap-2"><i data-lucide="check-circle-2" class="w-3 h-3 text-sage-300"></i> ${f}</li>`).join('')}
                            ${features.length === 0 ? '<li class="text-xs text-sage-400 flex items-center gap-2"><i data-lucide="check-circle-2" class="w-3 h-3 text-sage-300"></i> Full Digital Access</li>' : ''}
                        </ul>
                    </div>
                    <div class="mt-auto">
                        <div class="text-3xl font-serif text-sage-600 mb-6">$${new Intl.NumberFormat().format(plan.price)}</div>
                        <button onclick="processRenewal('${key}', ${plan.price}, '${pubKey}')" class="w-full bg-sage-500 text-white py-4 rounded-2xl font-bold hover:shadow-xl hover:shadow-sage-500/20 transition-all">
                            Choose Plan
                        </button>
                    </div>
                </div>
                `}).join('');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            throw new Error(data.message || 'API error');
        }
    } catch (e) {
        console.error("Renewal Plans Load Error:", e);
        container.innerHTML = `
                <div class="col-span-full py-12 text-center">
                <p class="text-coral-500 font-medium">Failed to load plans.</p>
                <p class="text-sage-300 text-[10px] mt-2">${e.message}</p>
                <button onclick="fetchRenewalPlans()" class="mt-4 text-xs text-sage-400 hover:text-sage-500 underline">Try Again</button>
            </div>
                `;
    }
}

function processRenewal(tier, amount, pubKey) {
    console.log("Processing renewal:", { tier, amount, pubKey });

    if (!pubKey) {
        alert("Payment system error: Public key is missing. Please contact support.");
        return;
    }

    if (typeof FlutterwaveCheckout !== 'undefined') {
        try {
            FlutterwaveCheckout({
                public_key: pubKey,
                tx_ref: "1W_" + Math.floor((Math.random() * 1000000000) + 1),
                amount: amount,
                currency: "USD",
                payment_options: "card, account, ussd",
                customer: {
                    email: userData?.user?.email || "",
                    phone_number: userData?.user?.phone || "",
                    name: (userData?.user?.first_name || "") + " " + (userData?.user?.last_name || ""),
                },
                customizations: {
                    title: "1wellness Plan Renewal",
                    description: "Payment for " + tier + " plan",
                    logo: "https://1wellness.club/assets/img/logo.png",
                },
                callback: async function (data) {
                    console.log("Flutterwave callback:", data);
                    if (data.status === "successful") {
                        // Verify on backend
                        const res = await fetch('/member/api/member_actions.php', {
                            method: 'POST',
                            body: new URLSearchParams({
                                action: 'verify_renewal',
                                transaction_id: data.transaction_id,
                                tx_ref: data.tx_ref,
                                tier: tier
                            }),
                            credentials: 'include'
                        });
                        const result = await res.json();
                        if (result.success) {
                            alert("Subscription renewed successfully!");
                            window.location.reload();
                        } else {
                            alert("Payment successful but verification failed: " + result.error);
                        }
                    }
                },
                onclose: function () {
                    console.log("Payment modal closed");
                }
            });
        } catch (err) {
            console.error("Flutterwave error:", err);
            alert("Error launching payment: " + err.message);
        }
    } else {
        console.log("Loading Flutterwave script...");
        const script = document.createElement('script');
        script.src = "https://checkout.flutterwave.com/v3.js";
        script.onload = () => {
            console.log("Flutterwave script loaded.");
            processRenewal(tier, amount, pubKey);
        };
        script.onerror = () => {
            alert("Failed to load payment gateway. Please check your internet connection.");
        };
        document.head.appendChild(script);
    }
}

// Onboarding / Missing Data Logic
let boardingStep = 1;
const totalSteps = 3;

// Pre-fill PCOS type from localStorage if available (from assessment results)
function prefillPCOSTypeFromStorage() {
    const savedPCOSType = localStorage.getItem('1w_pcos_type');
    if (savedPCOSType) {
        // Find the radio button with matching value and check it
        const radios = document.querySelectorAll('input[name="pcos_type"]');
        radios.forEach(radio => {
            if (radio.value === savedPCOSType) {
                radio.checked = true;
                // Trigger visual update for the label styling
                radio.dispatchEvent(new Event('change'));
            }
        });
        console.log('Pre-filled PCOS type from assessment:', savedPCOSType);
    }
}

// Call prefill when onboarding modal becomes visible
const onboardingObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.target.id === 'onboardingModal' && !mutation.target.classList.contains('hidden')) {
            prefillPCOSTypeFromStorage();
        }
    });
});

// Start observing once DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('onboardingModal');
    if (modal) {
        onboardingObserver.observe(modal, { attributes: true, attributeFilter: ['class'] });
        // Also prefill immediately if modal is already visible
        if (!modal.classList.contains('hidden')) {
            prefillPCOSTypeFromStorage();
        }
    }
});

function nextBoardingStep() {
    // Validation
    const currentStepEl = document.querySelector(`.boarding-step[data-step="${boardingStep}"]`);
    const inputs = currentStepEl.querySelectorAll('input:not(.hidden), select, textarea');
    let isValid = true;
    let firstInvalid = null;

    inputs.forEach(input => {
        if (input.hasAttribute('required') && !input.value) {
            isValid = false;
            highlightError(input);
            if (!firstInvalid) firstInvalid = input;
        } else if (input.type === 'radio' && input.hasAttribute('required')) {
            // Check if group has value
            const group = currentStepEl.querySelectorAll(`input[name="${input.name}"]:checked`);
            if (group.length === 0) {
                isValid = false;
                if (!firstInvalid) firstInvalid = input;
            }
        }
    });

    if (!isValid) {
        showInlineError("Please provide all required information to continue.");
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus({ preventScroll: true });
        }
        return;
    }

    hideInlineError();

    if (boardingStep < totalSteps) {
        boardingStep++;
        updateBoardingView();
    }
}

function highlightError(input) {
    input.classList.add('border-red-400', 'bg-red-50');
    // Remove error style on input
    input.addEventListener('input', () => {
        input.classList.remove('border-red-400', 'bg-red-50');
    }, { once: true });
}

function showInlineError(msg) {
    const el = document.getElementById('onboardingError');
    if (el) {
        el.classList.remove('hidden');
        el.innerHTML = `<i data-lucide="alert-circle" class="w-4 h-4 shrink-0 mt-0.5"></i> <span class="leading-tight">${msg}</span>`;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function hideInlineError() {
    const el = document.getElementById('onboardingError');
    if (el) el.classList.add('hidden');
}

function prevBoardingStep() {
    if (boardingStep > 1) {
        boardingStep--;
        updateBoardingView();
    }
}

function updateBoardingView() {
    document.querySelectorAll('.boarding-step').forEach(s => s.classList.add('hidden'));
    const currentStep = document.querySelector(`[data-step="${boardingStep}"]`);
    if (currentStep) currentStep.classList.remove('hidden');

    // Progress Bar
    const progress = (boardingStep / totalSteps) * 100;
    const progressBar = document.getElementById('boardingProgress');
    if (progressBar) progressBar.style.width = `${progress}% `;

    // Step Titles
    const titles = ["Vital Metrics", "Hormonal Profile", "Personalized Needs"];
    const titleEl = document.getElementById('boardingStepTitle');
    if (titleEl) titleEl.textContent = titles[boardingStep - 1];

    // Button Logic
    const prevBtn = document.querySelector('.boarding-prev-btn');
    const nextBtn = document.querySelector('.boarding-next-btn');
    const submitBtn = document.querySelector('.boarding-submit-btn');

    // Prev Button
    if (boardingStep > 1) {
        if (prevBtn) prevBtn.classList.remove('hidden');
    } else {
        if (prevBtn) prevBtn.classList.add('hidden');
    }

    // Next vs Submit
    if (boardingStep === totalSteps) {
        if (nextBtn) nextBtn.classList.add('hidden');
        if (submitBtn) submitBtn.classList.remove('hidden');
    } else {
        if (nextBtn) nextBtn.classList.remove('hidden');
        if (submitBtn) submitBtn.classList.add('hidden');
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function toggleHeightUnit() {
    const selectedUnitEl = document.querySelector('input[name="h_unit"]:checked');
    if (!selectedUnitEl) return;
    const unit = selectedUnitEl.value;
    const cmRow = document.getElementById('height_cm_row');
    const ftRow = document.getElementById('height_ft_row');

    if (unit === 'cm') {
        if (cmRow) cmRow.classList.remove('hidden');
        if (ftRow) ftRow.classList.add('hidden');
    } else {
        if (cmRow) cmRow.classList.add('hidden');
        if (ftRow) ftRow.classList.remove('hidden');
    }
}

async function saveOnboarding(e) {
    e.preventDefault();
    hideInlineError();

    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<div class="flex items-center gap-2"><span>Sculpting...</span><div class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></div></div>';
    btn.disabled = true;

    const formData = new FormData(e.target);

    // Height Conversion
    const unit = formData.get('h_unit');
    let heightCm = 0;
    if (unit === 'cm') {
        heightCm = formData.get('height_cm');
    } else {
        const ft = parseInt(formData.get('height_ft') || 0);
        const inch = parseInt(formData.get('height_in') || 0);
        heightCm = Math.round((ft * 30.48) + (inch * 2.54));
    }
    formData.set('height', heightCm);
    formData.append('action', 'update_body_data');

    // Timeout controller
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 20000); // 20s timeout

    try {
        const res = await fetch('/member/api/member_actions.php', {
            method: 'POST',
            body: formData,
            signal: controller.signal,
            credentials: 'include'
        });
        clearTimeout(timeoutId);

        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error("Server returned non-JSON:", text);
            throw new Error('Server error: Invalid response format.');
        }

        if (data.success) {
            showSculpting();
            const onboardingModal = document.getElementById('onboardingModal');
            if (onboardingModal) onboardingModal.classList.add('hidden');

            // Run generation in background
            try {
                await triggerProactiveGen();
            } catch (genErr) {
                console.warn("Generation warning:", genErr);
            }

            await fetchData();
            const sculptingOverlay = document.getElementById('sculptingOverlay');
            if (sculptingOverlay) sculptingOverlay.classList.add('hidden');
        } else {
            throw new Error(data.error || 'Unknown error occurred.');
        }
    } catch (err) {
        console.error(err);
        let msg = err.message;
        if (err.name === 'AbortError') {
            msg = "Connection timed out. Please check your internet.";
        } else if (msg.includes('Failed to fetch')) {
            msg = "Network error. Please check your connection.";
        }
        showInlineError(msg);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// ----------------------------------------------------
// RECIPE MODAL LOGIC (Global)
// ----------------------------------------------------
function openRecipeModal(mealType) {
    const meal = userData?.plan?.meals?.[mealType];
    if (!meal) return;

    const titleEl = document.getElementById('recipeModalTitle');
    if (titleEl) titleEl.textContent = meal.name;

    const content = document.getElementById('recipeModalContent');
    if (content) {
        content.innerHTML = `
            <!-- Ingredients Section -->
            <div class="bg-sage-50/50 rounded-2xl p-5 border border-sage-100">
                <h5 class="uppercase tracking-widest text-[0.65rem] font-bold text-sage-400 mb-4 flex items-center gap-2">
                    <i data-lucide="shopping-bag" class="w-3 h-3"></i> Ingredients
                </h5>
                <ul class="space-y-3">
                    ${meal.ingredients.map(ing => `
                        <li class="flex justify-between items-start text-sm text-sage-700 border-b border-sage-100/50 pb-2 last:border-0 last:pb-0">
                            <span class="font-medium pr-2">${ing.item}</span>
                            <span class="text-sage-500 text-xs whitespace-nowrap bg-white px-2 py-1 rounded-md border border-sage-100 shadow-sm font-number">${ing.quantity}</span>
                        </li>
                    `).join('')}
                </ul>
            </div>

            <!-- Instructions Section -->
            <div>
                <h5 class="uppercase tracking-widest text-[0.65rem] font-bold text-sage-400 mb-4 flex items-center gap-2" >
                    <i data-lucide="chef-hat" class="w-3 h-3"></i> Preparation
                </h5>
                <ol class="space-y-6 relative border-l-2 border-sage-100 ml-3 pl-6">
                    ${(Array.isArray(meal.instructions) ? meal.instructions : []).map((step, idx) => `
                        <li class="relative">
                            <span class="absolute -left-[1.65rem] top-0 w-6 h-6 rounded-full bg-white border-2 border-sage-200 text-sage-400 text-[10px] font-bold flex items-center justify-center">
                                ${idx + 1}
                            </span>
                            <p class="text-sm text-sage-600 leading-relaxed font-light">${step}</p>
                        </li>
                    `).join('')}
                    ${(!meal.instructions || meal.instructions.length === 0) ? '<li class="text-sm text-sage-400 italic">No instructions available.</li>' : ''}
                </ol>
            </div>
        `;
    }

    const recipeModal = document.getElementById('recipeModal');
    if (recipeModal) {
        recipeModal.classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function closeRecipeModal() {
    const recipeModal = document.getElementById('recipeModal');
    if (recipeModal) recipeModal.classList.add('hidden');
}

// Initial session check
document.addEventListener('DOMContentLoaded', () => {
    initialize();
});
