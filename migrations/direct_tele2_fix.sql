-- Direct Fix for Tele2 Position
-- Based on your trade data: 133 + 70 + 25 + 20 = 248 shares

USE psw_portfolio;

-- Update Tele2 position with correct calculations
UPDATE portfolio 
SET 
    shares_held = 248.0000,  -- 133 + 70 + 25 + 20
    total_cost_sek = 17077.4000,  -- 9482.90 + 4889.50 + 1325.00 + 1380.00  
    average_cost_price_sek = 17077.4000 / 248.0000,  -- Total cost / Total shares
    updated_at = NOW()
WHERE ticker = 'TEL2 B' AND isin = 'SE0005190238';

-- Verify the fix
SELECT 'FIXED - Tele2 Position:' as status;
SELECT 
    ticker,
    company_name,
    shares_held,
    ROUND(average_cost_price_sek, 4) as avg_cost_sek,
    ROUND(total_cost_sek, 2) as total_cost_sek,
    last_trade_date,
    updated_at
FROM portfolio 
WHERE ticker = 'TEL2 B';

-- Show the math breakdown
SELECT 'Trade Breakdown Verification:' as info;
SELECT 
    '2016-08-17 BUY' as trade,
    133 as shares,
    9482.90 as cost_sek
UNION ALL
SELECT 
    '2016-09-13 BUY' as trade,
    70 as shares,
    4889.50 as cost_sek
UNION ALL
SELECT 
    '2016-11-29 RIGHTS_ISSUE' as trade,
    25 as shares,
    1325.00 as cost_sek
UNION ALL
SELECT 
    '2016-12-02 BUY' as trade,
    20 as shares,
    1380.00 as cost_sek
UNION ALL
SELECT 
    'TOTAL (should match portfolio)' as trade,
    248 as shares,
    17077.40 as cost_sek;