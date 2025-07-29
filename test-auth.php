<?php
/**
 * Test authentication status for debugging
 */
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/middleware/Auth.php';

echo "<h1>Authentication Debug</h1>";
echo "<h2>Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Auth Status:</h2>";
echo "Is Logged In: " . (Auth::isLoggedIn() ? 'YES' : 'NO') . "<br>";
echo "Is Session Valid: " . (Auth::isSessionValid() ? 'YES' : 'NO') . "<br>";

if (isset($_SESSION['user_id'])) {
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "Username: " . $_SESSION['username'] . "<br>";
    echo "Role: " . $_SESSION['user_role'] . "<br>";
    echo "Last Activity: " . (isset($_SESSION['last_activity']) ? date('Y-m-d H:i:s', $_SESSION['last_activity']) : 'Not set') . "<br>";
}

echo "<h2>Config:</h2>";
echo "BASE_URL: " . BASE_URL . "<br>";
echo "SESSION_TIMEOUT: " . SESSION_TIMEOUT . " seconds<br>";

echo "<h2>Test Links:</h2>";
echo '<a href="/dashboard-redesign.php">Dashboard Redesign</a><br>';
echo '<a href="/login.php">Login</a><br>';
echo '<a href="/dashboard.php">Original Dashboard</a><br>';
?>