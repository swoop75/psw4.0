-- Populate portfolio handling UNIQUE ISIN constraint
-- We need to aggregate by ISIN only, not by ISIN+ticker
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
    -- Use the most recent ticker for this ISIN
    (SELECT ticker FROM psw_portfolio.log_trades lt2 
     WHERE lt2.isin = lt.isin 
     ORDER BY lt2.trade_date DESC LIMIT 1) as ticker,
    CASE 
        WHEN lt.isin = 'SE0022726485' THEN 'Betsson AB'
        WHEN lt.isin = 'SE0005190238' THEN 'Tele2 AB'
        WHEN lt.isin = 'SE0015812219' THEN 'SwedenCare AB'
        ELSE 'Unknown Company'
    END as company_name,
    SUM(CASE 
        WHEN lt.trade_type_id = 1 THEN lt.shares_traded  
        WHEN lt.trade_type_id = 9 THEN -lt.shares_traded 
        ELSE 0 
    END) as shares_held,
    'SEK' as currency_local,
    MAX(lt.trade_date) as last_trade_date,
    1 as is_active
FROM psw_portfolio.log_trades lt
GROUP BY lt.isin  -- Group by ISIN only
HAVING shares_held > 0
ORDER BY shares_held DESC;

-- Verify results
SELECT 
    isin,
    ticker,
    company_name,
    shares_held,
    last_trade_date
FROM psw_portfolio.portfolio;