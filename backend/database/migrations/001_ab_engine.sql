-- ============================================================
-- Migration 001: Smart A/B Testing Engine (MySQL)
-- 1wellness Funnel Experimentation Layer
--
-- Run: mysql -u <user> -p <db> < 001_ab_engine.sql
-- Or:  php backend/database/migrations/migrate.php   (driver-aware,
--      also works on SQLite for local development)
-- ============================================================

CREATE TABLE IF NOT EXISTS experiments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funnel_name VARCHAR(50) NOT NULL,            -- pcos | acne | weight | mens
    name VARCHAR(120) NOT NULL,
    hypothesis TEXT,
    stage VARCHAR(50) NOT NULL,                  -- landing | assessment | results | pricing | checkout
    primary_metric VARCHAR(60) NOT NULL,         -- assessment_start | assessment_complete | results_view | plan_select | checkout_init | purchase | purchase_rpv
    reward_type ENUM('binary','revenue') DEFAULT 'binary',
    status ENUM('draft','burn_in','active','paused','concluded','archived') DEFAULT 'draft',
    burn_in_hours INT DEFAULT 48,
    min_exposure_floor DECIMAL(4,3) DEFAULT 0.100,   -- 10% traffic floor per variant
    min_samples_per_variant INT DEFAULT 1000,
    decision_p_best DECIMAL(4,3) DEFAULT 0.950,      -- promote at P(best) > 95%
    decision_expected_loss DECIMAL(6,4) DEFAULT 0.0050,
    winner_variant_id INT NULL,
    started_at DATETIME NULL,
    concluded_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_exp_funnel_status (funnel_name, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,                  -- "Control", "B: urgency headline"
    type ENUM('control','structural','element') NOT NULL,
    directory VARCHAR(120) NULL,                 -- structural: e.g. pcos__longform
    overrides JSON NULL,                         -- element: override map (see docs/AB-ENGINE.md §6)
    -- Thompson Sampling state (binary reward)
    alpha DECIMAL(12,4) DEFAULT 1.0,             -- prior successes + observed
    beta  DECIMAL(12,4) DEFAULT 1.0,             -- prior failures + observed
    -- Revenue reward state
    exposures INT DEFAULT 0,
    conversions INT DEFAULT 0,
    revenue_total DECIMAL(12,2) DEFAULT 0,
    p_best DECIMAL(5,4) DEFAULT 0,               -- cached Monte Carlo P(best)
    expected_loss DECIMAL(8,6) DEFAULT 0,        -- cached expected loss
    status ENUM('pending_approval','active','killed','winner','rejected') DEFAULT 'active',
    source ENUM('human','ai_challenger') DEFAULT 'human',
    ai_rationale TEXT NULL,                      -- why the AI proposed this
    compliance_status ENUM('unchecked','compliant','non_compliant') DEFAULT 'unchecked',
    compliance_notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    experiment_id INT NOT NULL,
    variant_id INT NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_session_experiment (session_id, experiment_id),
    KEY idx_assign_variant (variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS variant_metrics_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    variant_id INT NOT NULL,
    metric_date DATE NOT NULL,
    exposures INT DEFAULT 0,
    assessment_starts INT DEFAULT 0,
    assessment_completes INT DEFAULT 0,
    results_views INT DEFAULT 0,
    plan_selects INT DEFAULT 0,
    checkout_inits INT DEFAULT 0,
    purchases INT DEFAULT 0,
    revenue DECIMAL(12,2) DEFAULT 0,
    UNIQUE KEY uq_variant_date (variant_id, metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NULL,
    funnel_name VARCHAR(50),
    insight_type ENUM('diagnostic','suggestion','challenger_rationale'),
    content JSON NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_insight_exp (experiment_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outbound webhook subscriptions (admin-managed). The queue table
-- (webhook_queue) already exists in the base schema; this is the missing
-- registry the queue's webhook_id points at.
CREATE TABLE IF NOT EXISTS webhooks (
    id VARCHAR(50) PRIMARY KEY,                  -- wh_xxxxxxxx
    name VARCHAR(120) NOT NULL,
    url TEXT NOT NULL,
    events TEXT NOT NULL,                        -- JSON array of subscribed event keys
    secret VARCHAR(128) NULL,                    -- HMAC-SHA256 signing secret
    headers TEXT NULL,                           -- JSON map of extra headers
    method VARCHAR(10) DEFAULT 'POST',
    status ENUM('active','paused') DEFAULT 'active',
    success_count INT DEFAULT 0,
    failure_count INT DEFAULT 0,
    last_triggered DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extend existing event store
ALTER TABLE funnel_tracking ADD COLUMN experiment_id INT NULL;
ALTER TABLE funnel_tracking ADD COLUMN variant_id INT NULL;
ALTER TABLE funnel_tracking ADD COLUMN revenue DECIMAL(10,2) NULL;
ALTER TABLE funnel_tracking ADD INDEX idx_ft_variant (variant_id, event_type, created_at);
ALTER TABLE funnel_tracking ADD INDEX idx_ft_session_event (session_id, event_type);
