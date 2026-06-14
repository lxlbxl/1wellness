<?php
/**
 * A/B Engine end-to-end dry run (pre-launch checklist item).
 *
 * Runs the full lifecycle against a throwaway SQLite database:
 *   create webhook -> create experiment -> start (burn-in) -> assign
 *   sessions -> simulate funnel events -> posterior recompute ->
 *   burn-in transition -> auto-conclusion -> winner promotion ->
 *   webhook queue verification.
 *
 * Usage: php backend/tests/ab_engine_dryrun.php
 */

error_reporting(E_ALL & ~E_DEPRECATED);

define('APP_ROOT', dirname(__DIR__));
define('DB_TYPE', 'sqlite');
define('DB_PATH', sys_get_temp_dir() . '/1w_ab_dryrun_' . getmypid() . '.db');

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/classes/Database.php';
require_once APP_ROOT . '/classes/ABSchema.php';
require_once APP_ROOT . '/classes/ExperimentManager.php';
require_once APP_ROOT . '/classes/WebhookDispatcher.php';
require_once APP_ROOT . '/classes/Bandit.php';

$pass = 0;
$fail = 0;

function check($label, $cond, $detail = '')
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  PASS  $label\n";
    } else {
        $fail++;
        echo "  FAIL  $label" . ($detail ? " — $detail" : '') . "\n";
    }
}

echo "A/B Engine dry run — SQLite at " . DB_PATH . "\n\n";

$db = Database::getInstance();
check('Database connection (not file storage)', !$db->isFileStorage());

// ---- 1. Schema ----
$ok = ABSchema::ensure();
check('Schema installed', $ok);
$pdo = $db->getConnection();
foreach (['experiments', 'variants', 'assignments', 'variant_metrics_daily', 'ai_insights', 'webhooks'] as $t) {
    check("Table exists: $t", ABSchema::tableExists($pdo, 'sqlite', $t));
}
$cols = array_column($db->fetchAll("PRAGMA table_info(funnel_tracking)"), 'name');
check('funnel_tracking extended', in_array('experiment_id', $cols) && in_array('variant_id', $cols) && in_array('revenue', $cols));
$prompt = $db->fetch("SELECT id FROM system_prompts WHERE prompt_key = 'ab_diagnostic_agent'");
check('AI prompts seeded', (bool) $prompt);

// ---- 2. Webhooks ----
$dispatcher = new WebhookDispatcher();
$wh = $dispatcher->createWebhook([
    'name' => 'Dry-run hook',
    'url' => 'https://example.com/hook',
    'events' => ['experiment.started', 'experiment.concluded', 'sale.completed'],
]);
check('Webhook created', isset($wh['id']), json_encode($wh));
check('Webhook secret auto-generated', !empty($wh['secret']));
$bad = $dispatcher->createWebhook(['name' => 'x', 'url' => 'notaurl', 'events' => ['sale.completed']]);
check('Webhook URL validation rejects garbage', isset($bad['error']));
$bad2 = $dispatcher->createWebhook(['name' => 'x', 'url' => 'https://ok.com', 'events' => ['fake.event']]);
check('Webhook event validation rejects unknown events', isset($bad2['error']));

$upd = $dispatcher->updateWebhook($wh['id'], ['events' => ['experiment.concluded']]);
check('Webhook update works', ($upd['events'] ?? null) === ['experiment.concluded']);
$dispatcher->updateWebhook($wh['id'], ['events' => ['experiment.started', 'experiment.concluded', 'sale.completed']]);

// ---- 3. Experiment creation ----
$manager = new ExperimentManager();
$result = $manager->createExperiment([
    'funnel_name' => 'pcos',
    'name' => 'Dry run: hero headline',
    'stage' => 'landing',
    'primary_metric' => 'assessment_start',
    'burn_in_hours' => 1,
    'min_samples_per_variant' => 50,
    'min_exposure_floor' => 0.10,
    'decision_p_best' => 0.95,
    'decision_expected_loss' => 0.01,
], [
    ['name' => 'Control', 'type' => 'control'],
    ['name' => 'B: urgency headline', 'type' => 'element', 'overrides' => [
        'text' => ["[data-exp='headline']" => 'Your hormones are not broken. Your plan was.'],
    ]],
]);
check('Experiment created', isset($result['id']), json_encode($result));
$expId = $result['id'] ?? 0;

