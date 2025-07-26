<?php
/**
 * Emergency Password Reset Script
 * Run this script once to reset a user's password
 * Delete this file after use for security
 */

// Include necessary files
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/src/utils/Security.php';

// Configuration - CHANGE THESE VALUES
$username = 'swoop';        // Username to reset
$newPassword = 'admin123';  // New password (change this!)

try {
    // Connect to database
    $db = Database::getConnection('foundation');
    
    // Hash the new password
    $hashedPassword = Security::hashPassword($newPassword);
    
    // Update the password in database
    $sql = "UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE username = :username";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':password_hash', $hashedPassword);
    $stmt->bindValue(':username', $username);
    
    if ($stmt->execute()) {
        echo "SUCCESS: Password for user '{$username}' has been reset to '{$newPassword}'\n";
        echo "Please login with these credentials and change your password immediately.\n";
        echo "IMPORTANT: Delete this script file for security!\n";
    } else {
        echo "ERROR: Failed to update password in database\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// For security, show warning about deleting this file
echo "\n=== SECURITY WARNING ===\n";
echo "This script contains passwords in plain text!\n";
echo "Please delete this file immediately after use.\n";
echo "File location: " . __FILE__ . "\n";
?>