-- Migration: Member Area Improvements
-- Adds normalized tracking for metrics (weight, water, etc.) and persistent user preferences.

CREATE TABLE IF NOT EXISTS user_tracking (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    metric_type TEXT NOT NULL, -- 'weight', 'water_liters', 'steps', 'sleep_hours'
    metric_value REAL NOT NULL,
    unit TEXT,
    logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS user_preferences (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    pref_key TEXT NOT NULL, -- 'avoid_meals', 'favorite_herbs', 'theme'
    pref_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, pref_key),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Indexing for performance on charts
CREATE INDEX IF NOT EXISTS idx_tracking_user_metric ON user_tracking(user_id, metric_type, logged_at);
