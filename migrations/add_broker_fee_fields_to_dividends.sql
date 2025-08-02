-- Migration: Add broker fee fields to log_dividends table
-- This enables proper fee % calculation for dividend logs
-- Date: 2025-01-02

USE psw_portfolio;

-- Check current table structure first
SELECT 'Current log_dividends structure:' as info;
DESCRIBE log_dividends;

-- Add broker fee columns if they don't exist
ALTER TABLE log_dividends 
ADD COLUMN IF NOT EXISTS broker_fee_local DECIMAL(15,4) DEFAULT 0 COMMENT 'Broker fee in local currency',
ADD COLUMN IF NOT EXISTS broker_fee_sek DECIMAL(15,4) DEFAULT 0 COMMENT 'Broker fee in SEK',
ADD COLUMN IF NOT EXISTS broker_fee_percent DECIMAL(8,4) DEFAULT 0 COMMENT 'Broker fee as percentage of dividend amount';

-- Verify the columns were added
SELECT 'Updated log_dividends structure:' as info;
DESCRIBE log_dividends;

-- Show sample data to verify
SELECT 'Sample record after migration:' as info;
SELECT 
    dividend_log_id,
    payment_date,
    ticker,
    dividend_amount_sek,
    broker_fee_local,
    broker_fee_sek,
    broker_fee_percent,
    created_at
FROM log_dividends 
LIMIT 3;

-- Migration completed successfully
SELECT 'Migration completed: Broker fee fields added to log_dividends table' as result;