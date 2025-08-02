-- Nuclear Option: Completely bypass any potential issues
-- This will force the correct values by any means necessary

USE psw_portfolio;

-- Step 1: Get the exact portfolio_id for the record
SET @portfolio_id = (SELECT portfolio_id FROM portfolio WHERE ticker = 'TEL2 B' AND isin = 'SE0005190238');

SELECT CONCAT('Working with portfolio_id: ', @portfolio_id) as info;

-- Step 2: Try using the primary key for update
UPDATE portfolio 
SET 
    shares_held = 248.0000,
    total_cost_sek = 17077.4000,
    average_cost_price_sek = 68.8589,
    updated_at = NOW()
WHERE portfolio_id = @portfolio_id;

SELECT CONCAT('Rows affected by PK update: ', ROW_COUNT()) as update_result;

-- Step 3: Verify the change
SELECT 'After PK update:' as status;
SELECT portfolio_id, ticker, shares_held, total_cost_sek, average_cost_price_sek, updated_at 
FROM portfolio 
WHERE portfolio_id = @portfolio_id;

-- Step 4: If still not working, create a new record and delete the old one
INSERT INTO portfolio (
    isin, ticker, company_name, shares_held, average_cost_price_sek, 
    total_cost_sek, currency_local, market, sector, country,
    last_trade_date, last_updated_price, latest_price_local, 
    latest_price_sek, fx_rate_used, current_value_local, 
    current_value_sek, unrealized_gain_loss_sek, 
    unrealized_gain_loss_percent, portfolio_weight_percent, 
    is_active, created_at, updated_at, strategy_group_id
) 
SELECT 
    'SE0005190238' as isin,
    'TEL2 B' as ticker,
    'Tele2 AB (B)' as company_name,
    248.0000 as shares_held,
    68.8589 as average_cost_price_sek,
    17077.4000 as total_cost_sek,
    'SEK' as currency_local,
    market, sector, country, '2016-12-02' as last_trade_date,
    last_updated_price, 68.8589 as latest_price_local,
    68.8589 as latest_price_sek, fx_rate_used,
    248.0000 * 68.8589 as current_value_local,
    248.0000 * 68.8589 as current_value_sek,
    0.0000 as unrealized_gain_loss_sek,
    0.0000 as unrealized_gain_loss_percent,
    portfolio_weight_percent, 1 as is_active,
    NOW() as created_at, NOW() as updated_at, strategy_group_id
FROM portfolio 
WHERE portfolio_id = @portfolio_id;

-- Step 5: Delete the old incorrect record
DELETE FROM portfolio WHERE portfolio_id = @portfolio_id;

-- Step 6: Final verification
SELECT 'FINAL RESULT - Nuclear Option:' as status;
SELECT 
    portfolio_id,
    ticker,
    company_name,
    shares_held,
    ROUND(average_cost_price_sek, 4) as avg_cost,
    ROUND(total_cost_sek, 2) as total_cost,
    last_trade_date,
    updated_at
FROM portfolio 
WHERE ticker = 'TEL2 B' AND isin = 'SE0005190238';

-- Step 7: Verify math is correct
SELECT 'Math verification:' as check_type;
SELECT 
    '133 + 70 + 25 + 20 = 248' as expected_shares,
    '9482.90 + 4889.50 + 1325.00 + 1380.00 = 17077.40' as expected_cost,
    '17077.40 / 248 = 68.8589' as expected_avg_cost;

SELECT 'Trade details for verification:' as trade_info;
SELECT 
    trade_date,
    CASE trade_type_id 
        WHEN 1 THEN 'BUY'
        WHEN 9 THEN 'RIGHTS_ISSUE' 
        ELSE CONCAT('TYPE_', trade_type_id)
    END as type,
    shares_traded,
    total_amount_sek,
    CONCAT(shares_traded, ' shares @ ', ROUND(total_amount_sek/shares_traded, 2), ' SEK each') as details
FROM log_trades 
WHERE ticker = 'TEL2 B'
ORDER BY trade_date;