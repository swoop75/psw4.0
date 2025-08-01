-- ===================================================================
-- Populate Portfolio Table with Calculated Data from Trade Logs
-- ===================================================================

-- First, let's see what data we have in log_trades
SELECT 'Checking trade logs data...' as status;
SELECT 
    isin, 
    ticker,
    COUNT(*) as trade_count,
    SUM(shares_traded) as total_shares,
    AVG(price_per_share_sek) as avg_price_sek,
    SUM(total_amount_sek) as total_cost_sek,
    MAX(trade_date) as last_trade_date
FROM psw_portfolio.log_trades 
WHERE isin IN (SELECT DISTINCT isin FROM psw_portfolio.portfolio)
GROUP BY isin, ticker;

-- Update portfolio table with calculated values from trade logs
SELECT 'Updating portfolio with trade data...' as status;

UPDATE psw_portfolio.portfolio p
INNER JOIN (
    SELECT 
        isin,
        AVG(price_per_share_sek) as avg_cost_price_sek,
        SUM(total_amount_sek) as total_cost_sek,
        SUM(shares_traded) as total_shares_from_trades,
        MAX(trade_date) as last_trade_date
    FROM psw_portfolio.log_trades
    WHERE isin IN (SELECT DISTINCT isin FROM psw_portfolio.portfolio)
    GROUP BY isin
) trades ON p.isin = trades.isin
SET 
    p.average_cost_price_sek = trades.avg_cost_price_sek,
    p.total_cost_sek = trades.total_cost_sek,
    p.last_trade_date = trades.last_trade_date,
    p.updated_at = CURRENT_TIMESTAMP;

-- Calculate current values (using cost basis for now since no live prices)
SELECT 'Calculating current values...' as status;

UPDATE psw_portfolio.portfolio 
SET 
    current_value_local = COALESCE(average_cost_price_sek * shares_held, 0),
    current_value_sek = COALESCE(average_cost_price_sek * shares_held, 0),
    latest_price_local = average_cost_price_sek,
    latest_price_sek = average_cost_price_sek,
    unrealized_gain_loss_sek = 0, -- No gain/loss if using cost basis as current price
    unrealized_gain_loss_percent = 0,
    updated_at = CURRENT_TIMESTAMP
WHERE average_cost_price_sek IS NOT NULL;

-- Show updated results
SELECT 'Updated portfolio data:' as status;
SELECT 
    isin,
    ticker,
    company_name,
    shares_held,
    average_cost_price_sek,
    total_cost_sek,
    current_value_sek,
    last_trade_date
FROM psw_portfolio.portfolio
WHERE is_active = 1
ORDER BY current_value_sek DESC;