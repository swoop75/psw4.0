-- Direct insert to test portfolio table
INSERT INTO psw_portfolio.portfolio (
    isin, 
    ticker, 
    company_name, 
    shares_held, 
    currency_local, 
    is_active
) VALUES 
(
    'US0378331005',
    'AAPL', 
    'Apple Inc.',
    100.0000,
    'USD',
    1
);

SELECT * FROM psw_portfolio.portfolio;