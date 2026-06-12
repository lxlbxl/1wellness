<?php
/**
 * Posterior Recompute Worker — run hourly via cron:
 *   0 * * * * php /path/to/backend/cron/recompute_posteriors.php
 *
 * For every running experiment:
 *  - refresh exposures/conversions/revenue per variant from funnel_tracking
 *  - update Beta posteriors (alpha = 1+conv, beta = 1+exp-conv)
 *  - roll daily counts into variant_metrics_daily
 *  - transition burn_in -> active after burn_in_hours
 *  - cache Monte Carlo P(best)/expected loss and auto-conclude when the
 *    decision rule holds (winner promoted to 100% traffic, webhook fired)
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/classes/Database.php';
require_once APP_ROOT . '/classes/ExperimentManager.php';

set_time_limit(300);

echo '[' . date('Y-m-d H:i:s') . "] Recomputing A/B posteriors...\n";

try {
    $db = Database::getInstance();
    if ($db->isFileStorage()) {
        echo "No database connection — skipping.\n";
        exit(0);
    }

    $manager = new ExperimentManager();
    $report = $manager->recomputePosteriors();

    if (empty($report)) {
        echo "No running experiments.\n";
    }
    foreach ($report as $entry) {
        $actions = empty($entry['actions']) ? 'updated' : implode('; ', $entry['actions']);
        echo "  #{$entry['experiment_id']} {$entry['name']}: $actions\n";
    }
    echo '[' . date('Y-m-d H:i:s') . "] Done.\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    error_log('recompute_posteriors: ' . $e->getMessage());
    exit(1);
}
