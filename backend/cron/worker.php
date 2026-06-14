<?php
/**
 * Async job queue worker.
 *
 * Cron: * * * * *  php /path/to/backend/cron/worker.php >> /dev/null 2>&1
 *
 * Register handlers below, then the worker drains the jobs table in batches.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/classes/Database.php';
require_once dirname(__DIR__) . '/classes/BaseModel.php';
require_once dirname(__DIR__) . '/classes/Settings.php';
require_once dirname(__DIR__) . '/classes/JobQueue.php';
require_once dirname(__DIR__) . '/classes/Mailer.php';

set_time_limit(55);

// -----------------------------------------------------------------------
// Handler registrations
// -----------------------------------------------------------------------

JobQueue::register('send_email', function (array $payload) {
    $mailer = new Mailer();
    $mailer->send(
        $payload['to'],
        $payload['subject'] ?? '(no subject)',
        $payload['html'] ?? $payload['body'] ?? '',
        $payload['from'] ?? null,
        $payload['from_name'] ?? null
    );
});

JobQueue::register('generate_plan', function (array $payload) {
    require_once dirname(__DIR__) . '/classes/User.php';
    require_once dirname(__DIR__) . '/classes/AIOrchestrator.php';
    require_once dirname(__DIR__) . '/classes/MealPlanner.php';
    $planner = new MealPlanner();
    $planner->ensurePlansExist((int) $payload['user_id'], (int) ($payload['days'] ?? 7));
});

JobQueue::register('send_notification', function (array $payload) {
    require_once dirname(__DIR__) . '/classes/Notifications/Channels/ChannelAdapterInterface.php';
    require_once dirname(__DIR__) . '/classes/Notifications/Channels/EmailChannel.php';
    require_once dirname(__DIR__) . '/classes/Notifications/Channels/WhatsAppChannel.php';
    require_once dirname(__DIR__) . '/classes/Notifications/Channels/SmsChannel.php';
    require_once dirname(__DIR__) . '/classes/Notifications/TemplateRenderer.php';
    require_once dirname(__DIR__) . '/classes/Notifications/ConsentManager.php';
    require_once dirname(__DIR__) . '/classes/Notifications/NotificationService.php';
    $ns  = NotificationService::getInstance();
    $row = Database::getInstance()->fetch(
        "SELECT * FROM notification_queue WHERE id = ?",
        [(int)$payload['queue_id']]
    );
    if ($row) {
        $ns->dispatch($row);
    }
});

// -----------------------------------------------------------------------
// Run
// -----------------------------------------------------------------------

$jq     = new JobQueue();
$counts = $jq->runBatch();

if (array_sum($counts) > 0) {
    echo '[' . date('Y-m-d H:i:s') . "] worker: processed={$counts['processed']} failed={$counts['failed']} skipped={$counts['skipped']}\n";
}
