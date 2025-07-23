-- Trigger to automatically populate Börsdata data when borsdata_available is set to TRUE
-- Database: psw_portfolio

USE psw_portfolio;

-- Drop trigger if exists
DROP TRIGGER IF EXISTS tr_new_companies_borsdata_update;

DELIMITER //

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