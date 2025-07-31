-- ================================================================================
-- PSW Portfolio Management System
-- CREATE TABLES: trade_types (psw_foundation) and log_trades (psw_portfolio)
-- 
-- Purpose: Trade execution log for portfolio valuation and performance analysis
-- Created: 2025-07-31
-- ================================================================================

-- ================================================================================
-- Step 1: Create trade_types lookup table in psw_foundation
-- ================================================================================

USE psw_foundation;

CREATE TABLE trade_types (
    trade_type_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    type_code VARCHAR(20) NOT NULL UNIQUE,
    type_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    affects_position TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = affects share position, 0 = neutral (like dividend reinvest)',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_type_code (type_code),
    INDEX idx_is_active (is_active)
) 
ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci 
COMMENT='Trade type definitions for log_trades table';

-- Insert standard trade types
INSERT INTO trade_types (type_code, type_name, description, affects_position) VALUES
('BUY', 'Buy', 'Purchase of securities', 1),
('SELL', 'Sell', 'Sale of securities', 1),
('SPLIT', 'Stock Split', 'Stock split adjustment', 1),
('MERGER', 'Merger', 'Merger or acquisition', 1),
('SPIN_OFF', 'Spin-off', 'Corporate spin-off', 1),
('DIVIDEND_REINVEST', 'Dividend Reinvestment', 'Automatic dividend reinvestment', 1),
('TRANSFER_IN', 'Transfer In', 'Securities transferred into account', 1),
('TRANSFER_OUT', 'Transfer Out', 'Securities transferred out of account', 1),
('RIGHTS_ISSUE', 'Rights Issue', 'Rights offering participation', 1),
('BONUS_ISSUE', 'Bonus Issue', 'Bonus shares received', 1);

-- ================================================================================
-- Step 2: Create log_trades table in psw_portfolio
-- ================================================================================

USE psw_portfolio;

CREATE TABLE log_trades (
    -- Primary identification
    trade_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    
    -- Trade execution details
    trade_date DATE NOT NULL,
    settlement_date DATE NULL,
    trade_type_id INT NOT NULL,  -- Foreign key to psw_foundation.trade_types
    
    -- Security identification (matching masterlist)
    isin CHAR(20) NOT NULL,
    ticker VARCHAR(20) NULL,
    
    -- Quantity details
    shares_traded DECIMAL(12,4) NOT NULL,
    
    -- Pricing in local currency
    price_per_share_local DECIMAL(15,6) NULL,
    total_amount_local DECIMAL(15,4) NULL,
    currency_local VARCHAR(3) NULL,
    
    -- Pricing in SEK (for consistent performance analysis)
    price_per_share_sek DECIMAL(15,6) NOT NULL,
    total_amount_sek DECIMAL(15,4) NOT NULL,
    exchange_rate_used DECIMAL(10,6) NULL,
    
    -- Fees and costs (important for performance analysis)
    broker_fees_local DECIMAL(15,4) NULL DEFAULT 0.0000,
    broker_fees_sek DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    tft_tax_local DECIMAL(15,4) NULL DEFAULT 0.0000,  -- Transaction Financial Tax (stamp duty, etc.)
    tft_tax_sek DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    tft_rate_percent DECIMAL(5,2) NULL,  -- TFT rate used (e.g., 0.50 for UK stamp duty)
    
    -- Net amounts (total ± fees ± taxes)
    net_amount_local DECIMAL(15,4) NULL,
    net_amount_sek DECIMAL(15,4) NOT NULL,
    
    -- Account relationships (matching log_dividends)
    broker_id INT NULL,
    portfolio_account_group_id INT NULL,
    
    -- Trade metadata
    broker_transaction_id VARCHAR(100) NULL,
    order_type ENUM('MARKET', 'LIMIT', 'STOP', 'OTHER') NULL,
    execution_status ENUM('EXECUTED', 'PARTIAL', 'CANCELLED') NOT NULL DEFAULT 'EXECUTED',
    
    -- Corporate actions & relationships
    related_corporate_action_id INT NULL,
    related_trade_id INT NULL, -- For splits, mergers, etc.
    
    -- Data quality (matching log_dividends pattern)
    is_complete TINYINT(1) NOT NULL DEFAULT 1,
    incomplete_fields TEXT NULL,
    data_source ENUM('MANUAL', 'BROKER_IMPORT', 'API', 'CORPORATE_ACTION') NULL,
    
    -- Notes and audit trail
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_trade_date (trade_date),
    INDEX idx_settlement_date (settlement_date),
    INDEX idx_isin (isin),
    INDEX idx_portfolio_group (portfolio_account_group_id),
    INDEX idx_broker (broker_id),
    INDEX idx_trade_type (trade_type_id),
    INDEX idx_created_at (created_at),
    INDEX idx_broker_transaction (broker_transaction_id),
    INDEX idx_execution_status (execution_status),
    INDEX idx_data_source (data_source),
    
    -- Composite indexes for common queries
    INDEX idx_isin_trade_date (isin, trade_date),
    INDEX idx_portfolio_trade_date (portfolio_account_group_id, trade_date),
    INDEX idx_broker_trade_date (broker_id, trade_date),
    INDEX idx_trade_type_date (trade_type_id, trade_date)
) 
ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci 
COMMENT='Trade execution log for portfolio valuation and performance analysis';

