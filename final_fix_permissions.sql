-- Final fix for MySQL permissions
-- Run these commands as root in MySQL

-- 1. Flush all privileges to clear any caching
FLUSH PRIVILEGES;

-- 2. Drop and recreate the user completely
DROP USER IF EXISTS 'psw_user'@'poseidon.tail89f731.ts.net';
DROP USER IF EXISTS 'psw_user'@'%';

-- 3. Create user with specific hostname first
CREATE USER 'psw_user'@'poseidon.tail89f731.ts.net' IDENTIFIED BY 'PSW4_SecurePass_2024!';

-- 4. Grant all privileges explicitly
GRANT ALL PRIVILEGES ON psw_foundation.* TO 'psw_user'@'poseidon.tail89f731.ts.net';
GRANT ALL PRIVILEGES ON psw_marketdata.* TO 'psw_user'@'poseidon.tail89f731.ts.net';
GRANT ALL PRIVILEGES ON psw_portfolio.* TO 'psw_user'@'poseidon.tail89f731.ts.net';

-- 5. Also create wildcard user as backup
CREATE USER 'psw_user'@'%' IDENTIFIED BY 'PSW4_SecurePass_2024!';
GRANT ALL PRIVILEGES ON psw_foundation.* TO 'psw_user'@'%';
GRANT ALL PRIVILEGES ON psw_marketdata.* TO 'psw_user'@'%';
GRANT ALL PRIVILEGES ON psw_portfolio.* TO 'psw_user'@'%';

-- 6. Flush privileges again
FLUSH PRIVILEGES;

-- 7. Verify the setup
SELECT User, Host FROM mysql.user WHERE User = 'psw_user';
SHOW GRANTS FOR 'psw_user'@'poseidon.tail89f731.ts.net';
SHOW GRANTS FOR 'psw_user'@'%';