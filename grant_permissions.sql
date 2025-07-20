-- Grant database permissions for psw_user
-- Run these commands on your MySQL server

-- Grant permissions for your specific hostname
GRANT ALL PRIVILEGES ON psw_foundation.* TO 'psw_user'@'poseidon.tail89f731.ts.net';
GRANT ALL PRIVILEGES ON psw_marketdata.* TO 'psw_user'@'poseidon.tail89f731.ts.net';
GRANT ALL PRIVILEGES ON psw_portfolio.* TO 'psw_user'@'poseidon.tail89f731.ts.net';

-- Also grant for wildcard (backup)
GRANT ALL PRIVILEGES ON psw_foundation.* TO 'psw_user'@'%';
GRANT ALL PRIVILEGES ON psw_marketdata.* TO 'psw_user'@'%';
GRANT ALL PRIVILEGES ON psw_portfolio.* TO 'psw_user'@'%';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify permissions
SHOW GRANTS FOR 'psw_user'@'poseidon.tail89f731.ts.net';

-- Test database access
USE psw_foundation;
SHOW TABLES;