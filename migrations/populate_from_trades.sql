-- Populate portfolio from actual trade data
-- First, clear the test data
DELETE FROM psw_portfolio.portfolio;

-- Let's see what trade data we have first
SELECT 'Current trade data:' as info;
SELECT isin, ticker, shares_traded, trade_date, trade_type_id 
FROM psw_portfolio.log_trades 
ORDER BY trade_date DESC;

-- Now populate from actual trades
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
    CONCAT(lt.ticker, ' Company') as company_name,  -- Temporary company name
    SUM(lt.shares_traded) as shares_held,
    COALESCE(lt.currency_local, 'SEK') as currency_local,
    MAX(lt.trade_date) as last_trade_date,
    1 as is_active
FROM psw_portfolio.log_trades lt
WHERE lt.isin IS NOT NULL 
    AND lt.ticker IS NOT NULL
    AND lt.shares_traded IS NOT NULL
GROUP BY lt.isin, lt.ticker, lt.currency_local
HAVING shares_held > 0;

-- Show results
SELECT 'Portfolio populated:' as info;
SELECT * FROM psw_portfolio.portfolio;