<?php
/**
 * File: config/database.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\config\database.php
 * Description: Database configuration and connection management for PSW 4.0
 * Handles connections to three databases: psw_foundation, psw_marketdata, psw_portfolio
 */

class Database {
    private static $instances = [];
    
    // Database configuration
    private static $config = [
        'foundation' => [
            'host' => 'localhost',
            'dbname' => 'psw_foundation',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4'
        ],
        'marketdata' => [
            'host' => 'localhost',
            'dbname' => 'psw_marketdata',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4'
        ],
        'portfolio' => [
            'host' => 'localhost',
            'dbname' => 'psw_portfolio',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4'
        ]
    ];
    
    /**
     * Get database connection instance
     * @param string $database Database name (foundation, marketdata, portfolio)
     * @return PDO Database connection
     */
    public static function getConnection($database = 'foundation') {
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
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_FOUND_ROWS => true
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