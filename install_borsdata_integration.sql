-- Installation script for Börsdata integration
-- Run this script to install triggers and stored procedures

USE psw_portfolio;

-- 1. First, install the stored procedure
DELIMITER //

DROP PROCEDURE IF EXISTS PopulateBorsdataCompanyData//

CREATE PROCEDURE PopulateBorsdataCompanyData(
    IN p_new_company_id INT
)
BEGIN
    DECLARE v_isin VARCHAR(20);
    DECLARE v_company_name VARCHAR(100);
    DECLARE v_ticker VARCHAR(15);
    DECLARE v_country_name VARCHAR(100);
    DECLARE v_country_id INT;
    DECLARE v_yield DECIMAL(4,2);
    DECLARE v_borsdata_id INT;
    DECLARE v_error_msg VARCHAR(500);
    DECLARE v_data_source VARCHAR(20);
    
    -- Error handling
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
            v_error_msg = MESSAGE_TEXT;
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
    END;
    
    START TRANSACTION;
    
    -- Get the ISIN from new_companies table
    SELECT isin INTO v_isin 
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
    
    -- If no data found in either table
    IF v_borsdata_id IS NULL THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = CONCAT('ISIN ', v_isin, ' not found in Börsdata global or nordic instruments tables');
    END IF;
    
    -- Get country_id for the country name
    IF v_country_name IS NOT NULL THEN
        SELECT id INTO v_country_id
        FROM psw_marketdata.countries
        WHERE nameEN = v_country_name
        LIMIT 1;
    END IF;
    
    -- Update the new_companies record with fetched data
    UPDATE new_companies SET
        company = COALESCE(v_company_name, company),
        ticker = COALESCE(v_ticker, ticker),
        country_name = COALESCE(v_country_name, country_name),
        country_id = COALESCE(v_country_id, country_id),
        yield = COALESCE(v_yield, yield)
    WHERE new_company_id = p_new_company_id;
    
    COMMIT;
    
    -- Return success message with data source
    SELECT CONCAT('Success: Data populated from Börsdata ', v_data_source, ' instruments') as result;
    
END//

-- 2. Install the triggers
DROP TRIGGER IF EXISTS tr_new_companies_borsdata_update//

CREATE TRIGGER tr_new_companies_borsdata_update
    AFTER UPDATE ON new_companies
    FOR EACH ROW
BEGIN
    -- Only trigger when borsdata_available changes from FALSE to TRUE
    IF OLD.borsdata_available = FALSE AND NEW.borsdata_available = TRUE THEN
        -- Call the stored procedure to populate data
        CALL PopulateBorsdataCompanyData(NEW.new_company_id);
    END IF;
END//

-- Also create an INSERT trigger for new records with borsdata_available = TRUE
DROP TRIGGER IF EXISTS tr_new_companies_borsdata_insert//

CREATE TRIGGER tr_new_companies_borsdata_insert
    AFTER INSERT ON new_companies
    FOR EACH ROW
BEGIN
    -- If new record has borsdata_available = TRUE, populate data
    IF NEW.borsdata_available = TRUE THEN
        -- Call the stored procedure to populate data
        CALL PopulateBorsdataCompanyData(NEW.new_company_id);
    END IF;
END//

DELIMITER ;

-- 3. Test if ISIN US40434L1052 exists in marketdata
SELECT 'Checking ISIN US40434L1052 in global_instruments:' as test_step;
SELECT gi.isin, gi.name, gi.yahoo, gi.insId, c.nameEN as country 
FROM psw_marketdata.global_instruments gi 
LEFT JOIN psw_marketdata.countries c ON gi.countryId = c.id 
WHERE gi.isin = 'US40434L1052' 
LIMIT 1;

SELECT 'Checking ISIN US40434L1052 in nordic_instruments:' as test_step;
SELECT ni.isin, ni.name, ni.yahoo, ni.insId, c.nameEN as country 
FROM psw_marketdata.nordic_instruments ni 
LEFT JOIN psw_marketdata.countries c ON ni.countryId = c.id 
WHERE ni.isin = 'US40434L1052' 
LIMIT 1;

-- 4. Show current entry for this ISIN
SELECT 'Current new_companies entry for US40434L1052:' as test_step;
SELECT * FROM new_companies WHERE isin = 'US40434L1052' ORDER BY new_company_id DESC LIMIT 1;

-- 5. Test manual procedure call (if entry exists)
-- Get the most recent entry ID
SET @entry_id = (SELECT new_company_id FROM new_companies WHERE isin = 'US40434L1052' ORDER BY new_company_id DESC LIMIT 1);

-- Update to ensure borsdata_available is TRUE
UPDATE new_companies SET borsdata_available = TRUE WHERE new_company_id = @entry_id;

-- Test the procedure manually
SELECT CONCAT('Testing procedure with entry ID: ', @entry_id) as test_step;
CALL PopulateBorsdataCompanyData(@entry_id);

-- Show the result
SELECT 'Updated entry after procedure call:' as test_step;
SELECT * FROM new_companies WHERE new_company_id = @entry_id;