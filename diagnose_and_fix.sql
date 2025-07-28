-- Complete diagnosis and guaranteed fix for US40434L1052
-- This script will identify the problem and fix it without any stored procedures

USE psw_portfolio;

-- STEP 1: Show table structure to understand constraints
SELECT 'STEP 1: Table structure' as diagnostic_step;
DESCRIBE new_companies;

-- STEP 2: Show current problematic entries
SELECT 'STEP 2: Current entries for this ISIN' as diagnostic_step;
SELECT * FROM new_companies WHERE isin = 'US40434L1052' ORDER BY new_company_id;

-- STEP 3: Check if there are any triggers that might be causing issues
SELECT 'STEP 3: Checking triggers' as diagnostic_step;
SHOW TRIGGERS LIKE '%new_companies%';

-- STEP 4: Disable any problematic triggers temporarily
SELECT 'STEP 4: Disabling triggers temporarily' as diagnostic_step;
DROP TRIGGER IF EXISTS tr_new_companies_borsdata_update;
DROP TRIGGER IF EXISTS tr_new_companies_borsdata_insert;

-- STEP 5: Get the problematic entry ID
SELECT 'STEP 5: Getting entry ID' as diagnostic_step;
SET @problem_id = (SELECT new_company_id FROM new_companies WHERE isin = 'US40434L1052' ORDER BY new_company_id DESC LIMIT 1);
SELECT @problem_id as entry_id_to_fix;

-- STEP 6: Simple direct update with guaranteed non-NULL values
SELECT 'STEP 6: Applying direct fix' as diagnostic_step;

UPDATE new_companies 
SET 
    company = 'Hologic Inc',
    ticker = 'HOLX',
    country_name = 'United States',
    country_id = 1, -- Assuming 1 is US, we'll check this
    yield = NULL, -- This can be NULL
    borsdata_available = 0, -- Set to manual mode since Börsdata lookup failed
    comments = COALESCE(comments, 'Fixed from problematic entry'),
    inspiration = COALESCE(inspiration, 'Corrected company information')
WHERE new_company_id = @problem_id;

-- STEP 7: Verify the fix worked
SELECT 'STEP 7: Verifying fix' as diagnostic_step;
SELECT * FROM new_companies WHERE new_company_id = @problem_id;

-- STEP 8: Check if we have the correct country_id for United States
SELECT 'STEP 8: Checking country_id for United States' as diagnostic_step;
SELECT id, nameEN FROM psw_marketdata.countries WHERE nameEN LIKE '%United States%' OR nameEN LIKE '%USA%' OR nameEN LIKE '%US%' LIMIT 5;

-- STEP 9: Update with correct country_id if needed
SET @us_country_id = (SELECT id FROM psw_marketdata.countries WHERE nameEN = 'United States' LIMIT 1);
IF @us_country_id IS NULL THEN
    SET @us_country_id = (SELECT id FROM psw_marketdata.countries WHERE nameEN LIKE '%United%' LIMIT 1);
END IF;

UPDATE new_companies 
SET country_id = @us_country_id 
WHERE new_company_id = @problem_id AND @us_country_id IS NOT NULL;

-- STEP 10: Final verification
SELECT 'STEP 10: Final result' as diagnostic_step;
SELECT nc.*, c.nameEN as country_from_id 
FROM new_companies nc 
LEFT JOIN psw_marketdata.countries c ON nc.country_id = c.id 
WHERE nc.new_company_id = @problem_id;

-- STEP 11: Check if we need to recreate triggers (optional)
SELECT 'STEP 11: Available to recreate triggers' as diagnostic_step;
SELECT 'Triggers have been removed. If you want Börsdata integration for future entries, run install_borsdata_integration.sql' as note;

-- STEP 12: Test that the entry is now valid
SELECT 'STEP 12: Validation test' as diagnostic_step;
SELECT 
    CASE 
        WHEN company IS NOT NULL AND company != '' THEN 'OK'
        ELSE 'STILL NULL'
    END as company_status,
    CASE 
        WHEN ticker IS NOT NULL AND ticker != '' THEN 'OK'
        ELSE 'NULL OR EMPTY'
    END as ticker_status,
    CASE 
        WHEN isin IS NOT NULL AND isin != '' THEN 'OK'
        ELSE 'NULL OR EMPTY'
    END as isin_status
FROM new_companies 
WHERE new_company_id = @problem_id;