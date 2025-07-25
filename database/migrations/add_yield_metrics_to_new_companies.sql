-- Migration: Add comprehensive yield metrics to new_companies table
-- Date: 2025-07-25
-- Description: Add yield data fields for different time periods and calculations

USE psw_portfolio;

-- Add yield metric columns to new_companies table
ALTER TABLE new_companies 
ADD COLUMN yield_current DECIMAL(6,4) NULL COMMENT 'Current yield from latest data',
ADD COLUMN yield_1y_avg DECIMAL(6,4) NULL COMMENT '1 year average yield',
ADD COLUMN yield_1y_cagr DECIMAL(6,4) NULL COMMENT '1 year yield CAGR',
ADD COLUMN yield_3y_avg DECIMAL(6,4) NULL COMMENT '3 year average yield',
ADD COLUMN yield_3y_cagr DECIMAL(6,4) NULL COMMENT '3 year yield CAGR',
ADD COLUMN yield_5y_avg DECIMAL(6,4) NULL COMMENT '5 year average yield',
ADD COLUMN yield_5y_cagr DECIMAL(6,4) NULL COMMENT '5 year yield CAGR',
ADD COLUMN yield_10y_avg DECIMAL(6,4) NULL COMMENT '10 year average yield',
ADD COLUMN yield_10y_cagr DECIMAL(6,4) NULL COMMENT '10 year yield CAGR',
ADD COLUMN yield_data_updated_at TIMESTAMP NULL COMMENT 'When yield data was last updated',
ADD COLUMN yield_source VARCHAR(50) DEFAULT 'borsdata' COMMENT 'Source of yield data (borsdata, manual)';

-- Add indexes for performance
CREATE INDEX idx_yield_current ON new_companies (yield_current);
CREATE INDEX idx_yield_data_updated ON new_companies (yield_data_updated_at);
CREATE INDEX idx_yield_source ON new_companies (yield_source);

-- Update existing yield column to match new precision if needed
-- ALTER TABLE new_companies MODIFY COLUMN yield DECIMAL(6,4) NULL COMMENT 'Legacy yield field - use yield_current instead';