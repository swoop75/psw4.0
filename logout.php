<?php
/**
 * File: public/logout.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\public\logout.php
 * Description: Logout handler for PSW 4.0
 */

// Start session and include required files
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/utils/Logger.php';

// Perform logout
Auth::logout();

// Start new session for flash message
session_start();
$_SESSION['flash_info'] = 'You have been logged out successfully.';

// Redirect to home page
header('Location: ' . BASE_URL . '/');
exit;
?>