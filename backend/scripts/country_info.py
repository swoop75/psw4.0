import os
from dotenv import load_dotenv
import requests
import pymysql
import logging
from datetime import datetime

# Load environment variables from .env file located at GitHub root (two levels up from this script)
load_dotenv(dotenv_path='../../.env')

db_config = {
    'user': os.getenv('DB_USERNAME'),
    'password': os.getenv('DB_PASSWORD'),
    'host': os.getenv('DB_HOST'),
    'port': int(os.getenv('DB_PORT', 3306)),
    'database': os.getenv('DB_MARKETDATA'),  # Adjust if you want a different DB
    'charset': 'utf8mb4',
    'cursorclass': pymysql.cursors.DictCursor
}

# === Setup Logging ===
log_dir = os.getenv('LOG_PATH', "../../storage/logs")
os.makedirs(log_dir, exist_ok=True)
log_filename = os.path.join(log_dir, "country_info.log")

logger = logging.getLogger()
logger.setLevel(logging.INFO)
logger.handlers.clear()
file_handler = logging.FileHandler(log_filename)
file_handler.setLevel(logging.INFO)
file_formatter = logging.Formatter("%(asctime)s %(levelname)s: %(message)s", datefmt="%Y-%m-%d %H:%M:%S")
file_handler.setFormatter(file_formatter)
console_handler = logging.StreamHandler()
console_handler.setLevel(logging.INFO)
console_formatter = logging.Formatter("%(levelname)s: %(message)s")
console_handler.setFormatter(console_formatter)
logger.addHandler(file_handler)
logger.addHandler(console_handler)


def fetch_countries():
    url = "https://restcountries.com/v3.1/all"
    logging.info(f"Fetching countries from REST Countries API: {url}")
    try:
        response = requests.get(url)
        response.raise_for_status()
        countries = response.json()
        logging.info(f"Fetched {len(countries)} countries.")
        return countries
    except requests.RequestException as e:
        logging.error(f"HTTP request failed: {e}")
        return []
    except Exception as e:
        logging.exception(f"Error fetching countries: {e}")
        return []


def parse_country_data(country):
    """Map REST Countries country data to your table schema."""
    # iso_alpha2: cca2
    iso_alpha2 = country.get('cca2')

    # iso_alpha3: cca3
    iso_alpha3 = country.get('cca3')

    # iso_numeric: ccn3 (string)
    iso_numeric = country.get('ccn3')

    # ioc_code: not provided by REST Countries; try 'cioc'
    ioc_code = country.get('cioc')

    # fips_code: not provided by REST Countries API, set None
    fips_code = None

    # license_plate: not provided; None
    license_plate = None

    # internet_tld: tlds is a list like [".us"], join or take first
    tlds = country.get('tld')
    internet_tld = tlds[0] if tlds and len(tlds) > 0 else None

    # currency_code: currencies is a dict with keys currency codes, e.g. {"USD": {...}} — take first key
    currencies = country.get('currencies')
    currency_code = None
    if currencies and isinstance(currencies, dict):
        currency_code = list(currencies.keys())[0]

    # calling_code: callingCodes is not directly provided, but callingCode is in `idd` with `root` and `suffixes`
    calling_code = None
    idd = country.get('idd')
    if idd:
        root = idd.get('root')
        suffixes = idd.get('suffixes')
        if root and suffixes and isinstance(suffixes, list) and len(suffixes) > 0:
            calling_code = root + suffixes[0]
        elif root:
            calling_code = root

    # geoname_id: not in REST Countries, set None
    geoname_id = None

    # country_name: use 'name' > 'common'
    country_name = None
    name = country.get('name')
    if name:
        country_name = name.get('common')

    # flag_emoji: use 'flag' field (unicode emoji flag)
    flag_emoji = country.get('flag')

    return {
        'iso_alpha2': iso_alpha2,
        'iso_alpha3': iso_alpha3,
        'iso_numeric': iso_numeric,
        'ioc_code': ioc_code,
        'fips_code': fips_code,
        'license_plate': license_plate,
        'internet_tld': internet_tld,
        'currency_code': currency_code,
        'calling_code': calling_code,
        'geoname_id': geoname_id,
        'country_name': country_name,
        'flag_emoji': flag_emoji
    }


def save_to_db(countries):
    inserted = 0
    errors = 0
    logging.info("Connecting to MySQL database...")
    try:
        conn = pymysql.connect(**db_config)
        logging.info(f"Connected to database: {db_config['database']}")

        with conn.cursor() as cursor:
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS country_info (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    iso_alpha2 VARCHAR(2),
                    iso_alpha3 VARCHAR(3),
                    iso_numeric VARCHAR(3),
                    ioc_code VARCHAR(3),
                    fips_code VARCHAR(2),
                    license_plate VARCHAR(5),
                    internet_tld VARCHAR(10),
                    currency_code VARCHAR(3),
                    calling_code VARCHAR(10),
                    geoname_id INT,
                    country_name VARCHAR(100),
                    flag_emoji VARCHAR(10),
                    UNIQUE KEY (iso_alpha2)
                )
            """)

            for country in countries:
                data = parse_country_data(country)

                if not data['iso_alpha2']:
                    logging.warning(f"Skipping country missing ISO alpha-2 code: {data}")
                    errors += 1
                    continue

                try:
                    cursor.execute("""
                        INSERT INTO country_info (
                            iso_alpha2, iso_alpha3, iso_numeric, ioc_code, fips_code,
                            license_plate, internet_tld, currency_code, calling_code,
                            geoname_id, country_name, flag_emoji
                        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                        ON DUPLICATE KEY UPDATE
                            iso_alpha3=VALUES(iso_alpha3),
                            iso_numeric=VALUES(iso_numeric),
                            ioc_code=VALUES(ioc_code),
                            fips_code=VALUES(fips_code),
                            license_plate=VALUES(license_plate),
                            internet_tld=VALUES(internet_tld),
                            currency_code=VALUES(currency_code),
                            calling_code=VALUES(calling_code),
                            geoname_id=VALUES(geoname_id),
                            country_name=VALUES(country_name),
                            flag_emoji=VALUES(flag_emoji)
                    """, (
                        data['iso_alpha2'], data['iso_alpha3'], data['iso_numeric'], data['ioc_code'], data['fips_code'],
                        data['license_plate'], data['internet_tld'], data['currency_code'], data['calling_code'],
                        data['geoname_id'], data['country_name'], data['flag_emoji']
                    ))
                    inserted += 1
                except Exception as e:
                    logging.error(f"Failed to insert country {data.get('country_name')} ({data.get('iso_alpha2')}): {e}")
                    errors += 1

        conn.commit()
        conn.close()
        logging.info(f"Inserted/updated {inserted} countries into the database.")
        if errors:
            logging.warning(f"{errors} countries failed to insert.")
    except Exception as e:
        logging.exception(f"Database error: {e}")
        raise


def main():
    start = datetime.now()
    print("="*50)
    print("COUNTRY INFO SCRIPT STARTED")
    print("="*50)
    logging.info("="*50)
    logging.info("COUNTRY INFO SCRIPT STARTED")
    logging.info("="*50)

    countries = fetch_countries()

    if not countries:
        logging.warning("No countries found or fetched.")
        return

    try:
        save_to_db(countries)
    except Exception as e:
        logging.exception(f"Error saving to database: {e}")
        return

    duration = datetime.now() - start
    logging.info(f"Script finished. Duration: {duration}.")


if __name__ == "__main__":
    main()
