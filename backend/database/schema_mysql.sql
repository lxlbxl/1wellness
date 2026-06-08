-- OJG Herbal Health Assessment System Database Schema
-- MySQL Database Structure

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Users table for storing customer information
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100), -- Made optional
    phone VARCHAR(20),
    date_of_birth DATE,
    gender VARCHAR(20),
    type VARCHAR(20) DEFAULT 'lead',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active'
);

-- Admin users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'manager', 'viewer') DEFAULT 'admin',
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- PCOS Assessments
CREATE TABLE IF NOT EXISTS pcos_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    age INT NOT NULL,
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    bmi DECIMAL(4,2),
    menstrual_cycle TEXT,
    symptoms TEXT, -- JSON format for multiple symptoms
    lifestyle_factors TEXT, -- JSON format
    medical_history TEXT,
    current_medications TEXT,
    assessment_score INT,
    recommendations TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Acne Assessments
CREATE TABLE IF NOT EXISTS acne_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    age INT NOT NULL,
    skin_type ENUM('oily', 'dry', 'combination', 'sensitive', 'normal'),
    acne_severity ENUM('mild', 'moderate', 'severe'),
    acne_type TEXT, -- JSON format for multiple types
    triggers TEXT, -- JSON format
    current_treatment TEXT,
    skincare_routine TEXT,
    lifestyle_factors TEXT, -- JSON format
    assessment_score INT,
    recommendations TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Weight Management Assessments
CREATE TABLE IF NOT EXISTS weight_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    age INT NOT NULL,
    current_weight DECIMAL(5,2) NOT NULL,
    target_weight DECIMAL(5,2),
    height DECIMAL(5,2) NOT NULL,
    current_bmi DECIMAL(4,2),
    target_bmi DECIMAL(4,2),
    activity_level ENUM('sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extremely_active'),
    diet_preferences TEXT, -- JSON format
    health_conditions TEXT,
    weight_history TEXT,
    goals TEXT, -- JSON format
    assessment_score INT,
    recommendations TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sales (
    id VARCHAR(50) PRIMARY KEY,
    user_id INT,
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
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Contact/Lead forms
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    category ENUM('pcos', 'acne', 'weight', 'general'),
    subject VARCHAR(255),
    message TEXT,
    source VARCHAR(50), -- which page/form they came from
    status ENUM('new', 'contacted', 'converted', 'closed') DEFAULT 'new',
    admin_notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- System logs for admin actions
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values TEXT, -- JSON format
    new_values TEXT, -- JSON format
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id)
);

-- Settings table for system configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Generic Assessments Table (used by current form handler)
CREATE TABLE IF NOT EXISTS assessments (
    id VARCHAR(50) PRIMARY KEY,
    user_id INT,
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
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Webhook Queue Table
CREATE TABLE IF NOT EXISTS webhook_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id VARCHAR(50) NOT NULL,
    event VARCHAR(50) NOT NULL,
    payload TEXT NOT NULL, -- JSON
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    last_attempt DATETIME,
    next_attempt DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Funnel Tracking Table
CREATE TABLE IF NOT EXISTS funnel_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100),
    user_id INT,
    email VARCHAR(255),
    funnel_name VARCHAR(50),
    step_name VARCHAR(100),
    event_type VARCHAR(50),
    metadata TEXT,
    url TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_created_at ON users(created_at);
CREATE INDEX idx_pcos_user_id ON pcos_assessments(user_id);
CREATE INDEX idx_pcos_created_at ON pcos_assessments(created_at);
CREATE INDEX idx_acne_user_id ON acne_assessments(user_id);
CREATE INDEX idx_acne_created_at ON acne_assessments(created_at);
CREATE INDEX idx_weight_user_id ON weight_assessments(user_id);
CREATE INDEX idx_weight_created_at ON weight_assessments(created_at);
CREATE INDEX idx_sales_user_id ON sales(user_id);
CREATE INDEX idx_sales_category ON sales(category);
CREATE INDEX idx_sales_order_date ON sales(order_date);
CREATE INDEX idx_contacts_category ON contacts(category);
CREATE INDEX idx_contacts_status ON contacts(status);
CREATE INDEX idx_contacts_created_at ON contacts(created_at);
CREATE INDEX idx_assessments_email ON assessments(email);
CREATE INDEX idx_assessments_type ON assessments(assessment_type);
CREATE INDEX idx_webhook_queue_status ON webhook_queue(status);
CREATE INDEX idx_webhook_queue_next_attempt ON webhook_queue(next_attempt);
CREATE INDEX idx_tracking_funnel ON funnel_tracking(funnel_name);
CREATE INDEX idx_tracking_session ON funnel_tracking(session_id);
CREATE INDEX idx_tracking_created ON funnel_tracking(created_at);

-- Insert default admin user
INSERT IGNORE INTO admin_users (username, email, password_hash, full_name, role) 
VALUES ('admin', 'admin@ojgherbal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');

-- Insert default settings
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'OJG Herbal Health Assessment', 'string', 'Website name'),
('site_email', 'info@ojgherbal.com', 'string', 'Contact email'),
('site_phone', '+1-234-567-8900', 'string', 'Contact phone'),
('assessment_scoring_enabled', '1', 'boolean', 'Enable assessment scoring system'),
('email_notifications', '1', 'boolean', 'Send email notifications for new submissions'),
('max_assessments_per_user', '5', 'integer', 'Maximum assessments per user per day');

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
