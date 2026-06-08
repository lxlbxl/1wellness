<?php
// backend/cron/daily_nudge.php

// run this via cron every morning at 7am
// command: php /path/to/backend/cron/daily_nudge.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/MealPlanner.php';

$db = Database::getInstance();
$planner = new MealPlanner();

echo "Starting Daily Nudge...\n";

// 1. Get active users
$users = $db->fetchAll("SELECT * FROM users WHERE status = 'active'");

if (empty($users)) {
    echo "No active users found.\n";
    exit;
}

$today = date('Y-m-d');
$count = 0;

foreach ($users as $user) {
    try {
        echo "Processing user: {$user['email']}... ";

        // 2. Check/Generate Plan
        // Force generation which saves to DB
        $plan = $planner->generateDailyPlan($user['id'], $today);

        if ($plan) {
            // 3. Send Notification (Email / WhatsApp logic)
            // Mocking send
            sendNotification($user['email'], $user['first_name'], $plan);
            echo "Nudge sent.\n";
            $count++;
        } else {
            echo "Failed to generate plan.\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "Done. Processed $count users.\n";

function sendNotification($email, $name, $plan)
{
    // Integrate Mailgun, SendGrid, or Twilio WhatsApp here
    // For now, simple mail() or log
    $subject = "Your PCOS Plan for Today!";
    $tip = $plan['focus_tip'] ?? "Have a great day!";

    $message = "Good morning $name!\n\n";
    $message .= "Today's Focus: $tip\n";
    $message .= "Login to see your meals: https://1wellness.club/member/login.php\n";

    // mail($email, $subject, $message); // Disabled for safety in dev
    // error_log("Sent email to $email");
}
