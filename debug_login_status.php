<?php
/**
 * Debug login status
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/src/middleware/Auth.php';

echo "=== Login Status Debug ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Is logged in: " . (Auth::isLoggedIn() ? 'YES' : 'NO') . "\n";
echo "Session data: " . print_r($_SESSION, true) . "\n";

if (Auth::isLoggedIn()) {
    echo "User ID: " . Auth::getUserId() . "\n";
    echo "Username: " . Auth::getUsername() . "\n";
    echo "User Role: " . Auth::getUserRole() . "\n";
    echo "Is Admin: " . (Auth::isAdmin() ? 'YES' : 'NO') . "\n";
} else {
    echo "User is not logged in.\n";
    echo "Redirect URL would be: " . BASE_URL . "/login.php\n";
}
?>