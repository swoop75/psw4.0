<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config/config.php';
require_once 'src/config/BrokerCsvConfig.php';

try {
    // For now, just use config brokers to ensure it works
    $configBrokers = BrokerCsvConfig::getBrokerNames();
    
    echo json_encode([
        'success' => true,
        'brokers' => [],
        'config_brokers' => $configBrokers
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'config_brokers' => ['1' => 'Broker 1', '2' => 'Broker 2']
    ]);
}
?>