$dup = $manager->createExperiment([
    'funnel_name' => 'pcos', 'name' => 'Dup', 'stage' => 'landing', 'primary_metric' => 'assessment_start',
], [['name' => 'Control', 'type' => 'control'], ['name' => 'B', 'type' => 'element']]);
check('Concurrency rule blocks 2nd landing experiment on same funnel', isset($dup['error']));

$badVariant = $manager->createExperiment([
    'funnel_name' => 'acne', 'name' => 'X', 'stage' => 'landing', 'primary_metric' => 'assessment_start',
], [['name' => 'Control', 'type' => 'control'], ['name' => 'S', 'type' => 'structural', 'directory' => 'acne__nonexistent']]);
check('Structural variant validation requires existing directory', isset($badVariant['error']));

// ---- 4. Start -> burn-in ----
$start = $manager->startExperiment($expId);
check('Experiment starts into burn_in', ($start['status'] ?? '') === 'burn_in', json_encode($start));
$queued = $db->fetch("SELECT COUNT(*) c FROM webhook_queue WHERE event = 'experiment.started'");
check('experiment.started webhook enqueued', (int) $queued['c'] === 1);

// ---- 5. Assignment: sticky + both variants served in burn-in ----
$exp = $manager->getExperiment($expId);
$variantIds = array_column($exp['variants'], 'id');
$seen = [];
for ($i = 0; $i < 60; $i++) {
    $sid = "sess_dryrun_$i";
    $a = $manager->assignSession($sid, 'pcos', []);
    check_once_assignment: {
        $vid = $a[$expId]['id'] ?? null;
        $seen[$vid] = ($seen[$vid] ?? 0) + 1;
    }
    // Stickiness: re-assign returns the same variant
    if ($i === 0) {
        $b = $manager->assignSession($sid, 'pcos', []);
        check('Assignment is sticky across requests', ($b[$expId]['id'] ?? -1) === $vid);
    }
    $manager->logExposure($sid, $expId, $vid, 'pcos', '/pcos/', '127.0.0.1', 'dryrun-agent');
    // Same-day duplicate exposure should dedup
    if ($i === 0) {
        $dupExp = $manager->logExposure($sid, $expId, $vid, 'pcos', '/pcos/', '127.0.0.1', 'dryrun-agent');
        check('Exposure dedup per session/day', $dupExp === false);
    }
}
check('Burn-in serves both variants', count(array_filter($seen)) === 2, json_encode($seen));

