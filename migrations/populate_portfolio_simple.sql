-- Simple portfolio population script with data checks
-- Step 1: Check if we have trade data
SELECT 'Checking trade data availability...' as status;
SELECT COUNT(*) as total_trades FROM psw_portfolio.log_trades;

-- Step 2: Check sample of trade data
SELECT 'Sample trade data:' as status;
SELECT isin, ticker, shares_traded, trade_date, trade_type_id 
FROM psw_portfolio.log_trades 
WHERE shares_traded > 0 
LIMIT 5;

-- Step 3: Clear existing portfolio data
TRUNCATE TABLE psw_portfolio.portfolio;

-- Step 4: Simple insert with basic aggregation
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
    'Sample Company' as company_name,  -- Simplified for now
    SUM(lt.shares_traded) as shares_held,  -- Simple sum for testing
    COALESCE(lt.currency_local, 'SEK') as currency_local,
    MAX(lt.trade_date) as last_trade_date,
    1 as is_active
FROM psw_portfolio.log_trades lt
WHERE lt.isin IS NOT NULL 
    AND lt.ticker IS NOT NULL
    AND lt.shares_traded > 0  -- Only positive trades for now
GROUP BY lt.isin, lt.ticker, lt.currency_local
HAVING shares_held > 0
LIMIT 10;  -- Limit to 10 positions for testing

-- Step 5: Verify results
SELECT 'Results:' as status;
SELECT COUNT(*) as positions_created FROM psw_portfolio.portfolio;
SELECT * FROM psw_portfolio.portfolio LIMIT 5;