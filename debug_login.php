<?php
/**
 * Debug Login Script
 * Check what's happening with the login process
 */

// Start session and include files
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/src/models/User.php';
require_once __DIR__ . '/src/utils/Security.php';

echo "=== LOGIN DEBUG ===\n";

// Test credentials
$username = 'swoop';
$password = 'admin123';

echo "Testing login for: $username\n";
echo "Password: $password\n\n";

// Create User instance and test authentication
try {
    $userModel = new User();
    $result = $userModel->authenticate($username, $password);
    
    echo "Authentication result:\n";
    print_r($result);
    
    // Also test finding the user
    echo "\n=== USER LOOKUP ===\n";
    $user = $userModel->findById(1); // Assuming swoop is user ID 1
    if ($user) {
        echo "Found user by ID 1:\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Hash starts with: " . substr($user['password_hash'], 0, 20) . "...\n";
        
        // Test password verification directly
        echo "\nDirect password verification:\n";
        $hashMatches = Security::verifyPassword($password, $user['password_hash']);
        echo "Password matches hash: " . ($hashMatches ? 'YES' : 'NO') . "\n";
    } else {
        echo "No user found with ID 1\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "This likely means the database connection failed.\n";
    echo "Please ensure MySQL is running and accessible.\n";
}

echo "\n=== END DEBUG ===\n";
?>