import os
from dotenv import load_dotenv
import requests
import pymysql
import logging
from datetime import datetime

# Load environment variables from .env file located at project root (two levels up from this script)
load_dotenv(dotenv_path='../../.env')

# Get configuration from environment variables
API_KEY = os.getenv('BORSDATA_API_KEY')
if not API_KEY:
    raise ValueError("BORSDATA_API_KEY not set in environment")

BASE_URL = "https://apiservice.borsdata.se/v1"

db_config = {
    'user': os.getenv('DB_USERNAME'),
    'password': os.getenv('DB_PASSWORD'),
    'host': os.getenv('DB_HOST'),
    'port': int(os.getenv('DB_PORT', 3306)),
    'database': os.getenv('DB_MARKETDATA'),  # Using marketdata database for instruments
    'charset': 'utf8mb4',
    'cursorclass': pymysql.cursors.DictCursor
}

# === Setup Logging ===
log_dir = os.getenv('LOG_PATH', "../../storage/logs")
os.makedirs(log_dir, exist_ok=True)
log_filename = os.path.join(log_dir, "nordic_instruments.log")

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

def fetch_instruments():
    url = f"{BASE_URL}/instruments?authKey={API_KEY}"
    logging.info("Fetching Nordic instruments from Börsdata API...")
    logging.info(f"API endpoint: {BASE_URL}/instruments")
    try:
        response = requests.get(url)
        response.raise_for_status()
        data = response.json()
        
        instruments = data.get("instruments")
        if instruments is None:
            logging.error("No 'instruments' key found in API response.")
            logging.debug(f"Full API response: {data}")
            return []
        
        logging.info(f"Successfully fetched {len(instruments)} instruments from API")
        return instruments
        
    except requests.RequestException as e:
        logging.error(f"HTTP request failed: {e}")
        return []
    except Exception as e:
        logging.exception(f"Error fetching instruments: {e}")
        return []

def save_to_db(instruments):
    inserted = 0
    errors = 0
    logging.info("Connecting to MySQL database...")
    try:
        conn = pymysql.connect(**db_config)
        logging.info(f"Connected to database: {db_config['database']}")
        
        with conn.cursor() as cursor:
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS nordic_instruments (
                    insId INT PRIMARY KEY,
                    name VARCHAR(255),
                    ticker VARCHAR(50),
                    isin VARCHAR(50),
                    sectorId INT
                )
            """)

            logging.info(f"About to process {len(instruments)} Nordic instruments")
            
            # Process in batches for better performance
            batch_size = 100
            batch_data = []

            for i, item in enumerate(instruments):
                if i % 500 == 0:  # Log progress every 500 items
                    logging.info(f"Processing Nordic instrument {i+1}/{len(instruments)}")
                
                # Check required keys
                if not all(k in item for k in ("insId", "name", "ticker", "isin", "sectorId")):
                    logging.warning(f"Missing keys in instrument item: {item}")
                    errors += 1
                    continue

                # Prepare data for batch insert
                data_tuple = (item["insId"], item["name"], item["ticker"], item["isin"], item["sectorId"])
                batch_data.append(data_tuple)
                
                # Process batch when it reaches batch_size or is the last item
                if len(batch_data) >= batch_size or i == len(instruments) - 1:
                    try:
                        # Execute batch insert
                        cursor.executemany("""
                            INSERT INTO nordic_instruments (insId, name, ticker, isin, sectorId)
                            VALUES (%s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                                name=VALUES(name),
                                ticker=VALUES(ticker),
                                isin=VALUES(isin),
                                sectorId=VALUES(sectorId)
                        """, batch_data)
                        
                        inserted += len(batch_data)
                        
                        # Commit every 500 records for safety
                        if inserted % 500 == 0 or i == len(instruments) - 1:
                            conn.commit()
                            logging.info(f"Committed {inserted} Nordic instruments to database")
                        
                        batch_data = []  # Clear batch for next set
                        
                    except Exception as e:
                        logging.error(f"Failed to insert Nordic instrument batch at item {i}: {e}")
                        errors += len(batch_data)
                        batch_data = []  # Clear failed batch
            
            # Final commit
            conn.commit()
        conn.close()
        logging.info(f"Inserted/updated {inserted} instruments into the database.")
        if errors:
            logging.warning(f"{errors} instruments failed to insert.")
    except Exception as e:
        logging.exception(f"Database error: {e}")
        raise

def main():
    start = datetime.now()
    # Console delimiter (clean display)
    print("="*50)
    print("NORDIC INSTRUMENTS SCRIPT STARTED")
    print("="*50)
    # File logging (with timestamps)
    logging.info("="*50)
    logging.info("NORDIC INSTRUMENTS SCRIPT STARTED")
    logging.info("="*50)
    
    instruments = fetch_instruments()
    
    if not instruments:
        logging.warning("No Nordic instruments found or fetched.")
        return
    
    try:
        save_to_db(instruments)
    except Exception as e:
        logging.exception(f"Error saving to database: {e}")
        return
    
    duration = datetime.now() - start
    logging.info(f"Script finished. Duration: {duration}.")

if __name__ == "__main__":
    main()