// ---- 6. Simulate conversions: variant B converts much better ----
list($controlId, $variantBId) = $variantIds;
$rows = $db->fetchAll("SELECT session_id, variant_id FROM assignments WHERE experiment_id = " . (int) $expId);
foreach ($rows as $r) {
    $isB = (int) $r['variant_id'] === (int) $variantBId;
    $convRate = $isB ? 0.60 : 0.10;
    if (mt_rand() / mt_getrandmax() < $convRate) {
        $db->insert('funnel_tracking', [
            'session_id' => $r['session_id'],
            'funnel_name' => 'pcos',
            'step_name' => 'assessment_start',
            'event_type' => 'assessment_start',
            'experiment_id' => $expId,
            'variant_id' => $r['variant_id'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

// ---- 7. Recompute: burn-in must NOT transition yet (1h not elapsed) ----
$manager->recomputePosteriors();
$exp = $manager->getExperiment($expId, false);
check('Burn-in holds before burn_in_hours elapse', $exp['status'] === 'burn_in');

// Force burn-in elapsed
$db->query("UPDATE experiments SET started_at = ? WHERE id = ?", [date('Y-m-d H:i:s', time() - 7200), $expId]);
$manager->recomputePosteriors();
$exp = $manager->getExperiment($expId, false);
check('burn_in -> active after burn_in_hours', $exp['status'] === 'active');

$variants = $manager->getVariants($expId);
foreach ($variants as $v) {
    check("Posteriors updated for '{$v['name']}' (alpha=" . round($v['alpha'], 1) . ", beta=" . round($v['beta'], 1) . ")",
        (float) $v['alpha'] > 1 || (float) $v['beta'] > 1);
}

// ---- 8. Thompson sampling now favors B ----
$bandit = new Bandit();
$expRow = $manager->getExperiment($expId, false);
$serveable = $manager->getServeableVariants($expId);
$bCount = 0;
for ($i = 0; $i < 300; $i++) {
    $picked = $bandit->assign($expRow, $serveable);
    if ((int) $picked['id'] === (int) $variantBId) $bCount++;
}
check("Bandit favors the better variant ($bCount/300 draws to B)", $bCount > 180);

// ---- 9. Push exposures past min_samples and auto-conclude ----
for ($i = 60; $i < 560; $i++) {
    $sid = "sess_dryrun_$i";
    $a = $manager->assignSession($sid, 'pcos', []);
    if (empty($a[$expId])) {
        break; // experiment auto-concluded mid-loop — stop sending traffic
    }
    $vid = $a[$expId]['id'];
    $manager->logExposure($sid, $expId, $vid, 'pcos', '/pcos/', '127.0.0.1', 'dryrun-agent');
    $isB = (int) $vid === (int) $variantBId;
    if (mt_rand() / mt_getrandmax() < ($isB ? 0.60 : 0.10)) {
        $db->insert('funnel_tracking', [
            'session_id' => $sid, 'funnel_name' => 'pcos', 'step_name' => 'assessment_start',
            'event_type' => 'assessment_start', 'experiment_id' => $expId, 'variant_id' => $vid,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
    // Periodic recompute (hourly cron in production) so the bandit sees
    // fresh exposure counts and the 10% floor keeps feeding the control.
    if ($i % 100 === 0) {
        $manager->recomputePosteriors();
    }
}
$report = $manager->recomputePosteriors();
$exp = $manager->getExperiment($expId, false);
check('Auto-conclusion fired (P(best) + expected loss + min samples)', $exp['status'] === 'concluded', json_encode($report));
check('Winner is variant B', (int) $exp['winner_variant_id'] === (int) $variantBId);

$winnerRow = $manager->getVariant($variantBId);
check("Winner promoted (status=winner)", $winnerRow['status'] === 'winner');
$controlRow = $manager->getVariant($controlId);
check('Loser killed on conclusion', $controlRow['status'] === 'killed');

$concludedHook = $db->fetch("SELECT COUNT(*) c FROM webhook_queue WHERE event = 'experiment.concluded'");
check('experiment.concluded webhook enqueued', (int) $concludedHook['c'] === 1);

// Post-conclusion traffic goes 100% to winner
$a = $manager->assignSession('sess_post_conclude', 'pcos', []);
check('Concluded experiment no longer assigns traffic (not running)', empty($a));

// ---- 10. Daily metrics rolled ----
$daily = $db->fetch("SELECT COUNT(*) c FROM variant_metrics_daily");
check('variant_metrics_daily populated', (int) $daily['c'] > 0);

// ---- 11. Stats package ----
$stats = $manager->getExperimentStats($expId);
check('Stats package complete', isset($stats['variants'][0]['waterfall']['view']) && isset($stats['daily_trend']));

// ---- 12. Webhook payload + HMAC shape ----
$payload = $dispatcher->buildPayload($wh['id'], 'sale.completed', ['amount' => 97]);
check('Payload envelope shape', $payload['event'] === 'sale.completed' && isset($payload['timestamp'], $payload['data']));
$queuedRow = $db->fetch("SELECT payload FROM webhook_queue LIMIT 1");
$decoded = json_decode($queuedRow['payload'], true);
check('Queued payload is valid JSON envelope', isset($decoded['event'], $decoded['data']));

// ---- Cleanup ----
@unlink(DB_PATH);

echo "\nResult: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
