-- Fix collation issues in marketdata views
-- Standardize all string columns to utf8mb4_unicode_ci for UNION compatibility

-- ============================================================================
-- 1. DROP AND RECREATE vw_all_instruments
-- ============================================================================
DROP VIEW IF EXISTS `psw_marketdata`.`vw_all_instruments`;

CREATE VIEW `psw_marketdata`.`vw_all_instruments` AS
SELECT 
    'nordic' as source,
    insId,
    ref_instrument_id,
    name COLLATE utf8mb4_unicode_ci as name,
    urlName COLLATE utf8mb4_unicode_ci as urlName,
    ticker COLLATE utf8mb4_unicode_ci as ticker,
    isin COLLATE utf8mb4_unicode_ci as isin,
    yahoo COLLATE utf8mb4_unicode_ci as yahoo,
    marketId,
    sectorID as sectorId,  -- Standardize column name
    industryID as branchId, -- Map industryID to branchId
    countryID as countryId, -- Standardize column name
    listingDate,
    stockPriceCurrency COLLATE utf8mb4_unicode_ci as stockPriceCurrency,
    reportCurrency COLLATE utf8mb4_unicode_ci as reportCurrency,
    updated
FROM `psw_marketdata`.`nordic_instruments`

UNION ALL

SELECT 
    'global' as source,
    insId,
    ref_instrument_id,
    name COLLATE utf8mb4_unicode_ci as name,
    urlName COLLATE utf8mb4_unicode_ci as urlName,
    ticker COLLATE utf8mb4_unicode_ci as ticker,
    isin COLLATE utf8mb4_unicode_ci as isin,
    yahoo COLLATE utf8mb4_unicode_ci as yahoo,
    marketId,
    sectorId,
    branchId,
    countryId,
    listingDate,
    stockPriceCurrency COLLATE utf8mb4_unicode_ci as stockPriceCurrency,
    reportCurrency COLLATE utf8mb4_unicode_ci as reportCurrency,
    NULL as updated  -- Global instruments may not have updated field
FROM `psw_marketdata`.`global_instruments`;

-- ============================================================================
-- 2. DROP AND RECREATE combined_instruments  
-- ============================================================================
DROP VIEW IF EXISTS `psw_marketdata`.`combined_instruments`;

CREATE VIEW `psw_marketdata`.`combined_instruments` AS
SELECT 
    -- Source identification
    'nordic' as data_source,
    ni.insId as source_id,
    ni.isin COLLATE utf8mb4_unicode_ci as isin,
    ni.ticker COLLATE utf8mb4_unicode_ci as ticker,
    ni.name COLLATE utf8mb4_unicode_ci as company_name,
    
    -- Company details with proper collation
    COALESCE(c.name COLLATE utf8mb4_unicode_ci, 'Unknown') as country,
    COALESCE(s.nameEn COLLATE utf8mb4_unicode_ci, s.nameSv COLLATE utf8mb4_unicode_ci, 'Unknown') as sector_name,
    COALESCE(b.nameEn COLLATE utf8mb4_unicode_ci, b.nameSv COLLATE utf8mb4_unicode_ci, 'Unknown') as branch_name,
    COALESCE(m.name COLLATE utf8mb4_unicode_ci, 'Unknown') as market_name,
    ni.stockPriceCurrency COLLATE utf8mb4_unicode_ci as currency,
    
    -- IDs for joining
    ni.sectorID as sector_id,
    ni.industryID as branch_id,
    ni.countryID as country_id,
    ni.marketId as market_id,
    
    -- Additional fields
    ni.listingDate,
    ni.reportCurrency COLLATE utf8mb4_unicode_ci as report_currency,
    ni.yahoo COLLATE utf8mb4_unicode_ci as yahoo_symbol,
    ni.updated as last_updated

FROM `psw_marketdata`.`nordic_instruments` ni
LEFT JOIN `psw_marketdata`.`countries` c ON ni.countryID = c.id
LEFT JOIN `psw_marketdata`.`sectors` s ON ni.sectorID = s.id
LEFT JOIN `psw_marketdata`.`branches` b ON ni.industryID = b.id
LEFT JOIN `psw_marketdata`.`markets` m ON ni.marketId = m.id

UNION ALL

SELECT 
    -- Source identification
    'global' as data_source,
    gi.insId as source_id,
    gi.isin COLLATE utf8mb4_unicode_ci as isin,
    gi.ticker COLLATE utf8mb4_unicode_ci as ticker,
    gi.name COLLATE utf8mb4_unicode_ci as company_name,
    
    -- Company details with proper collation
    COALESCE(c.name COLLATE utf8mb4_unicode_ci, 'Unknown') as country,
    COALESCE(s.nameEn COLLATE utf8mb4_unicode_ci, s.nameSv COLLATE utf8mb4_unicode_ci, 'Unknown') as sector_name,
    COALESCE(b.nameEn COLLATE utf8mb4_unicode_ci, b.nameSv COLLATE utf8mb4_unicode_ci, 'Unknown') as branch_name,
    COALESCE(m.name COLLATE utf8mb4_unicode_ci, 'Unknown') as market_name,
    gi.stockPriceCurrency COLLATE utf8mb4_unicode_ci as currency,
    
    -- IDs for joining
    gi.sectorId as sector_id,
    gi.branchId as branch_id,
    gi.countryId as country_id,
    gi.marketId as market_id,
    
    -- Additional fields
    gi.listingDate,
    gi.reportCurrency COLLATE utf8mb4_unicode_ci as report_currency,
    gi.yahoo COLLATE utf8mb4_unicode_ci as yahoo_symbol,
    NULL as last_updated  -- Global may not have updated field

FROM `psw_marketdata`.`global_instruments` gi
LEFT JOIN `psw_marketdata`.`countries` c ON gi.countryId = c.id
LEFT JOIN `psw_marketdata`.`sectors` s ON gi.sectorId = s.id
LEFT JOIN `psw_marketdata`.`branches` b ON gi.branchId = b.id
LEFT JOIN `psw_marketdata`.`markets` m ON gi.marketId = m.id;

-- ============================================================================
-- 3. TEST THE FIXED VIEWS
-- ============================================================================

-- Test vw_all_instruments
SELECT 'Testing vw_all_instruments...' as test;
SELECT source, COUNT(*) as count 
FROM psw_marketdata.vw_all_instruments 
GROUP BY source;

-- Test combined_instruments  
SELECT 'Testing combined_instruments...' as test;
SELECT data_source, COUNT(*) as count 
FROM psw_marketdata.combined_instruments 
GROUP BY data_source;

-- Test specific search for your Canadian/UK companies
SELECT 'Sample Canadian/UK companies:' as test;
SELECT data_source, isin, ticker, company_name, country, sector_name
FROM psw_marketdata.combined_instruments 
WHERE country IN ('Canada', 'United Kingdom', 'UK', 'Great Britain') 
LIMIT 10;

-- Show view creation success
SELECT 'Views recreated successfully!' as result;