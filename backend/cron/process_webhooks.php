<?php
/**
 * Webhook Processor Worker
 * Run this script via cron every minute
 * * * * * php /path/to/backend/cron/process_webhooks.php
 */

// Define app root
define('APP_ROOT', dirname(__DIR__));

// Load configuration
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/classes/Database.php';

// Set time limit to avoid timeouts
set_time_limit(55); // Slightly less than 60s to avoid overlap if running every minute

$db = Database::getInstance();
$processed = 0;
$failed = 0;

echo "[" . date('Y-m-d H:i:s') . "] Starting webhook processor...\n";

// Fetch pending webhooks
// We look for 'pending' items OR 'failed' items that are due for retry
$sql = "SELECT * FROM webhook_queue 
        WHERE (status = 'pending' OR (status = 'failed' AND attempts < " . WEBHOOK_MAX_RETRIES . "))
        AND next_attempt <= '" . date('Y-m-d H:i:s') . "'
        ORDER BY next_attempt ASC
        LIMIT 10"; // Process in batches

try {
    if ($db->isFileStorage()) {
        processFileBasedQueue();
    } else {
        processDatabaseQueue($db);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Finished. Processed: $processed, Failed: $failed\n";

function processDatabaseQueue($db)
{
    global $processed, $failed;

    // Get items to process
    $stmt = $db->getConnection()->prepare("
        SELECT * FROM webhook_queue 
        WHERE (status = 'pending' OR (status = 'failed' AND attempts < " . WEBHOOK_MAX_RETRIES . "))
        AND next_attempt <= ?
        ORDER BY next_attempt ASC
        LIMIT 10
    ");
    $stmt->execute([date('Y-m-d H:i:s')]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        echo "No items to process.\n";
        return;
    }

    foreach ($items as $item) {
        // Atomic lock
        $updateStmt = $db->getConnection()->prepare("
            UPDATE webhook_queue 
            SET status = 'processing', updated_at = ? 
            WHERE id = ? AND status != 'processing'
        ");
        $updateStmt->execute([date('Y-m-d H:i:s'), $item['id']]);

        if ($updateStmt->rowCount() === 0) {
            // Item was picked up by another worker
            continue;
        }

        // Get webhook config
        $webhook = getWebhookConfig($db, $item['webhook_id']);
        if (!$webhook) {
            markAsFailed($db, $item, "Webhook configuration not found");
            $failed++;
            continue;
        }

        // Send webhook
        $payload = json_decode($item['payload'], true);
        $result = sendWebhook($webhook, $payload);

        if ($result['success']) {
            markAsCompleted($db, $item, $result['response']);
            $processed++;
        } else {
            scheduleRetry($db, $item, $result['error']);
            $failed++;
        }
    }
}

function processFileBasedQueue()
{
    // Basic file-based queue processing (less robust)
    global $processed, $failed;

    $file = APP_ROOT . '/database/data/webhook_queue.json';
    if (!file_exists($file))
        return;

    $queue = json_decode(file_get_contents($file), true) ?: [];
    $modified = false;

    foreach ($queue as &$item) {
        if ($item['status'] === 'pending' || ($item['status'] === 'failed' && $item['attempts'] < WEBHOOK_MAX_RETRIES)) {
            // Check if due (simple check)
            // In file mode we just process immediately for simplicity

            $item['status'] = 'processing';
            $webhook = getWebhookConfigFile($item['webhook_id']);

            if ($webhook) {
                $result = sendWebhook($webhook, $item['payload']);
                if ($result['success']) {
                    $item['status'] = 'completed';
                    $processed++;
                } else {
                    $item['attempts']++;
                    $item['status'] = 'failed'; // Simple retry logic for file mode
                    $failed++;
                }
            }
            $modified = true;
        }
    }

    if ($modified) {
        file_put_contents($file, json_encode($queue, JSON_PRETTY_PRINT));
    }
}

function getWebhookConfig($db, $id)
{
    $stmt = $db->getConnection()->prepare("SELECT * FROM webhooks WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getWebhookConfigFile($id)
{
    $file = APP_ROOT . '/database/data/webhooks.json';
    if (!file_exists($file))
        return null;
    $webhooks = json_decode(file_get_contents($file), true) ?: [];
    foreach ($webhooks as $w) {
        if ($w['id'] === $id)
            return $w;
    }
    return null;
}

function sendWebhook($webhook, $payload)
{
    $ch = curl_init();

    $headers = ['Content-Type: application/json'];

    // Add HMAC Signature for security
    $secret = $webhook['secret'] ?? ''; // Assuming we might add secrets later
    if ($secret) {
        $signature = hash_hmac('sha256', json_encode($payload), $secret);
        $headers[] = 'X-Webhook-Signature: ' . $signature;
    }

    if (!empty($webhook['headers'])) {
        $customHeaders = json_decode($webhook['headers'], true);
        if (is_array($customHeaders)) {
            foreach ($customHeaders as $key => $value) {
                $headers[] = $key . ': ' . $value;
            }
        }
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $webhook['url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $webhook['method'] ?? 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false // In production, set to true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'response' => $response];
    }

    return ['success' => false, 'error' => "HTTP $httpCode: $response"];
}

function markAsCompleted($db, $item, $response)
{
    $stmt = $db->getConnection()->prepare("
        UPDATE webhook_queue 
        SET status = 'completed', updated_at = ? 
        WHERE id = ?
    ");
    $stmt->execute([date('Y-m-d H:i:s'), $item['id']]);

    // Update webhook stats
    $stmt = $db->getConnection()->prepare("
        UPDATE webhooks 
        SET success_count = success_count + 1, last_triggered = ? 
        WHERE id = ?
    ");
    $stmt->execute([date('Y-m-d H:i:s'), $item['webhook_id']]);
}

function markAsFailed($db, $item, $error)
{
    $stmt = $db->getConnection()->prepare("
        UPDATE webhook_queue 
        SET status = 'failed', updated_at = ? 
        WHERE id = ?
    ");
    $stmt->execute([date('Y-m-d H:i:s'), $item['id']]);
}

function scheduleRetry($db, $item, $error)
{
    $attempts = $item['attempts'] + 1;

    if ($attempts >= WEBHOOK_MAX_RETRIES) {
        markAsFailed($db, $item, $error);
        return;
    }

    // Exponential backoff: 5m, 15m, 45m, 2h, 6h
    $delay = pow(3, $attempts) * 5; // minutes
    $nextAttempt = date('Y-m-d H:i:s', strtotime("+$delay minutes"));

    $stmt = $db->getConnection()->prepare("
        UPDATE webhook_queue 
        SET status = 'failed', 
            attempts = ?, 
            next_attempt = ?, 
            updated_at = ? 
        WHERE id = ?
    ");
    $stmt->execute([$attempts, $nextAttempt, date('Y-m-d H:i:s'), $item['id']]);

    // Update webhook stats
    $stmt = $db->getConnection()->prepare("
        UPDATE webhooks 
        SET failure_count = failure_count + 1, last_triggered = ? 
        WHERE id = ?
    ");
    $stmt->execute([date('Y-m-d H:i:s'), $item['webhook_id']]);
}
