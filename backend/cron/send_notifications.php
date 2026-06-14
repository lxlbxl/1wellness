<?php
/**
 * Notification Send Worker
 * Cron: * * * * * php backend/cron/send_notifications.php
 *
 * Fetches pending notification_queue rows, checks suppression/quiet-hours/caps,
 * dispatches via NotificationService::dispatch(), and updates queue status.
 * Retries with exponential backoff (5m → 15m → 45m → 2h → 6h).
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/classes/Database.php';
require_once dirname(__DIR__) . '/classes/Settings.php';
require_once dirname(__DIR__) . '/classes/Mailer.php';
require_once dirname(__DIR__) . '/classes/ChannelAdapterInterface.php';
require_once dirname(__DIR__) . '/classes/EmailChannel.php';
require_once dirname(__DIR__) . '/classes/TemplateRenderer.php';
require_once dirname(__DIR__) . '/classes/NotificationService.php';

set_time_limit(55);

const BATCH_SIZE  = 25;
const MAX_RETRIES = 5;
const RETRY_DELAYS = [300, 900, 2700, 7200, 21600]; // 5m, 15m, 45m, 2h, 6h

$db    = Database::getInstance();
$ns    = NotificationService::getInstance();
$now   = date('Y-m-d H:i:s');
$start = microtime(true);

echo date('[Y-m-d H:i:s]') . " Notification worker starting\n";

if ($db->isFileStorage()) {
    echo "File-storage mode — SQL notification queue not available. Exiting.\n";
    exit(0);
}

$conn = $db->getConnection();

// ---- 1. Claim a batch of due rows -----------------------------------------
try {
    $rows = $db->fetchAll(
        "SELECT * FROM notification_queue
         WHERE status = 'pending' AND send_after <= ?
         ORDER BY send_after ASC
         LIMIT " . BATCH_SIZE,
        [$now]
    ) ?: [];
} catch (Exception $e) {
    echo "ERROR fetching queue: " . $e->getMessage() . "\n";
    exit(1);
}

if (empty($rows)) {
    echo "Nothing due.\n";
    exit(0);
}

// Mark as processing to prevent double-delivery across concurrent workers
$ids          = array_column($rows, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
try {
    $conn->prepare(
        "UPDATE notification_queue SET status = 'processing'
         WHERE id IN ($placeholders) AND status = 'pending'"
    )->execute($ids);
} catch (Exception $e) {
    echo "ERROR claiming rows: " . $e->getMessage() . "\n";
    exit(1);
}

// Re-fetch only rows we successfully claimed (avoids processing rows another
// worker grabbed between SELECT and UPDATE)
try {
    $rows = $db->fetchAll(
        "SELECT * FROM notification_queue
         WHERE id IN ($placeholders) AND status = 'processing'",
        $ids
    ) ?: [];
} catch (Exception $e) {
    echo "ERROR re-fetching claimed rows: " . $e->getMessage() . "\n";
    exit(1);
}

$succeeded = $failed = $suppressed = $deferred = 0;

// ---- 2. Dispatch each claimed row ------------------------------------------
foreach ($rows as $row) {
    $attempts = (int) $row['attempts'] + 1;
    $result   = $ns->dispatch($row);

    if ($result['ok'] || ($result['reason'] ?? '') === 'dry_run') {
        $conn->prepare(
            "UPDATE notification_queue SET status = 'sent', attempts = ? WHERE id = ?"
        )->execute([$attempts, $row['id']]);
        $succeeded++;
        $ch = $result['channel'] ?? 'dry_run';
        echo "  SENT   [{$row['journey_key']}#{$row['step']}] → {$row['email']} via $ch\n";

    } elseif (($result['reason'] ?? '') === 'quiet_hours') {
        // Defer to next quiet-hours boundary (next morning)
        $settings   = Settings::getInstance();
        $nextWindow = strtotime('tomorrow ' . $settings->get('notify_quiet_end', '08:00'));
        $conn->prepare(
            "UPDATE notification_queue SET status = 'pending', attempts = ?, send_after = ? WHERE id = ?"
        )->execute([$attempts, date('Y-m-d H:i:s', $nextWindow), $row['id']]);
        $deferred++;
        echo "  DEFER  [{$row['journey_key']}#{$row['step']}] → {$row['email']} (quiet hours)\n";

    } elseif (in_array($result['reason'] ?? '', [
        'opted_out', 'bounced', 'complained', 'all_channels_failed_or_suppressed',
        'daily_cap', 'weekly_cap', 'journey_disabled',
    ], true)) {
        $conn->prepare(
            "UPDATE notification_queue
             SET status = 'suppressed', attempts = ?, cancelled_reason = ?
             WHERE id = ?"
        )->execute([$attempts, $result['reason'], $row['id']]);
        $suppressed++;
        echo "  SKIP   [{$row['journey_key']}#{$row['step']}] → {$row['email']} ({$result['reason']})\n";

    } else {
        // Transient failure — retry with exponential backoff
        if ($attempts >= MAX_RETRIES) {
            $conn->prepare(
                "UPDATE notification_queue SET status = 'failed', attempts = ? WHERE id = ?"
            )->execute([$attempts, $row['id']]);
            $failed++;
            echo "  FAILED [{$row['journey_key']}#{$row['step']}] → {$row['email']} (max retries)\n";
        } else {
            $delay  = RETRY_DELAYS[min($attempts - 1, count(RETRY_DELAYS) - 1)];
            $nextAt = date('Y-m-d H:i:s', time() + $delay);
            $conn->prepare(
                "UPDATE notification_queue
                 SET status = 'pending', attempts = ?, send_after = ?
                 WHERE id = ?"
            )->execute([$attempts, $nextAt, $row['id']]);
            $failed++;
            $delayMin = round($delay / 60);
            echo "  RETRY  [{$row['journey_key']}#{$row['step']}] → {$row['email']} (attempt $attempts, retry in {$delayMin}m)\n";
        }
    }
}

// ---- 3. Release stuck 'processing' rows from crashed prior runs ------------
try {
    $stuckCutoff = date('Y-m-d H:i:s', time() - 300);
    $conn->prepare(
        "UPDATE notification_queue SET status = 'pending'
         WHERE status = 'processing' AND created_at < ?"
    )->execute([$stuckCutoff]);
} catch (Exception $e) { /* non-fatal */ }

$elapsed = round(microtime(true) - $start, 2);
echo date('[Y-m-d H:i:s]')
    . " Done. Sent: $succeeded, Failed: $failed, Suppressed: $suppressed, Deferred: $deferred ({$elapsed}s)\n";
