import os
from dotenv import load_dotenv
import requests
import mysql.connector
import json
import logging
from datetime import datetime, timedelta
import time
from collections import defaultdict

# Load environment variables from .env file located at project root
load_dotenv(dotenv_path='../../.env')

# Get configuration from environment variables
API_KEY = os.getenv('FREECURRENCYAPI_KEY')
if not API_KEY:
    raise ValueError("FREECURRENCYAPI_KEY not set in environment")

db_config = {
    'host': os.getenv('DB_HOST'),
    'user': os.getenv('DB_USERNAME'),
    'password': os.getenv('DB_PASSWORD'),
    'port': int(os.getenv('DB_PORT', 3306)),
    'database': os.getenv('DB_MARKETDATA'),  # Using marketdata database for FX rates
    'charset': 'utf8mb4',
    'cursorclass': mysql.connector.cursors.DictCursor
}

# Table name for FX rates
FX_TABLE_NAME = "fx_rates_freecurrency"

# === Setup Logging ===
log_dir = os.getenv('LOG_PATH', "../../storage/logs")
os.makedirs(log_dir, exist_ok=True)
log_filename = os.path.join(log_dir, "fx_rates_freecurrency.log")

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

# Currency pairs to fetch
CURRENCY_PAIRS_TO_FETCH = [
    ("AUD", "SEK"),
    ("CAD", "SEK"),
    ("CHF", "SEK"),
    ("CZK", "SEK"),
    ("DKK", "NOK"),
    ("DKK", "SEK"),
    ("EUR", "NOK"),
    ("EUR", "SEK"),
    ("GBP", "SEK"),
    ("GBP", "USD"),
    ("NOK", "SEK"),
    ("PLN", "EUR"),
    ("PLN", "SEK"),
    ("SGD", "SEK"),
    ("USD", "CAD"),
    ("USD", "CHF"),
    ("USD", "DKK"),
    ("USD", "NOK"),
    ("USD", "SEK"),
]

# Group target currencies by base for efficient API calls
CURRENCY_PAIRS_BY_BASE = defaultdict(list)
for base, target in CURRENCY_PAIRS_TO_FETCH:
    CURRENCY_PAIRS_BY_BASE[base].append(target)

def get_fx_data_freecurrencyapi(api_key: str, base_currency: str, target_currencies: list):
    """Fetch FX rates from FreeCurrency API"""
    if not api_key:
        logging.error("FREECURRENCYAPI_KEY is not set in environment")
        return None, None

    currencies_param = ",".join(target_currencies)
    url = (f"https://api.freecurrencyapi.com/v1/latest?"
           f"apikey={api_key}&"
           f"base_currency={base_currency}&"
           f"currencies={currencies_param}")
    
    logging.info(f"Fetching FX rates for {base_currency} to {currencies_param}")
    
    try:
        response = requests.get(url)
        response.raise_for_status()
        data = response.json()
        
        if "data" in data and data["data"] is not None:
            logging.info(f"Successfully fetched {len(data['data'])} FX rates for {base_currency}")
            return data["data"], data  # Return both rates and raw JSON
        elif "message" in data:
            logging.warning(f"API message: {data['message']}")
        elif "errors" in data:
            logging.error(f"API error: {data['errors']}")
        else:
            logging.warning(f"Unexpected API response: {data}")
            
    except requests.exceptions.RequestException as e:
        logging.error(f"HTTP request failed: {e}")
    except ValueError as e:
        logging.error(f"JSON decode error: {e}")
    except Exception as e:
        logging.exception(f"Error fetching FX data: {e}")
        
    return None, None

