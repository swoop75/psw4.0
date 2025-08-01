-- ===================================================================
-- PSW 4.0 Database Optimization Script
-- ===================================================================

-- Portfolio table indexes
CREATE INDEX IF NOT EXISTS idx_portfolio_isin_active ON psw_portfolio.portfolio (isin, is_active);
CREATE INDEX IF NOT EXISTS idx_portfolio_value_desc ON psw_portfolio.portfolio (current_value_sek DESC);
CREATE INDEX IF NOT EXISTS idx_portfolio_updated ON psw_portfolio.portfolio (updated_at);

-- Masterlist indexes
CREATE INDEX IF NOT EXISTS idx_masterlist_isin ON psw_foundation.masterlist (isin);
CREATE INDEX IF NOT EXISTS idx_masterlist_name ON psw_foundation.masterlist (name);

-- Create summary tables updated by triggers
CREATE TABLE IF NOT EXISTS psw_portfolio.portfolio_summary (
    summary_date DATE PRIMARY KEY,
    total_value_sek DECIMAL(15,2),
    total_positions INT,
    total_companies INT,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Additional useful indexes for performance
CREATE INDEX IF NOT EXISTS idx_portfolio_shares_held ON psw_portfolio.portfolio (shares_held);
CREATE INDEX IF NOT EXISTS idx_portfolio_currency ON psw_portfolio.portfolio (currency_local);
CREATE INDEX IF NOT EXISTS idx_masterlist_country ON psw_foundation.masterlist (country);

-- Log tables indexes for better performance
CREATE INDEX IF NOT EXISTS idx_log_dividends_date ON psw_portfolio.log_dividends (payment_date);
CREATE INDEX IF NOT EXISTS idx_log_dividends_isin ON psw_portfolio.log_dividends (isin);
CREATE INDEX IF NOT EXISTS idx_log_trades_date ON psw_portfolio.log_trades (trade_date);
CREATE INDEX IF NOT EXISTS idx_log_trades_isin ON psw_portfolio.log_trades (isin);

-- FX rates indexes
CREATE INDEX IF NOT EXISTS idx_fx_rates_currencies ON psw_marketdata.fx_rates_freecurrency (base_currency, target_currency);
CREATE INDEX IF NOT EXISTS idx_fx_rates_updated ON psw_marketdata.fx_rates_freecurrency (updated_at);