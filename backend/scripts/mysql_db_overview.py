import os
from dotenv import load_dotenv
import mysql.connector
import json
import logging
from datetime import datetime, date
from decimal import Decimal

# Load environment variables from .env file located at project root
load_dotenv(dotenv_path='../../.env')

# Get configuration from environment variables
db_config = {
    'host': os.getenv('DB_HOST'),
    'user': os.getenv('DB_USERNAME'),
    'password': os.getenv('DB_PASSWORD'),
    'port': int(os.getenv('DB_PORT', 3306))
}

# Output paths from environment
documentation_path = os.getenv('DOCUMENTATION_PATH', "../../documentation")
mysql_overview_dir = os.path.join(documentation_path, "MySQL_overview")

# Target databases from environment
target_databases = [
    os.getenv('DB_FOUNDATION', 'psw_foundation'),
    os.getenv('DB_MARKETDATA', 'psw_marketdata'),
    os.getenv('DB_PORTFOLIO', 'psw_portfolio')
]

# === Setup Logging ===
log_dir = os.getenv('LOG_PATH', "../../storage/logs")
os.makedirs(log_dir, exist_ok=True)
log_filename = os.path.join(log_dir, "mysql_db_overview.log")

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

def json_serial(obj):
    """JSON serializer for objects not serializable by default json code"""
    if isinstance(obj, (datetime, date)):
        return obj.isoformat()
    if isinstance(obj, Decimal):
        return str(obj)
    raise TypeError(f"Type {type(obj)} not serializable")

def get_mysql_server_info(cursor):
    """Get comprehensive MySQL server configuration and system information"""
    server_info = {}
    
    try:
        # MySQL version and basic info
        cursor.execute("SELECT VERSION(), @@hostname, @@port, @@datadir, @@basedir;")
        version_info = cursor.fetchone()
        server_info["basic"] = {
            "version": version_info[0],
            "hostname": version_info[1],
            "port": version_info[2],
            "data_directory": version_info[3],
            "base_directory": version_info[4]
        }
        
        # Critical system variables for optimization
        critical_vars = [
            'innodb_buffer_pool_size', 'max_connections', 'query_cache_size',
            'innodb_log_file_size', 'innodb_log_buffer_size', 'key_buffer_size',
            'sort_buffer_size', 'read_buffer_size', 'join_buffer_size',
            'tmp_table_size', 'max_heap_table_size', 'table_open_cache',
            'thread_cache_size', 'innodb_thread_concurrency', 'sql_mode',
            'default_storage_engine', 'character_set_server', 'collation_server'
        ]
        
        server_info["critical_variables"] = {}
        for var in critical_vars:
            try:
                cursor.execute(f"SELECT @@{var};")
                result = cursor.fetchone()
                server_info["critical_variables"][var] = result[0] if result else None
            except:
                server_info["critical_variables"][var] = "N/A"
        
        # Engine status
        cursor.execute("SHOW ENGINES;")
        engines = cursor.fetchall()
        server_info["storage_engines"] = [
            {"engine": row[0], "support": row[1], "comment": row[2]} 
            for row in engines
        ]
        
        # Global status for performance metrics
        performance_vars = [
            'Uptime', 'Questions', 'Queries', 'Slow_queries', 'Connections',
            'Max_used_connections', 'Aborted_connects', 'Aborted_clients',
            'Table_locks_immediate', 'Table_locks_waited', 'Key_read_requests',
            'Key_reads', 'Innodb_buffer_pool_reads', 'Innodb_buffer_pool_read_requests',
            'Created_tmp_tables', 'Created_tmp_disk_tables'
        ]
        
        server_info["performance_status"] = {}
        for var in performance_vars:
            try:
                cursor.execute(f"SHOW GLOBAL STATUS LIKE '{var}';")
                result = cursor.fetchone()
                server_info["performance_status"][var] = result[1] if result else None
            except:
                server_info["performance_status"][var] = "N/A"
        
        logging.info("Server configuration analysis completed")
        
    except Exception as e:
        logging.error(f"Failed to get server info: {e}")
        server_info["error"] = str(e)
    
    return server_info

