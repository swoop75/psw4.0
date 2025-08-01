-- Manual insert of the exact positions we know exist
INSERT INTO psw_portfolio.portfolio (isin, ticker, company_name, shares_held, currency_local, is_active) VALUES
('SE0022726485', 'BETS B', 'Betsson AB (B)', 369.0000, 'SEK', 1),
('SE0022726485', 'Betsson', 'Betsson AB', 139.0000, 'SEK', 1),
('SE0005190238', 'Tele2 B', 'Tele2 AB (B)', 133.0000, 'SEK', 1),
('SE0005190238', 'TEL2 B', 'Tele2 AB (B)', 45.0000, 'SEK', 1),
('SE0015812219', 'SWMA', 'SwedenCare AB', 17.0000, 'SEK', 1);

SELECT * FROM psw_portfolio.portfolio;