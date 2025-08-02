-- Manual test for adding the 4 unsupported companies
-- This simulates what the admin_company_management.php would do

USE psw_foundation;

-- First, check if these companies already exist
SELECT 'Checking for existing companies:' as info;
SELECT isin, company_name, ticker, country 
FROM manual_company_data 
WHERE isin IN ('CZ0008019106', 'IE0003290289', 'GB0001990497', 'CA33843T1084');

-- Insert the 4 test companies
INSERT INTO manual_company_data 
(isin, ticker, company_name, country, sector, branch, market_exchange, 
 currency, company_type, dividend_frequency, notes, created_by, created_at) 
VALUES 
-- Company 1: Czech Republic
('CZ0008019106', 'TEST1', 'Test Czech Company', 'Czech Republic', 
 'Technology', 'Software', 'Prague Stock Exchange', 'CZK', 
 'stock', 'annual', 'Added for testing unsupported company workflow', 'test_system', NOW()),

-- Company 2: Ireland  
('IE0003290289', 'TEST2', 'Test Irish Fund', 'Ireland',
 'Financial Services', 'Investment Funds', 'Irish Stock Exchange', 'EUR',
 'closed_end_fund', 'quarterly', 'Added for testing unsupported company workflow', 'test_system', NOW()),

-- Company 3: United Kingdom
('GB0001990497', 'TEST3', 'Test UK Investment Trust', 'United Kingdom',
 'Financial Services', 'Investment Trusts', 'London Stock Exchange', 'GBP',
 'closed_end_fund', 'monthly', 'Added for testing unsupported company workflow', 'test_system', NOW()),

-- Company 4: Canada
('CA33843T1084', 'TEST4', 'Test Canadian Corporation', 'Canada',
 'Energy', 'Oil & Gas', 'Toronto Stock Exchange', 'CAD',
 'stock', 'quarterly', 'Added for testing unsupported company workflow', 'test_system', NOW())

ON DUPLICATE KEY UPDATE 
    company_name = VALUES(company_name),
    updated_at = NOW();

-- Verify the companies were added
SELECT 'Companies added successfully:' as result;
SELECT 
    manual_id,
    isin, 
    ticker, 
    company_name, 
    country, 
    currency,
    company_type,
    dividend_frequency,
    sector,
    branch,
    created_at
FROM manual_company_data 
WHERE isin IN ('CZ0008019106', 'IE0003290289', 'GB0001990497', 'CA33843T1084')
ORDER BY created_at DESC;

-- Test that these companies can be found in unified view
SELECT 'Testing unified view integration:' as test;
SELECT 
    u.isin,
    u.company_name,
    u.ticker,
    u.country,
    u.currency,
    u.data_source
FROM psw_foundation.unified_company_view u
WHERE u.isin IN ('CZ0008019106', 'IE0003290289', 'GB0001990497', 'CA33843T1084');

-- Show the total count of manual companies
SELECT 'Total manual companies in system:' as summary;
SELECT COUNT(*) as total_manual_companies FROM manual_company_data;

-- Show data sources breakdown
SELECT 'Company data sources summary:' as summary;
SELECT data_source, COUNT(*) as count 
FROM psw_foundation.unified_company_view 
GROUP BY data_source 
ORDER BY count DESC;