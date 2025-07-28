<?php
/**
 * File: config/database.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\config\database.php
 * Description: Database configuration and connection management for PSW 4.0
 * Handles connections to three databases: psw_foundation, psw_marketdata, psw_portfolio
 */

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

class Database {
    private static $instances = [];
    
    // Database configuration
    private static $config = null;
    
    /**
     * Initialize database configuration
     */
    private static function initConfig() {
        if (self::$config === null) {
            self::$config = [
                'foundation' => [
                    'host' => $_ENV['DB_HOST'] ?? '100.117.171.98',
                    'dbname' => $_ENV['DB_FOUNDATION'] ?? 'psw_foundation',
                    'username' => $_ENV['DB_USERNAME'] ?? 'root',
                    'password' => $_ENV['DB_PASSWORD'] ?? '',
                    'charset' => 'utf8mb4'
                ],
                'marketdata' => [
                    'host' => $_ENV['DB_HOST'] ?? '100.117.171.98',
                    'dbname' => $_ENV['DB_MARKETDATA'] ?? 'psw_marketdata',
                    'username' => $_ENV['DB_USERNAME'] ?? 'root',
                    'password' => $_ENV['DB_PASSWORD'] ?? '',
                    'charset' => 'utf8mb4'
                ],
                'portfolio' => [
                    'host' => $_ENV['DB_HOST'] ?? '100.117.171.98',
                    'dbname' => $_ENV['DB_PORTFOLIO'] ?? 'psw_portfolio',
                    'username' => $_ENV['DB_USERNAME'] ?? 'root',
                    'password' => $_ENV['DB_PASSWORD'] ?? '',
                    'charset' => 'utf8mb4'
                ]
            ];
        }
    }
    
    /**
     * Get database connection instance
     * @param string $database Database name (foundation, marketdata, portfolio)
     * @return PDO Database connection
     */
    public static function getConnection($database = 'foundation') {
        self::initConfig();
        if (!isset(self::$instances[$database])) {
            if (!isset(self::$config[$database])) {
                throw new InvalidArgumentException("Database configuration for '{$database}' not found");
            }
            
            $config = self::$config[$database];
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            
            try {
                self::$instances[$database] = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                error_log("Database connection failed for {$database}: " . $e->getMessage());
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }
        
        return self::$instances[$database];
    }
    
    /**
     * Test all database connections
     * @return array Connection status for each database
     */
    public static function testConnections() {
        self::initConfig();
        $results = [];
        foreach (array_keys(self::$config) as $database) {
            try {
                $conn = self::getConnection($database);
                $stmt = $conn->query("SELECT 1");
                $results[$database] = $stmt ? 'Connected' : 'Failed';
            } catch (Exception $e) {
                $results[$database] = 'Error: ' . $e->getMessage();
            }
        }
        return $results;
    }
}