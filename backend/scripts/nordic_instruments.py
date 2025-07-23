import os
from dotenv import load_dotenv
import requests
import pymysql

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
    'database': os.getenv('DB_FOUNDATION'),  # Change if you want other DB from .env like DB_MARKETDATA
    'charset': 'utf8mb4',
    'cursorclass': pymysql.cursors.DictCursor
}

def fetch_instruments():
    url = f"{BASE_URL}/instruments?authKey={API_KEY}"
    try:
        response = requests.get(url)
        response.raise_for_status()
        data = response.json()
    except requests.RequestException as e:
        print(f"❌ Error fetching instruments from API: {e}")
        return []

    instruments = data.get("instruments")
    if instruments is None:
        print("❌ No 'instruments' key found in API response.")
        print("Full API response:", data)
        return []

    return instruments

def save_to_db(instruments):
    try:
        conn = pymysql.connect(**db_config)
        print(f"✅ Connected to database: {db_config['database']}")
    except Exception as e:
        print(f"❌ Failed to connect to database: {e}")
        raise
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS nordic_instruments (
                    id INT PRIMARY KEY,
                    name VARCHAR(255),
                    ticker VARCHAR(50),
                    isin VARCHAR(50),
                    sectorId INT
                )
            """)

            for item in instruments:
                # Check required keys
                if not all(k in item for k in ("insId", "name", "ticker", "isin", "sectorId")):
                    print(f"⚠️ Missing keys in item: {item}")
                    continue

                cursor.execute("""
                    INSERT INTO nordic_instruments (id, name, ticker, isin, sectorId)
                    VALUES (%s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        name=VALUES(name),
                        ticker=VALUES(ticker),
                        isin=VALUES(isin),
                        sectorId=VALUES(sectorId)
                """, (item["insId"], item["name"], item["ticker"], item["isin"], item["sectorId"]))

        conn.commit()
    finally:
        conn.close()

if __name__ == "__main__":
    print("🚀 Starting Nordic instruments fetch...")
    
    instruments = fetch_instruments()
    
    if not instruments:
        print("❌ No instruments fetched. Exiting.")
        exit(1)
    
    print(f"✅ Successfully fetched {len(instruments)} instruments from API")
    
    try:
        save_to_db(instruments)
        print(f"✅ Successfully saved {len(instruments)} instruments to database")
    except Exception as e:
        print(f"❌ Error saving to database: {e}")
        exit(1)
    
    print("🎉 Nordic instruments update completed successfully!")
