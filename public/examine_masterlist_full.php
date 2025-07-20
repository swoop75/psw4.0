<?php
/**
 * Complete masterlist examination
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection('foundation');
    
    echo "=== Masterlist Table Analysis ===\n";
    
    // Show available columns
    $stmt = $db->query("DESCRIBE masterlist");
    $columns = $stmt->fetchAll();
    
    echo "Available columns:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n=== Related Tables ===\n";
    
    // Check share_types table
    try {
        $stmt = $db->query("DESCRIBE share_types");
        $shareTypeColumns = $stmt->fetchAll();
        echo "Share Types table columns:\n";
        foreach ($shareTypeColumns as $col) {
            echo "- {$col['Field']} ({$col['Type']})\n";
        }
        
        // Show share types data
        $stmt = $db->query("SELECT * FROM share_types");
        $shareTypes = $stmt->fetchAll();
        echo "\nShare types data:\n";
        foreach ($shareTypes as $type) {
            echo "- ID: {$type['id']}, Name: {$type['name']}\n";
        }
    } catch (Exception $e) {
        echo "Share types table error: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Sample Masterlist Data ===\n";
    
    // Get sample with share type join
    $stmt = $db->query("
        SELECT m.*, st.name as share_type_name 
        FROM masterlist m 
        LEFT JOIN share_types st ON m.share_type_id = st.id 
        LIMIT 3
    ");
    $samples = $stmt->fetchAll();
    
    foreach ($samples as $i => $company) {
        echo "Company " . ($i + 1) . ":\n";
        echo "  ISIN: {$company['isin']}\n";
        echo "  Ticker: {$company['ticker']}\n";
        echo "  Name: {$company['name']}\n";
        echo "  Country: {$company['country']}\n";
        echo "  Market: {$company['market']}\n";
        echo "  Share Type: {$company['share_type_name']}\n";
        echo "  Delisted: " . ($company['delisted'] ? 'Yes' : 'No') . "\n";
        echo "  Current Version: " . ($company['current_version'] ? 'Yes' : 'No') . "\n";
        echo "\n";
    }
    
    echo "=== Statistics ===\n";
    echo "Total companies: " . $db->query("SELECT COUNT(*) FROM masterlist")->fetch()[0] . "\n";
    echo "Active companies: " . $db->query("SELECT COUNT(*) FROM masterlist WHERE delisted = 0")->fetch()[0] . "\n";
    echo "Delisted companies: " . $db->query("SELECT COUNT(*) FROM masterlist WHERE delisted = 1")->fetch()[0] . "\n";
    
    // Country breakdown
    echo "\nCountries:\n";
    $stmt = $db->query("SELECT country, COUNT(*) as count FROM masterlist GROUP BY country ORDER BY count DESC");
    $countries = $stmt->fetchAll();
    foreach ($countries as $country) {
        echo "- {$country['country']}: {$country['count']}\n";
    }
    
    // Market breakdown
    echo "\nMarkets:\n";
    $stmt = $db->query("SELECT market, COUNT(*) as count FROM masterlist GROUP BY market ORDER BY count DESC");
    $markets = $stmt->fetchAll();
    foreach ($markets as $market) {
        echo "- {$market['market']}: {$market['count']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>