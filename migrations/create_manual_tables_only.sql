-- Create just the essential tables for manual company management
-- Skip stored procedures for now

-- ============================================================================
-- 1. MANUAL COMPANY DATA TABLE
-- ============================================================================
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
-- 2. SIMPLE UNIFIED VIEW
-- ============================================================================
CREATE OR REPLACE VIEW `psw_foundation`.`vw_unified_companies` AS
SELECT 
    -- Source identification
    'borsdata_nordic' as data_source,
    ni.insId as source_id,
    ni.isin COLLATE utf8mb4_unicode_ci as isin,
    ni.ticker COLLATE utf8mb4_unicode_ci as ticker,
    ni.name COLLATE utf8mb4_unicode_ci as company_name,
    
    -- Company details
    'Unknown' as country,
    'Unknown' as sector,
    'Unknown' as branch,
    'Unknown' as market_exchange,
    ni.stockPriceCurrency COLLATE utf8mb4_unicode_ci as currency,
    'stock' as company_type,
    'quarterly' as dividend_frequency,
    
    -- Metadata
    ni.updated as last_updated,
    'Börsdata Nordic' as source_description,
    FALSE as is_manual,
    NULL as manual_notes

FROM `psw_marketdata`.`nordic_instruments` ni

UNION ALL

SELECT 
    -- Source identification
    'borsdata_global' as data_source,
    gi.insId as source_id,
    gi.isin COLLATE utf8mb4_unicode_ci as isin,
    gi.ticker COLLATE utf8mb4_unicode_ci as ticker,
    gi.name COLLATE utf8mb4_unicode_ci as company_name,
    
    -- Company details
    'Unknown' as country,
    'Unknown' as sector,
    'Unknown' as branch,
    'Unknown' as market_exchange,
    gi.stockPriceCurrency COLLATE utf8mb4_unicode_ci as currency,
    'stock' as company_type,
    'quarterly' as dividend_frequency,
    
    -- Metadata
    NULL as last_updated,
    'Börsdata Global' as source_description,
    FALSE as is_manual,
    NULL as manual_notes

FROM `psw_marketdata`.`global_instruments` gi

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

-- Test the table and view
SELECT 'Tables created successfully!' as result;
SELECT COUNT(*) as manual_companies FROM psw_foundation.manual_company_data;
SELECT COUNT(*) as unified_companies FROM psw_foundation.vw_unified_companies;