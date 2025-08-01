-- Test basic INSERT first
INSERT INTO psw_portfolio.portfolio (isin, ticker, company_name, shares_held, currency_local, is_active) 
VALUES ('TEST123', 'TEST', 'Test Company', 100, 'SEK', 1);

SELECT * FROM psw_portfolio.portfolio;

-- If that works, let's check the aggregation results:
SELECT 
    lt.isin,
    lt.ticker,
    SUM(CASE 
        WHEN lt.trade_type_id = 1 THEN lt.shares_traded  
        WHEN lt.trade_type_id = 9 THEN -lt.shares_traded 
        ELSE 0 
    END) as shares_held
FROM psw_portfolio.log_trades lt
GROUP BY lt.isin, lt.ticker, lt.currency_local
HAVING shares_held > 0
ORDER BY shares_held DESC;