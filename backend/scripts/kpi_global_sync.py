#!/usr/bin/env python3
"""
KPI Global Data Synchronization Script
Fetches global KPI statistics from Börsdata API and populates psw_marketdata.kpi_global table

API Endpoint: https://apiservice.borsdata.se/v1/instruments/global/kpis/{kpiId}/{group}/{calculation}
Target Database: psw_marketdata
Target Table: kpi_global

This script fetches global Key Performance Indicator statistics from Börsdata's API
for portfolio analysis and benchmarking against market averages.

Environment Variables Required:
- BORSDATA_AUTH_KEY: Börsdata API authentication key
- Database connection variables (DB_HOST, DB_USERNAME, DB_PASSWORD, etc.)

Author: PSW Development Team
Date: 2025-01-23
"""

import os
import sys
import requests
import mysql.connector
import logging
from datetime import datetime
from typing import Dict, List, Optional, Any, Tuple
from dotenv import load_dotenv
import time
import json
from decimal import Decimal

# Load environment variables from .env file located at project root
load_dotenv(dotenv_path='../../.env')

# Database configuration
DB_CONFIG = {
    'host': os.getenv('DB_HOST'),
    'user': os.getenv('DB_USERNAME'),
    'password': os.getenv('DB_PASSWORD'),
    'database': os.getenv('DB_MARKETDATA', 'psw_marketdata'),
    'port': int(os.getenv('DB_PORT', 3306)),
    'charset': 'utf8mb4',
    'use_unicode': True,
    'autocommit': False
}

# Börsdata API configuration
API_BASE_URL = 'https://apiservice.borsdata.se'
API_ENDPOINT_TEMPLATE = '/v1/instruments/global/kpis/{kpi_id}/{group}/{calculation}'
BORSDATA_AUTH_KEY = os.getenv('BORSDATA_AUTH_KEY', '55d57eb27768456b9aa975e158d12898')

API_HEADERS = {
    'accept': 'text/plain',
    'User-Agent': 'PSW-KPI-Global-Sync/1.0'
}

# Configuration for KPI data to fetch
# You can customize this based on which KPIs and time periods you want
KPI_FETCH_CONFIG = [
    # Format: (kpi_id, group, calculation)
    # Common time periods: '1year', '3year', '5year', '10year'
    # Common calculations: 'mean', 'median', 'max', 'min'
    (2, '1year', 'mean'),    # P/E 1-year mean
    (1, '1year', 'mean'),    # Dividend Yield 1-year mean
    (2, '3year', 'mean'),    # P/E 3-year mean
    (1, '3year', 'mean'),    # Dividend Yield 3-year mean
    (2, '5year', 'mean'),    # P/E 5-year mean
    (1, '5year', 'mean'),    # Dividend Yield 5-year mean
    # Add more KPI configurations as needed
]

# Logging configuration
LOG_DIR = os.getenv('LOG_PATH', "../../storage/logs")
os.makedirs(LOG_DIR, exist_ok=True)
LOG_FILENAME = os.path.join(LOG_DIR, "kpi_global_sync.log")

# Setup logger with both file and console output
logger = logging.getLogger(__name__)
logger.setLevel(logging.INFO)
logger.handlers.clear()

# File handler
file_handler = logging.FileHandler(LOG_FILENAME, encoding='utf-8')
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


