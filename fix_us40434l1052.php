<?php
/**
 * Debug and fix script for ISIN US40434L1052
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/controllers/NewCompaniesController.php';

try {
    $controller = new NewCompaniesController();
    
    echo "=== Fixing ISIN US40434L1052 Entry ===\n\n";
    
    // 1. Find the current entry
    $portfolioDb = Database::getConnection('portfolio');
    $stmt = $portfolioDb->prepare("SELECT * FROM new_companies WHERE isin = ? ORDER BY new_company_id DESC LIMIT 1");
    $stmt->execute(['US40434L1052']);
    $entry = $stmt->fetch();
    
    if (!$entry) {
        echo "❌ No entry found for ISIN US40434L1052\n";
        exit;
    }
    
    echo "✅ Found entry:\n";
    echo "   - ID: " . $entry['new_company_id'] . "\n";
    echo "   - Company: " . ($entry['company'] ?: 'NULL') . "\n";
    echo "   - Ticker: " . ($entry['ticker'] ?: 'NULL') . "\n";
    echo "   - Country: " . ($entry['country_name'] ?: 'NULL') . "\n";
    echo "   - Yield: " . ($entry['yield'] ?: 'NULL') . "\n";
    echo "   - Börsdata Available: " . ($entry['borsdata_available'] ? 'TRUE' : 'FALSE') . "\n\n";
    
    // 2. Enable Börsdata mode and update the entry
    echo "Step 1: Enabling Börsdata mode...\n";
    $updateData = [
        'borsdata_available' => 1
        // Don't set company to null since it has NOT NULL constraint
        // The stored procedure will update it with the correct data
    ];
    
    $result = $controller->updateNewCompanyEntry($entry['new_company_id'], $updateData);
    
    if ($result) {
        echo "✅ Successfully enabled Börsdata mode\n";
    } else {
        echo "❌ Failed to enable Börsdata mode\n";
        exit;
    }
    
    // 3. Check if data was auto-populated
    $stmt->execute(['US40434L1052']);
    $updatedEntry = $stmt->fetch();
    
    echo "\nStep 2: Checking if auto-population worked...\n";
    echo "   - Company: " . ($updatedEntry['company'] ?: 'NULL') . "\n";
    echo "   - Ticker: " . ($updatedEntry['ticker'] ?: 'NULL') . "\n";
    echo "   - Country: " . ($updatedEntry['country_name'] ?: 'NULL') . "\n";
    echo "   - Yield: " . ($updatedEntry['yield'] ?: 'NULL') . "\n";
    
    // 4. If auto-population didn't work, let's check why
    if (empty($updatedEntry['company']) || $updatedEntry['company'] === '1111') {
        echo "\n❌ Auto-population didn't work. Let's investigate...\n";
        
        // Check if triggers exist
        echo "\nChecking triggers...\n";
        $stmt = $portfolioDb->query("SHOW TRIGGERS LIKE 'tr_new_companies_borsdata%'");
        $triggers = $stmt->fetchAll();
        if (empty($triggers)) {
            echo "❌ Triggers not installed!\n";
            echo "📝 Solution: Run the install_borsdata_integration.sql script\n";
        } else {
            echo "✅ Triggers found: " . count($triggers) . "\n";
        }
        
        // Check if procedure exists
        echo "\nChecking stored procedure...\n";
        $stmt = $portfolioDb->query("SHOW PROCEDURE STATUS WHERE Db = 'psw_portfolio' AND Name = 'PopulateBorsdataCompanyData'");
        $procedures = $stmt->fetchAll();
        if (empty($procedures)) {
            echo "❌ Stored procedure not installed!\n";
            echo "📝 Solution: Run the install_borsdata_integration.sql script\n";
        } else {
            echo "✅ Stored procedure found\n";
        }
        
        // Check if ISIN exists in marketdata
        echo "\nChecking if ISIN exists in Börsdata...\n";
        $marketdataDb = Database::getConnection('marketdata');
        
        // Check global_instruments
        $stmt = $marketdataDb->prepare("SELECT name, yahoo FROM global_instruments WHERE isin = ? LIMIT 1");
        $stmt->execute(['US40434L1052']);
        $globalData = $stmt->fetch();
        
        if ($globalData) {
            echo "✅ Found in global_instruments: " . $globalData['name'] . " (" . $globalData['yahoo'] . ")\n";
        } else {
            // Check nordic_instruments
            $stmt = $marketdataDb->prepare("SELECT name, yahoo FROM nordic_instruments WHERE isin = ? LIMIT 1");
            $stmt->execute(['US40434L1052']);
            $nordicData = $stmt->fetch();
            
            if ($nordicData) {
                echo "✅ Found in nordic_instruments: " . $nordicData['name'] . " (" . $nordicData['yahoo'] . ")\n";
            } else {
                echo "❌ ISIN US40434L1052 not found in either global_instruments or nordic_instruments\n";
                echo "📝 This means the data is not available in your Börsdata database\n";
            }
        }
    } else {
        echo "\n✅ Auto-population successful!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>