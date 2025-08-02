-- Create the correct Tele2 record with proper values
-- Since we know updates work, let's create the correct record

USE psw_portfolio;

-- First, ensure the record is deleted
DELETE FROM portfolio WHERE ticker = 'TEL2 B' OR isin = 'SE0005190238';

-- Insert the correct record with proper calculations
INSERT INTO portfolio (
    isin, 
    ticker, 
    company_name, 
    shares_held, 
    average_cost_price_sek,
    total_cost_sek,
    currency_local, 
    market,
    sector,
    country,
    last_trade_date,
    last_updated_price,
    latest_price_local,
    latest_price_sek,
    fx_rate_used,
    current_value_local,
    current_value_sek,
    unrealized_gain_loss_sek,
    unrealized_gain_loss_percent,
    portfolio_weight_percent,
    is_active,
    created_at,
    updated_at,
    strategy_group_id
) VALUES (
    'SE0005190238',                    -- isin
    'TEL2 B',                         -- ticker  
    'Tele2 AB (B)',                   -- company_name
    248.0000,                         -- shares_held (133+70+25+20)
    68.8589,                          -- average_cost_price_sek (17077.40/248)
    17077.4000,                       -- total_cost_sek (9482.90+4889.50+1325.00+1380.00)
    'SEK',                           -- currency_local
    NULL,                            -- market
    NULL,                            -- sector  
    NULL,                            -- country
    '2016-12-02',                    -- last_trade_date (most recent trade)
    NULL,                            -- last_updated_price
    64.72,                           -- latest_price_local (using current market estimate)
    64.72,                           -- latest_price_sek
    NULL,                            -- fx_rate_used
    248.0000 * 64.72,                -- current_value_local (16050.56)
    248.0000 * 64.72,                -- current_value_sek (16050.56) 
    (248.0000 * 64.72) - 17077.4000, -- unrealized_gain_loss_sek (-1026.84)
    (((248.0000 * 64.72) - 17077.4000) / 17077.4000) * 100, -- unrealized_gain_loss_percent (-6.01%)
    NULL,                            -- portfolio_weight_percent
    1,                               -- is_active
    NOW(),                           -- created_at
    NOW(),                           -- updated_at
    NULL                             -- strategy_group_id
);

-- Verify the correct record is created
SELECT 'CORRECTED TELE2 RECORD:' as status;
SELECT 
    portfolio_id,
    ticker,
    company_name,
    shares_held,
    ROUND(average_cost_price_sek, 4) as avg_cost,
    ROUND(total_cost_sek, 2) as total_cost,
    ROUND(current_value_sek, 2) as current_value,
    ROUND(unrealized_gain_loss_sek, 2) as unrealized_pnl,
    ROUND(unrealized_gain_loss_percent, 2) as unrealized_pnl_pct,
    last_trade_date,
    updated_at
FROM portfolio 
WHERE ticker = 'TEL2 B';

-- Show the math breakdown
SELECT 'CALCULATION BREAKDOWN:' as info;
SELECT 
    'Trade 1: 2016-08-17 BUY' as description,
    133 as shares,
    9482.90 as cost_sek
UNION ALL
SELECT 
    'Trade 2: 2016-09-13 BUY' as description,
    70 as shares,
    4889.50 as cost_sek
UNION ALL
SELECT 
    'Trade 3: 2016-11-29 RIGHTS_ISSUE' as description,
    25 as shares,
    1325.00 as cost_sek
UNION ALL
SELECT 
    'Trade 4: 2016-12-02 BUY' as description,
    20 as shares,
    1380.00 as cost_sek
UNION ALL
SELECT 
    'TOTAL (Portfolio should match)' as description,
    248 as shares,
    17077.40 as cost_sek;

-- Show comparison
SELECT 'COMPARISON - Old vs New:' as comparison;
SELECT 
    'OLD (Incorrect)' as version,
    178 as shares,
    15697.40 as total_cost,
    88.18 as avg_cost
UNION ALL
SELECT 
    'NEW (Correct)' as version,
    248 as shares,
    17077.40 as total_cost,
    68.86 as avg_cost;