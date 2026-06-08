<?php
session_start();
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Initialize database and get statistics
$db = Database::getInstance();
$admin = new Admin();

// Get dashboard statistics
$stats = [
    'users' => 0,
    'pcos_assessments' => 0,
    'acne_assessments' => 0,
    'weight_assessments' => 0,
    'egbon_assessments' => 0,
    'sales' => 0,
    'contacts' => 0
];

try {
    // Get counts using Database class methods with proper filtering
    $stats['sales'] = $db->getSalesCount();
    $stats['pcos_assessments'] = $db->getAssessmentCount(null, 'pcos');
    $stats['acne_assessments'] = $db->getAssessmentCount(null, 'acne');
    $stats['weight_assessments'] = $db->getAssessmentCount(null, 'weight');
    $stats['egbon_assessments'] = $db->getAssessmentCount(null, 'egbon');

    // Get sales counts for conversion calculation
    $stats['pcos_sales'] = $db->getSalesCount('completed', 'pcos');
    $stats['acne_sales'] = $db->getSalesCount('completed', 'acne');
    $stats['weight_sales'] = $db->getSalesCount('completed', 'weight');
    $stats['egbon_sales'] = $db->getSalesCount('completed', 'egbon');

    // Calculate conversion rates (Overall)
    $conversionRates = [
        'PCOS' => $stats['pcos_assessments'] > 0 ? round(($stats['pcos_sales'] / $stats['pcos_assessments']) * 100, 1) : 0,
        'Acne' => $stats['acne_assessments'] > 0 ? round(($stats['acne_sales'] / $stats['acne_assessments']) * 100, 1) : 0,
        'Weight' => $stats['weight_assessments'] > 0 ? round(($stats['weight_sales'] / $stats['weight_assessments']) * 100, 1) : 0,
        'Egbon' => $stats['egbon_assessments'] > 0 ? round(($stats['egbon_sales'] / $stats['egbon_assessments']) * 100, 1) : 0,
    ];

    // Get Daily Conversion Data for Chart
    $funnels = ['pcos', 'acne', 'weight', 'egbon'];
    $dailyConversionData = [];
    $chartLabels = [];

    foreach ($funnels as $funnel) {
        $dailyAssessments = $db->getDailyAssessments(7, $funnel);
        $dailySales = $db->getDailySales(7, $funnel);

        if (empty($chartLabels)) {
            $chartLabels = array_keys($dailyAssessments);
        }

        $data = [];
        foreach ($chartLabels as $date) {
            $assessments = $dailyAssessments[$date] ?? 0;
            $sales = $dailySales[$date] ?? 0;
            $rate = $assessments > 0 ? round(($sales / $assessments) * 100, 1) : 0;
            $data[] = $rate;
        }
        $dailyConversionData[$funnel] = $data;
    }

    // Get actual user and contact counts from data files
    $stats['users'] = $db->getUserCount();
    $stats['contacts'] = $db->getContactCount();
} catch (Exception $e) {
    // If methods fail, stats remain at 0
    error_log("Dashboard stats error: " . $e->getMessage());
}

