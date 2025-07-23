import mysql.connector
import requests
import json # Used for debugging/inspecting API response if needed, but not strictly for the core logic
import time # For potential future retry mechanisms

# --- Configuration ---
DB_CONFIG = {
    'host': '100.117.171.98',
    'user': 'swoop',
    'password': 'QQ1122ww_1975!#',
    'database': 'psw_marketdata'
}

# API endpoint for fetching all country data
# The 'fields' parameter is now explicitly added as required by the API's error message.
# We list all fields that are extracted and used in the script.
RESTCOUNTRIES_API_URL = "https://restcountries.com/v3.1/all?fields=cca2,cca3,ccn3,cioc,car,tld,currencies,idd,name,flag"

# --- Database Connection Functions ---
def get_db_connection():
    """
    Establishes a connection to the MySQL database.
    Returns the connection object if successful, None otherwise.
    """
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        print("Successfully connected to the database.")
        return conn
    except mysql.connector.Error as err:
        print(f"Error connecting to database: {err}")
        print("Please check your DB_CONFIG settings (host, user, password, database).")
        return None

def close_db_connection(conn, cursor):
    """
    Closes the database cursor and connection.
    """
    if cursor:
        cursor.close()
    if conn:
        conn.close()
        print("Database connection closed.")

# --- Data Fetching Function ---
def fetch_country_data():
    """
    Fetches comprehensive country data from the REST Countries API.
    Returns a list of country dictionaries if successful, None otherwise.
    """
    print(f"Attempting to fetch country data from {RESTCOUNTRIES_API_URL}...")
    try:
        # Make an HTTP GET request to the API
        response = requests.get(RESTCOUNTRIES_API_URL, timeout=30)
        # Raise an HTTPError for bad responses (4xx or 5xx status codes)
        response.raise_for_status()
        
        # Parse the JSON response into a Python list of dictionaries
        countries_data = response.json()
        print(f"Successfully fetched data for {len(countries_data)} countries.")
        return countries_data
    except requests.exceptions.HTTPError as http_err:
        # Specific error for HTTP status codes (4xx, 5xx)
        print(f"HTTP error occurred: {http_err} for URL: {RESTCOUNTRIES_API_URL}")
        print(f"Response content: {response.text}") # Print response content for more details
        return None
    except requests.exceptions.ConnectionError as conn_err:
        # Specific error for network problems (e.g., DNS failure, refused connection)
        print(f"Connection error occurred: {conn_err}. Check your internet connection or API server status.")
        return None
    except requests.exceptions.Timeout as timeout_err:
        # Specific error for request timeout
        print(f"Timeout error occurred: {timeout_err}. The API took too long to respond.")
        return None
    except requests.exceptions.RequestException as e:
        # Catch any other request-related errors
        print(f"An unexpected error occurred while fetching country data: {e}")
        return None

