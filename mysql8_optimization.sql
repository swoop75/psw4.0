-- ===================================================================
-- PSW 4.0 Database Optimization Script for MySQL 8.0+
-- ===================================================================

-- Check current indexes first
SELECT 'Current indexes on portfolio table:' as info;
SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'psw_portfolio' 
AND TABLE_NAME = 'portfolio' 
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT 'Current indexes on masterlist table:' as info;
SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'psw_foundation' 
AND TABLE_NAME = 'masterlist' 
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- Create indexes only if they don't exist (MySQL 8.0+ syntax)
SELECT 'Creating portfolio indexes...' as status;

CREATE INDEX IF NOT EXISTS idx_portfolio_isin_active ON psw_portfolio.portfolio (isin, is_active);
CREATE INDEX IF NOT EXISTS idx_portfolio_value_desc ON psw_portfolio.portfolio (current_value_sek DESC);
CREATE INDEX IF NOT EXISTS idx_portfolio_shares_held ON psw_portfolio.portfolio (shares_held);
CREATE INDEX IF NOT EXISTS idx_portfolio_currency ON psw_portfolio.portfolio (currency_local);

SELECT 'Creating masterlist indexes...' as status;

CREATE INDEX IF NOT EXISTS idx_masterlist_isin ON psw_foundation.masterlist (isin);
CREATE INDEX IF NOT EXISTS idx_masterlist_name ON psw_foundation.masterlist (name);
CREATE INDEX IF NOT EXISTS idx_masterlist_country ON psw_foundation.masterlist (country);

SELECT 'Creating log table indexes...' as status;

CREATE INDEX IF NOT EXISTS idx_log_dividends_date ON psw_portfolio.log_dividends (payment_date);
CREATE INDEX IF NOT EXISTS idx_log_dividends_isin ON psw_portfolio.log_dividends (isin);
CREATE INDEX IF NOT EXISTS idx_log_trades_date ON psw_portfolio.log_trades (trade_date);
CREATE INDEX IF NOT EXISTS idx_log_trades_isin ON psw_portfolio.log_trades (isin);

SELECT 'Creating FX rates indexes...' as status;

CREATE INDEX IF NOT EXISTS idx_fx_rates_currencies ON psw_marketdata.fx_rates_freecurrency (base_currency, target_currency);
CREATE INDEX IF NOT EXISTS idx_fx_rates_updated ON psw_marketdata.fx_rates_freecurrency (updated_at);

SELECT 'Creating portfolio summary table...' as status;

CREATE TABLE IF NOT EXISTS psw_portfolio.portfolio_summary (
    summary_date DATE PRIMARY KEY,
    total_value_sek DECIMAL(15,2),
    total_positions INT,
    total_companies INT,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Final verification
SELECT 'Final index verification:' as info;
SELECT 
    TABLE_SCHEMA,
    TABLE_NAME, 
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA IN ('psw_portfolio', 'psw_foundation', 'psw_marketdata')
AND INDEX_NAME LIKE 'idx_%'
GROUP BY TABLE_SCHEMA, TABLE_NAME, INDEX_NAME
ORDER BY TABLE_SCHEMA, TABLE_NAME, INDEX_NAME;

SELECT 'Database optimization completed!' as status;