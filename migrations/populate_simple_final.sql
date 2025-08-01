-- Clear test data and populate with real trades
DELETE FROM psw_portfolio.portfolio WHERE isin = 'TEST123';

-- Simple INSERT based on corrected trade data
INSERT INTO psw_portfolio.portfolio (
    isin, 
    ticker, 
    company_name, 
    shares_held,
    currency_local, 
    last_trade_date,
    is_active
) VALUES
-- Betsson: 229 + 139 + 140 = 508 shares
('SE0022726485', 'BETS B', 'Betsson AB (B)', 508.0000, 'SEK', '2016-04-28', 1),
-- Tele2: 133 + 70 - 25 = 178 shares  
('SE0005190238', 'TEL2 B', 'Tele2 AB (B)', 178.0000, 'SEK', '2016-11-29', 1),
-- Swedish Match: 17 shares
('SE0015812219', 'SWMA', 'Swedish Match AB', 17.0000, 'SEK', '2016-11-11', 1);

-- Show results
SELECT * FROM psw_portfolio.portfolio;