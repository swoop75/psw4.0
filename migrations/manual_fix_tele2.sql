-- Manual Fix for Tele2 Rights Issue Portfolio Issue
-- Direct calculation to fix the Tele2 position

USE psw_portfolio;

-- First, let's check what trades exist for Tele2
SELECT 'Current Tele2 Trades:' as info;
SELECT 
    trade_date,
    trade_type_id,
    CASE 
        WHEN trade_type_id = 1 THEN 'BUY'
        WHEN trade_type_id = 2 THEN 'SELL'  
        WHEN trade_type_id = 9 THEN 'RIGHTS_ISSUE'
        ELSE CONCAT('TYPE_', trade_type_id)
    END as trade_type,
    shares_traded,
    price_per_share_sek,
    total_amount_sek
FROM log_trades 
WHERE ticker = 'TEL2 B'
ORDER BY trade_date;

-- Calculate correct totals
SELECT 'Correct Calculations:' as info;
SELECT 
    'Total Shares' as metric,
    SUM(CASE 
        WHEN trade_type_id IN (1, 9) THEN shares_traded  -- BUY and RIGHTS_ISSUE add shares
        WHEN trade_type_id = 2 THEN -shares_traded       -- SELL subtracts shares
        ELSE 0
    END) as value
FROM log_trades 
WHERE ticker = 'TEL2 B'

UNION ALL

SELECT 
    'Total Cost SEK' as metric,
    SUM(CASE 
        WHEN trade_type_id IN (1, 9) THEN total_amount_sek  -- BUY and RIGHTS_ISSUE add cost
        WHEN trade_type_id = 2 THEN -total_amount_sek       -- SELL reduces cost
        ELSE 0
    END) as value
FROM log_trades 
WHERE ticker = 'TEL2 B'

UNION ALL

SELECT 
    'Weighted Avg Cost' as metric,
    SUM(CASE 
        WHEN trade_type_id IN (1, 9) THEN total_amount_sek
        ELSE 0
    END) / SUM(CASE 
        WHEN trade_type_id IN (1, 9) THEN shares_traded
        ELSE 0
    END) as value
FROM log_trades 
WHERE ticker = 'TEL2 B';

-- Now update the portfolio table with correct values
UPDATE portfolio 
SET 
    shares_held = (
        SELECT SUM(CASE 
            WHEN trade_type_id IN (1, 9) THEN shares_traded  -- BUY and RIGHTS_ISSUE
            WHEN trade_type_id = 2 THEN -shares_traded       -- SELL
            ELSE 0
        END)
        FROM log_trades 
        WHERE ticker = 'TEL2 B'
    ),
    total_cost_sek = (
        SELECT SUM(CASE 
            WHEN trade_type_id IN (1, 9) THEN total_amount_sek  -- BUY and RIGHTS_ISSUE
            WHEN trade_type_id = 2 THEN -total_amount_sek       -- SELL
            ELSE 0
        END)
        FROM log_trades 
        WHERE ticker = 'TEL2 B'
    ),
    average_cost_price_sek = (
        SELECT SUM(CASE 
            WHEN trade_type_id IN (1, 9) THEN total_amount_sek
            ELSE 0
        END) / NULLIF(SUM(CASE 
            WHEN trade_type_id IN (1, 9) THEN shares_traded
            ELSE 0
        END), 0)
        FROM log_trades 
        WHERE ticker = 'TEL2 B'
    ),
    updated_at = NOW()
WHERE ticker = 'TEL2 B';

-- Verify the fix
SELECT 'AFTER FIX - Tele2 Position:' as result;
SELECT 
    ticker,
    company_name,
    shares_held,
    ROUND(average_cost_price_sek, 2) as avg_cost_sek,
    ROUND(total_cost_sek, 0) as total_cost_sek,
    last_trade_date
FROM portfolio 
WHERE ticker = 'TEL2 B';

-- Show verification against trade log
SELECT 'Trade Log vs Portfolio Comparison:' as comparison;
SELECT 
    'FROM_TRADES' as source,
    SUM(CASE 
        WHEN trade_type_id IN (1, 9) THEN shares_traded
        WHEN trade_type_id = 2 THEN -shares_traded
        ELSE 0
    END) as shares,
    SUM(CASE 
        WHEN trade_type_id IN (1, 9) THEN total_amount_sek
        WHEN trade_type_id = 2 THEN -total_amount_sek
        ELSE 0
    END) as cost
FROM log_trades 
WHERE ticker = 'TEL2 B'

UNION ALL

SELECT 
    'FROM_PORTFOLIO' as source,
    shares_held,
    total_cost_sek
FROM portfolio 
WHERE ticker = 'TEL2 B';