<?php
session_start();
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | 1wellness</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/images/brand/favicon-32.png">
    <link rel="shortcut icon" href="/images/brand/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        sage: {
                            50: '#F2F4F1',
                            100: '#E3E8E1',
                            200: '#C7D1C3',
                            300: '#A4B4A6',
                            400: '#6B7C70',
                            500: '#2C3E35',
                            600: '#1A2620',
                        },
                        coral: {
                            400: '#D97757',
                            500: '#BF6649',
                        },
                        cream: {
                            50: '#FDFCF8',
                            100: '#F2e6d8',
                        }
                    },
                    fontFamily: {
                        serif: ['"Playfair Display"', 'serif'],
                        sans: ['"Inter"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @layer base {
            body {
                background: #FDFCF8;
                color: #2C3E35;
                font-family: "Inter", sans-serif;
                -webkit-font-smoothing: antialiased;
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .nav-link.active {
            background: #2C3E35;
            color: white;
            box-shadow: 0 10px 15px -3px rgba(44, 62, 53, 0.2);
            transform: translateX(4px);
        }

        .nav-link.active i {
            color: white;
        }

        .view-section {
            display: none;
        }

        .view-section.active {
            display: block;
        }

        .stat-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #C7D1C3;
            border-radius: 99px;
        }
    </style>
</head>

<body class="min-h-screen">
    <div id="app" class="hidden">
        <!-- Sidebar - Desktop -->
        <aside
            class="hidden md:flex flex-col fixed left-0 top-0 bottom-0 w-64 bg-white border-r border-sage-100 p-8 z-50">
            <div class="flex items-center gap-3 mb-12 pl-2">
                <img src="/images/brand/logo-icon-sm.png" alt="1wellness" class="w-10 h-10 object-contain">
                <span class="font-serif text-2xl text-sage-600 tracking-tight">1wellness</span>
            </div>

            <nav class="flex-1 space-y-2">
                <a href="#" onclick="switchView('dashboard')"
                    class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all duration-300 group text-sage-400 hover:text-sage-600 hover:bg-sage-50 active"
                    data-view="dashboard">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    <span class="font-medium">Overview</span>
                </a>
                <a href="#" onclick="switchView('assessments')"
                    class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all duration-300 group text-sage-400 hover:text-sage-600 hover:bg-sage-50"
                    data-view="assessments">
                    <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                    <span class="font-medium">Assessments</span>
                </a>
                <a href="#" onclick="switchView('sales')"
                    class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all duration-300 group text-sage-400 hover:text-sage-600 hover:bg-sage-50"
                    data-view="sales">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    <span class="font-medium">Sales</span>
                </a>
                <a href="#" onclick="switchView('users')"
                    class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all duration-300 group text-sage-400 hover:text-sage-600 hover:bg-sage-50"
                    data-view="users">
                    <i data-lucide="users" class="w-5 h-5"></i>
                    <span class="font-medium">User Management</span>
                </a>
                <div class="pt-6 pb-2">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-sage-300 font-bold px-4">System</p>
                </div>
                <a href="#" onclick="switchView('settings')"
                    class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all duration-300 group text-sage-400 hover:text-sage-600 hover:bg-sage-50"
                    data-view="settings">
                    <i data-lucide="settings" class="w-5 h-5"></i>
                    <span class="font-medium">Settings</span>
                </a>
                <a href="#" onclick="switchView('audit')"
                    class="nav-link flex items-center gap-4 px-4 py-3 rounded-xl transition-all duration-300 group text-sage-400 hover:text-sage-600 hover:bg-sage-50"
                    data-view="audit">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                    <span class="font-medium">Security Logs</span>
                </a>
            </nav>

            <button onclick="handleLogout()"
                class="flex items-center gap-4 px-4 py-4 rounded-xl text-sage-400 hover:text-red-500 hover:bg-red-50 transition-all mt-auto group">
                <i data-lucide="log-out" class="w-5 h-5 group-hover:rotate-12 transition-transform"></i>
                <span class="font-medium">Logout Admin</span>
            </button>
        </aside>

        <!-- Main Content -->
        <div class="md:pl-64">
            <main class="max-w-7xl mx-auto p-6 md:p-12 min-h-screen">

                <!-- Dashboard View -->
                <section id="dashboard" class="view-section active animate-fade-in-up space-y-10">
                    <header class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                        <div>
                            <h1 class="text-4xl md:text-5xl font-serif text-sage-500 leading-tight">
                                Control <span class="italic font-normal opacity-60">Center</span>
                            </h1>
                            <p class="text-sage-400 mt-2">Welcome back, <span
                                    class="font-medium"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="px-4 py-2 bg-white rounded-full text-sm font-medium border border-sage-100 shadow-sm flex items-center gap-2 text-sage-500">
                                <i data-lucide="calendar" class="w-4 h-4"></i>
                                <span><?php echo date('M d, Y'); ?></span>
                            </div>
                        </div>
                    </header>

                    <!-- Primary Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div
                            class="stat-card bg-white p-8 rounded-[2rem] border border-sage-100 flex flex-col justify-between h-48">
                            <i data-lucide="users" class="w-8 h-8 text-sage-500 opacity-20"></i>
                            <div>
                                <p class="text-sage-400 text-sm font-medium uppercase tracking-wider">Total Users</p>
                                <h3 id="stat-users" class="text-4xl font-serif mt-1">0</h3>
                            </div>
                        </div>
                        <div
                            class="stat-card bg-sage-600 p-8 rounded-[2rem] text-white flex flex-col justify-between h-48 shadow-xl shadow-sage-600/20">
                            <i data-lucide="shopping-cart" class="w-8 h-8 opacity-40"></i>
                            <div>
                                <p class="text-sage-200 text-sm font-medium uppercase tracking-wider">Total Sales</p>
                                <h3 id="stat-sales" class="text-4xl font-serif mt-1">0</h3>
                            </div>
                        </div>
                        <div
                            class="stat-card bg-white p-8 rounded-[2rem] border border-sage-100 flex flex-col justify-between h-48">
                            <i data-lucide="mail" class="w-8 h-8 text-coral-400 opacity-20"></i>
                            <div>
                                <p class="text-sage-400 text-sm font-medium uppercase tracking-wider">Inquiries</p>
                                <h3 id="stat-contacts" class="text-4xl font-serif mt-1">0</h3>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                        <!-- Conversion Chart -->
                        <div
                            class="lg:col-span-2 bg-white rounded-[2.5rem] p-8 md:p-10 border border-sage-100 shadow-sm">
                            <div class="flex items-center justify-between mb-8">
                                <h2 class="font-serif text-2xl">Conversion Trends</h2>
                                <div class="flex gap-2">
                                    <span class="flex items-center gap-1.5 text-xs font-medium text-sage-400">
                                        <span class="w-2 h-2 rounded-full bg-coral-400"></span> PCOS
                                    </span>
                                    <span class="flex items-center gap-1.5 text-xs font-medium text-sage-400">
                                        <span class="w-2 h-2 rounded-full bg-sage-500"></span> Acne
                                    </span>
                                </div>
                            </div>
                            <div class="h-80 w-full">
                                <canvas id="conversionChart"></canvas>
                            </div>
                        </div>

                        <!-- Funnel Breakdown -->
                        <div class="bg-cream-100 rounded-[2.5rem] p-8 md:p-10 flex flex-col">
                            <h2 class="font-serif text-2xl mb-8">Funnels</h2>
                            <div class="space-y-8">
                                <div class="space-y-3">
                                    <div class="flex justify-between text-sm">
                                        <span class="font-medium text-sage-600 uppercase tracking-tighter">PCOS
                                            ASSESSMENT</span>
                                        <span id="rate-pcos" class="font-bold text-coral-400">0%</span>
                                    </div>
                                    <div class="h-2 w-full bg-white rounded-full overflow-hidden">
                                        <div id="progress-pcos" class="h-full bg-coral-400 transition-all duration-1000"
                                            style="width: 0%"></div>
                                    </div>
                                    <p class="text-[10px] text-sage-400"><span id="count-pcos">0</span> total
                                        assessments</p>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between text-sm">
                                        <span class="font-medium text-sage-600 uppercase tracking-tighter">ACNE
                                            PROTOCOL</span>
                                        <span id="rate-acne" class="font-bold text-sage-500">0%</span>
                                    </div>
                                    <div class="h-2 w-full bg-white rounded-full overflow-hidden">
                                        <div id="progress-acne" class="h-full bg-sage-500 transition-all duration-1000"
                                            style="width: 0%"></div>
                                    </div>
                                    <p class="text-[10px] text-sage-400"><span id="count-acne">0</span> total
                                        assessments</p>
                                </div>
                                <div class="space-y-3 pt-4">
                                    <button onclick="switchView('assessments')"
                                        class="w-full py-4 bg-white rounded-2xl text-sage-500 text-sm font-bold border border-sage-100 hover:bg-sage-50 transition-colors">
                                        Review All Data
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Log -->
                    <div class="bg-white rounded-[3rem] p-8 md:p-12 border border-sage-100 overflow-hidden">
                        <div class="flex items-center justify-between mb-10">
                            <div>
                                <h2 class="font-serif text-3xl">Recent Activity</h2>
                                <p class="text-sage-400 mt-1">Live system audit logs</p>
                            </div>
                            <button onclick="fetchData('dashboard')"
                                class="p-4 rounded-2xl hover:bg-sage-50 text-sage-400 transition-colors">
                                <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                            </button>
                        </div>
                        <ul id="recentActivityList" class="space-y-2">
                            <!-- Dynamic items -->
                        </ul>
                    </div>
                </section>

                <!-- Assessments View -->
                <section id="assessments" class="view-section animate-fade-in-up space-y-10">
                    <header class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                        <div>
                            <h1 class="text-4xl font-serif text-sage-500">Assessments</h1>
                            <p class="text-sage-400">Manage and track all health assessments.</p>
                        </div>
                    </header>

                    <!-- Filters -->
                    <div
                        class="bg-white rounded-3xl p-6 border border-sage-100 shadow-sm flex flex-wrap items-center gap-4">
                        <div class="flex-1 min-w-[200px] relative">
                            <i data-lucide="search"
                                class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-sage-300"></i>
                            <input type="text" id="filter-search" oninput="fetchData('assessments')"
                                placeholder="Search by name, email..."
                                class="w-full pl-12 pr-4 py-3 rounded-2xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm">
                        </div>
                        <select id="filter-funnel" onchange="fetchData('assessments')"
                            class="px-4 py-3 rounded-2xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm font-medium text-sage-600">
                            <option value="">All Funnels</option>
                            <option value="pcos">PCOS Assessment</option>
                            <option value="acne">Acne Protocol</option>
                            <option value="weight">Weight Loss</option>
                            <option value="egbon">Egbon</option>
                        </select>
                        <select id="filter-status" onchange="fetchData('assessments')"
                            class="px-4 py-3 rounded-2xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm font-medium text-sage-600">
                            <option value="">All Statuses</option>
                            <option value="completed">Completed</option>
                            <option value="follow_up">Follow Up</option>
                            <option value="pending">Pending</option>
                        </select>
                        <button onclick="fetchData('assessments')"
                            class="p-3 bg-sage-500 text-white rounded-2xl hover:bg-sage-600 transition-colors">
                            <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <!-- Table -->
                    <div class="bg-white rounded-[2.5rem] border border-sage-100 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="bg-sage-50/50">
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Customer</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Funnel</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Status</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Date</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400 text-right">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody id="assessments-list" class="divide-y divide-sage-50">
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Sales View -->
                <section id="sales" class="view-section animate-fade-in-up space-y-10">
                    <header class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                        <div>
                            <h1 class="text-4xl font-serif text-sage-500">Sales</h1>
                            <p class="text-sage-400">Track revenue and customer transactions.</p>
                        </div>
                    </header>

                    <!-- Sales Summary & Filters -->
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                        <div
                            class="lg:col-span-3 bg-white rounded-3xl p-6 border border-sage-100 shadow-sm flex flex-wrap items-center gap-4">
                            <div class="flex-1 min-w-[200px] relative">
                                <i data-lucide="search"
                                    class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-sage-300"></i>
                                <input type="text" id="sales-search" oninput="fetchData('sales')"
                                    placeholder="Search transactions..."
                                    class="w-full pl-12 pr-4 py-3 rounded-2xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm">
                            </div>
                            <select id="sales-status" onchange="fetchData('sales')"
                                class="px-4 py-3 rounded-2xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm font-medium text-sage-600">
                                <option value="">All Statuses</option>
                                <option value="completed">Completed</option>
                                <option value="pending">Pending</option>
                                <option value="failed">Failed</option>
                            </select>
                            <button onclick="fetchData('sales')"
                                class="p-3 bg-sage-500 text-white rounded-2xl hover:bg-sage-600 transition-colors">
                                <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                            </button>
                        </div>
                        <div class="bg-sage-500 rounded-3xl p-6 text-white flex flex-col justify-center">
                            <span class="text-[10px] font-bold uppercase tracking-widest opacity-60">Total
                                Revenue</span>
                            <span class="text-2xl font-serif mt-1" id="sales-total-revenue">$0.00</span>
                        </div>
                    </div>

                    <!-- Sales Table -->
                    <div class="bg-white rounded-[2.5rem] border border-sage-100 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="bg-sage-50/50">
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Transaction</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Customer</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Amount</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Status</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400 text-right">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody id="sales-list" class="divide-y divide-sage-50">
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Funnel Tracking View -->
                <section id="tracking" class="view-section animate-fade-in-up space-y-10">
                    <header class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                        <div>
                            <h1 class="text-4xl font-serif text-sage-500">Funnel Analytics</h1>
                            <p class="text-sage-400">Deep dive into user behavioral paths.</p>
                        </div>
                    </header>

                    <!-- Filters -->
                    <div
                        class="bg-white rounded-3xl p-6 border border-sage-100 shadow-sm flex flex-wrap items-center gap-4">
                        <select id="tracking-funnel" onchange="fetchData('tracking')"
                            class="px-4 py-3 rounded-2xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm font-medium text-sage-600">
                            <option value="">All Funnels</option>
                            <option value="pcos">PCOS</option>
                            <option value="acne">Acne</option>
                            <option value="weight">Weight</option>
                            <option value="egbon">Egbon</option>
                        </select>
                        <select id="tracking-event" onchange="fetchData('tracking')"
                            class="px-4 py-3 rounded-2xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm font-medium text-sage-600">
                            <option value="">All Events</option>
                            <option value="view">Page View</option>
                            <option value="conversion">Conversion</option>
                            <option value="PurchaseIntent">Purchase Intent</option>
                            <option value="SalesVisit">Sales Visit</option>
                        </select>
                        <button onclick="fetchData('tracking')"
                            class="p-3 bg-sage-500 text-white rounded-2xl hover:bg-sage-600 transition-colors">
                            <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <!-- Tracking Table -->
                    <div class="bg-white rounded-[2.5rem] border border-sage-100 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="bg-sage-50/50">
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Time</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Funnel</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Step</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Event</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            User</th>
                                    </tr>
                                </thead>
                                <tbody id="tracking-list" class="divide-y divide-sage-50">
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Placeholder Views -->
                <!-- Users Management View -->
                <section id="users" class="view-section animate-fade-in-up space-y-10">
                    <header class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                        <div>
                            <h1 class="text-4xl font-serif text-sage-500">User Management</h1>
                            <p class="text-sage-400">Control member access and profiles.</p>
                        </div>
                    </header>

                    <!-- Filters -->
                    <div
                        class="bg-white rounded-3xl p-6 border border-sage-100 shadow-sm flex flex-wrap items-center gap-4">
                        <div class="flex-1 min-w-[200px] relative">
                            <i data-lucide="search"
                                class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-sage-300"></i>
                            <input type="text" id="users-search" oninput="fetchData('users')"
                                placeholder="Search members..."
                                class="w-full pl-12 pr-4 py-3 rounded-2xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm">
                        </div>
                        <button onclick="fetchData('users')"
                            class="p-3 bg-sage-500 text-white rounded-2xl hover:bg-sage-600 transition-colors">
                            <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <!-- Users Table -->
                    <div class="bg-white rounded-[2.5rem] border border-sage-100 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="bg-sage-50/50">
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            User Profile</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Contact Details</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Join Date</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400 text-right">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody id="users-list" class="divide-y divide-sage-50">
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Audit Logs View -->
                <section id="audit" class="view-section animate-fade-in-up space-y-10">
                    <header class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                        <div>
                            <h1 class="text-4xl font-serif text-sage-500">Audit Trail</h1>
                            <p class="text-sage-400">Security and administrative activity logs.</p>
                        </div>
                    </header>

                    <!-- Audit Table -->
                    <div class="bg-white rounded-[2.5rem] border border-sage-100 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="bg-sage-50/50">
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Timestamp</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Admin</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Action</th>
                                        <th
                                            class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-sage-400">
                                            Details</th>
                                    </tr>
                                </thead>
                                <tbody id="audit-list" class="divide-y divide-sage-50">
                                    <!-- Dynamic content -->
                                </tbody>
                                <!-- System Settings View -->
                                <section id="settings" class="view-section animate-fade-in-up space-y-10">
                                    <header>
                                        <h1 class="text-4xl font-serif text-sage-500">Settings</h1>
                                        <p class="text-sage-400">Manage global site configurations.</p>
                                    </header>
                                    <div class="bg-white rounded-[2rem] p-12 border border-sage-100 text-center py-20">
                                        <div
                                            class="w-20 h-20 bg-sage-50 rounded-3xl mx-auto flex items-center justify-center text-sage-400 mb-6">
                                            <i data-lucide="settings" class="w-10 h-10"></i>
                                        </div>
                                        <h3 class="text-2xl font-serif text-sage-600">Global Configuration</h3>
                                        <p class="text-sage-400 mt-2 max-w-sm mx-auto">Configure payment gateways, email
                                            templates, and administrative access.</p>
                                    </div>
                                </section>
            </main>
        </div>
    </div>

    <!-- User Edit/Create Modal -->
    <div id="user-modal" class="fixed inset-0 z-50 invisible opacity-0 transition-all duration-300">
        <div class="absolute inset-0 bg-sage-900/40 backdrop-blur-sm" onclick="closeUserModal()"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-out flex flex-col"
            id="user-modal-container">
            <div class="px-8 py-6 border-b border-sage-100 flex items-center justify-between">
                <h2 id="user-modal-title" class="text-2xl font-serif text-sage-500">Edit Member</h2>
                <button onclick="closeUserModal()" class="p-2 hover:bg-sage-50 rounded-full transition-colors">
                    <i data-lucide="x" class="w-6 h-6 text-sage-400"></i>
                </button>
            </div>
            <form id="user-form" class="flex-1 overflow-y-auto p-8 space-y-6">
                <input type="hidden" id="user-id">
                <div class="space-y-2">
                    <label class="text-xs font-bold uppercase tracking-widest text-sage-300">Full Name</label>
                    <input type="text" id="user-name" required
                        class="w-full px-4 py-3 rounded-2xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm">
                </div>
                <div class="space-y-2">
                    <label class="text-xs font-bold uppercase tracking-widest text-sage-300">Email Address</label>
                    <input type="email" id="user-email" required
                        class="w-full px-4 py-3 rounded-2xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm">
                </div>
                <div class="space-y-2">
                    <label class="text-xs font-bold uppercase tracking-widest text-sage-300">Phone Number</label>
                    <input type="tel" id="user-phone"
                        class="w-full px-4 py-3 rounded-2xl bg-sage-50 border-none focus:ring-2 focus:ring-sage-500 text-sm">
                </div>
            </form>
            <div class="px-8 py-6 border-t border-sage-100 bg-sage-50/30 flex justify-end gap-3">
                <button onclick="closeUserModal()"
                    class="px-6 py-2.5 rounded-2xl text-sage-500 font-medium hover:bg-sage-100 transition-colors">Cancel</button>
                <button onclick="saveUser()"
                    class="px-6 py-2.5 rounded-2xl bg-sage-500 text-white font-medium hover:bg-sage-600 transition-colors">Save
                    Changes</button>
            </div>
        </div>
    </div>
    <!-- Footer Status -->
    <footer class="border-t border-sage-100 p-8 bg-white/50">
        <div
            class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center text-xs text-sage-400 gap-4">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                <span id="system-status">System Status: Online</span>
            </div>
            <div>1wellness Health Assessment Admin &copy; <?php echo date('Y'); ?></div>
        </div>
    </footer>
    </div>
    </div>

    <!-- Error/Loading Overlay -->
    <div id="loader" class="fixed inset-0 bg-cream-50 z-[100] flex items-center justify-center">
        <div class="flex flex-col items-center gap-4">
            <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-sage-500"></div>
            <p class="text-sage-400 font-medium animate-pulse">Securing Environment...</p>
        </div>
    </div>

    <!-- Global Detail Modal -->
    <div id="detail-modal" class="fixed inset-0 z-50 invisible opacity-0 transition-all duration-300">
        <div class="absolute inset-0 bg-sage-900/40 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-2xl bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-out flex flex-col"
            id="modal-container">
            <!-- Modal Header -->
            <div class="px-8 py-6 border-b border-sage-100 flex items-center justify-between">
                <div>
                    <h2 id="modal-title" class="text-2xl font-serif text-sage-500">Details</h2>
                    <p id="modal-subtitle" class="text-xs text-sage-300 uppercase tracking-widest font-bold mt-1"></p>
                </div>
                <button onclick="closeModal()" class="p-2 hover:bg-sage-50 rounded-full transition-colors">
                    <i data-lucide="x" class="w-6 h-6 text-sage-400"></i>
                </button>
            </div>

            <!-- Modal Content (Scrollable) -->
            <div class="flex-1 overflow-y-auto p-8 space-y-8" id="modal-body">
                <!-- Dynamically populated -->
            </div>

            <!-- Modal Footer -->
            <div class="px-8 py-6 border-t border-sage-100 bg-sage-50/30 flex justify-end gap-3" id="modal-footer">
                <button onclick="closeModal()"
                    class="px-6 py-2.5 rounded-2xl text-sage-500 font-medium hover:bg-sage-100 transition-colors">Close</button>
            </div>
        </div>
    </div>

    <!-- Initialization -->
    <script>
        // Global Modal Controls
        function openModal() {
            const modal = document.getElementById('detail-modal');
            const container = document.getElementById('modal-container');
            modal.classList.remove('invisible');
            modal.classList.add('opacity-100');
            container.classList.remove('translate-x-full');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('detail-modal');
            const container = document.getElementById('modal-container');
            container.classList.add('translate-x-full');
            modal.classList.remove('opacity-100');
            setTimeout(() => {
                modal.classList.add('invisible');
                document.body.style.overflow = '';
            }, 300);
        }
    </script>

    <script src="js/admin.js" defer></script>
</body>

</html>