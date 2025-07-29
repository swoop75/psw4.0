<?php
/**
 * File: dashboard-redesign-noauth.php
 * Description: Redesigned dashboard WITHOUT authentication (for testing only)
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

// BYPASS AUTHENTICATION FOR TESTING
// Auth::requireAuth(); // COMMENTED OUT

// Create fake session for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'testuser';
    $_SESSION['user_role'] = 'user';
    $_SESSION['last_activity'] = time();
}

try {
    // Initialize dashboard controller
    $dashboardController = new DashboardController();
    
    // Get dashboard data
    $dashboardData = $dashboardController->getDashboardData();
    
    // Set page variables
    $pageTitle = 'Dashboard - ' . APP_NAME . ' (No Auth Test)';
    $pageDescription = 'Portfolio overview and key metrics';
    $additionalJS = [ASSETS_URL . '/js/dashboard.js']; // For chart functionality
    
    // Prepare content
    ob_start();
    include __DIR__ . '/templates/pages/dashboard-redesign.php';
    $content = ob_get_clean();
    
    // Include redesigned base layout
    include __DIR__ . '/templates/layouts/base-redesign.php';
    
    // Log dashboard access
    Logger::logUserAction('dashboard_viewed', 'User accessed redesigned dashboard (no auth test)');
    
} catch (Exception $e) {
    Logger::error('Dashboard error: ' . $e->getMessage());
    
    $pageTitle = 'Dashboard Error - ' . APP_NAME;
    $content = '
        <div class="psw-card">
            <div class="psw-card-content" style="text-align: center; padding: var(--spacing-8);">
                <i class="fas fa-exclamation-triangle" style="font-size: var(--font-size-4xl); color: var(--error-color); margin-bottom: var(--spacing-4);"></i>
                <h1 style="color: var(--text-primary); margin-bottom: var(--spacing-4);">Dashboard Error</h1>
                <p style="color: var(--text-secondary); margin-bottom: var(--spacing-2);">We apologize, but there was an error loading your dashboard.</p>
                <p style="color: var(--text-muted);">Error: ' . htmlspecialchars($e->getMessage()) . '</p>
            </div>
        </div>
    ';
    
    include __DIR__ . '/templates/layouts/base-redesign.php';
}
?>