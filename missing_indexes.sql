-- ===================================================================
-- PSW 4.0 Missing Indexes (Based on Current Analysis)
-- ===================================================================

-- Portfolio table - missing indexes
CREATE INDEX IF NOT EXISTS idx_portfolio_currency ON psw_portfolio.portfolio (currency_local);

-- Masterlist table - missing indexes  
CREATE INDEX IF NOT EXISTS idx_masterlist_country ON psw_foundation.masterlist (country);

-- FX rates table - missing indexes (if table exists)
CREATE INDEX IF NOT EXISTS idx_fx_rates_currencies ON psw_marketdata.fx_rates_freecurrency (base_currency, target_currency);
CREATE INDEX IF NOT EXISTS idx_fx_rates_updated ON psw_marketdata.fx_rates_freecurrency (updated_at);

-- Create portfolio summary table
CREATE TABLE IF NOT EXISTS psw_portfolio.portfolio_summary (
    summary_date DATE PRIMARY KEY,
    total_value_sek DECIMAL(15,2),
    total_positions INT,
    total_companies INT,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clean up duplicate indexes (optional maintenance)
-- Note: You have both idx_portfolio_updated and idx_portfolio_updated_at
-- You can drop one of them:
-- DROP INDEX idx_portfolio_updated_at ON psw_portfolio.portfolio;

SELECT 'Missing indexes added successfully!' as status;