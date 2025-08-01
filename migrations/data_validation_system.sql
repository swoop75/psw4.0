-- PSW 4.0 Data Validation System
-- Comprehensive validation rules and checks for manual company data

-- ============================================================================
-- 1. DATABASE CONSTRAINTS AND TRIGGERS
-- ============================================================================

-- Add validation constraints to manual_company_data table
ALTER TABLE `psw_foundation`.`manual_company_data` 
ADD CONSTRAINT `chk_isin_format` 
CHECK (CHAR_LENGTH(isin) = 12 AND isin REGEXP '^[A-Z]{2}[A-Z0-9]{9}[0-9]$');

ALTER TABLE `psw_foundation`.`manual_company_data` 
ADD CONSTRAINT `chk_currency_format` 
CHECK (currency IS NULL OR currency REGEXP '^[A-Z]{3}$');

ALTER TABLE `psw_foundation`.`manual_company_data` 
ADD CONSTRAINT `chk_company_name_length` 
CHECK (CHAR_LENGTH(TRIM(company_name)) >= 2);

ALTER TABLE `psw_foundation`.`manual_company_data` 
ADD CONSTRAINT `chk_country_length` 
CHECK (CHAR_LENGTH(TRIM(country)) >= 2);

-- ============================================================================
-- 2. VALIDATION FUNCTIONS
-- ============================================================================

DELIMITER //

-- Function to validate ISIN check digit
CREATE FUNCTION IF NOT EXISTS `psw_foundation`.`validate_isin_checksum`(isin_code CHAR(12))
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE char_val CHAR(1);
    DECLARE num_string VARCHAR(24) DEFAULT '';
    DECLARE sum_val INT DEFAULT 0;
    DECLARE pos INT DEFAULT 1;
    DECLARE digit INT;
    DECLARE check_digit INT;
    
    -- Convert letters to numbers and build string
    WHILE i <= 11 DO
        SET char_val = SUBSTRING(isin_code, i, 1);
        IF char_val REGEXP '[0-9]' THEN
            SET num_string = CONCAT(num_string, char_val);
        ELSE
            SET num_string = CONCAT(num_string, ASCII(char_val) - 55);
        END IF;
        SET i = i + 1;
    END WHILE;
    
    -- Calculate checksum using Luhn algorithm
    SET i = CHAR_LENGTH(num_string);
    WHILE i > 0 DO
        SET digit = CAST(SUBSTRING(num_string, i, 1) AS UNSIGNED);
        IF pos % 2 = 0 THEN
            SET digit = digit * 2;
            IF digit > 9 THEN
                SET digit = digit - 9;
            END IF;
        END IF;
        SET sum_val = sum_val + digit;
        SET pos = pos + 1;
        SET i = i - 1;
    END WHILE;
    
    SET check_digit = (10 - (sum_val % 10)) % 10;
    
    RETURN check_digit = CAST(SUBSTRING(isin_code, 12, 1) AS UNSIGNED);
END//

-- Function to check for duplicate company entries across all sources
CREATE FUNCTION IF NOT EXISTS `psw_foundation`.`check_duplicate_company`(isin_code CHAR(12))
RETURNS VARCHAR(100)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE duplicate_source VARCHAR(100) DEFAULT NULL;
    DECLARE nordic_count INT DEFAULT 0;
    DECLARE global_count INT DEFAULT 0;
    DECLARE manual_count INT DEFAULT 0;
    DECLARE masterlist_count INT DEFAULT 0;
    
    -- Check Nordic instruments
    SELECT COUNT(*) INTO nordic_count 
    FROM psw_marketdata.nordic_instruments 
    WHERE isin COLLATE utf8mb4_unicode_ci = isin_code COLLATE utf8mb4_unicode_ci;
    
    -- Check Global instruments  
    SELECT COUNT(*) INTO global_count 
    FROM psw_marketdata.global_instruments 
    WHERE isin COLLATE utf8mb4_unicode_ci = isin_code COLLATE utf8mb4_unicode_ci;
    
    -- Check manual company data
    SELECT COUNT(*) INTO manual_count 
    FROM psw_foundation.manual_company_data 
    WHERE isin = isin_code;
    
    -- Check masterlist
    SELECT COUNT(*) INTO masterlist_count 
    FROM psw_foundation.masterlist 
    WHERE isin = isin_code;
    
    -- Return source of duplication
    IF nordic_count > 0 THEN
        SET duplicate_source = 'Börsdata Nordic';
    ELSEIF global_count > 0 THEN
        SET duplicate_source = 'Börsdata Global';
    ELSEIF masterlist_count > 0 THEN
        SET duplicate_source = 'Masterlist';
    ELSEIF manual_count > 0 THEN
        SET duplicate_source = 'Manual Data';
    END IF;
    
    RETURN duplicate_source;
END//

