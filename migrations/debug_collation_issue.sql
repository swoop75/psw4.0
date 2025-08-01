-- Debug the exact collation issue

-- 1. Check if the view was actually created
SHOW CREATE VIEW psw_marketdata.combined_instruments;

-- 2. Try the most basic UNION possible
SELECT 
    'nordic' as data_source,
    insId,
    isin COLLATE utf8mb4_unicode_ci as isin
FROM `psw_marketdata`.`nordic_instruments`
LIMIT 1
UNION ALL
SELECT 
    'global' as data_source,
    insId,
    isin COLLATE utf8mb4_unicode_ci as isin
FROM `psw_marketdata`.`global_instruments`
LIMIT 1;

-- 3. Check collations of lookup tables
SELECT 
    'countries' as table_name,
    COLUMN_NAME,
    COLLATION_NAME
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'psw_marketdata' 
    AND TABLE_NAME = 'countries'
    AND DATA_TYPE IN ('varchar', 'char', 'text');

-- 4. Force drop and try the absolute simplest version
DROP VIEW IF EXISTS `psw_marketdata`.`combined_instruments`;

CREATE VIEW `psw_marketdata`.`combined_instruments` AS
SELECT 
    insId,
    isin COLLATE utf8mb4_unicode_ci as isin,
    ticker COLLATE utf8mb4_unicode_ci as ticker,
    name COLLATE utf8mb4_unicode_ci as company_name
FROM `psw_marketdata`.`nordic_instruments`
UNION ALL
SELECT 
    insId,
    isin COLLATE utf8mb4_unicode_ci as isin,
    ticker COLLATE utf8mb4_unicode_ci as ticker,
    name COLLATE utf8mb4_unicode_ci as company_name
FROM `psw_marketdata`.`global_instruments`;

-- Test this minimal version
SELECT COUNT(*) FROM psw_marketdata.combined_instruments;