-- Fixed version of the Börsdata stored procedure
-- This version properly handles NOT NULL constraints

USE psw_portfolio;

DELIMITER //

DROP PROCEDURE IF EXISTS PopulateBorsdataCompanyData//

CREATE PROCEDURE PopulateBorsdataCompanyData(
    IN p_new_company_id INT
)
BEGIN
    DECLARE v_isin VARCHAR(20);
    DECLARE v_company_name VARCHAR(100) DEFAULT NULL;
    DECLARE v_ticker VARCHAR(15) DEFAULT NULL;
    DECLARE v_country_name VARCHAR(100) DEFAULT NULL;
    DECLARE v_country_id INT DEFAULT NULL;
    DECLARE v_yield DECIMAL(4,2) DEFAULT NULL;
    DECLARE v_borsdata_id INT DEFAULT NULL;
    DECLARE v_error_msg VARCHAR(500);
    DECLARE v_data_source VARCHAR(20) DEFAULT NULL;
    DECLARE v_current_company VARCHAR(100);
    
    -- Error handling
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
            v_error_msg = MESSAGE_TEXT;
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
    END;
    
    START TRANSACTION;
    
    -- Get the ISIN and current company name from new_companies table
    SELECT isin, company INTO v_isin, v_current_company
    FROM new_companies 
    WHERE new_company_id = p_new_company_id 
    AND borsdata_available = TRUE;
    
    -- Check if ISIN exists
    IF v_isin IS NULL OR v_isin = '' THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ISIN is required when borsdata_available is TRUE';
    END IF;
    
    -- First try to find in global_instruments (priority)
    SELECT 
        gi.name,
        gi.yahoo,
        gi.insId,
        c.nameEN
    INTO 
        v_company_name,
        v_ticker,
        v_borsdata_id,
        v_country_name
    FROM psw_marketdata.global_instruments gi
    LEFT JOIN psw_marketdata.countries c ON gi.countryId = c.id
    WHERE gi.isin = v_isin
    LIMIT 1;
    
    -- If found in global_instruments
    IF v_borsdata_id IS NOT NULL THEN
        SET v_data_source = 'global';
        
        -- Get yield from kpi_global (KPI ID 1 = Dividend Yield, 1year, mean)
        SELECT numeric_value INTO v_yield
        FROM psw_marketdata.kpi_global
        WHERE kpi_id = 1 
        AND group_period = '1year' 
        AND calculation = 'mean'
        AND instrument_id = v_borsdata_id
        ORDER BY updated_at DESC
        LIMIT 1;
        
    ELSE
        -- Try nordic_instruments if not found in global
        SELECT 
            ni.name,
            ni.yahoo,
            ni.insId,
            c.nameEN
        INTO 
            v_company_name,
            v_ticker,
            v_borsdata_id,
            v_country_name
        FROM psw_marketdata.nordic_instruments ni
        LEFT JOIN psw_marketdata.countries c ON ni.countryId = c.id
        WHERE ni.isin = v_isin
        LIMIT 1;
        
        -- If found in nordic_instruments
        IF v_borsdata_id IS NOT NULL THEN
            SET v_data_source = 'nordic';
            
            -- Get yield from kpi_nordic (KPI ID 1 = Dividend Yield, 1year, mean)
            SELECT numeric_value INTO v_yield
            FROM psw_marketdata.kpi_nordic
            WHERE kpi_id = 1 
            AND group_period = '1year' 
            AND calculation = 'mean'
            AND instrument_id = v_borsdata_id
            ORDER BY updated_at DESC
            LIMIT 1;
        END IF;
    END IF;
    
    -- If no data found in either table, don't update but don't fail either
    IF v_borsdata_id IS NULL THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = CONCAT('ISIN ', v_isin, ' not found in Börsdata global or nordic instruments tables. Manual entry required.');
    END IF;
    
    -- Get country_id for the country name
    IF v_country_name IS NOT NULL THEN
        SELECT id INTO v_country_id
        FROM psw_marketdata.countries
        WHERE nameEN = v_country_name
        LIMIT 1;
    END IF;
    
    -- Update the new_companies record with fetched data
    -- Only update fields that have data, preserving existing values for others
    UPDATE new_companies SET
        company = CASE 
            WHEN v_company_name IS NOT NULL AND v_company_name != '' THEN v_company_name 
            ELSE company 
        END,
        ticker = CASE 
            WHEN v_ticker IS NOT NULL AND v_ticker != '' THEN v_ticker 
            ELSE ticker 
        END,
        country_name = CASE 
            WHEN v_country_name IS NOT NULL AND v_country_name != '' THEN v_country_name 
            ELSE country_name 
        END,
        country_id = CASE 
            WHEN v_country_id IS NOT NULL THEN v_country_id 
            ELSE country_id 
        END,
        yield = CASE 
            WHEN v_yield IS NOT NULL THEN v_yield 
            ELSE yield 
        END
    WHERE new_company_id = p_new_company_id;
    
    COMMIT;
    
    -- Return success message with data source
    SELECT CONCAT('Success: Data populated from Börsdata ', COALESCE(v_data_source, 'unknown'), ' instruments') as result;
    
END//

DELIMITER ;

-- Test the procedure with a simple query first
SELECT 'Testing if ISIN US40434L1052 exists in marketdata:' as test_step;

-- Check global_instruments
SELECT 'Checking global_instruments:' as step;
SELECT gi.isin, gi.name, gi.yahoo, gi.insId, c.nameEN as country 
FROM psw_marketdata.global_instruments gi 
LEFT JOIN psw_marketdata.countries c ON gi.countryId = c.id 
WHERE gi.isin = 'US40434L1052' 
LIMIT 1;

-- Check nordic_instruments  
SELECT 'Checking nordic_instruments:' as step;
SELECT ni.isin, ni.name, ni.yahoo, ni.insId, c.nameEN as country 
FROM psw_marketdata.nordic_instruments ni 
LEFT JOIN psw_marketdata.countries c ON ni.countryId = c.id 
WHERE ni.isin = 'US40434L1052' 
LIMIT 1;