-- Force Update Tele2 Position - Bypass any potential issues
USE psw_portfolio;

-- First check current state
SELECT 'BEFORE UPDATE:' as status;
SELECT ticker, shares_held, total_cost_sek, average_cost_price_sek, updated_at 
FROM portfolio 
WHERE ticker = 'TEL2 B';

-- Check if there are any constraints or foreign keys
SELECT 'Table constraints:' as info;
SELECT 
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    TABLE_NAME
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = 'psw_portfolio' 
AND TABLE_NAME = 'portfolio';

-- Try direct UPDATE with explicit values
UPDATE psw_portfolio.portfolio 
SET 
    shares_held = 248,
    total_cost_sek = 17077.40,
    average_cost_price_sek = 68.8589,
    updated_at = CURRENT_TIMESTAMP
WHERE ticker = 'TEL2 B' 
AND isin = 'SE0005190238';

-- Check if update was successful
SELECT 'AFTER UPDATE ATTEMPT 1:' as status;
SELECT ticker, shares_held, total_cost_sek, average_cost_price_sek, updated_at 
FROM portfolio 
WHERE ticker = 'TEL2 B';

-- If still not working, try deleting and reinserting
DELETE FROM portfolio WHERE ticker = 'TEL2 B' AND isin = 'SE0005190238';

INSERT INTO portfolio (
    isin, ticker, company_name, shares_held, average_cost_price_sek, 
    total_cost_sek, currency_local, last_trade_date, is_active, 
    created_at, updated_at
) VALUES (
    'SE0005190238',
    'TEL2 B', 
    'Tele2 AB (B)',
    248.0000,
    68.8589,
    17077.4000,
    'SEK',
    '2016-12-02',
    1,
    NOW(),
    NOW()
);

-- Final verification
SELECT 'FINAL RESULT:' as status;
SELECT 
    ticker,
    company_name,
    shares_held,
    ROUND(average_cost_price_sek, 4) as avg_cost,
    ROUND(total_cost_sek, 2) as total_cost,
    last_trade_date,
    updated_at
FROM portfolio 
WHERE ticker = 'TEL2 B';

-- Verify against trades one more time
SELECT 'TRADE VERIFICATION:' as check_type;
SELECT 
    trade_date,
    CASE 
        WHEN trade_type_id = 1 THEN 'BUY'
        WHEN trade_type_id = 9 THEN 'RIGHTS_ISSUE'
        ELSE CONCAT('TYPE_', trade_type_id)
    END as type,
    shares_traded,
    total_amount_sek
FROM log_trades 
WHERE ticker = 'TEL2 B'
ORDER BY trade_date

UNION ALL

SELECT 
    'TOTAL' as trade_date,
    'SUM' as type,
    SUM(shares_traded) as shares_traded,
    SUM(total_amount_sek) as total_amount_sek
FROM log_trades 
WHERE ticker = 'TEL2 B';