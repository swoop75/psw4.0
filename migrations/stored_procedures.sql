-- PSW 4.0 Stored Procedures for Common Operations
-- Optimized procedures for frequent portfolio operations

DELIMITER //

-- ============================================================================
-- 1. UPDATE PORTFOLIO PRICES
-- ============================================================================
-- Procedure to update portfolio with latest market prices and calculate values
CREATE PROCEDURE IF NOT EXISTS psw_portfolio.sp_update_portfolio_prices()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_isin CHAR(20);
    DECLARE v_shares DECIMAL(12,4);
    DECLARE v_currency VARCHAR(3);
    DECLARE v_latest_price DECIMAL(15,4);
    DECLARE v_fx_rate DECIMAL(10,6);
    
    -- Cursor for active positions
    DECLARE portfolio_cursor CURSOR FOR
        SELECT isin, shares_held, currency_local
        FROM psw_portfolio.portfolio
        WHERE is_active = 1 AND shares_held > 0;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN portfolio_cursor;
    
    portfolio_loop: LOOP
        FETCH portfolio_cursor INTO v_isin, v_shares, v_currency;
        IF done THEN
            LEAVE portfolio_loop;
        END IF;
        
        -- Get latest price (global first, then nordic)
        SELECT COALESCE(glp.price, nlp.price) INTO v_latest_price
        FROM psw_portfolio.portfolio p
        LEFT JOIN psw_marketdata.global_latest_prices glp ON p.isin = glp.isin
        LEFT JOIN psw_marketdata.nordic_latest_prices nlp ON p.ticker = nlp.ticker
        WHERE p.isin = v_isin
        LIMIT 1;
        
        -- Get FX rate if needed
        IF v_currency != 'SEK' THEN
            SELECT rate INTO v_fx_rate
            FROM psw_marketdata.fx_rates_freecurrency
            WHERE from_currency = v_currency AND to_currency = 'SEK'
            ORDER BY last_updated DESC
            LIMIT 1;
        ELSE
            SET v_fx_rate = 1.0;
        END IF;
        
        -- Update portfolio record
        UPDATE psw_portfolio.portfolio
        SET 
            latest_price_local = v_latest_price,
            latest_price_sek = CASE 
                WHEN v_latest_price IS NOT NULL AND v_fx_rate IS NOT NULL THEN v_latest_price * v_fx_rate
                ELSE NULL
            END,
            fx_rate_used = v_fx_rate,
            current_value_local = CASE 
                WHEN v_latest_price IS NOT NULL THEN v_shares * v_latest_price
                ELSE current_value_local
            END,
            current_value_sek = CASE 
                WHEN v_latest_price IS NOT NULL AND v_fx_rate IS NOT NULL THEN v_shares * v_latest_price * v_fx_rate
                ELSE current_value_sek
            END,
            unrealized_gain_loss_sek = CASE 
                WHEN v_latest_price IS NOT NULL AND v_fx_rate IS NOT NULL AND total_cost_sek IS NOT NULL THEN
                    (v_shares * v_latest_price * v_fx_rate) - total_cost_sek
                ELSE unrealized_gain_loss_sek
            END,
            unrealized_gain_loss_percent = CASE 
                WHEN v_latest_price IS NOT NULL AND v_fx_rate IS NOT NULL AND total_cost_sek > 0 THEN
                    (((v_shares * v_latest_price * v_fx_rate) - total_cost_sek) / total_cost_sek) * 100
                ELSE unrealized_gain_loss_percent
            END,
            last_updated_price = NOW(),
            updated_at = NOW()
        WHERE isin = v_isin;
        
    END LOOP;
    
    CLOSE portfolio_cursor;
    
    -- Update portfolio weights
    CALL psw_portfolio.sp_update_portfolio_weights();
    
END//

-- ============================================================================
-- 2. UPDATE PORTFOLIO WEIGHTS
-- ============================================================================
-- Calculate and update portfolio weight percentages
CREATE PROCEDURE IF NOT EXISTS psw_portfolio.sp_update_portfolio_weights()
BEGIN
    DECLARE total_portfolio_value DECIMAL(15,4);
    
    -- Get total portfolio value
    SELECT SUM(COALESCE(current_value_sek, 0)) INTO total_portfolio_value
    FROM psw_portfolio.portfolio
    WHERE is_active = 1 AND shares_held > 0;
    
    -- Update weights
    UPDATE psw_portfolio.portfolio
    SET 
        portfolio_weight_percent = CASE 
            WHEN total_portfolio_value > 0 AND current_value_sek > 0 THEN
                (current_value_sek / total_portfolio_value) * 100
            ELSE 0
        END,
        updated_at = NOW()
    WHERE is_active = 1;
    
END//

