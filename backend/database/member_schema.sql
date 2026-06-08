-- Member Profiles (Extended User Data)
CREATE TABLE IF NOT EXISTS member_profiles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    pcos_type VARCHAR(50), -- e.g., Insulin Resistant, Adrenal
    allergies TEXT, -- JSON array
    dietary_preferences TEXT, -- JSON array
    cycle_length INTEGER DEFAULT 28,
    last_period_date DATE,
    subscription_tier VARCHAR(20) DEFAULT '30-day',
    start_date DATE,
    subscription_expiry DATE,
    subscription_status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Daily Plans (AI Generated)
CREATE TABLE IF NOT EXISTS daily_plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    plan_date DATE NOT NULL,
    plan_data TEXT, -- JSON: meals, workout, tasks, quote
    is_completed BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, plan_date)
);

-- Meal Swaps (Log of user swaps)
CREATE TABLE IF NOT EXISTS meal_swaps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    original_meal TEXT, -- JSON or string description
    new_meal TEXT, -- JSON
    reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Symptom Logs
CREATE TABLE IF NOT EXISTS symptom_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    log_date DATE NOT NULL,
    symptoms TEXT, -- JSON: {bloating: 3, energy: 5, mood: 'happy'}
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, log_date)
);

-- Weight Logs
CREATE TABLE IF NOT EXISTS weight_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    log_date DATE NOT NULL,
    weight DECIMAL(5,2),
    unit VARCHAR(10) DEFAULT 'kg',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- System Prompts (AI Personality/Rules)
CREATE TABLE IF NOT EXISTS system_prompts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    prompt_key VARCHAR(50) UNIQUE NOT NULL, -- e.g., 'meal_planner_base', 'workout_generator'
    prompt_text TEXT NOT NULL,
    description VARCHAR(255),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert Default Prompts
INSERT OR IGNORE INTO system_prompts (prompt_key, prompt_text, description) VALUES 
('pcos_meal_planner', 'You are an expert PCOS Nutritionist. Create a meal plan for a user with [PCOS_TYPE].', 'Base prompt for meal planning');

-- Indexes
CREATE INDEX IF NOT EXISTS idx_weight_logs_date ON weight_logs(log_date);

-- Auth Tokens (Auto-Login)
CREATE TABLE IF NOT EXISTS auth_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- AI Generation Logs (Oversight)
CREATE TABLE IF NOT EXISTS ai_generation_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    action VARCHAR(50), -- e.g., 'generate_weekly', 'generate_meal'
    status VARCHAR(20), -- 'success', 'failed', 'generating'
    target_date DATE,
    metadata TEXT,
    duration_ms INTEGER,
    error_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User Metric Tracking (Weight, Water, etc.)
CREATE TABLE IF NOT EXISTS user_tracking (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    metric_type TEXT NOT NULL, -- 'weight', 'water_liters', 'steps', 'sleep_hours'
    metric_value REAL NOT NULL,
    unit TEXT,
    logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User Preferences (AI Personalization, UI settings)
CREATE TABLE IF NOT EXISTS user_preferences (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    pref_key TEXT NOT NULL, -- 'avoid_meals', 'favorite_herbs', 'theme'
    pref_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, pref_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Index for tracking performance
CREATE INDEX IF NOT EXISTS idx_tracking_user_metric ON user_tracking(user_id, metric_type, logged_at);

