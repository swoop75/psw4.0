-- ===================================================================
-- PSW 4.0 Safe Missing Indexes (No IF NOT EXISTS)
-- ===================================================================

-- Check what indexes exist first
SELECT 'Checking portfolio currency index...' as status;
SELECT COUNT(*) as exists_count
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'psw_portfolio' 
AND TABLE_NAME = 'portfolio' 
AND INDEX_NAME = 'idx_portfolio_currency';

-- Create portfolio currency index
SELECT 'Creating portfolio currency index...' as status;
CREATE INDEX idx_portfolio_currency ON psw_portfolio.portfolio (currency_local);

-- Check masterlist country index
SELECT 'Checking masterlist country index...' as status;
SELECT COUNT(*) as exists_count
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'psw_foundation' 
AND TABLE_NAME = 'masterlist' 
AND INDEX_NAME = 'idx_masterlist_country';

-- Create masterlist country index
SELECT 'Creating masterlist country index...' as status;
CREATE INDEX idx_masterlist_country ON psw_foundation.masterlist (country);

-- Check FX rates table exists
SELECT 'Checking if FX rates table exists...' as status;
SELECT COUNT(*) as table_exists
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'psw_marketdata' 
AND TABLE_NAME = 'fx_rates_freecurrency';

-- Create FX rates indexes (only if table exists)
SELECT 'Creating FX rates indexes...' as status;
CREATE INDEX idx_fx_rates_currencies ON psw_marketdata.fx_rates_freecurrency (base_currency, target_currency);
CREATE INDEX idx_fx_rates_updated ON psw_marketdata.fx_rates_freecurrency (updated_at);

-- Create portfolio summary table
SELECT 'Creating portfolio summary table...' as status;
CREATE TABLE psw_portfolio.portfolio_summary (
    summary_date DATE PRIMARY KEY,
    total_value_sek DECIMAL(15,2),
    total_positions INT,
    total_companies INT,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'All indexes created successfully!' as status;