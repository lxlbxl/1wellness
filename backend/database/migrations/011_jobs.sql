-- Async job queue (spec §B.3)
-- Decouple slow work (email, AI generation, PDF) from the request cycle.
-- Worker: backend/cron/worker.php  (every minute)

CREATE TABLE IF NOT EXISTS jobs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    type        VARCHAR(80)  NOT NULL,
    payload     TEXT         NOT NULL DEFAULT '{}',
    status      VARCHAR(20)  NOT NULL DEFAULT 'pending',
    priority    SMALLINT     NOT NULL DEFAULT 5,
    attempts    SMALLINT     NOT NULL DEFAULT 0,
    max_attempts SMALLINT    NOT NULL DEFAULT 3,
    run_after   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at  DATETIME,
    finished_at DATETIME,
    error       TEXT,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_jobs_runnable
    ON jobs(status, priority DESC, run_after);
