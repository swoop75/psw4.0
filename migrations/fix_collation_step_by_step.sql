-- Step-by-step collation fix
-- First let's test which exact columns are causing issues

-- 1. Test basic UNION without any JOINs
SELECT 'Testing basic UNION...' as test;

DROP VIEW IF EXISTS `psw_marketdata`.`test_basic_union`;

CREATE VIEW `psw_marketdata`.`test_basic_union` AS
SELECT 
    'nordic' as source,
    insId,
    isin COLLATE utf8mb4_unicode_ci as isin,
    ticker COLLATE utf8mb4_unicode_ci as ticker,
    name COLLATE utf8mb4_unicode_ci as name
FROM `psw_marketdata`.`nordic_instruments`
UNION ALL
SELECT 
    'global' as source,
    insId,
    isin COLLATE utf8mb4_unicode_ci as isin,
    ticker COLLATE utf8mb4_unicode_ci as ticker,
    name COLLATE utf8mb4_unicode_ci as name
FROM `psw_marketdata`.`global_instruments`;

-- Test this basic view
SELECT * FROM psw_marketdata.test_basic_union LIMIT 5;

-- 2. Check what's wrong with the existing combined_instruments view
SHOW CREATE VIEW psw_marketdata.combined_instruments;

-- 3. Force drop and recreate combined_instruments with explicit collations
DROP VIEW IF EXISTS `psw_marketdata`.`combined_instruments`;

CREATE VIEW `psw_marketdata`.`combined_instruments` AS
SELECT 
    'nordic' as data_source,
    ni.insId as source_id,
    ni.isin COLLATE utf8mb4_unicode_ci as isin,
    ni.ticker COLLATE utf8mb4_unicode_ci as ticker,
    ni.name COLLATE utf8mb4_unicode_ci as company_name,
    'Unknown' as country,  -- Simplified for now
    'Unknown' as sector_name,
    'Unknown' as branch_name,
    'Unknown' as market_name,
    ni.stockPriceCurrency COLLATE utf8mb4_unicode_ci as currency,
    ni.sectorID as sector_id,
    ni.industryID as branch_id,
    ni.countryID as country_id,
    ni.marketId as market_id,
    ni.listingDate,
    ni.reportCurrency COLLATE utf8mb4_unicode_ci as report_currency,
    ni.yahoo COLLATE utf8mb4_unicode_ci as yahoo_symbol,
    ni.updated as last_updated
FROM `psw_marketdata`.`nordic_instruments` ni

UNION ALL

SELECT 
    'global' as data_source,
    gi.insId as source_id,
    gi.isin COLLATE utf8mb4_unicode_ci as isin,
    gi.ticker COLLATE utf8mb4_unicode_ci as ticker,
    gi.name COLLATE utf8mb4_unicode_ci as company_name,
    'Unknown' as country,  -- Simplified for now
    'Unknown' as sector_name,
    'Unknown' as branch_name,
    'Unknown' as market_name,
    gi.stockPriceCurrency COLLATE utf8mb4_unicode_ci as currency,
    gi.sectorId as sector_id,
    gi.branchId as branch_id,
    gi.countryId as country_id,
    gi.marketId as market_id,
    gi.listingDate,
    gi.reportCurrency COLLATE utf8mb4_unicode_ci as report_currency,
    gi.yahoo COLLATE utf8mb4_unicode_ci as yahoo_symbol,
    NULL as last_updated
FROM `psw_marketdata`.`global_instruments` gi;

-- Test the recreated view
SELECT 'Testing recreated combined_instruments...' as test;
SELECT data_source, COUNT(*) as count FROM psw_marketdata.combined_instruments GROUP BY data_source;

-- 4. Show Canadian/UK companies for your testing
SELECT 'Companies from Canada/UK for testing:' as test;
SELECT data_source, isin, ticker, company_name, currency 
FROM psw_marketdata.combined_instruments 
WHERE isin REGEXP '^(CA|GB|US)' 
LIMIT 20;