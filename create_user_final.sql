-- Final MySQL user creation for PSW 4.0
-- Run these commands on your MySQL server

-- First check what users exist
SELECT User, Host FROM mysql.user WHERE User = 'psw_user';

-- Drop existing users if they exist (to start fresh)
DROP USER IF EXISTS 'psw_user'@'%';
DROP USER IF EXISTS 'psw_user'@'poseidon.tail89f731.ts.net';

-- Create user for both wildcard and specific hostname
CREATE USER 'psw_user'@'%' IDENTIFIED BY 'PSW4_SecurePass_2024!';
CREATE USER 'psw_user'@'poseidon.tail89f731.ts.net' IDENTIFIED BY 'PSW4_SecurePass_2024!';

-- Grant privileges for wildcard user
GRANT ALL PRIVILEGES ON psw_foundation.* TO 'psw_user'@'%';
GRANT ALL PRIVILEGES ON psw_marketdata.* TO 'psw_user'@'%';
GRANT ALL PRIVILEGES ON psw_portfolio.* TO 'psw_user'@'%';

-- Grant privileges for hostname-specific user
GRANT ALL PRIVILEGES ON psw_foundation.* TO 'psw_user'@'poseidon.tail89f731.ts.net';
GRANT ALL PRIVILEGES ON psw_marketdata.* TO 'psw_user'@'poseidon.tail89f731.ts.net';
GRANT ALL PRIVILEGES ON psw_portfolio.* TO 'psw_user'@'poseidon.tail89f731.ts.net';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify users were created
SELECT User, Host FROM mysql.user WHERE User = 'psw_user';

-- Test grants
SHOW GRANTS FOR 'psw_user'@'%';
SHOW GRANTS FOR 'psw_user'@'poseidon.tail89f731.ts.net';