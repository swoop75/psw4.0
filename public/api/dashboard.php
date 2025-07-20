<?php
/**
 * File: public/api/dashboard.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\public\api\dashboard.php
 * Description: API endpoint for dashboard data in PSW 4.0
 */

// Start session and include required files
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/controllers/DashboardController.php';
require_once __DIR__ . '/../../src/utils/Logger.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Require authentication for API access
    if (!Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required',
            'timestamp' => time()
        ]);
        exit;
    }
    
    // Handle different HTTP methods
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;
        case 'POST':
            handlePostRequest();
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed',
                'timestamp' => time()
            ]);
            break;
    }
    
} catch (Exception $e) {
    Logger::error('Dashboard API error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'debug' => APP_DEBUG ? $e->getMessage() : null,
        'timestamp' => time()
    ]);
}

/**
 * Handle GET requests - return dashboard data
 */
function handleGetRequest() {
    try {
        $dashboardController = new DashboardController();
        $response = $dashboardController->getDashboardDataForAPI();
        
        http_response_code(200);
        echo json_encode($response);
        
        Logger::logUserAction('dashboard_api_accessed', 'Dashboard data retrieved via API');
        
    } catch (Exception $e) {
        Logger::error('Dashboard GET API error: ' . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to retrieve dashboard data',
            'timestamp' => time()
        ]);
    }
}

/**
 * Handle POST requests - update dashboard settings or trigger actions
 */
function handlePostRequest() {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON input',
                'timestamp' => time()
            ]);
            return;
        }
        
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'refresh_data':
                handleRefreshData();
                break;
            case 'update_preferences':
                handleUpdatePreferences($input);
                break;
            default:
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Unknown action',
                    'timestamp' => time()
                ]);
                break;
        }
        
    } catch (Exception $e) {
        Logger::error('Dashboard POST API error: ' . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to process request',
            'timestamp' => time()
        ]);
    }
}

/**
 * Handle data refresh request
 */
function handleRefreshData() {
    try {
        $dashboardController = new DashboardController();
        $response = $dashboardController->getDashboardDataForAPI();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Dashboard data refreshed',
            'data' => $response['data'],
            'timestamp' => time()
        ]);
        
        Logger::logUserAction('dashboard_refresh', 'Dashboard data manually refreshed');
        
    } catch (Exception $e) {
        Logger::error('Dashboard refresh error: ' . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to refresh data',
            'timestamp' => time()
        ]);
    }
}

/**
 * Handle update preferences request
 */
function handleUpdatePreferences($input) {
    try {
        // TODO: Implement user preference updates for dashboard
        // This could include default chart views, refresh intervals, etc.
        
        $preferences = $input['preferences'] ?? [];
        $userId = Auth::getUserId();
        
        // For now, just return success
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'timestamp' => time()
        ]);
        
        Logger::logUserAction('dashboard_preferences_updated', 'User updated dashboard preferences');
        
    } catch (Exception $e) {
        Logger::error('Dashboard preferences update error: ' . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update preferences',
            'timestamp' => time()
        ]);
    }
}
?>