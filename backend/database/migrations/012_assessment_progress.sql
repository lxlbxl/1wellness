-- Per-answer assessment persistence (spec §B.5)
-- One row per session+funnel, updated on each answer.
-- Allows resuming incomplete assessments.

CREATE TABLE IF NOT EXISTS assessment_progress (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id  VARCHAR(100) NOT NULL,
    funnel      VARCHAR(30)  NOT NULL DEFAULT 'pcos',
    step        SMALLINT     NOT NULL DEFAULT 0,
    answers     TEXT         NOT NULL DEFAULT '{}',
    email       VARCHAR(255),
    completed   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(session_id, funnel)
);

CREATE INDEX IF NOT EXISTS idx_ap_session  ON assessment_progress(session_id);
CREATE INDEX IF NOT EXISTS idx_ap_email    ON assessment_progress(email);
CREATE INDEX IF NOT EXISTS idx_ap_updated  ON assessment_progress(updated_at);
