-- Add foreign key constraint to portfolio table after ensuring character set compatibility
-- Run this AFTER creating the portfolio table and verifying character sets match

-- First, check the character sets of both tables:
/*
SELECT 
    TABLE_SCHEMA,
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_SET_NAME,
    COLLATION_NAME
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA IN ('psw_foundation', 'psw_portfolio') 
    AND COLUMN_NAME = 'isin'
    AND TABLE_NAME IN ('masterlist', 'portfolio');
*/

-- If character sets don't match, you may need to alter one of the tables:
-- Example to change portfolio table to match masterlist:
-- ALTER TABLE psw_portfolio.portfolio MODIFY COLUMN isin CHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

-- Or to change masterlist to match portfolio:
-- ALTER TABLE psw_foundation.masterlist MODIFY COLUMN isin CHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

-- Once character sets match, add the foreign key constraint:
ALTER TABLE `psw_portfolio`.`portfolio` 
ADD CONSTRAINT `fk_portfolio_isin` 
FOREIGN KEY (`isin`) 
REFERENCES `psw_foundation`.`masterlist` (`isin`) 
ON UPDATE CASCADE 
ON DELETE RESTRICT;