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
    
    // Read file and split into lines
    $content = file_get_contents($file['tmp_name']);
    $lines = preg_split('/\r\n|\r|\n/', $content);
    
    $dividendData = [];
    $warnings = [];
    $errors = [];
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Skip any line that looks like a header (contains text like "payment_date")
        if (stripos($line, 'payment_date') !== false || stripos($line, 'isin') !== false) {
            continue;
        }
        
        // Split by any whitespace (tabs, spaces, multiple spaces)
        $parts = preg_split('/\s+/', $line);
        
        // We expect at least 6 parts: date, isin, shares, dividend_local, tax_local, currency
        if (count($parts) >= 6) {
            
            // Map parts to our expected format
            $dividend = [
                'payment_date' => $parts[0],
                'isin' => $parts[1],
                'shares_held' => floatval(str_replace(',', '.', $parts[2])),
                'dividend_amount_local' => floatval(str_replace(',', '.', $parts[3])),
                'tax_amount_local' => floatval(str_replace(',', '.', $parts[4])),
                'currency_local' => $parts[5],
                'dividend_amount_sek' => isset($parts[6]) ? floatval(str_replace(',', '.', $parts[6])) : 0,
                'net_dividend_sek' => isset($parts[7]) ? floatval(str_replace(',', '.', $parts[7])) : 0,
                'exchange_rate_used' => isset($parts[8]) ? floatval(str_replace(',', '.', $parts[8])) : 1
            ];
            
            // Validate essential fields
            if (empty($dividend['payment_date']) || empty($dividend['isin'])) {
                $errors[] = "Line " . ($lineNum + 1) . ": Missing payment date or ISIN";
                continue;
            }
            
            if ($dividend['shares_held'] <= 0) {
                $errors[] = "Line " . ($lineNum + 1) . ": Invalid share count";
                continue;
            }
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dividend['payment_date'])) {
                $errors[] = "Line " . ($lineNum + 1) . ": Invalid date format (expected YYYY-MM-DD)";
                continue;
            }
            
            // Validate ISIN
            if (strlen($dividend['isin']) !== 12) {
                $warnings[] = "Line " . ($lineNum + 1) . ": ISIN should be 12 characters";
            }
            
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
                    $warnings[] = "Line " . ($lineNum + 1) . ": Company not found in masterlist for ISIN " . $dividend['isin'];
                    $dividend['company_name'] = 'Unknown Company';
                    $dividend['ticker'] = '';
                }
            } catch (Exception $e) {
                $warnings[] = "Line " . ($lineNum + 1) . ": Database lookup failed: " . $e->getMessage();
                $dividend['company_name'] = 'Unknown';
                $dividend['ticker'] = '';
            }
            
            // Calculate missing values
            if ($dividend['dividend_amount_sek'] == 0 && $dividend['dividend_amount_local'] > 0) {
                $dividend['dividend_amount_sek'] = $dividend['dividend_amount_local'] * $dividend['exchange_rate_used'];
            }
            
            if ($dividend['net_dividend_sek'] == 0 && $dividend['dividend_amount_sek'] > 0) {
                $dividend['net_dividend_sek'] = $dividend['dividend_amount_sek'] - ($dividend['tax_amount_local'] * $dividend['exchange_rate_used']);
            }
            
            // Calculate tax in SEK
            $dividend['tax_amount_sek'] = $dividend['dividend_amount_sek'] - $dividend['net_dividend_sek'];
            
            // Mark completeness
            $dividend['is_complete'] = ($dividend['dividend_amount_sek'] > 0) ? 1 : 0;
            
            $dividendData[] = $dividend;
        } else {
            $warnings[] = "Line " . ($lineNum + 1) . ": Insufficient data columns (found " . count($parts) . ", need at least 6)";
        }
    }
    
    echo json_encode([
        'success' => true,
        'total_rows' => count($dividendData),
        'errors' => $errors,
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