def insert_fx_rate(cursor, base_currency: str, target_currency: str, rate_date: str, exchange_rate: float, raw_response: dict):
    """Insert or update FX rate in database"""
    sql = f"""
    INSERT INTO {FX_TABLE_NAME} (
        base_currency, target_currency, exchange_rate, rate_date, provider, raw_response
    )
    VALUES (%s, %s, %s, %s, %s, %s)
    ON DUPLICATE KEY UPDATE
        exchange_rate = VALUES(exchange_rate),
        raw_response = VALUES(raw_response),
        updated_at = CURRENT_TIMESTAMP
    """
    try:
        cursor.execute(sql, (
            base_currency,
            target_currency,
            exchange_rate,
            rate_date,
            "freecurrencyapi",
            json.dumps(raw_response)
        ))
        logging.info(f"Updated {base_currency}/{target_currency} = {exchange_rate} for {rate_date}")
        return True
    except mysql.connector.Error as err:
        logging.error(f"Failed to insert rate for {base_currency}/{target_currency}: {err}")
        return False

def daily_update():
    """Perform daily FX rates update"""
    total_entered_count = 0
    error_count = 0
    api_call_count = 0
    
    logging.info("Starting daily FX rates update from FreeCurrency API")
    
    cnx = None
    cursor = None
    
    try:
        # Validate database configuration
        missing_config = [k for k, v in db_config.items() if not v and k != 'cursorclass']
        if missing_config:
            logging.error(f"Missing database configuration: {missing_config}")
            return False
            
        # Connect to MySQL
        logging.info("Connecting to MySQL database...")
        cnx = mysql.connector.connect(**{k: v for k, v in db_config.items() if k != 'cursorclass'})
        cursor = cnx.cursor()
        logging.info("Successfully connected to MySQL database")
        
        today_str = datetime.now().strftime('%Y-%m-%d')
        logging.info(f"Updating FX rates for date: {today_str}")

        # Always ensure SEK/SEK = 1.0 (no API call needed)
        if insert_fx_rate(cursor, "SEK", "SEK", today_str, 1.0, {}):
            total_entered_count += 1
        else:
            error_count += 1

        # Process each base currency
        for base_curr, target_currs_list in CURRENCY_PAIRS_BY_BASE.items():
            if base_curr == "SEK":
                continue
                
            logging.info(f"Processing {base_curr} to {len(target_currs_list)} target currencies")
            
            rates, raw_json = get_fx_data_freecurrencyapi(
                api_key=API_KEY,
                base_currency=base_curr,
                target_currencies=target_currs_list
            )
            api_call_count += 1
            
            if rates:
                for target_curr in target_currs_list:
                    rate = rates.get(target_curr)
                    if rate is not None and isinstance(rate, (int, float)):
                        if insert_fx_rate(cursor, base_curr, target_curr, today_str, rate, raw_json):
                            total_entered_count += 1
                        else:
                            error_count += 1
                    else:
                        logging.warning(f"Invalid rate for {base_curr}/{target_curr}: {rate}")
                        error_count += 1
            else:
                logging.warning(f"No data received for {base_curr}")
                error_count += len(target_currs_list)
                
            # Rate limiting: pause between API calls (except for last one)
            if base_curr != list(CURRENCY_PAIRS_BY_BASE.keys())[-1]:
                logging.info("Pausing 6 seconds for API rate limiting...")
                time.sleep(6)

        cnx.commit()
        logging.info(f"Database transaction committed")
        logging.info(f"Update summary - API calls: {api_call_count}, Inserted/updated: {total_entered_count}, Errors: {error_count}")
        
        return error_count == 0
        
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
        if cnx and cnx.is_connected():
            cnx.close()
            logging.info("Database connection closed")

def main():
    start = datetime.now()
    logging.info("="*50)
    logging.info("FX RATES FREECURRENCY SCRIPT STARTED")
    logging.info("="*50)
    
    success = daily_update()
    
    duration = datetime.now() - start
    if success:
        logging.info(f"Script completed successfully. Duration: {duration}.")
    else:
        logging.error(f"Script completed with errors. Duration: {duration}.")

if __name__ == "__main__":
    main()