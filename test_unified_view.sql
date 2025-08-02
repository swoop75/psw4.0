-- Test unified view integration
-- This script verifies that vw_unified_companies works correctly

USE psw_foundation;

-- Test 1: Check if unified view exists
SELECT 'Test 1: Checking if unified view exists' as test;
SHOW TABLES LIKE 'vw_unified_companies';

-- If the above shows no results, the view doesn't exist - run this to check views
SELECT 'Checking views instead:' as info;
SELECT TABLE_NAME, TABLE_TYPE 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'psw_foundation' 
AND TABLE_NAME LIKE '%unified%';

-- Test 2: Describe the view structure
SELECT 'Test 2: View structure' as test;
DESCRIBE vw_unified_companies;

-- Test 3: Count companies by data source
SELECT 'Test 3: Companies by data source' as test;
SELECT 
    data_source, 
    COUNT(*) as company_count,
    COUNT(CASE WHEN isin IS NOT NULL AND isin != '' THEN 1 END) as with_isin_count
FROM vw_unified_companies 
GROUP BY data_source 
ORDER BY company_count DESC;

-- Test 4: Sample data from each source
SELECT 'Test 4: Sample from each data source' as test;

-- Börsdata Nordic sample
SELECT 'Börsdata Nordic sample (first 3):' as info;
SELECT isin, company_name, ticker, country, currency
FROM vw_unified_companies 
WHERE data_source = 'borsdata_nordic'
LIMIT 3;

-- Börsdata Global sample  
SELECT 'Börsdata Global sample (first 3):' as info;
SELECT isin, company_name, ticker, country, currency
FROM vw_unified_companies 
WHERE data_source = 'borsdata_global'
LIMIT 3;

-- Manual data sample
SELECT 'Manual data sample (all):' as info;
SELECT isin, company_name, ticker, country, currency, company_type, dividend_frequency
FROM vw_unified_companies 
WHERE data_source = 'manual'
LIMIT 10;

-- Test 5: ISIN lookup test
SELECT 'Test 5: ISIN lookup functionality' as test;

-- Test with a known Swedish company (should be in Börsdata Nordic)
SELECT 'Looking for Swedish companies:' as info;
SELECT isin, company_name, ticker, data_source, country
FROM vw_unified_companies 
WHERE country = 'Sweden'
LIMIT 5;

-- Test search functionality
SELECT 'Test 6: Search functionality' as test;
SELECT 'Searching for "Tele":' as info;
SELECT isin, company_name, ticker, data_source, country
FROM vw_unified_companies 
WHERE company_name LIKE '%Tele%' 
   OR ticker LIKE '%TEL%'
LIMIT 5;

-- Test 7: Integration verification - check for our test companies
SELECT 'Test 7: Test companies verification' as test;
SELECT 'Looking for test companies:' as info;
SELECT isin, company_name, ticker, data_source, country, currency
FROM vw_unified_companies 
WHERE isin IN ('CZ0008019106', 'IE0003290289', 'GB0001990497', 'CA33843T1084');

-- Test 8: Performance and totals
SELECT 'Test 8: Performance metrics' as test;
SELECT 
    COUNT(*) as total_companies,
    COUNT(CASE WHEN data_source = 'borsdata_nordic' THEN 1 END) as nordic_count,
    COUNT(CASE WHEN data_source = 'borsdata_global' THEN 1 END) as global_count,
    COUNT(CASE WHEN data_source = 'manual' THEN 1 END) as manual_count,
    COUNT(DISTINCT country) as countries_count,
    COUNT(DISTINCT currency) as currencies_count
FROM vw_unified_companies;

-- Test 9: Data quality checks
SELECT 'Test 9: Data quality checks' as test;

-- Check for missing ISINs
SELECT 'Companies without ISIN:' as check;
SELECT data_source, COUNT(*) as missing_isin_count
FROM vw_unified_companies 
WHERE isin IS NULL OR isin = ''
GROUP BY data_source;

-- Check for duplicate ISINs (should not happen)
SELECT 'Duplicate ISIN check:' as check;
SELECT isin, COUNT(*) as duplicate_count
FROM vw_unified_companies 
WHERE isin IS NOT NULL AND isin != ''
GROUP BY isin
HAVING COUNT(*) > 1;

-- Test 10: Sample complex query
SELECT 'Test 10: Complex query test' as test;
SELECT 'Companies by country with counts:' as info;
SELECT 
    country,
    COUNT(*) as company_count,
    GROUP_CONCAT(DISTINCT data_source) as data_sources,
    COUNT(DISTINCT currency) as currencies_in_country
FROM vw_unified_companies 
WHERE country IS NOT NULL
GROUP BY country
ORDER BY company_count DESC
LIMIT 10;

SELECT 'Unified View Integration Test Complete!' as result;
SELECT 'If all queries above returned data, the unified view is working correctly.' as conclusion;