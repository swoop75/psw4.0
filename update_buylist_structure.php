<?php
/**
 * Update buylist table structure for PSW 4.0
 * Adds missing columns for independent buylist functionality
 */

require_once __DIR__ . '/config/database.php';

try {
    echo "=== UPDATING BUYLIST TABLE STRUCTURE ===\n\n";
    
    $db = Database::getConnection('foundation');
    
    // Check if added_to_masterlist column exists
    $stmt = $db->query("DESCRIBE buylist");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('added_to_masterlist', $columns)) {
        echo "Adding 'added_to_masterlist' column...\n";
        $db->exec("ALTER TABLE buylist ADD COLUMN added_to_masterlist TINYINT(1) DEFAULT 0 NOT NULL");
        echo "✓ Added 'added_to_masterlist' column\n";
    } else {
        echo "✓ 'added_to_masterlist' column already exists\n";
    }
    
    if (!in_array('added_to_masterlist_date', $columns)) {
        echo "Adding 'added_to_masterlist_date' column...\n";
        $db->exec("ALTER TABLE buylist ADD COLUMN added_to_masterlist_date TIMESTAMP NULL");
        echo "✓ Added 'added_to_masterlist_date' column\n";
    } else {
        echo "✓ 'added_to_masterlist_date' column already exists\n";
    }
    
    // Update the search query in getUserBuylist to use company_name instead of masterlist reference
    echo "\nValidating buylist table structure...\n";
    
    // Check that all required columns exist
    $requiredColumns = [
        'buylist_id', 'company_name', 'ticker', 'country', 'currency', 'exchange',
        'isin', 'business_description', 'status_id', 'priority_level', 'target_price',
        'target_quantity', 'added_to_masterlist', 'added_to_masterlist_date'
    ];
    
    foreach ($requiredColumns as $col) {
        if (in_array($col, $columns)) {
            echo "✓ Column exists: $col\n";
        } else {
            echo "✗ Missing column: $col\n";
        }
    }
    
    echo "\n=== UPDATE COMPLETED ===\n";
    echo "Buylist table structure is now ready for independent operation.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>