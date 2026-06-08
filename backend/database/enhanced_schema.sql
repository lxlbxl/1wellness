-- OJG Herbal Enhanced Database Schema
-- Adds tables for symptom tracking, streaks, meal ratings, weekly progress, and more

-- ============================================
-- 1. SYMPTOM TRACKING
-- ============================================
CREATE TABLE IF NOT EXISTS symptom_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    log_date DATE NOT NULL,
    energy_level VARCHAR(20) DEFAULT 'medium',  -- 'low', 'medium', 'high'
    mood VARCHAR(20) DEFAULT 'neutral',         -- 'stressed', 'neutral', 'calm', 'happy', 'anxious'
    acne_severity INTEGER DEFAULT 0,            -- 0-10 scale
    cramp_severity INTEGER DEFAULT 0,           -- 0-10 scale
    bloating_severity INTEGER DEFAULT 0,        -- 0-10 scale
    sleep_hours DECIMAL(3,1) DEFAULT 0,         -- Hours of sleep
    sleep_quality VARCHAR(20) DEFAULT 'average', -- 'poor', 'average', 'good', 'excellent'
    stress_level INTEGER DEFAULT 5,             -- 1-10 scale
    water_intake DECIMAL(3,1) DEFAULT 0,        -- Liters
    weight DECIMAL(5,2),                        -- Optional daily weight
    notes TEXT,                                 -- User notes
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_symptom_logs_user_date ON symptom_logs(user_id, log_date);

-- ============================================
-- 2. USER STREAKS
-- ============================================
CREATE TABLE IF NOT EXISTS user_streaks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    login_streak INTEGER DEFAULT 0,             -- Consecutive days logged in
    login_streak_longest INTEGER DEFAULT 0,     -- Best login streak
    activity_streak INTEGER DEFAULT 0,          -- Consecutive days with activities completed
    activity_streak_longest INTEGER DEFAULT 0,  -- Best activity streak
    perfect_days INTEGER DEFAULT 0,             -- Days with 100% completion
    perfect_days_streak INTEGER DEFAULT 0,      -- Consecutive perfect days
    last_login_date DATE,
    last_activity_date DATE,
    total_activities_completed INTEGER DEFAULT 0,
    total_meals_completed INTEGER DEFAULT 0,
    total_movement_completed INTEGER DEFAULT 0,
    total_tea_completed INTEGER DEFAULT 0,
    total_fruit_completed INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- 3. MEAL RATINGS & FEEDBACK
-- ============================================
CREATE TABLE IF NOT EXISTS meal_ratings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    meal_type VARCHAR(50) NOT NULL,             -- 'breakfast', 'lunch', 'dinner'
    meal_name VARCHAR(255),
    plan_date DATE NOT NULL,
    rating INTEGER DEFAULT 0,                   -- 1-5 stars
    feedback TEXT,                              -- User feedback
    would_recommend INTEGER DEFAULT 1,          -- 0 or 1
    difficulty VARCHAR(20) DEFAULT 'easy',      -- 'easy', 'medium', 'hard'
    time_to_prepare INTEGER,                    -- Minutes (optional)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_meal_ratings_user ON meal_ratings(user_id);
CREATE INDEX idx_meal_ratings_meal ON meal_ratings(meal_name);

