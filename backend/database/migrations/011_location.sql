-- Migration 011: Add location columns to users table
-- Supports geo-adaptive plan generation (v2 location-aware)

ALTER TABLE users
  ADD COLUMN country_code CHAR(2) NULL COMMENT 'ISO 3166-1 alpha-2: RS, KE, DE, PH...',
  ADD COLUMN country_name VARCHAR(80) NULL,
  ADD COLUMN region_city VARCHAR(120) NULL,
  ADD COLUMN locale VARCHAR(10) NULL COMMENT 'en-KE, sr-RS, en-US...',
  ADD COLUMN measurement_system ENUM('metric','imperial') DEFAULT 'metric',
  ADD COLUMN cuisine_pref VARCHAR(160) NULL COMMENT 'vegetarian, halal, Mediterranean, etc.',
  ADD COLUMN climate_zone VARCHAR(40) NULL COMMENT 'temperate, tropical, arid, continental...';

-- Index for efficient region lookups
CREATE INDEX idx_users_country ON users(country_code);
CREATE INDEX idx_users_region ON users(region_city);