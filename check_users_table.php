<?php
/**
 * Check users table structure
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getConnection('foundation');
    
    echo "=== Users Table Structure ===\n";
    
    // Show table structure
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo "Column: {$column['Field']}, Type: {$column['Type']}, Key: {$column['Key']}, Null: {$column['Null']}, Default: {$column['Default']}\n";
    }
    
    echo "\n=== Sample Users Data ===\n";
    
    // Show first few users
    $stmt = $db->query("SELECT * FROM users LIMIT 3");
    $users = $stmt->fetchAll();
    
    if (!empty($users)) {
        // Show column names
        echo "Columns: " . implode(', ', array_keys($users[0])) . "\n\n";
        
        foreach ($users as $user) {
            echo "User: " . json_encode($user) . "\n";
        }
    } else {
        echo "No users found in table.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>