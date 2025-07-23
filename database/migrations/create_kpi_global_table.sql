-- Create KPI Global table for PSW Market Data
-- This table stores global KPI statistics from Börsdata API
-- Database: psw_marketdata

USE psw_marketdata;

CREATE TABLE IF NOT EXISTS kpi_global (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Auto-incrementing primary key',
    kpi_id INT NOT NULL COMMENT 'KPI ID from API (links to kpi_metadata.kpi_id)',
    group_period VARCHAR(50) NOT NULL COMMENT 'Time period group (e.g., 1year, 3year, 5year)',
    calculation VARCHAR(50) NOT NULL COMMENT 'Calculation method (e.g., mean, median, max, min)',
    instrument_id INT NOT NULL COMMENT 'Instrument ID from API (i field)',
    numeric_value DECIMAL(20,10) NULL COMMENT 'Numeric KPI value (n field)',
    string_value TEXT NULL COMMENT 'String KPI value (s field)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record last update timestamp',
    
    -- Composite unique key to prevent duplicates
    UNIQUE KEY uk_kpi_global (kpi_id, group_period, calculation, instrument_id),
    
    -- Indexes for performance
    INDEX idx_kpi_id (kpi_id),
    INDEX idx_instrument_id (instrument_id),
    INDEX idx_group_period (group_period),
    INDEX idx_calculation (calculation),
    INDEX idx_numeric_value (numeric_value),
    INDEX idx_created_at (created_at),
    
    -- Composite indexes for common queries
    INDEX idx_kpi_group_calc (kpi_id, group_period, calculation),
    INDEX idx_instrument_kpi (instrument_id, kpi_id),
    
    -- Foreign key constraint to kpi_metadata
    CONSTRAINT fk_kpi_global_metadata 
        FOREIGN KEY (kpi_id) 
        REFERENCES kpi_metadata(kpi_id) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Global KPI statistics from Börsdata API endpoint /v1/instruments/global/kpis/{kpiId}/{group}/{calculation}';

-- Add some sample data based on the provided API response
-- This will be replaced by the Python script, but useful for initial testing
INSERT INTO kpi_global (kpi_id, group_period, calculation, instrument_id, numeric_value, string_value) VALUES
(2, '1year', 'mean', 10054, 0.015974819660186768, NULL),
(2, '1year', 'mean', 10055, -9.697495460510254, NULL)
ON DUPLICATE KEY UPDATE
    numeric_value = VALUES(numeric_value),
    string_value = VALUES(string_value),
    updated_at = CURRENT_TIMESTAMP;