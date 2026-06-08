-- 1Wellness PostgreSQL Database Schema
-- Auto-generated from SQLite schema

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
    status VARCHAR(20) DEFAULT 'active'
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
    status TEXT DEFAULT 'active'
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

CREATE TABLE IF NOT EXISTS assessments (
    id VARCHAR(50) PRIMARY KEY,
    user_id INTEGER,
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

CREATE TABLE IF NOT EXISTS sales (
    id SERIAL PRIMARY KEY,
    user_id INTEGER,
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

INSERT INTO admin_users (username, email, password_hash, full_name, role, created_at)
VALUES ('admin', 'admin@1wellness.club', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', NOW())
ON CONFLICT (username) DO NOTHING;
