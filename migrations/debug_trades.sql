-- Debug trade data step by step
SELECT 'Step 1: All trade records' as step;
SELECT * FROM psw_portfolio.log_trades;

SELECT 'Step 2: Check for NULL values' as step;  
SELECT 
    COUNT(*) as total_records,
    COUNT(isin) as non_null_isin,
    COUNT(ticker) as non_null_ticker, 
    COUNT(shares_traded) as non_null_shares
FROM psw_portfolio.log_trades;

SELECT 'Step 3: Records with valid data' as step;
SELECT isin, ticker, shares_traded, currency_local, trade_date
FROM psw_portfolio.log_trades 
WHERE isin IS NOT NULL 
    AND ticker IS NOT NULL
    AND shares_traded IS NOT NULL;

SELECT 'Step 4: Grouped aggregation (what would be inserted)' as step;
SELECT 
    lt.isin,
    lt.ticker,
    SUM(lt.shares_traded) as shares_held,
    COALESCE(lt.currency_local, 'SEK') as currency_local,
    MAX(lt.trade_date) as last_trade_date
FROM psw_portfolio.log_trades lt
WHERE lt.isin IS NOT NULL 
    AND lt.ticker IS NOT NULL
    AND lt.shares_traded IS NOT NULL
GROUP BY lt.isin, lt.ticker, lt.currency_local;

SELECT 'Step 5: Only positive holdings' as step;
SELECT 
    lt.isin,
    lt.ticker,
    SUM(lt.shares_traded) as shares_held,
    COALESCE(lt.currency_local, 'SEK') as currency_local,
    MAX(lt.trade_date) as last_trade_date
FROM psw_portfolio.log_trades lt
WHERE lt.isin IS NOT NULL 
    AND lt.ticker IS NOT NULL
    AND lt.shares_traded IS NOT NULL
GROUP BY lt.isin, lt.ticker, lt.currency_local
HAVING shares_held > 0;