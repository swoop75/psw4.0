-- Populate portfolio table with data from trade logs
-- This query aggregates trade data to calculate current holdings

-- First, let's clear any existing data to avoid duplicates
TRUNCATE TABLE psw_portfolio.portfolio;

-- Insert aggregated portfolio data from trade logs
INSERT INTO psw_portfolio.portfolio (
    isin, 
    ticker, 
    company_name, 
    shares_held, 
    average_cost_price_sek, 
    total_cost_sek,
    currency_local, 
    last_trade_date, 
    is_active,
    created_at,
    updated_at
)
SELECT 
    lt.isin,
    lt.ticker,
    COALESCE(ml.name, 'Unknown Company') as company_name,
    SUM(CASE 
        WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN', 'RIGHTS_ISSUE', 'BONUS_ISSUE') 
        THEN COALESCE(lt.shares_traded, 0)
        ELSE -COALESCE(lt.shares_traded, 0)
    END) as shares_held,
    CASE 
        WHEN SUM(CASE 
            WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN', 'RIGHTS_ISSUE', 'BONUS_ISSUE') 
            THEN COALESCE(lt.shares_traded, 0)
            ELSE 0
        END) > 0
        THEN SUM(CASE 
            WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN', 'RIGHTS_ISSUE', 'BONUS_ISSUE') 
            THEN COALESCE(lt.total_amount_sek, 0)
            ELSE 0
        END) / SUM(CASE 
            WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN', 'RIGHTS_ISSUE', 'BONUS_ISSUE') 
            THEN COALESCE(lt.shares_traded, 0)
            ELSE 0
        END)
        ELSE 0
    END as average_cost_price_sek,
    SUM(CASE 
        WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN', 'RIGHTS_ISSUE', 'BONUS_ISSUE') 
        THEN COALESCE(lt.total_amount_sek, 0)
        ELSE -COALESCE(lt.total_amount_sek, 0)
    END) as total_cost_sek,
    lt.currency_local,
    MAX(lt.trade_date) as last_trade_date,
    1 as is_active,
    NOW() as created_at,
    NOW() as updated_at
FROM psw_portfolio.log_trades lt
LEFT JOIN psw_foundation.masterlist ml ON lt.isin COLLATE utf8mb4_unicode_ci = ml.isin COLLATE utf8mb4_unicode_ci
LEFT JOIN psw_foundation.trade_types tt ON lt.trade_type_id = tt.trade_type_id
WHERE lt.isin IS NOT NULL 
    AND lt.ticker IS NOT NULL
    AND lt.shares_traded IS NOT NULL
    AND lt.shares_traded > 0
GROUP BY lt.isin, lt.ticker, ml.name, lt.currency_local
HAVING shares_held > 0
ORDER BY shares_held DESC;

-- Show results
SELECT COUNT(*) as positions_created FROM psw_portfolio.portfolio;

-- Show summary of populated data
SELECT 
    COUNT(*) as total_positions,
    SUM(shares_held) as total_shares,
    SUM(total_cost_sek) as total_cost_basis,
    COUNT(DISTINCT currency_local) as currencies,
    MIN(last_trade_date) as earliest_trade,
    MAX(last_trade_date) as latest_trade
FROM psw_portfolio.portfolio;