-- Function to validate country name
CREATE FUNCTION IF NOT EXISTS `psw_foundation`.`validate_country`(country_name VARCHAR(100))
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE valid_country BOOLEAN DEFAULT FALSE;
    DECLARE country_count INT DEFAULT 0;
    
    -- List of valid countries (can be expanded)
    IF country_name IN (
        'Austria', 'Belgium', 'Canada', 'Czech Republic', 'Denmark', 'Finland', 
        'France', 'Germany', 'Ireland', 'Italy', 'Netherlands', 'Norway', 
        'Poland', 'Spain', 'Sweden', 'Switzerland', 'United Kingdom', 'United States'
    ) THEN
        SET valid_country = TRUE;
    END IF;
    
    -- Also check if country exists in marketdata countries table
    SELECT COUNT(*) INTO country_count 
    FROM psw_marketdata.countries 
    WHERE name = country_name;
    
    IF country_count > 0 THEN
        SET valid_country = TRUE;
    END IF;
    
    RETURN valid_country;
END//

-- Validation trigger for INSERT
CREATE TRIGGER IF NOT EXISTS `tr_manual_company_validate_insert`
BEFORE INSERT ON `psw_foundation`.`manual_company_data`
FOR EACH ROW
BEGIN
    DECLARE error_msg VARCHAR(500);
    DECLARE duplicate_source VARCHAR(100);
    
    -- Normalize data
    SET NEW.isin = UPPER(TRIM(NEW.isin));
    SET NEW.ticker = UPPER(TRIM(NEW.ticker));
    SET NEW.company_name = TRIM(NEW.company_name);
    SET NEW.country = TRIM(NEW.country);
    SET NEW.currency = UPPER(TRIM(NEW.currency));
    
    -- Validate ISIN checksum
    IF NOT psw_foundation.validate_isin_checksum(NEW.isin) THEN
        SET error_msg = CONCAT('Invalid ISIN checksum: ', NEW.isin);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Check for duplicates
    SET duplicate_source = psw_foundation.check_duplicate_company(NEW.isin);
    IF duplicate_source IS NOT NULL THEN
        SET error_msg = CONCAT('Company already exists in ', duplicate_source, ': ', NEW.isin);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Validate country
    IF NOT psw_foundation.validate_country(NEW.country) THEN
        SET error_msg = CONCAT('Invalid or unsupported country: ', NEW.country);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Validate currency if provided
    IF NEW.currency IS NOT NULL AND NEW.currency NOT IN (
        'AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'NOK', 'SEK', 'USD'
    ) THEN
        SET error_msg = CONCAT('Unsupported currency: ', NEW.currency);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Auto-set data source and timestamps
    SET NEW.data_source = 'MANUAL';
    SET NEW.created_at = NOW();
    SET NEW.updated_at = NOW();
END//

-- Validation trigger for UPDATE
CREATE TRIGGER IF NOT EXISTS `tr_manual_company_validate_update`
BEFORE UPDATE ON `psw_foundation`.`manual_company_data`
FOR EACH ROW
BEGIN
    DECLARE error_msg VARCHAR(500);
    
    -- Don't allow ISIN changes
    IF OLD.isin != NEW.isin THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ISIN cannot be changed after creation';
    END IF;
    
    -- Normalize data
    SET NEW.ticker = UPPER(TRIM(NEW.ticker));
    SET NEW.company_name = TRIM(NEW.company_name);
    SET NEW.country = TRIM(NEW.country);
    SET NEW.currency = UPPER(TRIM(NEW.currency));
    
    -- Validate country
    IF NOT psw_foundation.validate_country(NEW.country) THEN
        SET error_msg = CONCAT('Invalid or unsupported country: ', NEW.country);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Validate currency if provided
    IF NEW.currency IS NOT NULL AND NEW.currency NOT IN (
        'AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'NOK', 'SEK', 'USD'
    ) THEN
        SET error_msg = CONCAT('Unsupported currency: ', NEW.currency);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    
    -- Update timestamp
    SET NEW.updated_at = NOW();
END//

DELIMITER ;

-- ============================================================================
-- 3. VALIDATION REPORT PROCEDURES
-- ============================================================================

DELIMITER //

