<?php
/**
 * Weekly AI Diagnostic Agent — run weekly via cron:
 *   0 6 * * 1 php /path/to/backend/cron/ai_diagnostics.php
 *
 * Per funnel with running experiments:
 *  - builds a structured performance payload (variant tables, step
 *    drop-offs vs baseline, device segments, concurrent experiments)
 *  - asks the `ab_diagnostic_agent` system prompt (via AIOrchestrator)
 *  - stores the JSON verdict in ai_insights
 *  - fires the experiment.insight_ready webhook (n8n -> WhatsApp/Slack)
 *
 * Optional: pass --challengers to auto-generate AI challengers for any
 * variant the diagnostic flags as a clear loser that is already killed.
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/classes/Database.php';
require_once APP_ROOT . '/classes/ExperimentManager.php';
require_once APP_ROOT . '/classes/WebhookDispatcher.php';
require_once APP_ROOT . '/classes/AIOrchestrator.php';

set_time_limit(600);

echo '[' . date('Y-m-d H:i:s') . "] AI diagnostics starting...\n";

$db = Database::getInstance();
if ($db->isFileStorage()) {
    echo "No database connection — skipping.\n";
    exit(0);
}

$manager = new ExperimentManager();
$dispatcher = new WebhookDispatcher();
$ai = new AIOrchestrator();

/** Device split from user agents for a variant. */
function deviceSegments($db, $variantId)
{
    $rows = $db->fetchAll(
        "SELECT user_agent FROM funnel_tracking WHERE variant_id = :v AND event_type = 'view' LIMIT 2000",
        [':v' => $variantId]
    );
    $mobile = 0;
    $desktop = 0;
    foreach ($rows as $r) {
        if (preg_match('/Mobi|Android|iPhone|iPad/i', $r['user_agent'] ?? '')) {
            $mobile++;
        } else {
            $desktop++;
        }
    }
    return ['mobile' => $mobile, 'desktop' => $desktop];
}

function parseAiJson($response)
{
    if (is_array($response)) {
        return isset($response['error']) ? null : $response;
    }
    if (!is_string($response)) {
        return null;
    }
    $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($response));
    $parsed = json_decode($clean, true);
    if ($parsed === null && preg_match('/\{.*\}/s', $clean, $m)) {
        $parsed = json_decode($m[0], true);
    }
    return $parsed;
}

$processed = 0;

foreach (ExperimentManager::FUNNELS as $funnel) {
    $experiments = $manager->getRunningExperiments($funnel);
    if (empty($experiments)) {
        continue;
    }

    $baseline = $manager->getFunnelBaseline($funnel, 30);

    $payload = [
        'funnel' => $funnel,
        'generated_at' => date('c'),
        'funnel_baseline' => $baseline,
        'concurrent_experiments' => array_map(function ($e) {
            return ['id' => (int) $e['id'], 'name' => $e['name'], 'stage' => $e['stage'], 'primary_metric' => $e['primary_metric']];
        }, $experiments),
        'experiments' => [],
    ];

    foreach ($experiments as $exp) {
        $stats = $manager->getExperimentStats($exp['id']);
        $variantRows = [];
        foreach ($stats['variants'] as $v) {
            $variantRows[] = [
                'variant' => $v['name'],
                'status' => $v['status'],
                'exposures' => $v['exposures'],
                'waterfall' => $v['waterfall'],
                'conversion_rate' => $v['conversion_rate'],
                'rpv' => $v['rpv'],
                'p_best' => $v['p_best'],
                'traffic_share' => $v['traffic_share'],
                'segments' => deviceSegments($db, $v['id']),
            ];
        }
        $payload['experiments'][] = [
            'id' => (int) $exp['id'],
            'name' => $exp['name'],
            'stage' => $exp['stage'],
            'primary_metric' => $exp['primary_metric'],
            'days_running' => $stats['days_running'],
            'variants' => $variantRows,
        ];
    }

    echo "  [$funnel] querying diagnostic agent (" . count($experiments) . " experiments)...\n";
    $response = $ai->generateResponse(
        'ab_diagnostic_agent',
        "Analyze this experiment data:\n" . json_encode($payload, JSON_UNESCAPED_SLASHES)
    );

    $verdict = parseAiJson($response);
    if (!is_array($verdict)) {
        echo "  [$funnel] WARNING: diagnostic agent returned unparseable output — skipped.\n";
        error_log("ai_diagnostics[$funnel]: bad AI output: " . substr(is_string($response) ? $response : json_encode($response), 0, 300));
        continue;
    }

    // Store one insight per experiment (so detail pages can show it) plus the funnel-level record
    $insightId = $db->insert('ai_insights', [
        'experiment_id' => count($experiments) === 1 ? (int) $experiments[0]['id'] : null,
        'funnel_name' => $funnel,
        'insight_type' => 'diagnostic',
        'content' => json_encode($verdict, JSON_UNESCAPED_SLASHES),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    if (count($experiments) > 1) {
        foreach ($experiments as $exp) {
            $db->insert('ai_insights', [
                'experiment_id' => (int) $exp['id'],
                'funnel_name' => $funnel,
                'insight_type' => 'diagnostic',
                'content' => json_encode($verdict, JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // Summarise top leak for the notification
    $summary = 'Diagnostic ready';
    if (!empty($verdict['funnel_leaks'][0])) {
        $leak = $verdict['funnel_leaks'][0];
        $summary = "Top leak: {$leak['stage']} (severity {$leak['severity']})";
    }

    $dispatcher->dispatch('experiment.insight_ready', [
        'insight_id' => (int) $insightId,
        'funnel' => $funnel,
        'experiments' => count($experiments),
        'summary' => $summary,
        'suggestions' => array_slice($verdict['suggestions'] ?? [], 0, 3),
    ]);

    $processed++;
    echo "  [$funnel] insight stored (#$insightId): $summary\n";
}

// Optional: generate challengers for killed variants that have none yet
if (in_array('--challengers', $argv ?? [])) {
    require_once APP_ROOT . '/classes/ChallengerGenerator.php';
    $gen = new ChallengerGenerator();
    $killed = $db->fetchAll(
        "SELECT v.id, v.name FROM variants v
         JOIN experiments e ON e.id = v.experiment_id
         WHERE v.status = 'killed' AND v.type = 'element'
           AND e.status IN ('burn_in','active')
           AND NOT EXISTS (
               SELECT 1 FROM variants c
               WHERE c.experiment_id = v.experiment_id
                 AND c.source = 'ai_challenger' AND c.status IN ('pending_approval','active')
           )"
    );
    foreach ($killed as $k) {
        echo "  Generating challenger to replace killed variant '{$k['name']}'...\n";
        $result = $gen->generateForKilledVariant($k['id']);
        echo isset($result['error'])
            ? "    FAILED: {$result['error']}\n"
            : "    Proposed '{$result['name']}' ({$result['compliance']}) — awaiting approval\n";
    }
}

echo '[' . date('Y-m-d H:i:s') . "] Done. Funnels processed: $processed\n";
