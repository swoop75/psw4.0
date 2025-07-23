import os
from dotenv import load_dotenv
import mysql.connector
import json
import logging
from datetime import datetime, date
from decimal import Decimal

# Load environment variables from .env file located at project root
load_dotenv(dotenv_path='../../.env')

# Get configuration from environment variables
db_config = {
    'host': os.getenv('DB_HOST'),
    'user': os.getenv('DB_USERNAME'),
    'password': os.getenv('DB_PASSWORD'),
    'port': int(os.getenv('DB_PORT', 3306))
}

# Output paths from environment
documentation_path = os.getenv('DOCUMENTATION_PATH', "../../documentation")
mysql_overview_dir = os.path.join(documentation_path, "MySQL_overview")

# Target databases from environment
target_databases = [
    os.getenv('DB_FOUNDATION', 'psw_foundation'),
    os.getenv('DB_MARKETDATA', 'psw_marketdata'),
    os.getenv('DB_PORTFOLIO', 'psw_portfolio')
]

# === Setup Logging ===
log_dir = os.getenv('LOG_PATH', "../../storage/logs")
os.makedirs(log_dir, exist_ok=True)
log_filename = os.path.join(log_dir, "mysql_db_overview.log")

# Setup logger with both file and console output
logger = logging.getLogger()
logger.setLevel(logging.INFO)

# Clear any existing handlers
logger.handlers.clear()

# File handler
file_handler = logging.FileHandler(log_filename)
file_handler.setLevel(logging.INFO)
file_formatter = logging.Formatter("%(asctime)s %(levelname)s: %(message)s", datefmt="%Y-%m-%d %H:%M:%S")
file_handler.setFormatter(file_formatter)

# Console handler
console_handler = logging.StreamHandler()
console_handler.setLevel(logging.INFO)
console_formatter = logging.Formatter("%(levelname)s: %(message)s")
console_handler.setFormatter(console_formatter)

# Add handlers to logger
logger.addHandler(file_handler)
logger.addHandler(console_handler)

def json_serial(obj):
    """JSON serializer for objects not serializable by default json code"""
    if isinstance(obj, (datetime, date)):
        return obj.isoformat()
    if isinstance(obj, Decimal):
        return str(obj)
    raise TypeError(f"Type {type(obj)} not serializable")

