-- Test and Manual Functions for Börsdata Integration
-- Database: psw_portfolio

USE psw_portfolio;

DELIMITER //

-- Function to manually populate a specific company by ID
DROP PROCEDURE IF EXISTS ManualPopulateBorsdataData//

CREATE PROCEDURE ManualPopulateBorsdataData(
    IN p_new_company_id INT
)
BEGIN
    DECLARE v_current_status BOOLEAN DEFAULT FALSE;
    
    -- Check if company exists
    SELECT borsdata_available INTO v_current_status
    FROM new_companies 
    WHERE new_company_id = p_new_company_id;
    
    IF v_current_status IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company ID not found';
    END IF;
    
    -- Set borsdata_available to TRUE to trigger the population
    UPDATE new_companies 
    SET borsdata_available = TRUE 
    WHERE new_company_id = p_new_company_id;
    
    SELECT 'Manual population triggered successfully' as result;
END//

-- Function to test ISIN lookup without updating the record
DROP PROCEDURE IF EXISTS TestISINLookup//

CREATE PROCEDURE TestISINLookup(
    IN p_isin VARCHAR(20)
)
BEGIN
    DECLARE v_global_found BOOLEAN DEFAULT FALSE;
    DECLARE v_nordic_found BOOLEAN DEFAULT FALSE;
    DECLARE v_global_name VARCHAR(255);
    DECLARE v_nordic_name VARCHAR(255);
    DECLARE v_global_id INT;
    DECLARE v_nordic_id INT;
    
    -- Check global_instruments
    SELECT name, insId INTO v_global_name, v_global_id
    FROM psw_marketdata.global_instruments
    WHERE isin = p_isin
    LIMIT 1;
    
    IF v_global_id IS NOT NULL THEN
        SET v_global_found = TRUE;
    END IF;
    
    -- Check nordic_instruments
    SELECT name, insId INTO v_nordic_name, v_nordic_id
    FROM psw_marketdata.nordic_instruments
    WHERE isin = p_isin
    LIMIT 1;
    
    IF v_nordic_id IS NOT NULL THEN
        SET v_nordic_found = TRUE;
    END IF;
    
    -- Return results
    SELECT 
        p_isin as isin_searched,
        v_global_found as found_in_global,
        v_global_name as global_company_name,
        v_global_id as global_borsdata_id,
        v_nordic_found as found_in_nordic,
        v_nordic_name as nordic_company_name,
        v_nordic_id as nordic_borsdata_id,
        CASE 
            WHEN v_global_found THEN 'global'
            WHEN v_nordic_found THEN 'nordic'
            ELSE 'not_found'
        END as recommended_source;
END//

-- Function to get available yield data for a Börsdata ID
DROP PROCEDURE IF EXISTS GetAvailableYieldData//

CREATE PROCEDURE GetAvailableYieldData(
    IN p_borsdata_id INT,
    IN p_source VARCHAR(10) -- 'global' or 'nordic'
)
BEGIN
    IF p_source = 'global' THEN
        SELECT 
            kpi_id,
            group_period,
            calculation,
            numeric_value as yield_value,
            updated_at
        FROM psw_marketdata.kpi_global
        WHERE instrument_id = p_borsdata_id
        AND kpi_id = 1  -- Dividend Yield
        ORDER BY updated_at DESC;
    ELSEIF p_source = 'nordic' THEN
        SELECT 
            kpi_id,
            group_period,
            calculation,
            numeric_value as yield_value,
            updated_at
        FROM psw_marketdata.kpi_nordic
        WHERE instrument_id = p_borsdata_id
        AND kpi_id = 1  -- Dividend Yield
        ORDER BY updated_at DESC;
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Source must be either global or nordic';
    END IF;
END//

DELIMITER ;

-- Example usage queries (commented out):
/*
-- Test ISIN lookup
CALL TestISINLookup('US0378331005');  -- Apple Inc.

-- Manually populate company ID 1
CALL ManualPopulateBorsdataData(1);

-- Check yield data for a specific Börsdata ID
CALL GetAvailableYieldData(123, 'global');
*/