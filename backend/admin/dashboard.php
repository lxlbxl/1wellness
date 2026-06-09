<?php
require_once 'auth.php';

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
    'mens_assessments' => 0,
    'sales' => 0,
    'contacts' => 0
];

try {
    // Get counts using Database class methods with proper filtering
    $stats['sales'] = $db->getSalesCount();
    $stats['pcos_assessments'] = $db->getAssessmentCount(null, 'pcos');
    $stats['acne_assessments'] = $db->getAssessmentCount(null, 'acne');
    $stats['weight_assessments'] = $db->getAssessmentCount(null, 'weight');
    $stats['mens_assessments'] = $db->getAssessmentCount(null, 'mens');

    // Get sales counts for conversion calculation
    $stats['pcos_sales'] = $db->getSalesCount('completed', 'pcos');
    $stats['acne_sales'] = $db->getSalesCount('completed', 'acne');
    $stats['weight_sales'] = $db->getSalesCount('completed', 'weight');
    $stats['mens_sales'] = $db->getSalesCount('completed', 'mens');

    // Calculate conversion rates (Overall)
    $conversionRates = [
        'PCOS' => $stats['pcos_assessments'] > 0 ? round(($stats['pcos_sales'] / $stats['pcos_assessments']) * 100, 1) : 0,
        'Acne' => $stats['acne_assessments'] > 0 ? round(($stats['acne_sales'] / $stats['acne_assessments']) * 100, 1) : 0,
        'Weight' => $stats['weight_assessments'] > 0 ? round(($stats['weight_sales'] / $stats['weight_assessments']) * 100, 1) : 0,
        'Mens' => $stats['mens_assessments'] > 0 ? round(($stats['mens_sales'] / $stats['mens_assessments']) * 100, 1) : 0,
    ];

    // Get Daily Conversion Data for Chart
    $funnels = ['pcos', 'acne', 'weight', 'mens'];
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

$pageTitle = 'Overview - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header Section -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-4">
    <div>
        <p class="text-[#6B7C70] text-sm font-medium uppercase tracking-wider mb-2">Admin Portal</p>
        <h2 class="text-4xl md:text-5xl font-serif text-[#2C3E35]">
            Overview
        </h2>
    </div>
    <div class="flex items-center gap-3">
        <span class="px-4 py-2 bg-[#E3E8E1] rounded-full text-[#2C3E35] text-sm font-medium flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#2C3E35] animate-pulse"></span>
            System Online
        </span>
        <span class="text-[#6B7C70] font-serif italic"><?php echo date('F j, Y'); ?></span>
    </div>
</div>

<!-- Primary Stats Grid (Bento Box Style) -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">

    <!-- Total Users (Hero Style) -->
    <div
        class="col-span-1 md:col-span-2 luxury-card p-8 bg-[#2C3E35] text-white border-none relative overflow-hidden group">
        <div
            class="absolute top-0 right-0 w-32 h-32 bg-white rounded-full blur-[60px] opacity-10 group-hover:opacity-20 transition-all">
        </div>
        <div class="relative z-10">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[#A4B4A6] text-sm font-medium uppercase tracking-wider mb-1">Community Growth</p>
                    <h3 class="text-5xl font-serif mt-2"><?php echo number_format($stats['users'] ?? 0); ?></h3>
                    <p class="text-[#E3E8E1] mt-2 font-light">Total Registered Members</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center backdrop-blur-sm">
                    <i class="fas fa-users text-white text-lg"></i>
                </div>
            </div>
            <div class="mt-8 pt-6 border-t border-white/10 flex gap-8">
                <div>
                    <span class="block text-2xl font-serif"><?php echo number_format($stats['contacts'] ?? 0); ?></span>
                    <span class="text-xs text-[#A4B4A6] uppercase">Contacts</span>
                </div>
                <div>
                    <span class="block text-2xl font-serif"><?php echo number_format($stats['sales'] ?? 0); ?></span>
                    <span class="text-xs text-[#A4B4A6] uppercase">Total Orders</span>
                </div>
            </div>
        </div>
    </div>

    <!-- PCOS Stats -->
    <div class="luxury-card p-6 flex flex-col justify-between group">
        <div class="flex justify-between items-start">
            <div class="w-10 h-10 rounded-full bg-[#FDF1E8] flex items-center justify-center text-[#D97757]">
                <i class="fas fa-heartbeat"></i>
            </div>
            <span class="text-xs font-bold bg-[#FDF1E8] text-[#D97757] px-2 py-1 rounded-full">PCOS</span>
        </div>
        <div class="mt-4">
            <h4 class="text-3xl font-serif text-[#2C3E35]">
                <?php echo number_format($stats['pcos_assessments'] ?? 0); ?>
            </h4>
            <p class="text-sm text-[#6B7C70] mt-1">Assessments Taken</p>
        </div>
        <div class="mt-4 pt-4 border-t border-[#EAEAE5]">
            <div class="flex justify-between items-center text-xs">
                <span class="text-[#6B7C70]">Conversion</span>
                <span class="font-bold text-[#2C3E35]"><?php echo $conversionRates['PCOS']; ?>%</span>
            </div>
            <div class="w-full bg-[#F2F4F1] h-1.5 rounded-full mt-2 overflow-hidden">
                <div class="bg-[#D97757] h-full rounded-full"
                    style="width: <?php echo min(100, $conversionRates['PCOS']); ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Acne Stats -->
    <div class="luxury-card p-6 flex flex-col justify-between group">
        <div class="flex justify-between items-start">
            <div class="w-10 h-10 rounded-full bg-[#FCE7E7] flex items-center justify-center text-[#E57373]">
                <i class="fas fa-face-frown"></i>
            </div>
            <span class="text-xs font-bold bg-[#FCE7E7] text-[#E57373] px-2 py-1 rounded-full">Acne</span>
        </div>
        <div class="mt-4">
            <h4 class="text-3xl font-serif text-[#2C3E35]">
                <?php echo number_format($stats['acne_assessments'] ?? 0); ?>
            </h4>
            <p class="text-sm text-[#6B7C70] mt-1">Assessments Taken</p>
        </div>
        <div class="mt-4 pt-4 border-t border-[#EAEAE5]">
            <div class="flex justify-between items-center text-xs">
                <span class="text-[#6B7C70]">Conversion</span>
                <span class="font-bold text-[#2C3E35]"><?php echo $conversionRates['Acne']; ?>%</span>
            </div>
            <div class="w-full bg-[#F2F4F1] h-1.5 rounded-full mt-2 overflow-hidden">
                <div class="bg-[#E57373] h-full rounded-full"
                    style="width: <?php echo min(100, $conversionRates['Acne']); ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Weight & Mens (Shared Column for space) -->
    <div class="col-span-1 md:col-span-2 lg:col-span-4 grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Weight Stats -->
        <div class="luxury-card p-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-[#F3E5F5] flex items-center justify-center text-[#9C27B0]">
                    <i class="fas fa-weight-scale"></i>
                </div>
                <div>
                    <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wide">Weight Loss</p>
                    <h4 class="text-2xl font-serif text-[#2C3E35]">
                        <?php echo number_format($stats['weight_assessments'] ?? 0); ?> <span
                            class="text-sm text-[#6B7C70] font-sans font-normal">Assessments</span>
                    </h4>
                </div>
            </div>
            <div class="text-right">
                <span class="block text-xl font-bold text-[#2C3E35]"><?php echo $conversionRates['Weight']; ?>%</span>
                <span class="text-xs text-[#6B7C70]">Conv. Rate</span>
            </div>
        </div>

        <!-- Mens Stats -->
        <div class="luxury-card p-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-[#E8EAF6] flex items-center justify-center text-[#3F51B5]">
                    <i class="fas fa-leaf"></i>
                </div>
                <div>
                    <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wide">Mens (General)</p>
                    <h4 class="text-2xl font-serif text-[#2C3E35]">
                        <?php echo number_format($stats['mens_assessments'] ?? 0); ?> <span
                            class="text-sm text-[#6B7C70] font-sans font-normal">Assessments</span>
                    </h4>
                </div>
            </div>
            <div class="text-right">
                <span class="block text-xl font-bold text-[#2C3E35]"><?php echo $conversionRates['Mens']; ?>%</span>
                <span class="text-xs text-[#6B7C70]">Conv. Rate</span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Grid -->
<h3 class="text-2xl font-serif text-[#2C3E35] mb-6">Management Tools</h3>
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-12">
    <?php
    $actions = [
        ['manage-users.php', 'users', 'Users', 'bg-[#E3E8E1]', 'text-[#2C3E35]'],
        ['reports.php', 'chart-bar', 'Reports', 'bg-[#FDF1E8]', 'text-[#D97757]'],
        ['settings.php', 'cog', 'Settings', 'bg-[#F2F4F1]', 'text-[#6B7C70]'],
        ['export.php', 'download', 'Export', 'bg-[#E0F2F1]', 'text-[#009688]'],
        ['webhooks.php', 'link', 'Webhooks', 'bg-[#FFEBEE]', 'text-[#E57373]'],
        ['pricing.php', 'tag', 'Pricing', 'bg-[#FFF3E0]', 'text-[#F57C00]'],
        ['assessments.php', 'clipboard-list', 'Results', 'bg-[#E8EAF6]', 'text-[#3F51B5]'],
        ['sales.php', 'shopping-cart', 'Sales', 'bg-[#F3E5F5]', 'text-[#9C27B0]']
    ];
    foreach ($actions as $action): ?>
        <a href="<?php echo $action[0]; ?>"
            class="action-button flex flex-col items-center justify-center p-4 rounded-2xl bg-white border border-[#EAEAE5] shadow-sm hover:shadow-md hover:border-[#D97757]/30 group">
            <div
                class="w-10 h-10 rounded-full <?php echo $action[3]; ?> <?php echo $action[4]; ?> flex items-center justify-center mb-2 text-lg group-hover:scale-110 transition-transform">
                <i class="fas fa-<?php echo $action[1]; ?>"></i>
            </div>
            <span class="text-sm font-medium text-[#2C3E35]"><?php echo $action[2]; ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Analytics Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">

    <!-- Assessment Chart -->
    <div class="luxury-card p-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-xl font-serif text-[#2C3E35]">Assessment Flow</h3>
                <p class="text-xs text-[#6B7C70] uppercase tracking-wide">Last 7 Days</p>
            </div>
            <div class="p-2 bg-[#F2F4F1] rounded-lg">
                <i class="fas fa-chart-line text-[#2C3E35]"></i>
            </div>
        </div>
        <div class="relative h-64">
            <canvas id="assessmentChart"></canvas>
        </div>
    </div>

    <!-- Conversion Chart -->
    <div class="luxury-card p-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-xl font-serif text-[#2C3E35]">Conversion Trends</h3>
                <p class="text-xs text-[#6B7C70] uppercase tracking-wide">Performance by Type</p>
            </div>
            <div class="p-2 bg-[#F2F4F1] rounded-lg">
                <i class="fas fa-percent text-[#2C3E35]"></i>
            </div>
        </div>
        <div class="relative h-64">
            <canvas id="conversionChart"></canvas>
        </div>
    </div>
</div>

<!-- Bottom Grid: Insights & Logs -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">

    <!-- System Insights -->
    <div class="luxury-card p-8 lg:col-span-1">
        <h3 class="text-xl font-serif text-[#2C3E35] mb-6">System Health</h3>
        <div class="space-y-4">
            <div class="flex items-center gap-4 p-4 bg-[#F2F4F1] rounded-2xl">
                <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-[#2C3E35] shadow-sm">
                    <i class="fas fa-server text-xs"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-bold text-[#2C3E35]">Database</p>
                    <p class="text-xs text-[#6B7C70]">
                        <?php echo $db->isFileStorage() ? 'File Storage' : 'MySQL'; ?>
                    </p>
                </div>
                <?php if ($db->isFileStorage()): ?>
                    <span class="w-2 h-2 rounded-full bg-[#D97757]" title="Upgrade Suggested"></span>
                <?php else: ?>
                    <span class="w-2 h-2 rounded-full bg-[#4CAF50]"></span>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-4 p-4 bg-[#E3E8E1] rounded-2xl">
                <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-[#2C3E35] shadow-sm">
                    <i class="fas fa-clock text-xs"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-bold text-[#2C3E35]">Cron Jobs</p>
                    <p class="text-xs text-[#6B7C70]">Required for webhooks</p>
                </div>
                <a href="../cron/README.md" target="_blank" class="text-xs font-bold text-[#2C3E35] underline">Check</a>
            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-[#EAEAE5]">
            <h4 class="text-sm font-bold text-[#2C3E35] mb-4">Funnel Distribution</h4>
            <?php
            $total = $stats['pcos_assessments'] + $stats['acne_assessments'] + $stats['weight_assessments'] + $stats['mens_assessments'];
            if ($total > 0):
                $pcosPct = round(($stats['pcos_assessments'] / $total) * 100);
                $acnePct = round(($stats['acne_assessments'] / $total) * 100);
                $weightPct = round(($stats['weight_assessments'] / $total) * 100);
                ?>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-xs mb-1 text-[#6B7C70]">
                            <span>PCOS</span><span><?php echo $pcosPct; ?>%</span>
                        </div>
                        <div class="w-full bg-[#F2F4F1] h-1.5 rounded-full">
                            <div class="bg-[#D97757] h-1.5 rounded-full" style="width: <?php echo $pcosPct; ?>%">
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1 text-[#6B7C70]">
                            <span>Acne</span><span><?php echo $acnePct; ?>%</span>
                        </div>
                        <div class="w-full bg-[#F2F4F1] h-1.5 rounded-full">
                            <div class="bg-[#E57373] h-1.5 rounded-full" style="width: <?php echo $acnePct; ?>%">
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1 text-[#6B7C70]">
                            <span>Weight</span><span><?php echo $weightPct; ?>%</span>
                        </div>
                        <div class="w-full bg-[#F2F4F1] h-1.5 rounded-full">
                            <div class="bg-[#9C27B0] h-1.5 rounded-full" style="width: <?php echo $weightPct; ?>%">
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-sm text-[#6B7C70] italic">No data yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="luxury-card p-8 lg:col-span-2">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-serif text-[#2C3E35]">Recent Logs</h3>
            <a href="audit-logs.php" class="text-sm font-medium text-[#D97757] hover:underline">View Full
                History</a>
        </div>

        <?php if (empty($recentActivities)): ?>
            <div class="text-center py-12 bg-[#FDFCF8] rounded-2xl border border-dashed border-[#EAEAE5]">
                <i class="fas fa-scroll text-[#E3E8E1] text-4xl mb-3"></i>
                <p class="text-[#6B7C70]">No recent activity recorded.</p>
            </div>
        <?php else: ?>
            <div class="space-y-0">
                <?php foreach ($recentActivities as $index => $activity): ?>
                    <div class="relative pl-8 pb-8 border-l border-[#EAEAE5] last:pb-0 last:border-0">
                        <div class="absolute left-[-5px] top-0 w-2.5 h-2.5 rounded-full bg-[#2C3E35] ring-4 ring-white">
                        </div>
                        <div class="flex justify-between items-start -mt-1.5">
                            <div>
                                <p class="text-sm font-medium text-[#2C3E35]">
                                    <?php echo htmlspecialchars($activity['username'] ?? 'System'); ?>
                                    <span class="font-normal text-[#6B7C70]">
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $activity['action']))); ?>
                                    </span>
                                </p>
                                <?php if (!empty($activity['details'])): ?>
                                    <p class="text-xs text-[#A4B4A6] mt-1 bg-[#F2F4F1] px-2 py-1 rounded inline-block">
                                        <?php echo htmlspecialchars($activity['details']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-[#A4B4A6] font-mono whitespace-nowrap">
                                <?php echo date('M j, H:i', strtotime($activity['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart Logic -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Common Chart Options for "Organic Luxury" Look
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#6B7C70';
    Chart.defaults.scale.grid.color = '#F2F4F1';

    <?php
    $dailyData = $db->getDailyAssessments(7);
    $labels = array_keys($dailyData);
    $values = array_values($dailyData);
    ?>

    // 1. Assessment Chart
    const ctx = document.getElementById('assessmentChart').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(44, 62, 53, 0.2)'); // Dark Moss low opacity
    gradient.addColorStop(1, 'rgba(44, 62, 53, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Assessments',
                data: <?php echo json_encode($values); ?>,
                borderColor: '#2C3E35', // Dark Moss
                backgroundColor: gradient,
                borderWidth: 2,
                pointBackgroundColor: '#2C3E35',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#2C3E35',
                    titleFont: {
                        family: 'Playfair Display',
                        size: 14
                    },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        borderDash: [5, 5]
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // 2. Conversion Chart
    const ctxConversion = document.getElementById('conversionChart').getContext('2d');
    new Chart(ctxConversion, {
        type: 'bar', // Changed to Bar for better visual distinctness in this style
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'PCOS',
                data: <?php echo json_encode($dailyConversionData['pcos']); ?>,
                backgroundColor: '#D97757', // Clay
                borderRadius: 4,
                barPercentage: 0.6
            },
            {
                label: 'Acne',
                data: <?php echo json_encode($dailyConversionData['acne']); ?>,
                backgroundColor: '#E57373', // Soft Red
                borderRadius: 4,
                barPercentage: 0.6
            },
            {
                label: 'Weight',
                data: <?php echo json_encode($dailyConversionData['weight']); ?>,
                backgroundColor: '#9C27B0', // Purple
                borderRadius: 4,
                barPercentage: 0.6
            },
            {
                label: 'Mens',
                data: <?php echo json_encode($dailyConversionData['mens']); ?>,
                backgroundColor: '#3F51B5', // Indigo
                borderRadius: 4,
                barPercentage: 0.6
            }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        padding: 20
                    }
                },
                tooltip: {
                    backgroundColor: '#2C3E35',
                    cornerRadius: 8
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
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
</script>

<?php include 'includes/footer.php'; ?>