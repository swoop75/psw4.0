-- Debug Portfolio Update Issue
-- Let's investigate what's preventing the portfolio update

USE psw_portfolio;

-- 1. Check if there are any running transactions
SELECT 'Active Transactions:' as info;
SELECT * FROM information_schema.INNODB_TRX;

-- 2. Check if table is locked
SELECT 'Table Locks:' as info;
SHOW OPEN TABLES WHERE Database = 'psw_portfolio' AND Table = 'portfolio';

-- 3. Check exact portfolio record details
SELECT 'Current Portfolio Record:' as info;
SELECT * FROM portfolio WHERE ticker = 'TEL2 B' AND isin = 'SE0005190238';

-- 4. Check if there are multiple records for the same ticker
SELECT 'All TEL2 B records:' as info;
SELECT portfolio_id, isin, ticker, shares_held, total_cost_sek, updated_at 
FROM portfolio 
WHERE ticker LIKE '%TEL2%' OR ticker LIKE '%Tele2%';

-- 5. Try updating a different field first to test
UPDATE portfolio 
SET updated_at = NOW() 
WHERE ticker = 'TEL2 B';

SELECT 'After timestamp update:' as info;
SELECT ticker, shares_held, total_cost_sek, updated_at 
FROM portfolio 
WHERE ticker = 'TEL2 B';

-- 6. Check if there are any events or stored procedures running
SELECT 'Events:' as info;
SELECT EVENT_NAME, STATUS, EVENT_TYPE 
FROM information_schema.EVENTS 
WHERE EVENT_SCHEMA = 'psw_portfolio';

-- 7. Check if there are any foreign key constraints
SELECT 'Foreign Key Constraints:' as info;
SELECT 
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_SCHEMA,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'psw_portfolio' 
AND TABLE_NAME = 'portfolio' 
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- 8. Test direct calculation from trades
SELECT 'Direct calculation from trades:' as calculation;
SELECT 
    ticker,
    SUM(CASE 
        WHEN trade_type_id IN (1, 9) THEN shares_traded
        WHEN trade_type_id = 2 THEN -shares_traded
        ELSE 0
    END) as calculated_shares,
    SUM(CASE 
        WHEN trade_type_id IN (1, 9) THEN total_amount_sek
        WHEN trade_type_id = 2 THEN -total_amount_sek
        ELSE 0
    END) as calculated_cost
FROM log_trades 
WHERE ticker = 'TEL2 B'
GROUP BY ticker;

-- 9. Check MySQL version and isolation level
SELECT 'MySQL Settings:' as info;
SELECT @@version as mysql_version, @@transaction_isolation as isolation_level;

-- 10. Try a simple test update on a different record
SELECT 'Testing update capability:' as test;
UPDATE portfolio 
SET portfolio_weight_percent = NULL 
WHERE ticker != 'TEL2 B' 
LIMIT 1;

SELECT ROW_COUNT() as rows_affected_by_test_update;