-- Add dividend fee support to log_dividends table
-- This adds broker fee tracking for dividend payments
-- Issue: Fee in % (dividend log) doesn't calculate

USE psw_portfolio;

-- Add broker fee fields to log_dividends table
ALTER TABLE `log_dividends` 
ADD COLUMN `broker_fee_local` DECIMAL(15,4) NULL DEFAULT 0.0000 
    COMMENT 'Broker fee in original currency for dividend payment' 
    AFTER `tax_amount_sek`,
ADD COLUMN `broker_fee_sek` DECIMAL(15,4) NULL DEFAULT 0.0000 
    COMMENT 'Broker fee in SEK for dividend payment' 
    AFTER `broker_fee_local`,
ADD COLUMN `broker_fee_percent` DECIMAL(5,4) NULL DEFAULT NULL 
    COMMENT 'Broker fee as percentage of gross dividend amount (e.g., 0.1500 = 0.15%)' 
    AFTER `broker_fee_sek`;

-- Calculate existing broker fee percentages for any existing data
-- (Most likely these will be 0 since fees weren't tracked before)
UPDATE `log_dividends` 
SET `broker_fee_percent` = CASE 
    WHEN `dividend_amount_sek` > 0 THEN (`broker_fee_sek` / `dividend_amount_sek`) * 100
    ELSE 0
END
WHERE `broker_fee_percent` IS NULL AND `broker_fee_sek` IS NOT NULL;

-- Add index for better performance when filtering/sorting by fees percentage
ALTER TABLE `log_dividends`
ADD INDEX `idx_broker_fee_percent` (`broker_fee_percent`);

-- Add composite index for dividend analysis
ALTER TABLE `log_dividends`
ADD INDEX `idx_dividend_fees_analysis` (`payment_date`, `broker_fee_sek`, `broker_fee_percent`);

-- Update the net dividend calculation to account for broker fees
-- Add computed column for true net dividend (after tax AND fees)
ALTER TABLE `log_dividends`
ADD COLUMN `net_dividend_after_fees_sek` DECIMAL(15,4) GENERATED ALWAYS AS 
    (`dividend_amount_sek` - `tax_amount_sek` - IFNULL(`broker_fee_sek`, 0)) STORED
    COMMENT 'Net dividend after withholding tax and broker fees in SEK';

-- Add index for the computed net dividend column
ALTER TABLE `log_dividends`
ADD INDEX `idx_net_dividend_after_fees` (`net_dividend_after_fees_sek`);

-- Display current table structure to verify changes
DESCRIBE `log_dividends`;