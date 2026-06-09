<!-- Navigation Content (Alpine x-data handled here) -->
<nav class="bg-transparent" x-data="{ mobileMenuOpen: false, userMenuOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo & Desktop Nav -->
            <div class="flex">
                <div class="flex-shrink-0 flex items-center">
                    <a href="dashboard.php" class="text-xl font-bold text-[#2C3E35] flex items-center">
                        <i class="fas fa-leaf text-[#D97757] mr-2"></i>
                        <span class="hidden sm:inline font-serif">1wellness Admin</span>
                        <span class="sm:hidden font-serif">1W</span>
                    </a>
                </div>
                <div class="hidden md:ml-6 md:flex md:space-x-4 items-center">
                    <?php
                    $currentPage = basename($_SERVER['PHP_SELF']);
                    $navItems = [
                        'dashboard.php' => 'Dashboard',
                        'assessments.php' => 'Assessments',
                        'sales.php' => 'Sales',
                        'funnel-tracking.php' => 'Funnels',
                        'pricing.php' => 'Pricing',
                        'pcos-plan.php' => '🌿 PCOS Plan',
                        'ai-oversight.php' => 'AI Oversight',
                        'audit-logs.php' => 'Logs'
                    ];
                    foreach ($navItems as $file => $label):
                        $isActive = ($currentPage === $file);
                        $class = $isActive
                            ? 'bg-[#E3E8E1] text-[#2C3E35]'
                            : 'text-[#6B7C70] hover:bg-[#F2F4F1] hover:text-[#2C3E35]';
                        ?>
                        <a href="<?php echo $file; ?>"
                            class="<?php echo $class; ?> px-3 py-2 rounded-full text-sm font-medium transition-colors">
                            <?php echo $label; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Side (User Menu) -->
            <div class="hidden md:ml-6 md:flex md:items-center">
                <div class="ml-3 relative">
                    <div>
                        <button @click="userMenuOpen = !userMenuOpen" @click.away="userMenuOpen = false" type="button"
                            class="max-w-xs flex items-center text-sm rounded-full focus:outline-none transition-transform hover:scale-105"
                            id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                            <span class="sr-only">Open user menu</span>
                            <div
                                class="h-8 w-8 rounded-full bg-[#E3E8E1] flex items-center justify-center text-[#2C3E35]">
                                <i class="fas fa-user"></i>
                            </div>
                            <span
                                class="ml-2 text-[#2C3E35] font-medium font-serif"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                            <i class="fas fa-chevron-down ml-2 text-[#6B7C70] text-xs"></i>
                        </button>
                    </div>

                    <div x-show="userMenuOpen" x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        class="origin-top-right absolute right-0 mt-2 w-48 rounded-xl shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50 border border-[#EAEAE5]"
                        role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1"
                        style="display: none;">
                        <a href="profile.php" class="block px-4 py-2 text-sm text-[#6B7C70] hover:bg-[#F2F4F1]"
                            role="menuitem">
                            <i class="fas fa-id-card mr-2 w-4 text-center"></i> Profile
                        </a>
                        <a href="settings.php" class="block px-4 py-2 text-sm text-[#6B7C70] hover:bg-[#F2F4F1]"
                            role="menuitem">
                            <i class="fas fa-cog mr-2 w-4 text-center"></i> Settings
                        </a>
                        <div class="border-t border-[#EAEAE5] my-1"></div>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-[#D97757] hover:bg-[#FDF1E8]"
                            role="menuitem">
                            <i class="fas fa-sign-out-alt mr-2 w-4 text-center"></i> Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mobile menu button -->
            <div class="-mr-2 flex items-center md:hidden">
                <button @click="mobileMenuOpen = !mobileMenuOpen" type="button"
                    class="inline-flex items-center justify-center p-2 rounded-md text-[#6B7C70] hover:text-[#2C3E35] hover:bg-[#F2F4F1] focus:outline-none"
                    aria-controls="mobile-menu" aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <i class="fas" :class="mobileMenuOpen ? 'fa-times' : 'fa-bars'"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div class="md:hidden border-t border-[#EAEAE5] bg-white" id="mobile-menu" x-show="mobileMenuOpen"
        style="display: none;">
        <div class="pt-2 pb-3 space-y-1 px-2">
            <?php
            foreach ($navItems as $file => $label):
                $isActive = ($currentPage === $file);
                $class = $isActive
                    ? 'bg-[#E3E8E1] text-[#2C3E35]'
                    : 'text-[#6B7C70] hover:bg-[#F2F4F1] hover:text-[#2C3E35]';
                ?>
                <a href="<?php echo $file; ?>"
                    class="<?php echo $class; ?> block px-3 py-2 rounded-md text-base font-medium">
                    <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="pt-4 pb-4 border-t border-[#EAEAE5]">
            <div class="flex items-center px-4">
                <div class="flex-shrink-0">
                    <div class="h-10 w-10 rounded-full bg-[#E3E8E1] flex items-center justify-center text-[#2C3E35]">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="ml-3">
                    <div class="text-base font-medium text-[#2C3E35] font-serif">
                        <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                    </div>
                    <div class="text-sm font-medium text-[#6B7C70]">Administrator</div>
                </div>
            </div>
            <div class="mt-3 space-y-1 px-2">
                <a href="settings.php"
                    class="block px-3 py-2 rounded-md text-base font-medium text-[#6B7C70] hover:text-[#2C3E35] hover:bg-[#F2F4F1]">
                    <i class="fas fa-cog mr-2"></i> Settings
                </a>
                <a href="logout.php"
                    class="block px-3 py-2 rounded-md text-base font-medium text-[#D97757] hover:bg-[#FDF1E8]">
                    <i class="fas fa-sign-out-alt mr-2"></i> Sign out
                </a>
            </div>
        </div>
    </div>
</nav>