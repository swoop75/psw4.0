-- EMERGENCY FIX - Just fix the entry, no diagnostics
-- Run this if you just want to fix the problem immediately

USE psw_portfolio;

-- Disable any triggers that might interfere
DROP TRIGGER IF EXISTS tr_new_companies_borsdata_update;
DROP TRIGGER IF EXISTS tr_new_companies_borsdata_insert;

-- Direct fix - no stored procedures, no fancy logic
UPDATE new_companies 
SET 
    company = 'Hologic Inc',
    ticker = 'HOLX',
    country_name = 'United States',
    borsdata_available = 0
WHERE isin = 'US40434L1052';

-- Show the result
SELECT 'Fixed entry:' as result;
SELECT new_company_id, company, ticker, isin, country_name, borsdata_available 
FROM new_companies 
WHERE isin = 'US40434L1052';