-- ============================================
-- 4. WEEKLY PROGRESS SUMMARIES (for LLM feedback)
-- ============================================
CREATE TABLE IF NOT EXISTS weekly_progress (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    week_number INTEGER NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    compliance_rate DECIMAL(3,2) DEFAULT 0,     -- 0.00 to 1.00
    meals_completed INTEGER DEFAULT 0,          -- Out of 21 (3 per day)
    meals_total INTEGER DEFAULT 21,
    movement_completed INTEGER DEFAULT 0,       -- Out of 7
    movement_total INTEGER DEFAULT 7,
    tea_completed INTEGER DEFAULT 0,            -- Out of 14 (2 per day)
    tea_total INTEGER DEFAULT 14,
    fruit_completed INTEGER DEFAULT 0,          -- Out of 7
    fruit_total INTEGER DEFAULT 7,
    avg_energy_level VARCHAR(20),
    avg_mood VARCHAR(20),
    avg_sleep_hours DECIMAL(3,1),
    avg_stress_level INTEGER,
    weight_start DECIMAL(5,2),
    weight_end DECIMAL(5,2),
    weight_change DECIMAL(4,2),
    symptoms_improved TEXT,                     -- JSON array
    symptoms_worsened TEXT,                     -- JSON array
    top_rated_meals TEXT,                       -- JSON array
    low_rated_meals TEXT,                       -- JSON array
    user_feedback_summary TEXT,
    llm_context TEXT,                           -- Generated context for next week's LLM
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_weekly_progress_user_week ON weekly_progress(user_id, week_number);

-- ============================================
-- 5. USER LIFESTYLE DATA (extend member_profiles)
-- ============================================
-- These columns should be added to member_profiles table
-- Using ALTER TABLE style for SQLite compatibility

-- Create a new table for lifestyle data
CREATE TABLE IF NOT EXISTS user_lifestyle (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    sleep_quality VARCHAR(50) DEFAULT 'average',        -- 'poor', 'average', 'good', 'excellent'
    sleep_hours_target DECIMAL(3,1) DEFAULT 7.0,        -- Target sleep hours
    stress_level VARCHAR(50) DEFAULT 'moderate',        -- 'low', 'moderate', 'high', 'very_high'
    exercise_level VARCHAR(50) DEFAULT 'sedentary',     -- 'sedentary', 'light', 'moderate', 'active', 'very_active'
    exercise_minutes_target INTEGER DEFAULT 30,         -- Target daily exercise minutes
    current_medications TEXT,                           -- JSON array of medications
    primary_symptoms TEXT,                              -- JSON array of main symptoms
    secondary_symptoms TEXT,                            -- JSON array of secondary symptoms
    food_budget VARCHAR(20) DEFAULT 'moderate',         -- 'low', 'moderate', 'high'
    cooking_ability VARCHAR(50) DEFAULT 'basic',        -- 'none', 'basic', 'intermediate', 'advanced'
    cooking_time_available INTEGER DEFAULT 30,          -- Minutes available for cooking
    work_schedule VARCHAR(50) DEFAULT '9-5',            -- '9-5', 'flexible', 'shift', 'remote', 'freelance'
    meal_prep_preference VARCHAR(20) DEFAULT 'daily',   -- 'daily', 'weekly', 'batch'
    support_system VARCHAR(50) DEFAULT 'self',          -- 'self', 'partner', 'family', 'friends'
    primary_goal VARCHAR(100) DEFAULT 'balance_hormones',
    secondary_goals TEXT,                               -- JSON array
    onboarding_completed INTEGER DEFAULT 0,
    onboarding_completed_at DATETIME,
    first_login_at DATETIME,
    welcome_shown INTEGER DEFAULT 0,
    tour_completed INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- 6. DAILY REMINDERS & NOTIFICATIONS
-- ============================================
CREATE TABLE IF NOT EXISTS user_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    notification_type VARCHAR(50) NOT NULL,     -- 'meal_reminder', 'tea_reminder', 'movement_reminder', 'daily_checkin', 'weekly_summary'
    scheduled_time TIME,
    scheduled_date DATE,
    title VARCHAR(255),
    message TEXT,
    sent_at DATETIME,
    delivered_at DATETIME,
    read_at DATETIME,
    action_taken INTEGER DEFAULT 0,
    channel VARCHAR(20) DEFAULT 'in_app',       -- 'in_app', 'email', 'push', 'whatsapp'
    status VARCHAR(20) DEFAULT 'pending',       -- 'pending', 'sent', 'delivered', 'read', 'failed'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_notifications_user_status ON user_notifications(user_id, status);
CREATE INDEX idx_notifications_pending ON user_notifications(status, scheduled_date);

-- ============================================
-- 7. USER ACHIEVEMENTS & BADGES
-- ============================================
CREATE TABLE IF NOT EXISTS user_achievements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    achievement_type VARCHAR(50) NOT NULL,      -- 'streak_7', 'streak_30', 'perfect_week', 'first_meal', etc.
    achievement_name VARCHAR(100),
    achievement_description TEXT,
    achievement_icon VARCHAR(50),               -- Emoji or icon name
    earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    milestone_value INTEGER,                    -- The value that earned this achievement
    is_displayed INTEGER DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_achievements_user ON user_achievements(user_id);

-- ============================================
-- 8. USER FEEDBACK (General)
-- ============================================
CREATE TABLE IF NOT EXISTS user_feedback (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    feedback_type VARCHAR(50) NOT NULL,         -- 'plan_feedback', 'feature_request', 'bug_report', 'general'
    rating INTEGER,                             -- 1-5 overall rating
    feedback_text TEXT,
    category VARCHAR(50),                       -- 'meals', 'movement', 'tea', 'app', 'support'
    is_resolved INTEGER DEFAULT 0,
    resolved_at DATETIME,
    admin_notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- 9. ADMIN SYSTEM LOGS
-- ============================================
CREATE TABLE IF NOT EXISTS admin_system_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_user_id INTEGER,
    action_type VARCHAR(50) NOT NULL,           -- 'login', 'user_edit', 'plan_regenerate', 'settings_change', etc.
    action_details TEXT,
    target_user_id INTEGER,
    target_type VARCHAR(50),                    -- 'user', 'plan', 'system', 'payment'
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_admin_logs_action ON admin_system_logs(action_type);
CREATE INDEX idx_admin_logs_target ON admin_system_logs(target_user_id);

-- ============================================
-- 10. SYSTEM HEALTH METRICS
-- ============================================
CREATE TABLE IF NOT EXISTS system_health_metrics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    metric_date DATE NOT NULL,
    metric_hour INTEGER,                        -- For hourly metrics
    active_users INTEGER DEFAULT 0,
    new_registrations INTEGER DEFAULT 0,
    plans_generated INTEGER DEFAULT 0,
    api_requests_total INTEGER DEFAULT 0,
    api_requests_failed INTEGER DEFAULT 0,
    avg_response_time_ms INTEGER DEFAULT 0,
    llm_api_calls INTEGER DEFAULT 0,
    llm_api_failures INTEGER DEFAULT 0,
    llm_avg_latency_ms INTEGER DEFAULT 0,
    payments_processed INTEGER DEFAULT 0,
    payments_total_amount DECIMAL(10,2) DEFAULT 0,
    emails_sent INTEGER DEFAULT 0,
    emails_failed INTEGER DEFAULT 0,
    error_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_health_metrics_date ON system_health_metrics(metric_date);

-- ============================================
-- 11. PCOS REASSESSMENT TRACKING
-- ============================================
CREATE TABLE IF NOT EXISTS pcos_reassessments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    reassessment_date DATE NOT NULL,
    previous_pcos_type VARCHAR(50),
    new_pcos_type VARCHAR(50),
    symptoms_change TEXT,                       -- JSON describing symptom changes
    user_self_report TEXT,
    ai_analysis TEXT,
    recommended_adjustments TEXT,
    completed INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_reassessments_user ON pcos_reassessments(user_id);

-- ============================================
-- 12. TIME WINDOW CUSTOMIZATION
-- ============================================
CREATE TABLE IF NOT EXISTS user_time_windows (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    breakfast_start TIME DEFAULT '07:00',
    breakfast_end TIME DEFAULT '08:00',
    lunch_start TIME DEFAULT '12:00',
    lunch_end TIME DEFAULT '13:00',
    dinner_start TIME DEFAULT '18:00',
    dinner_end TIME DEFAULT '19:00',
    morning_tea_start TIME DEFAULT '10:00',
    morning_tea_end TIME DEFAULT '10:30',
    evening_tea_start TIME DEFAULT '20:30',
    evening_tea_end TIME DEFAULT '21:00',
    fruit_ritual_start TIME DEFAULT '10:00',
    fruit_ritual_end TIME DEFAULT '11:00',
    movement_start TIME DEFAULT '06:00',
    movement_end TIME DEFAULT '07:00',
    secondary_movement_start TIME DEFAULT '18:00',
    secondary_movement_end TIME DEFAULT '18:30',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- 13. WELCOME TOUR TRACKING
-- ============================================
CREATE TABLE IF NOT EXISTS user_tour_progress (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    tour_type VARCHAR(50) DEFAULT 'first_login',
    current_step INTEGER DEFAULT 0,
    total_steps INTEGER DEFAULT 5,
    steps_completed TEXT,                       -- JSON array of completed step IDs
    tour_started_at DATETIME,
    tour_completed_at DATETIME,
    skipped INTEGER DEFAULT 0,
    skipped_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- 14. MOTIVATIONAL MESSAGES LIBRARY
-- ============================================
CREATE TABLE IF NOT EXISTS motivational_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_type VARCHAR(50) NOT NULL,          -- 'daily', 'cycle_phase', 'streak', 'achievement', 'morning', 'evening'
    cycle_phase VARCHAR(50),                    -- 'menstrual', 'follicular', 'ovulatory', 'luteal' (if cycle-specific)
    pcos_type VARCHAR(50),                      -- If PCOS-type specific
    message_text TEXT NOT NULL,
    message_author VARCHAR(100),                -- Optional attribution
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Pre-populate motivational messages
INSERT INTO motivational_messages (message_type, cycle_phase, message_text) VALUES
('morning', NULL, 'Good morning! Today is a fresh start for your hormonal health journey. Every small step counts.'),
('morning', 'follicular', 'You''re in your Follicular phase - energy is rising! Great day for new activities and nourishing meals.'),
('morning', 'ovulatory', 'Ovulatory phase energy! Your body is at its peak - make the most of this vibrant time.'),
('morning', 'luteal', 'Luteal phase: Focus on self-care and nourishing foods. Your body needs extra support now.'),
('morning', 'menstrual', 'Menstrual phase: Be gentle with yourself today. Rest and nourishment are your priorities.'),
('evening', NULL, 'Evening reflection: Celebrate what you accomplished today. Tomorrow brings new opportunities.'),
('evening', NULL, 'As you rest tonight, your body is healing. Quality sleep is part of your protocol.'),
('cycle_phase', 'menstrual', 'During menstruation, iron-rich foods like ugu (fluted pumpkin) support your body''s needs.'),
('cycle_phase', 'follicular', 'Follicular phase is perfect for trying new recipes and increasing physical activity.'),
('cycle_phase', 'ovulatory', 'Peak energy time! Your metabolism is most efficient during ovulation.'),
('cycle_phase', 'luteal', 'Luteal phase calls for magnesium-rich foods to support mood and reduce cramps.'),
('streak', NULL, 'You''re on a streak! Consistency is the key to hormonal balance.'),
('streak', NULL, 'Day by day, you''re building habits that transform your health.'),
('achievement', NULL, 'Congratulations! Your dedication is paying off.'),
('daily', NULL, 'Remember: Every meal is an opportunity to nourish your hormones.'),
('daily', NULL, 'Small consistent actions create lasting transformation.'),
('daily', NULL, 'Your body has incredible wisdom - trust the process.');

-- ============================================
-- 15. SUBSCRIPTION UPGRADE TRACKING
-- ============================================
CREATE TABLE IF NOT EXISTS subscription_upgrades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    from_tier VARCHAR(20),
    to_tier VARCHAR(20),
    upgrade_date DATETIME,
    payment_reference VARCHAR(100),
    amount DECIMAL(10,2),
    upgrade_reason TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- VIEWS FOR ANALYTICS
-- ============================================

-- Active Members View
CREATE VIEW IF NOT EXISTS v_active_members AS
SELECT 
    u.id,
    u.email,
    u.first_name,
    u.last_name,
    mp.pcos_type,
    mp.subscription_tier,
    mp.subscription_status,
    mp.start_date,
    mp.subscription_expiry,
    us.login_streak,
    us.activity_streak,
    us.total_activities_completed,
    ul.primary_goal,
    ul.onboarding_completed
FROM users u
LEFT JOIN member_profiles mp ON u.id = mp.user_id
LEFT JOIN user_streaks us ON u.id = us.user_id
LEFT JOIN user_lifestyle ul ON u.id = ul.user_id
WHERE mp.subscription_status = 'active';

-- Weekly Engagement View
CREATE VIEW IF NOT EXISTS v_weekly_engagement AS
SELECT 
    user_id,
    week_number,
    compliance_rate,
    meals_completed,
    movement_completed,
    weight_change,
    avg_energy_level,
    avg_mood,
    created_at
FROM weekly_progress
ORDER BY user_id, week_number DESC;

-- Symptom Trends View
CREATE VIEW IF NOT EXISTS v_symptom_trends AS
SELECT 
    sl.user_id,
    sl.log_date,
    sl.energy_level,
    sl.mood,
    sl.acne_severity,
    sl.cramp_severity,
    sl.sleep_hours,
    sl.stress_level,
    sl.water_intake,
    mp.pcos_type
FROM symptom_logs sl
LEFT JOIN member_profiles mp ON sl.user_id = mp.user_id
ORDER BY sl.user_id, sl.log_date DESC;

-- Admin Dashboard Summary View
CREATE VIEW IF NOT EXISTS v_admin_dashboard AS
SELECT 
    DATE(created_at) as report_date,
    COUNT(DISTINCT user_id) as active_users_today,
    SUM(CASE WHEN activity_type LIKE 'meal_%' AND status = 'completed' THEN 1 ELSE 0 END) as meals_logged,
    SUM(CASE WHEN activity_type = 'movement' AND status = 'completed' THEN 1 ELSE 0 END) as movement_logged,
    COUNT(DISTINCT CASE WHEN status = 'completed' THEN user_id END) as users_with_activity
FROM activity_logs
GROUP BY DATE(created_at)
ORDER BY report_date DESC
LIMIT 30;