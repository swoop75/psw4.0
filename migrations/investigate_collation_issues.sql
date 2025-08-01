-- Investigate collation issues in marketdata views
-- Check collations of all relevant tables

-- 1. Check collations of instrument tables
SELECT 
    'nordic_instruments' as table_name,
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_SET_NAME,
    COLLATION_NAME
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'psw_marketdata' 
    AND TABLE_NAME = 'nordic_instruments'
    AND DATA_TYPE IN ('varchar', 'char', 'text')
ORDER BY ORDINAL_POSITION;

SELECT 
    'global_instruments' as table_name,
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_SET_NAME,
    COLLATION_NAME
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'psw_marketdata' 
    AND TABLE_NAME = 'global_instruments'
    AND DATA_TYPE IN ('varchar', 'char', 'text')
ORDER BY ORDINAL_POSITION;

-- 2. Check the actual view definitions
SHOW CREATE VIEW psw_marketdata.vw_all_instruments;
SHOW CREATE VIEW psw_marketdata.combined_instruments;

-- 3. Check table collations
SELECT 
    TABLE_NAME,
    TABLE_COLLATION
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'psw_marketdata' 
    AND TABLE_NAME IN ('nordic_instruments', 'global_instruments');

-- 4. Quick test to identify problematic columns
-- Try a simple UNION to see which columns cause issues
SELECT isin, name FROM psw_marketdata.nordic_instruments LIMIT 1
UNION
SELECT isin, name FROM psw_marketdata.global_instruments LIMIT 1;