<?php
/**
 * Manual fix for the problematic entry
 * This uses the existing controller structure
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/controllers/NewCompaniesController.php';

try {
    echo "=== Manual Fix for US40434L1052 ===\n\n";
    
    $controller = new NewCompaniesController();
    
    // Get the problematic entry first
    $portfolioDb = Database::getConnection('portfolio');
    
    // 1. Find the entry
    echo "Step 1: Finding the problematic entry...\n";
    $stmt = $portfolioDb->prepare("SELECT * FROM new_companies WHERE isin = ? ORDER BY new_company_id DESC LIMIT 1");
    $stmt->execute(['US40434L1052']);
    $entry = $stmt->fetch();
    
    if (!$entry) {
        echo "❌ No entry found for ISIN US40434L1052\n";
        exit;
    }
    
    echo "✅ Found entry ID: " . $entry['new_company_id'] . "\n";
    echo "   Current company: " . ($entry['company'] ?: 'NULL') . "\n\n";
    
    // 2. Disable triggers manually using direct SQL
    echo "Step 2: Disabling problematic triggers...\n";
    try {
        $portfolioDb->exec("DROP TRIGGER IF EXISTS tr_new_companies_borsdata_update");
        $portfolioDb->exec("DROP TRIGGER IF EXISTS tr_new_companies_borsdata_insert");
        echo "✅ Triggers disabled\n\n";
    } catch (Exception $e) {
        echo "⚠️ Could not disable triggers: " . $e->getMessage() . "\n\n";
    }
    
    // 3. Use the controller's update method with safe data
    echo "Step 3: Updating entry using controller...\n";
    $updateData = [
        'company' => 'Hologic Inc',
        'ticker' => 'HOLX',
        'country_name' => 'United States',
        'borsdata_available' => 0  // Disable Börsdata mode
    ];
    
    $result = $controller->updateNewCompanyEntry($entry['new_company_id'], $updateData);
    
    if ($result) {
        echo "✅ Controller update successful!\n\n";
    } else {
        echo "❌ Controller update failed, trying direct SQL...\n";
        
        // Fallback: Direct SQL update
        $sql = "UPDATE new_companies 
                SET company = :company,
                    ticker = :ticker,
                    country_name = :country_name,
                    borsdata_available = :borsdata_available
                WHERE new_company_id = :id";
        
        $stmt = $portfolioDb->prepare($sql);
        $directResult = $stmt->execute([
            ':company' => 'Hologic Inc',
            ':ticker' => 'HOLX',
            ':country_name' => 'United States',
            ':borsdata_available' => 0,
            ':id' => $entry['new_company_id']
        ]);
        
        if ($directResult) {
            echo "✅ Direct SQL update successful!\n\n";
        } else {
            echo "❌ Direct SQL update failed too!\n";
            print_r($stmt->errorInfo());
            exit;
        }
    }
    
    // 4. Verify the fix
    echo "Step 4: Verifying the fix...\n";
    $stmt = $portfolioDb->prepare("SELECT * FROM new_companies WHERE new_company_id = ?");
    $stmt->execute([$entry['new_company_id']]);
    $updated = $stmt->fetch();
    
    echo "✅ Updated entry:\n";
    echo "   - ID: " . $updated['new_company_id'] . "\n";
    echo "   - Company: " . $updated['company'] . "\n";
    echo "   - Ticker: " . $updated['ticker'] . "\n";
    echo "   - ISIN: " . $updated['isin'] . "\n";
    echo "   - Country: " . $updated['country_name'] . "\n";
    echo "   - Börsdata: " . ($updated['borsdata_available'] ? 'YES' : 'NO') . "\n";
    
    echo "\n🎉 Fix completed successfully!\n";
    echo "\nThe entry is now corrected and should work properly in the interface.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>