-- Step-by-step permission fix for psw_user
-- Run these commands one by one on your MySQL server as root

-- 1. Check current users and their hosts
SELECT User, Host FROM mysql.user WHERE User = 'psw_user';

-- 2. Check current grants for the user
SHOW GRANTS FOR 'psw_user'@'poseidon.tail89f731.ts.net';

-- 3. Grant permissions to existing user (don't create, just grant)
GRANT ALL PRIVILEGES ON psw_foundation.* TO 'psw_user'@'poseidon.tail89f731.ts.net';
GRANT ALL PRIVILEGES ON psw_marketdata.* TO 'psw_user'@'poseidon.tail89f731.ts.net';
GRANT ALL PRIVILEGES ON psw_portfolio.* TO 'psw_user'@'poseidon.tail89f731.ts.net';

-- 4. Apply changes
FLUSH PRIVILEGES;

-- 5. Verify the grants were applied
SHOW GRANTS FOR 'psw_user'@'poseidon.tail89f731.ts.net';