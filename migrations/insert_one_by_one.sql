-- Insert one record at a time to test
INSERT INTO psw_portfolio.portfolio (isin, ticker, company_name, shares_held, currency_local, last_trade_date, is_active) 
VALUES ('SE0022726485', 'BETS B', 'Betsson AB (B)', 508.0000, 'SEK', '2016-04-28', 1);

SELECT 'After first insert:' as status;
SELECT * FROM psw_portfolio.portfolio;

INSERT INTO psw_portfolio.portfolio (isin, ticker, company_name, shares_held, currency_local, last_trade_date, is_active) 
VALUES ('SE0005190238', 'TEL2 B', 'Tele2 AB (B)', 178.0000, 'SEK', '2016-11-29', 1);

SELECT 'After second insert:' as status;
SELECT * FROM psw_portfolio.portfolio;

INSERT INTO psw_portfolio.portfolio (isin, ticker, company_name, shares_held, currency_local, last_trade_date, is_active) 
VALUES ('SE0015812219', 'SWMA', 'Swedish Match AB', 17.0000, 'SEK', '2016-11-11', 1);

SELECT 'Final result:' as status;
SELECT * FROM psw_portfolio.portfolio;