<?php
require_once 'config/database.php';

try {
    $portfolioDb = Database::getConnection('portfolio');
    
    echo "=== RUNNING BROKER FEE FIELDS MIGRATION ===\n";
    
    // Read and execute the migration SQL
    $migrationSql = file_get_contents('migrations/add_broker_fee_fields_to_dividends.sql');
    
    // Remove the USE statement since we're already connected to the right database
    $migrationSql = preg_replace('/USE\s+psw_portfolio;\s*/', '', $migrationSql);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $migrationSql)));
    
    foreach ($statements as $sql) {
        if (empty($sql) || strpos($sql, '--') === 0 || strpos($sql, 'SELECT') === 0) {
            continue; // Skip comments and SELECT statements for now
        }
        
        try {
            echo "Executing: " . substr($sql, 0, 50) . "...\n";
            $portfolioDb->exec($sql);
            echo "✓ Success\n";
        } catch (Exception $e) {
            echo "⚠ Warning: " . $e->getMessage() . "\n";
        }
    }
    
    // Now check if the columns exist
    echo "\n=== VERIFYING MIGRATION ===\n";
    $stmt = $portfolioDb->query("DESCRIBE log_dividends");
    $columns = $stmt->fetchAll();
    
    $brokerFeeColumns = array_filter($columns, function($col) {
        return strpos($col['Field'], 'broker_fee') !== false;
    });
    
    if (count($brokerFeeColumns) >= 3) {
        echo "✓ Migration successful! Found broker fee columns:\n";
        foreach ($brokerFeeColumns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    } else {
        echo "❌ Migration may have failed. Broker fee columns not found.\n";
    }
    
    echo "\nMigration completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>