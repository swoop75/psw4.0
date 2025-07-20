<?php
/**
 * File: public/login.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\public\login.php
 * Description: Login handler for PSW 4.0 - processes authentication requests
 */

// Start session and include required files
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/utils/Security.php';
require_once __DIR__ . '/../src/utils/Logger.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

// Handle POST request (login attempt)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['login_error'] = 'Security token invalid. Please try again.';
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        
        // Get and validate input
        $username = Security::sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'Please enter both username and password.';
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        
        // Check rate limiting
        $identifier = $_SERVER['REMOTE_ADDR'] . '_' . $username;
        if (!Security::checkRateLimit($identifier)) {
            $_SESSION['login_error'] = 'Too many login attempts. Please try again in 15 minutes.';
            Logger::warning('Rate limit exceeded for login attempt', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        
        // Attempt authentication
        $userModel = new User();
        $result = $userModel->authenticate($username, $password);
        
        if ($result['success']) {
            // Login successful
            Auth::login($result['user']);
            Security::resetLoginAttempts($identifier);
            
            // Set success message
            $_SESSION['flash_success'] = 'Welcome back, ' . $result['user']['username'] . '!';
            
            // Redirect to dashboard
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            // Login failed
            Security::recordLoginAttempt($identifier);
            $_SESSION['login_error'] = $result['message'] ?? 'Invalid username or password.';
            
            Logger::warning('Failed login attempt', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'reason' => $result['message'] ?? 'Unknown'
            ]);
            
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        
    } catch (Exception $e) {
        Logger::error('Login error: ' . $e->getMessage());
        $_SESSION['login_error'] = 'An error occurred during login. Please try again.';
        header('Location: ' . BASE_URL . '/');
        exit;
    }
}

// Handle GET request (logout)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    Auth::logout();
    $_SESSION['flash_info'] = 'You have been logged out successfully.';
    header('Location: ' . BASE_URL . '/');
    exit;
}

// If we reach here, redirect to home page
header('Location: ' . BASE_URL . '/');
exit;
?>