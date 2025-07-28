<?php
/**
 * AJAX endpoint for user editing
 * Separate endpoint to avoid conflicts with main page
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/controllers/UserManagementController.php';
require_once __DIR__ . '/src/utils/Logger.php';
require_once __DIR__ . '/src/utils/Security.php';

// Set JSON header immediately
header('Content-Type: application/json');

try {
    // Check authentication
    if (!Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    // Check admin privileges
    $adminRoles = ['Admin', 'admin', 'Administrator', 'administrator'];
    $isAdmin = isset($_SESSION['role_name']) && in_array($_SESSION['role_name'], $adminRoles);
    
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Admin privileges required']);
        exit;
    }

    // Only handle POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
        exit;
    }

    // Validate CSRF token
    if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    // Process the edit user request
    $controller = new UserManagementController();
    $result = $controller->editUser($_POST);
    
    // Log the result
    Logger::info('AJAX edit user result: ' . json_encode($result));
    
    // Return JSON response
    echo json_encode($result);
    
} catch (Exception $e) {
    Logger::error('AJAX edit user error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

exit;
?>