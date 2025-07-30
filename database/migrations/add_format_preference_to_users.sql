-- Migration: Add format preference to users table
-- File: database/migrations/add_format_preference_to_users.sql
-- Description: Add format_preference column to users table for localization settings

USE psw_foundation;

-- Add format_preference column to users table
ALTER TABLE users 
ADD COLUMN format_preference VARCHAR(5) DEFAULT 'US' 
COMMENT 'User preferred format (US, EU, SE, UK, DE, FR)' 
AFTER last_login;

-- Update existing users to have default US format
UPDATE users 
SET format_preference = 'US' 
WHERE format_preference IS NULL;

-- Add index for performance
CREATE INDEX idx_users_format_preference ON users(format_preference);

-- Show updated table structure
DESCRIBE users;