-- Direct approach to find unsupported companies
-- Bypass the broken views and query tables directly

-- 1. First, let's see what ISINs you have in your portfolio/trades
SELECT 'ISINs in your portfolio/trades:' as info;
SELECT DISTINCT 
    isin, 
    ticker, 
    'portfolio' as source
FROM psw_portfolio.portfolio 
WHERE isin IS NOT NULL
UNION
SELECT DISTINCT 
    isin, 
    ticker, 
    'trades' as source
FROM psw_portfolio.log_trades 
WHERE isin IS NOT NULL
UNION
SELECT DISTINCT 
    isin, 
    ticker, 
    'dividends' as source
FROM psw_portfolio.log_dividends 
WHERE isin IS NOT NULL
ORDER BY isin;

-- 2. Check which of your ISINs are NOT in Börsdata nordic
SELECT 'ISINs NOT in Börsdata Nordic:' as info;
SELECT DISTINCT p_isin.isin, p_isin.ticker
FROM (
    SELECT DISTINCT isin, ticker FROM psw_portfolio.portfolio WHERE isin IS NOT NULL
    UNION
    SELECT DISTINCT isin, ticker FROM psw_portfolio.log_trades WHERE isin IS NOT NULL
    UNION
    SELECT DISTINCT isin, ticker FROM psw_portfolio.log_dividends WHERE isin IS NOT NULL
) p_isin
LEFT JOIN psw_marketdata.nordic_instruments ni ON p_isin.isin COLLATE utf8mb4_unicode_ci = ni.isin COLLATE utf8mb4_unicode_ci
WHERE ni.isin IS NULL
ORDER BY p_isin.isin;

-- 3. Check which of your ISINs are NOT in Börsdata global
SELECT 'ISINs NOT in Börsdata Global:' as info;
SELECT DISTINCT p_isin.isin, p_isin.ticker
FROM (
    SELECT DISTINCT isin, ticker FROM psw_portfolio.portfolio WHERE isin IS NOT NULL
    UNION
    SELECT DISTINCT isin, ticker FROM psw_portfolio.log_trades WHERE isin IS NOT NULL
    UNION
    SELECT DISTINCT isin, ticker FROM psw_portfolio.log_dividends WHERE isin IS NOT NULL
) p_isin
LEFT JOIN psw_marketdata.global_instruments gi ON p_isin.isin COLLATE utf8mb4_unicode_ci = gi.isin COLLATE utf8mb4_unicode_ci
WHERE gi.isin IS NULL
ORDER BY p_isin.isin;

-- 4. Find ISINs that are in NEITHER Börsdata table (these need manual entry)
SELECT 'ISINs needing manual entry (not in either Börsdata table):' as info;
SELECT DISTINCT 
    p_isin.isin, 
    p_isin.ticker,
    CASE 
        WHEN p_isin.isin LIKE 'AT%' THEN 'Austria'
        WHEN p_isin.isin LIKE 'CA%' THEN 'Canada'  
        WHEN p_isin.isin LIKE 'GB%' THEN 'United Kingdom'
        WHEN p_isin.isin LIKE 'US%' THEN 'United States'
        WHEN p_isin.isin LIKE 'CZ%' THEN 'Czech Republic'
        ELSE 'Unknown Country'
    END as likely_country
FROM (
    SELECT DISTINCT isin, ticker FROM psw_portfolio.portfolio WHERE isin IS NOT NULL
    UNION
    SELECT DISTINCT isin, ticker FROM psw_portfolio.log_trades WHERE isin IS NOT NULL
    UNION
    SELECT DISTINCT isin, ticker FROM psw_portfolio.log_dividends WHERE isin IS NOT NULL
) p_isin
LEFT JOIN psw_marketdata.nordic_instruments ni ON p_isin.isin COLLATE utf8mb4_unicode_ci = ni.isin COLLATE utf8mb4_unicode_ci
LEFT JOIN psw_marketdata.global_instruments gi ON p_isin.isin COLLATE utf8mb4_unicode_ci = gi.isin COLLATE utf8mb4_unicode_ci
WHERE ni.isin IS NULL AND gi.isin IS NULL
ORDER BY likely_country, p_isin.isin;

-- 5. Sample of Canadian companies in Börsdata (to verify coverage)
SELECT 'Sample Canadian companies in Börsdata Global:' as info;
SELECT isin, ticker, name, stockPriceCurrency
FROM psw_marketdata.global_instruments 
WHERE isin LIKE 'CA%' 
LIMIT 10;

-- 6. Sample of UK companies in Börsdata (to verify coverage)  
SELECT 'Sample UK companies in Börsdata Global:' as info;
SELECT isin, ticker, name, stockPriceCurrency
FROM psw_marketdata.global_instruments 
WHERE isin LIKE 'GB%' 
LIMIT 10;