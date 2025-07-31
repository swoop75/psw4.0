-- Add broker_fees_percent column to log_trades table
-- This stores the calculated percentage to avoid recalculating on every query

ALTER TABLE `psw_portfolio`.`log_trades` 
ADD COLUMN `broker_fees_percent` DECIMAL(5,4) NULL DEFAULT NULL 
COMMENT 'Broker fees as percentage of total amount (e.g., 0.1500 = 0.15%)' 
AFTER `broker_fees_sek`;

-- Update existing records to calculate and store the percentage
UPDATE `psw_portfolio`.`log_trades` 
SET `broker_fees_percent` = CASE 
    WHEN `total_amount_sek` > 0 THEN (`broker_fees_sek` / `total_amount_sek`) * 100
    ELSE 0 
END
WHERE `broker_fees_percent` IS NULL;

-- Add index for better performance when filtering/sorting by fees percentage
ALTER TABLE `psw_portfolio`.`log_trades` 
ADD INDEX `idx_broker_fees_percent` (`broker_fees_percent`);