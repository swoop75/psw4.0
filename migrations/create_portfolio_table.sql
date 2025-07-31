-- Create portfolio table for current holdings
-- This table will store current portfolio positions aggregated from trade logs

CREATE TABLE IF NOT EXISTS `psw_portfolio`.`portfolio` (
    `portfolio_id` INT(11) NOT NULL AUTO_INCREMENT,
    `isin` CHAR(20) NOT NULL COMMENT 'International Securities Identification Number',
    `ticker` VARCHAR(20) DEFAULT NULL COMMENT 'Stock ticker symbol',
    `company_name` VARCHAR(255) DEFAULT NULL COMMENT 'Company name from masterlist',
    `shares_held` DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'Current number of shares held',
    `average_cost_price_sek` DECIMAL(15,4) DEFAULT NULL COMMENT 'Average cost price per share in SEK',
    `total_cost_sek` DECIMAL(15,4) DEFAULT NULL COMMENT 'Total cost basis in SEK',
    `currency_local` VARCHAR(3) DEFAULT NULL COMMENT 'Local trading currency',
    `market` VARCHAR(20) DEFAULT NULL COMMENT 'Primary market/exchange',
    `sector` VARCHAR(100) DEFAULT NULL COMMENT 'Business sector',
    `country` VARCHAR(50) DEFAULT NULL COMMENT 'Country of domicile',
    `last_trade_date` DATE DEFAULT NULL COMMENT 'Date of last trade for this position',
    `last_updated_price` DATETIME DEFAULT NULL COMMENT 'When price data was last updated',
    `latest_price_local` DECIMAL(15,4) DEFAULT NULL COMMENT 'Latest price in local currency',
    `latest_price_sek` DECIMAL(15,4) DEFAULT NULL COMMENT 'Latest price in SEK',
    `fx_rate_used` DECIMAL(10,6) DEFAULT NULL COMMENT 'FX rate used for SEK conversion',
    `current_value_local` DECIMAL(15,4) DEFAULT NULL COMMENT 'Current market value in local currency',
    `current_value_sek` DECIMAL(15,4) DEFAULT NULL COMMENT 'Current market value in SEK',
    `unrealized_gain_loss_sek` DECIMAL(15,4) DEFAULT NULL COMMENT 'Unrealized P&L in SEK',
    `unrealized_gain_loss_percent` DECIMAL(8,4) DEFAULT NULL COMMENT 'Unrealized P&L percentage',
    `portfolio_weight_percent` DECIMAL(8,4) DEFAULT NULL COMMENT 'Weight as % of total portfolio',
    `is_active` BOOLEAN DEFAULT TRUE COMMENT 'Whether position is currently active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`portfolio_id`),
    UNIQUE KEY `uk_portfolio_isin` (`isin`),
    KEY `idx_portfolio_ticker` (`ticker`),
    KEY `idx_portfolio_market` (`market`),
    KEY `idx_portfolio_country` (`country`),
    KEY `idx_portfolio_sector` (`sector`),
    KEY `idx_portfolio_shares_held` (`shares_held`),
    KEY `idx_portfolio_is_active` (`is_active`),
    KEY `idx_portfolio_updated_at` (`updated_at`),
    
    -- Foreign key constraints
    CONSTRAINT `fk_portfolio_isin` 
        FOREIGN KEY (`isin`) 
        REFERENCES `psw_foundation`.`masterlist` (`isin`) 
        ON UPDATE CASCADE ON DELETE RESTRICT
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Current portfolio holdings with real-time valuations';

-- Create indexes for performance
CREATE INDEX `idx_portfolio_value_sek` ON `psw_portfolio`.`portfolio` (`current_value_sek` DESC);
CREATE INDEX `idx_portfolio_weight` ON `psw_portfolio`.`portfolio` (`portfolio_weight_percent` DESC);
CREATE INDEX `idx_portfolio_performance` ON `psw_portfolio`.`portfolio` (`unrealized_gain_loss_percent` DESC);

-- Create view for portfolio overview with additional calculated fields
CREATE OR REPLACE VIEW `psw_portfolio`.`v_portfolio_overview` AS
SELECT 
    p.*,
    ml.name as company_name_master,
    ml.country as country_master,
    ml.currency as currency_master,
    ml.market as market_master,
    ml.sector as sector_master,
    
    -- Latest price data from global_latest_prices
    glp.price as global_latest_price,
    glp.currency as global_price_currency,
    glp.last_updated as global_price_updated,
    
    -- Latest price data from nordic_latest_prices  
    nlp.price as nordic_latest_price,
    nlp.currency as nordic_price_currency,
    nlp.last_updated as nordic_price_updated,
    
    -- FX rates
    fx.rate as fx_rate_current,
    fx.last_updated as fx_rate_updated,
    
    -- Calculated fields
    CASE 
        WHEN p.shares_held > 0 THEN 
            COALESCE(p.current_value_sek, 0) / NULLIF((SELECT SUM(current_value_sek) FROM portfolio WHERE is_active = 1), 0) * 100
        ELSE 0 
    END as calculated_weight_percent,
    
    CASE 
        WHEN p.total_cost_sek > 0 THEN 
            (COALESCE(p.current_value_sek, 0) - p.total_cost_sek) / p.total_cost_sek * 100
        ELSE 0 
    END as calculated_return_percent

FROM `psw_portfolio`.`portfolio` p
LEFT JOIN `psw_foundation`.`masterlist` ml ON p.isin = ml.isin
LEFT JOIN `psw_marketdata`.`global_latest_prices` glp ON p.isin = glp.isin
LEFT JOIN `psw_marketdata`.`nordic_latest_prices` nlp ON p.ticker = nlp.ticker
LEFT JOIN `psw_marketdata`.`fx_rates_freecurrency` fx ON p.currency_local = fx.from_currency AND fx.to_currency = 'SEK'
WHERE p.is_active = 1
ORDER BY p.current_value_sek DESC;