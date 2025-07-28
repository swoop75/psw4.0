<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config/config.php';
require_once 'config/database.php';

try {
    // Get account groups from database 
    $foundationDb = Database::getConnection('foundation');
    
    $stmt = $foundationDb->query("
        SELECT portfolio_account_group_id, portfolio_group_name, portfolio_group_description 
        FROM portfolio_account_groups 
        ORDER BY portfolio_group_name
    ");
    $accountGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'account_groups' => $accountGroups
    ]);
    
} catch (Exception $e) {
    // If database fails, return empty array
    echo json_encode([
        'success' => false,
        'account_groups' => [],
        'error' => $e->getMessage()
    ]);
}
?>