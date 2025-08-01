-- Populate portfolio with corrected logic
TRUNCATE TABLE psw_portfolio.portfolio;

INSERT INTO psw_portfolio.portfolio (
    isin, 
    ticker, 
    company_name, 
    shares_held,
    currency_local, 
    last_trade_date,
    is_active
)
SELECT 
    lt.isin,
    lt.ticker,
    CASE 
        WHEN lt.ticker = 'BETS B' THEN 'Betsson AB (B)'
        WHEN lt.ticker = 'Betsson' THEN 'Betsson AB'
        WHEN lt.ticker = 'Tele2 B' THEN 'Tele2 AB (B)'
        WHEN lt.ticker = 'TEL2 B' THEN 'Tele2 AB (B)'
        WHEN lt.ticker = 'SWMA' THEN 'SwedenCare AB'
        ELSE CONCAT(lt.ticker, ' Company')
    END as company_name,
    SUM(CASE 
        WHEN lt.trade_type_id = 1 THEN lt.shares_traded  
        WHEN lt.trade_type_id = 9 THEN -lt.shares_traded 
        ELSE 0 
    END) as shares_held,
    lt.currency_local,
    MAX(lt.trade_date) as last_trade_date,
    1 as is_active
FROM psw_portfolio.log_trades lt
GROUP BY lt.isin, lt.ticker, lt.currency_local
HAVING shares_held > 0
ORDER BY shares_held DESC;

-- Verify results
SELECT * FROM psw_portfolio.portfolio;