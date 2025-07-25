-- Direct fix for ISIN US40434L1052 entry
-- This script will manually fix the entry and test the Börsdata integration

USE psw_portfolio;

-- Show current entry
SELECT 'Current entry for US40434L1052:' as step;
SELECT * FROM new_companies WHERE isin = 'US40434L1052' ORDER BY new_company_id DESC LIMIT 1;

-- Get the entry ID
SET @entry_id = (SELECT new_company_id FROM new_companies WHERE isin = 'US40434L1052' ORDER BY new_company_id DESC LIMIT 1);

-- Step 1: Check if ISIN exists in Börsdata
SELECT 'Checking ISIN in global_instruments:' as step;
SELECT gi.isin, gi.name, gi.yahoo, gi.insId, c.nameEN as country 
FROM psw_marketdata.global_instruments gi 
LEFT JOIN psw_marketdata.countries c ON gi.countryId = c.id 
WHERE gi.isin = 'US40434L1052' 
LIMIT 1;

SELECT 'Checking ISIN in nordic_instruments:' as step;
SELECT ni.isin, ni.name, ni.yahoo, ni.insId, c.nameEN as country 
FROM psw_marketdata.nordic_instruments ni 
LEFT JOIN psw_marketdata.countries c ON ni.countryId = c.id 
WHERE ni.isin = 'US40434L1052' 
LIMIT 1;

-- Step 2: If ISIN is found in Börsdata, use stored procedure
-- First, check if the stored procedure exists
SELECT 'Checking if stored procedure exists:' as step;
SELECT ROUTINE_NAME, ROUTINE_TYPE 
FROM INFORMATION_SCHEMA.ROUTINES 
WHERE ROUTINE_SCHEMA = 'psw_portfolio' 
AND ROUTINE_NAME = 'PopulateBorsdataCompanyData';

-- Step 3: Enable Börsdata mode (this should trigger the auto-population if triggers are installed)
SELECT 'Enabling Börsdata mode:' as step;
UPDATE new_companies 
SET borsdata_available = TRUE 
WHERE new_company_id = @entry_id;

-- Step 4: Check if triggers exist
SELECT 'Checking triggers:' as step;
SHOW TRIGGERS LIKE 'tr_new_companies_borsdata%';

-- Step 5: If auto-population didn't work due to missing triggers, call procedure manually
-- Check if procedure exists and call it
SET @proc_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = 'psw_portfolio' AND ROUTINE_NAME = 'PopulateBorsdataCompanyData');

-- Call procedure if it exists
SELECT CASE 
    WHEN @proc_exists > 0 THEN 'Calling PopulateBorsdataCompanyData procedure manually:'
    ELSE 'Procedure does not exist - need to install it first'
END as step;

-- Only call if procedure exists (this is a conditional call)
SET @sql = CASE 
    WHEN @proc_exists > 0 THEN CONCAT('CALL PopulateBorsdataCompanyData(', @entry_id, ')')
    ELSE 'SELECT "Procedure not available" as result'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 6: Show final result
SELECT 'Final entry after processing:' as step;
SELECT * FROM new_companies WHERE new_company_id = @entry_id;

-- Step 7: If Börsdata data is not available, provide manual update option
SELECT 'Manual update option (run if Börsdata lookup failed):' as step;
SELECT CONCAT(
    'UPDATE new_companies SET ',
    'company = ''Hologic Inc'', ',
    'ticker = ''HOLX'', ',
    'country_name = ''United States'', ',
    'country_id = (SELECT id FROM psw_marketdata.countries WHERE nameEN = ''United States'' LIMIT 1), ',
    'borsdata_available = FALSE ',
    'WHERE new_company_id = ', @entry_id, ';'
) as manual_update_sql;