-- ============================================================================
-- 3. REBUILD PORTFOLIO FROM TRADES
-- ============================================================================
-- Procedure to recalculate portfolio positions from trade logs
CREATE PROCEDURE IF NOT EXISTS psw_portfolio.sp_rebuild_portfolio_from_trades()
BEGIN
    -- Clear existing portfolio data
    TRUNCATE TABLE psw_portfolio.portfolio;
    
    -- Rebuild from trade logs
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
        COALESCE(ml.name, CONCAT(lt.ticker, ' Company')) as company_name,
        SUM(CASE 
            WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN', 'RIGHTS_ISSUE', 'BONUS_ISSUE') 
            THEN lt.shares_traded  
            WHEN tt.type_code IN ('SELL', 'TRANSFER_OUT') 
            THEN -lt.shares_traded 
            ELSE 0 
        END) as shares_held,
        
        -- Weighted average cost (buy transactions only)
        CASE 
            WHEN SUM(CASE 
                WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN', 'RIGHTS_ISSUE', 'BONUS_ISSUE') 
                THEN lt.shares_traded 
                ELSE 0 
            END) > 0 THEN
                SUM(CASE 
                    WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN', 'RIGHTS_ISSUE', 'BONUS_ISSUE') 
                    THEN lt.total_amount_sek 
                    ELSE 0 
                END) / SUM(CASE 
                    WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN', 'RIGHTS_ISSUE', 'BONUS_ISSUE') 
                    THEN lt.shares_traded 
                    ELSE 0 
                END)
            ELSE 0
        END as average_cost_price_sek,
        
        -- Net cost basis
        SUM(CASE 
            WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN', 'RIGHTS_ISSUE', 'BONUS_ISSUE') 
            THEN lt.total_amount_sek 
            WHEN tt.type_code IN ('SELL', 'TRANSFER_OUT') 
            THEN -lt.total_amount_sek 
            ELSE 0 
        END) as total_cost_sek,
        
        lt.currency_local,
        MAX(lt.trade_date) as last_trade_date,
        1 as is_active
        
    FROM psw_portfolio.log_trades lt
    LEFT JOIN psw_foundation.masterlist ml ON lt.isin = ml.isin
    LEFT JOIN psw_foundation.trade_types tt ON lt.trade_type_id = tt.trade_type_id
    WHERE lt.isin IS NOT NULL 
        AND lt.ticker IS NOT NULL
        AND lt.shares_traded > 0
    GROUP BY lt.isin, lt.ticker, lt.currency_local, ml.name
    HAVING shares_held > 0
    ORDER BY shares_held DESC;
    
    -- Update with current prices
    CALL psw_portfolio.sp_update_portfolio_prices();
    
END//

-- ============================================================================
-- 4. GET PORTFOLIO SUMMARY
-- ============================================================================
-- Get comprehensive portfolio summary for dashboard
CREATE PROCEDURE IF NOT EXISTS psw_portfolio.sp_get_portfolio_summary()
BEGIN
    SELECT 
        COUNT(*) as total_positions,
        SUM(shares_held) as total_shares,
        SUM(COALESCE(current_value_sek, 0)) as total_value_sek,
        SUM(COALESCE(total_cost_sek, 0)) as total_cost_sek,
        SUM(COALESCE(unrealized_gain_loss_sek, 0)) as total_unrealized_pnl_sek,
        
        CASE 
            WHEN SUM(COALESCE(total_cost_sek, 0)) > 0 THEN
                (SUM(COALESCE(current_value_sek, 0)) - SUM(COALESCE(total_cost_sek, 0))) / SUM(COALESCE(total_cost_sek, 0)) * 100
            ELSE 0
        END as total_return_percent,
        
        COUNT(DISTINCT COALESCE(ni.sectorID, gi.sectorId)) as sectors_count,
        COUNT(DISTINCT ml.country) as countries_count,
        COUNT(DISTINCT currency_local) as currencies_count,
        
        MAX(last_updated_price) as last_price_update,
        NOW() as summary_generated_at
        
    FROM psw_portfolio.portfolio p
    LEFT JOIN psw_foundation.masterlist ml ON p.isin = ml.isin
    LEFT JOIN psw_marketdata.nordic_instruments ni ON p.isin = ni.isin
    LEFT JOIN psw_marketdata.global_instruments gi ON p.isin = gi.isin
    WHERE p.is_active = 1 AND p.shares_held > 0;
END//

-- ============================================================================
-- 5. GET DIVIDEND PROJECTIONS
-- ============================================================================
-- Calculate dividend projections based on recent payments
CREATE PROCEDURE IF NOT EXISTS psw_portfolio.sp_get_dividend_projections()
BEGIN
    SELECT 
        p.isin,
        p.ticker,
        p.company_name,
        p.shares_held,
        
        -- Recent dividend stats (last 12 months)
        COALESCE(div_stats.payments_count, 0) as recent_payments,
        COALESCE(div_stats.avg_dividend_per_share, 0) as avg_dividend_per_share,
        COALESCE(div_stats.total_received, 0) as total_received_12m,
        
        -- Projections
        CASE 
            WHEN div_stats.payments_count > 0 THEN
                p.shares_held * div_stats.avg_dividend_per_share * div_stats.payments_count
            ELSE 0
        END as projected_annual_dividend,
        
        CASE 
            WHEN p.current_value_sek > 0 AND div_stats.avg_dividend_per_share > 0 THEN
                (p.shares_held * div_stats.avg_dividend_per_share * COALESCE(div_stats.payments_count, 1)) / p.current_value_sek * 100
            ELSE 0
        END as projected_yield_percent
        
    FROM psw_portfolio.portfolio p
    LEFT JOIN (
        SELECT 
            ld.isin,
            COUNT(*) as payments_count,
            AVG(ld.net_dividend_sek / ld.shares_held_at_ex_date) as avg_dividend_per_share,
            SUM(ld.net_dividend_sek) as total_received
        FROM psw_portfolio.log_dividends ld
        WHERE ld.payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            AND ld.shares_held_at_ex_date > 0
        GROUP BY ld.isin
    ) div_stats ON p.isin = div_stats.isin
    WHERE p.is_active = 1 AND p.shares_held > 0
    ORDER BY projected_annual_dividend DESC;
END//

DELIMITER ;

-- Show created procedures
SHOW PROCEDURE STATUS WHERE Db = 'psw_portfolio';