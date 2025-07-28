<?php
/**
 * Fetch Yield Data from Börsdata API
 * 
 * This script fetches yield data for all periods and calculations
 * from the Börsdata API and stores it in the KPI tables
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/BorsdataAPI.php';

class YieldDataFetcher {
    private $marketdataDb;
    private $portfolioDb;
    private $borsdataAPI;
    
    // Define the time periods and calculations we want to fetch
    private $periods = ['last', '1year', '3year', '5year', '10year'];
    private $calculations = ['latest', 'mean', 'cagr'];
    
    // Yield KPI ID - this needs to be determined from your KPI metadata
    private $yieldKpiId = 1; // This is typically dividend yield - verify in your system!
    
    public function __construct() {
        $this->marketdataDb = Database::getConnection('marketdata');
        $this->portfolioDb = Database::getConnection('portfolio');
        $this->borsdataAPI = new BorsdataAPI();
    }
    
    /**
     * Fetch yield data for all instruments
     */
    public function fetchAllYieldData() {
        echo "Starting yield data fetch...\n";
        
        // Get all instruments that need yield data
        $instruments = $this->getInstrumentsNeedingYieldData();
        
        echo "Found " . count($instruments) . " instruments to process\n";
        
        foreach ($instruments as $instrument) {
            $this->fetchYieldDataForInstrument($instrument);
            
            // Add delay to respect API rate limits
            usleep(100000); // 0.1 second delay
        }
        
        echo "Yield data fetch completed\n";
    }
    
    /**
     * Get instruments that need yield data updates
     */
    private function getInstrumentsNeedingYieldData() {
        $sql = "
            SELECT DISTINCT gi.insId as instrument_id, gi.name, gi.isin
            FROM global_instruments gi
            LEFT JOIN kpi_global kg ON gi.insId = kg.instrument_id 
                AND kg.kpi_id = :yield_kpi_id 
                AND kg.updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            WHERE kg.id IS NULL
            LIMIT 100  -- Process in batches
        ";
        
        $stmt = $this->marketdataDb->prepare($sql);
        $stmt->bindValue(':yield_kpi_id', $this->yieldKpiId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Fetch yield data for a specific instrument
     */
    private function fetchYieldDataForInstrument($instrument) {
        $instrumentId = $instrument['instrument_id'];
        
        echo "Processing instrument {$instrumentId}: {$instrument['name']}\n";
        
        foreach ($this->periods as $period) {
            foreach ($this->calculations as $calculation) {
                // Skip invalid combinations
                if ($period === 'last' && $calculation !== 'latest') {
                    continue;
                }
                
                $this->fetchAndStoreYieldData($instrumentId, $period, $calculation);
            }
        }
        
        // Update the new_companies table if this instrument exists there
        $this->updateNewCompaniesYieldData($instrumentId);
    }
    
    /**
     * Fetch and store specific yield data point
     */
    private function fetchAndStoreYieldData($instrumentId, $period, $calculation) {
        try {
            // Construct API URL based on period and calculation
            if ($period === 'last') {
                $endpoint = "/v1/instruments/kpis/{$this->yieldKpiId}/last/latest";
            } else {
                $endpoint = "/v1/instruments/kpis/{$this->yieldKpiId}/{$period}/{$calculation}";
            }
            
            // Add instrument filter to the API call
            $url = $endpoint . "?instruments={$instrumentId}";
            
            $response = $this->borsdataAPI->makeRequest($url);
            
            if ($response && isset($response['values']) && !empty($response['values'])) {
                foreach ($response['values'] as $data) {
                    $this->storeYieldKpiData($instrumentId, $period, $calculation, $data);
                }
            }
            
        } catch (Exception $e) {
            echo "Error fetching yield data for instrument {$instrumentId}, {$period}/{$calculation}: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Store yield KPI data in the database
     */
    private function storeYieldKpiData($instrumentId, $period, $calculation, $data) {
        // Determine which table to use based on instrument
        $table = $this->isNordicInstrument($instrumentId) ? 'kpi_nordic' : 'kpi_global';
        
        $sql = "
            INSERT INTO {$table} (kpi_id, group_period, calculation, instrument_id, numeric_value, created_at, updated_at)
            VALUES (:kpi_id, :group_period, :calculation, :instrument_id, :numeric_value, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                numeric_value = VALUES(numeric_value),
                updated_at = NOW()
        ";
        
        $stmt = $this->marketdataDb->prepare($sql);
        $stmt->execute([
            ':kpi_id' => $this->yieldKpiId,
            ':group_period' => $period,
            ':calculation' => $calculation,
            ':instrument_id' => $instrumentId,
            ':numeric_value' => $data['v'] ?? null
        ]);
    }
    
    /**
     * Check if instrument is Nordic
     */
    private function isNordicInstrument($instrumentId) {
        $sql = "SELECT COUNT(*) FROM nordic_instruments WHERE insId = :instrument_id";
        $stmt = $this->marketdataDb->prepare($sql);
        $stmt->bindValue(':instrument_id', $instrumentId);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Update new_companies table with aggregated yield data
     */
    private function updateNewCompaniesYieldData($instrumentId) {
        // Get the ISIN for this instrument
        $sql = "SELECT isin FROM global_instruments WHERE insId = :instrument_id 
                UNION 
                SELECT isin FROM nordic_instruments WHERE insId = :instrument_id";
        $stmt = $this->marketdataDb->prepare($sql);
        $stmt->bindValue(':instrument_id', $instrumentId);
        $stmt->execute();
        $isin = $stmt->fetchColumn();
        
        if (!$isin) return;
        
        // Check if this ISIN exists in new_companies
        $sql = "SELECT new_company_id FROM new_companies WHERE isin = :isin";
        $stmt = $this->portfolioDb->prepare($sql);
        $stmt->bindValue(':isin', $isin);
        $stmt->execute();
        $companyId = $stmt->fetchColumn();
        
        if (!$companyId) return;
        
        // Get yield data from KPI tables and update new_companies
        $table = $this->isNordicInstrument($instrumentId) ? 'kpi_nordic' : 'kpi_global';
        
        $yieldData = $this->getYieldDataForInstrument($instrumentId, $table);
        
        if (!empty($yieldData)) {
            $this->updateNewCompaniesRecord($companyId, $yieldData);
        }
    }
    
    /**
     * Get all yield data for an instrument
     */
    private function getYieldDataForInstrument($instrumentId, $table) {
        $sql = "
            SELECT group_period, calculation, numeric_value
            FROM {$table}
            WHERE instrument_id = :instrument_id AND kpi_id = :kpi_id
        ";
        
        $stmt = $this->marketdataDb->prepare($sql);
        $stmt->execute([
            ':instrument_id' => $instrumentId,
            ':kpi_id' => $this->yieldKpiId
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert to associative array
        $yieldData = [];
        foreach ($results as $row) {
            $key = $row['group_period'] . '_' . $row['calculation'];
            $yieldData[$key] = $row['numeric_value'];
        }
        
        return $yieldData;
    }
    
    /**
     * Update new_companies record with yield data
     */
    private function updateNewCompaniesRecord($companyId, $yieldData) {
        $updateFields = [];
        $params = [':company_id' => $companyId];
        
        // Map KPI data to new_companies fields
        $fieldMapping = [
            'last_latest' => 'yield_current',
            '1year_mean' => 'yield_1y_avg',
            '1year_cagr' => 'yield_1y_cagr',
            '3year_mean' => 'yield_3y_avg',
            '3year_cagr' => 'yield_3y_cagr',
            '5year_mean' => 'yield_5y_avg',
            '5year_cagr' => 'yield_5y_cagr',
            '10year_mean' => 'yield_10y_avg',
            '10year_cagr' => 'yield_10y_cagr'
        ];
        
        foreach ($fieldMapping as $kpiKey => $dbField) {
            if (isset($yieldData[$kpiKey])) {
                $updateFields[] = "{$dbField} = :{$dbField}";
                $params[":{$dbField}"] = $yieldData[$kpiKey];
            }
        }
        
        if (!empty($updateFields)) {
            $updateFields[] = "yield_data_updated_at = NOW()";
            $updateFields[] = "yield_source = 'borsdata'";
            
            $sql = "UPDATE new_companies SET " . implode(', ', $updateFields) . " WHERE new_company_id = :company_id";
            
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->execute($params);
            
            echo "Updated yield data for company ID {$companyId}\n";
        }
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $fetcher = new YieldDataFetcher();
    $fetcher->fetchAllYieldData();
}
?>