<?php
header('Content-Type: application/json');

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'src/config/BrokerCsvConfig.php';

try {
    // Get brokers from database if available
    $portfolioDb = Database::getConnection('portfolio');
    
    $stmt = $portfolioDb->query("SELECT broker_id, broker_name FROM brokers ORDER BY broker_name");
    $dbBrokers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get config brokers as fallback
    $configBrokers = BrokerCsvConfig::getBrokerNames();
    
    echo json_encode([
        'success' => true,
        'brokers' => $dbBrokers,
        'config_brokers' => $configBrokers
    ]);
    
} catch (Exception $e) {
    // If database fails, return config brokers only
    $configBrokers = BrokerCsvConfig::getBrokerNames();
    
    echo json_encode([
        'success' => true,
        'brokers' => [],
        'config_brokers' => $configBrokers
    ]);
}
?>