<?php
/**
 * A/B Engine Schema Installer
 *
 * Driver-aware DDL for the experimentation layer (Migration 001).
 * MySQL is the production target (per spec); SQLite is supported so the
 * engine runs in local development without a MySQL server.
 *
 * Idempotent — safe to call on every request via ABSchema::ensure().
 */

class ABSchema
{
    private static $ensured = false;

    /** Ensure all A/B engine tables exist. Cheap after first call per request. */
    public static function ensure()
    {
        if (self::$ensured) {
            return true;
        }

        $db = Database::getInstance();
        if ($db->isFileStorage()) {
            // File storage cannot support the engine; tables are JSON files on demand.
            self::$ensured = true;
            return false;
        }

        $pdo = $db->getConnection();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if (self::tableExists($pdo, $driver, 'experiments')
                && self::tableExists($pdo, $driver, 'webhooks')
                && self::tableExists($pdo, $driver, 'webhook_queue')) {
                self::$ensured = true;
                return true;
            }
            self::install($pdo, $driver);
            self::seedPrompts($db);
            self::$ensured = true;
            return true;
        } catch (Exception $e) {
            error_log('ABSchema ensure error: ' . $e->getMessage());
            return false;
        }
    }

    public static function tableExists($pdo, $driver, $table)
    {
        if ($driver === 'mysql') {
            $stmt = $pdo->query("SHOW TABLES LIKE '" . $table . "'");
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name='" . $table . "'");
        } else {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . $table . "'");
        }
        return (bool) $stmt->fetch();
    }

    /** Create all engine tables + extend funnel_tracking. */
    public static function install($pdo, $driver)
    {
        $mysql = ($driver === 'mysql');

        $pk      = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $bigPk   = $mysql ? 'BIGINT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $json    = $mysql ? 'JSON' : 'TEXT';
        $suffix  = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $enum    = function ($values, $default) use ($mysql) {
            $list = "'" . implode("','", $values) . "'";
            return $mysql
                ? "ENUM($list) DEFAULT '$default'"
                : "TEXT DEFAULT '$default' CHECK (%COL% IN ($list))";
        };

        $statements = [];

        $expStatus = $mysql
            ? "ENUM('draft','burn_in','active','paused','concluded','archived') DEFAULT 'draft'"
            : "TEXT DEFAULT 'draft'";
        $rewardType = $mysql ? "ENUM('binary','revenue') DEFAULT 'binary'" : "TEXT DEFAULT 'binary'";

        $statements[] = "CREATE TABLE IF NOT EXISTS experiments (
            id $pk,
            funnel_name VARCHAR(50) NOT NULL,
            name VARCHAR(120) NOT NULL,
            hypothesis TEXT,
            stage VARCHAR(50) NOT NULL,
            primary_metric VARCHAR(60) NOT NULL,
            reward_type $rewardType,
            status $expStatus,
            burn_in_hours INT DEFAULT 48,
            min_exposure_floor DECIMAL(4,3) DEFAULT 0.100,
            min_samples_per_variant INT DEFAULT 1000,
            decision_p_best DECIMAL(4,3) DEFAULT 0.950,
            decision_expected_loss DECIMAL(6,4) DEFAULT 0.0050,
            winner_variant_id INT NULL,
            started_at DATETIME NULL,
            concluded_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$suffix";

        $varType   = $mysql ? "ENUM('control','structural','element') NOT NULL" : "TEXT NOT NULL";
        $varStatus = $mysql ? "ENUM('pending_approval','active','killed','winner','rejected') DEFAULT 'active'" : "TEXT DEFAULT 'active'";
        $varSource = $mysql ? "ENUM('human','ai_challenger') DEFAULT 'human'" : "TEXT DEFAULT 'human'";
        $compl     = $mysql ? "ENUM('unchecked','compliant','non_compliant') DEFAULT 'unchecked'" : "TEXT DEFAULT 'unchecked'";

        $statements[] = "CREATE TABLE IF NOT EXISTS variants (
            id $pk,
            experiment_id INT NOT NULL,
            name VARCHAR(120) NOT NULL,
            type $varType,
            directory VARCHAR(120) NULL,
            overrides $json NULL,
            alpha DECIMAL(12,4) DEFAULT 1.0,
            beta DECIMAL(12,4) DEFAULT 1.0,
            exposures INT DEFAULT 0,
            conversions INT DEFAULT 0,
            revenue_total DECIMAL(12,2) DEFAULT 0,
            p_best DECIMAL(5,4) DEFAULT 0,
            expected_loss DECIMAL(8,6) DEFAULT 0,
            status $varStatus,
            source $varSource,
            ai_rationale TEXT NULL,
            compliance_status $compl,
            compliance_notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP" .
            ($mysql ? ", FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE" : "") .
        ")$suffix";

        $statements[] = "CREATE TABLE IF NOT EXISTS assignments (
            id $bigPk,
            session_id VARCHAR(100) NOT NULL,
            experiment_id INT NOT NULL,
            variant_id INT NOT NULL,
            assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP" .
            ($mysql ? ", UNIQUE KEY uq_session_experiment (session_id, experiment_id), KEY idx_assign_variant (variant_id)" : "") .
        ")$suffix";

        $statements[] = "CREATE TABLE IF NOT EXISTS variant_metrics_daily (
            id $pk,
            variant_id INT NOT NULL,
            metric_date DATE NOT NULL,
            exposures INT DEFAULT 0,
            assessment_starts INT DEFAULT 0,
            assessment_completes INT DEFAULT 0,
            results_views INT DEFAULT 0,
            plan_selects INT DEFAULT 0,
            checkout_inits INT DEFAULT 0,
            purchases INT DEFAULT 0,
            revenue DECIMAL(12,2) DEFAULT 0" .
            ($mysql ? ", UNIQUE KEY uq_variant_date (variant_id, metric_date)" : "") .
        ")$suffix";

        $insightType = $mysql ? "ENUM('diagnostic','suggestion','challenger_rationale')" : "TEXT";
        $statements[] = "CREATE TABLE IF NOT EXISTS ai_insights (
            id $pk,
            experiment_id INT NULL,
            funnel_name VARCHAR(50),
            insight_type $insightType,
            content $json NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$suffix";

        // Delivery queue (normally created by the base schema; ensured here
        // because the webhook feature depends on it)
        $whqStatus = $mysql ? "ENUM('pending','processing','completed','failed') DEFAULT 'pending'" : "TEXT DEFAULT 'pending'";
        $statements[] = "CREATE TABLE IF NOT EXISTS webhook_queue (
            id $pk,
            webhook_id VARCHAR(50) NOT NULL,
            event VARCHAR(50) NOT NULL,
            payload TEXT NOT NULL,
            status $whqStatus,
            attempts INT DEFAULT 0,
            last_attempt DATETIME,
            next_attempt DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$suffix";

        $whStatus = $mysql ? "ENUM('active','paused') DEFAULT 'active'" : "TEXT DEFAULT 'active'";
        $statements[] = "CREATE TABLE IF NOT EXISTS webhooks (
            id VARCHAR(50) PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            url TEXT NOT NULL,
            events TEXT NOT NULL,
            secret VARCHAR(128) NULL,
            headers TEXT NULL,
            method VARCHAR(10) DEFAULT 'POST',
            status $whStatus,
            success_count INT DEFAULT 0,
            failure_count INT DEFAULT 0,
            last_triggered DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$suffix";

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }

        // SQLite unique indexes (declared inline for MySQL above)
        if (!$mysql) {
            foreach ([
                "CREATE UNIQUE INDEX IF NOT EXISTS uq_session_experiment ON assignments(session_id, experiment_id)",
                "CREATE INDEX IF NOT EXISTS idx_assign_variant ON assignments(variant_id)",
                "CREATE UNIQUE INDEX IF NOT EXISTS uq_variant_date ON variant_metrics_daily(variant_id, metric_date)",
                "CREATE INDEX IF NOT EXISTS idx_exp_funnel_status ON experiments(funnel_name, status)",
                "CREATE INDEX IF NOT EXISTS idx_insight_exp ON ai_insights(experiment_id, created_at)",
            ] as $idx) {
                try { $pdo->exec($idx); } catch (Exception $e) { /* exists */ }
            }
        } else {
            foreach ([
                "CREATE INDEX idx_exp_funnel_status ON experiments(funnel_name, status)",
                "CREATE INDEX idx_insight_exp ON ai_insights(experiment_id, created_at)",
            ] as $idx) {
                try { $pdo->exec($idx); } catch (Exception $e) { /* exists */ }
            }
        }

        // Extend funnel_tracking
        self::addColumn($pdo, $driver, 'funnel_tracking', 'experiment_id', 'INT NULL');
        self::addColumn($pdo, $driver, 'funnel_tracking', 'variant_id', 'INT NULL');
        self::addColumn($pdo, $driver, 'funnel_tracking', 'revenue', $mysql ? 'DECIMAL(10,2) NULL' : 'REAL NULL');

        foreach ([
            "CREATE INDEX idx_ft_variant ON funnel_tracking(variant_id, event_type, created_at)",
            "CREATE INDEX idx_ft_session_event ON funnel_tracking(session_id, event_type)",
        ] as $idx) {
            try { $pdo->exec($mysql ? $idx : str_replace('CREATE INDEX', 'CREATE INDEX IF NOT EXISTS', $idx)); } catch (Exception $e) { /* exists */ }
        }
    }

    private static function addColumn($pdo, $driver, $table, $name, $type)
    {
        try {
            if ($driver === 'sqlite') {
                $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) {
                    if ($c['name'] === $name) return;
                }
            }
            $pdo->exec("ALTER TABLE $table ADD COLUMN $name $type");
        } catch (Exception $e) {
            // Column already exists (MySQL throws 1060) — ignore
        }
    }

    /** Seed the A/B engine AI system prompts (insert-if-missing). */
    public static function seedPrompts($db)
    {
        $prompts = [
            'ab_diagnostic_agent' => [
                'description' => 'Weekly CRO diagnostic agent for A/B experiments',
                'text' => <<<'PROMPT'
You are a senior CRO (conversion rate optimization) analyst for 1wellness, a natural wellness brand running four funnels (PCOS, acne, weight, mens). You receive structured A/B experiment data as JSON: per-experiment variant performance (exposures, step counts, conversion rates, RPV, P(best)), funnel step drop-off rates vs baseline, segment splits, and concurrently running experiments.

Respond ONLY with valid JSON in exactly this structure (no markdown fences, no commentary):
{
  "funnel_leaks": [{"stage": "", "drop_rate": 0, "vs_baseline": "", "severity": "high|med|low"}],
  "variant_analysis": [{"variant": "", "verdict": "", "likely_cause": ""}],
  "segment_effects": [{"segment": "", "observation": ""}],
  "overlap_warnings": [],
  "suggestions": [{"priority": 1, "stage": "", "test_idea": "", "expected_impact": "", "rationale": ""}]
}

Rules:
- Never invent data. Base every statement on the numbers provided.
- Flag low-sample conclusions explicitly (under ~300 exposures per variant) in the verdict text.
- overlap_warnings: list experiments on the same funnel judged on the same metric.
- Suggestions must be concrete, testable copy/layout/pricing ideas, ranked by expected impact.
PROMPT
            ],
            'ab_challenger_agent' => [
                'description' => 'Generates challenger variants for killed A/B variants',
                'text' => <<<'PROMPT'
You are a direct-response copywriter for 1wellness, a natural wellness brand. You receive: the brand voice guide, the sub-brand identity for the funnel (CycleSync/GlowClear/LeanFlow/Vitale), the current control's copy, a killed variant's overrides, and the diagnostic verdict on why it lost.

Your task: propose ONE new challenger variant as element overrides.

Respond ONLY with valid JSON (no markdown fences):
{
  "overrides": {
    "text":  { "[data-exp='...']": "new text" },
    "html":  { "[data-exp='...']": "<strong>new html</strong>" },
    "attr":  { "[data-exp='...']": { "src": "/path.webp" } },
    "style": { "[data-exp='...']": { "display": "none" } },
    "config": { }
  },
  "name": "C: short descriptive variant name",
  "ai_rationale": "2-3 sentences: what you changed and why it should beat both the control and the killed variant."
}

COMPLIANCE RULES (non-negotiable for a health brand):
- NEVER claim to cure, treat, or reverse any medical condition ("cures PCOS", "reverses insulin resistance").
- NEVER guarantee outcomes or specific results.
- NEVER promise results in a specific timeframe ("lose 10kg in 30 days", "clear skin in 2 weeks").
- Use empowering, supportive, evidence-aware language ("support", "designed to help", "many women report").
- Only target selectors that exist in the control's copy you were given.
PROMPT
            ],
            'ab_compliance_agent' => [
                'description' => 'Classifies AI-generated funnel copy as compliant/non-compliant',
                'text' => <<<'PROMPT'
You are a compliance reviewer for a wellness brand's marketing copy. You receive JSON containing proposed funnel copy changes. Classify the copy.

Respond ONLY with valid JSON: {"compliant": true|false, "violations": ["..."], "notes": ""}

Mark non-compliant if the copy contains ANY of:
- Medical cure/treatment claims (cure, heal, treat, reverse, fix a medical condition)
- Guaranteed outcomes ("guaranteed results", "will lose", "100% success")
- Specific timeframe promises tied to outcomes ("in 30 days you'll...", "results in 2 weeks")
- Disease diagnosis implications or comparisons to prescription medication efficacy
Otherwise mark compliant. Be strict: borderline cases are non-compliant.
PROMPT
            ],
        ];

        foreach ($prompts as $key => $p) {
            try {
                $exists = $db->fetch("SELECT id FROM system_prompts WHERE prompt_key = :k", [':k' => $key]);
                if (!$exists) {
                    $db->insert('system_prompts', [
                        'prompt_key' => $key,
                        'prompt_text' => $p['text'],
                        'description' => $p['description'],
                    ]);
                }
            } catch (Exception $e) {
                error_log("ABSchema seedPrompts ($key): " . $e->getMessage());
            }
        }
    }
}
