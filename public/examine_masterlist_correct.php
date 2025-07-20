<?php
/**
 * Correct masterlist examination
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection('foundation');
    
    echo "=== Masterlist Table Analysis ===\n";
    
    echo "\n=== Share Types Data ===\n";
    try {
        $stmt = $db->query("SELECT * FROM share_types");
        $shareTypes = $stmt->fetchAll();
        echo "Share types:\n";
        foreach ($shareTypes as $type) {
            echo "- ID: {$type['share_type_id']}, Code: {$type['code']}, Description: {$type['description']}\n";
        }
    } catch (Exception $e) {
        echo "Share types error: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Sample Masterlist Data ===\n";
    
    // Get sample with correct join
    $stmt = $db->query("
        SELECT m.*, st.code as share_type_code, st.description as share_type_description 
        FROM masterlist m 
        LEFT JOIN share_types st ON m.share_type_id = st.share_type_id 
        LIMIT 5
    ");
    $samples = $stmt->fetchAll();
    
    foreach ($samples as $i => $company) {
        echo "Company " . ($i + 1) . ":\n";
        echo "  ISIN: {$company['isin']}\n";
        echo "  Ticker: {$company['ticker']}\n";
        echo "  Name: {$company['name']}\n";
        echo "  Country: {$company['country']}\n";
        echo "  Market: {$company['market']}\n";
        echo "  Share Type: {$company['share_type_code']} - {$company['share_type_description']}\n";
        echo "  Delisted: " . ($company['delisted'] ? 'Yes' : 'No') . "\n";
        if ($company['delisted_date']) {
            echo "  Delisted Date: {$company['delisted_date']}\n";
        }
        echo "  Current Version: " . ($company['current_version'] ? 'Yes' : 'No') . "\n";
        echo "\n";
    }
    
    echo "=== Statistics ===\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM masterlist");
    $total = $stmt->fetch()['total'];
    echo "Total companies: $total\n";
    
    $stmt = $db->query("SELECT COUNT(*) as active FROM masterlist WHERE delisted = 0");
    $active = $stmt->fetch()['active'];
    echo "Active companies: $active\n";
    
    $stmt = $db->query("SELECT COUNT(*) as delisted FROM masterlist WHERE delisted = 1");
    $delisted = $stmt->fetch()['delisted'];
    echo "Delisted companies: $delisted\n";
    
    // Country breakdown
    echo "\nCountries:\n";
    $stmt = $db->query("SELECT country, COUNT(*) as count FROM masterlist GROUP BY country ORDER BY count DESC");
    $countries = $stmt->fetchAll();
    foreach ($countries as $country) {
        echo "- {$country['country']}: {$country['count']}\n";
    }
    
    // Market breakdown
    echo "\nMarkets:\n";
    $stmt = $db->query("SELECT market, COUNT(*) as count FROM masterlist WHERE market IS NOT NULL GROUP BY market ORDER BY count DESC");
    $markets = $stmt->fetchAll();
    foreach ($markets as $market) {
        echo "- {$market['market']}: {$market['count']}\n";
    }
    
    // Share type breakdown
    echo "\nShare Types Distribution:\n";
    $stmt = $db->query("
        SELECT st.code, st.description, COUNT(*) as count 
        FROM masterlist m 
        LEFT JOIN share_types st ON m.share_type_id = st.share_type_id 
        GROUP BY st.code, st.description 
        ORDER BY count DESC
    ");
    $shareTypeDist = $stmt->fetchAll();
    foreach ($shareTypeDist as $dist) {
        $type = $dist['code'] ? "{$dist['code']} - {$dist['description']}" : "Unknown";
        echo "- $type: {$dist['count']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>