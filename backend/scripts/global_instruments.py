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
    'database': os.getenv('DB_FOUNDATION'),  # Using foundation database
    'charset': 'utf8mb4',
    'cursorclass': pymysql.cursors.DictCursor
}

# === Setup Logging ===
log_dir = os.getenv('LOG_PATH', "../../storage/logs")
os.makedirs(log_dir, exist_ok=True)
log_filename = os.path.join(log_dir, "global_instruments.log")

logging.basicConfig(
    filename=log_filename,
    level=logging.INFO,
    format="%(asctime)s %(levelname)s: %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S"
)

def fetch_global_instruments():
    url = f"{BASE_URL}/instruments/global?authKey={API_KEY}"
    logging.info("Fetching global instruments from API...")
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
    logging.info("Connecting to MariaDB database...")
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
                CREATE TABLE IF NOT EXISTS global_instruments (
                    id INT PRIMARY KEY,
                    name VARCHAR(255),
                    ticker VARCHAR(50),
                    isin VARCHAR(50),
                    sectorId INT
                )
            """)
            for item in instruments:
                if not all(k in item for k in ('insId', 'name', 'ticker', 'sectorId')):
                    logging.warning(f"Missing keys in instrument item: {item}")
                    errors += 1
                    continue
                try:
                    cursor.execute("""
                        INSERT INTO global_instruments (id, name, ticker, isin, sectorId)
                        VALUES (%s, %s, %s, %s, %s)
                        ON DUPLICATE KEY UPDATE
                            name=VALUES(name),
                            ticker=VALUES(ticker),
                            isin=VALUES(isin),
                            sectorId=VALUES(sectorId)
                    """, (
                        item['insId'],
                        item['name'],
                        item['ticker'],
                        item.get('isin'),
                        item['sectorId']
                    ))
                    inserted += 1
                except Exception as e:
                    logging.error(f"Failed to insert instrument {item.get('insId', 'N/A')}: {e}")
                    errors += 1
            conn.commit()
        conn.close()
        logging.info(f"Inserted/updated {inserted} instruments into the database.")
        if errors:
            logging.warning(f"⚠️ {errors} instruments failed to insert.")
    except Exception as e:
        logging.exception(f"Database error: {e}")

def main():
    start = datetime.now()
    logging.info("Script started.")
    instruments = fetch_global_instruments()
    if instruments:
        save_global_instruments(instruments)
    else:
        logging.warning("No global instruments found or fetched.")
    duration = datetime.now() - start
    logging.info(f"Script finished. Duration: {duration}. ✅")

if __name__ == "__main__":
    main()
