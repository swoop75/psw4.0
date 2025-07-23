-- Create KPI Metadata table for PSW Market Data
-- This table stores metadata for Key Performance Indicators (KPIs) from the API
-- Database: psw_marketdata

USE psw_marketdata;

CREATE TABLE IF NOT EXISTS kpi_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Auto-incrementing primary key',
    kpi_id INT NOT NULL UNIQUE COMMENT 'KPI ID from API',
    name_sv VARCHAR(255) NOT NULL COMMENT 'Swedish name of the KPI',
    name_en VARCHAR(255) NOT NULL COMMENT 'English name of the KPI',
    format VARCHAR(50) NULL COMMENT 'Format specification (e.g., %, currency)',
    is_string BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Whether the KPI value is a string type',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record last update timestamp',
    
    -- Indexes for performance
    INDEX idx_kpi_id (kpi_id),
    INDEX idx_name_en (name_en),
    INDEX idx_name_sv (name_sv),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KPI metadata from API endpoint /v1/instruments/kpis/metadata';

-- Add some sample data based on the provided JSON structure
-- This will be replaced by the Python script, but useful for initial testing
INSERT INTO kpi_metadata (kpi_id, name_sv, name_en, format, is_string) VALUES
(1, 'Direktavkastning', 'Dividend Yield', '%', FALSE),
(2, 'P/E', 'P/E', NULL, FALSE)
ON DUPLICATE KEY UPDATE
    name_sv = VALUES(name_sv),
    name_en = VALUES(name_en),
    format = VALUES(format),
    is_string = VALUES(is_string),
    updated_at = CURRENT_TIMESTAMP;