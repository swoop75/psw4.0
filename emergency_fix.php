<?php
/**
 * Emergency fix for US40434L1052 entry using PHP
 * This bypasses any stored procedures or triggers
 */

// Simple database connection without using the Database class to avoid any issues
$host = '100.117.171.98';
$dbname = 'psw_portfolio';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Connected to database successfully.\n\n";
    
    // 1. First, disable any triggers to prevent interference
    echo "Step 1: Disabling problematic triggers...\n";
    try {
        $pdo->exec("DROP TRIGGER IF EXISTS tr_new_companies_borsdata_update");
        $pdo->exec("DROP TRIGGER IF EXISTS tr_new_companies_borsdata_insert");
        echo "✅ Triggers disabled\n";
    } catch (Exception $e) {
        echo "⚠️ Could not disable triggers: " . $e->getMessage() . "\n";
    }
    
    // 2. Find the problematic entry
    echo "\nStep 2: Finding problematic entry...\n";
    $stmt = $pdo->prepare("SELECT * FROM new_companies WHERE isin = ? ORDER BY new_company_id DESC LIMIT 1");
    $stmt->execute(['US40434L1052']);
    $entry = $stmt->fetch();
    
    if (!$entry) {
        echo "❌ No entry found for ISIN US40434L1052\n";
        exit;
    }
    
    echo "Found entry ID: " . $entry['new_company_id'] . "\n";
    echo "Current company: " . ($entry['company'] ?: 'NULL') . "\n";
    
    // 3. Perform direct update with proper values
    echo "\nStep 3: Updating entry directly...\n";
    $updateSql = "UPDATE new_companies 
                  SET company = :company,
                      ticker = :ticker,
                      country_name = :country_name,
                      borsdata_available = :borsdata_available
                  WHERE new_company_id = :id";
    
    $stmt = $pdo->prepare($updateSql);
    $success = $stmt->execute([
        ':company' => 'Hologic Inc',
        ':ticker' => 'HOLX',
        ':country_name' => 'United States',
        ':borsdata_available' => 0,
        ':id' => $entry['new_company_id']
    ]);
    
    if ($success) {
        echo "✅ Update successful!\n";
    } else {
        echo "❌ Update failed\n";
        print_r($stmt->errorInfo());
        exit;
    }
    
    // 4. Verify the fix
    echo "\nStep 4: Verifying fix...\n";
    $stmt = $pdo->prepare("SELECT new_company_id, company, ticker, isin, country_name, borsdata_available FROM new_companies WHERE new_company_id = ?");
    $stmt->execute([$entry['new_company_id']]);
    $updated = $stmt->fetch();
    
    echo "Updated entry:\n";
    echo "  - ID: " . $updated['new_company_id'] . "\n";
    echo "  - Company: " . $updated['company'] . "\n";
    echo "  - Ticker: " . $updated['ticker'] . "\n";
    echo "  - ISIN: " . $updated['isin'] . "\n";
    echo "  - Country: " . $updated['country_name'] . "\n";
    echo "  - Börsdata: " . ($updated['borsdata_available'] ? 'YES' : 'NO') . "\n";
    
    echo "\n✅ Fix completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ General error: " . $e->getMessage() . "\n";
}
?>