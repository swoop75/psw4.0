-- Additional MySQL user setup for specific hostname
-- Run these commands on your MySQL server

-- Create user specifically for your hostname
CREATE USER 'psw_user'@'poseidon.tail89f731.ts.net' IDENTIFIED BY 'PSW4_SecurePass_2024!';

-- Grant privileges for this specific hostname
GRANT ALL PRIVILEGES ON psw_foundation.* TO 'psw_user'@'poseidon.tail89f731.ts.net';
GRANT ALL PRIVILEGES ON psw_marketdata.* TO 'psw_user'@'poseidon.tail89f731.ts.net';
GRANT ALL PRIVILEGES ON psw_portfolio.* TO 'psw_user'@'poseidon.tail89f731.ts.net';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify both users exist
SELECT User, Host FROM mysql.user WHERE User = 'psw_user';
SHOW GRANTS FOR 'psw_user'@'%';
SHOW GRANTS FOR 'psw_user'@'poseidon.tail89f731.ts.net';