def get_database_info(cursor, db_name):
    """Get detailed database-level configuration and metadata"""
    db_info = {"name": db_name}
    
    try:
        # Database character set and collation
        cursor.execute(f"""
            SELECT 
                DEFAULT_CHARACTER_SET_NAME,
                DEFAULT_COLLATION_NAME
            FROM information_schema.SCHEMATA 
            WHERE SCHEMA_NAME = '{db_name}'
        """)
        charset_info = cursor.fetchone()
        if charset_info:
            db_info["character_set"] = charset_info[0]
            db_info["collation"] = charset_info[1]
        
        # Database size and table count
        cursor.execute(f"""
            SELECT 
                COUNT(*) as table_count,
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb,
                ROUND(SUM(data_length) / 1024 / 1024, 2) AS data_size_mb,
                ROUND(SUM(index_length) / 1024 / 1024, 2) AS index_size_mb
            FROM information_schema.tables 
            WHERE table_schema = '{db_name}'
        """)
        size_info = cursor.fetchone()
        if size_info:
            db_info["table_count"] = size_info[0]
            db_info["total_size_mb"] = float(size_info[1]) if size_info[1] else 0
            db_info["data_size_mb"] = float(size_info[2]) if size_info[2] else 0
            db_info["index_size_mb"] = float(size_info[3]) if size_info[3] else 0
        
        # Storage engine distribution
        cursor.execute(f"""
            SELECT 
                ENGINE,
                COUNT(*) as table_count,
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = '{db_name}' AND ENGINE IS NOT NULL
            GROUP BY ENGINE
            ORDER BY size_mb DESC
        """)
        engine_stats = cursor.fetchall()
        db_info["storage_engines"] = [
            {
                "engine": row[0],
                "table_count": row[1],
                "size_mb": float(row[2]) if row[2] else 0
            }
            for row in engine_stats
        ]
        
        logging.info(f"Database configuration fetched for {db_name}")
        
    except Exception as e:
        logging.error(f"Failed to get database info for {db_name}: {e}")
        db_info["error"] = str(e)
    
    return db_info

def get_performance_analysis(cursor):
    """Get detailed performance analysis and optimization insights"""
    performance_data = {}
    
    try:
        # Process list for active queries
        cursor.execute("SHOW PROCESSLIST;")
        processes = cursor.fetchall()
        performance_data["active_processes"] = {
            "total_connections": len(processes),
            "processes": [
                {
                    "id": row[0],
                    "user": row[1],
                    "host": row[2],
                    "db": row[3],
                    "command": row[4],
                    "time": row[5],
                    "state": row[6],
                    "info": row[7][:100] if row[7] else None  # Truncate long queries
                }
                for row in processes[:20]  # Limit to first 20 processes
            ]
        }
        
        # InnoDB status for buffer pool and transaction analysis
        try:
            cursor.execute("SHOW ENGINE INNODB STATUS;")
            innodb_status = cursor.fetchone()
            if innodb_status:
                status_text = innodb_status[2]
                
                # Parse key metrics from InnoDB status
                performance_data["innodb_metrics"] = {
                    "status_available": True,
                    "buffer_pool_size": None,
                    "buffer_pool_hit_rate": None,
                    "pending_reads": None,
                    "pending_writes": None
                }
                
                # Extract buffer pool hit rate
                import re
                buffer_match = re.search(r'Buffer pool hit rate (\d+) / (\d+)', status_text)
                if buffer_match:
                    hits = int(buffer_match.group(1))
                    requests = int(buffer_match.group(2))
                    if requests > 0:
                        performance_data["innodb_metrics"]["buffer_pool_hit_rate"] = (hits / requests) * 100
        except:
            performance_data["innodb_metrics"] = {"status_available": False}
        
        # Table analysis for optimization opportunities
        cursor.execute("""
            SELECT 
                table_schema,
                table_name,
                table_rows,
                avg_row_length,
                data_length,
                index_length,
                (data_length + index_length) as total_size,
                engine
            FROM information_schema.tables 
            WHERE table_schema NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys')
            ORDER BY (data_length + index_length) DESC
            LIMIT 20
        """)
        
        large_tables = cursor.fetchall()
        performance_data["largest_tables"] = [
            {
                "schema": row[0],
                "table": row[1],
                "estimated_rows": row[2],
                "avg_row_length": row[3],
                "data_size_bytes": row[4],
                "index_size_bytes": row[5],
                "total_size_bytes": row[6],
                "engine": row[7]
            }
            for row in large_tables
        ]
        
        # Index analysis for unused or duplicate indexes
        cursor.execute("""
            SELECT 
                table_schema,
                table_name,
                index_name,
                MAX(non_unique) as non_unique,
                GROUP_CONCAT(column_name ORDER BY seq_in_index) as columns,
                MAX(index_type) as index_type,
                MAX(cardinality) as cardinality
            FROM information_schema.statistics 
            WHERE table_schema NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys')
            GROUP BY table_schema, table_name, index_name
            ORDER BY table_schema, table_name, MAX(cardinality) DESC
        """)
        
        indexes = cursor.fetchall()
        performance_data["index_analysis"] = [
            {
                "schema": row[0],
                "table": row[1],
                "index_name": row[2],
                "is_unique": row[3] == 0,
                "columns": row[4],
                "type": row[5],
                "cardinality": row[6]
            }
            for row in indexes
        ]
        
        logging.info("Performance analysis completed")
        
    except Exception as e:
        logging.error(f"Failed to get performance analysis: {e}")
        performance_data["error"] = str(e)
    
    return performance_data

