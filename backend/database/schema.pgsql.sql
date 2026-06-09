-- 1Wellness Full PostgreSQL Schema
-- Auto-generated from SQLite schema + enhanced_schema + member_schema

-- Core tables
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100),
    phone VARCHAR(20),
    age INTEGER,
    gender VARCHAR(20),
    type VARCHAR(20) DEFAULT 'lead',
    name VARCHAR(100),
    condition_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    plan_duration INTEGER DEFAULT 0,
    plan_start_date TIMESTAMP,
    plan_end_date TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active',
    marketing_consent INTEGER DEFAULT 0,
    data_consent INTEGER DEFAULT 1,
    username VARCHAR(50),
    password_hash VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS admin_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role TEXT DEFAULT 'admin',
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status TEXT DEFAULT 'active',
    permissions TEXT DEFAULT '["all"]'
);

-- Assessment tables
CREATE TABLE IF NOT EXISTS assessments (
    id VARCHAR(50) PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    phone VARCHAR(20),
    assessment_type VARCHAR(50),
    assessment_data TEXT,
    score NUMERIC(5,2),
    recommendations TEXT,
    tracking_data TEXT,
    ip_address TEXT,
    user_agent TEXT,
    referrer TEXT,
    status TEXT DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pcos_assessments (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    age INTEGER NOT NULL,
    weight NUMERIC(5,2),
    height NUMERIC(5,2),
    bmi NUMERIC(4,2),
    menstrual_cycle TEXT,
    symptoms TEXT,
    lifestyle_factors TEXT,
    medical_history TEXT,
    current_medications TEXT,
    assessment_score INTEGER,
    recommendations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS acne_assessments (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    age INTEGER NOT NULL,
    skin_type TEXT,
    acne_severity TEXT,
    acne_type TEXT,
    triggers TEXT,
    current_treatment TEXT,
    skincare_routine TEXT,
    lifestyle_factors TEXT,
    assessment_score INTEGER,
    recommendations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS weight_assessments (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    age INTEGER NOT NULL,
    current_weight NUMERIC(6,2),
    target_weight NUMERIC(6,2),
    height NUMERIC(5,2),
    bmi NUMERIC(4,2),
    activity_level TEXT,
    diet_preferences TEXT,
    medical_conditions TEXT,
    previous_diets TEXT,
    assessment_score INTEGER,
    recommendations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sales & payments
CREATE TABLE IF NOT EXISTS sales (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    email TEXT,
    name TEXT,
    phone TEXT,
    currency TEXT,
    tx_ref TEXT,
    amount NUMERIC(10,2),
    product_type TEXT,
    customer_data TEXT,
    product_data TEXT,
    payment_status VARCHAR(20) DEFAULT 'pending',
    ip_address TEXT,
    user_agent TEXT,
    referrer TEXT,
    notes TEXT,
    tracking_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contacts (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    message TEXT,
    source VARCHAR(50) DEFAULT 'website',
    status VARCHAR(20) DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin logs
CREATE TABLE IF NOT EXISTS admin_logs (
    id SERIAL PRIMARY KEY,
    admin_id INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
    id SERIAL PRIMARY KEY,
    key VARCHAR(100) UNIQUE NOT NULL,
    value TEXT,
    type VARCHAR(20) DEFAULT 'text',
    group_name VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Webhooks & API
CREATE TABLE IF NOT EXISTS webhook_queue (
    id SERIAL PRIMARY KEY,
    event_type VARCHAR(100),
    payload TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    attempts INTEGER DEFAULT 0,
    last_attempt_at TIMESTAMP,
    next_retry_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS funnel_tracking (
    id SERIAL PRIMARY KEY,
    session_id VARCHAR(100),
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    email VARCHAR(255),
    funnel_name VARCHAR(50),
    step_name VARCHAR(100),
    event_type VARCHAR(50),
    metadata TEXT,
    url TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Member area
CREATE TABLE IF NOT EXISTS member_profiles (
    id SERIAL PRIMARY KEY,
    user_id INTEGER UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    plan_type VARCHAR(50) DEFAULT '30-day',
    plan_start_date TIMESTAMP,
    plan_end_date TIMESTAMP,
    health_goals TEXT,
    allergies TEXT,
    meal_preferences TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS daily_plans (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    plan_date DATE NOT NULL,
    meal_plan TEXT,
    supplement_plan TEXT,
    activities TEXT,
    notes TEXT,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS meal_swaps (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    original_meal TEXT,
    suggested_swap TEXT,
    reason TEXT,
    approved BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS symptom_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    log_date DATE NOT NULL,
    symptom_type VARCHAR(100),
    severity INTEGER CHECK (severity >= 1 AND severity <= 10),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS auth_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(128) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ai_generation_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    assessment_id VARCHAR(50),
    prompt_type VARCHAR(50),
    prompt_text TEXT,
    generated_text TEXT,
    model_used VARCHAR(100),
    tokens_used INTEGER,
    generation_time_ms INTEGER,
    metadata TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed admin user
INSERT INTO admin_users (username, email, password_hash, full_name, created_at)
VALUES ('admin', 'admin@1wellness.club', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', NOW())
ON CONFLICT (username) DO NOTHING;

-- Seed default settings
INSERT INTO settings (key, value, type, group_name) VALUES
    ('flutterwave_public_key', 'FLWPUBK_TEST-SANDBOXDEMOKEY-X', 'text', 'payment'),
    ('flutterwave_secret_key', '', 'text', 'payment'),
    ('flutterwave_encryption_key', '', 'text', 'payment'),
    ('flutterwave_environment', 'sandbox', 'text', 'payment'),
    ('n8n_api_key', '', 'text', 'integration'),
    ('site_name', '1Wellness', 'text', 'general'),
    ('site_url', 'https://1wellness.club', 'text', 'general'),
    ('admin_email', 'admin@1wellness.club', 'text', 'general'),
    ('company_phone', '', 'text', 'general')
ON CONFLICT (key) DO NOTHING;
