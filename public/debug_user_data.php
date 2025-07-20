<?php
/**
 * Debug user data structure
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/controllers/UserManagementController.php';

if (!Auth::isLoggedIn()) {
    echo "Not logged in";
    exit;
}

$controller = new UserManagementController();
$userId = Auth::getUserId();

echo "=== User Data Debug ===\n";
echo "User ID from Auth: $userId\n\n";

try {
    $profileData = $controller->getUserProfile();
    echo "Profile data structure:\n";
    echo "User data: " . print_r($profileData['user'], true) . "\n";
    
    // Test direct User model call
    require_once __DIR__ . '/../src/models/User.php';
    $userModel = new User();
    $directUser = $userModel->findById($userId);
    echo "Direct User model data:\n";
    echo print_r($directUser, true) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>