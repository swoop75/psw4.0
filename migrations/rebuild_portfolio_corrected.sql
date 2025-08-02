-- Rebuild Portfolio with Corrected Rights Issue Logic
-- This script uses the correct stored procedure logic to fix the rights issue calculation

USE psw_portfolio;

-- Method 1: Use the stored procedure (RECOMMENDED)
-- The stored procedure already has the correct logic for rights issues
CALL sp_rebuild_portfolio_from_trades();

-- Verify the results
SELECT 'Portfolio Rebuilt Successfully' as status;

-- Show Tele2 position verification
SELECT 
    '=== TELE2 VERIFICATION ===' as section,
    NULL as ticker, NULL as shares, NULL as amount, NULL as type;

-- Trade log summary
SELECT 
    'TRADE LOG' as section,
    ticker,
    shares_traded as shares,
    total_amount_sek as amount,
    CASE 
        WHEN trade_type_id = 1 THEN 'BUY'
        WHEN trade_type_id = 9 THEN 'RIGHTS_ISSUE'
        ELSE CONCAT('TYPE_', trade_type_id)
    END as type
FROM log_trades 
WHERE ticker = 'TEL2 B'
ORDER BY trade_date

UNION ALL

SELECT 
    'TOTALS' as section,
    'TEL2 B' as ticker,
    SUM(shares_traded) as shares,
    SUM(total_amount_sek) as amount,
    'ALL_TRADES' as type
FROM log_trades 
WHERE ticker = 'TEL2 B'

UNION ALL

SELECT 
    'PORTFOLIO' as section,
    ticker,
    shares_held as shares,
    total_cost_sek as amount,
    'FINAL_POSITION' as type
FROM portfolio 
WHERE ticker = 'TEL2 B';

-- Show all current positions
SELECT 
    'All Portfolio Positions:' as info,
    ticker,
    company_name,
    shares_held,
    ROUND(average_cost_price_sek, 2) as avg_cost_sek,
    ROUND(total_cost_sek, 0) as total_cost_sek
FROM portfolio 
WHERE shares_held > 0
ORDER BY total_cost_sek DESC;