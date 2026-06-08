-- Add subscription tracking to member profiles
ALTER TABLE member_profiles ADD COLUMN subscription_tier VARCHAR(20) DEFAULT '30-day'; -- '30-day' or '90-day'
ALTER TABLE member_profiles ADD COLUMN subscription_status VARCHAR(20) DEFAULT 'active'; -- 'active', 'expired', 'upgraded'
ALTER TABLE member_profiles ADD COLUMN start_date DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE member_profiles ADD COLUMN end_date DATETIME;
