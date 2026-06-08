<?php
/**
 * Cron Job: Generate Weekly Plans
 * Should be run every Monday at 00:01 AM
 * Example: 1 0 * * 1 /usr/bin/php /path/to/backend/cron/generate_weekly_plans.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BaseModel.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/AIOrchestrator.php';
require_once __DIR__ . '/../classes/MealPlanner.php';

// Log start
$logFile = __DIR__ . '/cron_log.txt';
$startTime = date('Y-m-d H:i:s');
file_put_contents($logFile, "[$startTime] Starting Weekly Plan Generation\n", FILE_APPEND);

try {
    $db = Database::getInstance();
    $mealPlanner = new MealPlanner();

    // 1. Get all active customers
    $users = $db->fetchAll("SELECT id, name, email FROM users WHERE type = 'customer' AND status = 'active'");

    $count = 0;
    foreach ($users as $user) {
        $userId = $user['id'];

        // 2. Define range: This Monday to next Sunday
        $startDate = new DateTime('monday this week');
        $endDate = new DateTime('sunday this week');

        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');

        file_put_contents($logFile, "  - Processing User ID: $userId ({$user['email']}) for period $startStr to $endStr\n", FILE_APPEND);

        // 3. Generate Plans
        $mealPlanner->generateWeeklyPlanRange($userId, $startStr, $endStr);
        $count++;
    }

    $endTime = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$endTime] Completed! Processed $count users.\n\n", FILE_APPEND);
    echo "Successfully generated plans for $count users.\n";

} catch (Exception $e) {
    $errorTime = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$errorTime] ERROR: " . $e->getMessage() . "\n\n", FILE_APPEND);
    echo "Error: " . $e->getMessage() . "\n";
}
