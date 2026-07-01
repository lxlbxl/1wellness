<?php
$currentPage = basename($_SERVER['PHP_SELF']);

$navGroups = [
    'Analytics' => [
        'dashboard.php'         => ['Dashboard',     'fa-home'],
        'assessments.php'       => ['Assessments',   'fa-clipboard-list'],
        'sales.php'             => ['Sales',         'fa-chart-line'],
        'funnel-tracking.php'   => ['Funnels',       'fa-filter'],
    ],
    'Marketing' => [
        'experiments.php'       => ['Experiments',   'fa-flask'],
        'notifications.php'     => ['Notifications', 'fa-bell'],
    ],
    'Business' => [
        'payment-integrity.php' => ['Payments',      'fa-credit-card'],
        'pricing.php'           => ['Pricing',       'fa-tag'],
        'webhooks.php'          => ['Webhooks',      'fa-plug'],
    ],
    'Content' => [
        'pcos-plan.php'         => ['PCOS Plan',     'fa-seedling'],
    ],
    'System' => [
        'ai-oversight.php'      => ['AI Oversight',  'fa-brain'],
        'audit-logs.php'        => ['Audit Logs',    'fa-scroll'],
        'setup-guide.php'       => ['Setup Guide',   'fa-map-signs'],
    ],
];

// Bottom bar (mobile) — 4 primary + "More"
$bottomItems = [
    'dashboard.php'       => ['Home',    'fa-home'],
    'sales.php'           => ['Sales',   'fa-chart-line'],
    'notifications.php'   => ['Alerts',  'fa-bell'],
    'experiments.php'     => ['Tests',   'fa-flask'],
];

// Flat map for current page title
$flatAll = [];
foreach ($navGroups as $_ => $items) {
    foreach ($items as $file => [$lbl]) { $flatAll[$file] = $lbl; }
}
$flatAll['settings.php'] = 'Settings';
$pageLabel = $flatAll[$currentPage]
    ?? ucwords(str_replace(['.php', '-'], ['', ' '], $currentPage));
?>

<!-- ══════════════════════════════════════════════════════
     SIDEBAR
