<?php
/**
 * File: public/dashboard.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\public\dashboard.php
 * Description: Main dashboard page for PSW 4.0 - displays portfolio overview and key metrics
 */

// Start session and include required files
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/controllers/DashboardController.php';
require_once __DIR__ . '/src/utils/Security.php';
require_once __DIR__ . '/src/utils/Logger.php';

// Require authentication
Auth::requireAuth();

try {
    // Initialize dashboard controller
    $dashboardController = new DashboardController();
    
    // Get dashboard data
    $dashboardData = $dashboardController->getDashboardData();
    
    // Set page variables
    $pageTitle = 'Dashboard - ' . APP_NAME;
    $pageDescription = 'Portfolio overview and key metrics';
    $additionalCSS = [ASSETS_URL . '/css/improved-dashboard.css?v=' . time()];
    $additionalJS = [ASSETS_URL . '/js/dashboard.js'];
    
    // Prepare content
    ob_start();
    include __DIR__ . '/templates/pages/dashboard.php';
    $content = ob_get_clean();
    
    // Include base layout
    include __DIR__ . '/templates/layouts/base.php';
    
    // Log dashboard access
    Logger::logUserAction('dashboard_viewed', 'User accessed dashboard');
    
} catch (Exception $e) {
    Logger::error('Dashboard error: ' . $e->getMessage());
    
    $pageTitle = 'Dashboard Error - ' . APP_NAME;
    $content = '
        <div class="error-container text-center">
            <h1>Dashboard Error</h1>
            <p>We apologize, but there was an error loading your dashboard.</p>
            <p class="text-muted">Please try refreshing the page or contact support if the problem persists.</p>
        </div>
    ';
    
    if (APP_DEBUG) {
        $content .= '<div class="alert alert-error mt-3"><strong>Debug:</strong> ' . $e->getMessage() . '</div>';
    }
    
    include __DIR__ . '/templates/layouts/base.php';
}
?>