-- ================================================================================
-- Add sample data for testing (optional - remove in production)
-- ================================================================================

-- Sample BUY trade (trade_type_id = 1 for 'BUY')
INSERT INTO log_trades (
    trade_date, settlement_date, trade_type_id, isin, ticker,
    shares_traded, price_per_share_local, total_amount_local, currency_local,
    price_per_share_sek, total_amount_sek, exchange_rate_used,
    broker_fees_local, broker_fees_sek, tft_tax_local, tft_tax_sek, tft_rate_percent,
    net_amount_local, net_amount_sek,
    broker_id, portfolio_account_group_id,
    broker_transaction_id, order_type, execution_status, data_source,
    notes
) VALUES (
    '2025-07-31', '2025-08-02', 1, 'GB0002162385', 'BARC',
    100.0000, 2.1250, 212.50, 'GBP',
    24.7500, 2475.00, 11.6471,
    9.95, 115.92, 1.06, 12.38, 0.50,
    223.51, 2603.30,
    1, 1,
    'TXN123456', 'MARKET', 'EXECUTED', 'MANUAL',
    'Sample UK stock purchase with stamp duty'
);

-- Sample SELL trade (trade_type_id = 2 for 'SELL')
INSERT INTO log_trades (
    trade_date, settlement_date, trade_type_id, isin, ticker,
    shares_traded, price_per_share_local, total_amount_local, currency_local,
    price_per_share_sek, total_amount_sek, exchange_rate_used,
    broker_fees_local, broker_fees_sek,
    net_amount_local, net_amount_sek,
    broker_id, portfolio_account_group_id,
    broker_transaction_id, order_type, execution_status, data_source,
    notes
) VALUES (
    '2025-07-31', '2025-08-02', 2, 'US0378331005', 'AAPL',
    50.0000, 227.50, 11375.00, 'USD',
    2050.00, 102500.00, 9.0110,
    14.95, 134.64,
    11360.05, 102365.36,
    2, 1,
    'TXN789012', 'LIMIT', 'EXECUTED', 'BROKER_IMPORT',
    'Sample US stock sale'
);

-- ================================================================================
-- Verification queries
-- ================================================================================

-- Check table structure
DESCRIBE log_trades;

-- Check indexes
SHOW INDEX FROM log_trades;

-- Check sample data with trade type names
SELECT 
    lt.trade_id, lt.trade_date, tt.type_code, tt.type_name, 
    lt.isin, lt.ticker, lt.shares_traded, 
    lt.total_amount_sek, lt.net_amount_sek, lt.created_at
FROM log_trades lt
JOIN psw_foundation.trade_types tt ON lt.trade_type_id = tt.trade_type_id
ORDER BY lt.trade_date DESC;

-- Check trade_types table
SELECT * FROM psw_foundation.trade_types ORDER BY trade_type_id;

-- ================================================================================