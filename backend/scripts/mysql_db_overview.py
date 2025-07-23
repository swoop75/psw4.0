import mysql.connector
import json
import os
from datetime import datetime, date
from decimal import Decimal # Import Decimal type

# --- Configuration ---
# Database connection details
DB_CONFIG = {
    'host': '100.117.171.98',
    'user': 'swoop',
    'password': 'QQ1122ww_1975!#',
}

# Output file path
# Ensure this directory exists or create it before running the script.
# On Windows, use double backslashes or forward slashes for paths.
OUTPUT_FILE_PATH = r'C:\Users\laoan\Documents\mysql_database_overview.json'

# Databases to include in the overview. All others will be skipped.
TARGET_DATABASES = [
    'psw_foundation',
    'psw_marketdata',
    'psw_portfolio'
]

# --- Helper function for JSON serialization ---
# This function handles types that are not directly JSON serializable
# like datetime objects or Decimal objects.
def json_serial(obj):
    """JSON serializer for objects not serializable by default json code"""
    if isinstance(obj, (datetime, date)):
        return obj.isoformat()
    if isinstance(obj, Decimal): # Handle Decimal objects
        return str(obj) # Convert Decimal to string to preserve precision
    raise TypeError ("Type %s not serializable" % type(obj))

# --- Main Script Logic ---
def get_mysql_overview():
    all_db_data = []
    conn = None # Initialize conn to None
    cursor = None # Initialize cursor to None

    try:
        # Connect to MySQL
        print("Attempting to connect to MySQL...")
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
        print("Successfully connected to MySQL.")

        # Get all databases
        print("Fetching database list...")
        cursor.execute("SHOW DATABASES;")
        databases_found = [db[0] for db in cursor.fetchall()]
        print(f"Found {len(databases_found)} databases in total.")

        for db_name in databases_found:
            # Filter databases based on TARGET_DATABASES list
            if db_name not in TARGET_DATABASES:
                print(f"Skipping database (not in target list): {db_name}")
                continue

            print(f"\nProcessing target database: {db_name}")

            current_db_info = {
                "name": db_name,
                "tables": []
            }

            try:
                cursor.execute(f"USE `{db_name}`;") # Use backticks for database names that might contain special characters
                print(f"  Switched to database: {db_name}")

                # Get all tables in the current database
                cursor.execute("SHOW TABLES;")
                tables = [table[0] for table in cursor.fetchall()]
                print(f"  Found {len(tables)} tables in {db_name}.")

                if not tables:
                    print(f"  No tables found in {db_name}. Skipping.")
                    continue

                for table_name in tables:
                    current_table_info = {
                        "name": table_name,
                        "schema": [],
                        "sample_data": {
                            "columns": [],
                            "rows": []
                        }
                    }
                    print(f"    Processing table: {table_name}")

                    # Get table description (schema)
                    try:
                        cursor.execute(f"DESCRIBE `{table_name}`;") # Use backticks for table names
                        schema_columns = [i[0] for i in cursor.description] # Get column names for schema output
                        for col_info in cursor.fetchall():
                            col_dict = dict(zip(schema_columns, col_info))
                            current_table_info["schema"].append(col_dict)
                        print(f"      Schema fetched for {table_name}.")
                    except mysql.connector.Error as err:
                        print(f"      Error fetching schema for table {table_name} in {db_name}: {err}")
                        # Continue to next table if schema fetch fails

                    # Get 10 sample rows
                    try:
                        cursor.execute(f"SELECT * FROM `{table_name}` LIMIT 10;")
                        sample_rows = cursor.fetchall()

                        if cursor.description:
                            current_table_info["sample_data"]["columns"] = [i[0] for i in cursor.description]
                            current_table_info["sample_data"]["rows"] = [list(row) for row in sample_rows]
                        else:
                            print(f"      No columns found for sample data in {table_name}.")

                        print(f"      Sample data fetched for {table_name} ({len(sample_rows)} rows).")

                    except mysql.connector.Error as err:
                        print(f"      Error fetching sample data for table {table_name} in {db_name}: {err}")
                        # Continue to next table if sample data fetch fails

                    current_db_info["tables"].append(current_table_info)

            except mysql.connector.Error as err:
                print(f"  Error accessing database {db_name}: {err}")
                # Continue to next database if USE fails

            all_db_data.append(current_db_info)

    except mysql.connector.Error as err:
        print(f"An error occurred during MySQL operation: {err}")
    except Exception as e:
        print(f"An unexpected error occurred: {e}")
    finally:
        if cursor:
            cursor.close()
            print("Cursor closed.")
        if conn and conn.is_connected():
            conn.close()
            print("MySQL connection closed.")

    # Write the collected data to a JSON file
    try:
        # Ensure the directory exists
        output_dir = os.path.dirname(OUTPUT_FILE_PATH)
        if not os.path.exists(output_dir):
            os.makedirs(output_dir)
            print(f"Created directory: {output_dir}")

        with open(OUTPUT_FILE_PATH, 'w', encoding='utf-8') as f:
            json.dump({"databases": all_db_data}, f, indent=4, default=json_serial, ensure_ascii=False)
        print(f"\nSuccessfully wrote database overview to: {OUTPUT_FILE_PATH}")
    except IOError as e:
        print(f"Error writing to file {OUTPUT_FILE_PATH}: {e}")
    except Exception as e:
        print(f"An error occurred while writing JSON: {e}")

if __name__ == "__main__":
    get_mysql_overview()
