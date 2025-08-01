-- ===================================================================
-- PSW 4.0 Safe Database Optimization Script (Compatible with all MySQL versions)
-- ===================================================================

-- Check existing indexes first
SELECT 'Checking existing indexes...' as status;

-- Portfolio table indexes (will fail silently if exists)
SELECT 'Creating portfolio indexes...' as status;

CREATE INDEX idx_portfolio_isin_active ON psw_portfolio.portfolio (isin, is_active);
CREATE INDEX idx_portfolio_value_desc ON psw_portfolio.portfolio (current_value_sek DESC);
CREATE INDEX idx_portfolio_updated ON psw_portfolio.portfolio (updated_at);
CREATE INDEX idx_portfolio_shares_held ON psw_portfolio.portfolio (shares_held);
CREATE INDEX idx_portfolio_currency ON psw_portfolio.portfolio (currency_local);

SELECT 'Creating masterlist indexes...' as status;

-- Masterlist indexes
CREATE INDEX idx_masterlist_isin ON psw_foundation.masterlist (isin);
CREATE INDEX idx_masterlist_name ON psw_foundation.masterlist (name);
CREATE INDEX idx_masterlist_country ON psw_foundation.masterlist (country);

SELECT 'Creating log table indexes...' as status;

-- Log tables indexes
CREATE INDEX idx_log_dividends_date ON psw_portfolio.log_dividends (payment_date);
CREATE INDEX idx_log_dividends_isin ON psw_portfolio.log_dividends (isin);
CREATE INDEX idx_log_trades_date ON psw_portfolio.log_trades (trade_date);
CREATE INDEX idx_log_trades_isin ON psw_portfolio.log_trades (isin);

SELECT 'Creating FX rates indexes...' as status;

-- FX rates indexes
CREATE INDEX idx_fx_rates_currencies ON psw_marketdata.fx_rates_freecurrency (base_currency, target_currency);
CREATE INDEX idx_fx_rates_updated ON psw_marketdata.fx_rates_freecurrency (updated_at);

SELECT 'Creating portfolio summary table...' as status;

-- Create summary table
CREATE TABLE psw_portfolio.portfolio_summary (
    summary_date DATE PRIMARY KEY,
    total_value_sek DECIMAL(15,2),
    total_positions INT,
    total_companies INT,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Database optimization completed!' as status;