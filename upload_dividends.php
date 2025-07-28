<?php
header('Content-Type: application/json');
session_start();

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'src/config/BrokerCsvConfig.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    if (!isset($_FILES['csv_file']) || !isset($_POST['broker_id'])) {
        throw new Exception('Missing file or broker_id');
    }
    
    $brokerId = $_POST['broker_id'];
    $uploadedFile = $_FILES['csv_file'];
    
    // Validate file
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }
    
    // Get broker config
    $brokerConfig = BrokerCsvConfig::getBrokerConfig($brokerId);
    if (!$brokerConfig) {
        throw new Exception('Invalid broker configuration');
    }
    
    // Simple CSV/Excel parsing for your format
    $filePath = $uploadedFile['tmp_name'];
    $fileName = $uploadedFile['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    $dividendData = [];
    
    if ($fileExt === 'csv') {
        // Parse CSV with tab delimiter
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle, 0, "\t"); // Skip header row with tab delimiter
        
        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            // Debug: log the raw row
            error_log("Raw CSV row: " . print_r($row, true));
            
            if (count($row) >= 4) { // Minimum required columns
                // Based on your data: payment_date, isin, shares_held, ???, dividend_amount_local, currency_local, dividend_amount_sek, net_dividend_sek, exchange_rate_used
                $dividendData[] = [
                    'payment_date' => trim($row[0]),
                    'isin' => trim($row[1]), 
                    'shares_held' => floatval(str_replace(',', '.', trim($row[2]))),
                    'dividend_amount_local' => floatval(str_replace(',', '.', trim($row[4]))), // Skip row[3], use row[4]
                    'tax_amount_local' => 0, // Calculate later: dividend_sek - net_sek
                    'currency_local' => isset($row[5]) ? trim($row[5]) : 'SEK',
                    'dividend_amount_sek' => isset($row[6]) ? floatval(str_replace(',', '.', trim($row[6]))) : 0,
                    'net_dividend_sek' => isset($row[7]) ? floatval(str_replace(',', '.', trim($row[7]))) : 0,
                    'exchange_rate_used' => isset($row[8]) ? floatval(str_replace(',', '.', trim($row[8]))) : 1
                ];
            }
        }
        fclose($handle);
        
    } elseif ($fileExt === 'xlsx') {
        // For Excel files, we'd need a library like PhpSpreadsheet
        // For now, return a message to convert to CSV
        throw new Exception('Please convert Excel file to CSV format for now');
    }
    
    // Validate and enrich data
    $errors = [];
    $warnings = [];
    $processedData = [];
    
    foreach ($dividendData as $index => $dividend) {
        $rowNum = $index + 2; // +1 for 0-based, +1 for header
        
        // Validate required fields
        if (empty($dividend['payment_date']) || empty($dividend['isin']) || $dividend['shares_held'] <= 0) {
            $errors[] = "Row $rowNum: Missing required data (date, ISIN, or shares)";
            continue;
        }
        
        // Validate ISIN format
        if (strlen($dividend['isin']) !== 12) {
            $warnings[] = "Row $rowNum: ISIN length is not 12 characters";
        }
        
        // Look up company name from masterlist
        try {
            $foundationDb = Database::getConnection('foundation');
            $stmt = $foundationDb->prepare("SELECT name, ticker FROM masterlist WHERE isin = ?");
            $stmt->execute([$dividend['isin']]);
            $company = $stmt->fetch();
            
            if ($company) {
                $dividend['company_name'] = $company['name'];
                $dividend['ticker'] = $company['ticker'];
            } else {
                $warnings[] = "Row $rowNum: Company not found in masterlist for ISIN " . $dividend['isin'];
                $dividend['company_name'] = 'Unknown';
                $dividend['ticker'] = '';
            }
        } catch (Exception $e) {
            $warnings[] = "Row $rowNum: Could not lookup company for ISIN " . $dividend['isin'];
            $dividend['company_name'] = 'Unknown';
            $dividend['ticker'] = '';
        }
        
        // Calculate tax_amount_sek if not provided
        if ($dividend['tax_amount_local'] == 0 && $dividend['dividend_amount_sek'] > 0 && $dividend['net_dividend_sek'] > 0) {
            $dividend['tax_amount_sek'] = $dividend['dividend_amount_sek'] - $dividend['net_dividend_sek'];
            $dividend['tax_amount_local'] = $dividend['tax_amount_sek'] / ($dividend['exchange_rate_used'] ?: 1);
        }
        
        $processedData[] = $dividend;
    }
    
    echo json_encode([
        'success' => true,
        'total_rows' => count($processedData),
        'errors' => $errors,
        'warnings' => $warnings,
        'preview_data' => array_slice($processedData, 0, 10), // First 10 rows for preview
        'data' => $processedData // Full data for import
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>