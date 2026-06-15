-- Migration 004: Notification & Reminder System
-- Applies to both MySQL (production) and SQLite (local dev).
-- Run via: php backend/database/migrations/migrate.php
-- Or auto-installed by NotificationService::ensureSchema().

-- notification_queue: one row per scheduled outbound send
CREATE TABLE IF NOT EXISTS notification_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    journey_key VARCHAR(60) NOT NULL,
    step INTEGER NOT NULL DEFAULT 1,
    recipient_type VARCHAR(10) NOT NULL,      -- 'lead' | 'user'
    recipient_id INTEGER,                     -- users.id when recipient_type='user'
    email VARCHAR(255),
    phone VARCHAR(30),
    funnel VARCHAR(20),
    template_key VARCHAR(80) NOT NULL,
    payload TEXT,                             -- JSON merge vars
    channel_ladder VARCHAR(60) NOT NULL,      -- 'whatsapp,email' | 'email' | 'sms,email'
    dedupe_key VARCHAR(120) UNIQUE,
    send_after DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending|sent|failed|cancelled|suppressed
    attempts INTEGER NOT NULL DEFAULT 0,
    next_attempt DATETIME,
    cancelled_reason VARCHAR(60),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_nq_due ON notification_queue(status, send_after);
CREATE INDEX IF NOT EXISTS idx_nq_recipient ON notification_queue(email, journey_key);

-- notification_log: immutable delivery ledger
CREATE TABLE IF NOT EXISTS notification_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    queue_id INTEGER,
    journey_key VARCHAR(60),
    step INTEGER,
    channel VARCHAR(20) NOT NULL,            -- 'email' | 'whatsapp' | 'sms'
    provider VARCHAR(30),
    provider_msg_id VARCHAR(120),
    email VARCHAR(255),
    phone VARCHAR(30),
    status VARCHAR(20) NOT NULL,             -- sent|delivered|read|clicked|replied|bounced|failed
    error TEXT,
    cost_usd DECIMAL(8,5),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_nl_msgid ON notification_log(provider_msg_id);
CREATE INDEX IF NOT EXISTS idx_nl_email_journey ON notification_log(email, journey_key, created_at);

-- notification_consent: channel-level opt-in/out ledger
CREATE TABLE IF NOT EXISTS notification_consent (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255),
    phone VARCHAR(30),
    channel VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,             -- opted_in|opted_out|bounced|complained
    source VARCHAR(60),                      -- 'assessment_form'|'sms_stop'|'unsub_link'|'admin'
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_nc_unique ON notification_consent(email, channel);
CREATE INDEX IF NOT EXISTS idx_nc_phone ON notification_consent(phone, channel);

-- notification_templates: admin-editable message templates
CREATE TABLE IF NOT EXISTS notification_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_key VARCHAR(80) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    funnel VARCHAR(20) NOT NULL DEFAULT 'all',
    subject VARCHAR(255),
    body TEXT NOT NULL,
    wa_template_name VARCHAR(120),
    active INTEGER NOT NULL DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_nt_unique ON notification_templates(template_key, channel, funnel);
