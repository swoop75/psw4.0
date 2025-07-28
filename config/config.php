<?php
/**
 * File: config/config.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\config\config.php
 * Description: Main application configuration for PSW 4.0
 */

// Application settings
define('APP_NAME', 'PSW 4.0');
define('APP_FULL_NAME', 'Pengamaskinen Sverige + Worldwide');
define('APP_VERSION', '4.0.0');
define('APP_DEBUG', true);

// Base paths
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('TEMPLATE_PATH', BASE_PATH . '/templates');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('ASSETS_PATH', BASE_PATH . '/assets');

// URL configuration
define('BASE_URL', 'http://100.117.171.98');
define('ASSETS_URL', BASE_URL . '/assets');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);

// Pagination settings
define('ITEMS_PER_PAGE', 50);
define('COMPANIES_PER_PAGE', 100);

// Date format settings
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');

// Currency settings
define('BASE_CURRENCY', 'SEK');
define('CURRENCY_PRECISION', 2);

// Logging settings
define('LOG_PATH', STORAGE_PATH . '/logs');
define('LOG_LEVEL', 'INFO');

// File upload settings
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_UPLOAD_TYPES', ['csv', 'pdf', 'xlsx']);

// API settings
define('API_TIMEOUT', 30);
define('API_RETRY_ATTEMPTS', 3);

// Performance settings
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 3600);

// Database settings - Load from environment or use defaults
define('DB_HOST', $_ENV['DB_HOST'] ?? '100.117.171.98');
define('DB_USER', $_ENV['DB_USERNAME'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? '');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}