-- Ultimate Diagnostic: Figure out why portfolio can't be changed
USE psw_portfolio;

-- 1. Show the actual SQL commands being executed
SET sql_log_bin = 0;  -- Disable binary logging temporarily

-- 2. Check if this is even the right database/table
SELECT 'Database and Table Info:' as info;
SELECT 
    DATABASE() as current_database,
    COUNT(*) as total_portfolio_records,
    SUM(CASE WHEN ticker = 'TEL2 B' THEN 1 ELSE 0 END) as tele2_records
FROM portfolio;

-- 3. Show exact record details with all fields
SELECT 'Complete TEL2 B Record:' as info;
SELECT * FROM portfolio WHERE ticker = 'TEL2 B' LIMIT 1;

-- 4. Check if there are triggers on the portfolio table
SELECT 'Portfolio Table Triggers:' as info;
SHOW TRIGGERS WHERE `Table` = 'portfolio';

-- 5. Check table engine and status
SELECT 'Table Engine and Status:' as info;
SHOW TABLE STATUS LIKE 'portfolio';

-- 6. Test if we can update ANY field at all
SELECT 'Before update test:' as test;
SELECT portfolio_id, ticker, shares_held, updated_at FROM portfolio WHERE ticker = 'TEL2 B';

UPDATE portfolio SET updated_at = '2025-01-01 00:00:00' WHERE ticker = 'TEL2 B';
SELECT CONCAT('Update test affected rows: ', ROW_COUNT()) as update_test;

SELECT 'After update test:' as test;
SELECT portfolio_id, ticker, shares_held, updated_at FROM portfolio WHERE ticker = 'TEL2 B';

-- 7. Check if autocommit is on
SELECT 'Transaction Settings:' as info;
SELECT @@autocommit as autocommit_status, @@transaction_isolation as isolation_level;

-- 8. Try explicit transaction
START TRANSACTION;
UPDATE portfolio SET shares_held = 999 WHERE ticker = 'TEL2 B';
SELECT 'Inside transaction:' as status, shares_held FROM portfolio WHERE ticker = 'TEL2 B';
COMMIT;
SELECT 'After commit:' as status, shares_held FROM portfolio WHERE ticker = 'TEL2 B';

-- 9. Check if there are any views or instead-of triggers
SELECT 'Views that might be interfering:' as info;
SELECT TABLE_NAME, TABLE_TYPE 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'psw_portfolio' 
AND TABLE_NAME LIKE '%portfolio%';

-- 10. Check table permissions
SELECT 'Table Privileges:' as info;
SHOW GRANTS;

-- 11. Try creating a test table to verify write permissions
CREATE TEMPORARY TABLE test_write (id INT, value VARCHAR(50));
INSERT INTO test_write VALUES (1, 'test');
UPDATE test_write SET value = 'updated' WHERE id = 1;
SELECT 'Write test result:' as test, value FROM test_write WHERE id = 1;
DROP TEMPORARY TABLE test_write;

-- 12. Ultimate test: try to delete the record entirely
SELECT 'Before deletion test:' as before_delete;
SELECT COUNT(*) as count FROM portfolio WHERE ticker = 'TEL2 B';

DELETE FROM portfolio WHERE ticker = 'TEL2 B';
SELECT CONCAT('Delete affected rows: ', ROW_COUNT()) as delete_result;

SELECT 'After deletion test:' as after_delete;
SELECT COUNT(*) as count FROM portfolio WHERE ticker = 'TEL2 B';

-- If the record is still there after DELETE, something is very wrong
-- Let's see what the record looks like now
SELECT 'Record after delete attempt:' as final_check;
SELECT portfolio_id, ticker, shares_held, updated_at FROM portfolio WHERE ticker = 'TEL2 B';