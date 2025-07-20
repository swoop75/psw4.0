-- PSW 4.0 MySQL User Setup
-- Run these commands on your MySQL server at 100.117.171.98

-- Create dedicated user for PSW application
CREATE USER 'psw_user'@'%' IDENTIFIED BY 'PSW4_SecurePass_2024!';

-- Grant privileges for PSW databases
GRANT ALL PRIVILEGES ON psw_foundation.* TO 'psw_user'@'%';
GRANT ALL PRIVILEGES ON psw_marketdata.* TO 'psw_user'@'%';
GRANT ALL PRIVILEGES ON psw_portfolio.* TO 'psw_user'@'%';

-- Apply the changes
FLUSH PRIVILEGES;

-- Verify the user was created
SELECT User, Host FROM mysql.user WHERE User = 'psw_user';

-- Show grants for the new user
SHOW GRANTS FOR 'psw_user'@'%';