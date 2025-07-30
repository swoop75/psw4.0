<?php
/**
 * Clear format cache for immediate testing
 */

session_start();
require_once __DIR__ . '/src/utils/Localization.php';

// Clear the cached format
Localization::clearFormatCache();

// Also clear session cache
unset($_SESSION['user_format_preference']);

echo "Format cache cleared! Please refresh the dashboard to see the updated Swedish date format.";
echo "<br><br>";
echo '<a href="dashboard.php">Go to Dashboard</a>';
echo "<br>";
echo '<a href="format_preferences.php">Go to Format Settings</a>';
?>