<?php
/**
 * File: public/logs_dividends.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\public\logs_dividends.php
 * Description: Dividend logs page for PSW 4.0 - shows complete dividend transaction history
 */

// Start session and include required files
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/controllers/DividendLogsController.php';
require_once __DIR__ . '/../src/utils/Security.php';
require_once __DIR__ . '/../src/utils/Logger.php';

// Require authentication
Auth::requireAuth();

try {
    // Initialize controller
    $controller = new DividendLogsController();
    
    // Handle filters and pagination
    $filters = [
        'year' => Security::sanitizeInput($_GET['year'] ?? ''),
        'company' => Security::sanitizeInput($_GET['company'] ?? ''),
        'currency' => Security::sanitizeInput($_GET['currency'] ?? ''),
        'amount_min' => Security::sanitizeInput($_GET['amount_min'] ?? ''),
        'amount_max' => Security::sanitizeInput($_GET['amount_max'] ?? ''),
        'date_from' => Security::sanitizeInput($_GET['date_from'] ?? ''),
        'date_to' => Security::sanitizeInput($_GET['date_to'] ?? ''),
        'sort' => Security::sanitizeInput($_GET['sort'] ?? 'ex_date'),
        'order' => Security::sanitizeInput($_GET['order'] ?? 'DESC')
    ];
    
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(10, min(200, (int) ($_GET['per_page'] ?? ITEMS_PER_PAGE)));
    
    // Get dividend logs data
    $logsData = $controller->getDividendLogs($filters, $page, $perPage);
    
    // Set page variables
    $pageTitle = 'Dividend Logs - ' . APP_NAME;
    $pageDescription = 'Complete dividend transaction history and analytics';
    $additionalCSS = [ASSETS_URL . '/css/dividend-logs.css'];
    $additionalJS = [ASSETS_URL . '/js/dividend-logs.js'];
    
    // Prepare content
    ob_start();
    include __DIR__ . '/../templates/pages/dividend-logs.php';
    $content = ob_get_clean();
    
    // Include base layout
    include __DIR__ . '/../templates/layouts/base.php';
    
    // Log page access
    Logger::logUserAction('dividend_logs_viewed', 'User accessed dividend logs with filters', $filters);
    
} catch (Exception $e) {
    Logger::error('Dividend logs page error: ' . $e->getMessage());
    
    $pageTitle = 'Dividend Logs Error - ' . APP_NAME;
    $content = '
        <div class="error-container text-center">
            <h1>Dividend Logs Error</h1>
            <p>We apologize, but there was an error loading the dividend logs.</p>
            <p class="text-muted">Please try refreshing the page or contact support if the problem persists.</p>
        </div>
    ';
    
    if (APP_DEBUG) {
        $content .= '<div class="alert alert-error mt-3"><strong>Debug:</strong> ' . $e->getMessage() . '</div>';
    }
    
    include __DIR__ . '/../templates/layouts/base.php';
}
?>