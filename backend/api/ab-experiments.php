<?php
/**
 * A/B Experiments Management API
 *
 * Auth: admin session OR X-API-KEY (N8N_API_KEY). See docs/API-REFERENCE.md.
 *
 * GET  ?action=list[&funnel=pcos][&status=active]     List experiments (+variants)
 * GET  ?action=get&id=1                               One experiment (+variants)
 * GET  ?action=stats&id=1                             Full stats package (waterfall, trend, insight, queue)
 * GET  ?action=approval_queue                         All AI challengers pending approval
 * GET  ?action=meta                                   Valid funnels/stages/metrics/events
 *
 * POST {"action":"create", "experiment":{...}, "variants":[{...},...]}
 * POST {"action":"update", "id":1, "experiment":{...}}
 * POST {"action":"start","id":1} | {"action":"pause","id":1} | {"action":"archive","id":1}
 * POST {"action":"conclude","id":1,"winner_variant_id":2}
 * POST {"action":"delete","id":1}                     (draft/archived only)
 * POST {"action":"add_variant","id":1,"variant":{...}}
 * POST {"action":"update_overrides","variant_id":2,"overrides":{...},"name":"optional"}
 * POST {"action":"kill_variant","variant_id":2[,"generate_challenger":true]}
 * POST {"action":"approve_variant","variant_id":2} | {"action":"reject_variant","variant_id":2}
 * POST {"action":"recompute"}                         Force posterior recompute (normally hourly cron)
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
require_once __DIR__ . '/../classes/ExperimentManager.php';
require_once __DIR__ . '/lib/api_auth.php';

requireApiAuth();

function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function respondResult($result)
{
    if (isset($result['error'])) {
        respond(['success' => false, 'error' => $result['error']], 422);
    }
    respond(array_merge(['success' => true], is_array($result) ? $result : []));
}

try {
    $manager = new ExperimentManager();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'list';

        switch ($action) {
            case 'list':
                $filters = [];
                if (!empty($_GET['funnel'])) $filters['funnel'] = $_GET['funnel'];
                if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
                if (!empty($_GET['include_archived'])) $filters['include_archived'] = true;
                respond(['success' => true, 'experiments' => $manager->listExperiments($filters)]);

            case 'get':
                $exp = $manager->getExperiment((int) ($_GET['id'] ?? 0));
                if (!$exp) respond(['success' => false, 'error' => 'Not found'], 404);
                respond(['success' => true, 'experiment' => $exp]);

            case 'stats':
                $stats = $manager->getExperimentStats((int) ($_GET['id'] ?? 0));
                if (!$stats) respond(['success' => false, 'error' => 'Not found'], 404);
                respond(['success' => true, 'stats' => $stats]);

            case 'approval_queue':
                respond(['success' => true, 'queue' => $manager->getApprovalQueue()]);

            case 'meta':
                respond([
                    'success' => true,
                    'funnels' => ExperimentManager::FUNNELS,
                    'stages' => ExperimentManager::STAGES,
                    'metrics' => array_keys(ExperimentManager::METRICS),
                    'events' => ExperimentManager::EVENTS,
                ]);

            default:
                respond(['success' => false, 'error' => 'Unknown action'], 400);
        }
    }

    // POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $action = $input['action'] ?? '';
    $id = (int) ($input['id'] ?? 0);
    $variantId = (int) ($input['variant_id'] ?? 0);

    switch ($action) {
        case 'create':
            $result = $manager->createExperiment($input['experiment'] ?? [], $input['variants'] ?? []);
            respondResult($result);

        case 'update':
            respondResult($manager->updateExperiment($id, $input['experiment'] ?? []));

        case 'start':
            respondResult($manager->startExperiment($id));

        case 'pause':
            respondResult($manager->pauseExperiment($id));

        case 'archive':
            respondResult($manager->archiveExperiment($id));

        case 'conclude':
            respondResult($manager->concludeExperiment($id, (int) ($input['winner_variant_id'] ?? 0)));

        case 'delete':
            respondResult($manager->deleteExperiment($id));

        case 'add_variant':
            $exp = $manager->getExperiment($id, false);
            if (!$exp) respond(['success' => false, 'error' => 'Experiment not found'], 404);
            $variant = $input['variant'] ?? [];
            $err = $manager->validateVariant($variant, $exp['funnel_name']);
            if ($err) respond(['success' => false, 'error' => $err], 422);
            $vid = $manager->insertVariant($id, $variant);
            respond(['success' => true, 'variant_id' => (int) $vid]);

        case 'update_overrides':
            respondResult($manager->updateVariantOverrides($variantId, $input['overrides'] ?? null, $input['name'] ?? null));

        case 'kill_variant':
            respondResult($manager->killVariant($variantId, $input['reason'] ?? 'api', !empty($input['generate_challenger'])));

        case 'approve_variant':
            respondResult($manager->approveVariant($variantId));

        case 'reject_variant':
            respondResult($manager->rejectVariant($variantId));

        case 'recompute':
            respond(['success' => true, 'report' => $manager->recomputePosteriors()]);

        default:
            respond(['success' => false, 'error' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    error_log('ab-experiments API error: ' . $e->getMessage());
    respond(['success' => false, 'error' => 'Internal error: ' . $e->getMessage()], 500);
}