def get_mysql_overview():
    """Generate comprehensive MySQL database overview"""
    all_db_data = []
    conn = None
    cursor = None
    
    try:
        # Validate database configuration
        missing_config = [k for k, v in db_config.items() if not v]
        if missing_config:
            logging.error(f"Missing database configuration: {missing_config}")
            return False
            
        # Connect to MySQL
        logging.info("Connecting to MySQL database...")
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        logging.info("Successfully connected to MySQL database")

        # Get all databases
        logging.info("Fetching database list...")
        cursor.execute("SHOW DATABASES;")
        databases_found = [db[0] for db in cursor.fetchall()]
        logging.info(f"Found {len(databases_found)} databases in total")

        # Filter and process target databases
        target_found = [db for db in databases_found if db in target_databases]
        target_missing = [db for db in target_databases if db not in databases_found]
        
        if target_missing:
            logging.warning(f"Target databases not found: {target_missing}")
        
        logging.info(f"Processing {len(target_found)} target databases: {target_found}")

        for db_name in target_found:
            logging.info(f"Processing database: {db_name}")

            current_db_info = {
                "name": db_name,
                "tables": [],
                "processed_at": datetime.now().isoformat()
            }

            try:
                cursor.execute(f"USE `{db_name}`;")
                logging.info(f"Switched to database: {db_name}")

                # Get all tables in the current database
                cursor.execute("SHOW TABLES;")
                tables = [table[0] for table in cursor.fetchall()]
                logging.info(f"Found {len(tables)} tables in {db_name}")

                if not tables:
                    logging.warning(f"No tables found in {db_name}")
                    all_db_data.append(current_db_info)
                    continue

                for table_name in tables:
                    current_table_info = {
                        "name": table_name,
                        "schema": [],
                        "sample_data": {
                            "columns": [],
                            "rows": []
                        },
                        "row_count": 0
                    }
                    logging.info(f"Processing table: {db_name}.{table_name}")

                    # Get table description (schema)
                    try:
                        cursor.execute(f"DESCRIBE `{table_name}`;")
                        schema_columns = [i[0] for i in cursor.description]
                        for col_info in cursor.fetchall():
                            col_dict = dict(zip(schema_columns, col_info))
                            current_table_info["schema"].append(col_dict)
                        logging.info(f"Schema fetched for {table_name} ({len(current_table_info['schema'])} columns)")
                    except mysql.connector.Error as err:
                        logging.error(f"Failed to fetch schema for {table_name}: {err}")
                        continue

                    # Get row count
                    try:
                        cursor.execute(f"SELECT COUNT(*) FROM `{table_name}`;")
                        row_count = cursor.fetchone()[0]
                        current_table_info["row_count"] = row_count
                        logging.info(f"Row count for {table_name}: {row_count}")
                    except mysql.connector.Error as err:
                        logging.error(f"Failed to count rows for {table_name}: {err}")

                    # Get 10 sample rows
                    try:
                        cursor.execute(f"SELECT * FROM `{table_name}` LIMIT 10;")
                        sample_rows = cursor.fetchall()

                        if cursor.description:
                            current_table_info["sample_data"]["columns"] = [i[0] for i in cursor.description]
                            current_table_info["sample_data"]["rows"] = [list(row) for row in sample_rows]
                        
                        logging.info(f"Sample data fetched for {table_name} ({len(sample_rows)} rows)")

                    except mysql.connector.Error as err:
                        logging.error(f"Failed to fetch sample data for {table_name}: {err}")

                    current_db_info["tables"].append(current_table_info)

            except mysql.connector.Error as err:
                logging.error(f"Error accessing database {db_name}: {err}")
                continue

            all_db_data.append(current_db_info)
            logging.info(f"Completed processing database: {db_name}")

    except mysql.connector.Error as err:
        logging.error(f"MySQL operation failed: {err}")
        return False
    except Exception as e:
        logging.exception(f"Unexpected error occurred: {e}")
        return False
    finally:
        if cursor:
            cursor.close()
            logging.info("Database cursor closed")
        if conn and conn.is_connected():
            conn.close()
            logging.info("Database connection closed")

    # Create output directory and handle old file migration
    try:
        os.makedirs(mysql_overview_dir, exist_ok=True)
        logging.info(f"Ensured output directory exists: {mysql_overview_dir}")
        
        # Check for old file and move it to new location
        old_file_path = r'C:\Users\laoan\Documents\mysql_database_overview.json'
        if os.path.exists(old_file_path):
            import shutil
            # Create timestamped filename for the old file
            old_timestamp = datetime.fromtimestamp(os.path.getmtime(old_file_path)).strftime("%Y%m%d_%H%M%S")
            old_filename = f"mysql_database_overview_{old_timestamp}_migrated.json"
            new_old_file_path = os.path.join(mysql_overview_dir, old_filename)
            
            shutil.move(old_file_path, new_old_file_path)
            logging.info(f"Migrated old file from {old_file_path} to {new_old_file_path}")
        else:
            logging.info("No old file found to migrate")
            
    except Exception as e:
        logging.error(f"Failed to create output directory or migrate old file: {e}")
        return False

    # Write JSON overview file
    try:
        current_timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        json_filename = f"mysql_database_overview_{current_timestamp}.json"
        json_filepath = os.path.join(mysql_overview_dir, json_filename)
        
        overview_data = {
            "generated_at": datetime.now().isoformat(),
            "total_databases": len(all_db_data),
            "total_tables": sum(len(db["tables"]) for db in all_db_data),
            "databases": all_db_data
        }
        
        with open(json_filepath, 'w', encoding='utf-8') as f:
            json.dump(overview_data, f, indent=4, default=json_serial, ensure_ascii=False)
        
        logging.info(f"Successfully wrote database overview to: {json_filepath}")
        
        # Also create a latest copy
        latest_filepath = os.path.join(mysql_overview_dir, "mysql_database_overview_latest.json")
        with open(latest_filepath, 'w', encoding='utf-8') as f:
            json.dump(overview_data, f, indent=4, default=json_serial, ensure_ascii=False)
        
        logging.info(f"Also created latest copy at: {latest_filepath}")
        return True
        
    except IOError as e:
        logging.error(f"Failed to write output file: {e}")
        return False
    except Exception as e:
        logging.exception(f"Error occurred while writing JSON: {e}")
        return False

def main():
    start = datetime.now()
    # Console delimiter (clean display)
    print("="*50)
    print("MYSQL DATABASE OVERVIEW SCRIPT STARTED")
    print("="*50)
    # File logging (with timestamps)
    logging.info("="*50)
    logging.info("MYSQL DATABASE OVERVIEW SCRIPT STARTED")
    logging.info("="*50)
    
    success = get_mysql_overview()
    
    duration = datetime.now() - start
    if success:
        logging.info(f"Script completed successfully. Duration: {duration}.")
    else:
        logging.error(f"Script failed. Duration: {duration}.")

if __name__ == "__main__":
    main()