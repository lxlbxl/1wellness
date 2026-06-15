-- Member personalization schema (spec §C.1)
-- Extends users with condition metadata; adds milestone ledger.

-- SQLite: ALTER TABLE … ADD COLUMN IF NOT EXISTS is not supported; use plain ADD COLUMN.
-- MySQL: same — no IF NOT EXISTS for ADD COLUMN in MySQL < 8.
-- Run via: php backend/cron/migrate.php  OR  paste into DB console.
-- Each ALTER is safe to re-run if the column already exists — engine will error;
-- wrap in a try/catch in the migration runner.

ALTER TABLE users ADD COLUMN condition        VARCHAR(20)  DEFAULT 'pcos';
ALTER TABLE users ADD COLUMN sub_brand        VARCHAR(40)  DEFAULT NULL;
ALTER TABLE users ADD COLUMN assessment_type  VARCHAR(20)  DEFAULT NULL;
ALTER TABLE users ADD COLUMN assessment_json  TEXT         DEFAULT NULL;
ALTER TABLE users ADD COLUMN onboarded_at     DATETIME     DEFAULT NULL;
ALTER TABLE users ADD COLUMN streak_count     SMALLINT     NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN last_active_date DATE         DEFAULT NULL;

-- Milestone ledger: one row per earned milestone per user
CREATE TABLE IF NOT EXISTS member_milestones (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER      NOT NULL,
    milestone    VARCHAR(60)  NOT NULL,
    earned_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    meta         TEXT         DEFAULT NULL,
    UNIQUE(user_id, milestone)
);

CREATE INDEX IF NOT EXISTS idx_mm_user ON member_milestones(user_id);
