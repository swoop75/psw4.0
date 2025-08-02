-- Fix Portfolio Rights Issue Calculation
-- Issue: RIGHTS_ISSUE trades were being treated as SELL instead of BUY
-- This script corrects the portfolio calculation for rights issues

USE psw_portfolio;

-- Option 1: Use the stored procedure (recommended)
-- CALL sp_rebuild_portfolio_from_trades();

-- Option 2: Manual fix for immediate correction
-- Clear and rebuild portfolio using corrected logic

TRUNCATE TABLE portfolio;

INSERT INTO portfolio (
    isin, 
    ticker, 
    company_name, 
    shares_held, 
    average_cost_price_sek,
    total_cost_sek,
    currency_local, 
    last_trade_date,
    is_active
)
SELECT 
    lt.isin,
    lt.ticker,
    CASE 
        WHEN lt.ticker = 'BETS B' THEN 'Betsson AB (B)'
        WHEN lt.ticker = 'Betsson' THEN 'Betsson AB'
        WHEN lt.ticker = 'Tele2 B' THEN 'Tele2 AB (B)'
        WHEN lt.ticker = 'TEL2 B' THEN 'Tele2 AB (B)'
        WHEN lt.ticker = 'SWMA' THEN 'SwedenCare AB'
        ELSE CONCAT(lt.ticker, ' Company')
    END as company_name,
    -- CORRECTED: Rights issues ADD shares (positive)
    SUM(CASE 
        WHEN lt.trade_type_id = 1 THEN lt.shares_traded   -- BUY
        WHEN lt.trade_type_id = 2 THEN -lt.shares_traded  -- SELL  
        WHEN lt.trade_type_id = 9 THEN lt.shares_traded   -- RIGHTS_ISSUE (CORRECTED: was negative, now positive)
        ELSE 0 
    END) as shares_held,
    -- Calculate weighted average cost price
    CASE 
        WHEN SUM(CASE 
            WHEN lt.trade_type_id IN (1, 9) THEN lt.shares_traded 
            ELSE 0 
        END) > 0 THEN
            SUM(CASE 
                WHEN lt.trade_type_id IN (1, 9) THEN lt.total_amount_sek 
                ELSE 0 
            END) / SUM(CASE 
                WHEN lt.trade_type_id IN (1, 9) THEN lt.shares_traded 
                ELSE 0 
            END)
        ELSE 0
    END as average_cost_price_sek,
    -- CORRECTED: Rights issues INCREASE total cost (positive)
    SUM(CASE 
        WHEN lt.trade_type_id = 1 THEN lt.total_amount_sek   -- BUY (cost increases)
        WHEN lt.trade_type_id = 2 THEN -lt.total_amount_sek  -- SELL (cost decreases)
        WHEN lt.trade_type_id = 9 THEN lt.total_amount_sek   -- RIGHTS_ISSUE (CORRECTED: cost increases)
        ELSE 0 
    END) as total_cost_sek,
    lt.currency_local,
    MAX(lt.trade_date) as last_trade_date,
    1 as is_active
FROM log_trades lt
WHERE lt.isin IS NOT NULL 
    AND lt.ticker IS NOT NULL
    AND lt.shares_traded IS NOT NULL
    AND lt.shares_traded > 0
GROUP BY lt.isin, lt.ticker, lt.currency_local
HAVING shares_held > 0
ORDER BY shares_held DESC;

-- Verify the fix for Tele2
SELECT 'Before and After Portfolio Fix - Tele2 Position:' as status;

SELECT 
    'Trade Log Summary' as section,
    ticker,
    SUM(shares_traded) as total_shares_from_trades,
    COUNT(*) as trade_count
FROM log_trades 
WHERE ticker = 'TEL2 B'
GROUP BY ticker

UNION ALL

SELECT 
    'Portfolio Table' as section,
    ticker,
    shares_held as total_shares_in_portfolio,
    NULL as trade_count
FROM portfolio 
WHERE ticker = 'TEL2 B';

-- Show detailed trade breakdown for verification
SELECT 
    'Trade Details for TEL2 B' as info,
    trade_date,
    CAST(trade_type_id AS CHAR) as trade_type_id,
    CASE 
        WHEN trade_type_id = 1 THEN 'BUY'
        WHEN trade_type_id = 2 THEN 'SELL'
        WHEN trade_type_id = 9 THEN 'RIGHTS_ISSUE'
        ELSE 'OTHER'
    END as trade_type,
    shares_traded,
    total_amount_sek
FROM log_trades 
WHERE ticker = 'TEL2 B'
ORDER BY trade_date;

-- Final verification
SELECT 'Portfolio positions after fix:' as result;
SELECT 
    ticker,
    company_name,
    shares_held,
    ROUND(average_cost_price_sek, 2) as avg_cost,
    ROUND(total_cost_sek, 2) as total_cost,
    last_trade_date
FROM portfolio 
WHERE ticker = 'TEL2 B';