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
    'database': os.getenv('DB_MARKETDATA'),  # Using marketdata database for instruments
    'charset': 'utf8mb4',
    'cursorclass': pymysql.cursors.DictCursor
}

# === Setup Logging ===
log_dir = os.getenv('LOG_PATH', "../../storage/logs")
os.makedirs(log_dir, exist_ok=True)
log_filename = os.path.join(log_dir, "global_instruments.log")

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

def fetch_global_instruments():
    url = f"{BASE_URL}/instruments/global?authKey={API_KEY}"
    logging.info("Fetching global instruments from Börsdata API...")
    logging.info(f"API endpoint: {BASE_URL}/instruments/global")
    try:
        response = requests.get(url)
        response.raise_for_status()
        data = response.json()

        if isinstance(data, dict):
            for key in ['instruments', 'data']:
                if key in data:
                    logging.info(f"Found instruments under key '{key}' with {len(data[key])} entries.")
                    return data[key]
            logging.warning("No recognized key for instruments found in response.")
            logging.debug(f"Response keys: {list(data.keys())}")
            return []
        elif isinstance(data, list):
            logging.info(f"Received list of {len(data)} instruments.")
            return data
        else:
            logging.error("Unexpected data structure from API.")
            return []
    except Exception as e:
        logging.exception(f"Error fetching instruments: {e}")
        return []

def save_global_instruments(instruments):
    inserted = 0
    errors = 0
    logging.info("Connecting to MySQL database...")
    try:
        logging.info(f"Attempting connection to {db_config['host']}:{db_config['port']}")
        logging.info(f"Database: {db_config['database']}")
        logging.info(f"User: {db_config['user']}")
        
        conn = pymysql.connect(
            user=db_config["user"],
            password=db_config["password"],
            host=db_config["host"],
            port=db_config["port"],
            database=db_config["database"],
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor,
            connect_timeout=10,  # 10 second timeout
            read_timeout=30,     # 30 second read timeout
            write_timeout=30     # 30 second write timeout
        )
        logging.info("Successfully connected to MySQL database")
        
        with conn.cursor() as cursor:
            logging.info("Creating cursor successful")
            logging.info("About to create table...")
            # Check if table exists and get its structure
            cursor.execute("SHOW TABLES LIKE 'global_instruments'")
            table_exists = cursor.fetchone()
            
            if table_exists:
                logging.info("Found existing global_instruments table")
                cursor.execute("DESCRIBE global_instruments")
                columns = cursor.fetchall()
                # With DictCursor, columns are dictionaries with 'Field' key
                column_names = [col['Field'] for col in columns]
                logging.info(f"Existing table columns: {column_names}")
            else:
                logging.info("Creating new global_instruments table")
                cursor.execute("""
                    CREATE TABLE global_instruments (
                        insId INT PRIMARY KEY,
                        ref_instrument_id INT,
                        name VARCHAR(255) NOT NULL,
                        urlName VARCHAR(255),
                        instrument INT,
                        isin CHAR(20),
                        ticker VARCHAR(50),
                        yahoo VARCHAR(20),
                        sectorId INT,
                        marketId INT,
                        branchId INT,
                        countryId INT,
                        listingDate DATE,
                        stockPriceCurrency VARCHAR(3),
                        reportCurrency VARCHAR(3)
                    )
                """)
                logging.info("Table creation completed")
            
            logging.info(f"About to process {len(instruments)} instruments")
            
            # Process in batches for better performance
            batch_size = 100
            batch_data = []
            
            for i, item in enumerate(instruments):
                if i % 1000 == 0:  # Log progress every 1000 items
                    logging.info(f"Processing instrument {i+1}/{len(instruments)}")
                    
                if not all(k in item for k in ('insId', 'name', 'ticker', 'sectorId')):
                    logging.warning(f"Missing keys in instrument item: {item}")
                    errors += 1
                    continue
                    
                # Log first item for debugging
                if i == 0:
                    logging.info(f"First instrument data: {item}")
                
                # Prepare data for batch insert
                data_tuple = (
                    item['insId'],
                    item['name'],
                    item['ticker'],
                    item.get('isin'),
                    item['sectorId'],
                    item.get('urlName'),
                    item.get('instrument'),
                    item.get('yahoo'),
                    item.get('marketId'),
                    item.get('branchId'),
                    item.get('countryId'),
                    item.get('listingDate'),
                    item.get('stockPriceCurrency'),
                    item.get('reportCurrency')
                )
                batch_data.append(data_tuple)
                
                # Process batch when it reaches batch_size or is the last item
                if len(batch_data) >= batch_size or i == len(instruments) - 1:
                    try:
                        if i == 0:
                            logging.info("Attempting first batch INSERT...")
                        
                        # Execute batch insert
                        cursor.executemany("""
                            INSERT INTO global_instruments (
                                insId, name, ticker, isin, sectorId, urlName, instrument, 
                                yahoo, marketId, branchId, countryId, listingDate, 
                                stockPriceCurrency, reportCurrency
                            )
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                                name=VALUES(name),
                                ticker=VALUES(ticker),
                                isin=VALUES(isin),
                                sectorId=VALUES(sectorId),
                                urlName=VALUES(urlName),
                                instrument=VALUES(instrument),
                                yahoo=VALUES(yahoo),
                                marketId=VALUES(marketId),
                                branchId=VALUES(branchId),
                                countryId=VALUES(countryId),
                                listingDate=VALUES(listingDate),
                                stockPriceCurrency=VALUES(stockPriceCurrency),
                                reportCurrency=VALUES(reportCurrency)
                        """, batch_data)
                        
                        inserted += len(batch_data)
                        
                        if i == 0:
                            logging.info("First batch INSERT successful!")
                        
                        # Commit every 1000 records for safety
                        if inserted % 1000 == 0 or i == len(instruments) - 1:
                            conn.commit()
                            logging.info(f"Committed {inserted} records to database")
                        
                        batch_data = []  # Clear batch for next set
                        
                    except Exception as e:
                        logging.error(f"Failed to insert batch at item {i}: {e}")
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

def main():
    start = datetime.now()
    # Console delimiter (clean display)
    print("="*50)
    print("GLOBAL INSTRUMENTS SCRIPT STARTED")
    print("="*50)
    # File logging (with timestamps)
    logging.info("="*50)
    logging.info("GLOBAL INSTRUMENTS SCRIPT STARTED")
    logging.info("="*50)
    instruments = fetch_global_instruments()
    if instruments:
        save_global_instruments(instruments)
    else:
        logging.warning("No global instruments found or fetched.")
    duration = datetime.now() - start
    logging.info(f"Script finished. Duration: {duration}.")

if __name__ == "__main__":
    main()
