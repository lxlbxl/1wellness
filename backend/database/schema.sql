-- OJG Herbal Health Assessment System Database Schema
-- SQLite Database Structure

-- Enable foreign key constraints
PRAGMA foreign_keys = ON;

-- Users table for storing customer information
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100), -- Made optional
    phone VARCHAR(20),
    age INTEGER,
    gender VARCHAR(20),
    type VARCHAR(20) DEFAULT 'lead', -- Added for Lead/Customer distinction
    name VARCHAR(100), -- Full display name for admin
    condition_type VARCHAR(50), -- e.g. pcos, acne, weight
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    plan_duration INTEGER DEFAULT 0, -- 30 or 90 days
    plan_start_date DATETIME,
    plan_end_date DATETIME,
    status VARCHAR(20) DEFAULT 'active'
);

-- Admin users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role TEXT DEFAULT 'admin', -- SQLite handles ENUM as TEXT
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TEXT DEFAULT 'active'
);

-- PCOS Assessments
CREATE TABLE IF NOT EXISTS pcos_assessments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    age INTEGER NOT NULL,
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    bmi DECIMAL(4,2),
    menstrual_cycle TEXT,
    symptoms TEXT, -- JSON format for multiple symptoms
    lifestyle_factors TEXT, -- JSON format
    medical_history TEXT,
    current_medications TEXT,
    assessment_score INTEGER,
    recommendations TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Acne Assessments
CREATE TABLE IF NOT EXISTS acne_assessments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    age INTEGER NOT NULL,
    skin_type TEXT,
    acne_severity TEXT,
    acne_type TEXT, -- JSON format for multiple types
    triggers TEXT, -- JSON format
    current_treatment TEXT,
    skincare_routine TEXT,
    lifestyle_factors TEXT, -- JSON format
    assessment_score INTEGER,
    recommendations TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Weight Management Assessments
CREATE TABLE IF NOT EXISTS weight_assessments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    age INTEGER NOT NULL,
    current_weight DECIMAL(5,2) NOT NULL,
    target_weight DECIMAL(5,2),
    height DECIMAL(5,2) NOT NULL,
    current_bmi DECIMAL(4,2),
    target_bmi DECIMAL(4,2),
    activity_level TEXT,
    diet_preferences TEXT, -- JSON format
    health_conditions TEXT,
    weight_history TEXT,
    goals TEXT, -- JSON format
    assessment_score INTEGER,
    recommendations TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sales/Orders table
CREATE TABLE IF NOT EXISTS sales (
    id VARCHAR(50) PRIMARY KEY,
    user_id INTEGER,
    assessment_id VARCHAR(50),
    transaction_id VARCHAR(100),
    tx_ref VARCHAR(100),
    email VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    phone VARCHAR(20),
    product_type VARCHAR(50),
    product_name VARCHAR(255),
    amount DECIMAL(10,2),
    currency VARCHAR(10) DEFAULT 'NGN',
    payment_status VARCHAR(20) DEFAULT 'pending',
    payment_method VARCHAR(50),
    customer_data TEXT,
    product_data TEXT,
    tracking_data TEXT,
    ip_address TEXT,
    user_agent TEXT,
    referrer TEXT,
    plan_duration INTEGER DEFAULT 0, -- 30 or 90 days
    plan_start_date DATETIME,
    plan_end_date DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Contact/Lead forms
CREATE TABLE IF NOT EXISTS contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    category TEXT,
    subject VARCHAR(255),
    message TEXT,
    source VARCHAR(50), -- which page/form they came from
    status TEXT DEFAULT 'new',
    admin_notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- System logs for admin actions
CREATE TABLE IF NOT EXISTS admin_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INTEGER,
    old_values TEXT, -- JSON format
    new_values TEXT, -- JSON format
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id)
);

-- Settings table for system configuration
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type TEXT DEFAULT 'string',
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at);
CREATE INDEX IF NOT EXISTS idx_pcos_user_id ON pcos_assessments(user_id);
CREATE INDEX IF NOT EXISTS idx_pcos_created_at ON pcos_assessments(created_at);
CREATE INDEX IF NOT EXISTS idx_acne_user_id ON acne_assessments(user_id);
CREATE INDEX IF NOT EXISTS idx_acne_created_at ON acne_assessments(created_at);
CREATE INDEX IF NOT EXISTS idx_weight_user_id ON weight_assessments(user_id);
CREATE INDEX IF NOT EXISTS idx_weight_created_at ON weight_assessments(created_at);
CREATE INDEX IF NOT EXISTS idx_sales_user_id ON sales(user_id);
CREATE INDEX IF NOT EXISTS idx_sales_category ON sales(category);
CREATE INDEX IF NOT EXISTS idx_sales_order_date ON sales(order_date);
CREATE INDEX IF NOT EXISTS idx_contacts_category ON contacts(category);
CREATE INDEX IF NOT EXISTS idx_contacts_status ON contacts(status);
CREATE INDEX IF NOT EXISTS idx_contacts_created_at ON contacts(created_at);

