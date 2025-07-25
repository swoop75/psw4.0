<?php
require_once 'config/database.php';
try {
    $db = Database::getConnection('marketdata');
    $stmt = $db->query('SELECT kpi_id, name_en, name_sv, format FROM kpi_metadata WHERE name_en LIKE "%yield%" OR name_en LIKE "%dividend%" OR name_sv LIKE "%yield%" OR name_sv LIKE "%utdel%"');
    $kpis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo 'Yield-related KPIs:' . PHP_EOL;
    foreach ($kpis as $kpi) {
        echo 'ID: ' . $kpi['kpi_id'] . ' - EN: ' . $kpi['name_en'] . ' - SV: ' . $kpi['name_sv'] . ' - Format: ' . $kpi['format'] . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>