══════════════════════════════════════════════════════ -->
<aside id="sb"
       :style="sidebarStyle()"
       :class="sidebarClass()"
       class="flex flex-col bg-[#2C3E35]"
       style="overflow: hidden;">

    <!-- ── Logo + collapse toggle ── -->
    <div class="flex items-center h-14 px-3 flex-shrink-0" style="border-bottom: 1px solid rgba(255,255,255,.08);">
        <a href="dashboard.php" class="flex items-center gap-2.5 flex-1 min-w-0 overflow-hidden group">
            <img src="/images/brand/logo-icon-sm-white.png" alt="1wellness" class="h-8 w-auto flex-shrink-0 group-hover:scale-105 transition-transform">
            <span class="sb-label font-serif font-semibold text-white truncate" style="font-size: 15px; line-height: 1;">
                1wellness
            </span>
        </a>

        <!-- Desktop: collapse arrow -->
        <button @click="toggleDesktop()"
                class="hidden md:flex w-7 h-7 rounded-lg items-center justify-center transition-colors flex-shrink-0"
                style="color: rgba(255,255,255,.35);"
                @mouseenter="$el.style.background='rgba(255,255,255,.08)'; $el.style.color='rgba(255,255,255,.9)'"
                @mouseleave="$el.style.background=''; $el.style.color='rgba(255,255,255,.35)'"
                title="Toggle sidebar">
            <i class="fas fa-chevron-left transition-transform duration-200"
               :class="sidebarCollapsed ? 'rotate-180' : ''"
               style="font-size: 10px;"></i>
        </button>

        <!-- Mobile: close ✕ -->
        <button @click="closeMobile()"
                class="md:hidden w-7 h-7 flex items-center justify-center rounded-lg transition-colors flex-shrink-0"
                style="color: rgba(255,255,255,.4);"
                @mouseenter="$el.style.background='rgba(255,255,255,.08)'; $el.style.color='rgba(255,255,255,.9)'"
                @mouseleave="$el.style.background=''; $el.style.color='rgba(255,255,255,.4)'">
            <i class="fas fa-times" style="font-size: 12px;"></i>
        </button>
    </div>

    <!-- ── Scrollable nav ── -->
    <nav class="sb-scroll flex-1 overflow-y-auto overflow-x-hidden py-2 px-2" style="padding-bottom: 4px;">

        <?php foreach ($navGroups as $groupLabel => $items): ?>
        <div class="mb-1">
            <!-- Group label (hidden when collapsed via CSS) -->
            <p class="sb-grp px-3 pt-3 pb-1"
               style="font-size: 10px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.3);">
                <?php echo $groupLabel; ?>
            </p>

            <?php foreach ($items as $file => [$label, $icon]):
                $active = ($currentPage === $file); ?>
            <a href="<?php echo $file; ?>"
               class="sb-item relative flex items-center gap-3 rounded-xl my-0.5 transition-colors duration-150"
               style="padding: 9px 12px; color: <?php echo $active ? '#fff' : 'rgba(255,255,255,.58)'; ?>; background: <?php echo $active ? 'rgba(255,255,255,.12)' : 'transparent'; ?>;"
               <?php if ($active): ?>aria-current="page"<?php endif; ?>
               @mouseenter="if (!<?php echo $active ? 'true' : 'false'; ?>) { $el.style.background='rgba(255,255,255,.07)'; $el.style.color='rgba(255,255,255,.9)'; }"
               @mouseleave="if (!<?php echo $active ? 'true' : 'false'; ?>) { $el.style.background='transparent'; $el.style.color='rgba(255,255,255,.58)'; }">

                <!-- Active left bar -->
                <?php if ($active): ?>
                <span class="absolute left-0 top-1/2 -translate-y-1/2 rounded-r-full bg-[#D97757]"
                      style="width: 3px; height: 20px;"></span>
                <?php endif; ?>

                <i class="fas <?php echo $icon; ?> flex-shrink-0 text-center"
                   style="width: 18px; font-size: 14px; <?php echo $active ? 'color: #D97757;' : ''; ?>"></i>
                <span class="sb-label text-sm font-medium leading-none truncate"><?php echo $label; ?></span>

                <!-- Tooltip (visible only when sidebar collapsed on desktop) -->
                <span class="sb-tip"><?php echo $label; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>

    <!-- ── Bottom: Settings + user/logout ── -->
    <div class="flex-shrink-0 px-2 py-2 space-y-0.5" style="border-top: 1px solid rgba(255,255,255,.08);">

        <!-- Settings link -->
        <?php $isSett = ($currentPage === 'settings.php'); ?>
        <a href="settings.php"
           class="sb-usr relative flex items-center gap-3 rounded-xl transition-colors duration-150"
           style="padding: 9px 12px; color: <?php echo $isSett ? '#fff' : 'rgba(255,255,255,.55)'; ?>; background: <?php echo $isSett ? 'rgba(255,255,255,.12)' : 'transparent'; ?>;"
           @mouseenter="if (!<?php echo $isSett ? 'true' : 'false'; ?>) { $el.style.background='rgba(255,255,255,.07)'; $el.style.color='rgba(255,255,255,.9)'; }"
           @mouseleave="if (!<?php echo $isSett ? 'true' : 'false'; ?>) { $el.style.background='transparent'; $el.style.color='rgba(255,255,255,.55)'; }">
            <?php if ($isSett): ?>
            <span class="absolute left-0 top-1/2 -translate-y-1/2 rounded-r-full bg-[#D97757]" style="width: 3px; height: 20px;"></span>
            <?php endif; ?>
            <i class="fas fa-cog flex-shrink-0 text-center" style="width: 18px; font-size: 14px; <?php echo $isSett ? 'color: #D97757;' : ''; ?>"></i>
            <span class="sb-label text-sm font-medium leading-none">Settings</span>
            <span class="sb-tip">Settings</span>
        </a>

        <!-- Divider -->
        <div class="sb-sep mx-3 my-1" style="height: 1px; background: rgba(255,255,255,.07);"></div>

        <!-- User row -->
        <div class="sb-usr relative flex items-center gap-2.5 rounded-xl" style="padding: 8px 12px;">
            <div class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center"
                 style="background: rgba(217,119,87,.2);">
                <i class="fas fa-user text-[#D97757]" style="font-size: 11px;"></i>
            </div>
            <div class="sb-user-info flex-1 min-w-0">
                <p class="font-semibold text-white truncate" style="font-size: 13px; line-height: 1.2;">
                    <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                </p>
                <p style="font-size: 11px; color: rgba(255,255,255,.3); line-height: 1.2;">Administrator</p>
            </div>
            <a href="logout.php"
               class="sb-label flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-lg transition-colors"
               style="color: rgba(255,255,255,.3);"
               title="Sign out"
               @mouseenter="$el.style.color='#D97757'; $el.style.background='rgba(255,255,255,.07)';"
               @mouseleave="$el.style.color='rgba(255,255,255,.3)'; $el.style.background='';">
                <i class="fas fa-sign-out-alt" style="font-size: 13px;"></i>
            </a>
            <!-- Logout tooltip when only icon is visible -->
            <span class="sb-tip">Sign out</span>
        </div>
    </div>
</aside>


<!-- ══════════════════════════════════════════════════════
     MOBILE OVERLAY (tap to close)
══════════════════════════════════════════════════════ -->
<div x-show="sidebarOpen && isMobile"
     x-cloak
     @click="closeMobile()"
     x-transition:enter="transition-opacity duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-30 md:hidden"
     style="background: rgba(0,0,0,.48); backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px);"></div>


<!-- ══════════════════════════════════════════════════════
     TOP BAR
