<?php
/**
 * File: public/dividend_estimate.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\public\dividend_estimate.php
 * Description: Dividend estimate overview page for PSW 4.0
 */

// Start session and include required files
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/controllers/DividendEstimateController.php';
require_once __DIR__ . '/../src/utils/Security.php';
require_once __DIR__ . '/../src/utils/Logger.php';

// Require authentication
Auth::requireAuth();

try {
    // Initialize controller
    $controller = new DividendEstimateController();
    
    // Get dividend estimate data
    $estimateData = $controller->getOverviewData();
    
    // Set page variables
    $pageTitle = 'Dividend Estimate - ' . APP_NAME;
    $pageDescription = 'Dividend income estimates and forecasts';
    $additionalCSS = [ASSETS_URL . '/css/dividend-estimate.css'];
    $additionalJS = [ASSETS_URL . '/js/dividend-estimate.js'];
    
    // Prepare content
    ob_start();
    include __DIR__ . '/../templates/pages/dividend-estimate.php';
    $content = ob_get_clean();
    
    // Include base layout
    include __DIR__ . '/../templates/layouts/base.php';
    
    // Log page access
    Logger::logUserAction('dividend_estimate_viewed', 'User accessed dividend estimate overview');
    
} catch (Exception $e) {
    Logger::error('Dividend estimate page error: ' . $e->getMessage());
    
    $pageTitle = 'Dividend Estimate Error - ' . APP_NAME;
    $content = '
        <div class="error-container text-center">
            <h1>Dividend Estimate Error</h1>
            <p>We apologize, but there was an error loading the dividend estimates.</p>
            <p class="text-muted">Please try refreshing the page or contact support if the problem persists.</p>
        </div>
    ';
    
    if (APP_DEBUG) {
        $content .= '<div class="alert alert-error mt-3"><strong>Debug:</strong> ' . $e->getMessage() . '</div>';
    }
    
    include __DIR__ . '/../templates/layouts/base.php';
}
?>