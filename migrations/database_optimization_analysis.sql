-- PSW 4.0 Database Optimization Analysis and Recommendations
-- Based on database structure analysis and usage patterns

-- ============================================================================
-- 1. PORTFOLIO PERFORMANCE VIEW
-- ============================================================================
-- Unified view for portfolio holdings with real-time pricing and performance
CREATE OR REPLACE VIEW psw_portfolio.vw_portfolio_performance AS
SELECT 
    p.portfolio_id,
    p.isin,
    p.ticker,
    p.company_name,
    p.shares_held,
    p.currency_local,
    p.last_trade_date,
    
    -- Company details from masterlist
    ml.name as official_company_name,
    ml.country as company_country,
    ml.market as primary_market,
    
    -- Sector information from instruments
    COALESCE(s1.name, s2.name, 'Unknown') as sector,
    COALESCE(s1.sectorId, s2.sectorId) as sector_id,
    
    -- Latest pricing data
    COALESCE(glp.price, nlp.price) as latest_price,
    COALESCE(glp.currency, nlp.currency, p.currency_local) as price_currency,
    COALESCE(glp.last_updated, nlp.last_updated) as price_updated,
    
    -- FX rates for currency conversion
    fx.rate as fx_to_sek,
    fx.last_updated as fx_updated,
    
    -- Calculated values
    CASE 
        WHEN COALESCE(glp.price, nlp.price) IS NOT NULL THEN
            p.shares_held * COALESCE(glp.price, nlp.price)
        ELSE COALESCE(p.current_value_local, 0)
    END as current_value_local,
    
    CASE 
        WHEN COALESCE(glp.price, nlp.price) IS NOT NULL AND fx.rate IS NOT NULL THEN
            p.shares_held * COALESCE(glp.price, nlp.price) * fx.rate
        WHEN COALESCE(glp.price, nlp.price) IS NOT NULL AND p.currency_local = 'SEK' THEN
            p.shares_held * COALESCE(glp.price, nlp.price)
        ELSE COALESCE(p.current_value_sek, 0)
    END as current_value_sek,
    
    -- Performance calculations
    COALESCE(p.total_cost_sek, 0) as cost_basis_sek,
    CASE 
        WHEN p.total_cost_sek > 0 THEN
            ((CASE 
                WHEN COALESCE(glp.price, nlp.price) IS NOT NULL AND fx.rate IS NOT NULL THEN
                    p.shares_held * COALESCE(glp.price, nlp.price) * fx.rate
                WHEN COALESCE(glp.price, nlp.price) IS NOT NULL AND p.currency_local = 'SEK' THEN
                    p.shares_held * COALESCE(glp.price, nlp.price)
                ELSE COALESCE(p.current_value_sek, 0)
            END) - p.total_cost_sek) / p.total_cost_sek * 100
        ELSE 0
    END as return_percent,
    
    p.is_active
    
FROM psw_portfolio.portfolio p
LEFT JOIN psw_foundation.masterlist ml ON p.isin = ml.isin
LEFT JOIN psw_marketdata.nordic_instruments ni ON p.isin = ni.isin
LEFT JOIN psw_marketdata.global_instruments gi ON p.isin = gi.isin
LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.sectorId
LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.sectorId
LEFT JOIN psw_marketdata.global_latest_prices glp ON p.isin = glp.isin
LEFT JOIN psw_marketdata.nordic_latest_prices nlp ON p.ticker = nlp.ticker
LEFT JOIN psw_marketdata.fx_rates_freecurrency fx ON p.currency_local = fx.from_currency AND fx.to_currency = 'SEK'
WHERE p.is_active = 1 AND p.shares_held > 0;

-- ============================================================================
-- 2. DIVIDEND ANALYTICS VIEW
-- ============================================================================
-- Comprehensive dividend analysis with portfolio context
CREATE OR REPLACE VIEW psw_portfolio.vw_dividend_analytics AS
SELECT 
    ld.dividend_id,
    ld.isin,
    ld.ticker,
    ld.payment_date,
    ld.ex_date,
    ld.record_date,
    ld.shares_held_at_ex_date,
    ld.dividend_per_share_local,
    ld.net_dividend_local,
    ld.net_dividend_sek,
    ld.currency,
    ld.tax_withheld_percent,
    
    -- Company information
    ml.name as company_name,
    ml.country as company_country,
    COALESCE(s1.name, s2.name, 'Unknown') as sector,
    
    -- Portfolio context
    p.shares_held as current_shares_held,
    p.current_value_sek as current_position_value,
    
    -- Dividend yield calculations
    CASE 
        WHEN p.current_value_sek > 0 THEN
            (ld.net_dividend_sek / p.current_value_sek) * 100
        ELSE 0
    END as dividend_yield_current_position,
    
    -- Annualized projections (quarterly assumption)
    ld.net_dividend_sek * 4 as annualized_dividend_sek,
    
    -- Time-based groupings
    YEAR(ld.payment_date) as payment_year,
    QUARTER(ld.payment_date) as payment_quarter,
    MONTH(ld.payment_date) as payment_month,
    DAYNAME(ld.payment_date) as payment_day_name
    
