-- Simple aggregation test
SELECT 
    lt.isin,
    lt.ticker,
    SUM(CASE 
        WHEN lt.trade_type_id = 1 THEN lt.shares_traded  
        WHEN lt.trade_type_id = 9 THEN -lt.shares_traded 
        ELSE 0 
    END) as shares_held,
    lt.currency_local
FROM psw_portfolio.log_trades lt
GROUP BY lt.isin, lt.ticker, lt.currency_local
ORDER BY shares_held DESC;