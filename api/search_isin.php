<?php
/**
 * File: api/search_isin.php
 * Description: ISIN lookup API for autocomplete from masterlist
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/middleware/Auth.php';

// Require authentication
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $query = $_GET['q'] ?? '';
    
    if (strlen($query) < 2) {
        echo json_encode([]);
        exit;
    }
    
    $foundationDb = Database::getConnection('foundation');
    
    // Search by ISIN or company name
    $sql = "SELECT 
                isin, 
                name as company_name, 
                ticker,
                country,
                market as market_sector,
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
    
    // Format results for autocomplete
    $formatted = [];
    foreach ($results as $row) {
        $formatted[] = [
            'isin' => $row['isin'],
            'company_name' => $row['company_name'],
            'ticker' => $row['ticker'],
            'country' => $row['country'],
            'currency' => 'SEK', // Default since most securities are Swedish
            'market_sector' => $row['market_sector'],
            'share_type_id' => $row['share_type_id'],
            'display_text' => $row['isin'] . ' - ' . $row['company_name'],
            'label' => $row['isin'] . ' - ' . $row['company_name'] . ($row['ticker'] ? ' (' . $row['ticker'] . ')' : '')
        ];
    }
    
    echo json_encode($formatted);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>