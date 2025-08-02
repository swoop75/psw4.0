-- Populate portfolio with real trade data
TRUNCATE TABLE psw_portfolio.portfolio;

-- Insert aggregated positions from trade data
INSERT INTO psw_portfolio.portfolio (
    isin, 
    ticker, 
    company_name, 
    shares_held, 
    average_cost_price_sek,
    total_cost_sek,
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
        WHEN lt.trade_type_id = 1 THEN lt.shares_traded  -- BUY
        WHEN lt.trade_type_id = 9 THEN lt.shares_traded  -- RIGHTS_ISSUE (adds shares)
        ELSE 0 
    END) as shares_held,
    AVG(lt.price_per_share_sek) as average_cost_price_sek,
    SUM(CASE 
        WHEN lt.trade_type_id = 1 THEN lt.total_amount_sek  -- BUY (cost increases)
        WHEN lt.trade_type_id = 9 THEN lt.total_amount_sek  -- RIGHTS_ISSUE (cost increases)
        ELSE 0 
    END) as total_cost_sek,
    lt.currency_local,
    MAX(lt.trade_date) as last_trade_date,
    1 as is_active
FROM psw_portfolio.log_trades lt
WHERE lt.isin IS NOT NULL 
    AND lt.ticker IS NOT NULL
    AND lt.shares_traded IS NOT NULL
    AND lt.shares_traded > 0
GROUP BY lt.isin, lt.ticker, lt.currency_local
HAVING shares_held > 0
ORDER BY shares_held DESC;

-- Show results
SELECT 'Portfolio positions created:' as result;
SELECT 
    ticker,
    company_name,
    shares_held,
    ROUND(average_cost_price_sek, 2) as avg_cost,
    ROUND(total_cost_sek, 2) as total_cost,
    last_trade_date
FROM psw_portfolio.portfolio 
ORDER BY shares_held DESC;