class KPIGlobalSync:
    """Handles synchronization of global KPI data from Börsdata API to database"""
    
    def __init__(self):
        self.db_connection = None
        self.db_cursor = None
        self.session = requests.Session()
        self.session.headers.update(API_HEADERS)
        
        # Statistics
        self.stats = {
            'api_calls': 0,
            'processed_records': 0,
            'inserted': 0,
            'updated': 0,
            'errors': 0,
            'skipped': 0,
            'start_time': datetime.now()
        }
    
    def connect_database(self) -> bool:
        """Establish database connection"""
        try:
            # Validate database configuration
            missing_config = [k for k, v in DB_CONFIG.items() if not v and k not in ['charset', 'use_unicode', 'autocommit']]
            if missing_config:
                logger.error(f"Missing database configuration: {missing_config}")
                return False
            
            logger.info("Connecting to MySQL database...")
            self.db_connection = mysql.connector.connect(**DB_CONFIG)
            self.db_cursor = self.db_connection.cursor(buffered=True)
            logger.info("Successfully connected to MySQL database")
            return True
            
        except mysql.connector.Error as err:
            logger.error(f"Database connection failed: {err}")
            return False
        except Exception as e:
            logger.error(f"Unexpected error during database connection: {e}")
            return False
    
    def disconnect_database(self):
        """Close database connection"""
        try:
            if self.db_cursor:
                self.db_cursor.close()
                logger.info("Database cursor closed")
            
            if self.db_connection and self.db_connection.is_connected():
                self.db_connection.close()
                logger.info("Database connection closed")
                
        except Exception as e:
            logger.error(f"Error closing database connection: {e}")
    
    def fetch_kpi_global_data(self, kpi_id: int, group: str, calculation: str) -> Optional[Dict[str, Any]]:
        """Fetch global KPI data for specific parameters from Börsdata API"""
        try:
            # Build URL
            endpoint = API_ENDPOINT_TEMPLATE.format(kpi_id=kpi_id, group=group, calculation=calculation)
            url = f"{API_BASE_URL}{endpoint}"
            params = {'authKey': BORSDATA_AUTH_KEY}
            
            logger.debug(f"Fetching KPI data: KPI={kpi_id}, Group={group}, Calc={calculation}")
            
            response = self.session.get(url, params=params, timeout=30)
            self.stats['api_calls'] += 1
            
            if response.status_code == 200:
                data = response.json()
                logger.debug(f"Successfully fetched data for KPI {kpi_id}/{group}/{calculation}: {len(data.get('values', []))} values")
                return data
                
            elif response.status_code == 404:
                logger.warning(f"No data found for KPI {kpi_id}/{group}/{calculation} (404)")
                return None
            elif response.status_code == 401:
                logger.error("API authentication failed (401)")
                return None
            elif response.status_code == 403:
                logger.error("API access forbidden (403)")
                return None
            else:
                logger.error(f"API request failed with status code: {response.status_code}")
                logger.error(f"Response: {response.text[:500]}")
                return None
                
        except requests.exceptions.Timeout:
            logger.error(f"API request timed out for KPI {kpi_id}/{group}/{calculation}")
            return None
        except requests.exceptions.ConnectionError:
            logger.error(f"Failed to connect to API endpoint for KPI {kpi_id}/{group}/{calculation}")
            return None
        except requests.exceptions.RequestException as e:
            logger.error(f"API request failed for KPI {kpi_id}/{group}/{calculation}: {e}")
            return None
        except json.JSONDecodeError as e:
            logger.error(f"Failed to parse API response as JSON for KPI {kpi_id}/{group}/{calculation}: {e}")
            return None
        except Exception as e:
            logger.error(f"Unexpected error during API fetch for KPI {kpi_id}/{group}/{calculation}: {e}")
            return None
    
    def process_kpi_values(self, kpi_data: Dict[str, Any], kpi_id: int, group: str, calculation: str) -> int:
        """Process and insert/update KPI values from API response"""
        processed_count = 0
        
        try:
            values = kpi_data.get('values', [])
            if not values:
                logger.warning(f"No values found in response for KPI {kpi_id}/{group}/{calculation}")
                return 0
            
            # Prepare SQL statement with ON DUPLICATE KEY UPDATE
            insert_sql = """
                INSERT INTO kpi_global (kpi_id, group_period, calculation, instrument_id, numeric_value, string_value)
                VALUES (%s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    numeric_value = VALUES(numeric_value),
                    string_value = VALUES(string_value),
                    updated_at = CURRENT_TIMESTAMP
            """
            
            batch_data = []
            for value_item in values:
                try:
                    instrument_id = value_item.get('i')
                    numeric_value = value_item.get('n')
                    string_value = value_item.get('s')
                    
                    # Validate required fields
                    if instrument_id is None:
                        logger.warning(f"Skipping value with missing instrument_id: {value_item}")
                        self.stats['skipped'] += 1
                        continue
                    
                    # Convert numeric value to Decimal if not None
                    if numeric_value is not None:
                        try:
                            numeric_value = Decimal(str(numeric_value))
                        except (ValueError, TypeError):
                            logger.warning(f"Invalid numeric value for instrument {instrument_id}: {numeric_value}")
                            numeric_value = None
                    
                    batch_data.append((kpi_id, group, calculation, instrument_id, numeric_value, string_value))
                    processed_count += 1
                    
                except Exception as e:
                    logger.error(f"Error processing value item {value_item}: {e}")
                    self.stats['errors'] += 1
                    continue
            
            # Execute batch insert
            if batch_data:
                self.db_cursor.executemany(insert_sql, batch_data)
                
                # Track insert vs update counts (approximate)
                affected_rows = self.db_cursor.rowcount
                if affected_rows > 0:
                    # Rough estimate: if rowcount == len(batch_data), likely all inserts
                    # if rowcount == 2 * len(batch_data), likely all updates
                    if affected_rows <= len(batch_data):
                        self.stats['inserted'] += affected_rows
                    else:
                        self.stats['updated'] += (affected_rows - len(batch_data))
                        self.stats['inserted'] += (2 * len(batch_data) - affected_rows)
                
                logger.debug(f"Processed {len(batch_data)} values for KPI {kpi_id}/{group}/{calculation}")
            
            return processed_count
            
        except mysql.connector.Error as err:
            logger.error(f"Database error processing KPI values {kpi_id}/{group}/{calculation}: {err}")
            self.stats['errors'] += 1
            return 0
        except Exception as e:
            logger.error(f"Unexpected error processing KPI values {kpi_id}/{group}/{calculation}: {e}")
            self.stats['errors'] += 1
            return 0
    
    def get_available_kpi_ids(self) -> List[int]:
        """Get list of available KPI IDs from kpi_metadata table"""
        try:
            self.db_cursor.execute("SELECT DISTINCT kpi_id FROM kpi_metadata ORDER BY kpi_id")
            kpi_ids = [row[0] for row in self.db_cursor.fetchall()]
            logger.info(f"Found {len(kpi_ids)} available KPI IDs: {kpi_ids[:10]}{'...' if len(kpi_ids) > 10 else ''}")
            return kpi_ids
        except mysql.connector.Error as err:
            logger.error(f"Failed to fetch available KPI IDs: {err}")
            return []
    
    def sync_kpi_global_data(self) -> bool:
        """Main synchronization process"""
        try:
            logger.info("Starting KPI global data synchronization...")
            
            # Connect to database
            if not self.connect_database():
                return False
            
            # Get available KPI IDs if using dynamic configuration
            available_kpis = self.get_available_kpi_ids()
            if not available_kpis:
                logger.warning("No KPI metadata found. Run kpi_metadata_sync.py first.")
            
            # Process configured KPI combinations
            total_configs = len(KPI_FETCH_CONFIG)
            logger.info(f"Processing {total_configs} KPI configurations...")
            
            for i, (kpi_id, group, calculation) in enumerate(KPI_FETCH_CONFIG, 1):
                logger.info(f"Processing configuration {i}/{total_configs}: KPI {kpi_id}/{group}/{calculation}")
                
                # Check if KPI exists in metadata (if we have the list)
                if available_kpis and kpi_id not in available_kpis:
                    logger.warning(f"KPI {kpi_id} not found in metadata, skipping...")
                    self.stats['skipped'] += 1
                    continue
                
                # Fetch data from API
                kpi_data = self.fetch_kpi_global_data(kpi_id, group, calculation)
                
                if kpi_data:
                    # Process the values
                    processed = self.process_kpi_values(kpi_data, kpi_id, group, calculation)
                    self.stats['processed_records'] += processed
                    
                    # Commit after each KPI configuration
                    try:
                        self.db_connection.commit()
                        logger.debug(f"Committed {processed} records for KPI {kpi_id}/{group}/{calculation}")
                    except mysql.connector.Error as err:
                        logger.error(f"Failed to commit KPI {kpi_id}/{group}/{calculation}: {err}")
                        self.db_connection.rollback()
                        self.stats['errors'] += 1
                else:
                    logger.warning(f"No data received for KPI {kpi_id}/{group}/{calculation}")
                    self.stats['skipped'] += 1
                
                # Small delay between API calls to be respectful
                time.sleep(0.5)
            
            # Final statistics
            duration = datetime.now() - self.stats['start_time']
            logger.info("KPI global data synchronization completed")
            logger.info(f"Statistics: API_calls={self.stats['api_calls']}, "
                       f"Processed_records={self.stats['processed_records']}, "
                       f"Inserted={self.stats['inserted']}, "
                       f"Updated={self.stats['updated']}, "
                       f"Errors={self.stats['errors']}, "
                       f"Skipped={self.stats['skipped']}, "
                       f"Duration={duration}")
            
            return self.stats['errors'] == 0
            
        except Exception as e:
            logger.error(f"Unexpected error during synchronization: {e}")
            if self.db_connection:
                self.db_connection.rollback()
            return False
        
        finally:
            self.disconnect_database()


def main():
    """Main execution function"""
    start_time = datetime.now()
    
    # Console delimiter for clean display
    print("=" * 60)
    print("KPI GLOBAL DATA SYNCHRONIZATION SCRIPT STARTED")
    print("=" * 60)
    
    # File logging with timestamps
    logger.info("=" * 60)
    logger.info("KPI GLOBAL DATA SYNCHRONIZATION SCRIPT STARTED")
    logger.info("=" * 60)
    
    try:
        # Create synchronizer instance and run
        sync = KPIGlobalSync()
        success = sync.sync_kpi_global_data()
        
        duration = datetime.now() - start_time
        
        if success:
            logger.info(f"Script completed successfully. Duration: {duration}")
            sys.exit(0)
        else:
            logger.error(f"Script failed. Duration: {duration}")
            sys.exit(1)
            
    except KeyboardInterrupt:
        logger.warning("Script interrupted by user")
        sys.exit(130)
    except Exception as e:
        logger.error(f"Script failed with unexpected error: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()