-- Insert default admin user
INSERT OR IGNORE INTO admin_users (username, email, password_hash, full_name, role) 
VALUES ('admin', 'admin@ojgherbal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');

-- Insert default settings
INSERT OR IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'OJG Herbal Health Assessment', 'string', 'Website name'),
('site_email', 'info@ojgherbal.com', 'string', 'Contact email'),
('site_phone', '+1-234-567-8900', 'string', 'Contact phone'),
('assessment_scoring_enabled', '1', 'boolean', 'Enable assessment scoring system'),
('email_notifications', '1', 'boolean', 'Send email notifications for new submissions'),
('max_assessments_per_user', '5', 'integer', 'Maximum assessments per user per day');

-- Create triggers for updated_at timestamps
CREATE TRIGGER IF NOT EXISTS update_users_timestamp 
    AFTER UPDATE ON users
    BEGIN
        UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

CREATE TRIGGER IF NOT EXISTS update_admin_users_timestamp 
    AFTER UPDATE ON admin_users
    BEGIN
        UPDATE admin_users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

CREATE TRIGGER IF NOT EXISTS update_pcos_assessments_timestamp 
    AFTER UPDATE ON pcos_assessments
    BEGIN
        UPDATE pcos_assessments SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

CREATE TRIGGER IF NOT EXISTS update_acne_assessments_timestamp 
    AFTER UPDATE ON acne_assessments
    BEGIN
        UPDATE acne_assessments SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

CREATE TRIGGER IF NOT EXISTS update_weight_assessments_timestamp 
    AFTER UPDATE ON weight_assessments
    BEGIN
        UPDATE weight_assessments SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

CREATE TRIGGER IF NOT EXISTS update_sales_timestamp 
    AFTER UPDATE ON sales
    BEGIN
        UPDATE sales SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

CREATE TRIGGER IF NOT EXISTS update_contacts_timestamp 
    AFTER UPDATE ON contacts
    BEGIN
        UPDATE contacts SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

CREATE TRIGGER IF NOT EXISTS update_settings_timestamp 
    AFTER UPDATE ON settings
    BEGIN
        UPDATE settings SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

-- Generic Assessments Table (used by current form handler)
CREATE TABLE IF NOT EXISTS assessments (
    id VARCHAR(50) PRIMARY KEY,
    user_id INTEGER,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    phone VARCHAR(20),
    assessment_type VARCHAR(50),
    assessment_data TEXT, -- JSON
    score DECIMAL(5,2),
    recommendations TEXT, -- JSON
    tracking_data TEXT, -- JSON for UTM, device info, etc.
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer TEXT,
    status VARCHAR(20) DEFAULT 'completed',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_assessments_email ON assessments(email);
CREATE INDEX IF NOT EXISTS idx_assessments_type ON assessments(assessment_type);

-- Webhook Queue Table
CREATE TABLE IF NOT EXISTS webhook_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    webhook_id VARCHAR(50) NOT NULL,
    event VARCHAR(50) NOT NULL,
    payload TEXT NOT NULL, -- JSON
    status TEXT DEFAULT 'pending',
    attempts INTEGER DEFAULT 0,
    last_attempt DATETIME,
    next_attempt DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_webhook_queue_status ON webhook_queue(status);
CREATE INDEX IF NOT EXISTS idx_webhook_queue_next_attempt ON webhook_queue(next_attempt);

-- Funnel Tracking Table
CREATE TABLE IF NOT EXISTS funnel_tracking (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id VARCHAR(100),
    user_id INTEGER,
    email VARCHAR(255), -- Added to help link tracking if session is lost
    funnel_name VARCHAR(50),
    step_name VARCHAR(100),
    event_type VARCHAR(50),
    metadata TEXT, -- JSON
    url TEXT, -- Added to track full URL
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_tracking_funnel ON funnel_tracking(funnel_name);
CREATE INDEX IF NOT EXISTS idx_tracking_session ON funnel_tracking(session_id);
CREATE INDEX IF NOT EXISTS idx_tracking_created ON funnel_tracking(created_at);

-- Nurture Queue Table (WhatsApp/Email follow-up for non-buyers)
CREATE TABLE IF NOT EXISTS nurture_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    phone VARCHAR(20),
    pcos_type VARCHAR(50),
    confidence VARCHAR(20),
    funnel VARCHAR(50) DEFAULT 'pcos',
    session_id VARCHAR(100),
    assessment_completed_at DATETIME,
    sales_page_viewed_at DATETIME,
    status VARCHAR(20) DEFAULT 'pending',
    nurture_step INTEGER DEFAULT 0,
    next_contact_at DATETIME,
    last_contacted_at DATETIME,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_nurture_email ON nurture_queue(email);
CREATE INDEX IF NOT EXISTS idx_nurture_status ON nurture_queue(status);
CREATE INDEX IF NOT EXISTS idx_nurture_next_contact ON nurture_queue(next_contact_at);

CREATE TRIGGER IF NOT EXISTS update_nurture_queue_timestamp
    AFTER UPDATE ON nurture_queue
    BEGIN
        UPDATE nurture_queue SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;