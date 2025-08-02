<?php
/**
 * Test script to verify the unified view integration works
 * Tests that vw_unified_companies properly combines data from all sources
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

echo "PSW 4.0 - Unified View Integration Test\n";
echo "=======================================\n\n";

try {
    $foundationDb = Database::getConnection('foundation');
    echo "✓ Database connection successful\n\n";
    
    // Test 1: Check if unified view exists
    echo "1. Testing if unified view exists...\n";
    try {
        $stmt = $foundationDb->query("DESCRIBE vw_unified_companies");
        $columns = $stmt->fetchAll();
        echo "✓ View exists with " . count($columns) . " columns:\n";
        foreach ($columns as $col) {
            echo "   - {$col['Field']} ({$col['Type']})\n";
        }
    } catch (Exception $e) {
        echo "❌ View does not exist: " . $e->getMessage() . "\n";
        echo "   Need to run migrations/non_borsdata_management_system.sql\n";
    }
    echo "\n";
    
    // Test 2: Count companies by data source
    echo "2. Testing data source counts...\n";
    try {
        $stmt = $foundationDb->query("
            SELECT 
                data_source, 
                COUNT(*) as company_count,
                COUNT(CASE WHEN isin IS NOT NULL THEN 1 END) as with_isin_count
            FROM vw_unified_companies 
            GROUP BY data_source 
            ORDER BY company_count DESC
        ");
        $sources = $stmt->fetchAll();
        
        foreach ($sources as $source) {
            echo "   {$source['data_source']}: {$source['company_count']} companies ({$source['with_isin_count']} with ISIN)\n";
        }
    } catch (Exception $e) {
        echo "❌ Could not retrieve data source counts: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Test 3: Test ISIN lookup across all sources
    echo "3. Testing ISIN lookup functionality...\n";
    $testISINs = [
        'SE0000163594', // Should be in Börsdata (Tele2)
        'CZ0008019106', // Test Czech company (manual)
        'IE0003290289'  // Test Irish company (manual)
    ];
    
    foreach ($testISINs as $isin) {
        try {
            $stmt = $foundationDb->prepare("
                SELECT 
                    isin, 
                    company_name, 
                    ticker, 
                    data_source, 
                    country, 
                    currency,
                    is_manual
                FROM vw_unified_companies 
                WHERE isin = ?
            ");
            $stmt->execute([$isin]);
            $company = $stmt->fetch();
            
            if ($company) {
                echo "   ✓ $isin: {$company['company_name']} ({$company['data_source']})\n";
                echo "     - Ticker: {$company['ticker']}, Country: {$company['country']}, Currency: {$company['currency']}\n";
            } else {
                echo "   ⚠ $isin: Not found in unified view\n";
            }
        } catch (Exception $e) {
            echo "   ❌ $isin: Error - " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
    
    // Test 4: Test search functionality
    echo "4. Testing company search across all sources...\n";
    $searchTerms = ['Tele2', 'Test', 'Apple'];
    
    foreach ($searchTerms as $term) {
        try {
            $stmt = $foundationDb->prepare("
                SELECT 
                    isin,
                    company_name,
                    ticker,
                    data_source,
                    country
                FROM vw_unified_companies 
                WHERE company_name LIKE ? 
                   OR ticker LIKE ?
                LIMIT 3
            ");
            $searchPattern = "%$term%";
            $stmt->execute([$searchPattern, $searchPattern]);
            $results = $stmt->fetchAll();
            
            echo "   Search '$term': " . count($results) . " results\n";
            foreach ($results as $result) {
                echo "     - {$result['company_name']} ({$result['ticker']}) [{$result['data_source']}]\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Search '$term': Error - " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
    
    // Test 5: Test manual companies integration
    echo "5. Testing manual companies integration...\n";
    try {
        $stmt = $foundationDb->query("
            SELECT 
                isin,
                company_name,
                ticker,
                country,
                currency,
                company_type,
                dividend_frequency,
                manual_notes
            FROM vw_unified_companies 
            WHERE data_source = 'manual'
            LIMIT 5
        ");
        $manualCompanies = $stmt->fetchAll();
        
        echo "   Found " . count($manualCompanies) . " manual companies:\n";
        foreach ($manualCompanies as $company) {
            echo "     - {$company['isin']}: {$company['company_name']} ({$company['ticker']})\n";
            echo "       {$company['country']}, {$company['currency']}, {$company['company_type']}\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Manual companies test failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Test 6: Performance test
    echo "6. Testing view performance...\n";
    try {
        $startTime = microtime(true);
        $stmt = $foundationDb->query("SELECT COUNT(*) as total FROM vw_unified_companies");
        $result = $stmt->fetch();
        $endTime = microtime(true);
        
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        echo "   ✓ Total companies: {$result['total']}\n";
        echo "   ✓ Query execution time: {$executionTime}ms\n";
        
        if ($executionTime > 1000) {
            echo "   ⚠ Warning: Query took longer than 1 second - consider indexing\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Performance test failed: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    echo "=======================================\n";
    echo "Unified View Integration Test Complete!\n";
    echo "=======================================\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}
?>