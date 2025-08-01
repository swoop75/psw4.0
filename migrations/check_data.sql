-- Check what data is available
SELECT COUNT(*) as total_trades FROM psw_portfolio.log_trades;

SELECT isin, ticker, shares_traded, trade_date 
FROM psw_portfolio.log_trades 
WHERE shares_traded > 0 
LIMIT 5;

-- Check table structure
DESCRIBE psw_portfolio.log_trades;