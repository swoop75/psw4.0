<?php
/**
 * Reset swoop password
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/utils/Security.php';

echo "=== Resetting Swoop Password ===\n";

try {
    $db = Database::getConnection('foundation');
    
    // Set new password
    $newPassword = 'admin123';
    $passwordHash = Security::hashPassword($newPassword);
    
    echo "New password: $newPassword\n";
    echo "New hash: $passwordHash\n\n";
    
    // Update password in database
    $sql = "UPDATE users SET password_hash = :password_hash WHERE username = 'swoop'";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':password_hash', $passwordHash);
    
    if ($stmt->execute()) {
        echo "✅ Password updated successfully!\n";
        echo "You can now login with:\n";
        echo "Username: swoop\n";
        echo "Password: $newPassword\n\n";
        
        // Test the new password
        require_once __DIR__ . '/../src/models/User.php';
        $userModel = new User();
        $result = $userModel->authenticate('swoop', $newPassword);
        
        if ($result['success']) {
            echo "✅ Authentication test SUCCESSFUL!\n";
            echo "Login should now work.\n";
        } else {
            echo "❌ Authentication test FAILED!\n";
            echo "Error: " . $result['message'] . "\n";
        }
        
    } else {
        echo "❌ Failed to update password\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>