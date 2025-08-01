<?php
/**
 * File: philosophy.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\philosophy.php
 * Description: Philosophy page for PSW 4.0 - integrated with unified navigation
 */

// Start session and include required files
session_start();

// Include configuration and dependencies
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/utils/Security.php';
require_once __DIR__ . '/src/utils/Logger.php';

// Initialize variables for template
$pageTitle = 'My Investment Philosophy - PSW 4.0';
$pageDescription = 'A Disciplined Approach to Dividend Investing';

try {
    // Prepare content for philosophy page
    ob_start();
    include __DIR__ . '/templates/pages/philosophy.php';
    $content = ob_get_clean();
    
    // Include base layout
    include __DIR__ . '/templates/layouts/base.php';
    
} catch (Exception $e) {
    // Log error and show generic error page
    Logger::error('Application error on philosophy page: ' . $e->getMessage());
    
    $pageTitle = 'Error - ' . APP_NAME;
    $content = '
        <div class="error-container text-center">
            <h1>System Error</h1>
            <p>We apologize, but there was an error loading the philosophy page.</p>
            <p class="text-muted">Please try again later or contact support if the problem persists.</p>
        </div>
    ';
    
    if (APP_DEBUG) {
        $content .= '<div class="alert alert-error mt-3"><strong>Debug:</strong> ' . $e->getMessage() . '</div>';
    }
    
    include __DIR__ . '/templates/layouts/base.php';
}
?>