FROM psw_portfolio.log_dividends ld
LEFT JOIN psw_foundation.masterlist ml ON ld.isin = ml.isin
LEFT JOIN psw_portfolio.portfolio p ON ld.isin = p.isin
LEFT JOIN psw_marketdata.nordic_instruments ni ON ld.isin = ni.isin
LEFT JOIN psw_marketdata.global_instruments gi ON ld.isin = gi.isin
LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.sectorId
LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.sectorId
WHERE ld.net_dividend_sek > 0;

-- ============================================================================
-- 3. TRADE ANALYTICS VIEW
-- ============================================================================
-- Trade history with performance tracking
CREATE OR REPLACE VIEW psw_portfolio.vw_trade_analytics AS
SELECT 
    lt.trade_id,
    lt.trade_date,
    lt.settlement_date,
    lt.isin,
    lt.ticker,
    lt.shares_traded,
    lt.price_per_share_local,
    lt.price_per_share_sek,
    lt.total_amount_sek,
    lt.currency_local,
    lt.broker_fees_sek,
    lt.net_amount_sek,
    
    -- Trade type information
    tt.type_code,
    tt.description as trade_type_description,
    CASE WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN') THEN 'ACQUIRE'
         WHEN tt.type_code IN ('SELL', 'TRANSFER_OUT') THEN 'DISPOSE'
         ELSE 'OTHER' END as trade_category,
    
    -- Company information
    ml.name as company_name,
    ml.country as company_country,
    COALESCE(s1.name, s2.name, 'Unknown') as sector,
    
    -- Current market context
    COALESCE(glp.price, nlp.price) as current_price,
    CASE 
        WHEN COALESCE(glp.price, nlp.price) IS NOT NULL AND fx.rate IS NOT NULL THEN
            COALESCE(glp.price, nlp.price) * fx.rate
        WHEN COALESCE(glp.price, nlp.price) IS NOT NULL AND lt.currency_local = 'SEK' THEN
            COALESCE(glp.price, nlp.price)
        ELSE NULL
    END as current_price_sek,
    
    -- Performance vs trade price
    CASE 
        WHEN lt.price_per_share_sek > 0 AND COALESCE(glp.price, nlp.price) IS NOT NULL AND fx.rate IS NOT NULL THEN
            ((COALESCE(glp.price, nlp.price) * fx.rate) - lt.price_per_share_sek) / lt.price_per_share_sek * 100
        WHEN lt.price_per_share_sek > 0 AND COALESCE(glp.price, nlp.price) IS NOT NULL AND lt.currency_local = 'SEK' THEN
            (COALESCE(glp.price, nlp.price) - lt.price_per_share_sek) / lt.price_per_share_sek * 100
        ELSE NULL
    END as price_change_percent_since_trade,
    
    -- Time-based groupings
    YEAR(lt.trade_date) as trade_year,
    QUARTER(lt.trade_date) as trade_quarter,
    MONTH(lt.trade_date) as trade_month
    
FROM psw_portfolio.log_trades lt
LEFT JOIN psw_foundation.trade_types tt ON lt.trade_type_id = tt.trade_type_id
LEFT JOIN psw_foundation.masterlist ml ON lt.isin = ml.isin
LEFT JOIN psw_marketdata.nordic_instruments ni ON lt.isin = ni.isin
LEFT JOIN psw_marketdata.global_instruments gi ON lt.isin = gi.isin
LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.sectorId
LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.sectorId
LEFT JOIN psw_marketdata.global_latest_prices glp ON lt.isin = glp.isin
LEFT JOIN psw_marketdata.nordic_latest_prices nlp ON lt.ticker = nlp.ticker
LEFT JOIN psw_marketdata.fx_rates_freecurrency fx ON lt.currency_local = fx.from_currency AND fx.to_currency = 'SEK';

