-- Simple direct fix for the US40434L1052 entry
-- This bypasses the stored procedure and fixes the entry directly

USE psw_portfolio;

-- Show current problematic entry
SELECT 'Current entry:' as step;
SELECT new_company_id, company, ticker, isin, country_name, yield, borsdata_available 
FROM new_companies 
WHERE isin = 'US40434L1052' 
ORDER BY new_company_id DESC 
LIMIT 1;

-- Get the entry ID
SET @entry_id = (SELECT new_company_id FROM new_companies WHERE isin = 'US40434L1052' ORDER BY new_company_id DESC LIMIT 1);

-- First, let's check if this ISIN actually exists in Börsdata
-- Check global_instruments
SELECT 'Checking if ISIN exists in global_instruments:' as step;
SELECT gi.isin, gi.name, gi.yahoo, gi.insId, c.nameEN as country 
FROM psw_marketdata.global_instruments gi 
LEFT JOIN psw_marketdata.countries c ON gi.countryId = c.id 
WHERE gi.isin = 'US40434L1052' 
LIMIT 1;

-- Check nordic_instruments
SELECT 'Checking if ISIN exists in nordic_instruments:' as step;
SELECT ni.isin, ni.name, ni.yahoo, ni.insId, c.nameEN as country 
FROM psw_marketdata.nordic_instruments ni 
LEFT JOIN psw_marketdata.countries c ON ni.countryId = c.id 
WHERE ni.isin = 'US40434L1052' 
LIMIT 1;

-- If the ISIN is found in Börsdata, we'll update with that data
-- If not found, we'll update with manual data for Hologic Inc

-- Get data from global_instruments if it exists
SET @borsdata_company = (SELECT gi.name FROM psw_marketdata.global_instruments gi WHERE gi.isin = 'US40434L1052' LIMIT 1);
SET @borsdata_ticker = (SELECT gi.yahoo FROM psw_marketdata.global_instruments gi WHERE gi.isin = 'US40434L1052' LIMIT 1);
SET @borsdata_country = (SELECT c.nameEN FROM psw_marketdata.global_instruments gi LEFT JOIN psw_marketdata.countries c ON gi.countryId = c.id WHERE gi.isin = 'US40434L1052' LIMIT 1);

-- If not found in global, try nordic
IF @borsdata_company IS NULL THEN
    SET @borsdata_company = (SELECT ni.name FROM psw_marketdata.nordic_instruments ni WHERE ni.isin = 'US40434L1052' LIMIT 1);
    SET @borsdata_ticker = (SELECT ni.yahoo FROM psw_marketdata.nordic_instruments ni WHERE ni.isin = 'US40434L1052' LIMIT 1);
    SET @borsdata_country = (SELECT c.nameEN FROM psw_marketdata.nordic_instruments ni LEFT JOIN psw_marketdata.countries c ON ni.countryId = c.id WHERE ni.isin = 'US40434L1052' LIMIT 1);
END IF;

-- Use Börsdata data if available, otherwise use manual data for Hologic Inc
SET @final_company = COALESCE(@borsdata_company, 'Hologic Inc');
SET @final_ticker = COALESCE(@borsdata_ticker, 'HOLX'); 
SET @final_country = COALESCE(@borsdata_country, 'United States');

-- Show what we're going to update with
SELECT 'Will update with:' as step;
SELECT @final_company as company, @final_ticker as ticker, @final_country as country;

-- Perform the update
UPDATE new_companies 
SET 
    company = @final_company,
    ticker = @final_ticker,
    country_name = @final_country,
    borsdata_available = CASE WHEN @borsdata_company IS NOT NULL THEN TRUE ELSE FALSE END
WHERE new_company_id = @entry_id;

-- Show the updated entry
SELECT 'Updated entry:' as step;
SELECT new_company_id, company, ticker, isin, country_name, yield, borsdata_available 
FROM new_companies 
WHERE new_company_id = @entry_id;