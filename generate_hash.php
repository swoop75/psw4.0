<?php
/**
 * Generate Password Hash
 * Creates a fresh hash for a known password
 */

require_once __DIR__ . '/src/utils/Security.php';

$password = 'admin123';
$hash = Security::hashPassword($password);

echo "Password: $password\n";
echo "Hash: $hash\n";
echo "\nSQL to update:\n";
echo "UPDATE psw_foundation.users SET password_hash = '$hash', updated_at = NOW() WHERE username = 'swoop';\n";

// Test the hash
if (Security::verifyPassword($password, $hash)) {
    echo "\n✅ Hash verification successful\n";
} else {
    echo "\n❌ Hash verification failed\n";
}
?>