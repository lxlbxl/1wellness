<?php
require_once 'auth.php';
require_once '../classes/MealPlanner.php';

$db = Database::getInstance();
$planner = new MealPlanner();
$message = '';
$messageType = '';

// Handle manual trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate_plans') {
        $userId = $_POST['user_id'] ?? '';
        if ($userId) {
            try {
                // Determine range: Today to 7 days from now
                $today = date('Y-m-d');
                $endDate = date('Y-m-d', strtotime('+7 days'));

                $planner->generateWeeklyPlanRange($userId, $today, $endDate);

                $message = "Plans generated successfully for User ID $userId!";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Handle plan viewing
if (isset($_GET['view_plan'])) {
    $planId = $_GET['view_plan'];
    $plan = $db->fetch("SELECT p.*, u.first_name, u.last_name FROM daily_plans p JOIN users u ON p.user_id = u.id WHERE p.id = ?", [$planId]);
    if ($plan) {
        $planData = json_decode($plan['plan_data'], true);
        echo json_encode(['success' => true, 'data' => $planData, 'date' => $plan['plan_date'], 'user' => $plan['first_name'] . ' ' . $plan['last_name']]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

// Handle latest plan fetch (Admins want to see what user sees TODAY)
if (isset($_GET['get_latest'])) {
    $userId = $_GET['get_latest'];
    $today = date('Y-m-d');
    // Try to get today's plan specifically
    $plan = $db->fetch("SELECT p.*, u.first_name, u.last_name FROM daily_plans p JOIN users u ON p.user_id = u.id WHERE p.user_id = ? AND p.plan_date = ? LIMIT 1", [$userId, $today]);

    // If no plan for today, get the most recently created one
    if (!$plan) {
        $plan = $db->fetch("SELECT p.*, u.first_name, u.last_name FROM daily_plans p JOIN users u ON p.user_id = u.id WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 1", [$userId]);
    }

    if ($plan) {
        $planData = json_decode($plan['plan_data'], true);
        echo json_encode(['success' => true, 'data' => $planData, 'date' => $plan['plan_date'], 'user' => $plan['first_name'] . ' ' . $plan['last_name'], 'raw' => $plan['plan_data']]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

// Get users with their plan counts for the next 7 days
$users = $db->fetchAll("SELECT id, first_name, last_name, email FROM users WHERE type = 'customer' ORDER BY created_at DESC");
$today = date('Y-m-d');
$weekEnd = date('Y-m-d', strtotime('+7 days'));

foreach ($users as &$user) {
    $planCount = $db->fetch("SELECT COUNT(*) as count FROM daily_plans WHERE user_id = :uid AND plan_date BETWEEN :start AND :end", [
        ':uid' => $user['id'],
        ':start' => $today,
        ':end' => $weekEnd
    ]);
    $user['plan_count'] = $planCount['count'] ?? 0;

    // Last log status
    $lastLog = $db->fetch("SELECT status, created_at FROM ai_generation_logs WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1", [
        ':uid' => $user['id']
    ]);
    $user['last_ai_status'] = $lastLog['status'] ?? 'pending';
    $user['last_ai_time'] = $lastLog['created_at'] ?? 'Never';
}

// Get recent logs
$logs = $db->fetchAll("SELECT l.*, u.first_name, u.last_name 
                 FROM ai_generation_logs l 
                 LEFT JOIN users u ON l.user_id = u.id 
                 ORDER BY l.created_at DESC LIMIT 20");

$pageTitle = 'AI Plan Oversight - 1wellness Admin';
include 'includes/header.php';
?>

<div class="luxury-card p-8 mb-8">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="dashboard.php" class="text-[#6B7C70] hover:text-[#2C3E35] text-sm"><i
                        class="fas fa-arrow-left mr-1"></i> Dashboard</a>
                <span class="text-[#EAEAE5]">/</span>
                <span class="text-[#6B7C70] text-sm font-medium">Oversight</span>
            </div>
            <h2 class="text-4xl font-serif text-[#2C3E35]">AI Plan Oversight</h2>
            <p class="text-[#6B7C70] mt-2 italic font-serif">Monitor and manually manage AI-generated nutrition
                protocols.</p>
        </div>
        <div class="flex gap-3">
            <div class="px-4 py-2 bg-[#FAFAF8] border border-[#EAEAE5] rounded-xl text-center">
                <div class="text-xs font-bold text-[#6B7C70] uppercase">Plan Coverage</div>
                <div class="text-xl font-serif text-[#2C3E35]">Next 7 Days</div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div
            class="mb-6 p-4 rounded-2xl flex items-center gap-3 <?php echo $messageType === 'success' ? 'bg-[#E3E8E1] text-[#2C3E35]' : 'bg-[#FFEBEE] text-[#E57373]'; ?>">
            <div
                class="w-6 h-6 rounded-full flex items-center justify-center <?php echo $messageType === 'success' ? 'bg-[#2C3E35] text-white' : 'bg-[#E57373] text-white'; ?>">
                <i class="fas <?php echo $messageType === 'success' ? 'fa-check' : 'fa-exclamation'; ?> text-xs"></i>
            </div>
            <span class="font-medium text-sm"><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <!-- User Monitoring Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-[#EAEAE5]">
            <thead class="bg-[#FDFCF8]">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">Customer
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">Plan
                        Status</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">AI Health
                    </th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-[#6B7C70] uppercase tracking-wider">Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-[#EAEAE5]">
                <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-[#FAFAF8] transition-colors group">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div
                                    class="w-8 h-8 rounded-full bg-[#E3E8E1] flex items-center justify-center text-[#2C3E35] font-serif font-bold text-sm mr-3">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-[#2C3E35]">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </div>
                                    <div class="text-xs text-[#6B7C70]"><?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-[#EAEAE5] rounded-full overflow-hidden">
                                    <div class="h-full bg-[#2C3E35]"
                                        style="width: <?php echo ($user['plan_count'] / 7) * 100; ?>%"></div>
                                </div>
                                <span class="text-xs font-medium text-[#6B7C70]"><?php echo $user['plan_count']; ?>/7
                                    Days</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                            $statusClass = 'bg-[#F2F4F1] text-[#6B7C70]';
                            if ($user['last_ai_status'] === 'success')
                                $statusClass = 'bg-[#E3E8E1] text-[#2C3E35]';
                            if ($user['last_ai_status'] === 'failed')
                                $statusClass = 'bg-[#FFEBEE] text-[#E57373]';
                            if ($user['last_ai_status'] === 'generating')
                                $statusClass = 'bg-[#FDF1E8] text-[#D97757] animate-pulse';
                            ?>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                <i class="fas fa-circle text-[8px] mr-1.5 opacity-60"></i>
                                <?php echo ucfirst($user['last_ai_status']); ?>
                            </span>
                            <div class="text-[10px] text-[#A4B4A6] mt-0.5">Last attempt:
                                <?php echo date('M j, H:i', strtotime($user['last_ai_time'])); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <div class="flex justify-end gap-2">
                                <button onclick="viewLatestPlan(<?php echo $user['id']; ?>)"
                                    class="text-[#6B7C70] hover:text-[#2C3E35] w-8 h-8 rounded-full hover:bg-[#E3E8E1] flex items-center justify-center transition-colors"
                                    title="Review Latest Plan">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="generate_plans">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit"
                                        class="bg-[#2C3E35] hover:bg-[#3D5245] text-white px-4 py-1.5 rounded-full text-xs transition-colors shadow-sm flex items-center gap-2">
                                        <i class="fas fa-magic"></i> Generate
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-1 gap-8">
    <!-- Log Section -->
    <div class="luxury-card p-6">
        <h3 class="font-serif text-[#2C3E35] text-xl mb-4 flex items-center gap-2">
            <i class="fas fa-terminal text-[#D97757] text-sm"></i> AI Activity Log
        </h3>
        <div class="space-y-3 max-h-[400px] overflow-y-auto pr-2">
            <?php foreach ($logs as $log): ?>
                <div
                    class="p-3 bg-[#FAFAF8] border-l-4 rounded-r-xl <?php echo $log['status'] === 'success' ? 'border-[#2C3E35]' : ($log['status'] === 'failed' ? 'border-[#E57373]' : 'border-[#D97757]'); ?>">
                    <div class="flex justify-between items-start mb-1">
                        <span class="text-xs font-bold text-[#2C3E35] uppercase tracking-wider">
                            <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                            <span class="text-[#6B7C70] font-normal lowercase">— <?php echo $log['action']; ?></span>
                        </span>
                        <span
                            class="text-[10px] text-[#A4B4A6]"><?php echo date('M j, H:i:s', strtotime($log['created_at'])); ?></span>
                    </div>
                    <?php if ($log['status'] === 'failed'): ?>
                        <div class="text-xs text-[#E57373] mt-1 p-2 bg-white rounded border border-[#FFEBEE]">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <?php echo htmlspecialchars($log['error_message']); ?>
                        </div>
                    <?php else: ?>
                        <div class="text-xs text-[#6B7C70]">
                            Target Date: <span class="font-medium"><?php echo $log['target_date']; ?></span>
                            | Duration: <span class="font-medium"><?php echo $log['duration_ms']; ?>ms</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Plan Review Modal -->
<div id="planModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-[#2C3E35]/40 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl border border-[#EAEAE5]">
                <div class="bg-white px-6 py-5 border-b border-[#EAEAE5] flex justify-between items-center">
                    <div>
                        <h3 class="text-xl font-serif text-[#2C3E35]" id="modalTitle">Plan Review</h3>
                        <p class="text-[10px] text-[#6B7C70] uppercase font-bold tracking-widest mt-1">Real-time AI
                            Output Data</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex bg-[#F2F4F1] p-1 rounded-lg text-[10px] font-bold">
                            <button onclick="switchTab('visual')" id="tabVisual"
                                class="px-3 py-1.5 rounded-md bg-white shadow-sm transition-all">VISUAL</button>
                            <button onclick="switchTab('raw')" id="tabRaw"
                                class="px-3 py-1.5 rounded-md text-[#6B7C70] transition-all">RAW JSON</button>
                        </div>
                        <button onclick="closePlanModal()" class="text-[#6B7C70] hover:text-[#2C3E35]"><i
                                class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="p-6 max-h-[70vh] overflow-y-auto bg-[#FAFAF8]" id="modalBody">
                    <!-- Dynamic Content -->
                </div>
                <div class="p-6 max-h-[70vh] overflow-y-auto bg-[#1A2620] text-[#A4B4A6] font-mono text-xs hidden"
                    id="modalRaw">
                    <!-- Raw JSON -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    async function viewLatestPlan(userId) {
        const modal = document.getElementById('planModal');
        const body = document.getElementById('modalBody');
        const title = document.getElementById('modalTitle');
        const raw = document.getElementById('modalRaw');

        body.innerHTML = '<div class="flex justify-center p-12"><i class="fas fa-spinner fa-spin text-3xl text-[#6B7C70]"></i></div>';
        raw.classList.add('hidden');
        body.classList.remove('hidden');
        modal.classList.remove('hidden');

        try {
            const response = await fetch(`ai-oversight.php?get_latest=${userId}`);
            const result = await response.json();

            if (result.success) {
                title.textContent = `Review: Protocol for ${result.user} (${result.date})`;
                raw.textContent = JSON.stringify(JSON.parse(result.raw), null, 4);

                let html = '<div class="space-y-6 text-sm">';

                // Focus Tip
                if (result.data.focus_tip) {
                    html += `
                        <div class="bg-[#E3E8E1] p-5 rounded-2xl border border-[#2C3E35]/10">
                            <div class="text-[10px] font-bold text-[#2C3E35] uppercase mb-2 tracking-widest">Daily Focus Tip</div>
                            <p class="font-serif text-xl italic text-[#2C3E35]">"${result.data.focus_tip}"</p>
                        </div>
                    `;
                }

                // Meals
                if (result.data.meals) {
                    html += '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
                    for (const [type, meal] of Object.entries(result.data.meals)) {
                        html += `
                            <div class="luxury-card p-4 bg-white border border-[#EAEAE5]">
                                <div class="text-[10px] font-bold text-[#D97757] uppercase mb-1">${type}</div>
                                <div class="font-serif text-[#2C3E35] mb-2 font-bold">${meal.name}</div>
                                <div class="text-[11px] text-[#6B7C70] leading-relaxed">${meal.description || ''}</div>
                            </div>
                        `;
                    }
                    html += '</div>';
                }

                // Movement & Workout
                if (result.data.workout) {
                    html += `
                        <div class="bg-white p-5 rounded-2xl border border-[#EAEAE5] flex justify-between items-center">
                            <div>
                                <div class="text-[10px] font-bold text-[#A4B4A6] uppercase mb-1 tracking-widest">Movement Strategy</div>
                                <div class="font-serif text-lg text-[#2C3E35]">${result.data.workout.name || 'Not Specified'}</div>
                                <div class="text-xs text-[#6B7C70]">${result.data.workout.duration_minutes || '20'} mins • Intensity: ${result.data.workout.intensity || 'Low'}</div>
                            </div>
                            <i class="fas fa-heartbeat text-2xl text-[#D97757] opacity-20"></i>
                        </div>
                    `;
                }

                // Recommendations
                if (result.data.recommendations) {
                    html += `
                        <div class="luxury-card p-4 bg-white border-l-4 border-[#2C3E35] shadow-sm">
                            <div class="text-xs font-bold text-[#2C3E35] uppercase mb-2 tracking-widest">Protocol Recommendations</div>
                            <ul class="text-xs text-[#6B7C70] space-y-2">
                                ${result.data.recommendations.map(r => `<li class="flex gap-2"><span class="text-[#D97757]">•</span> ${r}</li>`).join('')}
                            </ul>
                        </div>
                    `;
                }

                html += '</div>';
                body.innerHTML = html;
            } else {
                body.innerHTML = '<div class="p-12 text-center text-[#E57373]">No plans found for this user. Click "Generate" to create one.</div>';
            }
        } catch (e) {
            body.innerHTML = '<div class="p-12 text-center text-[#E57373]">Error loading plan data.</div>';
        }
    }

    function switchTab(tab) {
        const visual = document.getElementById('modalBody');
        const raw = document.getElementById('modalRaw');
        const tabVisual = document.getElementById('tabVisual');
        const tabRaw = document.getElementById('tabRaw');

        if (tab === 'visual') {
            visual.classList.remove('hidden');
            raw.classList.add('hidden');
            tabVisual.classList.add('bg-white', 'shadow-sm');
            tabRaw.classList.remove('bg-white', 'shadow-sm');
            tabRaw.classList.add('text-[#6B7C70]');
        } else {
            visual.classList.add('hidden');
            raw.classList.remove('hidden');
            tabRaw.classList.add('bg-white', 'shadow-sm');
            tabRaw.classList.remove('text-[#6B7C70]');
            tabVisual.classList.remove('bg-white', 'shadow-sm');
        }
    }

    function closePlanModal() {
        document.getElementById('planModal').classList.add('hidden');
    }
</script>

<?php include 'includes/footer.php'; ?>