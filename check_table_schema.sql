-- Check the new_companies table schema to understand constraints
USE psw_portfolio;

-- Show table structure
DESCRIBE new_companies;

-- Show table creation statement
SHOW CREATE TABLE new_companies;

-- Check if triggers exist
SHOW TRIGGERS LIKE '%new_companies%';

-- Check if stored procedures exist
SELECT ROUTINE_NAME, ROUTINE_TYPE, CREATED, LAST_ALTERED
FROM INFORMATION_SCHEMA.ROUTINES 
WHERE ROUTINE_SCHEMA = 'psw_portfolio' 
AND ROUTINE_NAME LIKE '%borsdata%';

-- Show current problematic entry
SELECT * FROM new_companies WHERE isin = 'US40434L1052' ORDER BY new_company_id DESC LIMIT 1;