def get_security_analysis(cursor):
    """Get security and user privilege analysis"""
    security_data = {}
    
    try:
        # User accounts and privileges (compatible with different MySQL versions)
        try:
            cursor.execute("DESCRIBE mysql.user;")
            user_columns = [row[0] for row in cursor.fetchall()]
            
            # Build query based on available columns
            base_columns = ['user', 'host']
            optional_columns = {
                'account_locked': 'account_locked',
                'password_expired': 'password_expired', 
                'password_last_changed': 'password_last_changed',
                'password_lifetime': 'password_lifetime',
                'max_connections': 'max_connections_per_hour',
                'max_queries_per_hour': 'max_queries_per_hour',
                'max_updates_per_hour': 'max_updates_per_hour',
                'max_user_connections': 'max_user_connections'
            }
            
            select_cols = base_columns.copy()
            for key, col_name in optional_columns.items():
                if col_name in user_columns:
                    select_cols.append(col_name)
                else:
                    select_cols.append(f"NULL as {key}")
            
            query = f"SELECT {', '.join(select_cols)} FROM mysql.user ORDER BY user, host"
            cursor.execute(query)
        except Exception as e:
            logging.warning(f"Could not query mysql.user table: {e}")
            cursor.execute("SELECT user, host FROM mysql.user ORDER BY user, host")
            user_columns = ['user', 'host']
        
        users = cursor.fetchall()
        security_data["user_accounts"] = []
        
        for row in users:
            if len(row) >= 2:  # At minimum we have user, host
                user_info = {
                    "user": row[0],
                    "host": row[1]
                }
                
                # Add additional fields if available
                if len(row) > 2:
                    try:
                        user_info.update({
                            "account_locked": row[2] == 'Y' if row[2] is not None else False,
                            "password_expired": row[3] == 'Y' if row[3] is not None else False,
                            "password_last_changed": row[4].isoformat() if row[4] else None,
                            "password_lifetime": row[5] if len(row) > 5 else None,
                            "max_connections": row[6] if len(row) > 6 else None,
                            "max_queries_per_hour": row[7] if len(row) > 7 else None,
                            "max_updates_per_hour": row[8] if len(row) > 8 else None,
                            "max_user_connections": row[9] if len(row) > 9 else None
                        })
                    except:
                        pass  # If parsing fails, just keep basic info
                
                security_data["user_accounts"].append(user_info)
        
        # Database privileges
        cursor.execute("""
            SELECT 
                user,
                host,
                db,
                select_priv,
                insert_priv,
                update_priv,
                delete_priv,
                create_priv,
                drop_priv,
                grant_priv,
                index_priv,
                alter_priv
            FROM mysql.db
            ORDER BY db, user, host
        """)
        
        db_privs = cursor.fetchall()
        security_data["database_privileges"] = [
            {
                "user": row[0],
                "host": row[1],
                "database": row[2],
                "privileges": {
                    "select": row[3] == 'Y',
                    "insert": row[4] == 'Y',
                    "update": row[5] == 'Y',
                    "delete": row[6] == 'Y',
                    "create": row[7] == 'Y',
                    "drop": row[8] == 'Y',
                    "grant": row[9] == 'Y',
                    "index": row[10] == 'Y',
                    "alter": row[11] == 'Y'
                }
            }
            for row in db_privs
        ]
        
        # SSL and encryption status
        cursor.execute("SHOW VARIABLES LIKE 'have_ssl';")
        ssl_status = cursor.fetchone()
        
        cursor.execute("SHOW VARIABLES LIKE 'ssl%';")
        ssl_vars = cursor.fetchall()
        
        security_data["ssl_configuration"] = {
            "ssl_available": ssl_status[1] if ssl_status else "UNKNOWN",
            "ssl_variables": {row[0]: row[1] for row in ssl_vars}
        }
        
        # Security-related system variables
        security_vars = [
            'local_infile', 'secure_file_priv', 'sql_mode', 
            'log_bin', 'binlog_format', 'general_log', 'slow_query_log'
        ]
        
        security_data["security_settings"] = {}
        for var in security_vars:
            try:
                cursor.execute(f"SELECT @@{var};")
                result = cursor.fetchone()
                security_data["security_settings"][var] = result[0] if result else None
            except:
                security_data["security_settings"][var] = "N/A"
        
        logging.info("Security analysis completed")
        
    except Exception as e:
        logging.error(f"Failed to get security analysis: {e}")
        security_data["error"] = str(e)
    
    return security_data

