<?php
require_once 'config/database.php';

try {
    $portfolioDb = Database::getConnection('portfolio');
    
    echo "=== LOG_DIVIDENDS TABLE STRUCTURE ===\n";
    $stmt = $portfolioDb->query("DESCRIBE log_dividends");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo sprintf("%-25s %-20s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'] == 'YES' ? 'NULL' : 'NOT NULL'
        );
    }
    
    echo "\n=== SAMPLE RECORD ===\n";
    $stmt = $portfolioDb->query("SELECT * FROM log_dividends LIMIT 1");
    $sample = $stmt->fetch();
    
    if ($sample) {
        foreach ($sample as $field => $value) {
            echo sprintf("%-25s: %s\n", $field, $value ?? 'NULL');
        }
    } else {
        echo "No records found in log_dividends table\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>