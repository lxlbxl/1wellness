<?php
// Run every 5 minutes via cron: */5 * * * * php /path/to/backend/cron/journeys.php

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/classes/Database.php';
require_once dirname(__DIR__) . '/classes/Settings.php';
require_once dirname(__DIR__) . '/classes/Notifications/NotificationService.php';
require_once dirname(__DIR__) . '/classes/Notifications/JourneyEngine.php';

$start = microtime(true);
echo date('[Y-m-d H:i:s]') . " Journey engine starting\n";

try {
    $engine = new JourneyEngine();
    $counts = $engine->run();

    $total = array_sum($counts);
    foreach ($counts as $journey => $n) {
        if ($n > 0) {
            echo "  $journey: $n enqueued\n";
        }
    }
    $elapsed = round(microtime(true) - $start, 2);
    echo date('[Y-m-d H:i:s]') . " Done. Total enqueued: $total ({$elapsed}s)\n";
} catch (Exception $e) {
    echo date('[Y-m-d H:i:s]') . " ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
