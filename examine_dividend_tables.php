<?php
/**
 * Examine dividend-related table structures
 */

require_once __DIR__ . '/config/database.php';

echo "=== EXAMINING DIVIDEND-RELATED TABLES ===\n\n";

// Examine log_dividends table in portfolio database
try {
    $portfolioDb = Database::getConnection('portfolio');
    echo "1. LOG_DIVIDENDS Table Structure (psw_portfolio database):\n";
    echo "=========================================================\n";
    
    $stmt = $portfolioDb->query('DESCRIBE log_dividends');
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo sprintf("%-25s %-20s %-8s %-8s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Key'], 
            $column['Null'], 
            $column['Default'] ?? 'NULL'
        );
    }
    
    echo "\nSample data from log_dividends:\n";
    echo "--------------------------------\n";
    $stmt = $portfolioDb->query('SELECT * FROM log_dividends ORDER BY payment_date DESC LIMIT 3');
    $samples = $stmt->fetchAll();
    
    if (!empty($samples)) {
        echo "Columns: " . implode(', ', array_keys($samples[0])) . "\n\n";
        foreach ($samples as $i => $row) {
            echo "Row " . ($i + 1) . ":\n";
            foreach ($row as $key => $value) {
                echo "  $key: $value\n";
            }
            echo "\n";
        }
    }
    
    // Get record count
    $stmt = $portfolioDb->query('SELECT COUNT(*) as count FROM log_dividends');
    $count = $stmt->fetch()['count'];
    echo "Total records in log_dividends: $count\n\n";
    
} catch (Exception $e) {
    echo "Error examining log_dividends: " . $e->getMessage() . "\n\n";
}

// Examine distribution_classification table in foundation database
try {
    $foundationDb = Database::getConnection('foundation');
    echo "2. DISTRIBUTION_CLASSIFICATION Table Structure (psw_foundation database):\n";
    echo "========================================================================\n";
    
    $stmt = $foundationDb->query('DESCRIBE distribution_classification');
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo sprintf("%-25s %-20s %-8s %-8s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Key'], 
            $column['Null'], 
            $column['Default'] ?? 'NULL'
        );
    }
    
    echo "\nSample data from distribution_classification:\n";
    echo "--------------------------------------------\n";
    $stmt = $foundationDb->query('SELECT * FROM distribution_classification LIMIT 5');
    $samples = $stmt->fetchAll();
    
    if (!empty($samples)) {
        foreach ($samples as $i => $row) {
            echo "Row " . ($i + 1) . ": " . json_encode($row) . "\n";
        }
    }
    
    // Get record count
    $stmt = $foundationDb->query('SELECT COUNT(*) as count FROM distribution_classification');
    $count = $stmt->fetch()['count'];
    echo "\nTotal records in distribution_classification: $count\n\n";
    
} catch (Exception $e) {
    echo "Error examining distribution_classification: " . $e->getMessage() . "\n\n";
}

// Examine dividend_type table in foundation database
try {
    $foundationDb = Database::getConnection('foundation');
    echo "3. DIVIDEND_TYPE Table Structure (psw_foundation database):\n";
    echo "=========================================================\n";
    
    $stmt = $foundationDb->query('DESCRIBE dividend_type');
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo sprintf("%-25s %-20s %-8s %-8s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Key'], 
            $column['Null'], 
            $column['Default'] ?? 'NULL'
        );
    }
    
    echo "\nSample data from dividend_type:\n";
    echo "-------------------------------\n";
    $stmt = $foundationDb->query('SELECT * FROM dividend_type LIMIT 5');
    $samples = $stmt->fetchAll();
    
    if (!empty($samples)) {
        foreach ($samples as $i => $row) {
            echo "Row " . ($i + 1) . ": " . json_encode($row) . "\n";
        }
    }
    
    // Get record count
    $stmt = $foundationDb->query('SELECT COUNT(*) as count FROM dividend_type');
    $count = $stmt->fetch()['count'];
    echo "\nTotal records in dividend_type: $count\n\n";
    
} catch (Exception $e) {
    echo "Error examining dividend_type: " . $e->getMessage() . "\n\n";
}

echo "=== ANALYSIS COMPLETE ===\n";
?>