-- Procedure to run comprehensive data validation
CREATE PROCEDURE IF NOT EXISTS `psw_foundation`.`sp_validate_all_manual_companies`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_isin CHAR(12);
    DECLARE v_company_name VARCHAR(255);
    DECLARE v_validation_errors INT DEFAULT 0;
    
    -- Cursor for all manual companies
    DECLARE company_cursor CURSOR FOR
        SELECT isin, company_name FROM psw_foundation.manual_company_data;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Create temporary results table
    DROP TEMPORARY TABLE IF EXISTS temp_validation_results;
    CREATE TEMPORARY TABLE temp_validation_results (
        isin CHAR(12),
        company_name VARCHAR(255),
        validation_status VARCHAR(20),
        error_details TEXT
    );
    
    OPEN company_cursor;
    
    validation_loop: LOOP
        FETCH company_cursor INTO v_isin, v_company_name;
        IF done THEN
            LEAVE validation_loop;
        END IF;
        
        -- Validate each company
        BEGIN
            DECLARE validation_error VARCHAR(500) DEFAULT NULL;
            DECLARE duplicate_source VARCHAR(100);
            
            -- Check ISIN
            IF NOT psw_foundation.validate_isin_checksum(v_isin) THEN
                SET validation_error = CONCAT(COALESCE(validation_error, ''), 'Invalid ISIN checksum; ');
            END IF;
            
            -- Check duplicates
            SET duplicate_source = psw_foundation.check_duplicate_company(v_isin);
            IF duplicate_source IS NOT NULL AND duplicate_source != 'Manual Data' THEN
                SET validation_error = CONCAT(COALESCE(validation_error, ''), 'Duplicate in ', duplicate_source, '; ');
            END IF;
            
            -- Insert results
            IF validation_error IS NULL THEN
                INSERT INTO temp_validation_results VALUES (v_isin, v_company_name, 'VALID', NULL);
            ELSE
                INSERT INTO temp_validation_results VALUES (v_isin, v_company_name, 'ERROR', validation_error);
                SET v_validation_errors = v_validation_errors + 1;
            END IF;
        END;
        
    END LOOP;
    
    CLOSE company_cursor;
    
    -- Return results
    SELECT 
        validation_status,
        COUNT(*) as company_count
    FROM temp_validation_results 
    GROUP BY validation_status;
    
    SELECT * FROM temp_validation_results WHERE validation_status = 'ERROR';
    
    SELECT CONCAT('Validation complete. ', v_validation_errors, ' errors found.') as summary;
    
END//

-- Procedure to check system data integrity
CREATE PROCEDURE IF NOT EXISTS `psw_foundation`.`sp_system_data_integrity_check`()
BEGIN
    SELECT 'Data Integrity Check Results' as report_section;
    
    -- Check for orphaned portfolio entries
    SELECT 'Orphaned Portfolio Entries' as check_type;
    SELECT COUNT(*) as count, 'Portfolio entries without company data' as description
    FROM psw_portfolio.portfolio p
    LEFT JOIN psw_foundation.vw_unified_companies uc ON p.isin = uc.isin
    WHERE uc.isin IS NULL;
    
    -- Check for missing price data
    SELECT 'Missing Price Data' as check_type;
    SELECT COUNT(*) as count, 'Manual companies without price data' as description
    FROM psw_foundation.manual_company_data mcd
    LEFT JOIN psw_portfolio.portfolio p ON mcd.isin = p.isin
    WHERE p.latest_price_local IS NULL AND p.is_active = 1;
    
    -- Check for invalid ISINs in portfolio
    SELECT 'Invalid ISINs in Portfolio' as check_type;
    SELECT COUNT(*) as count, 'Portfolio entries with invalid ISIN format' as description
    FROM psw_portfolio.portfolio
    WHERE isin IS NOT NULL 
        AND NOT (CHAR_LENGTH(isin) = 12 AND isin REGEXP '^[A-Z]{2}[A-Z0-9]{9}[0-9]$');
    
    -- Check for currency mismatches
    SELECT 'Currency Mismatches' as check_type;
    SELECT COUNT(*) as count, 'Companies with mismatched currencies' as description
    FROM psw_foundation.manual_company_data mcd
    JOIN psw_portfolio.portfolio p ON mcd.isin = p.isin
    WHERE mcd.currency != p.currency_local;
    
END//

DELIMITER ;

-- ============================================================================
-- 4. TEST VALIDATION SYSTEM
-- ============================================================================

-- Test valid ISIN
SELECT 'Testing ISIN validation...' as test_section;
SELECT 
    'US0378331005' as isin,
    psw_foundation.validate_isin_checksum('US0378331005') as is_valid,
    'Should be TRUE for Apple Inc ISIN' as expected;

-- Test invalid ISIN  
SELECT 
    'US0378331006' as isin,
    psw_foundation.validate_isin_checksum('US0378331006') as is_valid,
    'Should be FALSE (wrong check digit)' as expected;

-- Test country validation
SELECT 'Testing country validation...' as test_section;
SELECT 
    'Canada' as country,
    psw_foundation.validate_country('Canada') as is_valid,
    'Should be TRUE' as expected;

SELECT 
    'Fakeland' as country,
    psw_foundation.validate_country('Fakeland') as is_valid,
    'Should be FALSE' as expected;

-- Test duplicate detection
SELECT 'Testing duplicate detection...' as test_section;
SELECT 
    'SE0022726485' as isin,
    psw_foundation.check_duplicate_company('SE0022726485') as duplicate_source,
    'Should show source if exists' as expected;

SELECT 'Validation system installation complete!' as result;