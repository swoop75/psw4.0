<?php
session_start();
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/src/middleware/Auth.php';

if (!Auth::isLoggedIn()) {
    echo "Not logged in";
    exit;
}

echo "<h1>Session Debug</h1>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Current User Info</h2>";
echo "Username: " . Auth::getUsername() . "<br>";
echo "User ID: " . Auth::getUserId() . "<br>";
echo "Role Name: " . ($_SESSION['role_name'] ?? 'NOT SET') . "<br>";

$adminRoles = ['Admin', 'admin', 'Administrator', 'administrator'];
echo "<h2>Admin Check</h2>";
echo "Is admin role set: " . (isset($_SESSION['role_name']) ? 'Yes' : 'No') . "<br>";
echo "Role matches admin: " . (in_array($_SESSION['role_name'] ?? '', $adminRoles) ? 'Yes' : 'No') . "<br>";
?>