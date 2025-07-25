<?php
/**
 * Test script for Börsdata integration
 */

require_once __DIR__ . '/config/database.php';

try {
    $portfolioDb = Database::getConnection('portfolio');
    $marketdataDb = Database::getConnection('marketdata');
    
    echo "=== Börsdata Integration Test ===\n\n";
    
    // 1. Check if triggers exist
    echo "1. Checking triggers...\n";
    $stmt = $portfolioDb->query("SHOW TRIGGERS LIKE 'tr_new_companies_borsdata%'");
    $triggers = $stmt->fetchAll();
    if (empty($triggers)) {
        echo "❌ No Börsdata triggers found!\n";
    } else {
        echo "✅ Found " . count($triggers) . " trigger(s):\n";
        foreach ($triggers as $trigger) {
            echo "   - " . $trigger['Trigger'] . "\n";
        }
    }
    echo "\n";
    
    // 2. Check if stored procedure exists
    echo "2. Checking stored procedure...\n";
    $stmt = $portfolioDb->query("SHOW PROCEDURE STATUS WHERE Db = 'psw_portfolio' AND Name = 'PopulateBorsdataCompanyData'");
    $procedures = $stmt->fetchAll();
    if (empty($procedures)) {
        echo "❌ PopulateBorsdataCompanyData procedure not found!\n";
    } else {
        echo "✅ PopulateBorsdataCompanyData procedure exists\n";
    }
    echo "\n";
    
    // 3. Check if ISIN exists in Börsdata tables
    echo "3. Checking ISIN US40434L1052 in Börsdata tables...\n";
    
    // Check global_instruments
    $stmt = $marketdataDb->prepare("SELECT isin, name, yahoo, insId FROM global_instruments WHERE isin = ? LIMIT 1");
    $stmt->execute(['US40434L1052']);
    $globalResult = $stmt->fetch();
    
    if ($globalResult) {
        echo "✅ Found in global_instruments:\n";
        echo "   - Name: " . $globalResult['name'] . "\n";
        echo "   - Ticker: " . $globalResult['yahoo'] . "\n";
        echo "   - ID: " . $globalResult['insId'] . "\n";
    } else {
        echo "❌ Not found in global_instruments\n";
        
        // Check nordic_instruments
        $stmt = $marketdataDb->prepare("SELECT isin, name, yahoo, insId FROM nordic_instruments WHERE isin = ? LIMIT 1");
        $stmt->execute(['US40434L1052']);
        $nordicResult = $stmt->fetch();
        
        if ($nordicResult) {
            echo "✅ Found in nordic_instruments:\n";
            echo "   - Name: " . $nordicResult['name'] . "\n";
            echo "   - Ticker: " . $nordicResult['yahoo'] . "\n";
            echo "   - ID: " . $nordicResult['insId'] . "\n";
        } else {
            echo "❌ Not found in nordic_instruments either\n";
        }
    }
    echo "\n";
    
    // 4. Check current entry in new_companies
    echo "4. Checking current entry in new_companies...\n";
    $stmt = $portfolioDb->prepare("SELECT * FROM new_companies WHERE isin = ? ORDER BY new_company_id DESC LIMIT 1");
    $stmt->execute(['US40434L1052']);
    $entry = $stmt->fetch();
    
    if ($entry) {
        echo "✅ Found entry in new_companies:\n";
        echo "   - ID: " . $entry['new_company_id'] . "\n";
        echo "   - Company: " . ($entry['company'] ?: 'NULL') . "\n";
        echo "   - Ticker: " . ($entry['ticker'] ?: 'NULL') . "\n";
        echo "   - Country: " . ($entry['country_name'] ?: 'NULL') . "\n";
        echo "   - Yield: " . ($entry['yield'] ?: 'NULL') . "\n";
        echo "   - Börsdata Available: " . ($entry['borsdata_available'] ? 'TRUE' : 'FALSE') . "\n";
    } else {
        echo "❌ No entry found in new_companies\n";
    }
    echo "\n";
    
    // 5. If procedure exists, test it manually
    if (!empty($procedures) && $entry) {
        echo "5. Testing manual procedure call...\n";
        try {
            $stmt = $portfolioDb->prepare("CALL PopulateBorsdataCompanyData(?)");
            $stmt->execute([$entry['new_company_id']]);
            $result = $stmt->fetch();
            echo "✅ Procedure executed successfully\n";
            if ($result) {
                echo "   Result: " . ($result['result'] ?? 'No result message') . "\n";
            }
            
            // Check if data was updated
            $stmt = $portfolioDb->prepare("SELECT company, ticker, country_name, yield FROM new_companies WHERE new_company_id = ?");
            $stmt->execute([$entry['new_company_id']]);
            $updatedEntry = $stmt->fetch();
            
            echo "   Updated data:\n";
            echo "   - Company: " . ($updatedEntry['company'] ?: 'NULL') . "\n";
            echo "   - Ticker: " . ($updatedEntry['ticker'] ?: 'NULL') . "\n";
            echo "   - Country: " . ($updatedEntry['country_name'] ?: 'NULL') . "\n";
            echo "   - Yield: " . ($updatedEntry['yield'] ?: 'NULL') . "\n";
            
        } catch (Exception $e) {
            echo "❌ Procedure call failed: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>