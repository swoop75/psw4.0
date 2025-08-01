-- Insert the missing Tele2 position
INSERT INTO psw_portfolio.portfolio (isin, ticker, company_name, shares_held, currency_local, last_trade_date, is_active) 
VALUES ('SE0005190238', 'TEL2 B', 'Tele2 AB (B)', 178.0000, 'SEK', '2016-11-29', 1);

-- Show all positions
SELECT 
    ticker,
    company_name,
    shares_held,
    last_trade_date
FROM psw_portfolio.portfolio 
ORDER BY shares_held DESC;