-- ============================================================================
-- 4. SECTOR ALLOCATION VIEW
-- ============================================================================
-- Portfolio allocation by sector with performance metrics
CREATE OR REPLACE VIEW psw_portfolio.vw_sector_allocation AS
SELECT 
    COALESCE(s1.name, s2.name, 'Unknown') as sector,
    COALESCE(s1.sectorId, s2.sectorId, 0) as sector_id,
    COUNT(*) as positions,
    SUM(p.shares_held) as total_shares,
    SUM(COALESCE(p.current_value_sek, 0)) as total_value_sek,
    SUM(COALESCE(p.total_cost_sek, 0)) as total_cost_sek,
    
    -- Performance metrics
    CASE 
        WHEN SUM(COALESCE(p.total_cost_sek, 0)) > 0 THEN
            (SUM(COALESCE(p.current_value_sek, 0)) - SUM(COALESCE(p.total_cost_sek, 0))) / SUM(COALESCE(p.total_cost_sek, 0)) * 100
        ELSE 0
    END as sector_return_percent,
    
    -- Portfolio weight
    SUM(COALESCE(p.current_value_sek, 0)) / (SELECT SUM(COALESCE(current_value_sek, 0)) FROM psw_portfolio.portfolio WHERE is_active = 1) * 100 as portfolio_weight_percent,
    
    -- Dividend metrics
    COALESCE(div_stats.annual_dividends_sek, 0) as annual_dividends_sek,
    CASE 
        WHEN SUM(COALESCE(p.current_value_sek, 0)) > 0 THEN
            COALESCE(div_stats.annual_dividends_sek, 0) / SUM(COALESCE(p.current_value_sek, 0)) * 100
        ELSE 0
    END as sector_yield_percent
    
FROM psw_portfolio.portfolio p
LEFT JOIN psw_marketdata.nordic_instruments ni ON p.isin = ni.isin
LEFT JOIN psw_marketdata.global_instruments gi ON p.isin = gi.isin
LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.sectorId
LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.sectorId
LEFT JOIN (
    SELECT 
        COALESCE(s1.name, s2.name, 'Unknown') as sector,
        SUM(ld.net_dividend_sek) * 4 as annual_dividends_sek  -- Quarterly assumption
    FROM psw_portfolio.log_dividends ld
    LEFT JOIN psw_marketdata.nordic_instruments ni ON ld.isin = ni.isin
    LEFT JOIN psw_marketdata.global_instruments gi ON ld.isin = gi.isin
    LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.sectorId
    LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.sectorId
    WHERE ld.payment_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    GROUP BY COALESCE(s1.name, s2.name, 'Unknown')
) div_stats ON COALESCE(s1.name, s2.name, 'Unknown') = div_stats.sector
WHERE p.is_active = 1 AND p.shares_held > 0
GROUP BY COALESCE(s1.name, s2.name, 'Unknown'), COALESCE(s1.sectorId, s2.sectorId, 0)
ORDER BY total_value_sek DESC;

-- ============================================================================
-- 5. DASHBOARD SUMMARY VIEW
-- ============================================================================
-- High-level portfolio metrics for dashboard
CREATE OR REPLACE VIEW psw_portfolio.vw_dashboard_summary AS
SELECT 
    -- Portfolio totals
    COUNT(*) as total_positions,
    SUM(shares_held) as total_shares,
    SUM(COALESCE(current_value_sek, 0)) as total_value_sek,
    SUM(COALESCE(total_cost_sek, 0)) as total_cost_sek,
    
    -- Performance
    CASE 
        WHEN SUM(COALESCE(total_cost_sek, 0)) > 0 THEN
            (SUM(COALESCE(current_value_sek, 0)) - SUM(COALESCE(total_cost_sek, 0))) / SUM(COALESCE(total_cost_sek, 0)) * 100
        ELSE 0
    END as total_return_percent,
    
    SUM(COALESCE(current_value_sek, 0)) - SUM(COALESCE(total_cost_sek, 0)) as unrealized_gain_loss_sek,
    
    -- Diversification metrics
    COUNT(DISTINCT COALESCE(s1.name, s2.name, 'Unknown')) as sectors_count,
    COUNT(DISTINCT ml.country) as countries_count,
    COUNT(DISTINCT p.currency_local) as currencies_count
    
FROM psw_portfolio.portfolio p
LEFT JOIN psw_foundation.masterlist ml ON p.isin = ml.isin
LEFT JOIN psw_marketdata.nordic_instruments ni ON p.isin = ni.isin
LEFT JOIN psw_marketdata.global_instruments gi ON p.isin = gi.isin
LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.sectorId
LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.sectorId
WHERE p.is_active = 1 AND p.shares_held > 0;

-- Show created views
SHOW TABLES LIKE 'vw_%';