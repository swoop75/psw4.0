-- Stored Procedure to Auto-populate Company Data from Börsdata
-- This procedure fetches company data from Börsdata tables when borsdata_available is set to TRUE
-- Database: psw_portfolio, psw_marketdata

USE psw_portfolio;

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
    
    -- Log success (you can remove this if not needed)
    -- INSERT INTO system_log (message, created_at) VALUES 
    -- (CONCAT('Populated Börsdata data for company ID ', p_new_company_id, ' from ', v_data_source, ' source'), NOW());
    
    COMMIT;
    
    -- Return success message with data source
    SELECT CONCAT('Success: Data populated from Börsdata ', v_data_source, ' instruments') as result;
    
END//

DELIMITER ;

-- Grant execute permission (adjust username as needed)
-- GRANT EXECUTE ON PROCEDURE PopulateBorsdataCompanyData TO 'your_app_user'@'%';