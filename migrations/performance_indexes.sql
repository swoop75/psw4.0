-- PSW 4.0 Performance Optimization - Recommended Indexes
-- Based on query patterns and JOIN operations

-- ============================================================================
-- PORTFOLIO TABLE INDEXES
-- ============================================================================
-- These may already exist, but ensuring optimal coverage

-- Performance index for active positions query
CREATE INDEX IF NOT EXISTS idx_portfolio_active_shares ON psw_portfolio.portfolio (is_active, shares_held);

-- Composite index for ISIN lookups with active filter
CREATE INDEX IF NOT EXISTS idx_portfolio_isin_active ON psw_portfolio.portfolio (isin, is_active);

-- Index for sorting by value (dashboard, overview pages)
CREATE INDEX IF NOT EXISTS idx_portfolio_value_desc ON psw_portfolio.portfolio (current_value_sek DESC);

-- ============================================================================
-- TRADE LOGS INDEXES
-- ============================================================================
-- For trade history and portfolio calculations

-- Composite index for ISIN-based aggregations
CREATE INDEX IF NOT EXISTS idx_trades_isin_date ON psw_portfolio.log_trades (isin, trade_date);

-- Index for trade type joins and filtering
CREATE INDEX IF NOT EXISTS idx_trades_type_date ON psw_portfolio.log_trades (trade_type_id, trade_date);

-- Index for ticker-based lookups
CREATE INDEX IF NOT EXISTS idx_trades_ticker_date ON psw_portfolio.log_trades (ticker, trade_date);

-- Index for settlement date queries
CREATE INDEX IF NOT EXISTS idx_trades_settlement ON psw_portfolio.log_trades (settlement_date);

-- ============================================================================
-- DIVIDEND LOGS INDEXES
-- ============================================================================
-- For dividend analytics and reporting

-- Composite index for ISIN and payment date
CREATE INDEX IF NOT EXISTS idx_dividends_isin_payment ON psw_portfolio.log_dividends (isin, payment_date);

-- Index for ex-date lookups (upcoming dividends)
CREATE INDEX IF NOT EXISTS idx_dividends_ex_date ON psw_portfolio.log_dividends (ex_date);

-- Index for payment year/quarter analysis
CREATE INDEX IF NOT EXISTS idx_dividends_payment_year ON psw_portfolio.log_dividends (payment_date, net_dividend_sek);

-- Index for ticker-based dividend lookups
CREATE INDEX IF NOT EXISTS idx_dividends_ticker_payment ON psw_portfolio.log_dividends (ticker, payment_date);

-- ============================================================================
-- MASTERLIST TABLE INDEXES
-- ============================================================================
-- For company information joins

-- Index for country-based filtering
CREATE INDEX IF NOT EXISTS idx_masterlist_country ON psw_foundation.masterlist (country);

-- Index for market-based filtering
CREATE INDEX IF NOT EXISTS idx_masterlist_market ON psw_foundation.masterlist (market);

-- Composite index for active listings
CREATE INDEX IF NOT EXISTS idx_masterlist_active ON psw_foundation.masterlist (delisted, current_version);

-- ============================================================================
-- MARKETDATA INDEXES
-- ============================================================================
-- For instrument and pricing data

-- Nordic instruments indexes
CREATE INDEX IF NOT EXISTS idx_nordic_isin ON psw_marketdata.nordic_instruments (isin);
CREATE INDEX IF NOT EXISTS idx_nordic_sector ON psw_marketdata.nordic_instruments (sectorID);
CREATE INDEX IF NOT EXISTS idx_nordic_ticker ON psw_marketdata.nordic_instruments (ticker);

-- Global instruments indexes
CREATE INDEX IF NOT EXISTS idx_global_isin ON psw_marketdata.global_instruments (isin);
CREATE INDEX IF NOT EXISTS idx_global_sector ON psw_marketdata.global_instruments (sectorId);
CREATE INDEX IF NOT EXISTS idx_global_ticker ON psw_marketdata.global_instruments (ticker);

-- Latest prices indexes
CREATE INDEX IF NOT EXISTS idx_global_prices_isin_updated ON psw_marketdata.global_latest_prices (isin, last_updated);
CREATE INDEX IF NOT EXISTS idx_nordic_prices_ticker_updated ON psw_marketdata.nordic_latest_prices (ticker, last_updated);

-- FX rates indexes
CREATE INDEX IF NOT EXISTS idx_fx_rates_from_to ON psw_marketdata.fx_rates_freecurrency (from_currency, to_currency, last_updated);

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Check index usage and performance

-- Show all indexes on portfolio table
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    CARDINALITY
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'psw_portfolio' 
    AND TABLE_NAME = 'portfolio'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Show all indexes on trade logs
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    CARDINALITY
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'psw_portfolio' 
    AND TABLE_NAME = 'log_trades'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;