# --- Data Processing and Database Upsert Function ---
def populate_and_update_countries(countries_data):
    """
    Iterates through the fetched country data and performs an UPSERT operation
    (UPDATE if country exists, INSERT if not) into the 'country_info' table.
    """
    conn = get_db_connection()
    if not conn:
        return # Exit if database connection failed

    cursor = conn.cursor()
    
    # SQL queries for checking existence, inserting, and updating records
    SELECT_SQL = "SELECT id FROM country_info WHERE iso_alpha2 = %s"
    
    INSERT_SQL = """
    INSERT INTO country_info (
        iso_alpha2, iso_alpha3, iso_numeric, ioc_code, fips_code, license_plate,
        internet_tld, currency_code, calling_code, geoname_id, country_name, flag_emoji
    ) VALUES (
        %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
    )
    """
    
    UPDATE_SQL = """
    UPDATE country_info SET
        iso_alpha3 = %s, iso_numeric = %s, ioc_code = %s, fips_code = %s,
        license_plate = %s, internet_tld = %s, currency_code = %s,
        calling_code = %s, geoname_id = %s, country_name = %s, flag_emoji = %s
    WHERE iso_alpha2 = %s
    """

    inserted_count = 0
    updated_count = 0
    
    for country in countries_data:
        # --- Extract and Sanitize Data from API Response ---
        # Use .get() with a default value (e.g., None or empty list/dict)
        # to safely access dictionary keys that might not always be present.
        
        iso_alpha2 = country.get('cca2')
        if not iso_alpha2:
            # Skip countries without an alpha2 code as it's our primary lookup key
            print(f"Warning: Skipping a country due to missing 'cca2' code. Data: {country.get('name',{}).get('common', 'N/A')}")
            continue

        iso_alpha3 = country.get('cca3')
        iso_numeric = country.get('ccn3')
        ioc_code = country.get('cioc') # International Olympic Committee code

        # FIPS code and Geoname ID are generally not available from restcountries.com
        # They are set to None and will be stored as NULL in the database.
        fips_code = None 
        geoname_id = None

        # Extract license plate sign: 'car' is a dict, 'signs' is a list
        # Safely get the 'signs' list, and then get the first element if the list is not empty
        car_info = country.get('car', {})
        signs = car_info.get('signs')
        license_plate = signs[0] if signs and len(signs) > 0 else None

        # Extract internet TLD: 'tld' is a list
        # Safely get the 'tld' list, and then get the first element if the list is not empty
        tlds = country.get('tld')
        internet_tld = tlds[0] if tlds and len(tlds) > 0 else None

        # Extract currency code: 'currencies' is an object where keys are currency codes
        currency_code = None
        currencies = country.get('currencies')
        if currencies:
            # Get the first currency code found (e.g., 'EUR' from {"EUR": {...}})
            currency_code = list(currencies.keys())[0] if currencies else None

        # Extract calling code: 'idd' contains 'root' and 'suffixes'
        calling_code = None
        idd = country.get('idd')
        if idd:
            root = idd.get('root')
            suffixes = idd.get('suffixes')
            if root and suffixes:
                # Combine root with the first suffix if available, otherwise just root
                calling_code = root + suffixes[0] if suffixes else root
            elif root:
                calling_code = root

        country_name = country.get('name', {}).get('common')
        flag_emoji = country.get('flag') # This field directly provides the emoji

        # Prepare the data tuple for SQL operations
        # The order of values must match the order of columns in INSERT_SQL
        insert_data_tuple = (
            iso_alpha2, iso_alpha3, iso_numeric, ioc_code, fips_code, license_plate,
            internet_tld, currency_code, calling_code, geoname_id, country_name, flag_emoji
        )
        
        # --- Database UPSERT Logic ---
        try:
            # 1. Check if the country already exists in the database
            cursor.execute(SELECT_SQL, (iso_alpha2,))
            result = cursor.fetchone() # Returns (id,) if found, None otherwise

            if result:
                # Country exists, perform an UPDATE
                # The order of values must match the SET clause in UPDATE_SQL,
                # with iso_alpha2 last for the WHERE clause.
                update_data_tuple = (
                    iso_alpha3, iso_numeric, ioc_code, fips_code, license_plate,
                    internet_tld, currency_code, calling_code, geoname_id,
                    country_name, flag_emoji, iso_alpha2 # iso_alpha2 is for the WHERE clause
                )
                cursor.execute(UPDATE_SQL, update_data_tuple)
                updated_count += 1
                # print(f"Updated: {country_name} ({iso_alpha2})") # Uncomment for detailed logging
            else:
                # Country does not exist, perform an INSERT
                cursor.execute(INSERT_SQL, insert_data_tuple)
                inserted_count += 1
                # print(f"Inserted: {country_name} ({iso_alpha2})") # Uncomment for detailed logging
            
            # Commit the transaction after each successful insert/update
            conn.commit()
        except mysql.connector.Error as err:
            # If an error occurs during DB operation, print it and rollback the transaction
            print(f"Error processing {country_name} ({iso_alpha2}): {err}")
            conn.rollback() # Revert changes for the current country

    print(f"\n--- Database Update Summary ---")
    print(f"Total countries attempted to process: {len(countries_data)}")
    print(f"New countries inserted: {inserted_count}")
    print(f"Existing countries updated: {updated_count}")

    close_db_connection(conn, cursor)

# --- Main Execution Block ---
def main():
    """
    Main function to orchestrate the script execution.
    """
    print("Starting country data population and update script...")
    
    # Step 1: Fetch country data from the API
    countries_data = fetch_country_data()
    
    if countries_data:
        # Step 2: Process data and update the database
        populate_and_update_countries(countries_data)
    else:
        print("Failed to fetch country data from the API. Cannot proceed with database update.")

if __name__ == "__main__":
    # This ensures main() is called only when the script is executed directly
    main()
