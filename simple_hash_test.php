<?php
/**
 * Simple Hash Test
 * Test different password scenarios
 */

require_once __DIR__ . '/src/utils/Security.php';

echo "=== PASSWORD HASH TESTING ===\n\n";

// Test the exact password
$password = 'admin123';
$newHash = Security::hashPassword($password);

echo "Password: $password\n";
echo "New Hash: $newHash\n\n";

// Test against the old hash from database logs
$oldHash = '$2y$10$TT6GqRZ/oaDvp51U0wR2MOJ1v1RZIsqKzVwFCv07LCkFN/8PmRlr.';
echo "Testing against old hash: $oldHash\n";
echo "Result: " . (Security::verifyPassword($password, $oldHash) ? 'MATCH' : 'NO MATCH') . "\n\n";

// Test different variations
$testPasswords = [
    'admin123',
    'Admin123',
    'ADMIN123',
    'admin',
    'swoop',
    'password',
    '123456'
];

echo "Testing common passwords against old hash:\n";
foreach ($testPasswords as $testPass) {
    $result = Security::verifyPassword($testPass, $oldHash);
    echo "  $testPass: " . ($result ? 'MATCH' : 'no match') . "\n";
}

echo "\n=== SQL COMMANDS ===\n";
echo "To check current user in database:\n";
echo "SELECT username, email, password_hash FROM psw_foundation.users WHERE username = 'swoop';\n\n";

echo "To update with new hash:\n";
echo "UPDATE psw_foundation.users SET password_hash = '$newHash', updated_at = NOW() WHERE username = 'swoop';\n\n";

// Also test the case sensitivity issue
echo "=== CASE SENSITIVITY TEST ===\n";
$upperUser = 'SWOOP';
$lowerUser = 'swoop';
echo "Testing username case sensitivity...\n";
echo "If login uses exact match, 'SWOOP' != 'swoop'\n";
echo "Check if your database has username as 'swoop' or 'SWOOP'\n";

?>