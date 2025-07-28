<?php
header('Content-Type: application/json');

require_once 'config/config.php';
require_once 'config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    if (!isset($_FILES['csv_file'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }
    
    // Read the entire file as text and split by lines
    $content = file_get_contents($file['tmp_name']);
    $lines = preg_split('/\r\n|\r|\n/', $content);
    
    $dividendData = [];
    $warnings = [];
    
    // Skip the first line (header) and process data lines
    for ($i = 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;
        
        // Split by tabs (or multiple spaces/tabs)
        $parts = preg_split('/\s+/', $line);
        
        if (count($parts) >= 4) {
            $dividend = [
                'payment_date' => $parts[0] ?? '',
                'isin' => $parts[1] ?? '',
                'shares_held' => floatval(str_replace(',', '.', $parts[2] ?? '0')),
                'dividend_amount_local' => floatval(str_replace(',', '.', $parts[3] ?? '0')),
                'tax_amount_local' => floatval(str_replace(',', '.', $parts[4] ?? '0')),
                'currency_local' => $parts[5] ?? 'SEK',
                'dividend_amount_sek' => floatval(str_replace(',', '.', $parts[6] ?? '0')),
                'net_dividend_sek' => floatval(str_replace(',', '.', $parts[7] ?? '0')),
                'exchange_rate_used' => floatval(str_replace(',', '.', $parts[8] ?? '1'))
            ];
            
            // Basic validation
            if (!empty($dividend['payment_date']) && !empty($dividend['isin']) && $dividend['shares_held'] > 0) {
                
                // Look up company from masterlist
                try {
                    $foundationDb = Database::getConnection('foundation');
                    $stmt = $foundationDb->prepare("SELECT name, ticker FROM masterlist WHERE isin = ?");
                    $stmt->execute([$dividend['isin']]);
                    $company = $stmt->fetch();
                    
                    if ($company) {
                        $dividend['company_name'] = $company['name'];
                        $dividend['ticker'] = $company['ticker'];
                    } else {
                        $warnings[] = "Row " . ($i + 1) . ": Company not found in masterlist for ISIN " . $dividend['isin'];
                        $dividend['company_name'] = 'Unknown Company';
                        $dividend['ticker'] = '';
                    }
                } catch (Exception $e) {
                    $warnings[] = "Row " . ($i + 1) . ": Could not lookup company: " . $e->getMessage();
                    $dividend['company_name'] = 'Unknown Company';
                    $dividend['ticker'] = '';
                }
                
                // Calculate tax if needed
                if ($dividend['tax_amount_local'] == 0 && $dividend['dividend_amount_sek'] > $dividend['net_dividend_sek']) {
                    $dividend['tax_amount_sek'] = $dividend['dividend_amount_sek'] - $dividend['net_dividend_sek'];
                } else {
                    $dividend['tax_amount_sek'] = $dividend['tax_amount_local'] * $dividend['exchange_rate_used'];
                }
                
                // Mark as complete or incomplete
                $dividend['is_complete'] = 1;
                if ($dividend['dividend_amount_sek'] <= 0) {
                    $dividend['is_complete'] = 0;
                    $warnings[] = "Row " . ($i + 1) . ": Missing SEK amount";
                }
                
                $dividendData[] = $dividend;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'total_rows' => count($dividendData),
        'errors' => [],
        'warnings' => $warnings,
        'preview_data' => $dividendData,
        'broker_id' => 'minimal'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>