def get_mysql_overview():
    """Generate comprehensive MySQL database overview"""
    all_db_data = []
    conn = None
    cursor = None
    
    try:
        # Validate database configuration
        missing_config = [k for k, v in db_config.items() if not v]
        if missing_config:
            logging.error(f"Missing database configuration: {missing_config}")
            return False
            
        # Connect to MySQL
        logging.info("Connecting to MySQL database...")
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        logging.info("Successfully connected to MySQL database")
        
        # Get comprehensive server information
        logging.info("Analyzing MySQL server configuration...")
        server_info = get_mysql_server_info(cursor)
        
        # Get performance analysis
        logging.info("Performing database performance analysis...")
        performance_info = get_performance_analysis(cursor)
        
        # Get security analysis
        logging.info("Analyzing database security configuration...")
        security_info = get_security_analysis(cursor)
        
        # Get backup and replication status
        logging.info("Checking backup and replication status...")
        replication_info = {}
        try:
            # Check if this is a master server
            cursor.execute("SHOW MASTER STATUS;")
            master_status = cursor.fetchone()
            if master_status:
                replication_info["master_status"] = {
                    "file": master_status[0],
                    "position": master_status[1],
                    "binlog_do_db": master_status[2],
                    "binlog_ignore_db": master_status[3]
                }
            
            # Check if this is a slave server
            cursor.execute("SHOW SLAVE STATUS;")
            slave_status = cursor.fetchone()
            if slave_status and len(slave_status) > 0:
                replication_info["slave_status"] = {
                    "slave_io_running": slave_status[10] if len(slave_status) > 10 else None,
                    "slave_sql_running": slave_status[11] if len(slave_status) > 11 else None,
                    "master_host": slave_status[1] if len(slave_status) > 1 else None,
                    "master_port": slave_status[3] if len(slave_status) > 3 else None,
                    "seconds_behind_master": slave_status[32] if len(slave_status) > 32 else None
                }
            
            logging.info("Replication status checked")
        except Exception as e:
            logging.warning(f"Could not get replication status: {e}")
            replication_info["error"] = str(e)

        # Get all databases
        logging.info("Fetching database list...")
        cursor.execute("SHOW DATABASES;")
        databases_found = [db[0] for db in cursor.fetchall()]
        logging.info(f"Found {len(databases_found)} databases in total")

        # Filter and process target databases
        target_found = [db for db in databases_found if db in target_databases]
        target_missing = [db for db in target_databases if db not in databases_found]
        
        if target_missing:
            logging.warning(f"Target databases not found: {target_missing}")
        
        logging.info(f"Processing {len(target_found)} target databases: {target_found}")

        for db_name in target_found:
            logging.info(f"Processing database: {db_name}")

            # Get comprehensive database information
            current_db_info = get_database_info(cursor, db_name)
            current_db_info["tables"] = []
            current_db_info["processed_at"] = datetime.now().isoformat()

            try:
                cursor.execute(f"USE `{db_name}`;")
                logging.info(f"Switched to database: {db_name}")

                # Get all tables in the current database
                cursor.execute("SHOW TABLES;")
                tables = [table[0] for table in cursor.fetchall()]
                logging.info(f"Found {len(tables)} tables in {db_name}")

                if not tables:
                    logging.warning(f"No tables found in {db_name}")
                    all_db_data.append(current_db_info)
                    continue

                for table_name in tables:
                    current_table_info = {
                        "name": table_name,
                        "schema": [],
                        "sample_data": {
                            "columns": [],
                            "rows": []
                        },
                        "statistics": {},
                        "indexes": [],
                        "constraints": [],
                        "data_analysis": {}
                    }
                    logging.info(f"Processing table: {db_name}.{table_name}")

                    # Get table description (schema)
                    try:
                        cursor.execute(f"DESCRIBE `{table_name}`;")
                        schema_columns = [i[0] for i in cursor.description]
                        for col_info in cursor.fetchall():
                            col_dict = dict(zip(schema_columns, col_info))
                            current_table_info["schema"].append(col_dict)
                        logging.info(f"Schema fetched for {table_name} ({len(current_table_info['schema'])} columns)")
                    except mysql.connector.Error as err:
                        logging.error(f"Failed to fetch schema for {table_name}: {err}")
                        continue

                    # Get comprehensive table statistics with partitioning info
                    try:
                        # Enhanced table statistics including auto_increment and partitioning
                        cursor.execute(f"""
                            SELECT 
                                table_rows,
                                data_length,
                                index_length,
                                (data_length + index_length) as total_size,
                                avg_row_length,
                                create_time,
                                update_time,
                                table_collation,
                                engine,
                                auto_increment,
                                row_format,
                                table_comment
                            FROM information_schema.tables 
                            WHERE table_schema = '{db_name}' AND table_name = '{table_name}'
                        """)
                        
                        stats = cursor.fetchone()
                        if stats:
                            current_table_info["statistics"] = {
                                "row_count": stats[0] or 0,
                                "data_size_bytes": stats[1] or 0,
                                "index_size_bytes": stats[2] or 0,
                                "total_size_bytes": stats[3] or 0,
                                "avg_row_length": stats[4] or 0,
                                "created": stats[5].isoformat() if stats[5] else None,
                                "updated": stats[6].isoformat() if stats[6] else None,
                                "collation": stats[7],
                                "engine": stats[8],
                                "auto_increment": stats[9],
                                "row_format": stats[10],
                                "table_comment": stats[11]
                            }
                        
                        # Check for partitioning information
                        cursor.execute(f"""
                            SELECT 
                                partition_name,
                                partition_ordinal_position,
                                partition_method,
                                partition_expression,
                                partition_description,
                                table_rows,
                                avg_row_length,
                                data_length,
                                index_length
                            FROM information_schema.partitions 
                            WHERE table_schema = '{db_name}' AND table_name = '{table_name}' AND partition_name IS NOT NULL
                        """)
                        
                        partitions = cursor.fetchall()
                        if partitions:
                            current_table_info["partitioning"] = {
                                "is_partitioned": True,
                                "partition_count": len(partitions),
                                "partitions": [
                                    {
                                        "name": row[0],
                                        "position": row[1],
                                        "method": row[2],
                                        "expression": row[3],
                                        "description": row[4],
                                        "rows": row[5],
                                        "avg_row_length": row[6],
                                        "data_size": row[7],
                                        "index_size": row[8]
                                    }
                                    for row in partitions
                                ]
                            }
                        else:
                            current_table_info["partitioning"] = {"is_partitioned": False}
                        
                        logging.info(f"Enhanced statistics fetched for {table_name}: {current_table_info['statistics']['row_count']} rows")
                    except mysql.connector.Error as err:
                        logging.error(f"Failed to fetch statistics for {table_name}: {err}")

                    # Get indexes
                    try:
                        cursor.execute(f"SHOW INDEX FROM `{table_name}`;")
                        index_columns = [i[0] for i in cursor.description]
                        for index_info in cursor.fetchall():
                            index_dict = dict(zip(index_columns, index_info))
                            current_table_info["indexes"].append(index_dict)
                        logging.info(f"Indexes fetched for {table_name}: {len(current_table_info['indexes'])} indexes")
                    except mysql.connector.Error as err:
                        logging.error(f"Failed to fetch indexes for {table_name}: {err}")

                    # Get foreign key constraints
                    try:
                        cursor.execute(f"""
                            SELECT 
                                CONSTRAINT_NAME,
                                COLUMN_NAME,
                                REFERENCED_TABLE_NAME,
                                REFERENCED_COLUMN_NAME
                            FROM information_schema.KEY_COLUMN_USAGE
                            WHERE table_schema = '{db_name}' 
                            AND table_name = '{table_name}'
                            AND REFERENCED_TABLE_NAME IS NOT NULL
                        """)
                        
                        constraint_columns = [i[0] for i in cursor.description]
                        for constraint_info in cursor.fetchall():
                            constraint_dict = dict(zip(constraint_columns, constraint_info))
                            current_table_info["constraints"].append(constraint_dict)
                        logging.info(f"Constraints fetched for {table_name}: {len(current_table_info['constraints'])} foreign keys")
                    except mysql.connector.Error as err:
                        logging.error(f"Failed to fetch constraints for {table_name}: {err}")

                    # Enhanced sample data with data analysis
                    try:
                        # Get larger sample for better analysis
                        cursor.execute(f"SELECT * FROM `{table_name}` LIMIT 50;")
                        sample_rows = cursor.fetchall()

                        if cursor.description:
                            current_table_info["sample_data"]["columns"] = [i[0] for i in cursor.description]
                            current_table_info["sample_data"]["rows"] = [list(row) for row in sample_rows[:10]]  # Keep first 10 for display
                            
                            # Data analysis on larger sample
                            if sample_rows:
                                columns = current_table_info["sample_data"]["columns"]
                                for col_index, col_name in enumerate(columns):
                                    values = [row[col_index] for row in sample_rows if row[col_index] is not None]
                                    
                                    analysis = {
                                        "non_null_count": len(values),
                                        "null_count": len(sample_rows) - len(values),
                                        "unique_count": len(set(str(v) for v in values)) if values else 0,
                                        "data_type": type(values[0]).__name__ if values else "unknown"
                                    }
                                    
                                    # Additional analysis for numeric data
                                    if values and isinstance(values[0], (int, float)):
                                        try:
                                            analysis.update({
                                                "min_value": min(values),
                                                "max_value": max(values),
                                                "avg_value": sum(values) / len(values)
                                            })
                                        except:
                                            pass
                                    
                                    # Sample unique values for categorical data
                                    if analysis["unique_count"] <= 20:
                                        analysis["sample_values"] = list(set(str(v) for v in values))[:10]
                                    
                                    current_table_info["data_analysis"][col_name] = analysis
                        
                        logging.info(f"Enhanced sample data fetched for {table_name} ({len(sample_rows)} rows analyzed)")

                    except mysql.connector.Error as err:
                        logging.error(f"Failed to fetch sample data for {table_name}: {err}")

                    current_db_info["tables"].append(current_table_info)

            except mysql.connector.Error as err:
                logging.error(f"Error accessing database {db_name}: {err}")
                continue

            all_db_data.append(current_db_info)
            logging.info(f"Completed processing database: {db_name}")

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
        if conn and conn.is_connected():
            conn.close()
            logging.info("Database connection closed")

    # Create output directory and handle old file migration
    try:
        os.makedirs(mysql_overview_dir, exist_ok=True)
        logging.info(f"Ensured output directory exists: {mysql_overview_dir}")
        
        # Check for old file and move it to new location
        old_file_path = r'C:\Users\laoan\Documents\mysql_database_overview.json'
        if os.path.exists(old_file_path):
            import shutil
            # Create timestamped filename for the old file
            old_timestamp = datetime.fromtimestamp(os.path.getmtime(old_file_path)).strftime("%Y%m%d_%H%M%S")
            old_filename = f"mysql_database_overview_{old_timestamp}_migrated.json"
            new_old_file_path = os.path.join(mysql_overview_dir, old_filename)
            
            shutil.move(old_file_path, new_old_file_path)
            logging.info(f"Migrated old file from {old_file_path} to {new_old_file_path}")
        else:
            logging.info("No old file found to migrate")
            
    except Exception as e:
        logging.error(f"Failed to create output directory or migrate old file: {e}")
        return False

    # Write JSON overview file
    try:
        current_timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        json_filename = f"mysql_database_overview_{current_timestamp}.json"
        json_filepath = os.path.join(mysql_overview_dir, json_filename)
        
        overview_data = {
            "generated_at": datetime.now().isoformat(),
            "server_info": server_info,
            "performance_analysis": performance_info,
            "security_analysis": security_info,
            "replication_status": replication_info,
            "summary": {
                "total_databases": len(all_db_data),
                "total_tables": sum(len(db["tables"]) for db in all_db_data),
                "total_size_mb": sum(db.get("total_size_mb", 0) for db in all_db_data),
                "largest_database": max(all_db_data, key=lambda x: x.get("total_size_mb", 0))["name"] if all_db_data else None,
                "engine_distribution": {}
            },
            "databases": all_db_data
        }
        
        # Calculate engine distribution across all databases
        engine_counts = {}
        for db in all_db_data:
            for engine_info in db.get("storage_engines", []):
                engine = engine_info["engine"]
                engine_counts[engine] = engine_counts.get(engine, 0) + engine_info["table_count"]
        overview_data["summary"]["engine_distribution"] = engine_counts
        
        with open(json_filepath, 'w', encoding='utf-8') as f:
            json.dump(overview_data, f, indent=4, default=json_serial, ensure_ascii=False)
        
        logging.info(f"Successfully wrote database overview to: {json_filepath}")
        
        # Also create a latest copy
        latest_filepath = os.path.join(mysql_overview_dir, "mysql_database_overview_latest.json")
        with open(latest_filepath, 'w', encoding='utf-8') as f:
            json.dump(overview_data, f, indent=4, default=json_serial, ensure_ascii=False)
        
        logging.info(f"Also created latest copy at: {latest_filepath}")
        return True
        
    except IOError as e:
        logging.error(f"Failed to write output file: {e}")
        return False
    except Exception as e:
        logging.exception(f"Error occurred while writing JSON: {e}")
        return False

def main():
    start = datetime.now()
    # Console delimiter (clean display)
    print("="*50)
    print("MYSQL DATABASE OVERVIEW SCRIPT STARTED")
    print("="*50)
    # File logging (with timestamps)
    logging.info("="*50)
    logging.info("MYSQL DATABASE OVERVIEW SCRIPT STARTED")
    logging.info("="*50)
    
    success = get_mysql_overview()
    
    duration = datetime.now() - start
    if success:
        logging.info(f"Script completed successfully. Duration: {duration}.")
    else:
        logging.error(f"Script failed. Duration: {duration}.")

if __name__ == "__main__":
    main()