<?php
require_once 'auth.php';
require_once '../classes/Database.php';

$db = Database::getInstance();

// Get data for reports
function getReportData($db)
{
    $data = [
        'users' => [],
        'assessments' => [],
        'sales' => [],
        'contacts' => []
    ];

    if ($db->isFileStorage()) {
        // File storage
        $dataPath = '../database/data/';

        // Users
        $usersFile = $dataPath . 'users.json';
        if (file_exists($usersFile)) {
            $data['users'] = json_decode(file_get_contents($usersFile), true) ?: [];
        }

        // Assessments
        $assessmentsFile = $dataPath . 'assessments.json';
        if (file_exists($assessmentsFile)) {
            $data['assessments'] = json_decode(file_get_contents($assessmentsFile), true) ?: [];
        }

        // Sales
        $salesFile = $dataPath . 'sales.json';
        if (file_exists($salesFile)) {
            $data['sales'] = json_decode(file_get_contents($salesFile), true) ?: [];
        }

        // Contacts
        $contactsFile = $dataPath . 'contacts.json';
        if (file_exists($contactsFile)) {
            $data['contacts'] = json_decode(file_get_contents($contactsFile), true) ?: [];
        }
    } else {
        // Database storage
        try {
            $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
            $data['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $data['users'] = [];
        }

        try {
            $stmt = $db->query("SELECT * FROM assessments ORDER BY created_at DESC");
            $data['assessments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $data['assessments'] = [];
        }

        try {
            $stmt = $db->query("SELECT * FROM sales ORDER BY created_at DESC");
            $data['sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $data['sales'] = [];
        }

        try {
            $stmt = $db->query("SELECT * FROM contacts ORDER BY created_at DESC");
            $data['contacts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $data['contacts'] = [];
        }
    }

    return $data;
}

$reportData = getReportData($db);

// Calculate statistics
$stats = [
    'total_users' => count($reportData['users']),
    'total_assessments' => count($reportData['assessments']),
    'total_sales' => count($reportData['sales']),
    'total_contacts' => count($reportData['contacts']),
    'total_revenue' => 0,
    'avg_assessment_score' => 0,
    'condition_breakdown' => [],
    'monthly_trends' => []
];

// Calculate revenue
foreach ($reportData['sales'] as $sale) {
    if (isset($sale['amount'])) {
        $stats['total_revenue'] += floatval($sale['amount']);
    }
}

// Calculate average assessment score
if (!empty($reportData['assessments'])) {
    $totalScore = 0;
    $scoreCount = 0;
    foreach ($reportData['assessments'] as $assessment) {
        if (isset($assessment['score'])) {
            $totalScore += floatval($assessment['score']);
            $scoreCount++;
        }
    }
    if ($scoreCount > 0) {
        $stats['avg_assessment_score'] = round($totalScore / $scoreCount, 1);
    }
}

// Condition breakdown
foreach ($reportData['users'] as $user) {
    if (!empty($user['condition_type'])) {
        $condition = ucfirst($user['condition_type']);
        $stats['condition_breakdown'][$condition] = ($stats['condition_breakdown'][$condition] ?? 0) + 1;
    }
}

// Monthly trends (last 6 months)
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthlyData[$month] = [
        'users' => 0,
        'assessments' => 0,
        'sales' => 0,
        'revenue' => 0
    ];
}

foreach ($reportData['users'] as $user) {
    if (!empty($user['created_at'])) {
        $month = date('Y-m', strtotime($user['created_at']));
        if (isset($monthlyData[$month])) {
            $monthlyData[$month]['users']++;
        }
    }
}

foreach ($reportData['assessments'] as $assessment) {
    if (!empty($assessment['created_at'])) {
        $month = date('Y-m', strtotime($assessment['created_at']));
        if (isset($monthlyData[$month])) {
            $monthlyData[$month]['assessments']++;
        }
    }
}

foreach ($reportData['sales'] as $sale) {
    if (!empty($sale['created_at'])) {
        $month = date('Y-m', strtotime($sale['created_at']));
        if (isset($monthlyData[$month])) {
            $monthlyData[$month]['sales']++;
            if (isset($sale['amount'])) {
                $monthlyData[$month]['revenue'] += floatval($sale['amount']);
            }
        }
    }
}

$stats['monthly_trends'] = $monthlyData;

$pageTitle = 'Reports & Analytics - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to
            Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Analytics & Reports</h2>
        <p class="text-[#6B7C70] mt-1">Deep dive into your platform's performance</p>
    </div>
    <div class="flex gap-2">
        <button
            class="px-4 py-2 bg-white border border-[#EAEAE5] rounded-xl text-sm font-medium hover:bg-[#F2F4F1] text-[#2C3E35] transition-colors shadow-sm">
            <i class="fas fa-download mr-1"></i> Export PDF
        </button>
    </div>
</div>

<!-- Overview Stats -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="luxury-card p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-1">Total Users</p>
            <p class="text-3xl font-serif text-[#2C3E35]"><?php echo $stats['total_users']; ?></p>
        </div>
        <div class="bg-[#E3E8E1] w-12 h-12 rounded-full flex items-center justify-center text-[#2C3E35]">
            <i class="fas fa-users text-lg"></i>
        </div>
    </div>

    <div class="luxury-card p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-1">Assessments</p>
            <p class="text-3xl font-serif text-[#2C3E35]"><?php echo $stats['total_assessments']; ?></p>
        </div>
        <div class="bg-[#FDF1E8] w-12 h-12 rounded-full flex items-center justify-center text-[#D97757]">
            <i class="fas fa-clipboard-list text-lg"></i>
        </div>
    </div>

    <div class="luxury-card p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-1">Sales</p>
            <p class="text-3xl font-serif text-[#2C3E35]"><?php echo $stats['total_sales']; ?></p>
        </div>
        <div class="bg-[#E8EAF6] w-12 h-12 rounded-full flex items-center justify-center text-[#3F51B5]">
            <i class="fas fa-shopping-cart text-lg"></i>
        </div>
    </div>

    <div class="luxury-card p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-1">Revenue</p>
            <p class="text-3xl font-serif text-[#2C3E35]">
                $<?php echo number_format($stats['total_revenue']); ?></p>
        </div>
        <div class="bg-[#FFF8E1] w-12 h-12 rounded-full flex items-center justify-center text-[#FFA000]">
            <i class="fas fa-coins text-lg"></i>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Monthly Trends Chart -->
    <div class="luxury-card p-6">
        <h3 class="text-xl font-serif text-[#2C3E35] mb-6">Monthly Trends</h3>
        <div class="relative h-64 w-full">
            <canvas id="trendsChart"></canvas>
        </div>
    </div>

    <!-- Condition Breakdown Chart -->
    <div class="luxury-card p-6">
        <h3 class="text-xl font-serif text-[#2C3E35] mb-6">Condition Breakdown</h3>
        <div class="relative h-64 w-full">
            <canvas id="conditionChart"></canvas>
        </div>
    </div>
</div>

<!-- Data Tables -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Recent Assessments -->
    <div class="luxury-card overflow-hidden">
        <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8]">
            <h3 class="flex items-center text-lg font-serif text-[#2C3E35]">
                <i class="fas fa-clipboard-list mr-2 text-[#D97757] text-sm"></i>Recent Assessments
            </h3>
        </div>
        <div class="overflow-x-auto">
            <?php if (empty($reportData['assessments'])): ?>
                <div class="p-8 text-center text-[#6B7C70]">
                    <i class="fas fa-clipboard-list text-3xl mb-2 text-[#A4B4A6]"></i>
                    <p>No assessments found</p>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead class="bg-[#F9FAF9]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">User
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Type
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Score
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Date
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EAEAE5]">
                        <?php foreach (array_slice($reportData['assessments'], 0, 5) as $assessment): ?>
                            <tr class="hover:bg-[#FDFCF8] transition-colors">
                                <td class="px-4 py-3 text-sm text-[#2C3E35] font-medium">
                                    <?php echo htmlspecialchars($assessment['name'] ?? $assessment['user_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-[#6B7C70]">
                                    <?php
                                    $typeDisplay = ucfirst($assessment['type'] ?? $assessment['condition_type'] ?? 'General');

                                    // Robust data extraction
                                    $rawData = $assessment['assessment_data'] ?? $assessment['data'] ?? '{}';
                                    $data = is_string($rawData) ? json_decode($rawData, true) : $rawData;
                                    // Handle double-encoded JSON if necessary
                                    if (is_string($data)) {
                                        $data = json_decode($data, true);
                                    }

                                    if (!empty($data) && is_array($data)) {
                                        if (isset($data['pcosType'])) {
                                            $pcos = $data['pcosType'];
                                            // Handle if pcosType is array or object
                                            if (is_array($pcos)) {
                                                $primary = $pcos['primary'] ?? $pcos['type'] ?? null;
                                                if ($primary)
                                                    $typeDisplay = ucfirst($primary) . ' PCOS';
                                            } elseif (is_string($pcos)) {
                                                $typeDisplay = ucfirst($pcos) . ' PCOS';
                                            }
                                        } elseif (isset($data['acneType'])) {
                                            $acne = $data['acneType'];
                                            if (is_array($acne)) {
                                                $primary = $acne['primary'] ?? $acne['type'] ?? null;
                                                if ($primary)
                                                    $typeDisplay = ucfirst($primary) . ' Acne';
                                            } elseif (is_string($acne)) {
                                                $typeDisplay = ucfirst($acne) . ' Acne';
                                            }
                                        } elseif (isset($data['weightType'])) {
                                            $weight = $data['weightType'];
                                            if (is_array($weight)) {
                                                $primary = $weight['primary'] ?? $weight['type'] ?? null;
                                                if ($primary)
                                                    $typeDisplay = ucfirst($primary) . ' Weight Type';
                                            } elseif (is_string($weight)) {
                                                $typeDisplay = ucfirst($weight) . ' Weight Type';
                                            }
                                        }
                                    }
                                    echo htmlspecialchars($typeDisplay);
                                    ?>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <?php if (isset($assessment['score'])): ?>
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-[#E3E8E1] text-[#2C3E35]">
                                            <?php echo htmlspecialchars($assessment['score']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[#A4B4A6] text-xs">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-[#A4B4A6]">
                                    <?php echo date('M j', strtotime($assessment['created_at'] ?? 'now')); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="luxury-card overflow-hidden">
        <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8]">
            <h3 class="flex items-center text-lg font-serif text-[#2C3E35]">
                <i class="fas fa-shopping-cart mr-2 text-[#2C3E35] text-sm"></i>Recent Sales
            </h3>
        </div>
        <div class="overflow-x-auto">
            <?php if (empty($reportData['sales'])): ?>
                <div class="p-8 text-center text-[#6B7C70]">
                    <i class="fas fa-shopping-cart text-3xl mb-2 text-[#A4B4A6]"></i>
                    <p>No sales found</p>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead class="bg-[#F9FAF9]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">
                                Customer
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">
                                Product</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Amount
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-[#A4B4A6] uppercase tracking-wider">Date
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EAEAE5]">
                        <?php foreach (array_slice($reportData['sales'], 0, 5) as $sale): ?>
                            <tr class="hover:bg-[#FDFCF8] transition-colors">
                                <td class="px-4 py-3 text-sm text-[#2C3E35] font-medium">
                                    <?php echo htmlspecialchars($sale['name'] ?? $sale['customer_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-[#6B7C70]">
                                    <?php echo htmlspecialchars($sale['product'] ?? $sale['product_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-4 py-3 text-sm font-bold text-[#2C3E35]">
                                    <?php if (isset($sale['amount'])): ?>
                                        $<?php echo number_format(floatval($sale['amount']), 0); ?>
                                    <?php else: ?>
                                        <span class="text-[#A4B4A6] font-normal">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-[#A4B4A6]">
                                    <?php echo date('M j', strtotime($sale['created_at'] ?? 'now')); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Aesthetics Configuration for Chart.js
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#6B7C70';
    Chart.defaults.scale.grid.color = '#F2F4F1';

    // Monthly Trends Chart
    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
    const monthlyData = <?php echo json_encode($stats['monthly_trends']); ?>;
    const months = Object.keys(monthlyData);
    const usersData = months.map(month => monthlyData[month].users);
    const assessmentsData = months.map(month => monthlyData[month].assessments);
    const salesData = months.map(month => monthlyData[month].sales);

    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: months.map(month => {
                const date = new Date(month + '-01');
                return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
            }),
            datasets: [
                {
                    label: 'Users',
                    data: usersData,
                    borderColor: '#2C3E35', // Moss
                    backgroundColor: 'rgba(44, 62, 53, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Assessments',
                    data: assessmentsData,
                    borderColor: '#D97757', // Clay
                    backgroundColor: 'rgba(217, 119, 87, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Sales',
                    data: salesData,
                    borderColor: '#3F51B5', // Indigo (keeping distinctive but muted? maybe change to dark grey)
                    // Let's us a dark grey for sales to keep palette strict
                    borderColor: '#6B7C70',
                    backgroundColor: 'rgba(107, 124, 112, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8
                    }
                },
                tooltip: {
                    backgroundColor: '#2C3E35',
                    titleColor: '#FDFCF8',
                    bodyColor: '#FDFCF8',
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, border: { display: false } }
            }
        }
    });

    // Condition Breakdown Chart
    const conditionCtx = document.getElementById('conditionChart').getContext('2d');
    const conditionData = <?php echo json_encode($stats['condition_breakdown']); ?>;
    const conditionLabels = Object.keys(conditionData);
    const conditionValues = Object.values(conditionData);

    new Chart(conditionCtx, {
        type: 'doughnut',
        data: {
            labels: conditionLabels,
            datasets: [{
                data: conditionValues,
                backgroundColor: [
                    '#2C3E35', // Moss
                    '#D97757', // Clay
                    '#E3E8E1', // Sage
                    '#6B7C70', // Mute
                    '#A4B4A6',
                    '#F2F4F1'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        padding: 20,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: '#2C3E35',
                    bodyColor: '#FDFCF8',
                    padding: 12,
                    cornerRadius: 8
                }
            },
            cutout: '75%'
        }
    });
</script>

<?php include 'includes/footer.php'; ?>