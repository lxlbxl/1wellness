<?php
/**
 * Webhooks Management API
 *
 * Auth: admin session OR X-API-KEY (N8N_API_KEY). See docs/WEBHOOKS.md.
 *
 * GET  ?action=list                      All webhook subscriptions
 * GET  ?action=get&id=wh_xxx             One subscription
 * GET  ?action=events                    Event catalog (key, label, description, sample payload)
 * GET  ?action=deliveries&id=wh_xxx      Recent delivery attempts for a webhook
 *
 * POST {"action":"create","name":"...","url":"https://...","events":["sale.completed"],
 *       "secret":"optional","headers":{"X-Custom":"v"},"method":"POST"}
 * POST {"action":"update","id":"wh_xxx", ...same fields...}
 * POST {"action":"delete","id":"wh_xxx"}
 * POST {"action":"test","id":"wh_xxx"}    Send a signed sample payload now
 * POST {"action":"pause","id":"wh_xxx"} | {"action":"resume","id":"wh_xxx"}
 * POST {"action":"dispatch","event":"sale.completed","data":{...}}
 *       Manually fire an event into the queue (for n8n round-trips/testing)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/WebhookDispatcher.php';
require_once __DIR__ . '/lib/api_auth.php';

requireApiAuth();

function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

/** Hide the signing secret in list responses (full value only on create). */
function maskSecret($webhook)
{
    if (!empty($webhook['secret'])) {
        $webhook['secret_preview'] = substr($webhook['secret'], 0, 6) . '…';
        unset($webhook['secret']);
    }
    return $webhook;
}

try {
    $dispatcher = new WebhookDispatcher();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'list';
        switch ($action) {
            case 'list':
                respond(['success' => true, 'webhooks' => array_map('maskSecret', $dispatcher->listWebhooks())]);

            case 'get':
                $w = $dispatcher->getWebhook($_GET['id'] ?? '');
                if (!$w) respond(['success' => false, 'error' => 'Not found'], 404);
                respond(['success' => true, 'webhook' => maskSecret($w)]);

            case 'events':
                $catalog = [];
                foreach (WebhookDispatcher::eventCatalog() as $key => $meta) {
                    $catalog[] = [
                        'event' => $key,
                        'label' => $meta['label'],
                        'description' => $meta['description'],
                        'sample_payload' => $dispatcher->samplePayload($key),
                    ];
                }
                respond(['success' => true, 'events' => $catalog]);

            case 'deliveries':
                respond(['success' => true, 'deliveries' => $dispatcher->recentDeliveries($_GET['id'] ?? '', (int) ($_GET['limit'] ?? 20))]);

            default:
                respond(['success' => false, 'error' => 'Unknown action'], 400);
        }
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $action = $input['action'] ?? '';
    $id = $input['id'] ?? '';

    switch ($action) {
        case 'create':
            $result = $dispatcher->createWebhook($input);
            if (isset($result['error'])) respond(['success' => false, 'error' => $result['error']], 422);
            // Full secret returned exactly once, at creation
            respond(['success' => true, 'webhook' => $result], 201);

        case 'update':
            $result = $dispatcher->updateWebhook($id, $input);
            if (isset($result['error'])) respond(['success' => false, 'error' => $result['error']], 422);
            respond(['success' => true, 'webhook' => maskSecret($result)]);

        case 'delete':
            $dispatcher->deleteWebhook($id);
            respond(['success' => true]);

        case 'test':
            $result = $dispatcher->sendTest($id);
            respond(array_merge(['success' => (bool) $result['success']], $result), $result['success'] ? 200 : 502);

        case 'pause':
        case 'resume':
            $result = $dispatcher->updateWebhook($id, ['status' => $action === 'pause' ? 'paused' : 'active']);
            if (isset($result['error'])) respond(['success' => false, 'error' => $result['error']], 422);
            respond(['success' => true, 'status' => $result['status']]);

        case 'dispatch':
            $event = $input['event'] ?? '';
            if (!WebhookDispatcher::isValidEvent($event)) {
                respond(['success' => false, 'error' => 'Unknown event. GET ?action=events for the catalog.'], 422);
            }
            $count = $dispatcher->dispatch($event, is_array($input['data'] ?? null) ? $input['data'] : []);
            respond(['success' => true, 'enqueued' => $count]);

        default:
            respond(['success' => false, 'error' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    error_log('webhooks API error: ' . $e->getMessage());
    respond(['success' => false, 'error' => 'Internal error: ' . $e->getMessage()], 500);
}
