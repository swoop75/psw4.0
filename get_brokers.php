<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config/config.php';
require_once 'config/database.php';

try {
    // Get brokers from database 
    $foundationDb = Database::getConnection('foundation');
    
    $stmt = $foundationDb->query("SELECT broker_id, broker_name FROM brokers ORDER BY broker_name");
    $dbBrokers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add the 'minimal' format as first option
    $allBrokers = [
        ['broker_id' => 'minimal', 'broker_name' => 'Minimal Format (PSW Standard)']
    ];
    
    // Add database brokers
    foreach ($dbBrokers as $broker) {
        $allBrokers[] = $broker;
    }
    
    echo json_encode([
        'success' => true,
        'brokers' => $allBrokers,
        'config_brokers' => []
    ]);
    
} catch (Exception $e) {
    // If database fails, return minimal format only
    echo json_encode([
        'success' => true,
        'brokers' => [
            ['broker_id' => 'minimal', 'broker_name' => 'Minimal Format (PSW Standard)']
        ],
        'config_brokers' => []
    ]);
}
?>