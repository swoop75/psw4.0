# Broker Fee Fields Migration for log_dividends Table

## Overview
This migration adds missing broker fee fields to the `log_dividends` table to enable proper fee percentage calculations in the dividend log system.

## Files Updated
1. `migrations/add_broker_fee_fields_to_dividends.sql` - Database migration script
2. `import_dividends.php` - Updated to include broker fee fields in INSERT statements
3. `run_migration.php` - PHP script to execute migration (requires MySQL PDO driver)

## Required Database Changes

### 1. Add Missing Columns to log_dividends Table
Execute the following SQL on the `psw_portfolio` database:

```sql
USE psw_portfolio;

ALTER TABLE log_dividends 
ADD COLUMN IF NOT EXISTS broker_fee_local DECIMAL(15,4) DEFAULT 0 COMMENT 'Broker fee in local currency',
ADD COLUMN IF NOT EXISTS broker_fee_sek DECIMAL(15,4) DEFAULT 0 COMMENT 'Broker fee in SEK',
ADD COLUMN IF NOT EXISTS broker_fee_percent DECIMAL(8,4) DEFAULT 0 COMMENT 'Broker fee as percentage of dividend amount';
```

### 2. Verify Migration
After running the migration, verify the columns were added:

```sql
DESCRIBE log_dividends;
```

You should see the new columns:
- `broker_fee_local` DECIMAL(15,4) DEFAULT 0
- `broker_fee_sek` DECIMAL(15,4) DEFAULT 0  
- `broker_fee_percent` DECIMAL(8,4) DEFAULT 0

## Code Changes Made

### import_dividends.php
- Updated INSERT statement to include broker fee fields
- Added automatic broker fee percentage calculation: `(broker_fee_sek / dividend_amount_sek) * 100`
- Added broker fee values to execute parameters with default values of 0

### DividendLogsController.php (Previously Fixed)
- Already includes broker fee fields in SELECT queries
- Already processes broker fee data in results

### dividend-logs-redesign.php Template (Previously Fixed)  
- Already displays Fee % column
- Already handles broker_fee_percent field display

## How to Execute Migration

### Option 1: Via phpMyAdmin or MySQL Workbench
1. Connect to the database server (100.117.171.98)
2. Select `psw_portfolio` database
3. Run the ALTER TABLE statements above

### Option 2: Via Command Line (if MySQL client is available)
```bash
mysql -u swoop -p'QQ1122ww_1975!#' -h 100.117.171.98 psw_portfolio < migrations/add_broker_fee_fields_to_dividends.sql
```

### Option 3: Via PHP Script (requires MySQL PDO driver installation)
```bash
php run_migration.php
```

## Testing After Migration
1. Import a dividend CSV file with broker fee data
2. Check that Fee % column displays calculated percentages
3. Verify that broker fee fields are properly stored in the database

## Expected Behavior After Migration
- Dividend import will properly store broker fee amounts and percentages
- Fee % column in dividend logs will display calculated broker fee percentages
- System will handle both existing records (with 0 fees) and new records with actual fee data

## Migration Status
✓ Database migration script created  
✓ import_dividends.php updated to handle broker fee fields  
✓ Code ready - awaiting database migration execution  
⏳ **PENDING**: Database migration needs to be executed manually

## Next Steps
1. Execute the database migration using one of the methods above
2. Test dividend import functionality with broker fee data
3. Verify Fee % calculations work correctly in the dividend logs interface