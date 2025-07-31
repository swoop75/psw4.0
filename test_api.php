<?php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';

// Force authentication for testing
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'test';

echo "<h2>Testing ISIN Search API</h2>";

try {
    $foundationDb = Database::getConnection('foundation');
    
    // First check if the ISIN exists in masterlist
    $checkSql = "SELECT * FROM masterlist WHERE isin = 'SE0022726485'";
    $checkStmt = $foundationDb->prepare($checkSql);
    $checkStmt->execute();
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Direct database check:</h3>";
    if ($result) {
        echo "<pre>";
        print_r($result);
        echo "</pre>";
    } else {
        echo "ISIN not found in masterlist table";
    }
    
    // Now test the search logic
    $query = 'SE0022726485';
    $sql = "SELECT 
                isin, 
                name as company_name, 
                ticker_symbol as ticker,
                country,
                currency,
                market_sector,
                share_type_id
            FROM masterlist 
            WHERE (isin LIKE :query OR name LIKE :query_name) 
            AND isin IS NOT NULL 
            AND isin != ''
            ORDER BY 
                CASE 
                    WHEN isin LIKE :exact_query THEN 1
                    WHEN isin LIKE :starts_query THEN 2
                    WHEN name LIKE :starts_name THEN 3
                    ELSE 4
                END,
                name
            LIMIT 10";
    
    $stmt = $foundationDb->prepare($sql);
    $stmt->execute([
        ':query' => '%' . $query . '%',
        ':query_name' => '%' . $query . '%',
        ':exact_query' => $query . '%',
        ':starts_query' => $query . '%',
        ':starts_name' => $query . '%'
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Search API logic test:</h3>";
    if ($results) {
        echo "<pre>";
        print_r($results);
        echo "</pre>";
    } else {
        echo "No results from search logic";
    }
    
    // Test the API endpoint directly
    echo "<h3>Testing API endpoint:</h3>";
    $apiUrl = BASE_URL . '/api/search_isin.php?q=' . urlencode($query);
    echo "API URL: " . $apiUrl . "<br>";
    
    // Use file_get_contents to test API
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Cookie: ' . session_name() . '=' . session_id()
            ]
        ]
    ]);
    
    $apiResult = file_get_contents($apiUrl, false, $context);
    echo "API Response: <pre>" . $apiResult . "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>