// Get recent activities
$recentActivities = [];
try {
    $recentActivities = $admin->getActivityLogs(null, 5);
} catch (Exception $e) {
    error_log("Recent activities error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - 1wellness Health Assessment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include 'includes/nav.php'; ?>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Dashboard Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900">Dashboard</h2>
            <p class="text-gray-600 mt-2">Overview of your health assessment system</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Users Card -->
            <div class="stat-card bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                <i class="fas fa-users text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    <?php echo number_format($stats['users'] ?? 0); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PCOS Assessments Card -->
            <div class="stat-card bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-pink-500 rounded-md flex items-center justify-center">
                                <i class="fas fa-heartbeat text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">PCOS Assessments</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    <?php echo number_format($stats['pcos_assessments'] ?? 0); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acne Assessments Card -->
            <div class="stat-card bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                                <i class="fas fa-face-frown text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Acne Assessments</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    <?php echo number_format($stats['acne_assessments'] ?? 0); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weight Assessments Card -->
            <div class="stat-card bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                <i class="fas fa-weight-scale text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Weight Assessments</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    <?php echo number_format($stats['weight_assessments'] ?? 0); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Egbon Assessments Card -->
            <div class="stat-card bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                                <i class="fas fa-leaf text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Egbon Assessments</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    <?php echo number_format($stats['egbon_assessments'] ?? 0); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Card -->
            <div class="stat-card bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Sales</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    <?php echo number_format($stats['sales'] ?? 0); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contacts Card -->
            <div class="stat-card bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                <i class="fas fa-envelope text-white"></i>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Contacts</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    <?php echo number_format($stats['contacts'] ?? 0); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <a href="manage-users.php"
                        class="bg-blue-500 text-white p-4 rounded-lg text-center hover:bg-blue-600 transition-colors">
                        <i class="fas fa-users text-2xl mb-2"></i>
                        <div class="font-medium">Manage Users</div>
                    </a>
                    <a href="reports.php"
                        class="bg-green-500 text-white p-4 rounded-lg text-center hover:bg-green-600 transition-colors">
                        <i class="fas fa-chart-bar text-2xl mb-2"></i>
                        <div class="font-medium">View Reports</div>
                    </a>
                    <a href="settings.php"
                        class="bg-purple-500 text-white p-4 rounded-lg text-center hover:bg-purple-600 transition-colors">
                        <i class="fas fa-cog text-2xl mb-2"></i>
                        <div class="font-medium">Settings</div>
                    </a>
                    <a href="export.php"
                        class="bg-orange-500 text-white p-4 rounded-lg text-center hover:bg-orange-600 transition-colors">
                        <i class="fas fa-download text-2xl mb-2"></i>
                        <div class="font-medium">Export Data</div>
                    </a>
                    <a href="webhooks.php"
                        class="bg-red-500 text-white p-4 rounded-lg text-center hover:bg-red-600 transition-colors">
                        <i class="fas fa-link text-2xl mb-2"></i>
                        <div class="font-medium">Webhooks</div>
                    </a>
                    <a href="assessments.php"
                        class="bg-teal-500 text-white p-4 rounded-lg text-center hover:bg-teal-600 transition-colors">
                        <i class="fas fa-clipboard-list text-2xl mb-2"></i>
                        <div class="font-medium">Assessments</div>
                    </a>
                    <a href="sales.php"
                        class="bg-indigo-500 text-white p-4 rounded-lg text-center hover:bg-indigo-600 transition-colors">
                        <i class="fas fa-shopping-cart text-2xl mb-2"></i>
                        <div class="font-medium">Sales</div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Charts & Insights -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Assessments Chart -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Assessment Trends (Last 7 Days)</h3>
                <canvas id="assessmentChart" height="200"></canvas>
            </div>

            <!-- Conversion Rate Chart -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Conversion Rate Trends (Last 7 Days)</h3>
                <canvas id="conversionChart" height="200"></canvas>
            </div>



            <!-- Insights -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">System Insights</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-clock text-blue-500 text-xl mr-3"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Cron Job Status</p>
                                <p class="text-xs text-gray-500">Required for webhooks</p>
                            </div>
                        </div>
                        <a href="../cron/README.md" target="_blank"
                            class="text-sm text-blue-600 hover:text-blue-800">Setup Guide &rarr;</a>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-green-50 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-database text-green-500 text-xl mr-3"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Database Engine</p>
                                <p class="text-xs text-gray-500">
                                    <?php echo $db->isFileStorage() ? 'SQLite / File (Upgrade Recommended)' : 'MySQL (Production Ready)'; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Top Performing Funnels</h4>
                        <?php
                        $total = $stats['pcos_assessments'] + $stats['acne_assessments'] + $stats['weight_assessments'] + $stats['egbon_assessments'];
                        if ($total > 0):
                            $pcosPct = round(($stats['pcos_assessments'] / $total) * 100);
                            $acnePct = round(($stats['acne_assessments'] / $total) * 100);
                            $weightPct = round(($stats['weight_assessments'] / $total) * 100);
                            ?>
                            <div class="space-y-2">
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span>PCOS</span>
                                        <span><?php echo $pcosPct; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-pink-500 h-1.5 rounded-full" style="width: <?php echo $pcosPct; ?>%">
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span>Acne</span>
                                        <span><?php echo $acnePct; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-red-500 h-1.5 rounded-full" style="width: <?php echo $acnePct; ?>%">
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span>Weight</span>
                                        <span><?php echo $weightPct; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-purple-500 h-1.5 rounded-full"
                                            style="width: <?php echo $weightPct; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-gray-500">No data available yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Activity</h3>
                    <a href="audit-logs.php" class="text-sm text-blue-600 hover:text-blue-800">View all</a>
                </div>
                <?php if (empty($recentActivities)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-history text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">No recent activity to display</p>
                        <p class="text-sm text-gray-400 mt-2">Activity logs will appear here as users interact with the
                            system</p>
                    </div>
                <?php else: ?>
                    <div class="flow-root">
                        <ul class="-mb-8">
                            <?php foreach ($recentActivities as $index => $activity): ?>
                                <li>
                                    <div class="relative pb-8">
                                        <?php if ($index < count($recentActivities) - 1): ?>
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
                                                aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span
                                                    class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white">
                                                    <i class="fas fa-user text-white text-xs"></i>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                <div>
                                                    <p class="text-sm text-gray-500">
                                                        <span
                                                            class="font-medium text-gray-900"><?php echo htmlspecialchars($activity['username'] ?? 'System'); ?></span>
                                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $activity['action']))); ?>
                                                        <?php if (!empty($activity['details'])): ?>
                                                            <span
                                                                class="block text-xs mt-1"><?php echo htmlspecialchars($activity['details']); ?></span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                    <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        <?php
        $dailyData = $db->getDailyAssessments(7);
        $labels = array_keys($dailyData);
        $values = array_values($dailyData);
        ?>
        const ctx = document.getElementById('assessmentChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Daily Assessments',
                    data: <?php echo json_encode($values); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Conversion Rate Chart (Time-based)
        const ctxConversion = document.getElementById('conversionChart').getContext('2d');
        new Chart(ctxConversion, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [
                    {
                        label: 'PCOS',
                        data: <?php echo json_encode($dailyConversionData['pcos']); ?>,
                        borderColor: 'rgb(236, 72, 153)', // Pink
                        backgroundColor: 'rgba(236, 72, 153, 0.1)',
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Acne',
                        data: <?php echo json_encode($dailyConversionData['acne']); ?>,
                        borderColor: 'rgb(239, 68, 68)', // Red
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Weight',
                        data: <?php echo json_encode($dailyConversionData['weight']); ?>,
                        borderColor: 'rgb(168, 85, 247)', // Purple
                        backgroundColor: 'rgba(168, 85, 247, 0.1)',
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Egbon',
                        data: <?php echo json_encode($dailyConversionData['egbon']); ?>,
                        borderColor: 'rgb(99, 102, 241)', // Indigo
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.3,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + context.parsed.y + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    </script>

    <!-- System Status Footer -->
    <footer class="bg-white border-t mt-12">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center text-sm text-gray-500">
                <div>
                    <span class="inline-flex items-center">
                        <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                        System Status: Online
                    </span>
                </div>
                <div>
                    Storage: <?php echo $db->isFileStorage() ? 'File-based' : 'Database'; ?>
                </div>
                <div>
                    Last Login: <?php echo date('M j, Y g:i A'); ?>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Dropdown menu functionality
        document.addEventListener('DOMContentLoaded', function () {
            const userMenuButton = document.getElementById('userMenuButton');
            const userMenu = document.getElementById('userMenu');

            userMenuButton.addEventListener('click', function (e) {
                e.preventDefault();
                userMenu.classList.toggle('hidden');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function (e) {
                if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                    userMenu.classList.add('hidden');
                }
            });
        });
    </script>
</body>

</html>