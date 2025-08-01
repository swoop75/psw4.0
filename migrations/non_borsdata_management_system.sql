-- Non-Börsdata Company Management System
-- Comprehensive solution for managing companies not covered by Börsdata API

-- ============================================================================
-- 1. MANUAL COMPANY DATA TABLE
-- ============================================================================
-- Store manually entered data for non-Börsdata companies
CREATE TABLE IF NOT EXISTS `psw_foundation`.`manual_company_data` (
    `manual_id` INT NOT NULL AUTO_INCREMENT,
    `isin` CHAR(20) NOT NULL COMMENT 'ISIN code from broker',
    `ticker` VARCHAR(50) DEFAULT NULL COMMENT 'Ticker symbol if known',
    `company_name` VARCHAR(255) NOT NULL COMMENT 'Manually entered company name',
    `country` VARCHAR(100) NOT NULL COMMENT 'Company country',
    `sector` VARCHAR(100) DEFAULT NULL COMMENT 'Business sector',
    `branch` VARCHAR(100) DEFAULT NULL COMMENT 'Industry branch/sub-sector',
    `market_exchange` VARCHAR(100) DEFAULT NULL COMMENT 'Primary exchange',
    `currency` VARCHAR(3) DEFAULT NULL COMMENT 'Trading currency',
    `company_type` ENUM('stock', 'etf', 'closed_end_fund', 'reit', 'other') DEFAULT 'stock',
    `dividend_frequency` ENUM('annual', 'semi_annual', 'quarterly', 'monthly', 'irregular', 'none') DEFAULT 'quarterly',
    `notes` TEXT DEFAULT NULL COMMENT 'Additional notes',
    `data_source` VARCHAR(50) DEFAULT 'MANUAL' COMMENT 'Source of this data',
    `created_by` VARCHAR(100) DEFAULT 'admin' COMMENT 'Who entered this data',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`manual_id`),
    UNIQUE KEY `uk_manual_isin` (`isin`),
    KEY `idx_manual_country` (`country`),
    KEY `idx_manual_sector` (`sector`),
    KEY `idx_manual_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Manual company data for non-Börsdata supported companies';

-- ============================================================================
-- 2. COMPANY DATA SOURCE TRACKING
-- ============================================================================
-- Track which companies come from which sources and monitor changes
CREATE TABLE IF NOT EXISTS `psw_foundation`.`company_data_sources` (
    `tracking_id` INT NOT NULL AUTO_INCREMENT,
    `isin` CHAR(20) NOT NULL,
    `data_source` ENUM('borsdata_nordic', 'borsdata_global', 'manual', 'alternative_api') NOT NULL,
    `source_id` VARCHAR(50) DEFAULT NULL COMMENT 'ID in source system (e.g., Börsdata insId)',
    `company_name` VARCHAR(255) DEFAULT NULL,
    `last_seen_date` DATE NOT NULL COMMENT 'Last date this ISIN was found in source',
    `first_seen_date` DATE NOT NULL COMMENT 'First date this ISIN was found in source',
    `status` ENUM('active', 'missing', 'migrated') DEFAULT 'active',
    `missing_since` DATE DEFAULT NULL COMMENT 'Date when company went missing from source',
    `missing_notification_sent` BOOLEAN DEFAULT FALSE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`tracking_id`),
    UNIQUE KEY `uk_source_isin` (`isin`, `data_source`),
    KEY `idx_source_last_seen` (`last_seen_date`),
    KEY `idx_source_status` (`status`),
    KEY `idx_source_missing` (`missing_since`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Track company data sources and monitor availability';

-- ============================================================================
-- 3. DATA SYNC LOG TABLE
-- ============================================================================
-- Log all data sync operations for troubleshooting
CREATE TABLE IF NOT EXISTS `psw_foundation`.`data_sync_log` (
    `log_id` INT NOT NULL AUTO_INCREMENT,
    `sync_type` ENUM('borsdata_nordic', 'borsdata_global', 'price_sync', 'manual_entry') NOT NULL,
    `sync_started` DATETIME NOT NULL,
    `sync_completed` DATETIME DEFAULT NULL,
    `records_processed` INT DEFAULT 0,
    `records_added` INT DEFAULT 0,
    `records_updated` INT DEFAULT 0,
    `records_removed` INT DEFAULT 0,
    `companies_missing` INT DEFAULT 0 COMMENT 'Companies that disappeared from source',
    `new_unsupported_found` INT DEFAULT 0 COMMENT 'New companies needing manual entry',
    `sync_status` ENUM('running', 'completed', 'failed', 'partial') DEFAULT 'running',
    `error_message` TEXT DEFAULT NULL,
    `details` JSON DEFAULT NULL COMMENT 'Additional sync details',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`log_id`),
    KEY `idx_sync_type_date` (`sync_type`, `sync_started`),
    KEY `idx_sync_status` (`sync_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Log of all data synchronization operations';

