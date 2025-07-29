<?php
/**
 * File: philosophy-redesign.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\philosophy-redesign.php
 * Description: Redesigned philosophy page for PSW 4.0 using new design system
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
    include __DIR__ . '/templates/pages/philosophy-redesign.php';
    $content = ob_get_clean();
    
    // Include redesigned base layout
    include __DIR__ . '/templates/layouts/base-redesign.php';
    
} catch (Exception $e) {
    // Log error and show generic error page
    Logger::error('Application error on philosophy page: ' . $e->getMessage());
    
    $pageTitle = 'Error - ' . APP_NAME;
    $content = '
        <div class="psw-card">
            <div class="psw-card-content" style="text-align: center; padding: var(--spacing-8);">
                <i class="fas fa-exclamation-triangle" style="font-size: var(--font-size-4xl); color: var(--error-color); margin-bottom: var(--spacing-4);"></i>
                <h1 style="color: var(--text-primary); margin-bottom: var(--spacing-4);">System Error</h1>
                <p style="color: var(--text-secondary); margin-bottom: var(--spacing-2);">We apologize, but there was an error loading the philosophy page.</p>
                <p style="color: var(--text-muted);">Please try again later or contact support if the problem persists.</p>
            </div>
        </div>
    ';
    
    if (APP_DEBUG) {
        $content .= '
            <div class="psw-alert psw-alert-error psw-mb-4">
                <strong>Debug:</strong> ' . htmlspecialchars($e->getMessage()) . '
            </div>
        ';
    }
    
    include __DIR__ . '/templates/layouts/base-redesign.php';
}
?>