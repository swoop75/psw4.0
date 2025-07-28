<?php
/**
 * Password Checker Script
 * Check what password matches the current hash
 */

require_once __DIR__ . '/src/utils/Security.php';

// Current hash from database
$currentHash = '$2y$10$TT6GqRZ/oaDvp51U0wR2MOJ1v1RZIsqKzVwFCv07LCkFN/8PmRlr.';

// Common passwords to test
$testPasswords = [
    'password',
    'admin',
    'admin123',
    'swoop',
    'swoop123',
    '123456',
    'password123',
    'admin1',
    'test',
    'test123'
];

echo "Testing passwords against current hash...\n";
echo "Current hash: $currentHash\n\n";

foreach ($testPasswords as $password) {
    if (Security::verifyPassword($password, $currentHash)) {
        echo "✅ MATCH FOUND: Password is '$password'\n";
        break;
    } else {
        echo "❌ '$password' - no match\n";
    }
}

echo "\nIf no match found, the password was changed to a generated random password.\n";
?>