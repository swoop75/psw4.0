<?php
/**
 * Examine masterlist table structure and data
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection('foundation');
    
    echo "=== Masterlist Table Structure ===\n";
    
    // Show table structure
    $stmt = $db->query("DESCRIBE masterlist");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo "Column: {$column['Field']}, Type: {$column['Type']}, Key: {$column['Key']}, Null: {$column['Null']}, Default: {$column['Default']}\n";
    }
    
    echo "\n=== Table Statistics ===\n";
    
    // Count total records
    $stmt = $db->query("SELECT COUNT(*) as total FROM masterlist");
    $total = $stmt->fetch()['total'];
    echo "Total companies: $total\n";
    
    // Count by country
    $stmt = $db->query("SELECT country, COUNT(*) as count FROM masterlist GROUP BY country ORDER BY count DESC LIMIT 10");
    $countries = $stmt->fetchAll();
    echo "\nTop countries:\n";
    foreach ($countries as $country) {
        echo "- {$country['country']}: {$country['count']} companies\n";
    }
    
    // Count by currency
    $stmt = $db->query("SELECT currency, COUNT(*) as count FROM masterlist GROUP BY currency ORDER BY count DESC LIMIT 10");
    $currencies = $stmt->fetchAll();
    echo "\nCurrencies:\n";
    foreach ($currencies as $currency) {
        echo "- {$currency['currency']}: {$currency['count']} companies\n";
    }
    
    echo "\n=== Sample Data ===\n";
    
    // Show first few records
    $stmt = $db->query("SELECT * FROM masterlist LIMIT 5");
    $samples = $stmt->fetchAll();
    
    if (!empty($samples)) {
        echo "Columns: " . implode(', ', array_keys($samples[0])) . "\n\n";
        
        foreach ($samples as $i => $company) {
            echo "Company " . ($i + 1) . ":\n";
            foreach ($company as $key => $value) {
                echo "  $key: " . (strlen($value) > 50 ? substr($value, 0, 50) . "..." : $value) . "\n";
            }
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>