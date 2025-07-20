<?php
/**
 * File: test_db_connection.php
 * Description: Test database connections for PSW 4.0
 */

require_once __DIR__ . '/config/database.php';

echo "Testing database connections...\n\n";

$results = Database::testConnections();

foreach ($results as $database => $status) {
    $icon = (strpos($status, 'Connected') === 0) ? '✅' : '❌';
    echo "{$icon} {$database}: {$status}\n";
}

echo "\nIf you see connection errors, please update your database configuration.\n";
?>