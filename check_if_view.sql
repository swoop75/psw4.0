-- Check if 'portfolio' is actually a VIEW not a TABLE
USE psw_portfolio;

-- Check table type
SELECT 
    TABLE_NAME,
    TABLE_TYPE,
    ENGINE,
    TABLE_COMMENT
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'psw_portfolio' 
AND TABLE_NAME = 'portfolio';

-- If it's a view, show the view definition
SELECT 
    VIEW_DEFINITION 
FROM information_schema.VIEWS 
WHERE TABLE_SCHEMA = 'psw_portfolio' 
AND TABLE_NAME = 'portfolio';

-- Show all portfolio-related tables/views
SELECT 
    TABLE_NAME,
    TABLE_TYPE,
    ENGINE
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'psw_portfolio' 
AND TABLE_NAME LIKE '%portfolio%'
ORDER BY TABLE_TYPE, TABLE_NAME;