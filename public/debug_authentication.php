<?php
/**
 * Debug authentication process step by step
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/utils/Security.php';

echo "=== Debugging Authentication Process ===\n";

try {
    $username = 'swoop';
    $password = 'admin123';
    
    echo "1. Testing database connection...\n";
    $db = Database::getConnection('foundation');
    echo "✅ Database connected\n\n";
    
    echo "2. Looking up user in database...\n";
    $sql = "SELECT u.user_id, u.username, u.email, u.password_hash, u.role_id, r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.role_id 
            WHERE u.username = :username OR u.email = :username";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ User found:\n";
        echo "   - User ID: " . $user['user_id'] . "\n";
        echo "   - Username: " . $user['username'] . "\n";
        echo "   - Email: " . $user['email'] . "\n";
        echo "   - Role ID: " . $user['role_id'] . "\n";
        echo "   - Role Name: " . $user['role_name'] . "\n";
        echo "   - Password Hash: " . $user['password_hash'] . "\n\n";
        
        echo "3. Testing password verification...\n";
        echo "Password to test: '$password'\n";
        echo "Stored hash: " . $user['password_hash'] . "\n";
        
        $passwordMatch = Security::verifyPassword($password, $user['password_hash']);
        echo "Password verification result: " . ($passwordMatch ? '✅ MATCH' : '❌ NO MATCH') . "\n\n";
        
        // Test with PHP's password_verify directly
        echo "4. Testing with PHP password_verify directly...\n";
        $phpVerify = password_verify($password, $user['password_hash']);
        echo "PHP password_verify result: " . ($phpVerify ? '✅ MATCH' : '❌ NO MATCH') . "\n\n";
        
        // Check the Security::verifyPassword method
        echo "5. Checking Security::verifyPassword method...\n";
        $securityFile = file_get_contents(__DIR__ . '/../src/utils/Security.php');
        if (strpos($securityFile, 'function verifyPassword') !== false) {
            echo "✅ Security::verifyPassword method exists\n";
        } else {
            echo "❌ Security::verifyPassword method not found\n";
        }
        
        // Test creating a new hash and verifying it
        echo "\n6. Testing hash creation and verification...\n";
        $testHash = Security::hashPassword($password);
        echo "New hash created: $testHash\n";
        $testVerify = Security::verifyPassword($password, $testHash);
        echo "New hash verification: " . ($testVerify ? '✅ WORKS' : '❌ BROKEN') . "\n";
        
    } else {
        echo "❌ User not found in database\n";
        
        // Show what users exist
        echo "\nUsers in database:\n";
        $stmt = $db->query("SELECT user_id, username, email FROM users");
        $users = $stmt->fetchAll();
        foreach ($users as $u) {
            echo "- " . $u['username'] . " (ID: " . $u['user_id'] . ")\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>