-- ============================================================================
-- 4. NOTIFICATIONS QUEUE TABLE
-- ============================================================================
-- Queue for email notifications about missing/new companies
CREATE TABLE IF NOT EXISTS `psw_foundation`.`notification_queue` (
    `notification_id` INT NOT NULL AUTO_INCREMENT,
    `notification_type` ENUM('company_missing', 'company_new_unsupported', 'sync_error', 'manual_review_needed') NOT NULL,
    `isin` CHAR(20) DEFAULT NULL,
    `company_name` VARCHAR(255) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    `notification_status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    `email_sent_at` DATETIME DEFAULT NULL,
    `retry_count` INT DEFAULT 0,
    `max_retries` INT DEFAULT 3,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`notification_id`),
    KEY `idx_notification_status` (`notification_status`),
    KEY `idx_notification_created` (`created_at`),
    KEY `idx_notification_type` (`notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Queue for email notifications about data issues';

-- ============================================================================
-- 5. UNIFIED COMPANY VIEW
-- ============================================================================
-- Unified view combining Börsdata and manual data
CREATE OR REPLACE VIEW `psw_foundation`.`vw_unified_companies` AS
SELECT 
    -- Source identification
    'borsdata_nordic' as data_source,
    ni.insId as source_id,
    ni.isin,
    ni.ticker,
    ni.name as company_name,
    
    -- Company details
    COALESCE(c.name, 'Unknown') as country,
    COALESCE(s.nameEn, s.nameSv, 'Unknown') as sector,
    COALESCE(b.nameEn, b.nameSv, 'Unknown') as branch,
    COALESCE(m.name, 'Unknown') as market_exchange,
    ni.stockPriceCurrency as currency,
    'stock' as company_type,
    'quarterly' as dividend_frequency,
    
    -- Metadata
    ni.updated as last_updated,
    'Börsdata Nordic' as source_description,
    FALSE as is_manual,
    NULL as manual_notes

FROM `psw_marketdata`.`nordic_instruments` ni
LEFT JOIN `psw_marketdata`.`countries` c ON ni.countryID = c.id
LEFT JOIN `psw_marketdata`.`sectors` s ON ni.sectorID = s.id
LEFT JOIN `psw_marketdata`.`branches` b ON ni.industryID = b.id
LEFT JOIN `psw_marketdata`.`markets` m ON ni.marketId = m.id

UNION ALL

SELECT 
    -- Source identification
    'borsdata_global' as data_source,
    gi.insId as source_id,
    gi.isin,
    gi.ticker,
    gi.name as company_name,
    
    -- Company details
    COALESCE(c.name, 'Unknown') as country,
    COALESCE(s.nameEn, s.nameSv, 'Unknown') as sector,
    COALESCE(b.nameEn, b.nameSv, 'Unknown') as branch,
    COALESCE(m.name, 'Unknown') as market_exchange,
    gi.stockPriceCurrency as currency,
    'stock' as company_type,
    'quarterly' as dividend_frequency,
    
    -- Metadata
    NOW() as last_updated,  -- Global instruments may not have updated field
    'Börsdata Global' as source_description,
    FALSE as is_manual,
    NULL as manual_notes

FROM `psw_marketdata`.`global_instruments` gi
LEFT JOIN `psw_marketdata`.`countries` c ON gi.countryId = c.id
LEFT JOIN `psw_marketdata`.`sectors` s ON gi.sectorId = s.id
LEFT JOIN `psw_marketdata`.`branches` b ON gi.branchId = b.id
LEFT JOIN `psw_marketdata`.`markets` m ON gi.marketId = m.id

UNION ALL

SELECT 
    -- Source identification
    'manual' as data_source,
    mcd.manual_id as source_id,
    mcd.isin,
    mcd.ticker,
    mcd.company_name,
    
    -- Company details
    mcd.country,
    COALESCE(mcd.sector, 'Unknown') as sector,
    COALESCE(mcd.branch, 'Unknown') as branch,
    COALESCE(mcd.market_exchange, 'Unknown') as market_exchange,
    mcd.currency,
    mcd.company_type,
    mcd.dividend_frequency,
    
    -- Metadata
    mcd.updated_at as last_updated,
    'Manual Entry' as source_description,
    TRUE as is_manual,
    mcd.notes as manual_notes

FROM `psw_foundation`.`manual_company_data` mcd;

-- ============================================================================
-- 6. STORED PROCEDURES FOR MONITORING
-- ============================================================================

DELIMITER //

-- Procedure to identify unsupported companies from portfolio/trades
CREATE PROCEDURE IF NOT EXISTS `psw_foundation`.`sp_identify_unsupported_companies`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_isin CHAR(20);
    DECLARE v_ticker VARCHAR(50);
    DECLARE v_count INT;
    
    -- Cursor for ISINs in portfolio/trades that aren't in any data source
    DECLARE unsupported_cursor CURSOR FOR
        SELECT DISTINCT isin, ticker
        FROM (
            SELECT isin, ticker FROM psw_portfolio.portfolio WHERE isin IS NOT NULL
            UNION
            SELECT isin, ticker FROM psw_portfolio.log_trades WHERE isin IS NOT NULL
            UNION 
            SELECT isin, ticker FROM psw_portfolio.log_dividends WHERE isin IS NOT NULL
        ) all_isins
        WHERE isin NOT IN (
            SELECT isin FROM psw_marketdata.nordic_instruments WHERE isin IS NOT NULL
            UNION
            SELECT isin FROM psw_marketdata.global_instruments WHERE isin IS NOT NULL
            UNION
            SELECT isin FROM psw_foundation.manual_company_data WHERE isin IS NOT NULL
        );
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Start sync log
    INSERT INTO psw_foundation.data_sync_log (sync_type, sync_started)
    VALUES ('manual_entry', NOW());
    
    SET @sync_log_id = LAST_INSERT_ID();
    SET @new_count = 0;
    
    OPEN unsupported_cursor;
    
    unsupported_loop: LOOP
        FETCH unsupported_cursor INTO v_isin, v_ticker;
        IF done THEN
            LEAVE unsupported_loop;
        END IF;
        
        -- Add to notifications queue
        INSERT INTO psw_foundation.notification_queue 
        (notification_type, isin, company_name, message, priority)
        VALUES (
            'company_new_unsupported',
            v_isin,
            CONCAT('Unknown Company (', COALESCE(v_ticker, 'No ticker'), ')'),
            CONCAT('New unsupported company found: ISIN ', v_isin, 
                   CASE WHEN v_ticker IS NOT NULL THEN CONCAT(' (Ticker: ', v_ticker, ')') ELSE '' END,
                   '. Please add manual company data.'),
            'medium'
        );
        
        SET @new_count = @new_count + 1;
        
    END LOOP;
    
    CLOSE unsupported_cursor;
    
    -- Update sync log
    UPDATE psw_foundation.data_sync_log 
    SET sync_completed = NOW(),
        new_unsupported_found = @new_count,
        sync_status = 'completed',
        records_processed = @new_count
    WHERE log_id = @sync_log_id;
    
    SELECT CONCAT('Found ', @new_count, ' unsupported companies') as result;
    
END//

-- Procedure to check for missing companies (run after Börsdata sync)
CREATE PROCEDURE IF NOT EXISTS `psw_foundation`.`sp_check_missing_companies`()
BEGIN
    DECLARE v_missing_count INT DEFAULT 0;
    DECLARE v_cutoff_date DATE;
    
    SET v_cutoff_date = DATE_SUB(CURDATE(), INTERVAL 3 DAY);
    
    -- Mark companies as missing if not seen in 3+ days
    UPDATE psw_foundation.company_data_sources 
    SET status = 'missing',
        missing_since = CASE WHEN missing_since IS NULL THEN CURDATE() ELSE missing_since END
    WHERE last_seen_date < v_cutoff_date 
        AND status = 'active'
        AND data_source IN ('borsdata_nordic', 'borsdata_global');
    
    SET v_missing_count = ROW_COUNT();
    
    -- Create notifications for newly missing companies
    INSERT INTO psw_foundation.notification_queue 
    (notification_type, isin, company_name, message, priority)
    SELECT 
        'company_missing',
        cds.isin,
        cds.company_name,
        CONCAT('Company ', cds.company_name, ' (ISIN: ', cds.isin, ') has been missing from ',
               cds.data_source, ' for ', DATEDIFF(CURDATE(), cds.missing_since), ' days.'),
        'high'
    FROM psw_foundation.company_data_sources cds
    WHERE cds.status = 'missing' 
        AND cds.missing_notification_sent = FALSE
        AND cds.missing_since <= v_cutoff_date;
    
    -- Mark notifications as queued
    UPDATE psw_foundation.company_data_sources 
    SET missing_notification_sent = TRUE
    WHERE status = 'missing' 
        AND missing_notification_sent = FALSE
        AND missing_since <= v_cutoff_date;
    
    SELECT CONCAT('Found ', v_missing_count, ' missing companies') as result;
    
END//

-- Procedure to update company data source tracking (call after each Börsdata sync)
CREATE PROCEDURE IF NOT EXISTS `psw_foundation`.`sp_update_data_source_tracking`()
BEGIN
    -- Update Nordic instruments tracking
    INSERT INTO psw_foundation.company_data_sources 
    (isin, data_source, source_id, company_name, last_seen_date, first_seen_date, status)
    SELECT 
        ni.isin,
        'borsdata_nordic',
        ni.insId,
        ni.name,
        CURDATE(),
        CURDATE(),
        'active'
    FROM psw_marketdata.nordic_instruments ni
    WHERE ni.isin IS NOT NULL
    ON DUPLICATE KEY UPDATE
        last_seen_date = CURDATE(),
        status = 'active',
        company_name = VALUES(company_name),
        source_id = VALUES(source_id);
    
    -- Update Global instruments tracking  
    INSERT INTO psw_foundation.company_data_sources 
    (isin, data_source, source_id, company_name, last_seen_date, first_seen_date, status)
    SELECT 
        gi.isin,
        'borsdata_global',
        gi.insId,
        gi.name,
        CURDATE(),
        CURDATE(),
        'active'
    FROM psw_marketdata.global_instruments gi
    WHERE gi.isin IS NOT NULL
    ON DUPLICATE KEY UPDATE
        last_seen_date = CURDATE(),
        status = 'active',
        company_name = VALUES(company_name),
        source_id = VALUES(source_id);
        
    -- Check for missing companies
    CALL psw_foundation.sp_check_missing_companies();
    
END//

DELIMITER ;

-- ============================================================================
-- 7. SAMPLE DATA AND TESTING
-- ============================================================================

-- Insert some sample manual company data for testing
INSERT INTO `psw_foundation`.`manual_company_data` 
(isin, ticker, company_name, country, sector, branch, market_exchange, currency, company_type, dividend_frequency, notes, created_by)
VALUES 
('AT0000730007', 'OMV', 'OMV AG', 'Austria', 'Energy', 'Oil & Gas', 'Vienna Stock Exchange', 'EUR', 'stock', 'annual', 'Austrian oil and gas company', 'admin'),
('GB00B39GTL76', 'HICL', 'HICL Infrastructure PLC', 'United Kingdom', 'Infrastructure', 'Infrastructure Investment', 'London Stock Exchange', 'GBP', 'closed_end_fund', 'quarterly', 'UK infrastructure investment trust', 'admin');

-- Show results
SELECT 'Manual company data created:' as status;
SELECT * FROM psw_foundation.manual_company_data;

SELECT 'Sample unified view:' as status;  
SELECT * FROM psw_foundation.vw_unified_companies WHERE data_source = 'manual';

-- Show created tables
SHOW TABLES LIKE '%manual%';
SHOW TABLES LIKE '%data_source%';
SHOW TABLES LIKE '%sync_log%';
SHOW TABLES LIKE '%notification%';