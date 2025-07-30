<?php
/**
 * Run format preference migration
 */

require_once __DIR__ . '/config/database.php';

try {
    echo "Running format preference migration...\n";
    
    $db = Database::getConnection('foundation');
    
    // Check if column already exists
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'format_preference'");
    $columnExists = $stmt->fetch();
    
    if ($columnExists) {
        echo "✅ Column 'format_preference' already exists.\n";
    } else {
        echo "Adding format_preference column to users table...\n";
        
        // Add the column
        $db->exec("ALTER TABLE users ADD COLUMN format_preference VARCHAR(5) DEFAULT 'US' COMMENT 'User preferred format (US, EU, SE, UK, DE, FR)' AFTER last_login");
        echo "✅ Column added successfully.\n";
        
        // Update existing users to have default US format
        $db->exec("UPDATE users SET format_preference = 'US' WHERE format_preference IS NULL");
        echo "✅ Updated existing users with default format.\n";
        
        // Add index for performance
        $db->exec("CREATE INDEX idx_users_format_preference ON users(format_preference)");
        echo "✅ Index created successfully.\n";
    }
    
    // Show updated table structure
    echo "\n=== Users Table Structure ===\n";
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        $line = "Column: {$column['Field']}, Type: {$column['Type']}";
        if ($column['Field'] === 'format_preference') {
            $line = "✅ " . $line . " (NEW)";
        }
        echo $line . "\n";
    }
    
    echo "\n🎉 Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>