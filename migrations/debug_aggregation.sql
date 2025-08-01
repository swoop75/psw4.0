-- Debug the aggregation step by step
SELECT 'Step 1: Basic aggregation without HAVING clause' as step;
SELECT 
    lt.isin,
    lt.ticker,
    SUM(CASE 
        WHEN lt.trade_type_id = 1 THEN lt.shares_traded  -- BUY
        WHEN lt.trade_type_id = 9 THEN -lt.shares_traded -- SELL
        ELSE 0 
    END) as shares_held,
    lt.currency_local
FROM psw_portfolio.log_trades lt
WHERE lt.isin IS NOT NULL 
    AND lt.ticker IS NOT NULL
    AND lt.shares_traded IS NOT NULL
    AND lt.shares_traded > 0
GROUP BY lt.isin, lt.ticker, lt.currency_local
ORDER BY shares_held DESC;

SELECT 'Step 2: Check what gets filtered by HAVING shares_held > 0' as step;
SELECT 
    lt.isin,
    lt.ticker,
    SUM(CASE 
        WHEN lt.trade_type_id = 1 THEN lt.shares_traded  
        WHEN lt.trade_type_id = 9 THEN -lt.shares_traded 
        ELSE 0 
    END) as shares_held
FROM psw_portfolio.log_trades lt
WHERE lt.isin IS NOT NULL 
    AND lt.ticker IS NOT NULL
    AND lt.shares_traded IS NOT NULL
    AND lt.shares_traded > 0
GROUP BY lt.isin, lt.ticker, lt.currency_local
HAVING shares_held > 0;