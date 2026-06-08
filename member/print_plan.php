<?php
session_start();
require_once '../backend/classes/Database.php';
require_once '../backend/classes/MemberAuth.php';
require_once '../backend/classes/MealPlanner.php';

$auth = new MemberAuth();
$auth->requireLogin();
$user = $auth->getCurrentUser();
$planner = new MealPlanner();

// Get week plan (mocking 7 days)
$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date('Y-m-d', strtotime("+$i days"));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Weekly Plan - 1wellness</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body {
                -webkit-print-color-adjust: exact;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-white p-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-start mb-8 border-b pb-6">
            <div>
                <h1 class="text-3xl font-bold text-green-800">Weekly Meal & Workout Plan</h1>
                <p class="text-gray-600">Prepared for <?php echo htmlspecialchars($user['first_name']); ?></p>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-green-900">1wellness</div>
                <p class="text-sm text-gray-500"><?php echo date('M j, Y'); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8">
            <?php foreach ($days as $date):
                $plan = $planner->getTodayPlan($user['user_id'], $date); // Reusing getTodayPlan which takes date
                $dayName = date('l', strtotime($date));
                ?>
                <div class="border rounded-lg p-6 break-inside-avoid shadow-sm hover:shadow-md transition-shadow">
                    <h3 class="text-xl font-bold text-green-900 mb-4 border-b border-green-100 pb-2">
                        <?php echo $dayName; ?> (<?php echo date('M j', strtotime($date)); ?>)
                    </h3>

                    <?php if (isset($plan['meals'])): ?>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                            <?php foreach (['breakfast', 'lunch', 'dinner', 'snack'] as $type):
                                $m = $plan['meals'][$type] ?? null; ?>
                                <div>
                                    <span
                                        class="text-[10px] font-bold text-green-700 uppercase tracking-widest block mb-1"><?php echo $type; ?></span>
                                    <p class="text-sm font-semibold text-gray-800"><?php echo $m['name'] ?? 'Not set'; ?></p>
                                    <p class="text-[11px] text-gray-500 italic mt-1"><?php echo $m['description'] ?? ''; ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="bg-green-50/50 p-4 rounded-xl border border-green-100">
                            <div class="flex items-center gap-3">
                                <span class="text-xl">🏃‍♀️</span>
                                <div>
                                    <span class="text-[10px] font-bold text-green-700 uppercase tracking-widest block">Movement
                                        Protocol</span>
                                    <p class="text-sm font-bold text-green-900">
                                        <?php echo $plan['workout']['name'] ?? 'PCOS Friendly Walk'; ?></p>
                                    <p class="text-xs text-green-800/70 mt-0.5">
                                        <?php echo $plan['workout']['description'] ?? '15 minutes of gentle movement.'; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="py-4 text-center">
                            <p class="text-sm text-gray-400 italic">Plan not yet generated for this day.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-8 pt-6 border-t text-center text-gray-500 text-sm">
            <p>1wellness Health Assessment System</p>
            <p>Disclaimer: This plan is for informational purposes only. Consult your doctor before starting any new
                diet.</p>
        </div>
    </div>

    <!-- Print Control -->
    <div class="fixed top-4 right-4 no-print space-x-4">
        <button onclick="window.print()" class="bg-green-700 text-white px-6 py-2 rounded shadow hover:bg-green-800">
            Click to Save as PDF
        </button>
        <button onclick="window.close()" class="bg-gray-200 text-gray-800 px-6 py-2 rounded shadow hover:bg-gray-300">
            Close
        </button>
    </div>

    <script>
        // Auto print prompt
        // window.print();
    </script>
</body>

</html>