<?php
/**
 * File: src/utils/Logger.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\src\utils\Logger.php
 * Description: Logging utility class for PSW 4.0 - handles application logging
 */

class Logger {
    private static $logFile;
    
    /**
     * Initialize logger
     */
    private static function init() {
        if (!self::$logFile) {
            $date = date('Y-m-d');
            self::$logFile = LOG_PATH . "/psw_{$date}.log";
            
            // Create log directory if it doesn't exist
            if (!file_exists(LOG_PATH)) {
                mkdir(LOG_PATH, 0755, true);
            }
        }
    }
    
    /**
     * Log message with level
     * @param string $level Log level (INFO, WARNING, ERROR, DEBUG)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private static function log($level, $message, $context = []) {
        self::init();
        
        $timestamp = date(DATETIME_FORMAT);
        $userId = $_SESSION['user_id'] ?? 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'user_id' => $userId,
            'ip' => $ip,
            'message' => $message
        ];
        
        if (!empty($context)) {
            $logEntry['context'] = $context;
        }
        
        $logLine = json_encode($logEntry) . PHP_EOL;
        file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log info message
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    /**
     * Log warning message
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Log error message
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * Log debug message
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function debug($message, $context = []) {
        if (APP_DEBUG) {
            self::log('DEBUG', $message, $context);
        }
    }
    
    /**
     * Log user authentication events
     * @param string $username Username attempting login
     * @param bool $success Whether login was successful
     * @param string $reason Failure reason if applicable
     */
    public static function logAuth($username, $success, $reason = '') {
        $message = $success ? "Successful login for user: {$username}" : "Failed login attempt for user: {$username}";
        $context = [
            'username' => $username,
            'success' => $success,
            'reason' => $reason,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        if ($success) {
            self::info($message, $context);
        } else {
            self::warning($message, $context);
        }
    }
    
    /**
     * Log database operations
     * @param string $operation Operation type (SELECT, INSERT, UPDATE, DELETE)
     * @param string $table Table name
     * @param array $data Additional data
     */
    public static function logDatabase($operation, $table, $data = []) {
        $message = "Database operation: {$operation} on table {$table}";
        $context = [
            'operation' => $operation,
            'table' => $table,
            'data' => $data
        ];
        
        self::debug($message, $context);
    }
    
    /**
     * Log user actions
     * @param string $action Action performed
     * @param string $details Action details
     */
    public static function logUserAction($action, $details = '') {
        $userId = $_SESSION['user_id'] ?? 'anonymous';
        $message = "User action: {$action}";
        $context = [
            'action' => $action,
            'details' => $details,
            'user_id' => $userId
        ];
        
        self::info($message, $context);
    }
}