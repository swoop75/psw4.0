import requests
import pymysql
import logging
from datetime import datetime
import os
from dotenv import load_dotenv

# Load environment variables from .env file located at project root
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
    'database': os.getenv('DB_MARKETDATA'),  # Using marketdata database for prices
    'charset': 'utf8mb4',
    'cursorclass': pymysql.cursors.DictCursor
}

# === Setup Logging ===
log_dir = os.getenv('LOG_PATH', "../../storage/logs")
os.makedirs(log_dir, exist_ok=True)
log_filename = os.path.join(log_dir, "global_latest_prices.log")

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

def fetch_latest_prices():
    url = f"{BASE_URL}/instruments/stockprices/global/last?authKey={API_KEY}"
    logging.info("Fetching global latest prices from Börsdata API...")
    logging.info(f"API endpoint: {BASE_URL}/instruments/stockprices/global/last")
    try:
        response = requests.get(url)
        response.raise_for_status()
        data = response.json()
        
        if isinstance(data, dict):
            # Try different possible keys for the price data
            for key in ['stockPricesList', 'stockPrices', 'prices', 'data']:
                if key in data and data[key]:
                    logging.info(f"Found prices under key '{key}' with {len(data[key])} entries.")
                    return data[key]
            logging.warning("No recognized key for prices found in response.")
            logging.info(f"Response keys: {list(data.keys())}")
            return []
        elif isinstance(data, list):
            logging.info(f"Received list of {len(data)} prices.")
            return data
        else:
            logging.error("Unexpected data structure from API.")
            return []
    except requests.RequestException as e:
        logging.error(f"HTTP request failed: {e}")
        return []
    except Exception as e:
        logging.exception(f"Error fetching prices: {e}")
        return []

def save_latest_prices(prices):
    inserted = 0
    errors = 0
    logging.info("Connecting to MySQL database...")
    try:
        conn = pymysql.connect(
            user=db_config["user"],
            password=db_config["password"],
            host=db_config["host"],
            port=db_config["port"],
            database=db_config["database"],
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor
        )
        with conn.cursor() as cursor:
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS global_latest_prices (
                    instrument_id INT PRIMARY KEY,
                    closing_price DECIMAL(18,4),
                    price_date DATE
                )
            """)
            logging.info(f"About to process {len(prices)} price records")
            
            # Process in batches for better performance
            batch_size = 100
            batch_data = []
            
            for i, item in enumerate(prices):
                if i % 1000 == 0:  # Log progress every 1000 items
                    logging.info(f"Processing price record {i+1}/{len(prices)}")
                    
                if not all(k in item for k in ('i', 'c', 'd')):
                    logging.warning(f"Missing keys in price item: {item}")
                    errors += 1
                    continue
                
                # Prepare data for batch insert
                data_tuple = (item['i'], item['c'], item['d'])
                batch_data.append(data_tuple)
                
                # Process batch when it reaches batch_size or is the last item
                if len(batch_data) >= batch_size or i == len(prices) - 1:
                    try:
                        # Execute batch insert
                        cursor.executemany("""
                            INSERT INTO global_latest_prices (instrument_id, closing_price, price_date)
                            VALUES (%s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                                closing_price=VALUES(closing_price),
                                price_date=VALUES(price_date)
                        """, batch_data)
                        
                        inserted += len(batch_data)
                        
                        # Commit every 1000 records for safety
                        if inserted % 1000 == 0 or i == len(prices) - 1:
                            conn.commit()
                            logging.info(f"Committed {inserted} price records to database")
                        
                        batch_data = []  # Clear batch for next set
                        
                    except Exception as e:
                        logging.error(f"Failed to insert price batch at item {i}: {e}")
                        errors += len(batch_data)
                        batch_data = []  # Clear failed batch
            
            # Final commit
            conn.commit()
        conn.close()
        logging.info(f"Inserted/updated {inserted} records into the database.")
        if errors:
            logging.warning(f"{errors} records failed to insert.")
    except Exception as e:
        logging.exception(f"Database error: {e}")

def main():
    start = datetime.now()
    # Console delimiter (clean display)
    print("="*50)
    print("GLOBAL LATEST PRICES SCRIPT STARTED")
    print("="*50)
    # File logging (with timestamps)
    logging.info("="*50)
    logging.info("GLOBAL LATEST PRICES SCRIPT STARTED")
    logging.info("="*50)
    prices = fetch_latest_prices()
    if prices:
        save_latest_prices(prices)
    else:
        logging.warning("No global latest prices found or fetched.")
    duration = datetime.now() - start
    logging.info(f"Script finished. Duration: {duration}.")

if __name__ == "__main__":
    main()