══════════════════════════════════════════════════════ -->
<header id="tb"
        :style="topbarStyle()"
        class="flex items-center gap-3 px-4"
        style="background: rgba(253,252,248,.92); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid #EAEAE5;">

    <!-- Hamburger (mobile) / collapse toggle (desktop via toggleSidebar) -->
    <button @click="toggleSidebar()"
            class="flex items-center justify-center w-9 h-9 rounded-xl transition-colors flex-shrink-0"
            style="color: #6B7C70;"
            @mouseenter="$el.style.background='#F2F4F1'; $el.style.color='#2C3E35';"
            @mouseleave="$el.style.background=''; $el.style.color='#6B7C70';">
        <i class="fas fa-bars" style="font-size: 14px;"></i>
    </button>

    <!-- Current page label -->
    <div class="flex-1 min-w-0">
        <p class="font-serif font-semibold text-[#2C3E35] truncate" style="font-size: 15px; line-height: 1.3;">
            <?php echo htmlspecialchars($pageLabel); ?>
        </p>
    </div>

    <!-- Right: notifications + user menu -->
    <div class="flex items-center gap-1 flex-shrink-0">

        <!-- Notifications shortcut -->
        <a href="notifications.php"
           class="w-9 h-9 flex items-center justify-center rounded-xl transition-colors"
           style="color: #6B7C70;"
           @mouseenter="$el.style.background='#F2F4F1'; $el.style.color='#2C3E35';"
           @mouseleave="$el.style.background=''; $el.style.color='#6B7C70';"
           title="Notifications">
            <i class="fas fa-bell" style="font-size: 14px;"></i>
        </a>

        <!-- User dropdown -->
        <div class="relative" @click.away="userMenuOpen = false">
            <button @click="userMenuOpen = !userMenuOpen"
                    class="flex items-center gap-2 rounded-xl transition-colors"
                    style="padding: 6px 10px 6px 6px;"
                    @mouseenter="$el.style.background='#F2F4F1';"
                    @mouseleave="$el.style.background='';">
                <div class="w-7 h-7 rounded-full bg-[#E3E8E1] flex items-center justify-center text-[#2C3E35] flex-shrink-0">
                    <i class="fas fa-user" style="font-size: 11px;"></i>
                </div>
                <span class="hidden sm:block text-sm font-medium text-[#2C3E35]">
                    <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                </span>
                <i class="fas fa-chevron-down hidden sm:block text-[#6B7C70] transition-transform duration-150"
                   :class="userMenuOpen ? 'rotate-180' : ''"
                   style="font-size: 9px;"></i>
            </button>

            <!-- Dropdown panel -->
            <div x-show="userMenuOpen"
                 x-cloak
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-1"
                 class="absolute right-0 top-full mt-2 rounded-2xl shadow-xl border border-[#EAEAE5] bg-white py-1.5 z-50"
                 style="width: 176px;">
                <a href="settings.php"
                   class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-[#6B7C70] transition-colors"
                   @mouseenter="$el.style.background='#F2F4F1'; $el.style.color='#2C3E35';"
                   @mouseleave="$el.style.background=''; $el.style.color='#6B7C70';">
                    <i class="fas fa-cog w-4 text-center" style="font-size: 12px;"></i> Settings
                </a>
                <div class="my-1" style="height: 1px; background: #EAEAE5; margin: 4px 12px;"></div>
                <a href="logout.php"
                   class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-[#D97757] transition-colors"
                   @mouseenter="$el.style.background='#FDF1E8';"
                   @mouseleave="$el.style.background='';">
                    <i class="fas fa-sign-out-alt w-4 text-center" style="font-size: 12px;"></i> Sign out
                </a>
            </div>
        </div>
    </div>
</header>


<!-- ══════════════════════════════════════════════════════
     MOBILE BOTTOM NAV BAR
══════════════════════════════════════════════════════ -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-30 flex items-stretch bg-white"
     style="height: 64px; border-top: 1px solid #EAEAE5; padding-bottom: env(safe-area-inset-bottom, 0px);">

    <?php foreach ($bottomItems as $file => [$label, $icon]):
        $active = ($currentPage === $file); ?>
    <a href="<?php echo $file; ?>"
       class="relative flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors"
       style="color: <?php echo $active ? '#2C3E35' : '#9AABA0'; ?>;">

        <!-- Active indicator dot -->
        <?php if ($active): ?>
        <span class="absolute top-0 left-1/2 -translate-x-1/2 w-6 rounded-b-full bg-[#D97757]" style="height: 3px;"></span>
        <?php endif; ?>

        <i class="fas <?php echo $icon; ?>" style="font-size: 19px; <?php echo $active ? 'color: #D97757;' : ''; ?>"></i>
        <span style="font-size: 10px; font-weight: 600; letter-spacing: .01em;"><?php echo $label; ?></span>
    </a>
    <?php endforeach; ?>

    <!-- More → opens sidebar drawer -->
    <button @click="openMobile()"
            class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors"
            style="color: #9AABA0; background: none; border: none; cursor: pointer;"
            @mouseenter="$el.style.color='#2C3E35';"
            @mouseleave="$el.style.color='#9AABA0';">
        <i class="fas fa-th" style="font-size: 19px;"></i>
        <span style="font-size: 10px; font-weight: 600; letter-spacing: .01em;">More</span>
    </button>
</nav>
