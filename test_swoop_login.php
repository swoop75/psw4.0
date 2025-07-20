<?php
/**
 * Test swoop user authentication
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/models/User.php';
require_once __DIR__ . '/src/utils/Security.php';

echo "=== Testing Swoop User Authentication ===\n";

try {
    $userModel = new User();
    
    // Check if swoop user exists
    echo "1. Checking if swoop user exists...\n";
    $db = Database::getConnection('foundation');
    $stmt = $db->prepare("SELECT user_id, username, email, password_hash, role_id FROM users WHERE username = :username");
    $stmt->bindValue(':username', 'swoop');
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ User 'swoop' found:\n";
        echo "   - User ID: " . $user['user_id'] . "\n";
        echo "   - Email: " . $user['email'] . "\n";
        echo "   - Role ID: " . $user['role_id'] . "\n";
        echo "   - Password hash: " . substr($user['password_hash'], 0, 20) . "...\n\n";
        
        // Test authentication with empty password
        echo "2. Testing authentication with empty password...\n";
        $result = $userModel->authenticate('swoop', '');
        echo "Result: " . json_encode($result) . "\n\n";
        
        // Test authentication with common passwords
        $testPasswords = ['password', 'swoop', '123456', 'admin', 'test'];
        echo "3. Testing common passwords...\n";
        foreach ($testPasswords as $password) {
            echo "Testing password: '$password' - ";
            $result = $userModel->authenticate('swoop', $password);
            echo ($result['success'] ? "✅ SUCCESS" : "❌ FAILED") . "\n";
            if ($result['success']) {
                break;
            }
        }
        
        // Show password hash details
        echo "\n4. Password hash details:\n";
        echo "Hash algorithm: " . password_get_info($user['password_hash'])['algoName'] . "\n";
        echo "Full hash: " . $user['password_hash'] . "\n";
        
    } else {
        echo "❌ User 'swoop' not found!\n";
        
        // Show all users
        echo "\nAvailable users:\n";
        $stmt = $db->query("SELECT user_id, username, email FROM users");
        $users = $stmt->fetchAll();
        foreach ($users as $u) {
            echo "- " . $u['username'] . " (ID: " . $u['user_id'] . ", Email: " . $u['email'] . ")\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>