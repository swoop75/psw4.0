#!/usr/bin/env python3
"""
KPI Metadata Synchronization Script
Fetches KPI metadata from Börsdata API and populates psw_marketdata.kpi_metadata table

API Endpoint: https://apiservice.borsdata.se/v1/instruments/kpis/metadata
Target Database: psw_marketdata
Target Table: kpi_metadata

This script fetches Key Performance Indicator metadata from Börsdata's API
and synchronizes it with the local database for portfolio analysis and new company evaluation.

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
from typing import Dict, List, Optional, Any
from dotenv import load_dotenv
import time
import json

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
API_ENDPOINT = '/v1/instruments/kpis/metadata'
BORSDATA_AUTH_KEY = os.getenv('BORSDATA_AUTH_KEY', '55d57eb27768456b9aa975e158d12898')

API_HEADERS = {
    'accept': 'application/json',
    'User-Agent': 'PSW-KPI-Sync/1.0'
}

# Logging configuration
LOG_DIR = os.getenv('LOG_PATH', "../../storage/logs")
os.makedirs(LOG_DIR, exist_ok=True)
LOG_FILENAME = os.path.join(LOG_DIR, "kpi_metadata_sync.log")

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


class KPIMetadataSync:
    """Handles synchronization of KPI metadata from API to database"""
    
    def __init__(self):
        self.db_connection = None
        self.db_cursor = None
        self.session = requests.Session()
        self.session.headers.update(API_HEADERS)
        
        # Statistics
        self.stats = {
            'processed': 0,
            'inserted': 0,
            'updated': 0,
            'errors': 0,
            'start_time': datetime.now()
        }
    
    def connect_database(self) -> bool:
        """Establish database connection"""
        try:
            # Validate database configuration
            missing_config = [k for k, v in DB_CONFIG.items() if not v and k != 'charset' and k != 'use_unicode' and k != 'autocommit']
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
    
    def fetch_kpi_metadata(self) -> Optional[List[Dict[str, Any]]]:
        """Fetch KPI metadata from Börsdata API endpoint"""
        try:
            # Build URL with auth key parameter for Börsdata API
            url = f"{API_BASE_URL.rstrip('/')}{API_ENDPOINT}"
            params = {'authKey': BORSDATA_AUTH_KEY}
            
            logger.info(f"Fetching KPI metadata from Börsdata API: {url}")
            logger.info(f"Using auth key: {BORSDATA_AUTH_KEY[:10]}...")  # Log first 10 chars for verification
            
            response = self.session.get(url, params=params, timeout=30)
            
            if response.status_code == 200:
                data = response.json()
                
                # Handle the expected JSON structure
                if 'kpiHistoryMetadatas' in data:
                    kpi_list = data['kpiHistoryMetadatas']
                    logger.info(f"Successfully fetched {len(kpi_list)} KPI metadata records")
                    return kpi_list
                else:
                    logger.warning("API response does not contain 'kpiHistoryMetadatas' field")
                    logger.debug(f"Response structure: {list(data.keys()) if isinstance(data, dict) else type(data)}")
                    return None
                    
            elif response.status_code == 404:
                logger.error("API endpoint not found (404)")
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
            logger.error("API request timed out")
            return None
        except requests.exceptions.ConnectionError:
            logger.error("Failed to connect to API endpoint")
            return None
        except requests.exceptions.RequestException as e:
            logger.error(f"API request failed: {e}")
            return None
        except json.JSONDecodeError as e:
            logger.error(f"Failed to parse API response as JSON: {e}")
            return None
        except Exception as e:
            logger.error(f"Unexpected error during API fetch: {e}")
            return None
    
    def process_kpi_record(self, kpi_data: Dict[str, Any]) -> bool:
        """Process and insert/update a single KPI metadata record"""
        try:
            # Extract fields from API data
            kpi_id = kpi_data.get('kpiId')
            name_sv = kpi_data.get('nameSv', '').strip()
            name_en = kpi_data.get('nameEn', '').strip()
            format_spec = kpi_data.get('format')
            is_string = bool(kpi_data.get('isString', False))
            
            # Validate required fields
            if not kpi_id or not name_sv or not name_en:
                logger.warning(f"Skipping record with missing required fields: {kpi_data}")
                self.stats['errors'] += 1
                return False
            
            # Prepare SQL statement with ON DUPLICATE KEY UPDATE
            insert_sql = """
                INSERT INTO kpi_metadata (kpi_id, name_sv, name_en, format, is_string)
                VALUES (%s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    name_sv = VALUES(name_sv),
                    name_en = VALUES(name_en),
                    format = VALUES(format),
                    is_string = VALUES(is_string),
                    updated_at = CURRENT_TIMESTAMP
            """
            
            # Execute the query
            self.db_cursor.execute(insert_sql, (kpi_id, name_sv, name_en, format_spec, is_string))
            
            # Check if this was an insert or update
            if self.db_cursor.rowcount == 1:
                self.stats['inserted'] += 1
                logger.debug(f"Inserted new KPI metadata: {kpi_id} - {name_en}")
            elif self.db_cursor.rowcount == 2:
                self.stats['updated'] += 1
                logger.debug(f"Updated existing KPI metadata: {kpi_id} - {name_en}")
            
            self.stats['processed'] += 1
            return True
            
        except mysql.connector.Error as err:
            logger.error(f"Database error processing KPI {kpi_data.get('kpiId', 'unknown')}: {err}")
            self.stats['errors'] += 1
            return False
        except Exception as e:
            logger.error(f"Unexpected error processing KPI {kpi_data.get('kpiId', 'unknown')}: {e}")
            self.stats['errors'] += 1
            return False
    
    def sync_kpi_metadata(self) -> bool:
        """Main synchronization process"""
        try:
            logger.info("Starting KPI metadata synchronization...")
            
            # Connect to database
            if not self.connect_database():
                return False
            
            # Fetch data from API
            kpi_metadata_list = self.fetch_kpi_metadata()
            if not kpi_metadata_list:
                logger.error("No KPI metadata received from API")
                return False
            
            logger.info(f"Processing {len(kpi_metadata_list)} KPI metadata records...")
            
            # Process each KPI record
            batch_size = 50
            for i in range(0, len(kpi_metadata_list), batch_size):
                batch = kpi_metadata_list[i:i + batch_size]
                
                logger.info(f"Processing batch {i//batch_size + 1}/{(len(kpi_metadata_list) + batch_size - 1)//batch_size}")
                
                for kpi_data in batch:
                    self.process_kpi_record(kpi_data)
                
                # Commit batch
                try:
                    self.db_connection.commit()
                    logger.debug(f"Committed batch of {len(batch)} records")
                except mysql.connector.Error as err:
                    logger.error(f"Failed to commit batch: {err}")
                    self.db_connection.rollback()
                    return False
                
                # Small delay between batches to avoid overwhelming the database
                time.sleep(0.1)
            
            # Final statistics
            duration = datetime.now() - self.stats['start_time']
            logger.info("KPI metadata synchronization completed successfully")
            logger.info(f"Statistics: Processed={self.stats['processed']}, "
                       f"Inserted={self.stats['inserted']}, "
                       f"Updated={self.stats['updated']}, "
                       f"Errors={self.stats['errors']}, "
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
    print("KPI METADATA SYNCHRONIZATION SCRIPT STARTED")
    print("=" * 60)
    
    # File logging with timestamps
    logger.info("=" * 60)
    logger.info("KPI METADATA SYNCHRONIZATION SCRIPT STARTED")
    logger.info("=" * 60)
    
    try:
        # Create synchronizer instance and run
        sync = KPIMetadataSync()
        success = sync.sync_kpi_metadata()
        
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