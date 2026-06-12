<?php
/**
 * A/B Engine Migration Runner (driver-aware)
 *
 * Usage (CLI):  php backend/database/migrations/migrate.php
 *
 * Creates the experimentation tables on whichever driver the app is
 * configured for (MySQL in production, SQLite for local dev) and seeds
 * the AI system prompts. Idempotent.
 */

if (php_sapi_name() !== 'cli') {
    // Allow web execution only for authenticated admins
    require_once dirname(__DIR__, 2) . '/admin/auth.php';
}

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/classes/Database.php';
require_once APP_ROOT . '/classes/ABSchema.php';

$db = Database::getInstance();

if ($db->isFileStorage()) {
    echo "ERROR: Database connection unavailable (file-storage fallback active).\n";
    echo "The A/B engine requires MySQL (production) or SQLite (development).\n";
    exit(1);
}

$pdo = $db->getConnection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Connected via driver: $driver\n";

if ($driver !== 'mysql') {
    echo "WARNING: Production deployments must run the tracking/experiment tables on MySQL.\n";
    echo "         ($driver is fine for local development only.)\n";
}

try {
    ABSchema::install($pdo, $driver);
    echo "Tables created/verified: experiments, variants, assignments, variant_metrics_daily, ai_insights, webhooks\n";
    echo "funnel_tracking extended with experiment_id, variant_id, revenue\n";

    ABSchema::seedPrompts($db);
    echo "System prompts seeded: ab_diagnostic_agent, ab_challenger_agent, ab_compliance_agent\n";

    echo "\nMigration 001_ab_engine complete.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
